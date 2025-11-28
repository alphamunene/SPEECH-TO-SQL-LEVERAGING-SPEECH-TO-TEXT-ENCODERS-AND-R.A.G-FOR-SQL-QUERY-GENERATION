import json
import pickle
from pathlib import Path
from datetime import datetime
import shutil
import uuid
import difflib
import re
import soundfile as sf
import numpy as np
import torch
import faiss
import numpy as np
import whisper
import io

from fastapi import FastAPI, Request, UploadFile, File, Header, HTTPException
from fastapi.responses import HTMLResponse, JSONResponse
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from sqlalchemy import create_engine, text, inspect
from transformers import AutoModelForSeq2SeqLM, AutoTokenizer
from sentence_transformers import SentenceTransformer
import uvicorn

# ======================================================
# MODEL SETUP
# ======================================================
MODEL_PATH = "trainedmodel"

tokenizer = AutoTokenizer.from_pretrained(MODEL_PATH)
model = AutoModelForSeq2SeqLM.from_pretrained(MODEL_PATH)
model.eval()

embedder = SentenceTransformer("all-MiniLM-L6-v2")
model_whisper = whisper.load_model("base")

BASE_DIR = Path("rag_store")
BASE_DIR.mkdir(exist_ok=True)
INDEX_PATH = BASE_DIR / "schema_index.faiss"
SCHEMA_PATH = BASE_DIR / "schemas.pkl"

# ======================================================
# FASTAPI SETUP
# ======================================================
app = FastAPI(title="Speech2SQL")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.mount("/static", StaticFiles(directory="static"), name="static")
templates = Jinja2Templates(directory="templates")

engine = None
DB_URL = None
index = None
schemas = []

# ======================================================
# HISTORY DATABASE
# ======================================================
HISTORY_DB = "history.db"
history_engine = create_engine(f"sqlite:///{HISTORY_DB}", echo=False)

with history_engine.connect() as conn:
    conn.execute(text("""
        CREATE TABLE IF NOT EXISTS history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            query TEXT NOT NULL,
            result TEXT NOT NULL,
            created_at TEXT NOT NULL
        )
    """))
    conn.commit()

# ======================================================
# HELPER FUNCTIONS
# ======================================================
def generate_sql(prompt: str) -> str:
    inputs = tokenizer(prompt, return_tensors="pt", truncation=True, max_length=512)
    with torch.no_grad():
        output = model.generate(
            **inputs,
            max_length=256,
            num_beams=5,
            no_repeat_ngram_size=2
        )
    return tokenizer.decode(output[0], skip_special_tokens=True)

def fuzzy_match(word, options):
    word_clean = word.lower().replace(" ", "")
    normalized = [o.lower().replace(" ", "") for o in options]
    match = difflib.get_close_matches(word_clean, normalized, n=1, cutoff=0.7)
    if match:
        idx = normalized.index(match[0])
        return options[idx]
    return None

# ======================================================
# CONNECT TO DATABASE
# ======================================================
@app.post("/api/connect_db")
async def connect_db(request: Request):
    global engine, DB_URL, schemas, index

    data = await request.json()
    DB_URL = data.get("conn_str", "").strip()

    if not DB_URL:
        return JSONResponse(status_code=400, content={"detail": "Missing DB connection string"})

    if DB_URL.endswith(".db"):
        DB_URL = f"sqlite:///{DB_URL}"
    elif DB_URL.startswith("mysql://"):
        DB_URL = DB_URL.replace("mysql://", "mysql+pymysql://")
    elif DB_URL.startswith("postgres://"):
        DB_URL = DB_URL.replace("postgres://", "postgresql+psycopg2://")

    try:
        engine = create_engine(DB_URL, pool_pre_ping=True)

        with engine.connect() as conn:
            conn.execute(text("SELECT 1"))
            inspector = inspect(engine)
            tables = inspector.get_table_names()

        schemas = []
        for table in tables:
            cols = inspector.get_columns(table)
            schemas.append(f"table {table} ({', '.join([c['name'] for c in cols])})")

        schema_embeddings = embedder.encode(schemas)
        dim = schema_embeddings.shape[1]
        index = faiss.IndexFlatL2(dim)
        index.add(np.array(schema_embeddings))
        faiss.write_index(index, str(INDEX_PATH))

        with open(SCHEMA_PATH, "wb") as f:
            pickle.dump(schemas, f)

        return {"message": "✅ Connected successfully", "tables": schemas}

    except Exception as e:
        return JSONResponse(status_code=500, content={"detail": str(e)})

