# Runbook — Remboursement de la commission UGC (crédit wallet)

**Audience :** Opérateurs / on-call / support traitant les remboursements de commission UGC (Producteur non servi : deal refusé, fenêtre d'acceptation expirée, mission terminée sans participant).

**Scope :** Commission UGC uniquement (`bookings.commission_ugc` / `missions.commission_ugc`). Ce runbook ne couvre **pas** les remboursements cash/escrow (`EscrowService::refund`, flux booking standard) ni les abonnements Face (`FaceSubscription` — son `transaction.refunded` reste un `Log::critical` à revue admin).

> 🎯 **Story 2.6 — SUPERSEDE le flux manuel FedaPay de la 2.5.** Le remboursement n'est plus un geste ops au dashboard FedaPay : c'est un **crédit wallet interne automatique et synchrone** (pattern maison `BookingCancellationRefund`). Il n'y a, en régime nominal, **rien à faire** pour l'ops — ce runbook sert au monitoring et à la réconciliation des rares échecs.

---

## 1. Contexte — pourquoi un crédit wallet (et plus un refund FedaPay)

La 2.5 réglait le remboursement par un **refund manuel au dashboard FedaPay** suivi du webhook `transaction.refunded`. Deux faits l'ont rendu obsolète :

1. **Le refund FedaPay est manuel ET MTN-only** (`docs.fedapay.com/dashboard/fr/refunds-fr` : « Le remboursement est uniquement possible via MTN Mobile Money »). Un Producteur ayant payé par **Moov** ou **carte** ne pouvait donc **pas** être remboursé. Le SDK `fedapay/fedapay-php ^0.4.7` n'expose par ailleurs **aucune** méthode `refund()` (spike OI-2).
2. **Le reste de WeAct rembourse déjà via crédit wallet** (annulation/no-show de booking → `WalletService::credit` + `WalletCreditMotif::BookingCancellationRefund`). Le flux de retrait wallet (`WithdrawalService`) tourne en prod.

**Décision (PO/finance/PL, 2026-06-13) :** la commission UGC non aboutie est **recréditée sur le portefeuille WeAct du Producteur**. La commission encaissée reste sur le solde FedaPay WeAct ; le crédit wallet crée la **dette** envers le Producteur ; le **money-out réel se fait au retrait** (flux `WithdrawalService` existant, éprouvé en prod).

Le système fait tout, en une seule transaction :

1. **Détecte** (refus Face / fenêtre expirée / mission sans participant) ;
2. **Règle dans le même appel** — pose `commission_refund_requested_at` + `commission_refund_reason` + `commission_refunded_at`, **crédite le portefeuille** du Producteur de `commission_ugc` (`WalletTransaction` libellée « Remboursement de la commission — deal UGC non abouti »), enregistre un `FinancialEvent refund` côté booking, et notifie le Producteur (`ugc_commission_refunded` → « créditée sur votre portefeuille WeAct ») ;
3. **Le Producteur retire** quand il veut via le flux de retrait standard (`/producer/wallet`).

> Plus de mail ops, plus de notification intermédiaire « remboursement en cours », plus de fenêtre asynchrone : le cycle « requested » de la 2.5 est supprimé.

## 2. Déclencheurs et cycle de vie

| Déclencheur | Chemin code | Raison (`commission_refund_reason`) | Statut owner après |
| --- | --- | --- | --- |
| La Face refuse un deal payé (`commission_paid`) | `BookingService::refuse` → hook post-commit `UgcRefundService::settleRefundForBooking` | `refused` | booking `refused` |
| Fenêtre d'acceptation expirée (défaut 7 j après `commission_paid_at`, config `ugc.acceptance_window_days`) | cron hourly `ugc:expire-unaccepted-deals` → `expireBookingPastAcceptanceWindow` → `settleRefundForBooking` | `acceptance_window_expired` | booking `expired` |
| Mission UGC publiée + payée, deadline passée, **zéro** candidature engagée (`confirmed`/`in_progress`/`completed`) | même cron → `closeMissionPastDeadlineWithoutEngagement` → `settleRefundForMission` | `mission_deadline_expired` | mission `closed` |
| Paiement orphelin (paiement arrivé sur un owner terminal) | `Log::critical` de `UgcCommissionPaymentService` — **pas de remboursement système** | — (aucune colonne posée) | inchangé — voir §6 |

Cycle de vie des colonnes (sur `bookings` ET `missions`) — **les 3 colonnes sont posées dans le même appel** :

```
commission_paid_at  →  [ commission_refund_requested_at + reason + commission_refunded_at ]  + crédit wallet
   (settlement 1.5)            (détection ET settlement synchrone — story 2.6)
```

- Tout est **idempotent** : refus + cron + re-appels ne créent jamais de double crédit wallet, ni de double `FinancialEvent`, ni de double notification. La garde est `commission_refunded_at !== null` (re-check sous lock).
- Une mission avec ≥ 1 engagement à la deadline n'est **jamais** remboursée (commission de publication consommée — D-2.5.d).
- Asymétrie d'audit (D-2.6.g) : `FinancialEvent refund` côté **booking** uniquement ; la mission est auditée par ses colonnes `commission_refund*`.

## 3. Cas nominal — rien à faire (monitoring)

En régime nominal, **aucune action ops**. Le crédit est automatique et le Producteur retire lui-même. Pour vérifier qu'un remboursement précis a bien eu lieu :

```sql
-- Booking
SELECT id, status, commission_ugc, commission_refund_requested_at,
       commission_refund_reason, commission_refunded_at
FROM bookings WHERE id = :booking_id;
-- Mission
SELECT id, status, commission_ugc, commission_refund_requested_at,
       commission_refund_reason, commission_refunded_at
FROM missions WHERE id = :mission_id;
```

Attendu : `commission_refunded_at` non-null. Le crédit wallet correspondant :

```sql
SELECT wt.id, wt.user_id, wt.booking_id, wt.amount, wt.description, wt.created_at
FROM wallet_transactions wt
WHERE wt.type = 'credit'
  AND wt.description = 'Remboursement de la commission — deal UGC non abouti'
  AND wt.user_id = :producer_user_id          -- bookings.producer_id directement ; pour une mission, résoudre via users.userable
ORDER BY wt.id DESC;
```

Côté booking, le `FinancialEvent` d'audit :

```sql
SELECT id, type, amount, fedapay_ref, created_at
FROM financial_events
WHERE booking_id = :booking_id AND type = 'refund';
```

Le Producteur voit le crédit dans **`/producer/wallet`** et le retire via le flux de retrait standard (`WithdrawalService`).

## 4. Réconciliation si un settlement a échoué

Les settlements sont **no-throw** : tout échec est loggé en `critical`, jamais propagé (la file n'est pas empoisonnée). Trois signaux à surveiller dans `laravel.log` :

- `UGC refund: échec expiration/settlement booking — réconciliation requise` / `UGC refund: échec clôture/settlement mission — réconciliation requise` — exception pendant l'expiration/clôture cron **ou** le crédit wallet. Le changement de statut ET le crédit sont dans **la même transaction** : un échec **annule tout** (rollback complet, booking resté `commission_paid` / mission restée `published`). **Le cron réessaie automatiquement au tick suivant** — n'intervenir manuellement que si le critical **persiste** plusieurs ticks (panne durable du wallet) ;
- `UGC refund: échec settlement wallet booking — réconciliation requise` / `… mission …` — exception pendant un règlement **hors-cron** (hook refuse Face, ou réconciliation tinker) : rollback complet, mais **pas de reprise auto** (le refus a déjà réussi côté API) → réconciliation manuelle via §4 ;
- `UGC refund: producteur introuvable pour la mission — settlement différé` — User producteur orphelin (mission) : `commission_refunded_at` **non posé**, aucun crédit, mission tout de même clôturée (réconciliation manuelle, AC2) ;
- `UGC refund: commission_ugc absente/invalide — rien à créditer` — owner (booking ou mission) encaissé mais commission nulle/0 (anomalie de données) : ni remboursé ni crédité (le contexte du log porte `booking_id` ou `mission_id`) ;
- `UGC refund: demande sans encaissement — rien à créditer` — appel sur un owner jamais encaissé (anomalie de données).

**Réconciliation** — ré-appeler la détection/settlement via tinker. C'est **idempotent** (un deal déjà réglé est un no-op) et **synchrone** (le crédit wallet est posé dans l'appel) :

```php
// php artisan tinker
use App\Models\Booking;
use App\Models\Mission;
use App\Enums\UgcRefundReason;
use App\Services\Ugc\UgcRefundService;

$service = app(UgcRefundService::class);

// Booking — choisir la raison selon le statut (refused / acceptance_window_expired)
$booking = Booking::find($bookingId);
$service->settleRefundForBooking($booking, UgcRefundReason::Refused);

// Mission — deadline sans engagement (force la clôture si encore Published)
$mission = Mission::find($missionId);
$service->settleRefundForMission($mission, UgcRefundReason::MissionDeadlineExpired);
```

> `settleRefundForMission` **force le statut `closed`** si la mission est encore `published` au moment du règlement (filet défensif : aucune policy mission ne garde `commission_refunded_at`, contrairement au booking) — une mission remboursée ne reste donc jamais découvrable/acceptable.

> Pour un **orphelin mission** (User producteur introuvable), corriger d'abord la donnée (rattacher le `User` `userable` au `Producer`, ou créditer manuellement le bon `users.id` via `WalletService::creditDirect` dans une `DB::transaction`), puis ré-appeler `settleRefundForMission`. Ne jamais créditer deux fois : vérifier `commission_refunded_at IS NULL` avant.

## 5. Requêtes de suivi

**Remboursements récents** (réglés — crédit wallet posé) :

```sql
SELECT 'booking' AS kind, id, status, commission_ugc, commission_refund_reason, commission_refunded_at
FROM bookings WHERE commission_refunded_at IS NOT NULL
UNION ALL
SELECT 'mission', id, status, commission_ugc, commission_refund_reason, commission_refunded_at
FROM missions WHERE commission_refunded_at IS NOT NULL
ORDER BY commission_refunded_at DESC;
```

**Settlements manqués** (le système aurait dû régler mais ne l'a pas fait — échec no-throw ou orphelin : à réconcilier via §4) :

```sql
-- Bookings UGC refusés/expirés, encaissés, NON remboursés
SELECT b.id, b.status, b.commission_ugc
FROM bookings b
WHERE BINARY b.type_contenu = 'UGC'
  AND b.status IN ('refused', 'expired')
  AND b.commission_paid_at IS NOT NULL
  AND b.commission_refunded_at IS NULL;

-- Missions UGC closes, payées, sans engagement, NON remboursées
SELECT m.id, m.status, m.commission_ugc
FROM missions m
WHERE m.type_mission = 'ugc'
  AND m.status = 'closed'
  AND m.commission_paid_at IS NOT NULL
  AND m.commission_refunded_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM candidatures c
      WHERE c.mission_id = m.id
        AND c.status IN ('confirmed', 'in_progress', 'completed')
  );
```

**Refund FedaPay hors-procédure détecté** (`Log::critical` « refund UGC inattendu — les refunds UGC se règlent désormais via wallet ») : un `transaction.refunded` est arrivé sur un owner UGC alors que les refunds passent désormais par le wallet. Le webhook a **refusé** de régler (pas de double crédit). Investiguer **pourquoi** un refund FedaPay a été déclenché manuellement (geste ops au dashboard à proscrire) et documenter dans le ticket.

## 6. Paiements orphelins (Log::critical de la story 1.5)

`UgcCommissionPaymentService` logge en **critical** quand un paiement FedaPay arrive sur un owner non-payable (booking refusé/expiré/annulé entre l'initiation et le webhook, mission plus `pending_payment`) :

- « UGC: paiement reçu sur un booking non-payable — remboursement ops requis » (branche booking) ;
- « UGC: paiement reçu sur une mission non-publiable — remboursement ops requis » (branche mission).

Ces cas n'ont **pas de remboursement système** (`commission_refund_requested_at` reste null). Volume attendu ≈ 0. Procédure :

1. **Détection** — alerting sur les deux messages critical, plus le balayage « settlements manqués » §5.
2. **Décider** : recréditer le Producteur via crédit wallet (cas standard — appeler `settleRefundForBooking`/`settleRefundForMission` via tinker, §4, qui pose les colonnes ET crédite), ou traiter en exception si le paiement doit être rejeté autrement.
3. **Notifier le Producteur** est automatique au settlement (`ugc_commission_refunded`).

## 7. Vérif data au déploiement 2.6 (one-shot)

La 2.6 ne contient **aucune migration de schéma** (colonnes et wallet existants). Une seule vérif **read-only** au déploiement : décompter les deals laissés **en attente par l'ancien flux 2.5** (`commission_refund_requested_at` posé mais `commission_refunded_at` toujours null — la demande était tracée mais jamais réglée par l'ops/webhook FedaPay) :

```sql
SELECT 'booking' AS kind, COUNT(*) AS pending
FROM bookings
WHERE commission_refund_requested_at IS NOT NULL AND commission_refunded_at IS NULL
UNION ALL
SELECT 'mission', COUNT(*)
FROM missions
WHERE commission_refund_requested_at IS NOT NULL AND commission_refunded_at IS NULL;
```

- **Attendu ≈ 0** : le refund 2.5 n'a jamais été exécuté en prod (confirmé PO, 2026-06-13).
- **Si > 0** : pour chaque deal, soit le régler une fois via crédit wallet (`settleRefundForBooking`/`settleRefundForMission` via tinker, §4 — idempotent, pose le crédit), soit, **s'il a déjà été remboursé au dashboard FedaPay sous l'ancien flux**, poser uniquement `commission_refunded_at = NOW()` à la main (et créditer le wallet **seulement si** l'argent n'a pas déjà été renvoyé, pour ne pas rembourser deux fois). Consigner le décompte et le traitement dans le ticket de déploiement.

---

**Incidents :** pour toute réconciliation, garder le grep des logs (booking/mission id), un dump des lignes affectées avant/après (colonnes `commission_refund*` + `wallet_transactions`), et ouvrir un ticket tagué `incident/ugc-refund` avec la raison racine.
