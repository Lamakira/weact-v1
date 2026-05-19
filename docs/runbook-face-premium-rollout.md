# Runbook — Rollout Face Premium (Sprint 14, FEATURE-FP-1.X)

**Audience :** ingénieur on-call WEACT déployant l'abonnement annuel Face Premium en production.

**Scope :** dérouler la mise en production des stories FEATURE-FP-1.1 → FEATURE-FP-1.10 : configuration des variables d'environnement, scheduler hourly, worker de queue, driver de broadcast, audit pré-lancement, smoke tests post-déploiement, et plan de rollback en 3 couches.

---

## 1. Scope & Audience

Ce runbook couvre :

- L'inventaire des fichiers livrés par les stories FP-1.1 → FP-1.10.
- Les variables d'environnement requises en production (paiement Fedapay, mail SMTP, broadcast Reverb, queue).
- Les entrées du scheduler Laravel et la procédure de vérification.
- L'audit pré-lancement read-only via `php artisan faces:audit-premium-readiness`.
- Les smoke tests post-déploiement (5 commandes concrètes).
- Le plan de rollback en 3 couches (pause scheduler / revert code / migrate:rollback).
- Le tableau release-bundle (commit par story FP-1.X).
- Les caveats opérationnels (defers connus, route vers les hardening stories).

Ce runbook **ne couvre pas** :