# ======================================================
# VIEW SCHEMA WITH KEYS & SAMPLE DATA
# ======================================================
@app.get("/api/view_schema")
async def view_schema():
    if engine is None:
        return {"error": "No database connected ❌"}

    inspector = inspect(engine)
    output = {}

    for table_name in inspector.get_table_names():
        cols = inspector.get_columns(table_name)
        column_details = [f"{c['name']} ({str(c['type'])})" for c in cols]

        pkeys = inspector.get_pk_constraint(table_name).get("constrained_columns", [])

        fk_data = inspector.get_foreign_keys(table_name)
        fkeys = [
            f"{', '.join(fk['constrained_columns'])} → {fk['referred_table']}({', '.join(fk['referred_columns'])})"
            for fk in fk_data
        ]

        try:
            with engine.connect() as conn:
                rows = conn.execute(text(f"SELECT * FROM {table_name} LIMIT 5")).fetchall()
            sample_rows = [dict(r._mapping) for r in rows]
        except Exception:
            sample_rows = []

        output[table_name] = {
            "columns": column_details,
            "primary_keys": pkeys,
            "foreign_keys": fkeys,
            "example_rows": sample_rows
        }

    return {"schema": output}

# ======================================================
# SHOW TABLES QUICK VIEW
# ======================================================
@app.get("/api/show-tables")
async def show_tables(limit: int = 5):
    if not engine:
        return {"detail": "Database not connected ❌"}

    tables_data = []

    try:
        inspector = inspect(engine)
        tables = inspector.get_table_names()

        with engine.connect() as conn:
            for table in tables:
                result = conn.execute(text(f"SELECT * FROM {table} LIMIT :n"), {"n": limit})
                cols = result.keys()
                rows = [dict(r._mapping) for r in result]
                tables_data.append({"table": table, "columns": list(cols), "rows": rows})

        return {"tables": tables_data}

    except Exception as e:
        return JSONResponse(status_code=500, content={"detail": str(e)})

# ======================================================
# PREVIEW LLM SQL
# ======================================================
@app.post("/api/view_sql")
async def preview_sql(request: Request):
    data = await request.json()
    nl_query = data.get("query")
    if not nl_query:
        return {"detail": "Missing query"}

    relevant_schema = schemas[:3]
    prompt = f"Generate SQL for: {nl_query}\nSchema:\n" + "\n".join(relevant_schema)

    sql_query = generate_sql(prompt)
    return {"nl_query": nl_query, "sql_preview": sql_query}

