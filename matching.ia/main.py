from fastapi import FastAPI
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer, util
import os

app = FastAPI()

# 1. Charger TON modèle que tu viens d'entraîner
model_path = "model_saved/mon_modele_ia"
model = SentenceTransformer(model_path)

class TextRequest(BaseModel):
    text: str

@app.post("/embed")
async def get_embedding(request: TextRequest):
    # Cette route transforme un texte en vecteur (nombres)
    vector = model.encode(request.text).tolist()
    return {"vector": vector}

@app.get("/")
def home():
    return {"status": "L'IA de CardioLink est en ligne !"}