---
stepsCompleted: [1, 2, 3, 4]
status: 'complete'
completedAt: '2026-04-03'
totalEpics: 1
totalStories: 2
frCoverage: '3/3'
inputDocuments: []
project_name: 'WEACT - Correctifs Post-Lancement'
user_name: 'Lamakira'
date: '2026-04-03'
---

# WEACT - Correctifs Post-Lancement - Epic Breakdown

## Overview

Ce document regroupe les correctifs demandés par le client après le lancement en production de la plateforme WEACT. Il s'agit d'ajustements fonctionnels urgents, sans refonte d'architecture.

## Requirements Inventory

### Functional Requirements

FIX-FR1: La catégorie "Influenceur" ne doit plus être sélectionnable dans les paramètres du profil Face (en attente du processus de vérification)
FIX-FR2: Ajouter la catégorie "Voix off" aux catégories disponibles pour les Faces
FIX-FR3: Ajouter un champ "Numéro WhatsApp" au formulaire d'inscription Face, visible uniquement côté admin

## Epic & Story Breakdown

---

### Epic FIX-1: Correctifs Post-Lancement Client

**Goal:** Appliquer les ajustements demandés par le client suite au lancement en production.

**Priority:** Haute — corrections visibles par les utilisateurs finaux et demandées par le client.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-1.1 | Gestion des catégories Face (désactiver Influenceur + ajouter Voix off) | FIX-FR1, FIX-FR2 | Haute |
| FIX-1.2 | Champ WhatsApp à l'inscription Face (visible admin uniquement) | FIX-FR3 | Haute |

---

#### FIX-1.1: Gestion des catégories Face

**Description:** Désactiver la catégorie "Influenceur" des options disponibles dans les paramètres du profil Face (sans supprimer les données existantes) et ajouter la nouvelle catégorie "Voix off".

**Acceptance Criteria:**
- La catégorie "Voix off" apparaît dans les options du profil Face
- La catégorie "Influenceur" n'apparaît plus dans les options sélectionnables
- Les Faces existantes avec "Influenceur" conservent leur catégorie
- L'admin peut toujours voir "Influenceur" sur les profils existants

**Technical Notes:**
- Backend: ajouter `VOIX_OFF` à l'enum `FaceCategory`, filtrer `INFLUENCEUR` des endpoints options et de la validation
- Frontend: aucun changement (consomme dynamiquement l'API)
- Migration: nettoyage des données existantes (suppression de "influenceur" des categories JSON) — décision prise le 2026-04-03 car le futur workflow de vérification nécessitera de toute façon une re-soumission

---

#### FIX-1.2: Champ WhatsApp à l'inscription Face (visible admin uniquement)

**Description:** Ajouter un champ optionnel "Numéro WhatsApp" au formulaire d'inscription Face. Ce numéro est stocké en base et affiché uniquement dans la fiche admin de la Face. Il ne doit PAS apparaître sur le profil Face ni le profil public.

**Acceptance Criteria:**
- Le formulaire d'inscription Face a un champ "Numéro WhatsApp" (optionnel)
- Le numéro est stocké en base sur la Face
- L'admin voit le numéro WhatsApp dans la fiche détail d'une Face
- Le numéro n'apparaît PAS sur le profil Face ni le profil public

**Technical Notes:**
- Backend: migration ajout colonne `whatsapp_number`, inclusion dans RegisterFaceController et admin FaceController
- Frontend: champ input dans FaceRegistrationForm, affichage dans AdminFaceDetailPage
