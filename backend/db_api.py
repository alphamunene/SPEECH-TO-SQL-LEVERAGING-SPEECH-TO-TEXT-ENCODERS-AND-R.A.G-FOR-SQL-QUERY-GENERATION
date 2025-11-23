from fastapi import APIRouter, HTTPException
import os, shutil, traceback, sqlite3, re
from pathlib import Path
from pydantic import BaseModel
from typing import Optional
router = APIRouter()

BACKEND_DIR = Path(__file__).parent
DATA_DB = BACKEND_DIR / "current_data.db"

from . import rag, sql_exec
import openai

try:
    import mysql.connector
    MYSQL_CONNECTOR_AVAILABLE = True
except Exception as e:
    mysql = None
    MYSQL_CONNECTOR_AVAILABLE = False
    MYSQL_IMPORT_ERROR = str(e)

class ConnPayload(BaseModel):
    conn_str: str

def parse_conn_string(s: str):
    s = s.strip()
    if s.startswith("sqlite:///"):
        return {"scheme": "sqlite", "params": {"path": s.replace("sqlite:///","")}}
    if s.endswith(".db") or s.endswith(".sqlite") or os.path.exists(s):
        return {"scheme": "sqlite", "params": {"path": s}}
    m = re.match(r"mysql:\/\/(?P<user>[^:\/]+):(?P<pass>[^@\/]+)@(?P<host>[^:\/]+)(:(?P<port>\d+))?\/(?P<db>.+)", s)
    if m:
        return {
            "scheme": "mysql",
            "params": {
                "host": m.group("host"),
                "port": int(m.group("port")) if m.group("port") else 3306,
                "user": m.group("user"),
                "password": m.group("pass"),
                "database": m.group("db")
            }
        }
    kvs = {}
    parts = re.split(r'[;,\|]+', s)
    for p in parts:
        if '=' in p:
            k,v = p.split('=',1)
            kvs[k.strip().lower()] = v.strip()
    if kvs:
        if 'host' in kvs or 'user' in kvs:
            if 'database' in kvs:
                return {"scheme": "mysql", "params": {
                    "host": kvs.get('host','localhost'),
                    "port": int(kvs.get('port', 3306)),
                    "user": kvs.get('user'),
                    "password": kvs.get('password'),
                    "database": kvs.get('database')
                }}
            elif 'path' in kvs:
                return {"scheme": "sqlite", "params": {"path": kvs.get('path')}}
    raise ValueError("Unrecognized connection string format")

def copy_mysql_to_sqlite(mysql_conf: dict, sqlite_path: str, max_rows_per_table: Optional[int]=None):
    if not MYSQL_CONNECTOR_AVAILABLE:
        raise RuntimeError(f"mysql.connector not available. Install 'mysql-connector-python'. Import error: {MYSQL_IMPORT_ERROR}")

    conn = mysql.connector.connect(
        host = mysql_conf.get("host", "localhost"),
        port = int(mysql_conf.get("port", 3306)),
        user = mysql_conf.get("user"),
        password = mysql_conf.get("password"),
        database = mysql_conf.get("database")
    )
    cursor = conn.cursor()
    cursor.execute("SHOW TABLES")
    rows = cursor.fetchall()
    table_names = [r[0] for r in rows]

    dest_dir = os.path.dirname(sqlite_path)
    if dest_dir and not os.path.exists(dest_dir):
        os.makedirs(dest_dir, exist_ok=True)
    if os.path.exists(sqlite_path):
        os.remove(sqlite_path)

    sconn = sqlite3.connect(sqlite_path)
    scur = sconn.cursor()
    try:
        for t in table_names:
            cursor.execute(f"SELECT * FROM `{t}` LIMIT 0")
            colnames = [d[0] for d in cursor.description] if cursor.description else []
            if not colnames:
                continue
            col_defs = ", ".join([f"'{c}' TEXT" for c in colnames])
            scur.execute(f"CREATE TABLE IF NOT EXISTS '{t}' ({col_defs});")

            fetch_sql = f"SELECT * FROM `{t}`"
            if max_rows_per_table:
                fetch_sql += f" LIMIT {int(max_rows_per_table)}"
            cursor.execute(fetch_sql)
            all_rows = cursor.fetchall()
            if not all_rows:
                continue

            placeholders = ", ".join(["?"] * len(colnames))
            insert_sql = f"INSERT INTO '{t}' ({', '.join(['`'+c+'`' for c in colnames])}) VALUES ({placeholders});"
            scur.executemany(insert_sql, all_rows)
            sconn.commit()
    finally:
        scur.close()
        sconn.close()
        cursor.close()
        conn.close()

    return table_names

