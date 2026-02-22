from flask import Flask, request, jsonify
import pickle

app = Flask(__name__)

# Chargement du modèle entraîné au démarrage
try:
    with open('cardio_model.pkl', 'rb') as f:
        model = pickle.load(f)
    print("✅ IA chargée et prête.")
except FileNotFoundError:
    print("❌ Erreur : Lancez d'abord 'train_model.py' !")
    model = None

@app.route('/analyze_message', methods=['POST'])
def analyze():
    if not model:
        return jsonify({"error": "Model not loaded"}), 500

    data = request.json
    content = data.get('content', '')

    # Prédiction avec le modèle
    prediction = model.predict([content])[0]
    
    # On peut aussi récupérer les probabilités (confiance)
    proba = model.predict_proba([content]).max()

    return jsonify({
        "classification": prediction, # URGENT, ADMIN, ou NORMAL
        "confidence": round(proba * 100, 2)
    })

if __name__ == '__main__':
    app.run(port=5001, debug=True)