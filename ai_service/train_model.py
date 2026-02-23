import mysql.connector
import pandas as pd
import pickle
from sklearn.feature_extraction.text import CountVectorizer
from sklearn.naive_bayes import MultinomialNB
from sklearn.pipeline import make_pipeline

# 1. Connexion à la BDD Symfony (A adapter selon ta config)
db_connection = mysql.connector.connect(
    host="127.0.0.1",
    user="root",
    password="", # Ton mot de passe root (souvent vide sous XAMPP/WAMP)
    database="cardiolink" # Le nom de ta base
)

# 2. Récupérer les données d'entraînement
query = "SELECT content, classification FROM message WHERE classification IS NOT NULL"
df = pd.read_sql(query, db_connection)

print(f"📊 Données chargées : {len(df)} messages")
print(df['classification'].value_counts()) # Affiche la répartition

# 3. Création du Pipeline (Nettoyage + IA)
# CountVectorizer : Transforme les mots en nombres
# MultinomialNB : L'algorithme de classification
model = make_pipeline(CountVectorizer(), MultinomialNB())

# 4. Entraînement (Le moment magique !)
model.fit(df['content'], df['classification'])
print("✅ Modèle entraîné avec succès !")

# 5. Sauvegarde du modèle
with open('cardio_model.pkl', 'wb') as f:
    pickle.dump(model, f)
print("💾 Modèle sauvegardé dans 'cardio_model.pkl'")