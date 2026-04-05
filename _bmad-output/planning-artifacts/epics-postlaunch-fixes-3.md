---
stepsCompleted: [1, 2, 3, 4]
status: 'ready'
totalEpics: 3
totalStories: 4
project_name: 'WEACT - Correctifs Post-Lancement Sprint 3'
user_name: 'Lamakira'
date: '2026-04-05'
---

# WEACT - Correctifs Post-Lancement Sprint 3 - Epic Breakdown

## Overview

Correctifs et améliorations remontés le 05/04/2026. Couvre : standardisation des données de localisation, mise en avant des Faces par l'admin, validation temporelle du booking, et réorganisation du header pour les utilisateurs connectés.

## Requirements Inventory

### Functional Requirements

FIX3-FR1: Le champ "Ville" du profil Face doit être un select parmi les villes du Bénin (remplace la saisie libre)
FIX3-FR2: Le champ "Quartier" doit être supprimé du profil Face
FIX3-FR3: Si le pays de la Face n'est pas le Bénin, le champ "Ville" doit être désactivé/grisé
FIX3-FR4: L'admin doit pouvoir marquer une Face comme "en vedette" via un checkbox dans le détail admin
FIX3-FR5: Les Faces en vedette doivent apparaître en premier dans la liste publique et les carrousels
FIX3-FR6: Ordre d'affichage : Faces en vedette > photo + tarifs > photo seule > reste
FIX3-FR7: La Face ne doit pas pouvoir confirmer la réalisation d'un booking avant la date de début du tournage
FIX3-FR8: Le Producer ne doit pas pouvoir confirmer la réalisation d'un booking avant la date de début du tournage
FIX3-FR9: Les liens "Mes missions" et "Mes candidatures" ne doivent pas apparaître directement dans le header quand l'utilisateur est connecté — les regrouper dans un menu

## Epic & Story Breakdown

---

### Epic FIX-4: Standardisation localisation & mise en avant Faces

**Goal:** Standardiser les données de localisation des Faces avec un select de villes béninoises et permettre à l'admin de mettre en avant certaines Faces.

**Priority:** Haute — impact direct sur la qualité des données et la visibilité des Faces.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-4.1 | Remplacer ville/quartier par un select de villes béninoises | FIX3-FR1, FIX3-FR2, FIX3-FR3 | Haute |
| FIX-4.2 | Ajouter is_featured (admin) et modifier l'ordre d'affichage | FIX3-FR4, FIX3-FR5, FIX3-FR6 | Haute |

---

#### FIX-4.1: Remplacer ville/quartier par un select de villes béninoises

**Description:** Le champ "Ville" du profil Face est actuellement un champ texte libre, ce qui entraîne des variations d'orthographe (Abomey Calavi, Ab-Calavi, Abomey-Calavie...). Le remplacer par un select parmi les villes/communes du Bénin. Supprimer le champ "Quartier". Si le pays de la Face n'est pas le Bénin, le champ ville est désactivé.

**Acceptance Criteria:**
- Le champ "Ville" est un `<select>` avec la liste des villes/communes du Bénin
- Le champ "Quartier" est supprimé du formulaire et de la base de données
- Si le pays de la Face ≠ "Bénin", le select Ville est désactivé et vide
- Les données existantes sont migrées vers les valeurs standardisées (best-effort matching)
- Les filtres publics utilisent la même liste standardisée

---

#### FIX-4.2: Ajouter is_featured et modifier l'ordre d'affichage

**Description:** L'admin doit pouvoir cocher une Face comme "en vedette" depuis la page de détail admin. Les Faces marquées apparaissent en priorité dans la liste publique et les carrousels. L'ordre est : featured > photo + tarifs > photo seule > reste.

**Acceptance Criteria:**
- Un checkbox `is_featured` est visible dans le détail admin d'une Face
- L'admin peut cocher/décocher la mise en vedette
- L'ordre d'affichage sur `/faces` est : Faces en vedette d'abord, puis photo+tarifs, puis photo seule, puis le reste
- Les carrousels (dashboard Face/Producer) suivent le même ordre
- Le champ `is_featured` n'est pas visible par la Face elle-même

---

### Epic FIX-5: Correctif booking — validation date de réalisation

**Goal:** Empêcher la confirmation prématurée de la réalisation d'un booking avant le début du tournage.

**Priority:** Haute — permet actuellement de valider un booking avant même que le travail ait eu lieu.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-5.1 | Empêcher la confirmation de réalisation avant la date de tournage | FIX3-FR7, FIX3-FR8 | Haute |

---

#### FIX-5.1: Empêcher la confirmation de réalisation avant la date de tournage

**Description:** Actuellement, la Face et le Producer peuvent confirmer la réalisation d'un booking même si la date de tournage n'est pas encore arrivée. Il faut bloquer cette action côté backend et afficher un message explicatif côté frontend.

**Acceptance Criteria:**
- Le backend refuse la confirmation de réalisation si `shooting_date > now()`
- Le message d'erreur est clair : "La confirmation n'est possible qu'à partir du jour du tournage"
- Le bouton de confirmation est désactivé côté frontend avec un tooltip explicatif si la date n'est pas arrivée
- S'applique à la fois à la Face et au Producer

---

### Epic FIX-6: UX Header navigation connecté

**Goal:** Simplifier le header pour les utilisateurs connectés en regroupant les liens de navigation.

**Priority:** Moyenne — amélioration UX, pas de bug bloquant.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-6.1 | Réorganiser le header pour les utilisateurs connectés | FIX3-FR9 | Moyenne |

---

#### FIX-6.1: Réorganiser le header pour les utilisateurs connectés

**Description:** Les liens "Mes missions" et "Mes candidatures" apparaissent directement dans le header quand un utilisateur est connecté. Les regrouper dans le menu profil ou un autre mécanisme pour alléger le header.

**Acceptance Criteria:**
- Les liens "Mes missions" et "Mes candidatures" ne sont plus directement dans la barre de navigation du header
- Ils sont accessibles via le menu profil (dropdown) ou la sidebar du dashboard
- La navigation reste intuitive et accessible en 1-2 clics maximum
- Le header est visuellement plus épuré une fois connecté
