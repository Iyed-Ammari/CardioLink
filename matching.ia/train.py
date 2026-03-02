from sentence_transformers import SentenceTransformer, InputExample, losses
from torch.utils.data import DataLoader
import pandas as pd
import os

# 1. Charger un modèle de base (Multilingue pour comprendre le français)
# 'paraphrase-multilingual-MiniLM-L12-v2' est léger et très efficace
print("Chargement du modèle de base...")
model = SentenceTransformer('paraphrase-multilingual-MiniLM-L12-v2')

# 2. Charger tes données depuis le CSV
print("Chargement des données d'entraînement...")
data = pd.read_csv('train_data.csv')
train_examples = []

for i, row in data.iterrows():
    train_examples.append(InputExample(texts=[str(row['phrase1']), str(row['phrase2'])], label=float(row['score'])))

# 3. Configurer l'entraînement
train_dataloader = DataLoader(train_examples, shuffle=True, batch_size=16)
train_loss = losses.CosineSimilarityLoss(model)

# 4. Lancer l'apprentissage (Fine-tuning)
print("Début de l'entraînement... Cela peut prendre quelques minutes.")
model.fit(
    train_objectives=[(train_dataloader, train_loss)], 
    epochs=10, # On fait 10 passages pour bien apprendre
    warmup_steps=100
)

# 5. Sauvegarder ton modèle personnel
if not os.path.exists('model_saved'):
    os.makedirs('model_saved')

model.save('model_saved/mon_modele_ia')
print("Succès ! Ton IA est enregistrée dans le dossier 'model_saved/mon_modele_ia'.")