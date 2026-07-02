# Runbook — Rollout Face Premium (Sprint 15, FEATURE-FP-2.X — tier model)

**Audience :** ingénieur on-call WEACT déployant l'abonnement annuel Face Premium en production sous le **modèle 4 paliers** (Free / Starter / Pro / Élite).

**Scope :** dérouler la mise en production des stories FEATURE-FP-2.1 → FEATURE-FP-2.11 : architecture capabilities-matrix, configuration des variables d'environnement, scheduler hourly à **3 commandes**, worker de queue, driver de broadcast, audit pré-lancement tier-aware, smoke tests post-déploiement, et plan de rollback en 3 couches adapté au schéma FP-2.

**Convention de répertoire de travail :** sauf indication contraire explicite, **toutes les commandes `php artisan`, `composer`, `vendor/bin/...`, `php artisan tinker` et `php artisan queue:work` se lancent depuis `backend/`**. Toutes les commandes `npm`/`npx` se lancent depuis `frontend/`. Les commandes shell brutes (`grep`, `curl`, `vim`, `git`) sont lancées depuis la racine projet sauf si un chemin relatif explicite est indiqué.

---

## 1. Scope & Audience

Ce runbook couvre :

- L'inventaire des fichiers livrés par les stories FP-2.1 → FP-2.11 (capabilities matrix, schéma `face_subscriptions` aligné 4-paliers, table `face_videos`, paiement Fedapay tier-aware, admin tier selector, frontend `/pricing` 4 cartes, lifecycle notifications per-tier).
- Les variables d'environnement requises en production (Fedapay, mail SMTP, broadcast Reverb, queue). **Note FP-2 :** plus aucune variable `FACE_PREMIUM_ANNUAL_*` — la tarification est désormais pilotée par `config/face_subscription_tiers.php`.
- Les entrées du scheduler Laravel (3 crons hourly) et la procédure de vérification.
- L'audit pré-lancement read-only via `php artisan faces:audit-premium-readiness` (refactor FP-2.11 : sections A-E avec breakdown per-tier + distribution effective).
- Les smoke tests post-déploiement (6 commandes concrètes).
- Le plan de rollback en 3 couches (pause scheduler / revert code par story / migrate:rollback ciblé).
- Le tableau release-bundle (commit par story FP-2.X).
- Les caveats opérationnels (defers connus, route vers les hardening stories, **carve-out FP-2.14 retention non couvert ici**).

Ce runbook **ne couvre pas** :

