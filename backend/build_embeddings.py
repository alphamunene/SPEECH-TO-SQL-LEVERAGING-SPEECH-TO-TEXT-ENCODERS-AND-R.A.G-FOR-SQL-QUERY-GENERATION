# backend/build_embeddings.py
import os
import pickle
from sentence_transformers import SentenceTransformer
import faiss

# Example: sample SQL-related texts (can be table names, schema docs, example queries)
documents = [
    "Get all employees from the employees table",
    "List all orders where amount > 100",
    "Find customer names and addresses",
    "Show sales grouped by region",
]

# Load embedding model
model = SentenceTransformer("all-MiniLM-L6-v2")

# Encode
embeddings = model.encode(documents)

# Save embeddings in FAISS
dimension = embeddings.shape[1]
index = faiss.IndexFlatL2(dimension)
index.add(embeddings)

# Persist
faiss.write_index(index, "backend/vector.index")
with open("backend/documents.pkl", "wb") as f:
    pickle.dump(documents, f)

print("✅ Embeddings built and saved to backend/vector.index")