# ======================================================
# NATURAL LANGUAGE → SQL EXECUTION WITH RAG
# ======================================================
@app.post("/api/query_nl")
async def query_nl(request: Request):
    if not engine:
        return {"detail": "Database not connected"}

    data = await request.json()
    nl_query = data.get("query", "").strip()

    if not nl_query:
        return {"detail": "No query provided"}

    raw = nl_query.lower()
    inspector = inspect(engine)
    tables = inspector.get_table_names()

    # ---- If user wants schema ----
    if any(k in raw for k in ["schema", "structure", "blueprint"]):
        return await view_schema()

    # ---- If user wants list of tables ----
    if any(k in raw for k in ["show", "display", "list"]) and "table" in raw:
        return {"status": "ok", "tables": tables}

    # ---- Detect table via fuzzy match ----
    detected_table = None
    for word in raw.split():
        possible = fuzzy_match(word, tables)
        if possible:
            detected_table = possible
            break

    # ---- RAG retrieval for schema context ----
    if schemas and index:
        query_embedding = embedder.encode([nl_query])
        D, I = index.search(np.array(query_embedding), k=3)  # top 3 matches
        relevant_schema = [schemas[i] for i in I[0]]
    else:
        relevant_schema = schemas[:3]

    # ======================================================
    # EXECUTE SQL IF TABLE DETECTED
    # ======================================================
    if detected_table:
        cols_db = [c["name"] for c in inspector.get_columns(detected_table)]
        raw_clean = raw.replace(" ", "")

        matched_cols = []
        for col in cols_db:
            if col.replace(" ", "").lower() in raw_clean:
                matched_cols.append(col)

        # Fuzzy column match
        if not matched_cols:
            for col in cols_db:
                if difflib.SequenceMatcher(None, col.lower(), raw.replace(" ", "").lower()).ratio() > 0.65:
                    matched_cols.append(col)

        if not matched_cols:
            matched_cols = cols_db  # default all columns

        # Detect conditions like <50, >30, =10
        condition = ""
        pattern = r"(<|>|=)\s*([0-9]+)"
        match = re.search(pattern, raw)
        if match:
            operator, value = match.groups()
            condition = f"WHERE {matched_cols[0]} {operator} {value}"

        sql_query = f"SELECT {', '.join(matched_cols)} FROM `{detected_table}` {condition}".strip()

        with engine.connect() as conn:
            result = conn.execute(text(sql_query))
            cols = result.keys()
            rows = [dict(r._mapping) for r in result]

        # Save to history
        with history_engine.connect() as conn:
            conn.execute(
                text("INSERT INTO history(query, result, created_at) VALUES(:q, :r, :t)"),
                {
                    "q": nl_query,
                    "r": json.dumps(rows),
                    "t": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                }
            )
            conn.commit()

        return {"status": "ok", "sql": sql_query, "columns": list(cols), "rows": rows}

    # ======================================================
    # FALLBACK → LLM WITH RAG
    # ======================================================
    prompt = f"User: {nl_query}\nSchema:\n" + "\n".join(relevant_schema)
    sql_query = generate_sql(prompt)

    try:
        with engine.connect() as conn:
            result = conn.execute(text(sql_query))
            cols = result.keys()
            rows = [dict(r._mapping) for r in result]
    except Exception as e:
        return {"error": "SQL execution failed", "reason": str(e), "sql": sql_query}

    with history_engine.connect() as conn:
        conn.execute(
            text("INSERT INTO history(query, result, created_at) VALUES(:q, :r, :t)"),
            {
                "q": nl_query,
                "r": json.dumps(rows),
                "t": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            }
        )
        conn.commit()

    return {"status": "ok", "sql": sql_query, "columns": list(cols), "rows": rows}

# ======================================================
# SPEECH → TEXT (WHISPER)
# ======================================================


@app.post("/api/upload_audio")
async def upload_audio(file: UploadFile = File(...)):
    try:
        # Read the uploaded file into memory
        audio_bytes = await file.read()
        audio_buffer = io.BytesIO(audio_bytes)

        # Use soundfile to read waveform
        data, samplerate = sf.read(audio_buffer)
        
        # Whisper expects float32
        if data.dtype != np.float32:
            data = data.astype(np.float32)
        
        # Convert to mono if stereo
        if len(data.shape) > 1:
            data = np.mean(data, axis=1)

        # Transcribe using Whisper
        result = model_whisper.transcribe(data, fp16=False, samplerate=samplerate)

        return {"transcript": result["text"]}

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Audio transcription failed: {e}")
# ======================================================
# HISTORY API
# ======================================================
@app.get("/api/history")
def get_history(page: int = 1, limit: int = 10):
    offset = (page - 1) * limit

    with history_engine.connect() as conn:
        rows = conn.execute(
            text("SELECT * FROM history ORDER BY id DESC LIMIT :limit OFFSET :offset"),
            {"limit": limit, "offset": offset}
        ).fetchall()

    return {
        "page": page,
        "limit": limit,
        "results": [
            {"id": r.id, "query": r.query, "result": r.result, "created_at": r.created_at} for r in rows
        ]
    }

@app.delete("/api/history/{history_id}")
def delete_history(history_id: int):
    with history_engine.connect() as conn:
        result = conn.execute(text("DELETE FROM history WHERE id=:id"), {"id": history_id})
        conn.commit()

    if result.rowcount == 0:
        raise HTTPException(status_code=404, detail="History entry not found")
    return {"status": "deleted", "id": history_id}

