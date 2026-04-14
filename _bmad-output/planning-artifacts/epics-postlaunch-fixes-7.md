---
stepsCompleted: [1, 2, 3, 4]
status: 'complete'
completedAt: '2026-04-12'
totalEpics: 1
totalStories: 5
project_name: 'WEACT - Correctifs Post-Lancement Sprint 10'
user_name: 'Amakira'
date: '2026-04-12'
---

# WEACT - Correctifs Post-Lancement Sprint 10 - Epic Breakdown

## Overview

Incident de production sur le flux mission: la sélection des candidatures est commitée avant la création de transaction FedaPay. Quand FedaPay échoue, la mission reste en `pending_payment`, les candidatures sont mutées, et le producer n'a ni redirection checkout ni chemin propre de reprise.

## Requirements Inventory

### Functional Requirements

FIX19-FR1: Un échec d'initialisation du paiement mission ne doit laisser aucune mutation métier persistée si aucune transaction FedaPay exploitable n'a été créée
FIX19-FR2: Un paiement mission déjà initié et encore `pending` doit pouvoir être repris sans refaire la sélection
FIX19-FR3: Le frontend mission ne doit pas afficher un faux état `pending_payment` ni un spinner infini quand aucun checkout réel n'existe
FIX19-FR4: Le flux mission doit être idempotent et résistant aux doubles soumissions ou refreshs
FIX19-FR5: Les opérateurs doivent pouvoir diagnostiquer et corriger rapidement un échec de paiement mission via logs et procédure de récupération

## Epic & Story Breakdown

---

### Epic FIX-19: Résilience locale du paiement mission

**Goal:** Rendre le workflow mission robuste face aux échecs FedaPay afin qu'aucune mission ni candidature ne reste dans un état incohérent, tout en permettant la reprise contrôlée d'un paiement déjà initié.

**Priority:** Critique — affecte directement des producers en production et génère des remises en état manuelles.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FIX-19.1 | Compenser les mutations mission si l'initialisation paiement échoue | FIX19-FR1 | Critique |
| FIX-19.2 | Reprendre un checkout mission déjà initié | FIX19-FR2, FIX19-FR4 | Haute |
| FIX-19.3 | Empêcher les faux états pending côté frontend mission | FIX19-FR3 | Haute |
| FIX-19.4 | Ajouter observabilité et procédure de récupération mission | FIX19-FR5 | Haute |
| FIX-19.5 | Couvrir les chemins d'échec mission par des tests de non-régression | FIX19-FR1, FIX19-FR2, FIX19-FR3, FIX19-FR4 | Critique |

---

#### FIX-19.1: Compenser les mutations mission si l'initialisation paiement échoue

**Description:** Le backend doit traiter la confirmation de sélection et l'initialisation FedaPay comme une opération compensable. Si l'appel FedaPay échoue avant qu'une transaction exploitable soit persistée, la mission et toutes les candidatures reviennent à leur état pré-paiement.

**Acceptance Criteria:**
- Si FedaPay échoue avant l'obtention d'un `fedapay_transaction_id`, la mission reste ou revient à `published`
- Les candidatures sélectionnées reviennent à `pending`
- Les autres candidatures précédemment rejetées par le workflow reviennent à `pending`
- Aucun `mission_payment` ni `mission_payment_candidature` orphelin ne subsiste
- L'API renvoie une erreur métier exploitable au lieu d'une 500 générique

**Technical Notes:**
- Root cause: `MissionPaymentController::confirmAndPay()` enchaîne `confirmSelection()` puis `initiatePayment()` en deux étapes indépendantes
- Root cause: `MissionPaymentService::confirmSelection()` commit l'état métier avant l'appel externe FedaPay
- Fix attendu: orchestrateur compensable ou refactor du flux pour séparer préparation, appel externe, et finalisation

---

#### FIX-19.2: Reprendre un checkout mission déjà initié

**Description:** Si un `mission_payment` est déjà `pending` avec une transaction FedaPay existante, le producer doit pouvoir reprendre le checkout sans refaire la sélection ni dupliquer les effets métier.

**Acceptance Criteria:**
- Un `mission_payment` pending avec `fedapay_transaction_id` non nul retourne un `checkout_url` réutilisable
- Une double soumission ne crée pas de doublons de paiement ni de nouvelles mutations de candidature
- Un paiement pending déjà initié peut être repris après refresh ou retour sur la page
- Si la transaction distante est terminalement échouée, le système passe par un chemin de réinitialisation contrôlé avant nouvelle tentative

**Technical Notes:**
- S'aligner sur le pattern de résilience déjà appliqué côté `BookingService::initiatePayment()`
- Introduire une clé/idempotence mission si nécessaire

---

#### FIX-19.3: Empêcher les faux états pending côté frontend mission

**Description:** La page candidatures mission doit distinguer un paiement réellement en attente d'un échec d'initialisation. L'utilisateur ne doit plus voir de spinner infini si aucun checkout réel n'a été créé.

**Acceptance Criteria:**
- Le frontend ne démarre pas de polling si le backend n'a pas de paiement exploitable à suivre
- La sélection courante n'est pas perdue sur erreur d'initialisation paiement
- Le toast d'erreur est explicite et actionnable
- La bannière `Paiement en attente de confirmation` n'apparaît que quand un paiement peut réellement être confirmé

**Technical Notes:**
- Root cause: la page se base sur `mission.status === pending_payment` pour lancer le polling automatiquement
- Le contrat API doit exposer assez d'information pour distinguer `pending without transaction` de `pending with transaction`

---

#### FIX-19.4: Ajouter observabilité et procédure de récupération mission

**Description:** Le backend doit logger les échecs du flux mission avec suffisamment de contexte, et l'équipe doit disposer d'une procédure documentée pour remettre une mission en état sans improvisation.

**Acceptance Criteria:**
- Les logs d'échec contiennent au minimum `mission_id`, `producer_id`, `payment_id` si disponible, phase d'échec, résultat de compensation
- Un runbook de récupération mission documente les requêtes SQL de vérification et de remise en état
- Un incident similaire peut être diagnostiqué sans inspection manuelle extensive du code

**Technical Notes:**
- Se concentrer sur l'observabilité locale du flux mission, pas sur la correction de l'intégration FedaPay elle-même

---

#### FIX-19.5: Couvrir les chemins d'échec mission par des tests de non-régression

**Description:** Les chemins critiques du flux mission doivent être couverts par des tests backend et frontend pour éviter toute régression future sur les échecs FedaPay et les reprises de paiement.

**Acceptance Criteria:**
- Test backend: échec FedaPay avant transaction persistée restaure l'état initial mission/candidatures
- Test backend: reprise d'un checkout pending existant retourne une URL sans duplication
- Test backend: double soumission ne crée ni second paiement ni mutations supplémentaires
- Test frontend: pas de spinner infini en absence de transaction à suivre
- Test frontend: l'erreur d'initialisation conserve la sélection et affiche un message de réessai

**Technical Notes:**
- Priorité à `backend/tests/Feature/Mission/*` et aux composables/pages mission côté frontend