- Le rollout du frontend Face (la SubscriptionCard FP-1.7 est déjà mergée et n'a pas de feature flag — elle est visible dès que le backend est en ligne).
- La communication marketing / customer support pour les Faces qui verront leur médias passer en "locked" au lancement (briefer le support via la sortie `--detailed` de l'audit).
- Les runbooks de mission (cf. `docs/runbook-mission-payment-recovery.md`) et d'email infra (cf. `docs/runbook-email-notifications.md`) — dépendances externes assumées en place.

---

## 2. État actuel (post-FP-1.9)

Inventaire de la surface FP-1.X mergée au moment où FP-1.10 est créée :

**Migrations (3) — schéma final, aucune migration supplémentaire dans FP-1.10 :**

- `backend/database/migrations/2026_05_11_083606_create_face_subscriptions_table.php` — table `face_subscriptions` (FP-1.1).
- `backend/database/migrations/2026_05_12_000000_create_face_subscription_audits_table.php` — table `face_subscription_audits` (FP-1.4).
- `backend/database/migrations/2026_05_15_000000_add_reminder_sent_at_columns_to_face_subscriptions_table.php` — colonnes nullables `reminder_30d_sent_at` + `reminder_7d_sent_at` (FP-1.9).

**Services backend :**

- `App\Services\FaceEntitlementService` — source de vérité de l'entitlement premium (FP-1.1). Source unique consommée par tous les Resources publics.
- `App\Services\FaceSubscriptionAdminService` — opérations admin (activate / cancel / extend / correct) avec écriture transactionnelle dans `face_subscription_audits` (FP-1.4).
- `App\Services\FaceSubscriptionPaymentService` — initiation paiement Fedapay + activation idempotente sur webhook (FP-1.5).

**Events / Listeners / Mailables (FP-1.9) :**

- 3 events : `FaceSubscriptionActivated`, `FaceSubscriptionExpired`, `FaceSubscriptionCancelled`.
- 5 listeners enregistrés via `#[AsEventListener]` : 3 in-app (`NotifyFaceOnSubscription{Activated|Expired|Cancelled}`) + 2 email (`SendFaceSubscription{Activated|Expired}Email` ; cancellation est in-app uniquement, décision produit FP-1.9).
- 3 mailables : `FaceSubscriptionActivatedMail`, `FaceSubscriptionExpiredMail`, `FaceSubscriptionRenewalReminderMail` + 3 blade views correspondantes sous `backend/resources/views/emails/face-subscriptions/`.

**Commandes artisan (3 au total post-FP-1.10) :**

- `subscriptions:expire-faces` — hourly, expiration des Active dont `expires_at <= now()` (FP-1.8).
- `subscriptions:remind-face-renewals` — hourly, fenêtres 30j + 7j idempotentes (FP-1.9).
- `faces:audit-premium-readiness` — **on-demand uniquement**, audit read-only (FP-1.10, ce runbook).

**Frontend :**

- 1 composant `SubscriptionCard` à `/face/profile` (FP-1.7) consommant `/api/v1/face/subscription-status` (FP-1.3) avec polling 5 s + écoute du canal Reverb privé.

---

## 3. Variables d'environnement requises

À mettre à jour dans le `.env` de production (les valeurs `<...>` sont à remplir avec les credentials prod, **jamais commit dans `.env.example`**) :

```env
# --- Face Premium pricing (FP-1.5) ---
FACE_PREMIUM_ANNUAL_AMOUNT=50000
FACE_PREMIUM_ANNUAL_CURRENCY=XOF
FACE_PREMIUM_ANNUAL_PROVIDER=fedapay

# --- Fedapay credentials (FP-1.5) ---
FEDAPAY_SECRET_KEY=<secret>
FEDAPAY_PUBLIC_KEY=<public>
FEDAPAY_ENVIRONMENT=live
FEDAPAY_WEBHOOK_SECRET=<webhook-secret>

# --- URLs prod (référencées par les mailables FP-1.9) ---
FRONTEND_URL=https://app.weact.bj
APP_URL=https://api.weact.bj

# --- Queue (mailables FP-1.9 sérialisés en queue) ---
QUEUE_CONNECTION=database

# --- Broadcast (NotificationCreated FP-1.9 broadcasté sur canal privé Reverb) ---
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<reverb-app-id>
REVERB_APP_KEY=<reverb-key>
REVERB_APP_SECRET=<reverb-secret>
REVERB_HOST=<reverb-host>
REVERB_PORT=443
REVERB_SCHEME=https

# --- Mail SMTP (FP-1.9 envoie des emails activation / expiration / reminders 30j+7j) ---
MAIL_MAILER=smtp
MAIL_HOST=<smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<smtp-user>
MAIL_PASSWORD=<smtp-pass>
MAIL_FROM_ADDRESS=noreply@weact.bj
MAIL_FROM_NAME=WEACT

# --- Admin email (FP-1.9 reminders cron logs vers cette adresse) ---
ADMIN_EMAIL=admin@weact.bj
```

**Important — pas de feature flag FP-1.** Aucune variable `FACE_PREMIUM_ENABLED` ou équivalent n'existe : la fonctionnalité est permanente dès le merge. Le rollback se fait par revert du code (cf. Section 9), **pas par un flip d'env**. Cette décision est documentée dans FP-1.1 et reprise dans FP-1.10 — l'entitlement est dérivé exclusivement de la donnée (`face_subscriptions.status = Active AND expires_at > now()`), il n'y a pas de kill-switch produit.

Explication par variable :

| Variable | Story | Usage |
| --- | --- | --- |
| `FACE_PREMIUM_ANNUAL_AMOUNT` | FP-1.5 | Montant annuel Face Premium utilisé par l'initiation Fedapay. |
| `FACE_PREMIUM_ANNUAL_CURRENCY` | FP-1.5 | Devise du paiement annuel, attendue en `XOF`. |
| `FACE_PREMIUM_ANNUAL_PROVIDER` | FP-1.5 | Provider de paiement attendu pour les rows `face_subscriptions`. |
| `FEDAPAY_SECRET_KEY` | FP-1.5 | Clé serveur utilisée pour créer et vérifier les transactions Fedapay. |
| `FEDAPAY_PUBLIC_KEY` | FP-1.5 | Clé publique exposée aux flows de paiement côté client/provider. |
| `FEDAPAY_ENVIRONMENT` | FP-1.5 | Doit être `live` en production pour éviter les transactions sandbox. |
| `FEDAPAY_WEBHOOK_SECRET` | FP-1.5 | Secret de validation des webhooks d'activation paiement. |
| `FRONTEND_URL` | FP-1.7 / FP-1.9 | URL utilisée dans les CTA email et les redirections vers l'app. |
| `APP_URL` | FP-1.9 | URL canonique API utilisée par Laravel pour générer les liens. |
| `QUEUE_CONNECTION` | FP-1.9 | Doit pointer vers une queue persistante pour les emails lifecycle. |
| `BROADCAST_CONNECTION` | FP-1.7 / FP-1.9 | Active Reverb pour les notifications temps réel de statut abonnement. |
| `REVERB_APP_ID` | FP-1.7 / FP-1.9 | Identifiant applicatif Reverb côté serveur. |
| `REVERB_APP_KEY` | FP-1.7 / FP-1.9 | Clé Reverb partagée avec le frontend Echo. |
| `REVERB_APP_SECRET` | FP-1.7 / FP-1.9 | Secret serveur Reverb, jamais exposé au frontend. |
| `REVERB_HOST` | FP-1.7 / FP-1.9 | Host public Reverb derrière le reverse proxy TLS. |
| `REVERB_PORT` | FP-1.7 / FP-1.9 | Port public Reverb, attendu à `443` en production TLS. |
| `REVERB_SCHEME` | FP-1.7 / FP-1.9 | Schéma public Reverb, attendu à `https` en production. |
| `MAIL_MAILER` | FP-1.9 | Driver d'envoi des emails activation, expiration et reminders. |
| `MAIL_HOST` | FP-1.9 | Host SMTP de production. |
| `MAIL_PORT` | FP-1.9 | Port SMTP de production, généralement `587`. |
| `MAIL_USERNAME` | FP-1.9 | Identifiant SMTP de production. |
| `MAIL_PASSWORD` | FP-1.9 | Mot de passe SMTP de production, secret à ne jamais committer. |
| `MAIL_FROM_ADDRESS` | FP-1.9 | Adresse expéditrice des emails Face Premium. |
| `MAIL_FROM_NAME` | FP-1.9 | Nom expéditeur affiché dans les emails. |
| `ADMIN_EMAIL` | FP-1.9 | Adresse de référence ops/admin pour les alertes et vérifications. |

Pour le détail du choix SMTP (Brevo / Mailgun / SES + enregistrements DNS SPF/DKIM/DMARC), cf. `docs/runbook-email-notifications.md`.

---

## 4. Scheduler — entrées requises

Vérifier que `backend/routes/console.php` enregistre bien les 2 commandes FP-1.X hourly :

```php
app(Schedule::class)->command(ExpireFaceSubscriptionsCommand::class)->hourly();          // FP-1.8
app(Schedule::class)->command(RemindFaceSubscriptionRenewalsCommand::class)->hourly();   // FP-1.9
```

Smoke check rapide en prod :

```bash
php artisan schedule:list | grep subscriptions:
```

Sortie attendue :

```
  0 * * * *  php artisan subscriptions:expire-faces           Next Due: dans X minutes
  0 * * * *  php artisan subscriptions:remind-face-renewals   Next Due: dans X minutes
```

Le scheduler Laravel doit tourner en arrière-plan (cron Linux + `php artisan schedule:run`, ou `php artisan schedule:work` en supervisé). Si `schedule:list` retourne du vide ou si les commandes n'apparaissent pas, vérifier que le cron Linux est bien actif (`systemctl status cron`) et qu'il appelle `php artisan schedule:run` toutes les minutes.

**La commande FP-1.10 `faces:audit-premium-readiness` n'est PAS schedulée** — c'est intentionnel, elle est invoquée manuellement par l'on-call (cf. Section 7).

---

## 5. Queue worker — requis

FP-1.9 sérialise les mailables (activation / expiration / reminders 30j + 7j) vers la queue `default`. Sans worker, les jobs s'accumulent dans la table `jobs` mais aucun email ne part.

Lancer un worker en production (idéalement sous supervisord / systemd) :

```bash
php artisan queue:work --queue=default --tries=3 --backoff=60
```

Smoke check à chaud :

```bash
php artisan queue:work --queue=default --once
```

— si la sortie indique `[N] Processing: App\Mail\FaceSubscription...Mail` puis `Processed:`, le worker draine la queue correctement. Si la table `jobs` grossit sans baisser, le worker est down.

---

## 6. Broadcast driver — requis

`App\Events\NotificationCreated` est auto-broadcasté sur le canal privé `App.Models.User.{id}` à chaque nouvelle row `notifications`. La SubscriptionCard FP-1.7 écoute ce canal pour réagir en temps réel à l'activation / expiration / cancellation.

```env
BROADCAST_CONNECTION=reverb
```

Vérifier que Reverb tourne :

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

— en prod, le serveur Reverb tourne sous supervisord ; le port public 443 (`REVERB_PORT=443`, `REVERB_SCHEME=https`) doit être derrière un reverse-proxy TLS.

**Fallback gracieux :** si Reverb est down, la SubscriptionCard FP-1.7 retombe automatiquement sur le polling 5 s de `/api/v1/face/subscription-status` (FP-1.3). L'UX se dégrade en latence mais ne casse pas.

---

## 7. Audit pré-lancement — procédure

À exécuter **avant** d'annoncer le feature aux Faces, idéalement juste après le déploiement et avant le premier passage du scheduler :

```bash
cd backend
php artisan faces:audit-premium-readiness --detailed | tee /tmp/face-premium-readiness-$(date +%Y%m%d).txt
```

Interpréter la sortie :

- **Section A — Active premium overview.** Au premier déploiement, les counts sont attendus à 0. Si un admin a déjà pré-provisionné des Faces via FP-1.4 (`AdminFaceSubscriptionController::activate`), `Active premium subscriptions` ≥ 1. C'est intentionnel et autorisé.
- **Section B — Free Faces with > 2 album photos.** Compte des Faces qui verront leurs photos 3-4 passer en "locked" sur les endpoints publics dès que le déploiement landera. Capturer ce count et briefer le support avec la liste `--detailed` pour anticiper les questions « pourquoi mes photos sont cachées ? ».
- **Section C — Free Faces with non-null acting_video.** Compte des Faces qui verront leur acting video disparaître publiquement. Même brief support que Section B.
- **Section D — Data hygiene anomalies.** Non-bloquant pour le lancement :
  - `Active subscriptions with NULL expires_at: N` — corrupt-data hint (cf. defer `deferred-work.md:334`). Si `N > 0`, capturer la liste manuellement (`SELECT id, face_id FROM face_subscriptions WHERE status='Active' AND expires_at IS NULL;`) et router vers une hardening story.
  - `Active subscriptions with past expires_at: N` — rows stale qui seront balayées au prochain run du cron `subscriptions:expire-faces`. Si `N > 50` au lancement, cela suggère que le scheduler était en pause depuis > N heures — alerter ops.

Si N = 0 partout en Section D et que les Sections B/C correspondent aux attentes produit, on est good to ship.

---

## 8. Smoke tests post-déploiement

À exécuter immédiatement après que le code est en place et que les services (scheduler, queue worker, Reverb) sont up :

```bash
# 1. Scheduler enregistré
php artisan schedule:list | grep subscriptions:

# 2. Event listeners découverts (#[AsEventListener] auto-discovery)
php artisan event:list | grep FaceSubscription

# 3. Audit command callable
php artisan faces:audit-premium-readiness

# 4. Subscription status endpoint répond OK pour une Face free
curl -H 'Authorization: Bearer <face-token>' https://api.weact.bj/api/v1/face/subscription-status

# 5. Queue worker draine
php artisan queue:work --queue=default --once
```

Sorties attendues :

- **Test 1** — 2 lignes (`subscriptions:expire-faces`, `subscriptions:remind-face-renewals`).
- **Test 2** — 3 events listés avec leurs listeners (cf. mémo : `event:list` peut nécessiter un `event:clear` au premier déploiement si le cache d'événements est resté avec une version antérieure).
- **Test 3** — la commande termine avec exit code 0 et affiche `Audit complete.`.
- **Test 4** — `200 OK` avec body JSON `{"data":{"status":"...","is_premium":..., ...}}` ; pour une Face free, attendu `status=null` ou `status=expired`, `is_premium=false`.
- **Test 5** — `Processed N jobs` ou `No jobs to process` ; aucune exception.

Si l'un de ces 5 tests échoue, **stopper le rollout** et déclencher le plan de rollback Section 9.

---

## 9. Plan de rollback — 3 couches

À choisir en fonction du blast radius identifié, du plus réversible au plus invasif.

### Layer 1 — Pauser le scheduler (réversible, blast radius minimal)

Stopper le process cron / supervisord qui appelle `php artisan schedule:run`. Effet :

- Les commandes `subscriptions:expire-faces` et `subscriptions:remind-face-renewals` cessent de tirer.
- Aucun customer state n'est modifié — les Active restent Active jusqu'à leur prochain passage en cron (qui ne vient pas).
- Reverb, queue worker et endpoints publics continuent à servir normalement.

Utilisé quand une seule commande est suspecte (ex : le cron de reminders envoie trop d'emails). Réactivation simple en relançant cron.

### Layer 2 — Revert du code deploy (réversible, blast radius moyen)

Revert des commits FP-1.10 → FP-1.1 (cf. tableau Section 10 pour les SHAs) si l'objectif est de désactiver Face Premium entièrement. Les migrations FP-1.X **ne sont pas rollback** dans cette couche — les colonnes ajoutées (`face_subscriptions.*`, `face_subscription_audits.*`, `face_subscriptions.reminder_*_sent_at`) restent en base et deviennent inertes côté production une fois le code retiré.

Si l'incident est clairement isolé à une couche plus haute, appliquer un revert sélectif depuis le tableau Section 10 plutôt que toute la pile (ex : FP-1.10 uniquement pour une erreur de runbook/test, FP-1.9 uniquement pour un problème d'emails/reminders, FP-1.7 uniquement pour la SubscriptionCard frontend).

Procédure :

```bash
git revert <sha-fp-1-10>
git revert <sha-fp-1-9>
git revert <sha-fp-1-8>
git revert <sha-fp-1-7>
git revert <sha-fp-1-6>
git revert <sha-fp-1-5>
git revert <sha-fp-1-4>
git revert <sha-fp-1-3>
git revert <sha-fp-1-2>
git revert <sha-fp-1-1>
git push
# Déploiement standard du tag de revert
```

Effet :

- Le code FP-1.X disparaît côté API/frontend. Les Faces qui avaient un abonnement Active voient simplement leur card disparaître ; le row DB reste en place.
- Les rows `face_subscriptions` / `face_subscription_audits` restent intactes et seront récupérables au prochain forward deploy.
- Les emails déjà queued mais non envoyés peuvent throw — vider la queue manuellement si nécessaire (`php artisan queue:flush`).

Utilisé quand le bug est dans le code applicatif, pas dans le schéma.

### Layer 3 — `migrate:rollback` (destructif, blast radius maximal)

À n'utiliser **que** si le schéma lui-même est identifié comme la cause racine (cas extrêmement rare). Détruit les rows `face_subscriptions` et `face_subscription_audits`.

**Prérequis obligatoires :**

```bash
# Exporter les tables vers des tables de backup AVANT le rollback.
# Exécuter depuis le shell sur l'hôte qui a accès à MySQL.
set -a
source /var/www/weact/backend/.env
set +a

BACKUP_SUFFIX="$(date +%Y%m%d_%H%M%S)"

mysql "$DB_DATABASE" -e "
CREATE TABLE face_subscriptions_backup_${BACKUP_SUFFIX} LIKE face_subscriptions;
INSERT INTO face_subscriptions_backup_${BACKUP_SUFFIX} SELECT * FROM face_subscriptions;
CREATE TABLE face_subscription_audits_backup_${BACKUP_SUFFIX} LIKE face_subscription_audits;
INSERT INTO face_subscription_audits_backup_${BACKUP_SUFFIX} SELECT * FROM face_subscription_audits;
"
```

Puis :

```bash
php artisan migrate:status | grep -E 'face_subscriptions|face_subscription_audits'

php artisan migrate:rollback --force \
  --path=database/migrations/2026_05_15_000000_add_reminder_sent_at_columns_to_face_subscriptions_table.php \
  --path=database/migrations/2026_05_12_000000_create_face_subscription_audits_table.php \
  --path=database/migrations/2026_05_11_083606_create_face_subscriptions_table.php
```

— rollback ciblé des 3 migrations FP-1.X uniquement. Ne pas utiliser `--step=3` en production : cette option rollback les trois dernières migrations exécutées dans l'environnement courant, qui peuvent inclure des migrations sans rapport si un autre déploiement est passé entre-temps. Récupération impossible sans restore manuel depuis les tables `_backup_*`.

---

## 10. Release-bundle table

Commits FP-1.X mergés sur `main` (vérifié via `git log --oneline --grep='FEATURE-FP-1'`). À utiliser pour cibler un revert sélectif en Layer 2.

| Story  | Merge SHA  | Surface principale                                                                   | Revertable seule ?                                                                |
| ------ | ---------- | ------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------- |
| FP-1.1 | `5a6c600`  | Migration `face_subscriptions` + `FaceEntitlementService` + factory + enums          | Oui (schéma + service ; tout le reste FP-1.X dépend de cette couche)              |
| FP-1.2 | `8908013`  | Album mask + acting video mask (resources publics)                                   | Oui (mais visuellement régresse les Faces premium déjà actifs vers 2 photos)      |
| FP-1.3 | `e5132e4`  | API `/api/v1/face/subscription-status` + entitlement endpoint                        | Oui (la SubscriptionCard FP-1.7 cassera son polling — symptôme : card vide)       |
| FP-1.4 | `df2bda3`  | Admin endpoints activate/cancel/extend/correct + table `face_subscription_audits`    | Oui (les admins perdent les opérations manuelles ; les rows audit restent)        |
| FP-1.5 | `ced2167`  | Paiement Fedapay + webhook + activation idempotente                                  | Oui (les Faces ne peuvent plus payer ; admin activate FP-1.4 toujours dispo)      |
| FP-1.6 | `0137a6a`  | Featured-bucket = active subscription dans `PublicFacesListController`               | Oui (l'ordering retombe sur `is_featured` manuel uniquement)                      |
| FP-1.7 | `993f110`  | Frontend SubscriptionCard `/face/profile`                                            | Oui (la page profile fonctionne sans la card)                                     |
| FP-1.8 | `56526f9`  | `subscriptions:expire-faces` hourly cron                                             | Oui (les Active expirés restent Active jusqu'à intervention manuelle — risque)    |
| FP-1.9 | `8570ef3`  | 3 events + 5 listeners + 3 mailables + cron reminders + colonnes reminder            | Oui (les Faces ne reçoivent plus notifs / emails ; entitlement intact)            |
| FP-1.10| _this PR_  | Regression matrix tests + audit command + runbook                                    | Oui (zero impact runtime, c'est exclusivement tests + docs + commande on-demand)  |

Pour récupérer les SHAs à jour à n'importe quel moment :

```bash
git log --oneline --grep='FEATURE-FP-1' main
```

---

## 11. Caveats opérationnels connus

Defers ouverts dans `_bmad-output/implementation-artifacts/deferred-work.md` qui touchent à FP-1.X. **Aucun n'est résolu par FP-1.10** — chacun reste owned par une hardening story future. À garder en tête pour le triage incident.

- **`deferred-work.md:258` — `FaceEntitlementService::isPremium()` n'exige pas `starts_at <= now()`.** Conséquence : un row Active pré-effectif (admin pre-provisioning avec `starts_at` dans le futur) accorde immédiatement le premium. Symptôme prod : « j'ai activé un Face pour la semaine prochaine et il voit déjà ses 4 photos publiques ». Workaround : laisser `starts_at = now()` ou passé à l'activation admin.
- **`deferred-work.md:265` — Pas de partial unique index sur `(face_id, status='Active' AND plan=AnnualPremium)`.** Conséquence : deux rows Active concurrents pour un même Face sont théoriquement possibles si un webhook double-fire dans la même seconde, ou si admin activate race avec un paiement entrant. Le service `qualifiesAsPremium` reste correct (il prend la plus récente), mais l'audit `:329` peut compter 2 rows Active pour 1 Face. Symptôme : `Active premium subscriptions: N` strictement supérieur à `Distinct Faces with active premium: N` dans la sortie de l'audit.
- **`deferred-work.md:269` — Asymmetric `qualifiesAsPremium` re-check dans `resolveActivePremiumSubscription`.** Race window microscopique (expiry crossing mid-request). Pas de symptôme prod connu ; pin pour future story de hardening service-level.
- **`deferred-work.md:271` — Index composite `(face_id, status, expires_at)` n'inclut pas `plan`.** Performance OK aujourd'hui (un seul plan, low row count). À revisiter quand `face_subscriptions` dépasse ~10k rows ou qu'un second plan ship.
- **`deferred-work.md:272` — Précision `dateTime` 1 s sur `expires_at` / `starts_at` / `cancelled_at`.** Webhook double-fire dans la même wall-clock seconde → 2 rows avec timestamps identiques. Mitigation actuelle : `unique(provider_reference)` sur Fedapay-style refs. À durcir si un opérateur webhook NULL-ref apparaît.
- **`deferred-work.md:273` — Trait `HasRouteUuid` non testé sur `FaceSubscription`.** Le factory pré-fill `uuid` explicitement, donc le trait n'est jamais exercé en test. Risque très faible (le trait est exercé sur Face / FacePhoto sans souci).
- **`deferred-work.md:274` — Pas de DB-level CHECK / enum sur `plan` / `status`.** Une row avec un plan inconnu (migration drift, legacy import) ferait crash la hydration Eloquent. À considérer avec une décision archi schéma-level.
- **`deferred-work.md:329` — N+1 hazard sur admin-viewing nested `FaceResource`.** 3 queries entitlement-service par Face dans les paths admin (candidature listing, user show). À fix dans une hardening story dédiée au eager-load contract.
- **`deferred-work.md:330` — Index column coverage du EXISTS subquery (FP-1.6).** À revisiter quand l'active-subscriber population grossit.
- **`deferred-work.md:334` — Active + `expires_at = NULL` jamais expiré.** L'audit FP-1.10 surface ces rows dans Section D mais ne les fix pas. Symptôme : un Face avec une telle row corrompue n'a aucun premium (l'entitlement filtre `expires_at > now()` qui exclut NULL) → support brief : « j'ai été activé mais je vois encore mes photos cachées ». Fix manuel : admin `correct` avec `expires_at` non-null.

Si un nouveau bug est identifié pendant le rollout, **ne pas le fix dans FP-1.10** — ajouter une entrée à `deferred-work.md` sous un nouveau heading « FP-1.10 readiness scan » et router vers une hardening story dédiée.
