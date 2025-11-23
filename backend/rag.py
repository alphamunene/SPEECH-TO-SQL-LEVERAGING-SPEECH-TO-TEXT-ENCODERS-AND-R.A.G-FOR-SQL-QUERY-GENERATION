# backend/rag.py
import os, sqlite3, pickle
from pathlib import Path
try:
    from sentence_transformers import SentenceTransformer
except Exception:
    SentenceTransformer = None
import numpy as np
BASE = Path(__file__).parent
INDEX_PATH = BASE / "schema.index.pkl"
MODEL_NAME = os.environ.get("RAG_EMBED_MODEL", "all-MiniLM-L6-v2")

def extract_schema(db_path):
    docs = []
    if not os.path.exists(db_path):
        return docs
    conn = sqlite3.connect(db_path)
    cur = conn.cursor()
    cur.execute("""SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';""")
    tables = [r[0] for r in cur.fetchall()]
    for t in tables:
        cur.execute(f"PRAGMA table_info('{t}')")
        cols = cur.fetchall()
        col_desc = ", ".join([f"{c[1]} ({c[2]})" for c in cols])
        doc = { "table": t, "columns": [{"name": c[1], "type": c[2]} for c in cols], "text": f"Table {t}: columns: {col_desc}" }
        docs.append(doc)
    conn.close()
    return docs

def _get_embedder():
    if SentenceTransformer is None:
        raise RuntimeError("SentenceTransformer not installed.")
    return SentenceTransformer(MODEL_NAME)

def build_index(db_path):
    docs = extract_schema(db_path)
    if not docs:
        with open(INDEX_PATH, 'wb') as f:
            pickle.dump({'docs':[], 'embeddings': None}, f)
        return {'status':'no_tables', 'count':0}
    embedder = _get_embedder()
    texts = [d['text'] for d in docs]
    embeddings = embedder.encode(texts, show_progress_bar=False)
    embeddings = np.array(embeddings, dtype=np.float32)
    with open(INDEX_PATH, 'wb') as f:
        pickle.dump({'docs':docs, 'embeddings':embeddings}, f)
    return {'status':'built', 'count': len(docs)}

def load_index():
    if not INDEX_PATH.exists():
        return {'docs':[], 'embeddings': None}
    with open(INDEX_PATH, 'rb') as f:
        return pickle.load(f)

def query_schema(nl_query, k=5):
    data = load_index()
    docs = data.get('docs', [])
    emb = data.get('embeddings', None)
    if not docs or emb is None:
        return []
    embedder = _get_embedder()
    qvec = embedder.encode([nl_query])[0].astype(np.float32)
    norms = (np.linalg.norm(emb, axis=1) * (np.linalg.norm(qvec) + 1e-12))
    sims = (emb @ qvec) / norms
    idx = np.argsort(-sims)[:k]
    results = []
    for i in idx:
        results.append({'score': float(sims[i]), 'doc': docs[i]})
    return results