- Le rollout du frontend FP-2 (`/pricing` 4 cartes FP-2.13/2.13.1, `SubscriptionCard` FP-2.7 sur `/face/profile`, modal change-tier admin FP-2.10) — ces surfaces n'ont pas de feature flag et sont visibles dès que le backend FP-2 est en ligne.
- La communication marketing / customer support pour les Faces qui verront leur palier effectif passer à Free (perte de quotas vidéo + photos 2+) lors du déploiement FP-2.1 (briefer le support via la sortie `--detailed` de l'audit, plus la nouvelle Section E "tier distribution").
- Les runbooks de mission (cf. `docs/runbook-mission-payment-recovery.md`) et d'email infra (cf. `docs/runbook-email-notifications.md`) — dépendances externes assumées en place.
- **La rétention 90 jours des médias post-expiration + commande de purge (FP-2.14, statut `backlog`)** — non shippée à date du runbook. Une révision documentaire (`feature-fp-2-11-1-retention-coverage`) ajoutera la procédure purge ops quand FP-2.14 landera.
- **Le badge public Élite (FP-2.12, statut `backlog`)** — pas d'impact opérationnel.

---

## 2. État actuel (post-FP-2.X)

Inventaire de la surface FP-2.X mergée au moment où FP-2.11 est créée :

**Migrations (6) — schéma final FP-2 :**

- `backend/database/migrations/2026_05_11_083606_create_face_subscriptions_table.php` — table `face_subscriptions` (FP-1.1, inchangée par FP-2).
- `backend/database/migrations/2026_05_12_000000_create_face_subscription_audits_table.php` — table `face_subscription_audits` (FP-1.4, inchangée par FP-2 — l'extension `tier` snapshot voyage en JSON).
- `backend/database/migrations/2026_05_15_000000_add_reminder_sent_at_columns_to_face_subscriptions_table.php` — colonnes `reminder_30d_sent_at` + `reminder_7d_sent_at` (FP-1.9, inchangées par FP-2).
- `backend/database/migrations/2026_05_21_000000_align_face_subscriptions_for_tiers.php` — **FP-2.1** : purge des rows héritées du modèle FP-1 `annual_premium`. `down()` est volontairement vide (irréversible).
- `backend/database/migrations/2026_05_21_010000_create_face_videos_table.php` — **FP-2.2** : table `face_videos` (types `acting` / `ugc`, unique `(face_id, type, position)`).
- `backend/database/migrations/2026_05_21_010001_migrate_acting_video_to_face_videos.php` — **FP-2.2** : copie des `faces.acting_video` legacy vers `face_videos type=acting` puis nullification de la colonne legacy.

**Services backend :**

- `App\Services\FaceEntitlementService` — **source de vérité unique** de l'entitlement tier-aware (FP-1.1 → FP-2.1). Méthode canonique `capabilities(Face)` retourne le `TierCapabilities` VO depuis `config/face_subscription_tiers.php`. **Tous les consumers (resources, controllers, services, form requests) consomment `capabilities()` ; aucun ne branche sur `plan` directement** (invariant pinné automatiquement par `FaceEntitlementContractAuditTest`).
- `App\Services\FaceSubscriptionAdminService` — opérations admin avec **paramètre `plan` requis** (FP-2.4) + nouvelle action `changeTier()` qui mute un palier actif sans redémarrer les dates. Écrit `tier` dans `previous_state`/`new_state` de chaque audit row.
- `App\Services\FaceSubscriptionPaymentService` — initiation paiement Fedapay avec sélection de palier + activation idempotente sur webhook (FP-2.5). Chained renewal supporté ; upgrade/downgrade en ligne réinitialise les dates (pas de pro-rata, Product Decision #3).
- `App\Services\FaceVideoService` — CRUD sur `face_videos` avec garde tier-aware (FP-2.2). Vérifie le quota courant via `capabilities()` avant upload.
- `App\Services\PhotoAlbumService` — refactor tier-aware (FP-1.2 → FP-2.2). Quota uniquement piloté par `capabilities()->maxAlbumPhotos`.

**Events / Listeners / Mailables (FP-2.9) :**

- 3 events : `FaceSubscriptionActivated`, `FaceSubscriptionExpired`, `FaceSubscriptionCancelled`.
- 5 listeners enregistrés via `#[AsEventListener]` : 3 in-app (`NotifyFaceOnSubscription{Activated|Expired|Cancelled}`) + 2 email (`SendFaceSubscription{Activated|Expired}Email` ; cancellation reste in-app uniquement par décision produit).
- 3 mailables + 3 vues Blade sous `backend/resources/views/emails/face-subscriptions/`, avec **copy per-tier** dérivée de `FaceSubscriptionPlan::premiumMediaSummary()`.

**Commandes artisan (4 au total post-FP-2.11) :**

- `subscriptions:expire-faces` — **hourly**, expiration des Active dont `expires_at <= now()` (FP-2.8). Drop le palier → Free.
- `subscriptions:fail-stale-pending` — **hourly**, auto-fail des `pending_payment` plus vieux que `stale_pending_max_hours` (FP-2.8.1, défaut 48 h).
- `subscriptions:remind-face-renewals` — **hourly**, fenêtres 30j + 7j idempotentes avec copy per-tier (FP-2.9).
- `faces:audit-premium-readiness` — **on-demand uniquement**, audit read-only tier-aware (FP-2.11, ce runbook).

**Frontend FP-2 :**

- `AdminFaceSubscriptionSection.vue` (`frontend/src/features/admin/components/`) — section admin subscription FP-1.11 → FP-2.10 (tier selector activate + change-tier modal + audit tier badges).
- `SubscriptionCard.vue` (`frontend/src/features/face/components/`) — carte profile face FP-1.7 → FP-2.7 (4 cards tier + flow upgrade).
- `TierChangeModal.vue` — modal de sélection de palier partagé entre `/face/profile` et `/pricing` (FP-2.7 + FP-2.13.1).
- `FaceVideos*.vue` — portfolio vidéo Face (FP-2.7.1, acting + UGC upload sur l'API typée `face_videos`).
- `PricingView.vue` — page publique `/pricing` 4 paliers (FP-2.13 visuel + FP-2.13.1 layer auth-aware).

---

## 3. Variables d'environnement requises

À mettre à jour dans le `.env` de production (les valeurs `<...>` sont à remplir avec les credentials prod, **jamais commit dans `.env.example`**) :

```env
# --- Fedapay credentials (FP-2.5 : tier-aware payment) ---
FEDAPAY_SECRET_KEY=<secret>
FEDAPAY_PUBLIC_KEY=<public>
FEDAPAY_ENVIRONMENT=live
FEDAPAY_WEBHOOK_SECRET=<webhook-secret>

# --- URLs prod (référencées par les mailables FP-2.9) ---
FRONTEND_URL=https://app.weact.bj
APP_URL=https://api.weact.bj

# --- Queue (mailables FP-2.9 sérialisés en queue) ---
QUEUE_CONNECTION=database

# --- Broadcast (NotificationCreated FP-2.9 broadcasté sur canal privé Reverb) ---
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<reverb-app-id>
REVERB_APP_KEY=<reverb-key>
REVERB_APP_SECRET=<reverb-secret>
REVERB_HOST=<reverb-host>
REVERB_PORT=443
REVERB_SCHEME=https

# --- Mail SMTP (FP-2.9 envoie des emails activation / expiration / reminders 30j+7j per-tier) ---
MAIL_MAILER=smtp
MAIL_HOST=<smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<smtp-user>
MAIL_PASSWORD=<smtp-pass>
MAIL_FROM_ADDRESS=noreply@weact.bj
MAIL_FROM_NAME=WEACT

# --- Admin email (FP-2.9 reminders cron logs vers cette adresse) ---
ADMIN_EMAIL=admin@weact.bj
```

**Important — pas de variable `FACE_PREMIUM_ANNUAL_*` en FP-2.** Le modèle FP-1 utilisait `FACE_PREMIUM_ANNUAL_AMOUNT`, `FACE_PREMIUM_ANNUAL_CURRENCY` et `FACE_PREMIUM_ANNUAL_PROVIDER`. Ces variables ont été supprimées par FP-2.1 : la tarification + currency + provider sont désormais pilotés par `config/face_subscription_tiers.php` (config-driven, **FEAT-FP2-NFR1** — ajouter un palier ou changer un prix se fait uniquement dans ce fichier de config, sans migration ni env).

**Important — pas de feature flag FP-2.** Aucune variable `FACE_PREMIUM_ENABLED` ou équivalent. L'entitlement est dérivé exclusivement de la donnée (`face_subscriptions.status = Active AND expires_at > now()`) résolue par `FaceEntitlementService::capabilities()`. Le rollback se fait par revert du code (cf. Section 9), **pas par un flip d'env**.

Explication par variable :

| Variable | Story | Usage |
| --- | --- | --- |
| `FEDAPAY_SECRET_KEY` | FP-2.5 | Clé serveur utilisée pour créer et vérifier les transactions Fedapay tier-aware. |
| `FEDAPAY_PUBLIC_KEY` | FP-2.5 | Clé publique exposée aux flows de paiement côté client/provider. |
| `FEDAPAY_ENVIRONMENT` | FP-2.5 | Doit être `live` en production pour éviter les transactions sandbox. |
| `FEDAPAY_WEBHOOK_SECRET` | FP-2.5 | Secret de validation des webhooks d'activation paiement. |
| `FRONTEND_URL` | FP-2.7 / FP-2.9 / FP-2.13 | URL utilisée dans les CTA email et les redirections vers l'app (`/face/profile`, `/pricing`). |
| `APP_URL` | FP-2.9 | URL canonique API utilisée par Laravel pour générer les liens. |
| `QUEUE_CONNECTION` | FP-2.9 | Doit pointer vers une queue persistante pour les emails lifecycle. |
| `BROADCAST_CONNECTION` | FP-2.7 / FP-2.9 | Active Reverb pour les notifications temps réel de statut abonnement. |
| `REVERB_APP_ID` | FP-2.7 / FP-2.9 | Identifiant applicatif Reverb côté serveur. |
| `REVERB_APP_KEY` | FP-2.7 / FP-2.9 | Clé Reverb partagée avec le frontend Echo. |
| `REVERB_APP_SECRET` | FP-2.7 / FP-2.9 | Secret serveur Reverb, jamais exposé au frontend. |
| `REVERB_HOST` | FP-2.7 / FP-2.9 | Host public Reverb derrière le reverse proxy TLS. |
| `REVERB_PORT` | FP-2.7 / FP-2.9 | Port public Reverb, attendu à `443` en production TLS. |
| `REVERB_SCHEME` | FP-2.7 / FP-2.9 | Schéma public Reverb, attendu à `https` en production. |
| `MAIL_MAILER` | FP-2.9 | Driver d'envoi des emails activation, expiration et reminders. |
| `MAIL_HOST` | FP-2.9 | Host SMTP de production. |
| `MAIL_PORT` | FP-2.9 | Port SMTP de production, généralement `587`. |
| `MAIL_USERNAME` | FP-2.9 | Identifiant SMTP de production. |
| `MAIL_PASSWORD` | FP-2.9 | Mot de passe SMTP de production, secret à ne jamais committer. |
| `MAIL_FROM_ADDRESS` | FP-2.9 | Adresse expéditrice des emails Face Premium. |
| `MAIL_FROM_NAME` | FP-2.9 | Nom expéditeur affiché dans les emails. |
| `ADMIN_EMAIL` | FP-2.9 | Adresse de référence ops/admin pour les alertes et vérifications. |

**Tarification (FP-2.1 / FEAT-FP2-NFR1) :** pour changer un prix ou ajouter/retirer un palier en production, éditer `backend/config/face_subscription_tiers.php` puis lancer `php artisan config:clear` (ou redéployer). Aucun changement env, aucune migration. Le on-call qui doit bumper le prix Pro applique :

```bash
# Éditer le fichier de config
vim backend/config/face_subscription_tiers.php
# Invalider le cache de config si Laravel tournait avec config:cache
php artisan config:clear
# Puis re-cacher si besoin
php artisan config:cache
```

Pour le détail du choix SMTP (Brevo / Mailgun / SES + enregistrements DNS SPF/DKIM/DMARC), cf. `docs/runbook-email-notifications.md`.

---

## 4. Scheduler — entrées requises

Vérifier que `backend/routes/console.php` enregistre bien les **3 commandes FP-2.X hourly** :

```php
app(Schedule::class)->command(ExpireFaceSubscriptionsCommand::class)->hourly();          // FP-2.8
app(Schedule::class)->command(FailStalePendingFaceSubscriptionsCommand::class)->hourly(); // FP-2.8.1
app(Schedule::class)->command(RemindFaceSubscriptionRenewalsCommand::class)->hourly();   // FP-2.9
```

Smoke check rapide en prod :

```bash
php artisan schedule:list | grep subscriptions:
```

Sortie attendue (3 lignes) :

```
  0 * * * *  php artisan subscriptions:expire-faces           Next Due: dans X minutes
  0 * * * *  php artisan subscriptions:fail-stale-pending     Next Due: dans X minutes
  0 * * * *  php artisan subscriptions:remind-face-renewals   Next Due: dans X minutes
```

Le scheduler Laravel doit tourner en arrière-plan (cron Linux + `php artisan schedule:run`, ou `php artisan schedule:work` en supervisé). Si `schedule:list` retourne du vide ou si les commandes n'apparaissent pas, vérifier que le cron Linux est bien actif (`systemctl status cron`) et qu'il appelle `php artisan schedule:run` toutes les minutes.

**La commande FP-2.11 `faces:audit-premium-readiness` n'est PAS schedulée** — c'est intentionnel, elle est invoquée manuellement par l'on-call (cf. Section 7).

---

## 5. Queue worker — requis

FP-2.9 sérialise les mailables (activation / expiration / reminders 30j + 7j) vers la queue `default`. Sans worker, les jobs s'accumulent dans la table `jobs` mais aucun email ne part.

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

`App\Events\NotificationCreated` est auto-broadcasté sur le canal privé `App.Models.User.{id}` à chaque nouvelle row `notifications`. La `SubscriptionCard` FP-2.7 écoute ce canal pour réagir en temps réel à l'activation / expiration / cancellation.

```env
BROADCAST_CONNECTION=reverb
```

Vérifier que Reverb tourne :

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

— en prod, le serveur Reverb tourne sous supervisord ; le port public 443 (`REVERB_PORT=443`, `REVERB_SCHEME=https`) doit être derrière un reverse-proxy TLS.

**Fallback gracieux :** si Reverb est down, la `SubscriptionCard` FP-2.7 retombe automatiquement sur le polling cyclique de `/api/v1/face/subscription-status` (FP-2.3). L'UX se dégrade en latence mais ne casse pas.

---

## 7. Audit pré-lancement — procédure

À exécuter **avant** d'annoncer le feature aux Faces, idéalement juste après le déploiement et avant le premier passage du scheduler :

```bash
cd backend
php artisan faces:audit-premium-readiness --detailed | tee /tmp/face-premium-readiness-$(date +%Y%m%d).txt
```

Interpréter la sortie (5 sections post-FP-2.11) :

- **Section A — Active subscriptions by tier.** Au premier déploiement, les counts par palier sont attendus à 0 sauf si des admins ont déjà pré-provisionné des Faces via FP-2.4 (`AdminFaceSubscriptionController::activate(plan=...)`). La ligne `Total: N` synthétise les 3 paliers payants (+ une ligne `Unknown: N (plans: ...)` si des rows aux plans hors-enum apparaissent — typiquement résidus FP-1 `annual_premium`). `Distinct Faces with active paid subscription` est le compte de Faces uniques. **Cas chained-renewal :** le pattern FP-1.5 d'usage normal crée 1 row `Expired` + 1 row `Active` sur le même Face, donc Section A compte 1 row Active = 1 distinct. Si **deux rows Active concurrentes** sur le même Face apparaissent (race webhook + admin manual + auto-renew, cf. `deferred-work.md` "no partial unique index on `(face_id, status=Active)`"), `Total` (somme des rows par plan) sera **strictement supérieur** à `Distinct Faces` : c'est le signal opérationnel que la race s'est produite. Section E ne double-compte plus dans ce cas — la sub-query canonique choisit la row au `expires_at` le plus tardif (id en tie-break), comme `Face::activeSubscription` au runtime.

- **Section B — Free Faces with > 1 album photo (positions 2+ will be hidden publicly at launch).** Compte des Faces non-payantes qui verront leurs photos 2+ passer en "locked" sur les endpoints publics dès que le déploiement FP-2 landera. **Le seuil FP-2 est `> 1`** (Free quota = 1 photo) et non plus `> 2` comme en FP-1. Capturer ce count et briefer le support avec la liste `--detailed`.

- **Section C — Free Faces with stored videos (3 lines).** FP-2 a 3 surfaces vidéo : `presentation_video` (scalar sur `faces`), acting (`face_videos type=acting`), UGC (`face_videos type=ugc`). Free a 0 quota partout — chaque Face non-payante qui a stocké une de ces 3 vidéos la verra masquée publiquement au lancement. Les 3 lignes ventilent : présentation / acting / UGC. `--detailed` énumère par Face avec annotation (`has_presentation_video=1` / `acting_videos=k` / `ugc_videos=k`).

- **Section D — Data hygiene anomalies.** Non-bloquant pour le lancement :
  - `Active subscriptions with NULL expires_at: N` — corrupt-data hint (cf. defer dans `_bmad-output/implementation-artifacts/deferred-work.md` sous l'en-tête `## Deferred from: code review of feature-fp-1-8-expiration-command-and-entitlement-removal (2026-05-14)`, environ ligne 372). Si `N > 0`, capturer la liste manuellement (`SELECT id, face_id, plan FROM face_subscriptions WHERE status='active' AND expires_at IS NULL;`) et router vers une hardening story.
  - `Active subscriptions with past expires_at: N` — rows stale qui seront balayées au prochain run du cron `subscriptions:expire-faces`. Si `N > 50` au lancement, cela suggère que le scheduler était en pause depuis > N heures — alerter ops.

- **Section E — Effective tier distribution across all Faces.** Census FP-2 du palier effectif par Face (résolu via `capabilities()` : Free pour toute Face sans active+future paid row, sinon le palier du row Active). Lit `(Free: A%, Starter: B%, Pro: C%, Élite: D%, [Unknown: U%,] Total: N Faces)`. C'est le signal-clé pour le pré-launch ops : connaître la répartition des paliers permet de calibrer la communication marketing post-déploiement (combien de Faces vont voir leur palier passer en Free → quel pourcentage de la population est payant aujourd'hui). **Caveat `is_active` (déferé pour révision future) :** ce `Total N Faces` joint la table `faces` sans filtrer sur `users.is_active = true`, alors que la liste publique (`/api/v1/public/faces`) ne sert que les Faces dont le User est actif. Donc `Total` inclut les Faces dont l'utilisateur est soft-désactivé — le pool publiquement visible peut être strictement inférieur. Pour estimer le pool exposé, soustraire `SELECT COUNT(*) FROM faces f JOIN users u ON u.userable_id = f.id WHERE u.is_active = false;`. Cf. `_bmad-output/implementation-artifacts/deferred-work.md` sous l'en-tête `## Deferred from: code review of feature-fp-2-11-...`.

Si N = 0 partout en Section D et que les Sections B/C/E correspondent aux attentes produit, on est good to ship.

---

## 8. Smoke tests post-déploiement

À exécuter immédiatement après que le code est en place et que les services (scheduler, queue worker, Reverb) sont up :

```bash
# 1. Scheduler enregistré (3 entrées attendues)
php artisan schedule:list | grep subscriptions:

# 2. Event listeners découverts (#[AsEventListener] auto-discovery — 3 events × 5 listeners)
php artisan event:clear && php artisan event:list | grep FaceSubscription

# 3. Audit command callable + 5 sections affichées
php artisan faces:audit-premium-readiness

# 4. Subscription status endpoint répond OK pour une Face free (capabilities matrix complète)
curl -H 'Authorization: Bearer <face-token>' https://api.weact.bj/api/v1/face/subscription-status

# 5. Queue worker draine
php artisan queue:work --queue=default --once

# 6. Public /pricing — vérification VISUELLE NAVIGATEUR (pas de smoke shell automatisable)
#    Ouvrir https://app.weact.bj/pricing dans un navigateur ;
#    vérifier visuellement que les 4 cartes (Free / Starter / Pro / Élite) s'affichent
#    et que le `data-testid="pricing-grid"` est présent dans le DOM (DevTools → Elements).
```

Sorties attendues :

- **Test 1** — 3 lignes (`subscriptions:expire-faces`, `subscriptions:fail-stale-pending`, `subscriptions:remind-face-renewals`).
- **Test 2** — 3 events listés avec leurs 5 listeners (cf. mémo : `event:list` peut nécessiter un `event:clear` au premier déploiement si le cache d'événements est resté avec une version antérieure — le smoke ici enchaîne les deux commandes pour éviter le piège).
- **Test 3** — la commande termine avec exit code 0 et affiche `=== Face Premium readiness audit (FP-2 tier model) ===` puis les 5 sections (A → E) et `Audit complete.`.
- **Test 4** — `200 OK` avec body JSON `{"data":{"current":{"tier":"...","capabilities":{...},"starts_at":...,"expires_at":...},"offers":[...],"cta":{...}}}` (la forme actuelle expose `data.current.tier` + `data.current.capabilities` nichés, **pas** un `data.current_tier` plat ; la clé temporelle de début est `starts_at` côté Resource, **pas** `started_at`). Pour une Face free, `data.current.tier` doit être `"free"` et `data.current.capabilities.max_album_photos` doit valoir `1` (la response key utilise le snake_case côté Resource).
- **Test 5** — `Processed N jobs` ou `No jobs to process` ; aucune exception.
- **Test 6** — **Vérification visuelle navigateur uniquement** : le frontend est une SPA Vue/Vite sans SSR, donc `curl https://app.weact.bj/pricing` retourne un `index.html` vide (`<div id="app"></div>` + un `<script src="...">`) — Vue construit la page dans le navigateur, le `data-testid="pricing-grid"` n'est jamais présent dans la réponse HTTP brute. Le on-call DOIT ouvrir l'URL dans un navigateur et confirmer visuellement (a) que les 4 cartes `Free / Starter / Pro / Élite` s'affichent, (b) que la pile DOM contient `data-testid="pricing-grid"` (DevTools → Elements → Ctrl-F). Un smoke `curl` automatisable serait factice (toujours OK ou toujours KO selon que l'index.html est servi). Pour automatiser ce check, prévoir un Playwright/Puppeteer dans une révision future du runbook — hors scope FP-2.11.

Si l'un de ces 6 tests échoue, **stopper le rollout** et déclencher le plan de rollback Section 9.

**Mint d'un token Face pour le test #4 :**

Lancer `php artisan tinker` depuis `backend/`, puis exécuter (sans les `>>>` prompts) :

```php
$user = App\Models\User::query()->whereHas('userable', fn ($q) => $q->where('username', '<known-face-username>'))->firstOrFail();
$user->createToken('smoke')->plainTextToken;
```

(Le token affiché est à passer en `Authorization: Bearer <token>` au curl du Test #4.)

---

## 9. Plan de rollback — 3 couches

À choisir en fonction du blast radius identifié, du plus réversible au plus invasif.

### Layer 1 — Pauser le scheduler (réversible, blast radius minimal)

Stopper le process cron / supervisord qui appelle `php artisan schedule:run`. Effet :

- Les 3 commandes (`subscriptions:expire-faces`, `subscriptions:fail-stale-pending`, `subscriptions:remind-face-renewals`) cessent de tirer.
- Aucun customer state n'est modifié — les Active restent Active jusqu'à leur prochain passage en cron (qui ne vient pas).
- Reverb, queue worker, endpoints publics, et `/pricing` continuent à servir normalement.

Utilisé quand une seule commande est suspecte (ex : le cron de fail-stale-pending fail-fail des rows à tort). Réactivation simple en relançant cron.

### Layer 2 — Revert du code deploy (réversible, blast radius variable selon la story)

Revert des commits FP-2.11 → FP-2.1 (cf. tableau Section 10 pour les SHAs) si l'objectif est de désactiver Face Premium entièrement. Les migrations FP-2.X **ne sont pas rollback** dans cette couche — les colonnes/tables ajoutées restent en base et deviennent inertes côté production une fois le code retiré.

Si l'incident est clairement isolé à une couche plus haute, appliquer un revert sélectif depuis le tableau Section 10 plutôt que toute la pile.

Procédure (revert en ordre inverse de merge, FP-2.11 → FP-2.1) :

```bash
git revert <sha-fp-2-11>
git revert <sha-fp-2-13-1>  # frontend-only — peut être revert seul si besoin
git revert <sha-fp-2-13>    # frontend-only
git revert <sha-fp-2-10>    # frontend-only
git revert <sha-fp-2-9>
git revert <sha-fp-2-8-1>
git revert <sha-fp-2-8>
git revert <sha-fp-2-7-1>   # frontend-only
git revert <sha-fp-2-7>     # frontend-only
git revert <sha-fp-2-6>
git revert <sha-fp-2-5>
git revert <sha-fp-2-4>
git revert <sha-fp-2-3>
git revert <sha-fp-2-2-1>
git revert <sha-fp-2-2>
git revert <sha-fp-2-1>
git push
# Déploiement standard du tag de revert
```

Effet :

- Le code FP-2.X disparaît côté API/frontend. Les Faces qui avaient un abonnement Active voient leur card disparaître ; le row DB reste en place (les colonnes/tables FP-2 restent en base, inertes).
- Les emails déjà queued mais non envoyés peuvent throw — vider la queue manuellement si nécessaire (`php artisan queue:flush`).

Utilisé quand le bug est dans le code applicatif, pas dans le schéma.

**Notes sur la revertabilité par story :**

- **FP-2.1** (schema align migration) : ne PAS revert seul — le code FP-2 (services, resources, enums) suppose l'absence de rows `annual_premium`. Si on revert FP-2.1, recréer/migrer rapidement.
- **FP-2.2 / FP-2.2.1** (face_videos table + migration acting) : revert détruit la table `face_videos` — perte définitive des rows acting/UGC sans backup préalable (cf. Layer 3).
- **FP-2.3 / FP-2.4 / FP-2.5** : revertables mais cassent les flows downstream (frontend FP-2.7 ne pourra plus consommer `/subscription-status` ; admin tier-selector FP-2.10 ne pourra plus changer de palier ; paiement FP-2.5 ne pourra plus initier).
- **FP-2.6** (ordering par tier priority) : revertable seule — l'ordering retombe sur `is_featured` + complétude profile (FP-1.6 baseline).
- **FP-2.7 / FP-2.7.1 / FP-2.10 / FP-2.13** : revertables seules (frontend-only, blast radius minimal).
- **FP-2.13.1** (authenticated payment flow on `/pricing`) : revertable **indépendamment** de FP-2.13 (cf. Layer 2.5 ci-dessous). C'est une couche JS ajoutée par-dessus FP-2.13 statique pour câbler le bouton "Choisir" sur la session utilisateur ; si seule cette couche est buggée (paiement initié sur le mauvais palier, popup bloquée, etc.), revert FP-2.13.1 seul rend `/pricing` à son comportement FP-2.13 (page visuelle 4 cartes sans CTA payment).
- **FP-2.8 / FP-2.8.1** : revertable — les rows pending/active arrêtent juste d'être ageing par cron. Risque : les rows Active expirées restent Active (intervention manuelle requise).
- **FP-2.9** : revertable — les Faces ne reçoivent plus de reminders / lifecycle emails. Entitlement intact.
- **FP-2.11** (ce PR) : revertable seule — zero impact runtime (tests + docs + command refactor + carve-out doc).

### Layer 2.5 — Revert ciblé de la couche payment-flow `/pricing` (FP-2.13.1 only)

Sous-cas de Layer 2 spécifique à la couche payment-flow JS du `/pricing`. À choisir quand l'incident est isolé au flow de paiement initié depuis la page publique (mauvais palier sélectionné, popup bloquée, redirect post-Fedapay cassé) mais que la page visuelle (FP-2.13) reste saine et que le reste du backend FP-2 fonctionne.

```bash
git revert <sha-fp-2-13-1>
git push
# Déploiement frontend uniquement (build Vite + rsync — pas besoin de toucher le backend)
```

Effet :

- Le `PricingView.vue` retombe sur son comportement FP-2.13 statique : 4 cartes affichées, mais le bouton "Choisir" n'initie plus de paiement depuis cette page.
- Les Faces peuvent toujours s'abonner via le flow `SubscriptionCard` (FP-2.7) sur `/face/profile` — aucun ban d'acquisition, juste un détour d'un clic.
- Aucun changement backend, aucune migration, aucun row DB touché. Blast radius : 1 fichier Vue + ses tests.

Utilisé en complément de Layer 1 (pause scheduler) si l'incident touche uniquement le funnel d'acquisition depuis `/pricing` sans déstabiliser le state des abonnements actifs.

### Layer 3 — `migrate:rollback` (destructif, blast radius maximal)

À n'utiliser **que** si le schéma lui-même est identifié comme la cause racine (cas extrêmement rare). Détruit les rows `face_subscriptions`, `face_subscription_audits`, ET `face_videos`.

**FP-2.1 align migration est irréversible par design** (`down()` est vide). Layer 3 ne peut donc PAS rollback FP-2.1 ; seules les migrations FP-1 base (subscriptions + audits) + FP-2.2 face_videos sont rollback-ables.

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
CREATE TABLE face_videos_backup_${BACKUP_SUFFIX} LIKE face_videos;
INSERT INTO face_videos_backup_${BACKUP_SUFFIX} SELECT * FROM face_videos;
"
```

Puis :

```bash
php artisan migrate:status | grep -E 'face_subscriptions|face_subscription_audits|face_videos'

# Rollback ciblé des 3 migrations rollback-ables (NOT la FP-2.1 align).
php artisan migrate:rollback --force \
  --path=database/migrations/2026_05_21_010001_migrate_acting_video_to_face_videos.php \
  --path=database/migrations/2026_05_21_010000_create_face_videos_table.php \
  --path=database/migrations/2026_05_15_000000_add_reminder_sent_at_columns_to_face_subscriptions_table.php \
  --path=database/migrations/2026_05_12_000000_create_face_subscription_audits_table.php \
  --path=database/migrations/2026_05_11_083606_create_face_subscriptions_table.php
```

— rollback ciblé des migrations FP-1 base + FP-2.2 face_videos. **La FP-2.1 align migration ne peut pas être rollback** (son `down()` est intentionnellement vide). Ne pas utiliser `--step=N` en production : cette option rollback les N dernières migrations exécutées dans l'environnement courant, qui peuvent inclure des migrations sans rapport si un autre déploiement est passé entre-temps. Récupération impossible sans restore manuel depuis les tables `_backup_*`.

---

## 10. Release-bundle table

Commits FP-2.X mergés sur `main` (vérifié via `git log --oneline --grep='FEATURE-FP-2'`). À utiliser pour cibler un revert sélectif en Layer 2.

| Story        | Merge SHA  | Surface principale                                                                              | Revertable seule ?                                                                  |
| ------------ | ---------- | ----------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| FP-2.1       | `<sha>`    | Migration align `face_subscriptions` + 4 paliers enums + `TierCapabilities` VO + service refactor | **Non** — tout FP-2 dépend de cette couche (schema + service)                       |
| FP-2.2       | `<sha>`    | Migration `face_videos` + service `FaceVideoService` + photo album dynamic quota                | Non (FP-2.7.1 + portfolio frontend cassent immédiatement)                           |
| FP-2.2.1     | `<sha>`    | 3 video types (`presentation` / `acting` / `ugc`) + masking par type                            | Non (la migration acting→face_videos déplace les rows FP-1)                         |
| FP-2.3       | `<sha>`    | API `/api/v1/face/subscription-status` capabilities matrix + 4 tier offers                      | Oui (frontend FP-2.7 perd son polling — symptôme : card vide)                       |
| FP-2.4       | `<sha>`    | Admin endpoints `activate(plan=...)` + `change-tier` + audit `tier` snapshot                    | Oui (les admins perdent le tier-selector ; les rows audit restent intactes)         |
| FP-2.5       | `<sha>`    | Paiement Fedapay tier-aware + upgrade/downgrade sans pro-rata                                   | Oui (les Faces ne peuvent plus payer ; admin FP-2.4 toujours dispo)                 |
| FP-2.6       | `<sha>`    | Ordering listing public par `sort_priority` per tier (4 buckets)                                | Oui (l'ordering retombe sur `is_featured` manuel + complétude FP-1.6)               |
| FP-2.7       | `<sha>`    | Frontend `SubscriptionCard` 4 cards + flow upgrade/downgrade                                    | Oui (frontend-only, blast radius minimal)                                           |
| FP-2.7.1     | `<sha>`    | Frontend portfolio Face videos (acting + UGC upload sur API typée)                              | Oui (frontend-only)                                                                 |
| FP-2.8       | `<sha>`    | `subscriptions:expire-faces` hourly cron — drop palier → Free                                   | Oui (les Active expirés restent Active jusqu'à intervention manuelle — risque)      |
| FP-2.8.1     | `<sha>`    | `subscriptions:fail-stale-pending` hourly cron + user-cancel-pending endpoint                   | Oui (les rows pending s'accumulent, déblocage manuel requis)                        |
| FP-2.9       | `<sha>`    | 3 events + 5 listeners + 3 mailables per-tier copy + cron reminders 30j/7j                      | Oui (les Faces ne reçoivent plus notifs / emails ; entitlement intact)              |
| FP-2.10      | `<sha>`    | Frontend admin tier-selector + change-tier modal + audit tier badges                            | Oui (frontend-only — les admins reviennent au FP-1.11 baseline binaire)             |
| FP-2.13      | `<sha>`    | Page publique `/pricing` 4 cartes (visuel statique)                                             | Oui (frontend-only — pricing retombe sur le legacy producer page si on revert main) |
| FP-2.13.1    | `<sha>`    | Layer auth-aware sur `/pricing` (tier-selection + payment-flow pour Face logged-in)             | Oui (frontend-only — `/pricing` perd ses CTAs payants, garde les register CTAs)     |
| FP-2.11      | _this PR_  | Regression matrix tests + audit command tier-aware + runbook FP-2 + contract audit test         | Oui (zero impact runtime — tests + docs + command refactor uniquement)              |

Pour récupérer les SHAs à jour à n'importe quel moment :

```bash
git log --oneline --grep='FEATURE-FP-2' main
```

---

## 11. Caveats opérationnels connus

Defers ouverts dans `_bmad-output/implementation-artifacts/deferred-work.md` qui touchent à FP-2.X. **Aucun n'est résolu par FP-2.11** — chacun reste owned par une hardening story future. À garder en tête pour le triage incident.

- **Listener `Log::warning` catch blocks sans `'plan'` (FP-2.9 partiel).** Les 3 in-app listeners (`Notify*.php`) loggent `face_subscription_id` + `error` sans `plan` — les opérateurs ne peuvent pas filtrer les listener failures par palier. Cron est OK, listeners non.
- **Race window `dispatch($subscription->fresh())` dans `ExpireFaceSubscriptionsCommand`.** Hérité de FP-1.8, préservé en FP-2.8. Le `fresh()` post-transaction peut lire une row mutée par un autre worker entre le commit et le dispatch.
- **`change_tier` ne garde pas contre une row `pending_payment` du même Face.** Asymétrie délibérée avec `activate()` — résolution holistique attendue dans une story de hardening FP-2.5/2.8.
- **Chained-renewal + tier change** laisse potentiellement une row stale Active si un Face a 2 Active rows (renouvellement prépayé) et que l'admin change le palier de l'une seulement.
- **Public faces list a un N+1 pré-existant** sur `average_rating` accessor par Face — pas introduit par FP-2.6 mais non corrigé.
- **Popup-blocked checkout** laisse une row `pending_payment` orpheline (FP-2.5) — `subscriptions:fail-stale-pending` la collecte au prochain cron (par défaut 48 h).
- **Singleton cache leak across users on shared browser** : les composables `useSubscriptionStatus` / `usePhotoAlbum` / `useFaceVideos` ne sont pas invalidés sur logout (TTL 60s à 5 min). Critique en contexte cybercafé / kiosque. Hardening story cross-feature requise.
- **`FREE_CAPABILITIES` drift risk** : le fallback frontend dans `useSubscriptionStatus.ts` réplique manuellement la config backend du palier Free. Tout changement de la config backend exige une sync manuelle frontend.
- **Photos en position > 6 invisibles dans le grid** si admin override DB direct créerait une row à position 7+ (cap UI ≤ 6 = Élite max). Pas de signal produit qu'un cas legacy >6 existe en prod.
- **Cron `->orderBy('id')->get()` non chunked** dans `FailStalePendingFaceSubscriptionsCommand` et `ExpireFaceSubscriptionsCommand` — OOM risk si backlog >10k rows post-incident Fedapay multi-jours. Mitigation : `chunkById(500)`.
- **Modal admin trap operator pendant requête lente** sur les 5 modals admin (`activate`, `extend`, `cancel`, `correct`, `change_tier`) — pas d'affordance "Annuler la requête". Hardening FP-1.11 cross-modal.

**Defers hérités de FP-1.X (toujours owned par futures hardening stories) :**

- `FaceEntitlementService::isPremium()` / `FaceSubscription::isActive()` n'exigent pas `starts_at <= now()` — pre-effective Active rows accordent l'entitlement.
- Pas de partial unique index sur `(face_id, status='Active')` — 2 rows Active concurrentes théoriquement possibles via race webhook.
- Précision `dateTime` 1 s sur `expires_at` / `starts_at` / `cancelled_at` — race window microscopique sur webhook double-fire.
- Active + `expires_at = NULL` jamais expiré — surfacing dans Section D de l'audit FP-2.11 mais non auto-corrigé.

**Carve-out FP-2.14 (non couvert par ce runbook) :**

La story **FEATURE-FP-2.14 (90-day post-expiration media retention window + purge command)** est en statut `backlog`. Tant que FP-2.14 n'a pas été shippée :

- Le runbook ne documente PAS la fenêtre de rétention 90 jours.
- Le runbook ne documente PAS la commande `faces:purge-expired-media` (qui n'existe pas encore).
- L'audit `faces:audit-premium-readiness` ne contient PAS de section "media pending purge".
- La matrice de régression `FaceSubscriptionRegressionMatrixTest` ne couvre PAS le contrat "media within 90-day window stays visible privately ; media past the window is purged".

Une **révision documentaire** (`feature-fp-2-11-1-retention-coverage`) sera créée après que FP-2.14 landera pour ajouter ces 4 surfaces au runbook + à l'audit + à la matrice. Cf. l'entrée `deferred-work.md` sous le heading `## FEATURE-FP-2.11.1 (retention coverage — pending FP-2.14)`.

Si un nouveau bug est identifié pendant le rollout FP-2, **ne pas le fix dans FP-2.11** — ajouter une entrée à `deferred-work.md` sous un nouveau heading « FP-2.11 readiness scan » et router vers une hardening story dédiée.
