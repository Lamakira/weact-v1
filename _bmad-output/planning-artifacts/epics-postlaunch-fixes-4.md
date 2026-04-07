---
stepsCompleted: [1, 2, 3, 4]
status: 'ready'
totalEpics: 2
totalStories: 3
project_name: 'WEACT - Correctifs Post-Lancement Sprint 4'
user_name: 'Lamakira'
date: '2026-04-05'
---

# WEACT - Correctifs Post-Lancement Sprint 4 - Epic Breakdown

## Overview

Correctifs et améliorations remontés le 05/04/2026 (soir). Couvre : standardisation des champs du formulaire de publication de mission (durée et lieu) et option de visibilité de l'âge pour les Faces.

## Requirements Inventory

### Functional Requirements

FIX4-FR1: La durée de tournage dans le formulaire de publication de mission doit être un select avec des presets (comme pour le booking)
FIX4-FR2: Le lieu de tournage dans le formulaire de publication de mission doit être un select avec les villes du Bénin
FIX4-FR3: Les Faces doivent pouvoir choisir si leur âge est affiché publiquement ou non

## Epic & Story Breakdown

---

### Epic FIX-7: Standardisation formulaire de publication de mission

**Goal:** Remplacer les champs texte libre "durée de tournage" et "lieu de tournage" du formulaire de publication de mission par des selects standardisés pour améliorer la qualité des données.

**Priority:** Haute — impact direct sur la cohérence des données de mission.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-7.1 | Remplacer la durée de tournage par un select avec presets | FIX4-FR1 | Haute |
| FIX-7.2 | Remplacer le lieu de tournage par un select de villes béninoises | FIX4-FR2 | Haute |

---

#### FIX-7.1: Remplacer la durée de tournage par un select avec presets

**Description:** Le champ "durée" du formulaire de publication de mission est actuellement un champ texte libre (string max 100 chars), ce qui entraîne des valeurs inconsistantes ("2 jours", "2j", "48h", etc.). Le remplacer par un select avec des presets de durée identiques à ceux utilisés dans le formulaire de booking (`BookingFormSheet.vue`), avec une option "Personnalisé" pour les durées hors presets.

**Acceptance Criteria:**
- Le champ "Durée du tournage" est un `<select>` avec les mêmes presets que le booking : Demi-journée (4h), 1 jour (8h), 1,5 jours (12h), 2 jours (16h), 2,5 jours (20h), 3 jours (24h), 3,5 jours (28h), 4 jours (32h), 4,5 jours (36h), 5 jours (40h), Personnalisé (> 5 jours)
- L'option "Personnalisé" affiche un champ numérique pour saisir le nombre de jours (comme dans le booking)
- La valeur stockée en base reste le champ `duree` (string) mais au format standardisé (ex: "4h", "8h", "16h", "6 jours")
- Les missions existantes avec des valeurs texte libre continuent de s'afficher correctement (pas de migration destructrice)
- Le formulaire d'édition de mission utilise le même select
- La validation frontend et backend accepte les nouvelles valeurs standardisées

---

#### FIX-7.2: Remplacer le lieu de tournage par un select de villes béninoises

**Description:** Le champ "lieu" du formulaire de publication de mission est actuellement un champ texte libre (string max 150 chars). Le remplacer par un select utilisant la même liste standardisée de villes du Bénin (`BENIN_CITY_OPTIONS` de `shared/constants/beninCities.ts`) déjà utilisée pour le profil Face.

**Acceptance Criteria:**
- Le champ "Lieu du tournage" est un `<select>` avec la liste des 84 villes/communes du Bénin (même liste que `BENIN_CITY_OPTIONS`)
- Les missions existantes avec des valeurs texte libre continuent de s'afficher correctement
- Le formulaire d'édition de mission utilise le même select
- La validation frontend et backend accepte les nouvelles valeurs standardisées
- L'affichage public des missions montre la ville sélectionnée

---

### Epic FIX-8: Visibilité de l'âge des Faces

**Goal:** Permettre aux Faces de contrôler si leur âge est affiché publiquement sur leur profil.

**Priority:** Moyenne — amélioration de la vie privée, pas de bug bloquant.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-8.1 | Ajouter un toggle de visibilité de l'âge pour les Faces | FIX4-FR3 | Moyenne |

---

#### FIX-8.1: Ajouter un toggle de visibilité de l'âge pour les Faces

**Description:** Actuellement l'âge de la Face (calculé depuis `date_naissance`) est toujours affiché publiquement dans le profil public détaillé et dans le résumé candidature/booking. Les Faces doivent pouvoir choisir de masquer leur âge via un toggle dans leur profil.

**Acceptance Criteria:**
- Un toggle "Afficher mon âge publiquement" est disponible dans la section informations personnelles du profil Face (`PersonalInfoForm.vue`)
- Par défaut, l'âge est affiché (toggle activé) pour ne pas casser le comportement existant
- Quand le toggle est désactivé, l'âge n'apparaît plus sur :
  - Le profil public de la Face (`PublicFaceProfileView.vue`)
  - Le résumé candidature/booking (`CandidateResumeSummary.vue`)
- L'âge reste toujours visible pour la Face elle-même dans son profil
- L'âge reste toujours visible pour les admins dans le panel admin
- Le champ `show_age` (boolean, default true) est ajouté à la table `faces` via migration
- L'API `PublicFaceProfileResource` respecte le flag `show_age` et retourne `null` si masqué
- L'API `FaceResource` (profil propre) retourne toujours l'âge indépendamment du flag
