# 🏥 CardioLink - Plateforme de Monitoring Cardiovasculaire

[![License](https://img.shields.io/badge/License-Proprietary-red)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP->=8.1-blue)]()
[![Symfony](https://img.shields.io/badge/Symfony-6.4-green)]()
[![Docker](https://img.shields.io/badge/Docker-Compose-blue)]()

CardioLink est une **plateforme médicale complète** dédiée au monitoring cardiovasculaire, à la gestion des dossiers médicaux et aux rendez-vous médicaux. Elle combine une **application web Symfony robuste** avec des **services Python d'IA et ML** pour une prise en charge optimale des patients.

---

## 📋 Table des matières

- [✨ Fonctionnalités](#-fonctionnalités)
- [🏗️ Architecture](#️-architecture)
- [📦 Prérequis](#-prérequis)
- [🚀 Installation](#-installation)
- [⚙️ Configuration](#️-configuration)
- [💻 Utilisation](#-utilisation)
- [📁 Structure du Projet](#-structure-du-projet)
- [🤖 Services Python](#-services-python)
- [🔌 API Endpoints](#-api-endpoints)
- [📊 Base de Données](#-base-de-données)
- [🧪 Tests](#-tests)
- [🛠️ Développement](#️-développement)
- [📝 Contribution](#-contribution)

---

## ✨ Fonctionnalités

### 🏥 Modules Principaux

#### 1. **Module de Monitoring Cardiovasculaire** ⚕️
- 📊 Enregistrement de données cardiologiques en temps réel
- 🚨 Système d'alerte automatique pour valeurs critiques
- 📈 Historique complet des suivis
- 🎯 Seuils critiques configurables par type de donnée

**Types de données supportés:**
- Fréquence cardiaque (bpm)
- Tension artérielle (mmHg)
- SpO2 - Saturation oxygène (%)
- Température corporelle (°C)
- Glycémie (mg/dL)

#### 2. **Gestion des Dossiers Médicaux** 📁
- 📄 Dossier patient centralisé
- 📋 Historique médical complet
- 🔒 Sécurité et confidentialité des données
- 🏷️ Catégorisation des documents

#### 3. **Système de Rendez-vous** 📅
- 📆 Planification des consultations
- 🤖 **Prédiction IA** des créneaux disponibles
- 📧 Notifications automatiques
- 🔄 Gestion des reportages

#### 4. **Forum Médical** 💬
- 💭 Discussions communautaires
- 📝 Posts et commentaires
- 👥 Système de modération
- 🏷️ Catégories et tags

#### 5. **Système de Conversation** 💌
- 📨 Messagerie entre patients et médecins
- 🔔 Notifications en temps réel
- 📱 Interface intuitive

#### 6. **Dashboard Personnel** 📊
- 👤 Profil utilisateur personnalisé
- 📊 Vue d'ensemble des données de santé
- ⚡ Alertes et notifications
- 🎯 KPIs de santé

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────┐
│         Frontend (Symfony Twig + StimulusJS)        │
├─────────────────────────────────────────────────────┤
│  Controllers (MVC) → Services → Repositories        │
├─────────────────────────────────────────────────────┤
│    PostgreSQL Database + Doctrine ORM               │
├─────────────────────────────────────────────────────┤
│  WebSocket Server (Ratchet) - Notifications         │
├─────────────────────────────────────────────────────┤
│   Services Python (IA/ML)                           │
│  ├─ Moderation Service                              │
│  ├─ ML Prediction Service                           │
│  ├─ Summarizer Service                              │
│  └─ Matching IA Service                             │
└─────────────────────────────────────────────────────┘
```

### Stack Technologique

| Couche | Technologie |
|--------|-------------|
| **Frontend** | Twig, StimulusJS, Bootstrap, CSS/SCSS |
| **Backend** | Symfony 6.4, PHP 8.1+ |
| **Base de Données** | PostgreSQL 16 |
| **ORM** | Doctrine 3.6 |
| **WebSocket** | Ratchet 0.4.4 |
| **IA/ML** | Python, TensorFlow, Scikit-learn |
| **Containerisation** | Docker Compose |
| **Stockage Fichiers** | Cloudinary |
| **PDF** | DOMPDF |

---

## 📦 Prérequis

### Configuration Minimale
- **PHP:** >= 8.1
- **PostgreSQL:** >= 16
- **Python:** >= 3.8 (pour services IA/ML)
- **Docker:** version récente
- **Docker Compose:** version récente
- **Node.js:** >= 18 (facultatif, pour assets)
- **Composer:** version récente

### Extensions PHP Requises
```
- ext-ctype
- ext-iconv
- ext-pdo_pgsql
```

---

## 🚀 Installation

### 1. Clonage du Projet

```bash
git clone <repository-url>
cd CardioLink
```

### 2. Configuration de l'Environnement

Créer un fichier `.env.local` à partir de `.env`:

```bash
cp .env .env.local
```

**Configurer les variables essentielles:**

```env
# Base de données
DATABASE_URL="postgresql://app:!ChangeMe!@database:5432/app?serverVersion=16&charset=utf8"
POSTGRES_DB=app
POSTGRES_USER=app
POSTGRES_PASSWORD=!ChangeMe!
POSTGRES_VERSION=16

# Symfony
APP_ENV=dev
APP_SECRET=votre_secret_unique_ici
APP_DEBUG=1

# Cloudinary (stockage fichiers)
CLOUDINARY_URL=cloudinary://key:secret@cloud_name

# Email
MAILER_DSN=gmail://username:password@default

# JWT (si authentification)
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
```

### 3. Démarrage avec Docker Compose

```bash
# Démarrer les services
docker-compose up -d

# Optionnel: démarrer sans détacher
docker-compose up
```

### 4. Installation des Dépendances PHP

```bash
# Dans le conteneur ou localement
composer install
```

### 5. Création de la Base de Données

```bash
# Créer la base de données
php bin/console doctrine:database:create

# Appliquer les migrations
php bin/console doctrine:migrations:migrate
```

### 6. Chargement des Fixtures (Données de Test)

```bash
php bin/console doctrine:fixtures:load --no-interaction
```

### 7. Build des Assets

```bash
# Si Node.js est configuré
npm install
npm run build

# Ou avec Symfony AssetMapper
php bin/console asset-map:compile
```

### 8. Accès à l'Application

- **Frontend:** http://localhost
- **Base de données:** localhost:5432
- **WebSocket:** ws://localhost:8080

---

## ⚙️ Configuration

### Configuration Symfony

Les fichiers de configuration se trouvent dans `config/`:

```
config/
├── bundles.php              # Bundles activés
├── services.yaml            # Services DI
├── routes.yaml              # Routage principal
└── packages/                # Configuration des bundles
    ├── dev/
    ├── test/
    ├── doctrine.yaml
    ├── framework.yaml
    └── security.yaml
```

### Variables d'Environnement Clés

| Variable | Description | Valeur Par Défaut |
|----------|-------------|------------------|
| `APP_ENV` | Environnement | `dev` |
| `APP_DEBUG` | Mode debug | `1` |
| `DATABASE_URL` | URL PostgreSQL | Voir .env |
| `MAILER_DSN` | Serveur mail | Voir .env |
| `CLOUDINARY_URL` | Stockage fichiers | Voir .env |

### Configuration WebSocket

Le serveur WebSocket (Ratchet) écoute sur `localhost:8080`:

```bash
# Démarrer le serveur WebSocket
php bin/console chat:server
```

---

## 💻 Utilisation

### Pour les Patients

#### Enregistrer une Mesure de Santé

1. Se connecter au dashboard
2. Naviguer vers **"Mes Suivis"** (📊)
3. Cliquer sur **"Nouveau Suivi"**
4. Sélectionner le type: Fréquence Cardiaque, Tension, SpO2, etc.
5. Entrer la valeur mesurée
6. Cliquer **"Enregistrer"**

✅ **Le système remplit automatiquement:**
- Unité de mesure
- Date/Heure
- Niveau d'urgence
- Patient (vous-même)

#### Recevoir des Alertes

- Si la valeur est **critique**, une **alerte SOS** est créée automatiquement
- Les médecins reçoivent une notification
- Un historique est maintenu

#### Consulter l'Historique

- Section **"Mes Suivis"** → Voir tous les enregistrements
- Filtrer par type, date, urgence
- Exporter en PDF

### Pour les Médecins

#### Gérer les Interventions

1. Dashboard → **"Interventions"** (⚕️)
2. Voir les **alertes SOS** en rouge
3. Accepter ou Refuser l'intervention
4. Marquer comme **Effectuée** quand terminée

#### Consulter le Dossier Patient

1. Patients → Sélectionner un patient
2. Voir **l'historique complet**
3. Ajouter des notes
4. Consulter les prescriptions

#### Planifier des Rendez-vous

1. Dashboard → **"Rendez-vous"** (📅)
2. Créer un nouveau rendez-vous
3. Le système **suggère automatiquement** les meilleurs créneaux (IA)
4. Confirmer la date/heure

### Pour les Administrateurs

#### Gérer les Utilisateurs

```bash
# Créer un nouvel administrateur
php bin/console app:create-admin email@example.com password

# Lister tous les utilisateurs
php bin/console doctrine:query:sql "SELECT * FROM user"
```

#### Modération du Forum

- Dashboard Admin → **"Forum"** (💬)
- Modérer les posts et commentaires
- Bannir les utilisateurs problématiques

#### Monitoring du Système

- Voir les **logs** dans `var/log/`
- Vérifier l'état de la base de données
- Gérer les backups

---

## 📁 Structure du Projet

```
CardioLink/
├── bin/                          # Scripts d'exécution
│   └── console                   # Commandes Symfony
├── config/                       # Configuration
│   ├── bundles.php              # Bundles activés
│   ├── services.yaml            # Services/DI
│   ├── routes.yaml              # Routage principal
│   ├── routes/                  # Routage modulaire
│   └── packages/                # Configuration bundles
├── src/                          # Code source PHP
│   ├── Controller/              # Contrôleurs (MVC)
│   ├── Entity/                  # Entités Doctrine (ORM)
│   ├── Form/                    # Formulaires Symfony
│   ├── Repository/              # Requêtes personnalisées
│   ├── Service/                 # Logique métier
│   ├── Validator/               # Validateurs personnalisés
│   ├── Security/                # Authentification/Autorisation
│   ├── Command/                 # Commandes console
│   ├── Twig/                    # Filtres/Fonctions Twig
│   ├── WebSocket/               # WebSocket Ratchet
│   ├── DataFixtures/            # Données de test
│   └── Kernel.php               # Noyau Symfony
├── templates/                    # Vues Twig
│   ├── base.html.twig           # Layout principal
│   ├── base_forum.html.twig     # Layout forum
│   ├── admin/                   # Templates admin
│   ├── dashboard/               # Templates dashboard
│   ├── rendez_vous/             # Templates RDV
│   ├── forum/                   # Templates forum
│   ├── dossier_medical/         # Templates dossiers
│   ├── profile/                 # Gestion profil
│   ├── security/                # Authentification
│   ├── patient/                 # Gestion patients
│   ├── conversation/            # Messagerie
│   ├── intervention/            # Interventions
│   ├── ordonnance/              # Prescriptions
│   ├── suivi/                   # Monitoring
│   ├── simulation/              # Simulations
│   └── transcribe/              # Transcription audio
├── public/                       # Racine web
│   ├── index.php                # Entrée principale
│   ├── assets/                  # Assets compilés
│   └── uploads/                 # Uploads utilisateurs
├── migrations/                   # Migrations Doctrine
│   └── Version*.php             # Migrations versionnées
├── tests/                        # Tests unitaires/fonctionnels
│   ├── Controller/              # Tests contrôleurs
│   ├── Entity/                  # Tests entités
│   └── bootstrap.php            # Amorce tests
├── assets/                       # Assets sources
│   ├── js/                      # JavaScript
│   ├── css/                     # Feuilles de styles
│   ├── controllers/             # Stimulus controllers
│   └── styles/                  # Styles
├── ai/                           # Services IA Python
│   ├── moderation/              # Modération contenu
│   └── requirements.txt
├── ai_service/                   # Service IA principal
│   ├── app.py                   # Application Flask
│   └── train_model.py           # Entraînement modèle
├── ml_service/                   # Service ML
│   └── app.py                   # Service ML Flask
├── ml/                           # Scripts ML
│   └── summarizer.py            # Résumé texte
├── matching.ia/                  # Matching IA
│   ├── main.py
│   ├── train.py
│   └── train_data.csv
├── prediction_rdv/               # Prédiction RDV
│   └── predict_rdv.py           # Prédiction créneaux
├── var/                          # Répertoire variable
│   ├── cache/                   # Cache Symfony
│   ├── log/                     # Logs
│   └── sessions/                # Sessions
├── vendor/                       # Dépendances Composer
├── node_modules/                 # Dépendances npm (optionnel)
├── docker-compose.yaml           # Composition services
├── compose.override.yaml         # Surcharge docker-compose
├── .env                          # Variables d'env (template)
├── .env.local                    # Variables d'env (local)
├── composer.json                 # Dépendances PHP
├── composer.lock                 # Lock file Composer
├── phpunit.xml.dist              # Configuration PHPUnit
├── phpstan.neon                  # Configuration PHPStan
└── README.md                     # Ce fichier
```

---

## 🤖 Services Python

CardioLink intègre plusieurs services Python pour l'IA et le ML:

### 1. **Service de Modération** 🛡️

Localisation: `ai/moderation/`

**Fonctionnalité:** Analyse les posts et commentaires du forum pour détecter:
- Contenu offensant
- Spam
- Contenu médical inapproprié

**Utilisation:**
```python
# Depuis PHP - via API Flask
POST /api/moderate
{
  "text": "Texte à modérer",
  "type": "post|comment"
}
```

### 2. **Service de Prédiction ML** 🔮

Localisation: `ai_service/` et `ml_service/`

**Fonctionnalité:** 
- Prédiction de créneaux disponibles (RDV)
- Analyse de patterns de santé
- Recommandations

**Utilisation:**
```python
# Entraînement du modèle
python ai_service/train_model.py

# Lancer le service
python ai_service/app.py  # Port 5000
```

### 3. **Service de Résumé** 📝

Localisation: `ml/summarizer.py`

**Fonctionnalité:** Génère des résumés automatiques des dossiers médicaux

**Utilisation:**
```python
python ml/summarizer.py <file_path>
```

### 4. **Service de Matching IA** 🎯

Localisation: `matching.ia/`

**Fonctionnalité:** Associe patients aux médecins les plus appropriés

**Utilisation:**
```python
# Entraînement
python matching.ia/train.py

# Prédiction
python matching.ia/main.py
```

### 5. **Service de Prédiction RDV** 📅

Localisation: `prediction_rdv/`

**Fonctionnalité:** Prédit les créneaux disponibles optimaux

**Utilisation:**
```python
python prediction_rdv/predict_rdv.py
```

---

## 🔌 API Endpoints

### Authentication

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/register` | Inscription utilisateur |
| POST | `/login` | Connexion |
| GET | `/logout` | Déconnexion |
| GET | `/reset-password` | Réinitialiser mot de passe |

### Suivi (Monitoring)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/suivi` | Liste mes suivis |
| POST | `/suivi/nouveau` | Créer un suivi |
| GET | `/suivi/{id}/voir` | Voir un suivi |
| POST | `/suivi/{id}/modifier` | Modifier un suivi |
| POST | `/suivi/{id}/supprimer` | Supprimer un suivi |

### Interventions

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/intervention` | Liste interventions |
| POST | `/intervention/{id}/accepter` | Accepter intervention |
| POST | `/intervention/{id}/refuser` | Refuser intervention |
| POST | `/intervention/{id}/complete` | Marquer comme effectuée |

### Rendez-vous

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/rendez-vous` | Liste RDV |
| POST | `/rendez-vous/nouveau` | Créer RDV |
| GET | `/rendez-vous/{id}` | Détail RDV |
| POST | `/rendez-vous/{id}/modifier` | Modifier RDV |
| POST | `/rendez-vous/{id}/annuler` | Annuler RDV |

### Forum

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/forum` | Liste posts |
| POST | `/forum/nouveau` | Créer post |
| POST | `/forum/{id}/comment` | Ajouter commentaire |
| POST | `/forum/{id}/delete` | Supprimer post |

### Dossier Médical

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/dossier` | Voir dossier |
| POST | `/dossier/upload` | Uploader document |
| GET | `/dossier/export` | Exporter dossier (PDF) |

### WebSocket

| Event | Description |
|-------|-------------|
| `chat:message` | Nouveau message reçu |
| `alert:critical` | Alerte critique |
| `notification:intervention` | Intervention créée |

---

## 📊 Base de Données

### Schéma Principal

```sql
-- Utilisateurs
user (id, email, password, role, created_at, updated_at)

-- Monitoring
suivi (id, patient_id, type_donnee, valeur, unite, 
       niveau_urgence, date_saisie, created_at)

intervention (id, type, description, statut, 
             date_planifiee, date_completion, 
             medecin_id, suivi_id, created_at)

-- Rendez-vous
rendez_vous (id, patient_id, medecin_id, date_rdv, 
            description, statut, created_at)

-- Forum
post (id, author_id, title, content, created_at, updated_at)
comment (id, post_id, author_id, content, created_at)

-- Dossier Médical
dossier_medical (id, patient_id, contenu, created_at)

-- Conversations
conversation (id, participant1_id, participant2_id, created_at)
message (id, conversation_id, sender_id, content, 
        created_at, is_read)
```

### Migrations

Les migrations sont versionnées dans `migrations/`:

```bash
# Voir les migrations
php bin/console doctrine:migrations:list

# Appliquer les migrations
php bin/console doctrine:migrations:migrate

# Revenir en arrière
php bin/console doctrine:migrations:migrate prev
```

---

## 🧪 Tests

### Exécuter les Tests

```bash
# Tous les tests
php bin/phpunit

# Tests d'une classe spécifique
php bin/phpunit tests/Controller/SuiviControllerTest.php

# Tests avec couverture de code
php bin/phpunit --coverage-html var/coverage
```

### Tests Disponibles

```
tests/
├── Controller/                  # Tests contrôleurs
├── Entity/                      # Tests entités
├── DossierMedicalManagerTest.php
├── MessageValidatorTest.php
├── PostManagerTest.php
└── UserManagerTest.php
```

---

## 🛠️ Développement

### Commandes Utiles

#### Gestion de la Base de Données

```bash
# Créer la BD
php bin/console doctrine:database:create

# Supprimer la BD
php bin/console doctrine:database:drop --force

# Valider le schéma
php bin/console doctrine:schema:validate

# Générer une migration
php bin/console make:migration

# Exécuter les migrations
php bin/console doctrine:migrations:migrate
```

#### Génération d'Code

```bash
# Générer une entité
php bin/console make:entity

# Générer un contrôleur
php bin/console make:controller

# Générer un formulaire
php bin/console make:form

# Générer une commande
php bin/console make:command
```

#### Debugging

```bash
# Voir la configuration
php bin/console debug:config

# Lister les services
php bin/console debug:container

# Lister les routes
php bin/console debug:router

# Tester une route
php bin/console debug:router app_suivi_index
```

#### Sécurité

```bash
# Générer des clés JWT
php bin/console lexik:jwt:generate-keypair

# Créer un utilisateur
php bin/console app:create-user
```

### Mode Développement

```bash
# Activer le mode debug
export APP_DEBUG=1
export APP_ENV=dev

# Vider le cache
php bin/console cache:clear

# Réchauffer le cache
php bin/console cache:warmup
```

### Linting et Analyse Statique

```bash
# PHPStan (analyse statique)
php bin/phpstan analyse src

# PHP-CS-Fixer (formatage)
php-cs-fixer fix src

# Psalm (vérification de type)
psalm
```

---

## 📝 Contribution

### Principes de Contribution

1. **Code Standard:** Respecter PSR-12 pour le PHP
2. **Tests:** Tout nouveau code doit avoir des tests
3. **Documentation:** Commenter le code complexe
4. **Git:** Utiliser des branches Feature pour le développement

### Workflow Git

```bash
# Créer une branche feature
git checkout -b feature/nouvelle-fonctionnalite

# Commiter avec messages clairs
git commit -m "feat: ajouter monitoring de glycémie"

# Pousser la branche
git push origin feature/nouvelle-fonctionnalite

# Créer une Pull Request
# Merger après révision
```

### Standards de Commit

```
feat:     Nouvelle fonctionnalité
fix:      Correction de bug
docs:     Documentation
style:    Formatage, pas de changement logique
refactor: Refonte sans changement fonctionnel
test:     Ajout ou modification de tests
chore:    Tâches de maintenance
```

---

## 🚨 Troubleshooting

### Problèmes Courants

#### 1. Erreur de Connexion à la Base de Données

```bash
# Vérifier les credentials
php bin/console doctrine:database:create

# Vérifier les migrations
php bin/console doctrine:migrations:status

# Recharger les fixtures
php bin/console doctrine:fixtures:load
```

#### 2. Erreur d'Assets

```bash
# Recompiler les assets
php bin/console asset-map:compile

# Ou avec npm
npm run build
```

#### 3. Erreur de Permission

```bash
# Corriger les permissions
chmod -R 777 var/
chmod -R 777 public/uploads/
```

#### 4. WebSocket ne fonctionne pas

```bash
# Vérifier que le port 8080 est libre
netstat -an | grep 8080

# Relancer le serveur
php bin/console chat:server
```

---

## 📞 Support et Documentation

- **Documentation Symfony:** https://symfony.com/doc/current/
- **Documentation PostgreSQL:** https://www.postgresql.org/docs/
- **Documentation Doctrine:** https://www.doctrine-project.org/
- **Ratchet WebSocket:** https://socketo.me/

---

## 📄 Licence

Ce projet est **propriétaire**. Tous les droits sont réservés.

---

## 👥 Équipe

- **Développement:** Équipe de développement CardioLink
- **Architecture:** Système médical intégré
- **Support:** Contactez l'équipe support

---

## 📊 Statistiques du Projet

```
Total Files:     ~150+
PHP Files:       ~80
Template Files:  ~50
Test Files:      ~10
Lines of Code:   ~15,000+
```

---

**Dernière mise à jour:** Mai 2026

**Statut:** ✅ Production Ready

