from flask import Flask, request, jsonify
import mysql.connector
import pandas as pd
from sklearn.linear_model import LinearRegression

app = Flask(__name__)

def get_db_connection():
    return mysql.connector.connect(
        host="localhost",
        user="root",
        password="",
        database="cardiolink"
    )

@app.route('/predict/<int:medecin_id>', methods=['GET'])
def predict(medecin_id):
    try:
        conn = get_db_connection()
        query = "SELECT DATE_FORMAT(date_heure, '%Y-%m') as mois, COUNT(id) as total FROM rendez_vous WHERE medecin_id = %s GROUP BY mois ORDER BY mois ASC"
        df = pd.read_sql(query, conn, params=(medecin_id,))
        conn.close()

        if len(df) < 2:
            return jsonify({"prediction": "Besoin de plus de données", "historique": []})

        # IA
        df['x'] = range(len(df))
        model = LinearRegression().fit(df[['x']], df['total'])
        prediction = model.predict([[len(df)]])[0]

        return jsonify({
            "prediction": round(prediction, 1),
            "historique": df.to_dict(orient='records')
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == '__main__':
    app.run(port=5000)