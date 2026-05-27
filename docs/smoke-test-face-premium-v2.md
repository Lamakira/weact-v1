# Smoke Test Plan — Face Premium v2 (epic-feature-fp-2)

**Objectif** : valider en live tous les comportements différentiants de l'épic avant merge sur `main` + rollout prod.
**Pré-conditions** : backend `php artisan serve` running, frontend `npm run dev` running, MySQL `weact` (ou `weact_local`) populated, FedaPay sandbox configuré.
**Format** : check-list cochable. Chaque scénario : Setup → Actions → Expected → ✅/❌.

---

## 0. Setup initial (à faire une fois)

- [ ] **Capability matrix sous les yeux** :

| Tier | Prix XOF | Album | Présentation | Acting | UGC | UGC access | Commission | Sort prio | Élite badge |
|---|---|---|---|---|---|---|---|---|---|
| Free | 0 | 1 | 0 | 0 | 0 | ❌ | 10 % | 4 | ❌ |
| Starter | 12 000 | 2 | 1 | 0 | 0 | ✅ | 10 % | 3 | ❌ |
| Pro | 25 000 | 4 | 1 | 1 | 0 | ✅ | 10 % | 2 | ❌ |
| Élite | 40 000 | 6 | 1 | 2 | 1 | ✅ | 5 % | 1 | ✅ |

- [x] **Audit preflight** : `cd backend && php artisan faces:audit-premium-readiness`
  → doit afficher 5 sections (A: per-tier counts, B: photo overflow, C: video overflow par type, D: anomalies, E: tier distribution + percentages). Aucun crash, aucune section vide. Vérifier qu'il n'y a pas de Faces en état incohérent.

- [x] **Compte de test prêts** :
  - 1 compte Face neuf (à créer) pour le flow free → starter → pro → élite
  - 1 compte Face existant avec photos/vidéos pour tester quotas
  - 1 compte Admin pour les opérations admin
  - Sandbox FedaPay : carte de test valide + carte qui refuse

- [x] **Crons activés en local** :
  - `php artisan schedule:list` → doit lister 13 entrées incluant `faces:expire-subscriptions` (hourly), `faces:fail-stale-pending` (hourly), `faces:remind-renewals` (hourly), `faces:purge-expired-media` (`0 3 * * *` UTC).

---

## 1. Page publique `/pricing` (FP-2.13)

- [x] **1.1** Ouvrir `/pricing` en **non-authentifié** → 4 cartes (Free / Starter / Pro / Élite) avec prix, capabilities, CTA. Tester scroll, FAQ en bas.
- [x] **1.2** CTA "S'inscrire" sur Free → redirige vers `/register/face`.
- [ ] **1.3** CTA "Choisir Starter/Pro/Élite" sur tier payant **non-authentifié** → redirige vers `/login` puis bounce-back vers `/pricing` après login avec le tier pré-sélectionné (FP-2.13.1).
- [x] **1.4** Ouvrir `/pricing` en **authentifié Face sans abonnement** → CTA tier payant déclenche flow paiement direct (modal FedaPay ou redirect provider).
- [x] **1.5** Ouvrir `/pricing` en **authentifié Face avec abonnement Pro actif** → la carte Pro affiche un état "tier actuel" distinct ; les autres tiers affichent "Passer à X" (upgrade) ou "Rétrograder à X" (downgrade).
- [x] **1.6** Bloc FAQ contient l'explication 90-day retention : *"Tes photos et vidéos restent stockées 90 jours, le temps de te réabonner"*.
- [x] **1.7** Responsive mobile : les 4 cartes s'empilent verticalement sans déborder.

---

## 2. Flow paiement Face (FP-2.5 + FP-2.13.1)

### 2.1 — Free → Starter (premier abonnement)

