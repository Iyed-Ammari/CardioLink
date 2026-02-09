# Module de Monitoring CardioLink - Documentation

## Vue d'ensemble
Le module de monitoring CardioLink a été entièrement développé selon vos spécifications. Il permet aux patients de suivre leurs données cardiaques et aux médecins de gérer les interventions d'urgence.

---

## 📊 Entities (Entités)

### 1. **Suivi**
Représente une mesure de donnée cardiaque d'un patient.

**Propriétés:**
- `typeDonnee` (string, 255) - Type de donnée mesuré
- `valeur` (float) - Valeur mesurée
- `unite` (string, 20) - Unité de mesure (automatiquement définie)
- `dateSaisie` (datetime_immutable) - Automatiquement définie à maintenant
- `niveauUrgence` (string, 50) - Normal/Stable/Critique (calculé automatiquement)
- `patient` (ManyToOne → User) - Patient auquel appartient le suivi
- `intervention` (OneToOne → Intervention) - Intervention créée si critique

**Méthodes:**
- `isCritical()` - Vérifie si les valeurs dépassent les seuils critiques
- `getFormattedValue()` - Retourne "120 bpm" ou "140/90 mmHg", etc.

**Seuils Critiques Implémentés:**
- Fréquence Cardiaque: > 120 bpm ou < 40 bpm
- SpO2: < 90%
- Température: > 39°C ou < 35°C
- Glycémie: > 250 mg/dL ou < 70 mg/dL
- Tension: Système sophistiqué avec valeurs critiques

---

### 2. **Intervention**
Représente une action médicale déclenchée automatiquement ou manuellement.

**Propriétés:**
- `type` (string, 255) - Type d'intervention (Alerte SOS, Consultation, etc.)
- `description` (text) - Description automatiquement générée pour les SOS
- `statut` (string, 50) - En attente/Acceptée/En cours/Effectuée/Annulée
- `datePlanifiee` (datetime_immutable) - Automatiquement définie à maintenant
- `dateCompletion` (datetime_immutable, nullable) - Définie lors du marquage "Effectuée"
- `medecin` (ManyToOne → User, nullable) - Médecin responsable
- `suiviOrigine` (OneToOne → Suivi, nullable) - Suivi qui a déclenché l'alerte

**Méthodes:**
- `markAsCompleted()` - Marque comme effectuée et enregistre l'heure
- `isUrgent()` - Vérifie si c'est une alerte SOS

---

## 🎯 Logique Implémentée

### Flux Automatique des Données

1. **Saisie du Suivi**
   - L'utilisateur entre uniquement: Type de donnée + Valeur
   - **Automatiquement remplis:**
     - Unité de mesure selon le type
     - Date/Heure actuelle
     - Niveau d'urgence (Normal/Stable/Critique)
     - Patient (utilisateur authentifié)

2. **Création Automatique d'Intervention**
   - Si `isCritical() == true`
   - Une intervention SOS est créée automatiquement
   - Description générée automatiquement avec détails du patient
   - Statut initial: "En attente"
   - Médecin: nullable (assigner ultérieurement)

3. **Processus Médecin**
   - Visualisation des interventions SOS urgentes
   - Acceptation de l'intervention
   - Marquage comme effectuée (avec timestamp de fin)

---

## 🔐 Validation Côté Serveur

**TOUTES les validations se font côté serveur:**

### Validation SuiviFormType
- `typeDonnee` - NotBlank + Choice (énumération stricte)
- `valeur` - NotBlank + Type(float) + Positive

### Validation InterventionFormType
- `type` - NotBlank + Choice
- `description` - NotBlank + Length(min: 10)
- `statut` - NotBlank + Choice

### Validation au niveau Entity
- Constraints Doctrine via attributs `#[Assert\...]`
- Validation en base de données (NOT NULL, Foreign Keys)

---

## 📁 Structure de Fichiers Créés

```
src/
├── Entity/
│   ├── Suivi.php
│   └── Intervention.php
├── Form/
│   ├── SuiviFormType.php
│   └── InterventionFormType.php
├── Repository/
│   ├── SuiviRepository.php
│   └── InterventionRepository.php
├── Controller/
│   ├── SuiviController.php
│   └── InterventionController.php

templates/
├── suivi/
│   ├── index.html.twig (liste des suivis)
│   ├── new.html.twig (créer un suivi)
│   ├── show.html.twig (détails du suivi)
│   └── edit.html.twig (modifier un suivi)
├── intervention/
│   ├── index.html.twig (interventions en attente)
│   ├── urgent.html.twig (alertes SOS)
│   ├── show.html.twig (détails intervention)
│   └── edit.html.twig (modifier intervention)

migrations/
└── Version20260209125400.php (migration BD)
```

---

## 🛣️ Routes Disponibles