# ✅ No `/api` in endpoint paths anymore

@router.post("/connect_db")
async def connect_db(payload: ConnPayload):
    conn_str = payload.conn_str.strip()
    if not conn_str:
        raise HTTPException(status_code=400, detail="No connection string provided.")
    try:
        parsed = parse_conn_string(conn_str)
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Could not parse connection string: {e}")
    try:
        if parsed["scheme"] == "sqlite":
            path = parsed["params"]["path"]
            if not os.path.exists(path):
                raise HTTPException(status_code=400, detail="SQLite file path not found on server.")
            if DATA_DB.exists():
                DATA_DB.unlink()
            shutil.copyfile(path, DATA_DB)
            return {"message": "Connected to provided SQLite DB file.", "db": str(DATA_DB)}

        elif parsed["scheme"] == "mysql":
            mysql_conf = parsed["params"]
            tables = copy_mysql_to_sqlite(mysql_conf, str(DATA_DB))
            return {"message": "Connected to MySQL and copied to local SQLite.", "tables": tables}
        else:
            raise HTTPException(status_code=400, detail="Unsupported connection scheme.")
    except Exception as e:
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/rebuild_schema_index")
async def rebuild_index():
    if not DATA_DB.exists():
        raise HTTPException(status_code=400, detail="No active DB file (current_data.db). Set connection first.")
    try:
        res = rag.build_index(str(DATA_DB))
        return {"message":"schema_index_built", "detail": res}
    except Exception as e:
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=str(e))

class NLQuery(BaseModel):
    query: str
    max_schema_hits: Optional[int] = 5

@router.post("/nl_to_sql")
async def nl_to_sql(payload: NLQuery):
    q = payload.query.strip()
    if not q:
        raise HTTPException(status_code=400, detail="Empty query provided.")
    schema_hits = rag.query_schema(q, k=payload.max_schema_hits or 5)
    context_texts = [h['doc']['text'] for h in schema_hits]
    context_block = "\n".join(context_texts)
    prompt = f"""You are given the database schema context below, and a user question. Generate a single SQL query (SQLite dialect) that answers the question.

SCHEMA CONTEXT:
{context_block}

USER QUESTION:
{q}

SQL (SQLite):"""
    api_key = os.environ.get("OPENAI_API_KEY")
    if not api_key:
        return {"prompt": prompt, "schema_hits": schema_hits}
    openai.api_key = api_key
    try:
        resp = openai.Completion.create(model="text-davinci-003", prompt=prompt, max_tokens=256, temperature=0)
        sql = resp.choices[0].text.strip()
        return {"sql": sql, "schema_hits": schema_hits}
    except Exception as e:
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/execute_sql")
async def execute_sql(payload: dict):
    sql = payload.get("sql","").strip()
    if not sql:
        raise HTTPException(status_code=400, detail="No SQL provided.")
    lowered = sql.lower().lstrip()
    if not lowered.startswith(("select","pragma","with")):
        raise HTTPException(status_code=400, detail="Only read-only SELECT/PRAGMA queries are allowed.")
    try:
        rows, cols = sql_exec.execute_sql(sql)
        return {"rows": rows, "columns": cols}
    except Exception as e:
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=str(e))
