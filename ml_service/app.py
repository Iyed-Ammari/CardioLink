from flask import Flask, request, jsonify
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
import numpy as np
from flask_cors import CORS
from sklearn.linear_model import LogisticRegression
from sklearn.preprocessing import StandardScaler

# ✅ IMPORTS reconnaissance faciale
from deepface import DeepFace
import base64
import os
import tempfile

app = Flask(__name__)
CORS(app) 

# ==================== ROUTES EXISTANTES ====================

@app.route('/predict_peak', methods=['POST'])
def predict_peak():
    data = request.json
    df = pd.DataFrame(data)

    X = df[['jour','mois','jour_semaine']]
    y = df['total']

    model = RandomForestRegressor(n_estimators=300)
    model.fit(X, y)

    future_data = []
    future_dates = []
    today = pd.Timestamp.today()

    for i in range(1, 31):
        date = today + pd.Timedelta(days=i)
        future_data.append([date.day, date.month, date.weekday()])
        future_dates.append(str(date.date()))

    future_df = pd.DataFrame(future_data, columns=['jour','mois','jour_semaine'])
    predictions = model.predict(future_df)
    threshold = np.mean(predictions) + np.std(predictions)

    results = []
    for i in range(len(predictions)):
        risk = "NORMAL"
        if predictions[i] > threshold * 1.3:
            risk = "CRITIQUE"
        elif predictions[i] > threshold:
            risk = "MOYEN"

        results.append({
            "date": future_dates[i],
            "prediction": float(predictions[i]),
            "peak": bool(predictions[i] > threshold),
            "risk": risk
        })

    return jsonify(results)


@app.route('/predict_evolution', methods=['POST'])
def predict_evolution():
    data = request.json
    
    imc = data.get('imc', 0) or 0
    tension_sys = data.get('tensionSystolique', 0) or 0
    tension_dia = data.get('tensionDiastolique', 0) or 0
    frequence = data.get('frequenceCardiaque', 0) or 0

    X_train = np.array([
        [18.5, 110, 70, 65],
        [22.0, 120, 80, 72],
        [24.5, 125, 82, 75],
        [26.0, 130, 85, 80],
        [28.0, 135, 88, 85],
        [29.5, 138, 89, 90],
        [31.0, 142, 92, 95],
        [33.0, 148, 95, 98],
        [35.0, 155, 98, 102],
        [38.0, 162, 102, 108],
        [40.0, 168, 105, 112],
        [42.0, 175, 110, 118],
    ])
    y_train = np.array([0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1])

    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X_train)
    model = LogisticRegression()
    model.fit(X_scaled, y_train)

    patient = np.array([[imc, tension_sys, tension_dia, frequence]])
    patient_scaled = scaler.transform(patient)
    probability = float(model.predict_proba(patient_scaled)[0][1])
    percentage = round(probability * 100, 1)

    evolution = []
    for month in range(1, 7):
        factor = 1 + (month * 0.02)
        future_imc = round(imc * factor, 2)
        future_tension = round(tension_sys * factor, 2)
        future_frequence = round(frequence * factor, 2)

        future_patient = np.array([[future_imc, future_tension, tension_dia, future_frequence]])
        future_scaled = scaler.transform(future_patient)
        future_prob = float(model.predict_proba(future_scaled)[0][1])
        future_percentage = round(future_prob * 100, 1)

        if future_percentage >= 75:
            risk = "CRITIQUE"
        elif future_percentage >= 50:
            risk = "ÉLEVÉ"
        elif future_percentage >= 25:
            risk = "MODÉRÉ"
        else:
            risk = "NORMAL"

        evolution.append({
            'mois': month,
            'imc': future_imc,
            'tension': future_tension,
            'probabilite': future_percentage,
            'risque': risk
        })

    if percentage >= 75:
        risk_level = "CRITIQUE"
    elif percentage >= 50:
        risk_level = "ÉLEVÉ"
    elif percentage >= 25:
        risk_level = "MODÉRÉ"
    else:
        risk_level = "NORMAL"

    return jsonify({
        'probabiliteActuelle': percentage,
        'risqueActuel': risk_level,
        'evolution': evolution
    })


# ==================== RECONNAISSANCE FACIALE ====================

@app.route('/face/encode', methods=['POST'])
def encode_face():
    try:
        data = request.json
        image_data = base64.b64decode(data['image'])

        with tempfile.NamedTemporaryFile(suffix='.jpg', delete=False) as f:
            f.write(image_data)
            tmp_path = f.name

        embedding = DeepFace.represent(img_path=tmp_path, model_name='Facenet', enforce_detection=True)
        os.unlink(tmp_path)

        return jsonify({'success': True, 'encoding': embedding[0]['embedding']})
    except Exception as e:
        return jsonify({'success': False, 'error': 'Aucun visage détecté'}), 400


@app.route('/face/verify', methods=['POST'])
def verify_face():
    try:
        data = request.json
        image_data = base64.b64decode(data['image'])
        stored_encoding = data['stored_encoding']

        with tempfile.NamedTemporaryFile(suffix='.jpg', delete=False) as f:
            f.write(image_data)
            tmp_path = f.name

        new_embedding = DeepFace.represent(img_path=tmp_path, model_name='Facenet', enforce_detection=True)
        os.unlink(tmp_path)

        v1 = np.array(stored_encoding)
        v2 = np.array(new_embedding[0]['embedding'])
        cosine_sim = float(np.dot(v1, v2) / (np.linalg.norm(v1) * np.linalg.norm(v2)))
        confidence = round(cosine_sim * 100, 2)

        return jsonify({
            'success': True,
            'match': cosine_sim > 0.7,
            'confidence': confidence
        })
    except Exception as e:
        return jsonify({'success': False, 'error': 'Aucun visage détecté'}), 400


if __name__ == '__main__':
    app.run(port=5000)