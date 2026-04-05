---
stepsCompleted: [1, 2, 3, 4]
status: 'complete'
completedAt: '2026-04-03'
totalEpics: 1
totalStories: 2
project_name: 'WEACT - Correctifs Post-Lancement Sprint 2'
user_name: 'Lamakira'
date: '2026-04-03'
---

# WEACT - Correctifs Post-Lancement Sprint 2 - Epic Breakdown

## Overview

Bugs critiques remontés par un producer ayant reçu 100+ candidatures sur une mission nécessitant 6 Faces. Le workflow de sélection multi-Faces et paiement est cassé.

## Requirements Inventory

### Functional Requirements

FIX2-FR1: La sélection de candidatures par le producer doit être persistée lors du changement de page de pagination
FIX2-FR2: Le paiement pour un lot de Faces sélectionnées ne doit PAS auto-rejeter les candidatures restantes — le producer doit pouvoir revenir sélectionner et payer d'autres Faces

## Epic & Story Breakdown

---

### Epic FIX-2: Workflow de sélection multi-Faces et paiement

**Goal:** Permettre au producer de sélectionner et payer plusieurs Faces sur plusieurs pages de candidatures sans perte de sélection ni rejet automatique des candidatures restantes.

**Priority:** Critique — affecte directement les producers en production.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-2.1 | Persister la sélection de candidatures lors de la pagination | FIX2-FR1 | Haute |
| FIX-2.2 | Supprimer le rejet automatique des candidatures après paiement | FIX2-FR2 | Critique |

---

#### FIX-2.1: Persister la sélection de candidatures lors de la pagination

**Description:** Quand le producer sélectionne des Faces sur la page 1 des candidatures et navigue vers la page 2, les sélections de la page 1 doivent être conservées. Le producer doit voir combien de Faces il a sélectionnées au total.

**Acceptance Criteria:**
- La sélection de candidatures est conservée quand le producer change de page
- Le nombre total de sélections est affiché au producer
- Le producer peut désélectionner une Face depuis n'importe quelle page

**Technical Notes:**
- Root cause: `useMissionPayment.ts:27` — `selectedCandidatureIds` est un `ref` local
- Fix: persister l'état de sélection dans le composable de manière à ce qu'il survive au changement de page

---

#### FIX-2.2: Supprimer le rejet automatique des candidatures après paiement

**Description:** Quand le producer confirme et paye pour un lot de Faces, les candidatures restantes en statut `pending` ne doivent PAS être auto-rejetées. Le producer doit pouvoir revenir et sélectionner/payer d'autres Faces par la suite.

**Acceptance Criteria:**
- Après paiement d'un lot, les candidatures `pending` restantes gardent leur statut
- Le producer peut revenir sur la page des candidatures et sélectionner d'autres Faces
- La mission ne passe pas en statut `pending_payment` tant qu'il reste des candidatures `pending` non traitées (ou bien ce statut n'empêche plus la sélection d'autres Faces)
- Les Faces non sélectionnées dans le premier lot ne reçoivent PAS de notification de rejet

**Technical Notes:**
- Root cause: `MissionPaymentService.php:122-140` — rejette explicitement toutes les candidatures pending après confirmation
- Fix: supprimer le bloc de rejet automatique, permettre au producer de traiter les candidatures en plusieurs lots