### Routes Suivi
- `GET  /suivi` - Liste des suivis du patient
- `GET/POST /suivi/nouveau` - Créer un nouveau suivi
- `GET  /suivi/{id}/voir` - Voir détails du suivi
- `GET/POST /suivi/{id}/modifier` - Modifier un suivi
- `POST /suivi/{id}/supprimer` - Supprimer un suivi

### Routes Intervention
- `GET  /intervention` - Liste des interventions en attente
- `GET  /intervention/urgent` - Alertes SOS urgentes
- `GET  /intervention/{id}/voir` - Détails intervention
- `POST /intervention/{id}/accepter` - Accepter intervention
- `POST /intervention/{id}/marquer-effectuee` - Marquer comme effectuée
- `GET/POST /intervention/{id}/modifier` - Modifier intervention
- `POST /intervention/{id}/supprimer` - Supprimer intervention

---

## 🔍 Fonctionnalités CRUD Complètes

### Suivi (✅ CRUD Complet)
- ✅ **CREATE** - Nouveau suivi avec validation complète
- ✅ **READ** - Voir liste et détails
- ✅ **UPDATE** - Modifier et recalculer automatiquement
- ✅ **DELETE** - Supprimer un suivi

### Intervention (✅ CRUD Complet)
- ✅ **CREATE** - Automatiquement ou manuellement
- ✅ **READ** - Vue d'ensemble et détails
- ✅ **UPDATE** - État, médecin assigné
- ✅ **DELETE** - Suppression (avec cascade)

---

## 🧪 Exemples d'Utilisation

### Créer un Suivi (Scénario 1: Normal)
```
Patient saisit:
- Type: "Fréquence Cardiaque"
- Valeur: 85

Résultat automatique:
- Unité: "bpm"
- Urgence: "Normal"
- isCritical(): false
- Pas d'intervention créée
```

### Créer un Suivi (Scénario 2: Critique)
```
Patient saisit:
- Type: "Fréquence Cardiaque"
- Valeur: 145

Résultat automatique:
- Unité: "bpm"
- Urgence: "Critique"
- isCritical(): true
- ⚠️ ALERTE SOS: Intervention créée automatiquement
- Description: "ALERTE URGENTE: La fréquence cardiaque du patient..."
- Médecin: (À assigner)
```

---

## 🎨 Interface Utilisateur

### Pour les Patients
- Dashboard: "Mes Suivis" avec tableau coloré
  - Vert = Normal
  - Orange = Stable
  - Rouge = Critique
- Formulaire simple (2 champs)
- Historique avec dates formatées

### Pour les Médecins
- Tableau: "Interventions" (toutes en attente)
- Vue rapide: "🚨 SOS Urgent" (alertes critiques)
- Actions: Accepter, Marquer effectuée, Modifier
- Détails du patient et du suivi d'origine

---

## 📋 Checklist Implémentation

- ✅ Entité Suivi avec toutes les propriétés
- ✅ Entité Intervention avec toutes les propriétés
- ✅ Méthodes `isCritical()` et `getFormattedValue()`
- ✅ Méthode `markAsCompleted()` et `isUrgent()`
- ✅ Remplissage automatique des champs (unité, urgence)
- ✅ Logique de création automatique d'Intervention
- ✅ Seuils critiques médicalement cohérents
- ✅ Formulaires avec validation serveur uniquement
- ✅ CRUD complet pour Suivi et Intervention
- ✅ Templates Twig professionnels
- ✅ Migration Doctrine exécutée
- ✅ Navigation intégrée au layout principal

---

## 🚀 Instruction de Test

1. Inscrivez-vous en tant que **Patient**
2. Allez à **"📊 Mes Suivis"**
3. Cliquez **"Nouveau Suivi"**
4. Entrez: Type = "Fréquence Cardiaque", Valeur = 85
5. Vérifiez que tout se remplit automatiquement
6. Testez avec Valeur = 145 pour déclencher une alerte

7. Inscrivez-vous en tant que **Médecin** (modifiez le rôle en BD)
8. Allez à **"🚨 SOS"** pour voir les alertes
9. Cliquez sur une alerte SOS
10. Acceptez et marquez comme effectuée

---

## 📝 Notes Importantes

- Toutes les validations sont côté serveur (pas de HTML ou JS)
- Les dates sont automatiquement converties en `DateTimeImmutable`
- Les unités sont automatiquement définis selon le type
- Les niveaux d'urgence sont calculés automatiquement
- Les interventions SOS sont créées automatiquement si critique
- Les descriptions SOS incluent le nom du patient et les détails
- L'authentification est requise pour accéder aux modules
- Les patients ne peuvent voir que leurs propres suivis
- Les médecins peuvent voir toutes les interventions

---

**Module de Monitoring - Prêt pour utilisation! 🎉**
