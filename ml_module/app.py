from flask import Flask, jsonify
import pandas as pd
from sqlalchemy import create_engine
from sklearn.linear_model import LinearRegression
import datetime
import numpy as np

app = Flask(__name__)

# Configuration de la base de données
ENGINE = create_engine('mysql+mysqlconnector://root:@localhost/cardiolink')

def get_predictions():
    # 1. Chargement des données
    query = "SELECT date_planifiee FROM intervention"
    df = pd.read_sql(query, ENGINE)
    
    if df.empty:
        return []

    df['date_planifiee'] = pd.to_datetime(df['date_planifiee'])
    df['date'] = df['date_planifiee'].dt.date
    daily_counts = df.groupby('date').size().reset_index(name='nb_interventions')
    daily_counts['date_ordinal'] = pd.to_datetime(daily_counts['date']).apply(lambda x: x.toordinal())

    # 2. Entraînement
    X = daily_counts[['date_ordinal']]
    y = daily_counts['nb_interventions']
    model = LinearRegression()
    model.fit(X, y)

    # 3. Prédiction (7 prochains jours)
    last_date = daily_counts['date'].max()
    future_dates = [last_date + datetime.timedelta(days=i) for i in range(1, 8)]
    future_ordinal = np.array([d.toordinal() for d in future_dates]).reshape(-1, 1)
    predictions = model.predict(future_ordinal)

    # 4. Formatage des résultats pour JSON
    results = []
    for date, pred in zip(future_dates, predictions):
        results.append({
            'date': date.strftime('%Y-%m-%d'),
            'nb_interventions': max(0, int(round(pred)))
        })
    return results

@app.route('/predict', methods=['GET'])
def predict():
    try:
        data = get_predictions()
        return jsonify(data)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    app.run(port=5002, debug=True)