- [x] **2.1.a** Créer un nouveau compte Face, login, naviguer vers `/pricing` ou `/face/profile`.
- [x] **2.1.b** Cliquer "S'abonner à Starter" → status passe à `PendingPayment` côté DB (`face_subscriptions.status`).
- [x] **2.1.c** Frontend : un bandeau "Paiement en cours" apparaît sur le profil ; pas encore d'accès aux capabilities Starter (max_album_photos toujours = 1).
- [x] **2.1.d** Compléter le paiement sur FedaPay sandbox → webhook reçu → status passe à `Active`, `expires_at = starts_at + 12 mois`.
- [x] **2.1.e** Frontend : toast "Paiement confirmé" + bandeau disparaît + `subscription-status` API renvoie tier Starter + capabilities Starter visibles.

### 2.2 — Annuler un paiement pending

- [x] **2.2.a** Initier un paiement (status → PendingPayment) mais ne pas le finaliser.
- [ ] **2.2.b** Sur le profil Face, cliquer "Annuler le paiement" (FP-2.8.1) → status passe à `Cancelled`, `cancelled_at` posé.
- [ ] **2.2.c** L'utilisateur peut ré-initier un nouveau paiement Starter (status → nouvelle row PendingPayment).

### 2.3 — Échec de paiement

- [ ] **2.3.a** Initier un paiement avec carte qui refuse → webhook `failed` → row passe à `Failed`.
- [ ] **2.3.b** Profil Face : message d'erreur clair "Paiement refusé, réessaie".
- [ ] **2.3.c** Une nouvelle initiation crée une nouvelle row PendingPayment (la Failed reste en historique).

### 2.4 — Upgrade Starter → Pro

- [X] **2.4.a** Compte Face avec Starter Active depuis ≥ 1 mois.
- [x] **2.4.b** Sur `/pricing` ou `/face/profile`, cliquer "Passer à Pro" → paiement de 25 000 XOF.
- [x] **2.4.c** Webhook reçu → ancienne row Starter `Cancelled` avec `cancelled_at = now()`, **nouvelle row Pro Active** créée avec `starts_at = now()`, `expires_at = now() + 12 mois`.
- [x] **2.4.d** `subscription-status` API : tier = Pro, capabilities Pro (4 photos, 1 acting video).
- [x] **2.4.e** Verify : l'ancienne row Starter conserve son `expires_at` original (contractuel) mais `status = Cancelled`.

### 2.5 — Downgrade Pro → Starter (chained-renewal cancel)

- [x] **2.5.a** Compte Face avec Pro Active + 4 photos uploadées.
- [x] **2.5.b** Sur `/pricing`, cliquer "Rétrograder à Starter" → paiement de 12 000 XOF.
- [x] **2.5.c** Webhook reçu → ancienne row Pro `Cancelled`, nouvelle row Starter Active.
- [x] **2.5.d** Verify : les 4 photos restent **visibles en privé** sur le profil Face (positions 2-4 marquées "over-quota" / lock badge). Sur le profil public, seule la photo position 1 est visible (FP-2.2 masking).
- [x] **2.5.e** Côté Face, les photos over-quota affichent un badge "Réabonne-toi à Pro pour les rendre publiques".

### 2.6 — Upgrade Pro → Élite

- [x] **2.6.a** Compte Face avec Pro Active.
- [x] **2.6.b** Payer Élite (40 000 XOF) → tier = Élite, 6 photos / 2 acting / 1 UGC autorisés.
- [x] **2.6.c** Profil Face : badge Élite visible sur le profil (FP-2.12 WBadge V13).
- [x] **2.6.d** Profil public `/faces/{username}` : badge Élite visible aussi.

---

## 3. Quotas photo (FP-2.2)

- [x] **3.1** Face Free (max 1) — essayer d'uploader 2 photos → upload de la 2ᵉ refusée avec message "Quota atteint, passe à Starter pour 2 photos".
- [x] **3.2** Face Starter (max 2) — uploader 2 photos OK, 3ᵉ refusée.
- [x] **3.3** Face Pro (max 4) — uploader 4 photos OK, 5ᵉ refusée.
- [x] **3.4** Face Élite (max 6) — uploader 6 photos OK, 7ᵉ refusée.
- [x] **3.5** Vérifier sur le profil public d'un Face Pro avec 4 photos : les 4 photos visibles.
- [x] **3.6** **Masking test** : Face Pro avec 4 photos qui expire (passe à Free) → profil public n'affiche plus que la photo position 1. Profil Face (logué) : 4 photos visibles avec lock badge sur positions 2-4.

