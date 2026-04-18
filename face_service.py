from fastapi import FastAPI, File, UploadFile, HTTPException, Request
from fastapi.responses import JSONResponse
import numpy as np
import cv2
from deepface import DeepFace
import logging
import os
import json

logging.basicConfig(level=logging.INFO)
app = FastAPI(title="TravelMate Face Recognition Service")

MODEL_NAME = os.getenv("FACE_MODEL_NAME", "ArcFace")
PRIMARY_DETECTOR = os.getenv("FACE_DETECTOR", "retinaface")
FALLBACK_DETECTORS = ["retinaface", "mtcnn", "opencv"]

logging.info(f"Chargement du modèle {MODEL_NAME}...")
DeepFace.build_model(MODEL_NAME)
logging.info("Modèle chargé avec succès")


def decode_image(contents: bytes):
    nparr = np.frombuffer(contents, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    if img is None:
        raise HTTPException(status_code=400, detail="Image invalide")
    img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
    return img_rgb


def resize_if_needed(img_rgb):
    h, w = img_rgb.shape[:2]
    if h > 1200 or w > 1200:
        scale = 1200 / max(h, w)
        img_rgb = cv2.resize(img_rgb, (int(w * scale), int(h * scale)))
    return img_rgb


def validate_quality(img_rgb):
    gray = cv2.cvtColor(img_rgb, cv2.COLOR_RGB2GRAY)
    brightness = float(np.mean(gray))
    sharpness = float(cv2.Laplacian(gray, cv2.CV_64F).var())

    # Seuils beaucoup plus tolérants pour webcam laptop
    if brightness < 20:
        raise HTTPException(status_code=400, detail="Image trop sombre, rapprochez-vous d'une source de lumière")
    if sharpness < 12:
        raise HTTPException(status_code=400, detail="Image trop floue, stabilisez la caméra quelques secondes")


def extract_embedding_with_fallback(img_rgb):
    last_error = None

    for detector in [PRIMARY_DETECTOR] + [d for d in FALLBACK_DETECTORS if d != PRIMARY_DETECTOR]:
        try:
            representations = DeepFace.represent(
                img_path=img_rgb,
                model_name=MODEL_NAME,
                detector_backend=detector,
                enforce_detection=True,
                align=True
            )

            if not representations or len(representations) != 1:
                raise HTTPException(status_code=400, detail="Veuillez fournir une image avec un seul visage")

            embedding = representations[0]["embedding"]
            return embedding, detector

        except Exception as e:
            last_error = e
            logging.warning(f"Échec avec detector {detector}: {str(e)}")

    raise HTTPException(status_code=500, detail=f"Impossible d'extraire le visage: {str(last_error)}")


def normalize_embedding(embedding):
    v = np.array(embedding, dtype=np.float32)
    if np.linalg.norm(v) == 0:
        raise HTTPException(status_code=400, detail="Embedding invalide")
    v = v / np.linalg.norm(v)
    return v.tolist()


@app.get("/health")
async def health():
    return {"status": "ok", "model": MODEL_NAME, "detector": PRIMARY_DETECTOR}


@app.post("/extract")
async def extract_embedding(file: UploadFile = File(...)):
    try:
        contents = await file.read()
        img_rgb = decode_image(contents)
        img_rgb = resize_if_needed(img_rgb)
        validate_quality(img_rgb)

        embedding, detector_used = extract_embedding_with_fallback(img_rgb)
        embedding = normalize_embedding(embedding)

        return JSONResponse({
            "success": True,
            "embedding": embedding,
            "model": MODEL_NAME,
            "detector": detector_used
        })

    except HTTPException:
        raise
    except Exception as e:
        logging.exception("Erreur dans /extract")
        raise HTTPException(status_code=500, detail=f"Erreur extraction: {str(e)}")


@app.post("/enroll")
async def enroll_face(files: list[UploadFile] = File(...)):
    try:
        if len(files) < 2:
            raise HTTPException(status_code=400, detail="Au moins 2 captures sont requises pour l'enrôlement")

        embeddings = []

        for file in files:
            contents = await file.read()
            img_rgb = decode_image(contents)
            img_rgb = resize_if_needed(img_rgb)
            validate_quality(img_rgb)

            embedding, _ = extract_embedding_with_fallback(img_rgb)
            embeddings.append(np.array(embedding, dtype=np.float32))

        mean_embedding = np.mean(embeddings, axis=0)
        mean_embedding = np.array(normalize_embedding(mean_embedding.tolist()), dtype=np.float32)

        return JSONResponse({
            "success": True,
            "embedding": mean_embedding.tolist(),
            "captures_used": len(files),
            "model": MODEL_NAME
        })

    except HTTPException:
        raise
    except Exception as e:
        logging.exception("Erreur dans /enroll")
        raise HTTPException(status_code=500, detail=f"Erreur enrôlement: {str(e)}")


@app.post("/compare")
async def compare_embeddings(request: Request):
    try:
        body = await request.json()

        embedding1 = body.get("embedding1")
        embedding2 = body.get("embedding2")
        threshold = float(body.get("threshold", 0.68))

        if embedding1 is None or embedding2 is None:
            raise HTTPException(status_code=400, detail="embedding1 et embedding2 sont requis")

        v1 = np.array(embedding1, dtype=np.float32)
        v2 = np.array(embedding2, dtype=np.float32)

        if np.linalg.norm(v1) == 0 or np.linalg.norm(v2) == 0:
            raise HTTPException(status_code=400, detail="Embedding invalide")

        v1 = v1 / np.linalg.norm(v1)
        v2 = v2 / np.linalg.norm(v2)

        similarity = float(np.dot(v1, v2))

        return JSONResponse({
            "success": True,
            "similarity": similarity,
            "threshold": threshold,
            "is_match": similarity >= threshold
        })

    except HTTPException:
        raise
    except Exception as e:
        logging.exception("Erreur dans /compare")
        raise HTTPException(status_code=500, detail=f"Erreur comparaison: {str(e)}")


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("face_service:app", host="127.0.0.1", port=8000, reload=True)