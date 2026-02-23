from fastapi import FastAPI
from transformers import pipeline
import uvicorn
import sys

app = FastAPI()

# Modele alternatif très stable et léger (distilbert multilingue)
model_name = "lxyuan/distilbert-base-multilingual-cased-sentiments-student"

print(f"Chargement du modele : {model_name}")

try:
    # On charge le pipeline de sentiment (plus simple d'accès)
    classifier = pipeline("sentiment-analysis", model=model_name)
    print("MODELE PRET")
except Exception as e:
    print(f"Erreur au chargement : {str(e)}")
    classifier = None

@app.post("/predict")
async def predict(data: dict):
    if classifier is None:
        return {"is_toxic": False, "score": 0.0, "label": "error"}

    text = data.get('text', "")
    result = classifier(text)[0]
    
    label = result['label'] # 'negative', 'neutral' ou 'positive'
    score = result['score']

    # Un message est considéré toxique ici s'il est "negative" avec un haut score
    # Ce modèle est très bon pour "Merci" (positive) vs "Idiot" (negative)
    is_toxic = True if (label.lower() == "negative" and score > 0.80) else False

    print(f"Texte: {text} | Label: {label} | Score: {score:.4f}")
    
    return {
        "is_toxic": is_toxic,
        "score": float(score),
        "label": label.lower().strip()
    }

if __name__ == "__main__":
    uvicorn.run(app, host="127.0.0.1", port=8001)
