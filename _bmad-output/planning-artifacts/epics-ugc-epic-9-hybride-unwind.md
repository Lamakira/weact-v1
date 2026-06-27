# Épic 9 — Dénouement hybride post-acceptation (escrow & slots)

**Status:** ready
**Créé:** 2026-06-27 (issu de la rétro épic 8, action item AI-2)
**Branche cible:** feat/ugc-epic-8/* (ou nouvelle branche dédiée)

## Contexte & motivation

La refonte mission UGC (épic 8) a livré le cycle de candidature explicite + le règlement hybride par-Face (escrow sur `mission_payment_candidatures`, paiement FedaPay à l'acceptation). La rétro épic 8 (`ugc-epic-8-retro-2026-06-27.md`, AI-2) + les defers `deferred-work.md` (8-2, 8-4) ont identifié un **trou valeur-cœur** : l'escrow Face `Locked` et le slot de capacité **ne sont pas dénoués** sur plusieurs chemins d'abandon/annulation **POST-acceptation** d'une candidature mission UGC hybride. C'est l'**analog MISSION du gate booking `5-0`** — mais la mission **diverge volontairement** : la Face PEUT se retirer avec remboursement post-acceptation (livré 8-4, D-8.4.h), on ne bloque donc pas, on **dénoue**.

**État vérifié au code (2026-06-27) :**
- ✅ Déjà branché (8-4) : `refundUgcCandidatureEscrow` (escrow `Locked→Refunded`, net au Producteur, idempotent) sur la décline Face (`Face\CandidatureController::cancel:431-441`), la suspension (`UgcSuspensionService:148-149`), le sweep deadline tunnel (`ProcessUgcDeadlinesCommand`).
- 🔴 Trous restants :
  - **(a) Face fantôme** : candidature hybride `accepted` (escrow `Locked`) dont la Face ne reconfirme **ni** ne décline **jamais**. Aucune deadline de reconfirmation ; `ExpireUnacceptedUgcDealsCommand` compte `Accepted` comme engagement (`:62`) + ne matche que `Published` + product-only (`whereNotNull('commission_paid_at')`). ⇒ cash immobilisé à vie, slot consommé.
  - **(b) Mission supprimée avec Faces acceptées** : `MissionService::cancelActiveCandidatesOnDelete:171-215` annule les candidatures mais ne dénoue jamais l'escrow ; le hard-delete cascade FK → argent orphelin (Producteur jamais remboursé, 0 `FinancialEvent`).
  - **(c) Slot jamais réouvert** : aucun chemin de libération (décline, suspension) ne rouvre une mission auto-fermée `Closed→Published`.

## Décisions produit verrouillées (PO Amakira, 2026-06-27)

- **D-E9.1 — Deadline de reconfirmation = 48 heures** après l'acceptation. Au-delà : récupération auto du slot (escrow remboursé au Producteur pour l'hybride).
- **D-E9.2 — Annulation manuelle Producteur AJOUTÉE** : le Producteur peut libérer une candidature acceptée précise (se rembourser, libérer le slot) sans supprimer toute la mission.
- **D-E9.3 — Réouverture automatique** `Closed→Published` quand un slot se libère (sweep, annulation Producteur, décline Face), tant que la `date_limite_candidature` n'est pas dépassée.
- **D-E9.4 — Money model inchangé** : remboursement = net escrow `montant_face_recoit` au Producteur (WeAct garde sa commission), via `refundUgcCandidatureEscrow` déjà codé (RH.2/8-4). Aucune nouvelle décision argent.

## Functional Requirements

- **FR1** — Une candidature `accepted` non reconfirmée 48 h après l'acceptation est balayée automatiquement : escrow remboursé au Producteur (hybride), candidature `Cancelled`, slot libéré + mission réouverte si `Closed`. Notification Face (place expirée) + Producteur (remboursé, hybride).
- **FR2** — Le Producteur peut libérer manuellement une candidature `accepted` (endpoint dédié) : même dénouement (refund hybride + `Cancelled` + slot libéré + réouverture).
- **FR3** — La suppression d'une mission dénoue l'escrow de chaque candidature engagée hybride (refund `Locked`, markFailed `Pending` in-flight) **avant** le hard-delete.
- **FR4** — Toute libération de slot (sweep, annulation Producteur, décline Face existante) rouvre la mission `Closed→Published` si la capacité repasse sous `nombre_faces_voulu` et la deadline n'est pas dépassée.
- **FR5** — Le compteur d'engagement (`[Accepted,Confirmed,InProgress,Completed]`, dupliqué sur 3-4 sites) est factorisé en un helper partagé, utilisé par l'auto-close ET l'auto-reopen (cohérence).

## Non-Functional / contraintes

- **NFR1** — Idempotence sous lock sur tous les dénouements (réutiliser le pattern `lockForUpdate` + garde `escrow_status === Locked`). No-throw sur le sweep (cf. `project_fedapay_webhook_no_throw`).
- **NFR2** — Non-régression stricte : produit-seul (accept gratuit), flux cash (`MissionPayment`, `ReconcileStaleSelectionsCommand`), booking (5-0, `EscrowService`), tunnel/suspension (8-4) inchangés. Le dénouement hybride réutilise les `*UgcCandidatureEscrow` sans modifier les chemins existants (sauf retrofit réouverture sur la décline Face).
- **NFR3** — Atomicité : settlement dans la même transaction que le flip de statut (calque `UgcRefundService::expireBookingPastAcceptanceWindow`).

## Stories

### ugc-9-1 — Dénouement hybride post-acceptation (BACKEND) 🔴
Deadline reconfirmation 48 h + sweep cron + endpoint release Producteur + unwind suppression-mission + réouverture auto + factorisation capacité. Réutilise `refundUgcCandidatureEscrow`/`markUgcMissionCandidatureFailed`. Migration `accepted_at` + config `reconfirm_window_hours`. **BACKEND SEUL.**

**Acceptance Criteria (BDD) :**
- **Given** une candidature mission UGC hybride `accepted` (escrow `Locked`) dont `accepted_at` remonte à > 48 h sans reconfirmation **When** le cron `ugc:expire-unreconfirmed-candidatures` tourne **Then** l'escrow est remboursé au Producteur (`Locked→Refunded`, net `montant_face_recoit`), la candidature passe `Cancelled`, le slot est libéré et la mission réouverte `Closed→Published` (deadline non dépassée) ; notifications Face + Producteur.
- **Given** une candidature `accepted` < 48 h **When** le cron tourne **Then** elle n'est PAS balayée (idempotent, borné par `accepted_at`).
- **Given** une candidature produit-seul `accepted` non reconfirmée > 48 h **When** le cron tourne **Then** le slot est libéré + mission réouverte, **sans** mouvement d'argent (pas d'escrow).
- **Given** un Producteur propriétaire **When** il appelle `POST /producer/candidatures/{candidature}/release` sur une candidature hybride `accepted` **Then** escrow remboursé + `Cancelled` + slot libéré + réouverture ; **403** pour un non-propriétaire ; **400/422** si la candidature n'est pas `accepted`.
- **Given** une mission hybride avec ≥1 candidature acceptée (escrow `Locked`) **When** le Producteur supprime la mission **Then** chaque escrow `Locked` est remboursé et chaque entry `Pending` in-flight est markFailed **avant** le hard-delete (0 argent orphelin).
- **Given** une candidature hybride `Pending` portant une entry escrow `Pending` in-flight **When** la Face annule sa candidature **Then** l'entry in-flight est supprimée (slot in-flight libéré).
- **Given** une mission hybride `Closed` à capacité **When** un slot se libère (sweep/release/décline) et la deadline n'est pas dépassée **Then** la mission repasse `Published`.
- **Given** les flux existants (produit-seul, cash, booking 5-0, tunnel/suspension 8-4, webhook) **When** cette story est livrée **Then** ils sont inchangés (non-régression).

**Source hints :** `MissionPaymentService` (`refundUgcCandidatureEscrow:1177`, `markUgcMissionCandidatureFailed:1038`, `markUgcMissionCandidaturePaid:913`, `notifySafely:1259`), `ExpireUnacceptedUgcDealsCommand` + `UgcRefundService::expireBookingPastAcceptanceWindow:248` (pattern), `MissionService::cancelActiveCandidatesOnDelete:171`/`reopenMission:281`, `ReopenMissionRequest`, `Producer\CandidatureController` (accept/reject + ctor) + `routes/api/producer.php:106-118`, `Face\CandidatureController::cancel:410`, `config/ugc.php`, `candidatures` migration + `CandidatureStatus`, fixtures `UgcMissionHybridSettlementTest:546-619`.

### ugc-9-2 — Dénouement hybride : surfaces UI (FRONTEND) 🟢
Mince. (a) Bouton Producteur « Libérer la place » sur une carte candidature `accepted` (appelle `release`, confirmation, refresh). (b) Libellé Face « Reconfirmez avant le {date}, sinon votre place sera libérée » sur la candidature acceptée (calculé depuis `accepted_at` + 48 h, exposé par la resource). **FRONTEND** (+ 1 additif resource exposant `accepted_at`/deadline si nécessaire).

**Acceptance Criteria (BDD) :**
- **Given** un Producteur sur la gestion des candidatures **When** une candidature est `accepted` **Then** un bouton « Libérer la place » est offert ; clic → confirmation → `release` → refresh (la candidature disparaît / repasse libre).
- **Given** une Face avec une candidature `accepted` non reconfirmée **When** elle voit le détail **Then** un libellé affiche la deadline de reconfirmation (`accepted_at` + 48 h) ; produit-seul ET hybride.
- **Given** les écrans existants **Then** non-régression (produit-seul accept, overlay paiement 8-5).

## References
- [Source: ugc-epic-8-retro-2026-06-27.md#Action items] (AI-2)
- [Source: deferred-work.md] (defers 8-2 « slot Accepted orphelin », 8-4 « markUgcMissionCandidaturePaid sans re-check engageabilité »)
- [Source: ugc-5-0-transverse-block-ugc-cancel.md] (analog booking — BLOCK ; mission diverge en DÉNOUEMENT)
- [Source: project_ugc_mission_redesign.md / project_ugc_module_epic.md]