@app.delete("/api/history")
def delete_all_history():
    with history_engine.connect() as conn:
        result = conn.execute(text("DELETE FROM history"))
        conn.commit()
    return {"status": "all deleted", "deleted_rows": result.rowcount}

# ======================================================
# ADMIN PASSWORD
# ======================================================
ADMIN_PASSWORD = "admin"

# ======================================================
# UPLOAD A NEW DB FILE
# ======================================================
@app.post("/api/upload_db")
async def upload_db(file: UploadFile = File(...), x_admin_auth: str = Header(...)):
    """
    Upload a new SQLite database (.db or .sqlite), auto-connect, and rebuild schema index.
    """
    import logging
    logging.basicConfig(level=logging.INFO)

    if x_admin_auth != ADMIN_PASSWORD:
        raise HTTPException(status_code=403, detail="Invalid admin password")
    if not (file.filename.endswith(".db") or file.filename.endswith(".sqlite")):
        raise HTTPException(status_code=400, detail="Only .db or .sqlite files allowed")

    DB_DIR = BASE_DIR / "databases"
    DB_DIR.mkdir(exist_ok=True)

    global engine, DB_URL, schemas, index

    db_path = DB_DIR / file.filename
    try:
        # Save uploaded file
        with db_path.open("wb") as buffer:
            buffer.write(await file.read())
        logging.info(f"Database saved to {db_path}")

        # Auto-connect to the uploaded DB
        DB_URL = f"sqlite:///{db_path}"
        engine = create_engine(DB_URL, pool_pre_ping=True)
        logging.info(f"Connected to database: {DB_URL}")

        # Inspect tables and rebuild schema
        inspector = inspect(engine)
        tables = inspector.get_table_names()
        schemas = []
        for table in tables:
            cols = inspector.get_columns(table)
            schemas.append(f"table {table} ({', '.join([c['name'] for c in cols])})")
        logging.info(f"Tables found: {tables}")

        # Build FAISS index for RAG
        schema_embeddings = embedder.encode(schemas)
        dim = schema_embeddings.shape[1]
        index = faiss.IndexFlatL2(dim)
        index.add(np.array(schema_embeddings))
        faiss.write_index(index, str(INDEX_PATH))

        # Save schemas to pickle
        with open(SCHEMA_PATH, "wb") as f:
            pickle.dump(schemas, f)

        return {
            "message": f"Database '{file.filename}' uploaded and connected successfully",
            "tables": schemas
        }

    except Exception as e:
        logging.error(f"Failed to upload/connect DB: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to save/connect DB file: {e}")

# ======================================================
# REBUILD FAISS INDEX
# ======================================================
@app.post("/api/rebuild_schema_index")
async def rebuild_schema_index(x_admin_auth: str = Header(...)):
    global schemas, index, engine
    if x_admin_auth != ADMIN_PASSWORD:
        raise HTTPException(status_code=403, detail="Invalid admin password")
    if engine is None:
        raise HTTPException(status_code=400, detail="No database connected")

    inspector = inspect(engine)
    tables = inspector.get_table_names()

    schemas = []
    for table in tables:
        cols = inspector.get_columns(table)
        col_names = [c["name"] for c in cols]
        schemas.append(f"table {table} ({', '.join(col_names)})")

    with open(SCHEMA_PATH, "wb") as f:
        pickle.dump(schemas, f)

    schema_embeddings = embedder.encode(schemas)
    dim = schema_embeddings.shape[1]

    index = faiss.IndexFlatL2(dim)
    index.add(np.array(schema_embeddings))
    faiss.write_index(index, str(INDEX_PATH))

    return {"message": f"Schema index rebuilt for {len(schemas)} tables"}

    

# ======================================================
# FRONTEND
# ======================================================
@app.get("/", response_class=HTMLResponse)
def index(request: Request):
    return templates.TemplateResponse("index.html", {"request": request})

# ======================================================
# RUN APP
# ======================================================
if __name__ == "__main__":
    uvicorn.run("main:app", host="0.0.0.0", port=8000, reload=True)
