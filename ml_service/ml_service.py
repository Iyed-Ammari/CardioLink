from flask import Flask, request, jsonify
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
import numpy as np

app = Flask(__name__)

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

    # ✅ Ajoute juste cette partie modifiée
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

if __name__ == '__main__':
    app.run(port=5000)