---

## 4. Vidéos par type (FP-2.2.1 + FP-2.7.1)

### 4.1 — Présentation video

- [x] **4.1.a** Face Free — uploader une presentation video → refusé (max 0 pour Free).
- [x] **4.1.b** Face Starter — uploader une presentation video → OK (max 1). 2ᵉ upload → refusé.
- [x] **4.1.c** Verify : la presentation video s'affiche sur le profil public en hero.

### 4.2 — Acting video

- [x] **4.2.a** Face Starter — uploader acting video → refusé (max 0 pour Starter).
- [x] **4.2.b** Face Pro — uploader 1 acting video → OK. 2ᵉ → refusé.
- [x] **4.2.c** Face Élite — uploader 2 acting videos → OK. 3ᵉ → refusé.

### 4.3 — UGC video

- [x] **4.3.a** Face Pro — uploader UGC video → refusé (max 0 pour Pro).
- [x] **4.3.b** Face Élite — uploader 1 UGC video → OK. 2ᵉ → refusé.
- [x] **4.3.c** Sur le profil public Élite : UGC video visible avec player.

### 4.4 — UGC access toggle

- [x] **4.4.a** Face Free — la section UGC n'est pas visible côté Face profile (gated par `ugc_access`).
- [x] **4.4.b** Face Starter/Pro — section UGC visible mais upload bloqué avec message "Passe à Élite pour publier des UGC".
- [x] **4.4.c** Face Élite — section UGC pleinement fonctionnelle.

---

## 5. Status API & subscription panel (FP-2.3 + FP-2.7)

- [ ] **5.1** `GET /api/v1/subscription-status` avec un Face Free → renvoie tier=`free`, capabilities Free, 3 offres (Starter/Pro/Élite) avec leurs capabilities et prix.
- [ ] **5.2** Même endpoint avec Face Pro Active → renvoie tier=`pro`, current subscription details (starts_at, expires_at, days_remaining), 2 offres (Starter downgrade, Élite upgrade).
- [ ] **5.3** Frontend `/face/profile` — bandeau "Mon abonnement" affiche le tier actuel + jours restants + CTA changer.
- [ ] **5.4** Face avec subscription qui expire dans < 30 jours → bandeau "Renouvellement bientôt" (FP-2.9).

---

## 6. Recherche & tri (FP-2.6)

- [ ] **6.1** **Setup** : créer 4 Faces avec 1 Élite Active + 1 Pro Active + 1 Starter Active + 1 Free. Tous publics, mêmes critères (ville, genre, etc.).
- [ ] **6.2** Page publique `/faces` (listing) → vérifier l'ordre : Élite en premier, puis Pro, puis Starter, puis Free.
- [ ] **6.3** Filtres + tri : même ordre tier-priority préservé à filtres égaux.
- [ ] **6.4** Verify backend : `GET /api/v1/public/faces` renvoie les Faces ordonnées par `sort_priority` ASC (1=Élite haut, 4=Free bas).

---

## 7. Badge Élite (FP-2.12 + FP-2.12.1)

- [ ] **7.1** Face Élite Active → profil public affiche WBadge V13 (composant `WBadge.vue`).
- [ ] **7.2** Listing public `/faces` : badge Élite visible sur les cartes des Faces Élite.
- [ ] **7.3** Page candidature Producer : si la Face est Élite, badge visible.
- [ ] **7.4** Face Élite Cancelled (downgrade) → badge disparaît du public, **reste visible côté Face en privé jusqu'au prochain cron**.
- [ ] **7.5** Verify : la propriété `has_elite_badge` est exposée dans les Resources Face publiques.

