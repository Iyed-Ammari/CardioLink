from transformers import pipeline

# Crée un pipeline de modération de texte
moderation = pipeline("text-classification", model="facebook/roberta-hate-speech")

# Texte à tester
texte = "Je vais te tuer"  # ou input() pour lire dynamiquement

result = moderation(texte)[0]

label = result['label']
score = result['score']

if label != "LABEL_0" and score > 0.6:  # seuil 60%
    print("⚠ Commentaire BLOQUÉ (toxique)")
else:
    print("✅ Commentaire OK")
