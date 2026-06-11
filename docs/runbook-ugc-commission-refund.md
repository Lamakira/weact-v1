# Runbook — Remboursement manuel de la commission UGC

**Audience :** Opérateurs / on-call / support traitant les demandes de remboursement de commission UGC (Producteur non servi : deal refusé, fenêtre d'acceptation expirée, mission terminée sans participant).

**Scope :** Commission UGC uniquement (`bookings.commission_ugc` / `missions.commission_ugc`). Ce runbook ne couvre **pas** les remboursements cash/escrow (`EscrowService::refund`, flux booking standard) ni les abonnements Face (`FaceSubscription` — son `transaction.refunded` reste un `Log::critical` à revue admin).

---

## 1. Contexte — pourquoi le remboursement est manuel (spike OI-2)

Le SDK `fedapay/fedapay-php ^0.4.7` n'expose **aucune** méthode `refund()` — seuls les status-checkers `wasRefunded()` / `wasPartiallyRefunded()` existent (vérifié `vendor/fedapay/fedapay-php/lib/Transaction.php:47-69` ; `grep -rniE "function refund" vendor/fedapay/fedapay-php/lib/` → vide). Conclusion du spike OI-2 (story 1.5, AC11) : le remboursement est un **flux OPS orchestré**, pas un refund automatique.

Le système fait tout sauf le mouvement d'argent :

1. **Détecte** (refus Face / fenêtre expirée / mission sans participant) ;
2. **Trace** — pose `commission_refund_requested_at` + `commission_refund_reason` (idempotent) ;
3. **Alerte** — mail `UgcRefundRequestedMail` à `config('app.admin_email')` + notification in-app Producteur (`ugc_commission_refund_requested`) ;
4. **L'ops rembourse** via le dashboard FedaPay (ce runbook, §3) ;
5. **Règle** — le webhook `transaction.refunded` pose `commission_refunded_at` (+ `FinancialEvent refund` côté booking) et notifie le Producteur (`ugc_commission_refunded`).

> **Ré-évaluation future :** si une version ultérieure du SDK (ou l'API REST FedaPay directe) expose un refund programmatique confirmé côté plateforme, ré-évaluer l'automatisation du pas §3. Hors-MVP à date.

## 2. Déclencheurs et cycle de vie

| Déclencheur | Chemin code | Raison (`commission_refund_reason`) | Statut owner après détection |
| --- | --- | --- | --- |
| La Face refuse un deal payé (`commission_paid`) | `BookingService::refuse` → hook post-commit `UgcRefundService::requestRefundForBooking` | `refused` | booking `refused` |
| Fenêtre d'acceptation expirée (défaut 7 j après `commission_paid_at`, config `ugc.acceptance_window_days`) | cron hourly `ugc:expire-unaccepted-deals` → `expireBookingPastAcceptanceWindow` | `acceptance_window_expired` | booking `expired` |
| Mission UGC publiée + payée, deadline passée, **zéro** candidature engagée (`confirmed`/`in_progress`/`completed`) | même cron → `closeMissionPastDeadlineWithoutEngagement` | `mission_deadline_expired` | mission `closed` |
| Paiement orphelin (paiement arrivé sur un owner terminal) | `Log::critical` de `UgcCommissionPaymentService` — **pas de demande système** | — (aucune colonne posée) | inchangé — voir §6 |

Cycle de vie des colonnes (sur `bookings` ET `missions`) :

```
commission_paid_at  →  commission_refund_requested_at (+ reason)  →  commission_refunded_at
   (settlement 1.5)        (détection, mail ops, notif)               (webhook transaction.refunded)
```

- Une demande **en attente** = `commission_refund_requested_at IS NOT NULL AND commission_refunded_at IS NULL`.
- Tout est **idempotent** : refus + cron + replays de webhook ne créent jamais de double demande ni de double `FinancialEvent`.
- Une mission avec ≥ 1 engagement à la deadline n'est **jamais** remboursée (la commission de publication est consommée — D-2.5.d).

## 3. Procédure ops pas-à-pas (cas nominal)

1. **Réception du mail** « Remboursement commission UGC à effectuer — Booking/Mission #id — N FCFA » sur `admin_email`. Il contient : type + id de l'owner, produit/titre, Producteur, montant `commission_ugc`, **`fedapay_transaction_id`** et la raison.
2. **Vérifier la demande en base** (optionnel mais recommandé) :
   ```sql
   -- Booking
   SELECT id, status, commission_ugc, fedapay_transaction_id,
          commission_refund_requested_at, commission_refund_reason, commission_refunded_at
   FROM bookings WHERE id = :booking_id;
   -- Mission
   SELECT id, status, commission_ugc, fedapay_transaction_id,
          commission_refund_requested_at, commission_refund_reason, commission_refunded_at
   FROM missions WHERE id = :mission_id;
   ```
   Attendu : `commission_refund_requested_at` non-null, `commission_refunded_at` null.
3. **Retrouver la transaction dans le dashboard FedaPay** par son id = `fedapay_transaction_id` (clé de recherche du mail). Vérifier que le statut est `approved` et que le montant correspond à `commission_ugc`.
4. **Effectuer le remboursement** depuis le dashboard FedaPay — **toujours un remboursement TOTAL de la transaction, jamais partiel**. Un `transaction.refunded` dont le statut contient `partially_refunded` est **refusé** par le webhook (`Log::critical`, pas de settlement) : la demande reste en attente (§5) jusqu'au remboursement du solde, dont le webhook portera le statut `refunded` complet.
5. **Vérifier le settlement automatique** : FedaPay émet `transaction.refunded` ; sous quelques minutes le webhook doit poser `commission_refunded_at` (re-runner la requête du pas 2) et le Producteur reçoit la notification `ugc_commission_refunded`. Côté booking, un `FinancialEvent` `type='refund'` au montant `commission_ugc` est enregistré :
   ```sql
   SELECT id, type, amount, fedapay_ref, created_at
   FROM financial_events
   WHERE booking_id = :booking_id AND type = 'refund';
   ```
6. **Si `commission_refunded_at` reste null après ~30 min** → le webhook n'est pas arrivé (ou a été rejeté en amont). Passer en §4.

## 4. Réconciliation manuelle si le webhook n'arrive pas

D'abord diagnostiquer : `SELECT * FROM fedapay_webhook_events ORDER BY id DESC LIMIT 20;` — si l'événement `transaction.refunded` est présent mais `status != 'processed'`, inspecter `laravel.log` (le settlement est no-throw : tout état inattendu est loggé, jamais propagé). S'il n'est jamais arrivé, règler à la main via tinker — les settlements sont **idempotents**, un webhook tardif rejouera en no-op :

```php
// php artisan tinker
use App\Models\Booking;
use App\Models\Mission;
use App\Services\Ugc\UgcRefundService;

$service = app(UgcRefundService::class);

// Booking : pose commission_refunded_at + FinancialEvent refund + notification Producteur
$booking = Booking::find($bookingId);
$service->markBookingCommissionRefunded($booking, (string) $booking->fedapay_transaction_id);

// Mission : pose commission_refunded_at + notification Producteur (pas de FinancialEvent — audit par colonnes)
$mission = Mission::find($missionId);
$service->markMissionCommissionRefunded($mission, (string) $mission->fedapay_transaction_id);
```

> Le 2ᵉ argument est la référence FedaPay ; utiliser la référence réelle de la transaction remboursée si elle diffère de l'id. **Ne réconcilier qu'après avoir vérifié dans le dashboard FedaPay que le remboursement a réellement été effectué** — ces méthodes écrivent les livres, elles ne bougent pas d'argent.

## 5. Requêtes de suivi

**Demandes en attente** (à traiter — l'ops n'a pas encore remboursé, ou le webhook n'est pas revenu) :

```sql
SELECT 'booking' AS kind, id, status, commission_ugc, fedapay_transaction_id,
       commission_refund_requested_at, commission_refund_reason
FROM bookings
WHERE commission_refund_requested_at IS NOT NULL AND commission_refunded_at IS NULL
UNION ALL
SELECT 'mission', id, status, commission_ugc, fedapay_transaction_id,
       commission_refund_requested_at, commission_refund_reason
FROM missions
WHERE commission_refund_requested_at IS NOT NULL AND commission_refunded_at IS NULL
ORDER BY commission_refund_requested_at;
```

**Demandes manquées** (le système aurait dû poser une demande mais ne l'a pas fait — échec no-throw loggé en critical, à réconcilier en appelant `requestRefundForBooking`/`requestRefundForMission` via tinker) :

```sql
-- Bookings UGC refusés/expirés, encaissés (FinancialEvent payment_confirmed), sans demande
SELECT b.id, b.status, b.commission_ugc, b.fedapay_transaction_id
FROM bookings b
INNER JOIN financial_events fe ON fe.booking_id = b.id AND fe.type = 'payment_confirmed'
WHERE BINARY b.type_contenu = 'UGC'
  AND b.status IN ('refused', 'expired')
  AND b.commission_refund_requested_at IS NULL;

-- Missions UGC closes, payées, sans engagement ni demande
SELECT m.id, m.status, m.commission_ugc, m.fedapay_transaction_id
FROM missions m
WHERE m.type_mission = 'ugc'
  AND m.status = 'closed'
  AND m.commission_paid_at IS NOT NULL
  AND m.commission_refund_requested_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM candidatures c
      WHERE c.mission_id = m.id
        AND c.status IN ('confirmed', 'in_progress', 'completed')
  );
```

> Faux positifs possibles côté mission : une mission close par le flux normal (capacité atteinte puis engagements terminés/annulés plus tard) peut matcher. Vérifier l'historique des candidatures (`SELECT status, COUNT(*) FROM candidatures WHERE mission_id = :id GROUP BY status`) avant de demander un remboursement.

**Remboursements hors-procédure détectés** (refund FedaPay arrivé sans demande locale — `Log::critical` « remboursement FedaPay reçu SANS demande locale ») : le settlement a été enregistré quand même (les livres suivent FedaPay) ; vérifier pourquoi l'ops a remboursé sans mail et documenter dans le ticket.

## 6. Paiements orphelins (Log::critical de la story 1.5)

`UgcCommissionPaymentService` logge en **critical** quand un paiement FedaPay arrive sur un owner non-payable (booking refusé/expiré/annulé entre l'initiation et le webhook, mission plus `pending_payment`) :

- « UGC: paiement reçu sur un booking non-payable — remboursement ops requis » (`UgcCommissionPaymentService.php`, branche booking) ;
- « UGC: paiement reçu sur une mission non-publiable — remboursement ops requis » (branche mission).

Ces cas n'ont **pas de demande système** (`commission_refund_requested_at` reste null — câbler l'auto-demande dans la transaction de settlement serait un anti-pattern, D-2.5.i). Volume attendu ≈ 0. Procédure :

1. **Détection** — alerting sur les deux messages critical ci-dessus, plus une requête de balayage :
   ```sql
   -- Bookings terminaux ayant pourtant encaissé (payment_confirmed) sans cycle refund
   SELECT b.id, b.status, fe.amount, fe.fedapay_ref, fe.created_at
   FROM bookings b
   INNER JOIN financial_events fe ON fe.booking_id = b.id AND fe.type = 'payment_confirmed'
   WHERE BINARY b.type_contenu = 'UGC'
     AND b.status IN ('cancelled', 'refused', 'expired')
     AND b.commission_refund_requested_at IS NULL
     AND b.commission_refunded_at IS NULL;
   ```
   (Le critical mission n'écrit aucun event — partir du log pour retrouver `mission_id` + `fedapay_ref`.)
2. **Remboursement manuel** dans le dashboard FedaPay par `fedapay_transaction_id` (même geste que §3.3-3.4) — il n'y a **pas eu de mail**, le déclencheur est le log/balayage.
3. **Settlement** : le webhook `transaction.refunded` arrivera sur l'owner et posera `commission_refunded_at` avec un `Log::critical` « SANS demande locale » attendu (booking : statut métier inchangé ; mission encore `published` : clôture forcée). Si le webhook n'arrive pas → §4.
4. **Notifier le Producteur** out-of-band si nécessaire (la notification automatique `ugc_commission_refunded` part au settlement).

## 7. Note de déploiement (premier rollout de la story 2.5)

La migration backfille `bookings.commission_paid_at` depuis l'historique des `FinancialEvent payment_confirmed`. Conséquence : **au premier tick du cron hourly**, tout booking UGC `commission_paid` dont l'encaissement date de plus de `ugc.acceptance_window_days` (7 j) est expiré d'un coup — une demande de remboursement, un mail ops et une notification Producteur **par booking**. Avant d'activer le scheduler en production :

1. Mesurer l'encours : `SELECT COUNT(*) FROM bookings WHERE status = 'commission_paid' AND BINARY type_contenu = 'UGC' AND commission_paid_at <= NOW() - INTERVAL 7 DAY;`
2. Si le volume est non trivial, traiter ces deals au cas par cas (relance Producteur/Face ou expiration assumée) **avant** le premier run, ou élargir temporairement `acceptance_window_days` (`config/ugc.php`) pour lisser.
3. Prévenir l'ops du burst de mails attendu au premier tick.

---

**Incidents :** pour tout remboursement manuel, garder le grep des logs (booking/mission id, `fedapay_ref`), un dump des lignes affectées avant/après, et ouvrir un ticket tagué `incident/ugc-refund` avec la raison racine.