---

## 8. Admin operations (FP-2.4 + FP-2.10)

- [ ] **8.1** Login admin sur `/admin/login`.
- [ ] **8.2** Naviguer vers `/admin/faces` → liste des Faces avec colonne "Tier" (Free/Starter/Pro/Élite avec badges colorés).
- [ ] **8.3** Cliquer sur un Face → `AdminFaceDetailPage` → onglet "Abonnement" avec historique des subscriptions.
- [ ] **8.4** **Activate manuellement** : sur un Face Free, clic "Activer abonnement" → modal de sélection tier (Starter/Pro/Élite) → confirmer → row Active créée avec `cancellation_reason = 'admin_activated'` et audit trail.
- [ ] **8.5** **Extend** : sur une subscription Active, clic "Prolonger" → modal qui demande durée → `expires_at` étendu, audit row créée.
- [ ] **8.6** **Cancel** : clic "Annuler" → modal confirmation → status passe à `Cancelled`, `cancelled_at = now()`, audit trail.
- [ ] **8.7** **Correct** : clic "Corriger" → permet de modifier les dates (cas d'erreur de saisie) → audit row "correction".
- [ ] **8.8** **Change tier (FP-2.10)** : sur une subscription Active Pro, clic "Changer de tier" → sélecteur (Starter/Élite) → confirmer Élite → ancienne row Cancelled + nouvelle row Élite Active créée, audit "change-tier".
- [ ] **8.9** Vérifier : tous les changements admin déclenchent une notification in-app vers le Face concerné (FP-2.9).
- [ ] **8.10** Sur le profil Face concerné, recharger : le nouveau tier est reflété immédiatement.

---

## 9. Crons (FP-2.8 + FP-2.8.1 + FP-2.9)

### 9.1 — Expiration cron (`faces:expire-subscriptions`)

- [ ] **9.1.a** Setup : un Face Pro avec `expires_at = now()->subHour()` (manipuler en DB direct ou Tinker).
- [ ] **9.1.b** Run : `php artisan faces:expire-subscriptions`
- [ ] **9.1.c** Verify : status passe à `Expired`, `expires_at` inchangé, console affiche per-Face line + global summary.
- [ ] **9.1.d** Face reçoit notification in-app "Abonnement expiré".
- [ ] **9.1.e** Frontend Face : tier = Free, photos position > 1 affichent lock badge.
- [ ] **9.1.f** Email reçu (vérifier MailHog ou logs).

### 9.2 — Stale-pending cleanup (`faces:fail-stale-pending`)

- [ ] **9.2.a** Setup : créer une row PendingPayment avec `created_at = now()->subHours(50)` (config `stale_pending_max_hours = 48`).
- [ ] **9.2.b** Run : `php artisan faces:fail-stale-pending`
- [ ] **9.2.c** Verify : row passe à `Failed`, log structuré contient `face_id`, `face_subscription_id`, `plan`.
- [ ] **9.2.d** Une row PendingPayment de 30h ne doit PAS être touchée.

### 9.3 — Renewal reminders (`faces:remind-renewals`)

- [ ] **9.3.a** Setup : Face Pro avec `expires_at = now()->addDays(7)`.
- [ ] **9.3.b** Run : `php artisan faces:remind-renewals`
- [ ] **9.3.c** Verify : notification in-app + email "Ton abonnement expire dans 7 jours" reçus.
- [ ] **9.3.d** Re-run le cron immédiatement → idempotent (pas de double notification).
- [ ] **9.3.e** Setup similaire avec `expires_at = now()->addDays(3)` → second reminder envoyé.
- [ ] **9.3.f** Setup avec `expires_at = now()->addHours(12)` → reminder J-1 envoyé.

### 9.4 — Audit (`faces:audit-premium-readiness`)

- [ ] **9.4.a** Run : `php artisan faces:audit-premium-readiness` → 5 sections affichées (A-E).
- [ ] **9.4.b** Run avec `--detailed` flag → sections incluent enumération per-Face des anomalies.
- [ ] **9.4.c** Verify : output reflète l'état actuel (créer une anomalie volontaire — Face Pro avec 5 photos par tinker — et re-run → Section B affiche `1 Face overflow album`).

---

## 10. Retention window 90-day (FP-2.14)

### 10.1 — Within window (no purge)

- [ ] **10.1.a** Setup : Face avec Pro Cancelled `cancelled_at = now()->subDays(30)` + 4 photos sur disque.
- [ ] **10.1.b** Run : `php artisan faces:purge-expired-media`
- [ ] **10.1.c** Verify : 4 photos toujours sur disque, 4 rows `face_photos` en DB. Log affiche `Found 1 Face(s) ... Done. Faces purged: 0`.
- [ ] **10.1.d** Profil Face (logué) : 4 photos visibles avec lock badge (over-quota).
- [ ] **10.1.e** Profil public : seule la photo position 1 visible (Free max=1).

### 10.2 — Beyond window (purge)

- [ ] **10.2.a** Setup : Face Élite Expired `expires_at = now()->subDays(91)` + 5 photos (positions 1-5) + 2 acting videos + 1 UGC video.
- [ ] **10.2.b** Run : `php artisan faces:purge-expired-media`
- [ ] **10.2.c** Verify : photos positions 2-5 supprimées (DB + disque), photo position 1 conservée (Free quota=1). Acting videos + UGC video supprimés. Presentation video conservée (jamais purgée).
- [ ] **10.2.d** Logs : `Log::info` par item avec `face_id`, `media_type` (`album_photo`/`acting_video`/`ugc_video`), `position`, `retention_until`.
- [ ] **10.2.e** Profil Face : seule la photo position 1 visible. Plus de message "réabonne-toi" sur les autres (elles n'existent plus).

### 10.3 — Re-subscription within window restores

- [ ] **10.3.a** Setup : Face Pro Expired `expires_at = now()->subDays(45)` + 4 photos.
- [ ] **10.3.b** Run purge → 0 photos purgées (window encore ouverte).
- [ ] **10.3.c** Face se réabonne Pro → row Active créée. Re-run purge → 0 photos purgées (tier actuel Pro couvre les 4).
- [ ] **10.3.d** Profil public : 4 photos visibles à nouveau.

### 10.4 — Idempotence

- [ ] **10.4.a** Setup beyond-window comme 10.2.
- [ ] **10.4.b** Run purge 2 fois de suite → 2ᵉ run ne purge rien de plus, pas d'erreur, exit code 0.

### 10.5 — Boundary exact (anchor + 90 jours)

- [ ] **10.5.a** Setup : Face Pro Expired `expires_at = now()->subDays(90)->subSeconds(1)`.
- [ ] **10.5.b** Run purge → photos positions 2-4 purgées (anchor + 90j atteint).
- [ ] **10.5.c** Avec `expires_at = now()->subDays(89)` → 0 purge (window encore ouverte).

### 10.6 — Cancelled pending-payment skipped

- [ ] **10.6.a** Setup : Face avec **uniquement** une row PendingPayment puis Cancelled (jamais Active) `cancelled_at = now()->subDays(100)`.
- [ ] **10.6.b** Run purge → 0 Face affected (cancelled pending-payment ignorée comme non-paid anchor).

### 10.7 — Presentation video NEVER purged

- [ ] **10.7.a** Setup beyond-window avec presentation video uploadée.
- [ ] **10.7.b** Run purge → presentation video conservée (DB + disque). Vérifier que `face_photos`/`face_videos` non-presentation purgés.

---

## 11. Cron schedule wiring

- [ ] **11.1** `php artisan schedule:list` doit afficher :
  - `faces:expire-subscriptions` — hourly (`0 * * * *`)
  - `faces:fail-stale-pending` — hourly (`0 * * * *`)
  - `faces:remind-renewals` — hourly (`0 * * * *`)
  - `faces:purge-expired-media` — daily 03:00 UTC (`0 3 * * *`) avec `onOneServer` + `withoutOverlapping`
- [ ] **11.2** Verify timezone : si `app.timezone` ≠ UTC, le purge fire à 03:00 UTC sans décalage.

---

## 12. Edge cases & guards

- [ ] **12.1** **Face sans User attaché** : ne doit pas crasher dans aucun cron / API.
- [ ] **12.2** **Subscription avec `expires_at` null** : audit affiche en Section D ; crons ne crashent pas (FP-2.14 invariant warning loggé).
- [ ] **12.3** **Face avec Active + Cancelled historique simultanés** : profile public utilise Active capabilities. Purge ne touche aucune photo couverte par Active.
- [ ] **12.4** **Concurrent uploads pendant cron** : pas de crash (race window documentée comme defer, acceptable).
- [ ] **12.5** **Disk file manquant pour une photo qu'on tente de purger** : `Log::warning` mais pas d'erreur, cron continue.
- [ ] **12.6** **Test admin sans permission** : `/admin/faces/{id}/subscriptions/activate` sans auth admin → 403.
- [ ] **12.7** **Rate limit public** : `/api/v1/public/faces` throttle 60 req/min respecté.

---

## 13. Notifications par tier (FP-2.9)

- [ ] **13.1** **Activation Starter** : email + in-app "Bienvenue chez Starter" avec liste des capabilities Starter.
- [ ] **13.2** **Activation Pro** : email + in-app "Bienvenue chez Pro" avec capabilities Pro.
- [ ] **13.3** **Activation Élite** : email + in-app "Bienvenue chez Élite" avec capabilities Élite (incluant badge).
- [ ] **13.4** **Expiration** : email + in-app "Ton abonnement X est expiré" avec CTA "Réabonne-toi" + mention 90-day retention.
- [ ] **13.5** **Cancellation admin** : email + in-app "Ton abonnement X a été annulé par l'équipe" + raison si fournie.

---

## 14. Cross-cutting frontend — KNOWN ISSUE à valider

- [ ] **14.1** **⚠️ Cache leak shared device** : sur un device partagé, login Face A → naviguer profil → logout → login Face B → vérifier que **les vidéos / photos / subscription-status de Face A ne sont pas brièvement visibles** avant le refresh.
  → **C'est le defer #1 critical-path de la rétro FP-2.** Si reproductible, **bloquer le rollout** jusqu'à fix.

---

## 15. Commission rate (FP-2.X — backend only)

- [ ] **15.1** Compléter une mission rémunérée avec un Face Free/Starter/Pro → commission de 10 % retenue.
- [ ] **15.2** Même mission avec un Face Élite → commission de 5 % retenue (capabilities `commission_rate = 0.05`).

---

## 16. Final sanity — full backend suite

- [ ] **16.1** `cd backend && php artisan test` → **2 546 / 2 546 passing, 12 913 assertions, 0 failure, 0 risky**.
- [ ] **16.2** `./vendor/bin/pint --test` → no diff.
- [ ] **16.3** `./vendor/bin/phpstan analyse --level=5` → no error sur les fichiers touchés FP-2.

---

## Critères de merge `main`

Avant `git push origin dev:main` (ou PR equivalent) :

- [ ] **Sections 1-13 toutes ✅** (les vraies fonctionnalités).
- [ ] **Section 14 (cache leak)** : soit ✅ (pas reproductible), soit l'item est explicitement déféré à une story d'infra dédiée et le rollout prod est gated derrière cette story.
- [ ] **Section 16 verte** (tests + lint + types).
- [ ] **Runbook `docs/runbook-face-premium-rollout.md`** ouvert sous les yeux pendant le rollout.

---

*Plan généré 2026-05-26. 90+ scénarios cochables. Couvre les 18 stories de l'épic + capability matrix + lifecycle + crons + retention window. Si un scénario échoue, noter la story FP-2.X concernée + ouvrir une issue avant merge.*
