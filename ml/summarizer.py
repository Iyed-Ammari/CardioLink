import sys
import nltk
from sklearn.feature_extraction.text import TfidfVectorizer
import numpy as np

# Téléchargement de punkt si nécessaire
nltk.download('punkt', quiet=True)

def summarize(text, num_sentences=3):
    sentences = nltk.sent_tokenize(text)
    if len(sentences) <= num_sentences:
        return text
    vectorizer = TfidfVectorizer(stop_words='english')
    X = vectorizer.fit_transform(sentences)
    scores = np.sum(X.toarray(), axis=1)
    ranked_sentences = [sentences[i] for i in np.argsort(scores)[-num_sentences:]]
    return " ".join(ranked_sentences)

if __name__ == "__main__":
    # Lire le texte depuis STDIN
    input_text = sys.stdin.read()
    if input_text.strip():
        print(summarize(input_text))
    else:
        print("Résumé non disponible")
