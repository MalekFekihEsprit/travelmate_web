from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer
import numpy as np
import faiss
import requests

app = Flask(__name__)
model = SentenceTransformer('all-MiniLM-L6-v2')
index = None
id_list = []
embs = None

def build_index(vectors: np.ndarray):
    global index
    d = vectors.shape[1]
    index = faiss.IndexFlatIP(d)
    faiss.normalize_L2(vectors)
    index.add(vectors)

@app.route('/index', methods=['POST'])
def index_documents():
    global id_list, embs
    payload = request.get_json(force=True)
    docs = payload.get('docs', [])
    texts = [str(d.get('text','')) for d in docs]
    ids = [d.get('id') for d in docs]

    if not texts:
        return jsonify({'error': 'no docs provided'}), 400

    vectors = model.encode(texts, convert_to_numpy=True, show_progress_bar=False)
    faiss.normalize_L2(vectors)

    id_list = ids
    embs = vectors
    build_index(vectors)

    return jsonify({'indexed': len(ids)})

@app.route('/recommend', methods=['POST'])
def recommend():
    global index, id_list
    if index is None:
        return jsonify({'error': 'index empty'}), 500

    payload = request.get_json(force=True)
    query = payload.get('query', '')
    k = int(payload.get('k', 6))

    if query == '':
        return jsonify({'error': 'empty query'}), 400

    qvec = model.encode([query], convert_to_numpy=True)
    faiss.normalize_L2(qvec)
    D, I = index.search(qvec, k)

    results = []
    for score, idx in zip(D[0].tolist(), I[0].tolist()):
        if idx < 0 or idx >= len(id_list):
            continue
        results.append({'id': id_list[idx], 'score': float(score)})

    return jsonify({'results': results})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
