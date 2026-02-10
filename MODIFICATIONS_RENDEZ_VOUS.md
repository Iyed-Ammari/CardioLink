# Modifications du Système de Rendez-vous

## Résumé des Modifications

Toutes les modifications demandées ont été implémentées avec succès.

---

## 1. PART 1: Création d'un RDV (`new.html.twig` et `RendezVousType.php`)

### ✅ Sécurité
- **Validation coté serveur** : Seul un patient avec `ROLE_PATIENT` peut créer un RDV
- **Annotation**: `#[IsGranted('ROLE_PATIENT')]` dans le contrôleur `new()`

### ✅ Formulaire Simplifié (4 champs seulement)

Le formulaire contient exactement 4 champs :

1. **Médecin** (Liste déroulante)
   - **Filtre ROLE_MEDECIN** : La méthode `findMedecins()` affiche seulement les utilisateurs ayant `ROLE_MEDECIN`
   - **Validation serveur** : Message d'erreur "Veuillez sélectionner un médecin."
   - **Type dureté** : Select HTML

2. **Date et Heure** 
   - **Format** : datetime-local
   - **Validation serveur** : Message d'erreur "La date et l'heure sont obligatoires."
   - **Contrôle additionnel** : La date doit être dans le futur

3. **Type de Consultation** (Boutons Radio)
   - **Options** : 
     - "Consultation au cabinet" → Valeur: `Présentiel`
     - "Vidéo Consultation" → Valeur: `Télémédecine`
   - **Validation serveur** : Message d'erreur "Veuillez sélectionner un type de consultation."
   - **Type dureté** : Radio buttons (`expanded: true`)

4. **Motif / Remarques** 
   - **Type** : TextArea
   - **Optionnel** : `required: false` mais avec validation serveur
   - **Placeholder** : "Ex: Douleurs thoraciques depuis 2 jours..."

### ✅ Validation Serveur (Contrôleur)

Le contrôleur `new()` inclut les validations suivantes :
- Vérifier que le médecin est bien défini
- Vérifier que la date/heure est bien définie
- Vérifier que le type est bien défini
- **Vérifier le rôle ROLE_MEDECIN** du médecin sélectionné
- **Date dans le futur** (contrôle supplémentaire)
- **Épargnable** : Vérifier la disponibilité du médecin (créneau non occupé)

### ✅ Logique Particulière

**Pour la Télémédecine** :
- Génération automatique d'un lien Jitsi unique
- Le lieu physique est annulé (`null`)

**Pour le Présentiel** :
- Assignation automatique du lieu selon le cabinet du médecin
- Création automatique d'un `Lieu` s'il n'existe pas

---

## 2. PART 2: Modification d'un RDV (`edit.html.twig` et `RendezVousType.php`)

### ✅ Patient - Peut modifier les 4 mêmes champs

Le patient utilise exactement **le même formulaire** que la création :
- Médecin
- Date et Heure
- Type de Consultation
- Motif / Remarques

**Sécurité** :
- Seul le patient peut modifier son RDV
- Le RDV ne doit pas être finalisé ou passé
- Utilisation de `canPatientEdit()` pour vérifier les permissions

### ✅ Médecin - Pas de changement

Le médecin continue avec le système existant :
- **Peut modifier seulement le statut** du RDV
- Utilise un formulaire différent (`RendezVousStatusType`)

---

## 3. PART 3: Affichage des RDV (`index.html.twig`)

### ✅ Aucun changement apporté
Le template `index.html.twig` reste inchangé, fonctionnant comme prévu.

---

## Fichiers Modifiés

### 1. `src/Form/RendezVousType.php`
- ✅ Suppression des champs redondants
- ✅ Suppression des imports inutiles (`Lieu`, `Ordonnance`)
- ✅ Ajout de `expanded: true` pour les radio buttons du type
- ✅ Nettoyage des options du formulaire

### 2. `src/Controller/RendezVousController.php`
- ✅ Suppression de l'option `edit_by_doctor` dans la méthode `edit()`
- ✅ Suppressions de la variable `$isEditByDoctor` (non utilisée)

### 3. `templates/rendez_vous/new.html.twig`
- ✅ Simplification à 4 champs seulement
- ✅ Affichage des erreurs avec icônes ⚠️
- ✅ Boutons radio pour le type de consultation
- ✅ Suppression du script JavaScript obsolète pour basculer le lieu

### 4. `templates/rendez_vous/edit.html.twig`
- ✅ Simplification à 4 champs seulement (identique au template de création)
- ✅ Affichage des erreurs avec icônes ⚠️
- ✅ Boutons radio pour le type de consultation

---

## Validations Côté Serveur (Résumé)

Tous les messages d'erreur s'affichent via :
1. **Contraintes du formulaire** (via `NotBlank`)
2. **Validation additionnelle du contrôleur** (sécurité redoublée)

### Messages d'Erreur

| Champ | Message | Origine |
|-------|---------|---------|
| Médecin | "Veuillez sélectionner un médecin." | Formulaire + Contrôleur |
| Date/Heure | "La date et l'heure sont obligatoires." | Formulaire + Contrôleur |
|  | "Veuillez sélectionner une date et une heure dans le futur." | Contrôleur |
| Type | "Veuillez sélectionner un type de consultation." | Formulaire + Contrôleur |
| Médecin | "Le praticien sélectionné n'est pas un médecin." | Contrôleur |
| Disponibilité | "Le médecin n'est pas disponible à ce créneau." | Contrôleur |

---

## Tests Recommandés

1. **Créer un RDV**
   - Tenter sans remplir les champs → Voir les messages d'erreur
   - Tenter avec une date passée → Message d'erreur adaptée
   - Tenter avec un médecin non confirmé → Message d'erreur

2. **Modifier un RDV**
   - Patient modifie les 4 champs → Fonctionne
   - Patient finalisé tente de modifier → Accès refusé
   - Médecin tente d'éditer via le formulaire patient → Comportement correct

3. **Validation des Rôles**
   - Vérifier que seuls les médecins apparaissent dans la liste déroulante
   - Vérifier que `ROLE_PATIENT` est obligatoire pour créer/modifier

---

## Notes Importantes

- Les **roles** sont définis dans `config/packages/security.yaml`
- La méthode `findMedecins()` dans `UserRepository.php` filtre déjà par `ROLE_MEDECIN`
- Le système de **validation double** (formulaire + contrôleur) est délibéré pour la sécurité
- Les téléchargements de la entité `RendezVous` ne contiennent pas de `lieu` obligatoire pour les modifications de médecins
