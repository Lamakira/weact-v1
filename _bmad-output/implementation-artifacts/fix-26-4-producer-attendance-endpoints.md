# Story FIX-26.4: Endpoints Producer — afficher et soumettre la validation présence

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As a **Producteur authentifié qui doit valider la présence des Faces sélectionnées sur sa mission tournée**,
I want **deux endpoints HTTP — `GET /api/v1/producer/missions/{mission}/attendance-form` qui me retourne la liste des Faces sélectionnées avec leur état actuel et les montants serveur-side, et `POST /api/v1/producer/missions/{mission}/validate-attendance` qui accepte ma soumission `{entries: [{entry_id, status: 'present'|'absent'}, ...]}` et délègue à `MissionAttendanceService::markAttendance`** —,
so that **la couche HTTP exhibe la validation présence pour FIX-26.7 (UI Vue) et permette au Producer de transitionner sa mission `Closed → PendingAttendanceValidation → Completed` sans jamais transmettre de montants depuis le frontend (invariant 10 epic)**.

## Acceptance Criteria

**Contexte produit** : FIX-26.4 est la story qui matérialise le contrat HTTP côté Producer pour la validation de présence. Elle dépend de FIX-26.1 (schéma `attendance_status`), FIX-26.2 (refactor `releaseFunds`) et FIX-26.3 (`MissionAttendanceService` livré, `markAttendance` testé bout-en-bout). Les stories qui consomment 26.4 : FIX-26.7 (UI Vue Producer) appelle ces endpoints, FIX-26.5 (notification + endpoint contestation Face) reste indépendante. Source : `_bmad-output/planning-artifacts/epics-postlaunch-fixes-10.md` § FIX-26.4 (lignes 208-222) + § « Nouveaux invariants financiers » lignes 71-77 (invariants 10/11/12) + § « Décisions produit » lignes 38-48.

**Pattern Prove It non applicable** : ce n'est pas un bug-fix, c'est une feature add (deux nouveaux endpoints qui adaptent un service déjà testé). Pattern test-first quand même : `ProducerAttendanceEndpointsTest` est écrit avant le controller, échoue (route inexistante = 404), puis le controller + FormRequest + Resource + routes sont implémentés, puis les tests passent.

**Architecture retenue — controller mince qui délègue à `MissionAttendanceService`** :

- ✅ **Nouveau controller `App\Http\Controllers\Api\V1\Producer\MissionAttendanceController`** — 2 actions HTTP : `show()` (GET form) et `validate()` (POST submit). Pas d'action métier dans le controller : **toute la mécanique financière, l'idempotence, la transaction et les locks vivent dans `MissionAttendanceService`** (livré FIX-26.3, `backend/app/Services/MissionAttendanceService.php:37-163`). Le controller construit la map `array<int, 'present'|'absent'>` depuis le payload list-of-objects et appelle `markAttendance($mission, $map, $request->user())`.
- ✅ **Nouveau FormRequest `App\Http\Requests\Mission\ValidateMissionAttendanceRequest`** dans `backend/app/Http/Requests/Mission/` — strict miroir namespace `CompleteMissionRequest` (fichier voisin `backend/app/Http/Requests/Mission/CompleteMissionRequest.php:1-133`). `authorize()` vérifie Producer + ownership ; `rules()` valide la forme du payload (`entries` array, `entries.*.entry_id` integer, `entries.*.status` in:present,absent) ; `withValidator()` ajoute deux gardes 422 défensifs (mission status ∈ {Closed, PendingAttendanceValidation}, entry_ids appartiennent tous à la mission via subquery).
- ✅ **GET endpoint utilise un guard inline dans le controller** (pas de FormRequest dédié) — strict miroir `MissionPaymentController::paymentStatus` (`backend/app/Http/Controllers/Api/V1/Producer/MissionPaymentController.php:94-153`). Justification : un GET sans body n'a pas de payload à valider. L'autorisation ownership + le status guard tiennent en quelques lignes inline. Cohérent avec le pattern controller-level guards déjà utilisé pour `paymentStatus`.
- ✅ **Nouvelle Resource `App\Http\Resources\MissionAttendanceEntryResource`** — sérialise une `MissionPaymentCandidature` côté API (id int, face uuid + display_name + profile_photo_url, montant_face_recoit, attendance_status + label fr (rendu inline pas de `AttendanceStatus::label()` dans cette story — voir Décisions retenues), escrow_status + label, notified_at). Utilisée par les **deux** endpoints (GET `show` retourne la liste ; POST `validate` retourne le snapshot frais après mutation).
- ✅ **Format de réponse cohérent avec le reste du controller Producer** : enveloppe `{ "data": {...}, "message": "..." }` (cf. `MissionController::index/show/complete` qui renvoient toutes ce shape, `backend/app/Http/Controllers/Api/V1/Producer/MissionController.php:50-53,73-76,178-181`). Erreurs au format global FIX-22.2 `{ "error": { "message", "code", "details"? } }` via le handler `bootstrap/app.php:69-228` — **AUCUN code d'erreur custom n'est ajouté** dans cette story (pas de nouveau case dans `ErrorCodes` enum).
- ✅ **Format API contract — `entry_id` est l'integer auto-increment de `mission_payment_candidatures.id`** — pas un UUID (le modèle `MissionPaymentCandidature` n'a pas de UUID, vérifié par `grep -n "uuid\|HasRouteUuid" backend/app/Models/MissionPaymentCandidature.php` qui ne renvoie rien, et la migration `backend/database/migrations/2026_03_20_100002_create_mission_payment_candidatures_table.php:13-25` n'expose que `$table->id()`). Cohérent avec la signature service `MissionAttendanceService::markAttendance(Mission, array<int, 'present'|'absent'>, User)` (`backend/app/Services/MissionAttendanceService.php:37`). L'authorization est server-side via `entry.missionPayment.mission_id === mission.id` (`backend/app/Services/MissionAttendanceService.php:121-125`) — un Producer qui tenterait d'envoyer l'`entry_id` d'une autre mission obtiendrait un 422 (via FormRequest `withValidator` ownership guard) puis, en défense-en-profondeur, une `RuntimeException` du service (qui surface en 500 via le handler `\Throwable` `bootstrap/app.php:207-228` — c'est OK car la 1ère ligne défensive du FormRequest l'attrape avant).
- ✅ **Format payload POST — list-of-objects** `{ "entries": [{"entry_id": 42, "status": "present"}, {"entry_id": 43, "status": "absent"}] }` — cohérent avec le wording de l'epic ligne 210 (`{entries: [{entry_id, status: 'present'|'absent'}]}`). Le controller transforme en map int=>status avant d'appeler le service. Justification du choix « list of objects » contre « map int=>status » : la map JSON `{"42": "present", ...}` a des clés string ambiguës (PHP-side `'42'` vs `42`) et aucune Resource ne sérialise naturellement vers ce shape — le list-of-objects est plus naturel à produire/consommer côté frontend.
- ✅ **Routes ajoutées dans `backend/routes/api/producer.php`** dans le groupe existant `Route::prefix('v1/producer')->middleware(['auth:sanctum', 'api.token'])` (lignes 26-110). Insertion juste après la ligne 76 `Route::post('/missions/{mission}/complete', [MissionController::class, 'complete'])` pour grouper visuellement les actions Mission. Throttle `ui-read` sur GET (cohérent `paymentStatus` ligne 82), `60,1` sur POST (cohérent `complete` ligne 76 + autres actions write).
- ✅ **Bridge backward-compat dans `MissionService::completeMission` (lignes 246-250)** : reste **inchangé fonctionnellement**. Le commentaire est mis à jour : la ligne `// This bridge will be REMOVED in FIX-26.4 once the new attendance endpoint` (ligne 249) **ne tient plus** — FIX-26.4 ajoute les endpoints SANS retirer le bridge. Patch 1 ligne pour repointer vers FIX-26.10 (`Désactivation/repurpose missions:auto-release-funds 14j` — story de fin de sprint qui nettoie le legacy auto-release ; les 11 tests `CompleteMissionTest` resteraient à migrer vers le nouveau endpoint à ce moment-là). Justification du report : retirer le bridge dans 26.4 forcerait à migrer 11 tests `CompleteMissionTest::*` (`backend/tests/Feature/Mission/CompleteMissionTest.php:100,154,245,357,367,381,397,411,425,439,446`) vers le nouveau endpoint, multipliant le scope par 3 ; l'objectif de 26.4 est strictement « exposer les endpoints Producer », pas « rendre obligatoire la nouvelle UI ».

**Décisions retenues contre alternatives** :

- ❌ **Retirer le bridge `MissionService::completeMission` lignes 246-254 dans cette story** : rejeté. Forcerait la migration des 11 tests `CompleteMissionTest` (qui posent toutes en `MissionPaymentCandidature::create([... 'escrow_status' => Locked, ... ])` SANS attendance_status, et comptent sur le bridge pour passer les entries en `present` au moment du `/complete`). Triple le scope. Reporté à FIX-26.10 (commentaire mis à jour pour refléter ce report).
- ❌ **Désactiver/retourner 410 Gone sur le legacy endpoint `/complete`** : rejeté. Idem — casserait `CompleteMissionTest` et tout caller direct API. Le legacy `/complete` continue de répondre 200 et de fonctionner via le bridge. La nouvelle UI (FIX-26.7) appelle exclusivement les nouveaux endpoints (decision FIX-26.7 → ne pas appeler `/complete`).
- ❌ **Format payload `{"entries": {"42": "present", "43": "absent"}}` (map int=>status)** : rejeté. Match plus directement la signature service mais (a) clés JSON forcées en string par la spec JSON, conversion ambiguë côté PHP entre `'42'` et `42` (gardé par le service via cast `(int) $entryId` ligne 113, mais boue du contrat), (b) ne se sérialise pas naturellement avec une Resource list-of-objects, (c) inconsistant avec le wording de l'epic ligne 210 qui dit explicitement `[{entry_id, status}]`.
- ❌ **Exposer `entry_id` comme UUID au lieu d'integer** : rejeté. Demanderait d'ajouter une colonne `uuid` à `mission_payment_candidatures` + migration + patch model — schema scope qui appartiendrait à FIX-26.1, pas à 26.4 ; le ticket schema est déjà livré et figé. Le risque d'enumeration sur entry_id integer est annulé par les guards d'ownership (FormRequest `withValidator` + service-level `entry.missionPayment.mission_id === mission.id`). Story future possible si l'API hardening devient prioritaire.
- ❌ **`AttendanceStatus::label()` ajouté à l'enum pour produire un libellé FR sérialisé par la Resource** : rejeté. (a) FIX-26.1 a livré l'enum sans `label()` (style minimaliste miroir `EscrowStatus`, cf. `_bmad-output/implementation-artifacts/fix-26-1-attendance-status-schema.md` § Architecture). (b) Ajouter `label()` à l'enum oblige à toucher une story déjà `done` (`AttendanceStatus.php`). (c) **Pas de besoin métier confirmé** dans 26.4 — le controller peut produire les libellés inline dans la Resource via un `match` local. La fonction inline reste co-localisée avec la sérialisation HTTP, n'élargit pas l'API publique de l'enum, et reste désactivable trivialement si FIX-26.7 préfère faire le mapping côté front. Décision retenue : libellés inline dans `MissionAttendanceEntryResource`.
- ❌ **Renvoyer la `MissionResource` complète dans la réponse POST `validate`** : rejeté. La `MissionResource` (`backend/app/Http/Resources/MissionResource.php:22-46`) charge `producer` + `has_paid_payment` (qui fait une SELECT EXISTS sur `mission_payments`) + `candidatures_count` — surplus pour le besoin Producer post-validation. Le payload retour minimal `{ mission: { id, titre, status, status_label, date_tournage }, entries: [Resource, ...] }` suffit pour que la Vue refresh. La pleine `MissionResource` reste accessible via le GET existant `/api/v1/producer/missions/{mission}` (`MissionController::show`) si nécessaire.
- ❌ **Étendre `CompleteMissionRequest` avec un mode « validate-attendance »** : rejeté. Couple deux contrats HTTP différents dans un seul FormRequest. `ValidateMissionAttendanceRequest` est dédié, plus court à lire, plus simple à tester. Pattern voisin déjà éclaté en plusieurs FormRequests : `CloseMissionRequest`, `ReopenMissionRequest`, `CompleteMissionRequest`, `DeleteMissionRequest`, `UpdateMissionRequest` — la convention est « 1 endpoint = 1 FormRequest ».
- ❌ **Mettre le FormRequest dans `App\Http\Requests\Producer\` namespace** : rejeté pour cette story. Le voisin le plus proche structurellement est `CompleteMissionRequest` (Producer action sur Mission via ownership check), localisé sous `App\Http\Requests\Mission\` (`backend/app/Http/Requests/Mission/CompleteMissionRequest.php:1-133`). Le namespace `Producer\` héberge `ConfirmMissionSelectionRequest` (Mission domain) ET `UpdateBioRequest`, `UpdateAgencyLogoRequest` etc. (profile-domain) — convention mixte historique. On suit la convention **« actions verbe sur Mission entity → namespace Mission »** (Close/Complete/Delete/Reopen/Store/Update/Filter/Validate Attendance) qui est la plus large dans le codebase actuel.
- ❌ **Status `Completed` accepté côté HTTP pour replay idempotent** : rejeté. Le service accepte `Completed` dans son whitelist (`MissionAttendanceService.php:45`, `94`) **uniquement** pour la défense en profondeur sous lock concurrent (D1=A décision FIX-26.3 R1, cf. `MissionAttendanceServiceTest::test_mark_attendance_revalidates_mission_status_under_lock`). Côté HTTP, restreindre à `{Closed, PendingAttendanceValidation}` est plus strict — un Producer qui POST sur une mission déjà `Completed` reçoit un 422 clair (« Mission déjà finalisée ») au lieu de paraître réussir avec une mutation no-op. La défense service-level reste intacte : si un appel HTTP passe la garde FormRequest puis la mission devient `Completed` entre temps via cron concurrent, le service rejette correctement le payload.

**Découvertes audit codebase à respecter** :

> 1. **`MissionAttendanceService` est déjà injectable via `app(MissionAttendanceService::class)`** — son constructor a deux dépendances (`MissionPaymentService`, `MissionService`) qui sont auto-résolues par le container Laravel (`backend/app/Services/MissionAttendanceService.php:25-28`). Le nouveau controller l'injecte via `private readonly MissionAttendanceService $attendanceService` dans son constructor — pattern strict miroir `MissionController::__construct` ligne 23-25.
> 2. **Mission utilise `HasRouteUuid` trait** (`backend/app/Concerns/HasRouteUuid.php:1-47`) — `getRouteKeyName()` retourne `'uuid'` (ligne 26-29). Donc `Route::get('/missions/{mission}/...')` résout automatiquement par UUID. Pas besoin d'override du model binding. Cohérent avec `complete`, `close`, `reopen` etc. déjà en place.
> 3. **`MissionPaymentCandidature` n'a PAS d'UUID** (vérifié par grep) — le `entry_id` du payload doit être l'integer auto-increment `id`. Validation FormRequest : `entries.*.entry_id => ['required', 'integer', 'min:1']`. **Pas** de règle `exists:mission_payment_candidatures,id` (l'existence + ownership sont vérifiés ensemble dans `withValidator` via subquery `whereHas('missionPayment', mission_id=...)`).
> 4. **Le pattern enveloppe `{ data, message }` est uniformément utilisé** dans `MissionController` (lignes 50-53, 73-76, 93-96, 111-114, 128-131, 144-147, 161-164, 178-181). On le reproduit. Pour la GET, le `message` est optionnel ou minimal (cf. `paymentStatus` qui n'a pas de message — c'est OK aussi). Décision retenue : GET sans `message` (consultation), POST avec `message` confirmant la validation.
> 5. **Le pattern `Route::prefix('v1/producer')->middleware(['auth:sanctum', 'api.token'])` est unique dans `routes/api/producer.php:26`** — toutes les routes Producer héritent. Pas besoin de re-déclarer `auth:sanctum` ou `api.token`. **Pas besoin** non plus du middleware `producer` (le fait que `userable_type === Producer::class` est checké dans le FormRequest `authorize()`).
> 6. **`bootstrap/app.php:129-148` normalise les `ValidationException`** en 422 avec enveloppe `{ error: {code: 'VALIDATION_ERROR', message, details} }` + champs legacy `{errors, message}` (FIX-22.2 AC #4/#9). Donc tout `validator->errors()->add()` dans `withValidator` produit automatiquement le shape FR attendu. Pas besoin de catcher manuellement.
> 7. **`bootstrap/app.php:92-100` normalise `AuthenticationException` en 401** avec `{ error: { message: 'Non authentifié.', code: 'UNAUTHENTICATED' } }`. Donc un user non-authentifié sur les nouvelles routes obtient ce shape automatiquement. Aucun code à écrire pour ce cas.
> 8. **`bootstrap/app.php:167-202` normalise `HttpException` (incl. abort(403))** en 403 avec `{ error: { message: <abort message ou 'Action non autorisée.'>, code: 'FORBIDDEN' } }`. Donc `FormRequest::authorize() === false` → Laravel raise `AuthorizationException` → handler convertit en `AccessDeniedHttpException` (HttpException 403) → enveloppe FORBIDDEN. Pas de glue à écrire.
> 9. **`bootstrap/app.php:109-127` normalise `NotFoundHttpException` + `ModelNotFoundException` en 404** avec `{ error: { message: 'Ressource introuvable.', code: 'NOT_FOUND' } }`. Donc une UUID Mission inexistante (route binding échec) → 404 NOT_FOUND automatique. Aucun glue.
> 10. **`MissionService::completeMission` (lignes 235-269) garde le bridge BackCompat (lignes 246-254)** — il auto-marque les entries `Locked + pending` en `present` AVANT `releaseFunds()`. Ce comportement reste actif pour le legacy endpoint `/complete`. La nouvelle UI (FIX-26.7) appellera exclusivement les nouveaux endpoints, donc une mission qui passe par les nouveaux endpoints n'invoquera **jamais** le bridge. Coexistence assurée.
> 11. **`MissionAttendanceService::markAttendance` ne retourne JAMAIS `null`** (`backend/app/Services/MissionAttendanceService.php:37`) — sa signature est `Mission` (non-nullable). Le controller peut consommer la valeur de retour directement sans nullsafe.
> 12. **`MissionAttendanceServiceTest` (suite FIX-26.3) couvre exhaustivement la mécanique métier de `markAttendance`** — 26 tests verts (cf. sprint-status.yaml ligne 510). FIX-26.4 NE re-teste PAS la mécanique métier : il teste uniquement les contrats HTTP (codes status, format payload, format réponse, gardes FormRequest). Pour les cas métier (race lock, idempotence, mission Completed) : 1 test feature golden-path par cas suffit.
> 13. **`Face` model expose `display_name` et `profile_photo_url` comme attributes apparents** (`backend/app/Models/Face.php` PHPDoc lignes 43-44, fillable lignes 130, 133). Donc `MissionAttendanceEntryResource` peut sérialiser `$entry->face->display_name` + `$entry->face->profile_photo_url` directement sans munging supplémentaire.
> 14. **Le test feature suite utilise `RefreshDatabase`** (cf. `MissionAttendanceServiceTest:35`, `CompleteMissionTest:27`). `ProducerAttendanceEndpointsTest` suit la même convention. La DB de test est `weact_test` (cf. memory `reference_test_db_setup.md`).
> 15. **Le pattern `Mail::fake()` est utilisé en début de test** (`MissionAttendanceServiceTest:47`, `CompleteMissionTest:102`) pour intercepter les `Mail::queue` non-fatals. À utiliser dans `ProducerAttendanceEndpointsTest` pour les tests qui appellent POST `validate` (sinon `MissionCompletedMail::queue` traverserait le mailer réel).
> 16. **Helper `createPaidMissionWithFaces($count, $status)` dans `MissionAttendanceServiceTest`** (lignes 60-136) est privé à ce fichier-test. Le nouveau test `ProducerAttendanceEndpointsTest` **duplique localement le pattern** dans son `setUp` ou son helper privé (cohérent avec la décision FIX-26.3 § Découvertes audit point 16 : duplication mineure acceptée pour préserver isolation des tests, refactor d'extraction → story dédiée future si la duplication s'avère pénalisante). Pas de nouvelle TestCase parente créée dans cette story.
> 17. **FIX-22.2 envelope normalization** rend `assertJson(['error' => ['code' => '...']])` la convention pour les assertions d'erreur. Cf. tests existants qui asserting le shape via `assertJsonStructure(['error' => ['code', 'message']])`.

**ACs en BDD format Given/When/Then** :

### Bloc A — `GET /api/v1/producer/missions/{mission}/attendance-form` — afficher le formulaire

1. **Given** un Producer authentifié = propriétaire de `$mission` (status `Closed`, payment `Paid`, 3 entries `Locked + pending` créées via le payment), **When** `GET /api/v1/producer/missions/{$mission->uuid}/attendance-form` **Then** :
   - HTTP 200.
   - `data.mission.id === $mission->uuid`, `data.mission.status === 'closed'`, `data.mission.status_label === 'Clôturée'`, `data.mission.titre === $mission->titre`, `data.mission.date_tournage` au format ISO 8601 (cf. `MissionResource:26`).
   - `data.entries` est un tableau de 3 éléments dans l'ordre de création (`MissionPaymentCandidature.id` croissant).
   - Chaque entry expose : `id` (int positif), `face` (objet avec `id` (uuid), `display_name`, `profile_photo_url`), `montant_face_recoit` (int = 90000 pour la fixture standard), `attendance_status` (`'pending'`), `attendance_status_label` (`'En attente'` — voir Task 4 § libellés), `escrow_status` (`'locked'`), `escrow_status_label` (`'Bloqué'`), `notified_at` (`null`).
   - `data.payment.montant_total_producteur` exposé (cf. AC FIX-26.7 récap financier — pour permettre au front d'afficher « X FCFA bloqués sur escrow » sans nouvelle requête).

2. **Given** un Producer propriétaire et `$mission` en `PendingAttendanceValidation` (transition mid-validation), **When** `GET .../attendance-form` **Then** :
   - HTTP 200, `data.mission.status === 'pending_attendance_validation'`, `data.mission.status_label === 'En attente de validation des présences'`.
   - `data.entries` reflète l'état actuel : entries déjà tranchées en `present + Released` apparaissent telles quelles (front peut les afficher en read-only) ; entries `pending` apparaissent toujours actionnable.

3. **Given** un Producer authentifié qui **n'est PAS propriétaire** de `$mission`, **When** `GET .../attendance-form` **Then** :
   - HTTP 403, payload `{ "error": { "message": "Cette action n'est pas autorisée", "code": "FORBIDDEN" } }`.
   - **Aucune donnée mission n'est leakée** dans la réponse.

4. **Given** un user authentifié dont `userable_type === Face::class` (Face, pas Producer), **When** `GET .../attendance-form` **Then** :
   - HTTP 403 avec enveloppe FORBIDDEN.

5. **Given** un user **non authentifié** (pas de bearer token, pas de session), **When** `GET .../attendance-form` **Then** :
   - HTTP 401, payload `{ "error": { "message": "Non authentifié.", "code": "UNAUTHENTICATED" } }` (handler global FIX-22.2).

6. **Given** un UUID Mission qui n'existe pas en DB (UUID valide mais pas de row), **When** `GET .../attendance-form` **Then** :
   - HTTP 404, payload `{ "error": { "message": "Ressource introuvable.", "code": "NOT_FOUND" } }`.

7. **Given** `$mission` en status invalide (`Draft`, `Published`, `PendingPayment`, `Completed`), **When** `GET .../attendance-form` **Then** :
   - HTTP 422 (envelope `VALIDATION_ERROR`), `details.status[0]` contient « La validation des présences n'est possible que sur une mission clôturée ou en attente de validation des présences. ».
   - **Aucune donnée entries n'est exposée** pour les missions hors-flow.

8. **Given** un Producer propriétaire et `$mission` en `Closed` mais **sans `MissionPayment`** (cas marginal — mission clôturée sans paiement initié, scénario hypothétique), **When** `GET .../attendance-form` **Then** :
   - HTTP 422, `details.payment[0]` contient « La validation des présences requiert un paiement confirmé sur la mission. ».

### Bloc B — `POST /api/v1/producer/missions/{mission}/validate-attendance` — soumettre

9. **Given** un Producer propriétaire, `$mission` en `Closed` avec 2 entries `Locked + pending`, et payload `{ "entries": [{"entry_id": entry1.id, "status": "present"}, {"entry_id": entry2.id, "status": "present"}] }`, **When** `POST .../validate-attendance` **Then** :
   - HTTP 200.
   - `data.mission.status === 'completed'`, `data.mission.status_label === 'Terminée'` (le service a finalisé via `tryCompleteIfReady` — toutes entries `Released`).
   - `data.entries[0].attendance_status === 'present'`, `escrow_status === 'released'`, `released_at` non-null ISO 8601.
   - `data.entries[1].attendance_status === 'present'`, `escrow_status === 'released'`.
   - `message === 'Présences validées avec succès.'`.
   - Wallet de chaque Face crédité (`User->balance === 90000` pour chacune).
   - `MissionCompletedMail::class` queued 2x via `Mail::fake()` + `Mail::assertQueuedCount(2)`.
   - `Notification` `mission_completed_producer` créée pour le Producer.
   - 2 `FinancialEvent` type `EscrowRelease` avec `idempotency_key` `mission_attendance_escrow_release:{entry_id}` et `metadata.reason === 'attendance_present'`.

10. **Given** un Producer propriétaire, `$mission` en `Closed` avec 3 entries `Locked + pending`, payload `[{entry1, present}, {entry2, absent}, {entry3, present}]`, **When** `POST .../validate-attendance` **Then** :
    - HTTP 200.
    - `data.mission.status === 'pending_attendance_validation'` (entry2 absent reste `Locked` non-finalisé → la mission ne peut pas se compléter).
    - `data.entries[0].attendance_status === 'present'`, `escrow_status === 'released'`.
    - `data.entries[1].attendance_status === 'absent'`, `escrow_status === 'locked'`, `notified_at` non-null (set 1ère fois par `markAttendance`).
    - `data.entries[2].attendance_status === 'present'`, `escrow_status === 'released'`.
    - 2 wallets Face crédités, wallet Face[1] inchangé (= 0).
    - 2 `FinancialEvent` `EscrowRelease`. **Aucun** `FinancialEvent` `Refund` (l'absent n'est pas finalisé tant que la fenêtre 72h Face / FIX-26.6 cron n'a pas joué).
    - `MissionCompletedMail::class` queued 2x.
    - **PAS** de notification `mission_completed_producer` (mission n'est pas Completed).

11. **Given** un Producer propriétaire, `$mission` en `Closed` avec 3 entries `Locked + pending`, payload **partiel** `[{entry1, present}]` (entry2 et entry3 omises), **When** `POST .../validate-attendance` **Then** :
    - HTTP 200.
    - `data.mission.status === 'pending_attendance_validation'` (entries 2 et 3 toujours `Locked + pending`).
    - `data.entries` retourné contient **les 3 entries** (snapshot complet du form, pas seulement celles soumises) — pour permettre au front de refresh sans deuxième GET.
    - `data.entries[0].attendance_status === 'present'`, `data.entries[1].attendance_status === 'pending'`, `data.entries[2].attendance_status === 'pending'`.
    - 1 wallet Face crédité.

12. **Given** un Producer authentifié non-propriétaire, payload valide, **When** `POST .../validate-attendance` **Then** :
    - HTTP 403 envelope FORBIDDEN. Le `FormRequest::authorize()` reject en amont (avant body validation).

13. **Given** un user non-authentifié, **When** `POST .../validate-attendance` **Then** :
    - HTTP 401 envelope UNAUTHENTICATED.

14. **Given** un UUID Mission inexistant, **When** `POST .../validate-attendance` **Then** :
    - HTTP 404 envelope NOT_FOUND.

15. **Given** un Producer propriétaire et `$mission` en status invalide pour la validation présence (`Draft`, `Published`, `PendingPayment`, `Completed`), **When** `POST .../validate-attendance` avec payload valide **Then** :
    - HTTP 422 envelope VALIDATION_ERROR, `details.status[0]` contient « La validation des présences n'est possible que sur une mission clôturée ou en attente de validation des présences. ».

16. **Given** un Producer propriétaire et payload `{ "entries": [] }` (tableau vide), **When** `POST .../validate-attendance` **Then** :
    - HTTP 422, `details.entries` contient « Au moins une entry doit être fournie. » (clé `entries`, message correspondant à la rule `required|array|min:1`).

17. **Given** payload sans clé `entries`, **When** `POST .../validate-attendance` **Then** :
    - HTTP 422, `details.entries` contient le message FR « Au moins une entry doit être fournie. ».

18. **Given** payload `{ "entries": [{"entry_id": "abc", "status": "present"}] }` (entry_id non-integer), **When** `POST .../validate-attendance` **Then** :
    - HTTP 422, `details.entries.0.entry_id` ou `details.entries.*.entry_id` contient « L'identifiant de l'entry doit être un entier positif. ».

19. **Given** payload `{ "entries": [{"entry_id": 42, "status": "pending"}] }` ou `{ "status": "disputed" }` ou `{ "status": "invalid_value" }`, **When** `POST .../validate-attendance` **Then** :
    - HTTP 422, `details.entries.0.status` contient « Les statuts acceptés sont : present, absent. » (rule `in:present,absent` avec message custom).

20. **Given** un Producer propriétaire de `$mission1` et `$mission2`, payload sur `$mission1` qui contient un `entry_id` **appartenant à `$mission2`**, **When** `POST /api/v1/producer/missions/{$mission1->uuid}/validate-attendance` **Then** :
    - HTTP 422, `details.entries[0]` contient « Une ou plusieurs entries ne correspondent pas à cette mission. ».
    - **Aucune** mutation DB sur les entries de `$mission2`.
    - **Justification** : la garde `withValidator` ownership ferme cette voie en amont du service. Si un test futur retire la garde du FormRequest, le service-level guard (`MissionAttendanceService.php:121-125`) raise une `RuntimeException` (qui surface en 500 INTERNAL_ERROR via `bootstrap/app.php:207-228` puisque `RuntimeException` n'est pas catchée par les handlers spécifiques — comportement défensif acceptable mais le 422 du FormRequest est l'UX attendue).

21. **Given** un Producer propriétaire et `POST .../validate-attendance` rejoué deux fois consécutivement avec le même payload `[{entry1, present}, {entry2, present}]` (mission complète au 1er call), **When** la 2ème invocation s'exécute **Then** :
    - HTTP 422 envelope VALIDATION_ERROR sur `details.status` (au 2ème call la mission est `Completed`, restreinte côté HTTP → 422 défensif). **Justification** : la décision retenue restreint le HTTP à `{Closed, PendingAttendanceValidation}` ; un Producer qui retentera doit voir un message clair plutôt qu'un 200 avec mutation no-op. La défense service-level (qui accepte `Completed` pour replay sous lock) reste intacte mais n'est pas atteignable depuis HTTP avec ce payload.
    - **Aucun** double crédit wallet, aucun double `FinancialEvent` (ni l'un ni l'autre n'aurait été émis même si on était passé au service — invariant 8 epic ligne 73).

22. **Given** un Producer propriétaire et un mock du service qui throw `\RuntimeException` (par ex. entry du même mission mais corrompue, scenario théorique), **When** `POST .../validate-attendance` **Then** :
    - HTTP 500 envelope `{ error: { code: 'INTERNAL_ERROR', message: 'Erreur interne du serveur.' } }` (handler `\Throwable` `bootstrap/app.php:207-228`).
    - Le test n'est pas obligatoire si la garde FormRequest ferme le seul scénario réaliste — voir Task 5 § Tests.

### Bloc C — Non-régression flux existants

23. **Given** la suite tests pré-FIX-26.4 (1951 tests verts, baseline FIX-26.3 done — cf. sprint-status.yaml ligne 510), **When** `ProducerAttendanceEndpointsTest` est ajoutée + bridge `MissionService::completeMission` reste en place + 1 commentaire 1-ligne mis à jour **Then** :
    - **`CompleteMissionTest` 11 tests restent verts SANS modification** (le bridge `MissionService::completeMission:246-254` couvre tout le flow legacy).
    - **`MissionAttendanceServiceTest` 26 tests restent verts SANS modification** (la story est une couche HTTP au-dessus du service, pas une refonte du service).
    - **`MissionAttendanceReleaseFundsTest` 7 tests restent verts SANS modification**.
    - **`AutoReleaseMissionFundsCommandTest` 1 test reste vert SANS modification**.
    - **Suite complète stays green**, nouveau total ≈ 1951 + 22 nouveaux tests `ProducerAttendanceEndpointsTest` = ~1973 verts.

### Bloc D — Invariants transversaux

24. **Given** le scope strict de la story, **Then** ces invariants sont respectés :
    - **Aucun** changement de logique métier (financière, lifecycle Mission, idempotence) — toute la mécanique reste dans `MissionAttendanceService` livré FIX-26.3.
    - **Aucune** migration DB, **aucune** modification de model Eloquent, **aucun** changement d'enum.
    - **Aucun** mailable, listener, event, notification template n'est créé. (`MissionCompletedMail` queue se déclenche via le service existant lorsqu'il appelle `releaseToFace`.)
    - **Aucun** front Vue n'est touché — FIX-26.7 dans une story dédiée future (l'UI consomme ces endpoints).
    - **Aucun** code d'erreur custom ajouté à `ErrorCodes` enum — les enveloppes FORBIDDEN/UNAUTHENTICATED/NOT_FOUND/VALIDATION_ERROR/INTERNAL_ERROR existantes suffisent.
    - **Le bridge backward-compat dans `MissionService::completeMission`** reste fonctionnel pour le legacy endpoint `/complete` ; commentaire mis à jour pour refléter le report à FIX-26.10.
    - **Le legacy endpoint `POST /api/v1/producer/missions/{mission}/complete`** reste actif et inchangé (controller, FormRequest, route).
    - **Invariant 10 epic respecté** : aucun montant n'est lu depuis le payload — tous les montants viennent de `MissionPaymentCandidature.montant_face_recoit` côté serveur, lus par le service.
    - **Invariant 11 epic respecté** : ownership Producer→Mission enforcé en deux couches (FormRequest authorize + service-level `isProducerMissionOwner`).

## Tasks / Subtasks

### Task 1 — Créer la Resource `MissionAttendanceEntryResource` (AC: #1, #2, #9, #10, #11)

- [x] 1.1 Créer `backend/app/Http/Resources/MissionAttendanceEntryResource.php` :
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Http\Resources;

  use App\Enums\AttendanceStatus;
  use App\Enums\EscrowStatus;
  use Illuminate\Http\Request;
  use Illuminate\Http\Resources\Json\JsonResource;

  /**
   * @mixin \App\Models\MissionPaymentCandidature
   */
  class MissionAttendanceEntryResource extends JsonResource
  {
      /**
       * @return array<string, mixed>
       */
      public function toArray(Request $request): array
      {
          return [
              'id' => $this->id,
              'face' => $this->whenLoaded('face', fn (): array => [
                  'id' => $this->face->uuid,
                  'display_name' => $this->face->display_name,
                  'profile_photo_url' => $this->face->profile_photo_url,
              ]),
              'montant_face_recoit' => (int) $this->montant_face_recoit,
              'attendance_status' => $this->attendance_status->value,
              'attendance_status_label' => $this->attendanceStatusLabel($this->attendance_status),
              'escrow_status' => $this->escrow_status->value,
              'escrow_status_label' => $this->escrowStatusLabel($this->escrow_status),
              'released_at' => $this->released_at?->toIso8601String(),
              'refunded_at' => $this->refunded_at?->toIso8601String(),
              'notified_at' => $this->notified_at?->toIso8601String(),
          ];
      }

      private function attendanceStatusLabel(AttendanceStatus $status): string
      {
          return match ($status) {
              AttendanceStatus::Pending => 'En attente',
              AttendanceStatus::Present => 'Présente',
              AttendanceStatus::Absent => 'Absente',
              AttendanceStatus::Disputed => 'Contestée',
          };
      }

      private function escrowStatusLabel(EscrowStatus $status): string
      {
          return match ($status) {
              EscrowStatus::Pending => 'En attente',
              EscrowStatus::Locked => 'Bloqué',
              EscrowStatus::Released => 'Libéré',
              EscrowStatus::Refunded => 'Remboursé',
          };
      }
  }
  ```
  - **Justification structurelle** :
    - `id` est l'integer auto-increment de `mission_payment_candidatures` (pas un UUID — invariant entry_id integer).
    - `face` est `whenLoaded` car la Resource est appelée avec `$entry->loadMissing('face')` côté controller — les champs du tableau ne sont sérialisés que si la relation est chargée (sinon le bloc reste absent), évite le N+1 silencieux.
    - `attendance_status_label` et `escrow_status_label` sont rendus inline via `match` privé (cf. décision retenue § Architecture — pas d'ajout de méthode `label()` à l'enum dans cette story).
    - `montant_face_recoit` cast `(int)` défensif — la column DB est `unsignedInteger` mais le cast Eloquent est déjà `'integer'` (cf. `MissionPaymentCandidature::casts:46`), le cast supplémentaire est ceinture+bretelles.
    - `released_at`, `refunded_at`, `notified_at` exposés ensemble pour cohérence (les 3 timestamps escrow forment un triplet logique : moment de libération vers Face / moment de remboursement Producer / moment de notification absence Face). `released_at` est consommé par AC #9 (test « entries released après mission complétée »), `refunded_at` reste `null` dans tous les flows FIX-26.4 (les absents ne sont pas refundés tant que FIX-26.6 cron n'a pas joué — exposé pour cohérence et utilité future FIX-26.6/26.8). `notified_at` est utilisé par FIX-26.5/26.7 pour afficher la fenêtre 72h Face.
- [x] 1.2 Lancer `cd backend && php -l app/Http/Resources/MissionAttendanceEntryResource.php` → 0 erreur de syntaxe.
- [x] 1.3 Lancer `cd backend && ./vendor/bin/phpstan analyse app/Http/Resources/MissionAttendanceEntryResource.php --level=max` → 0 erreur (ou si erreur sur `$this->whenLoaded(...)` callable typing : ajouter PHPDoc `@var \App\Models\Face $this->face` dans le callback ou utiliser `$this->face?->...`).
- [x] 1.4 Lancer `cd backend && ./vendor/bin/pint --test app/Http/Resources/MissionAttendanceEntryResource.php` → `{"result":"pass"}`.

### Task 2 — Créer le FormRequest `ValidateMissionAttendanceRequest` (AC: #12-#21)

- [x] 2.1 Créer `backend/app/Http/Requests/Mission/ValidateMissionAttendanceRequest.php` :
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Http\Requests\Mission;

  use App\Enums\MissionPaymentStatus;
  use App\Enums\MissionStatus;
  use App\Models\Mission;
  use App\Models\MissionPayment;
  use App\Models\MissionPaymentCandidature;
  use App\Models\Producer;
  use Illuminate\Foundation\Http\FormRequest;

  class ValidateMissionAttendanceRequest extends FormRequest
  {
      public function authorize(): bool
      {
          $user = $this->user();

          if (! $user || $user->userable_type !== Producer::class) {
              return false;
          }

          /** @var Mission $mission */
          $mission = $this->route('mission');

          return $user->userable_id === $mission->producer_id;
      }

      /**
       * @return array<string, mixed>
       */
      public function rules(): array
      {
          return [
              'entries' => ['required', 'array', 'min:1'],
              'entries.*' => ['required', 'array'],
              'entries.*.entry_id' => ['required', 'integer', 'min:1'],
              'entries.*.status' => ['required', 'string', 'in:present,absent'],
          ];
      }

      /**
       * @return array<string, string>
       */
      public function messages(): array
      {
          return [
              'entries.required' => 'Au moins une entry doit être fournie.',
              'entries.array' => 'Au moins une entry doit être fournie.',
              'entries.min' => 'Au moins une entry doit être fournie.',
              'entries.*.entry_id.required' => 'L\'identifiant de l\'entry est requis.',
              'entries.*.entry_id.integer' => 'L\'identifiant de l\'entry doit être un entier positif.',
              'entries.*.entry_id.min' => 'L\'identifiant de l\'entry doit être un entier positif.',
              'entries.*.status.required' => 'Le statut de l\'entry est requis.',
              'entries.*.status.in' => 'Les statuts acceptés sont : present, absent.',
          ];
      }

      public function withValidator($validator): void
      {
          $validator->after(function ($validator) {
              /** @var Mission $mission */
              $mission = $this->route('mission');

              // Status guard — narrower than the service whitelist (Closed | PendingAttendanceValidation).
              if (! in_array($mission->status, [MissionStatus::Closed, MissionStatus::PendingAttendanceValidation], true)) {
                  $validator->errors()->add(
                      'status',
                      'La validation des présences n\'est possible que sur une mission clôturée ou en attente de validation des présences.'
                  );

                  return;
              }

              // Payment guard — must be Paid (defensive; service also re-checks under lock).
              /** @var MissionPayment|null $payment */
              $payment = $mission->payment;

              if (! $payment || $payment->status !== MissionPaymentStatus::Paid) {
                  $validator->errors()->add(
                      'payment',
                      'La validation des présences requiert un paiement confirmé sur la mission.'
                  );

                  return;
              }

              // Ownership guard on entry_ids — every entry_id in the payload MUST belong to this mission.
              if ($validator->errors()->isNotEmpty()) {
                  return; // skip if the rules() above already failed (entries shape invalid)
              }

              /** @var array<int, array{entry_id: int|string, status: string}> $entries */
              $entries = (array) $this->input('entries', []);
              $payloadEntryIds = array_values(array_unique(array_map(
                  static fn (array $row): int => (int) $row['entry_id'],
                  $entries,
              )));

              if ($payloadEntryIds === []) {
                  return;
              }

              $missionEntryIds = MissionPaymentCandidature::query()
                  ->whereHas('missionPayment', fn ($q) => $q->where('mission_id', $mission->id))
                  ->whereIn('id', $payloadEntryIds)
                  ->pluck('id')
                  ->map(static fn ($id): int => (int) $id)
                  ->all();

              $foreignEntryIds = array_diff($payloadEntryIds, $missionEntryIds);

              if ($foreignEntryIds !== []) {
                  $validator->errors()->add(
                      'entries',
                      'Une ou plusieurs entries ne correspondent pas à cette mission.'
                  );
              }
          });
      }
  }
  ```
  - **Justification structurelle** :
    - `authorize()` reproduit strictement le pattern `CompleteMissionRequest::authorize:22-35` (Producer + ownership). Retour `false` → AccessDeniedHttpException 403 → enveloppe FORBIDDEN automatique.
    - `rules()` valide la forme du payload : `entries` est un tableau non-vide d'objets, chacun avec `entry_id` integer positif et `status` ∈ {present, absent}. Pas de règle `exists` sur `entry_id` — l'ownership guard est plus précis (un entry existe peut-être mais appartient à une autre mission).
    - `withValidator()` enchaîne 3 gardes 422 défensifs : status mission, payment, ownership entry_id. Premier en erreur retourne pour éviter la cascade. Pattern strict miroir `CompleteMissionRequest::withValidator:52-132`.
    - Le subquery `whereHas('missionPayment', mission_id=...)` s'appuie sur la relation `MissionPaymentCandidature::missionPayment(): BelongsTo` (model ligne 56-59) et `MissionPayment::mission(): BelongsTo` (model ligne 55-58). Une seule requête SQL pour valider en bloc tous les entry_ids du payload.
    - Le bloc `if ($validator->errors()->isNotEmpty()) { return; }` AVANT l'ownership guard évite le subquery si la forme `entries.*` échoue (par ex. payload avec `entry_id => 'abc'` → `$entries[0]['entry_id']` cast en `(int) 0` qui sortirait des résultats DB et produirait un 422 redondant).
- [x] 2.2 Lancer `cd backend && php -l app/Http/Requests/Mission/ValidateMissionAttendanceRequest.php` → 0 erreur.
- [x] 2.3 Lancer `cd backend && ./vendor/bin/phpstan analyse app/Http/Requests/Mission/ValidateMissionAttendanceRequest.php --level=max` → 0 erreur (PHPStan peut signaler `mixed` sur `$this->route('mission')` — déjà annoté `@var Mission $mission` PHPDoc inline).
- [x] 2.4 Lancer `cd backend && ./vendor/bin/pint --test app/Http/Requests/Mission/ValidateMissionAttendanceRequest.php` → `{"result":"pass"}`.

### Task 3 — Créer le controller `MissionAttendanceController` (AC: #1-#11, #15, #20)

- [x] 3.1 Créer `backend/app/Http/Controllers/Api/V1/Producer/MissionAttendanceController.php` :
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Http\Controllers\Api\V1\Producer;

  use App\Enums\MissionPaymentStatus;
  use App\Enums\MissionStatus;
  use App\Http\Controllers\Controller;
  use App\Http\Requests\Mission\ValidateMissionAttendanceRequest;
  use App\Http\Resources\MissionAttendanceEntryResource;
  use App\Models\Mission;
  use App\Models\MissionPayment;
  use App\Models\Producer;
  use App\Services\MissionAttendanceService;
  use Illuminate\Http\JsonResponse;
  use Illuminate\Http\Request;
  use Illuminate\Validation\ValidationException;

  class MissionAttendanceController extends Controller
  {
      public function __construct(
          private readonly MissionAttendanceService $attendanceService,
      ) {}

      /**
       * Display the attendance form for a Producer-owned mission.
       *
       * GET /api/v1/producer/missions/{mission}/attendance-form
       */
      public function show(Request $request, Mission $mission): JsonResponse
      {
          $user = $request->user();

          if ($user->userable_type !== Producer::class || $user->userable_id !== $mission->producer_id) {
              abort(403, 'Cette action n\'est pas autorisée');
          }

          if (! in_array($mission->status, [MissionStatus::Closed, MissionStatus::PendingAttendanceValidation], true)) {
              throw ValidationException::withMessages([
                  'status' => ['La validation des présences n\'est possible que sur une mission clôturée ou en attente de validation des présences.'],
              ]);
          }

          /** @var MissionPayment|null $payment */
          $payment = $mission->payment;

          if (! $payment || $payment->status !== MissionPaymentStatus::Paid) {
              throw ValidationException::withMessages([
                  'payment' => ['La validation des présences requiert un paiement confirmé sur la mission.'],
              ]);
          }

          $entries = $payment->entries()
              ->with('face')
              ->orderBy('id')
              ->get();

          return response()->json([
              'data' => [
                  'mission' => [
                      'id' => $mission->uuid,
                      'titre' => $mission->titre,
                      'status' => $mission->status->value,
                      'status_label' => $mission->status->label(),
                      'date_tournage' => $mission->date_tournage?->toIso8601String(),
                  ],
                  'payment' => [
                      'montant_total_producteur' => (int) $payment->montant_total_producteur,
                      'nombre_faces_retenues' => (int) $payment->nombre_faces_retenues,
                  ],
                  'entries' => MissionAttendanceEntryResource::collection($entries),
              ],
          ]);
      }

      /**
       * Submit Producer presence/absence decisions on selected Faces.
       *
       * POST /api/v1/producer/missions/{mission}/validate-attendance
       */
      public function validate(ValidateMissionAttendanceRequest $request, Mission $mission): JsonResponse
      {
          /** @var array<int, array{entry_id: int|string, status: string}> $rawEntries */
          $rawEntries = $request->validated('entries');

          $entryMap = [];
          foreach ($rawEntries as $row) {
              $entryMap[(int) $row['entry_id']] = $row['status'];
          }

          $freshMission = $this->attendanceService->markAttendance($mission, $entryMap, $request->user());

          /** @var MissionPayment|null $payment */
          $payment = $freshMission->payment;
          $entries = $payment
              ? $payment->entries()->with('face')->orderBy('id')->get()
              : collect();

          return response()->json([
              'data' => [
                  'mission' => [
                      'id' => $freshMission->uuid,
                      'titre' => $freshMission->titre,
                      'status' => $freshMission->status->value,
                      'status_label' => $freshMission->status->label(),
                      'date_tournage' => $freshMission->date_tournage?->toIso8601String(),
                  ],
                  'entries' => MissionAttendanceEntryResource::collection($entries),
              ],
              'message' => 'Présences validées avec succès.',
          ]);
      }
  }
  ```
  - **Justification structurelle** :
    - `show()` utilise un guard inline (cf. `MissionPaymentController::paymentStatus:94-153`). Pas de FormRequest dédié pour un GET sans body — overhead non justifié.
    - `validate()` reçoit le FormRequest type-hint qui auto-déclenche `authorize()` + `rules()` + `withValidator()` (Laravel injecte). Si l'un échoue : 403 ou 422 surfacés via le handler global avant que le controller body ne s'exécute.
    - La transformation `[{entry_id, status}, ...]` → `[entry_id_int => status, ...]` se fait inline juste après `validated('entries')`. Le map est ensuite passé tel quel au service.
    - `$freshMission = $this->attendanceService->markAttendance(...)` retourne le `Mission` frais ; le service a déjà lu le state final post-transition. Le payload de réponse re-charge les entries avec `with('face')` pour la sérialisation Resource (pas de N+1).
    - Le snapshot retour POST contient **toutes les entries** (snapshot complet du form, AC #11) — pas seulement celles soumises — pour permettre au front de refresh sans deuxième GET.
    - **Pas de try/catch** sur le service : les `ValidationException` levées par le service (race condition lock, idempotence rejet) surfacent automatiquement en 422 via le handler global FIX-22.2 ; les `RuntimeException` (entry corrompue, mission introuvable sous lock) surfacent en 500 INTERNAL_ERROR via le handler `\Throwable` — comportement défensif acceptable car la garde FormRequest devrait empêcher tous les scénarios atteignables côté client.
- [x] 3.2 Lancer `cd backend && php -l app/Http/Controllers/Api/V1/Producer/MissionAttendanceController.php` → 0 erreur.
- [x] 3.3 Lancer `cd backend && ./vendor/bin/phpstan analyse app/Http/Controllers/Api/V1/Producer/MissionAttendanceController.php --level=max` → 0 erreur (Larastan peut signaler `mixed` sur `validated('entries', [])` — auto-narrow via PHPDoc inline `@var array<...> $rawEntries`, déjà présent).
- [x] 3.4 Lancer `cd backend && ./vendor/bin/pint --test app/Http/Controllers/Api/V1/Producer/MissionAttendanceController.php` → `{"result":"pass"}`.

### Task 4 — Mettre à jour les routes Producer (AC: #1, #9 — exposition HTTP)

- [x] 4.1 Patcher `backend/routes/api/producer.php` :
  - Ajouter l'import dans le bloc `use` en haut du fichier (lignes 5-14), inséré **avant** `use App\Http\Controllers\Api\V1\Producer\MissionController;` pour préserver l'ordre alphabétique PSR-12 (`MissionAttendance` < `MissionController` car `A` < `C`) — Pint auto-trie de toute façon, mais on écrit l'ordre correct dès le départ :
    ```php
    use App\Http\Controllers\Api\V1\Producer\MissionAttendanceController;
    ```
  - Insérer **après la ligne 76** (`Route::post('/missions/{mission}/complete', ...)`) le bloc suivant — co-localisé avec les autres actions Mission :
    ```php
    // Mission attendance routes (FIX-26.4)
    Route::get('/missions/{mission}/attendance-form', [MissionAttendanceController::class, 'show'])
        ->middleware('throttle:ui-read');
    Route::post('/missions/{mission}/validate-attendance', [MissionAttendanceController::class, 'validate'])
        ->middleware('throttle:60,1');
    ```
  - **Justification** :
    - Throttle `ui-read` cohérent avec `paymentStatus` (ligne 82) et tous les GET du fichier — c'est le rate limiter dédié aux lectures UI.
    - Throttle `60,1` cohérent avec `complete`, `close`, `reopen` (lignes 71-76) — actions write Producer.
    - Pas de middleware `producer` ajouté : la garde Producer est dans `MissionAttendanceController::show` (inline) et `ValidateMissionAttendanceRequest::authorize()`. Cohérent avec le pattern `complete` qui ne met pas non plus `producer` middleware (l'auth Producer est dans `CompleteMissionRequest::authorize`).
    - Pas de middleware `verified` (email verification) : ce n'est pas une action de création publique, c'est une action sur une mission déjà payée par le Producer (qui est donc déjà vérifié au moment du `confirm-selection` — verified est imposé sur `store` ligne 64 mais pas sur les actions ultérieures).
- [x] 4.2 Lancer `cd backend && php -l routes/api/producer.php` → 0 erreur.
- [x] 4.3 Lancer `cd backend && php artisan route:list --path=api/v1/producer/missions | grep -E "attendance-form|validate-attendance"` → 2 routes affichées avec les bons URI/method/action.

### Task 5 — Mettre à jour le commentaire bridge `MissionService::completeMission` (AC: #24, scope clarté)

- [x] 5.1 Patcher `backend/app/Services/MissionService.php` lignes 246-250 :
  - Remplacer les 5 lignes du commentaire bridge :
    ```php
    // FIX-26.2 BACKWARD-COMPAT BRIDGE — TEMPORARY
    // Auto-mark all `Locked + pending` entries as `present` so the legacy Producer
    // flow (without the new attendance UI) continues to behave as before.
    // This bridge will be REMOVED in FIX-26.4 once the new attendance endpoint
    // (POST /api/v1/producer/missions/{uuid}/validate-attendance) takes over.
    ```
    par :
    ```php
    // FIX-26.2 BACKWARD-COMPAT BRIDGE — TEMPORARY
    // Auto-mark all `Locked + pending` entries as `present` so the legacy Producer
    // flow (POST /api/v1/producer/missions/{uuid}/complete, called by older clients
    // and existing CompleteMissionTest fixtures) continues to behave as before.
    // FIX-26.4 added the new attendance endpoint
    // (POST /api/v1/producer/missions/{uuid}/validate-attendance) but kept this
    // bridge intact to avoid migrating CompleteMissionTest. Bridge retirement is
    // deferred to FIX-26.10 (legacy auto-release-funds cron deprecation).
    ```
  - **Justification** : 1-ligne de logique inchangée (le `update(['attendance_status' => Present])` reste au même endroit). Seul le commentaire est patché pour refléter la décision retenue (bridge non retiré dans 26.4) et pointer le bon ticket de retrait (FIX-26.10). Cf. § Architecture retenue : « FIX-26.4 ajoute les endpoints SANS retirer le bridge ».
- [x] 5.2 Lancer `cd backend && php -l app/Services/MissionService.php` → 0 erreur.
- [x] 5.3 Lancer `cd backend && ./vendor/bin/phpstan analyse app/Services/MissionService.php --level=max` → 0 nouvelle erreur (changement de commentaire = no-op pour PHPStan).
- [x] 5.4 Lancer `cd backend && ./vendor/bin/pint --test app/Services/MissionService.php` → `{"result":"pass"}`.

### Task 6 — Créer `ProducerAttendanceEndpointsTest` (AC: #1-#23)

- [x] 6.1 Créer `backend/tests/Feature/Mission/ProducerAttendanceEndpointsTest.php` avec :
  - Imports requis :
    ```php
    use App\Enums\AttendanceStatus;
    use App\Enums\CandidatureStatus;
    use App\Enums\EscrowStatus;
    use App\Enums\FinancialEventType;
    use App\Enums\MissionPaymentStatus;
    use App\Enums\MissionStatus;
    use App\Mail\MissionCompletedMail;
    use App\Models\Candidature;
    use App\Models\Face;
    use App\Models\FinancialEvent;
    use App\Models\Mission;
    use App\Models\MissionPayment;
    use App\Models\MissionPaymentCandidature;
    use App\Models\Notification;
    use App\Models\Producer;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;
    use Illuminate\Support\Facades\Mail;
    use Tests\TestCase;
    ```
  - `class ProducerAttendanceEndpointsTest extends TestCase` avec `use RefreshDatabase;`.
  - Properties privées : `Producer $producer`, `User $producerUser`.
  - `setUp()` :
    ```php
    parent::setUp();

    Mail::fake();

    $this->producer = Producer::factory()->create();
    $this->producerUser = User::factory()->create([
        'userable_type' => Producer::class,
        'userable_id' => $this->producer->id,
    ]);
    ```
- [x] 6.2 Helper local `createPaidMissionWithFaces(int $faceCount, MissionStatus $missionStatus = MissionStatus::Closed): array{0: Mission, 1: list<array{candidature: Candidature, entry: MissionPaymentCandidature, faceUser: User, face: Face}>}` — strict miroir de `MissionAttendanceServiceTest::createPaidMissionWithFaces` (`backend/tests/Feature/Mission/MissionAttendanceServiceTest.php:60-136`) — copier-coller verbatim. Justification de la duplication : isolation tests (cf. découverte audit point 16 FIX-26.3 ; refactor d'extraction reporté à story dédiée future).
- [x] 6.3 Tests **GET attendance-form** (8 tests) :
  - `test_get_form_returns_entries_for_owner_producer` (AC #1) :
    ```php
    [$mission, $faces] = $this->createPaidMissionWithFaces(3);

    $response = $this->actingAs($this->producerUser)
        ->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form");

    $response->assertOk()
        ->assertJsonPath('data.mission.id', $mission->uuid)
        ->assertJsonPath('data.mission.status', 'closed')
        ->assertJsonPath('data.mission.status_label', 'Clôturée')
        ->assertJsonPath('data.payment.montant_total_producteur', 330000)
        ->assertJsonPath('data.payment.nombre_faces_retenues', 3)
        ->assertJsonCount(3, 'data.entries')
        ->assertJsonPath('data.entries.0.id', $faces[0]['entry']->id)
        ->assertJsonPath('data.entries.0.face.id', $faces[0]['face']->uuid)
        ->assertJsonPath('data.entries.0.face.display_name', $faces[0]['face']->display_name)
        ->assertJsonPath('data.entries.0.montant_face_recoit', 90000)
        ->assertJsonPath('data.entries.0.attendance_status', 'pending')
        ->assertJsonPath('data.entries.0.attendance_status_label', 'En attente')
        ->assertJsonPath('data.entries.0.escrow_status', 'locked')
        ->assertJsonPath('data.entries.0.escrow_status_label', 'Bloqué')
        ->assertJsonPath('data.entries.0.notified_at', null);
    ```
  - `test_get_form_works_for_pending_attendance_validation_status` (AC #2) :
    ```php
    [$mission] = $this->createPaidMissionWithFaces(2);
    $mission->update(['status' => MissionStatus::PendingAttendanceValidation]);

    $response = $this->actingAs($this->producerUser)
        ->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form");

    $response->assertOk()
        ->assertJsonPath('data.mission.status', 'pending_attendance_validation')
        ->assertJsonPath('data.mission.status_label', 'En attente de validation des présences');
    ```
  - `test_get_form_returns_403_for_non_owner` (AC #3) :
    ```php
    [$mission] = $this->createPaidMissionWithFaces(1);
    $otherProducer = Producer::factory()->create();
    $otherProducerUser = User::factory()->create([
        'userable_type' => Producer::class,
        'userable_id' => $otherProducer->id,
    ]);

    $this->actingAs($otherProducerUser)
        ->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
    ```
  - `test_get_form_returns_403_for_face_user` (AC #4) — couvre la **première branche** du check inline `userable_type !== Producer::class` (AC #3 couvre la **seconde** branche `userable_id !== mission.producer_id`) :
    ```php
    [$mission] = $this->createPaidMissionWithFaces(1);
    $face = Face::factory()->create();
    $faceUser = User::factory()->create([
        'userable_type' => Face::class,
        'userable_id' => $face->id,
    ]);

    $this->actingAs($faceUser)
        ->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
    ```
  - `test_get_form_returns_401_for_unauthenticated_user` (AC #5) :
    ```php
    [$mission] = $this->createPaidMissionWithFaces(1);

    $this->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form")
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    ```
  - `test_get_form_returns_404_for_unknown_uuid` (AC #6) :
    ```php
    $this->actingAs($this->producerUser)
        ->getJson('/api/v1/producer/missions/00000000-0000-0000-0000-000000000000/attendance-form')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'NOT_FOUND');
    ```
  - `test_get_form_returns_422_for_invalid_mission_status` (AC #7) :
    ```php
    $publishedMission = Mission::factory()->published()->create([
        'producer_id' => $this->producer->id,
    ]);

    $this->actingAs($this->producerUser)
        ->getJson("/api/v1/producer/missions/{$publishedMission->uuid}/attendance-form")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    ```
  - `test_get_form_returns_422_for_closed_mission_without_paid_payment` (AC #8) — fixture custom (PAS via `createPaidMissionWithFaces` qui crée un MissionPayment Paid) :
    ```php
    $closedMission = Mission::factory()->closed()->create([
        'producer_id' => $this->producer->id,
    ]);
    // Note: NO MissionPayment created — scenario marginal "Closed sans paiement".

    $this->actingAs($this->producerUser)
        ->getJson("/api/v1/producer/missions/{$closedMission->uuid}/attendance-form")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    ```
- [x] 6.4 Tests **POST validate-attendance happy paths** (≈ 4 tests) :
  - `test_validate_attendance_completes_mission_with_all_present` (AC #9) :
    ```php
    [$mission, $faces] = $this->createPaidMissionWithFaces(2);

    $response = $this->actingAs($this->producerUser)->postJson(
        "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
        ['entries' => [
            ['entry_id' => $faces[0]['entry']->id, 'status' => 'present'],
            ['entry_id' => $faces[1]['entry']->id, 'status' => 'present'],
        ]],
    );

    $response->assertOk()
        ->assertJsonPath('data.mission.status', 'completed')
        ->assertJsonPath('data.mission.status_label', 'Terminée')
        ->assertJsonPath('message', 'Présences validées avec succès.')
        ->assertJsonCount(2, 'data.entries')
        ->assertJsonPath('data.entries.0.attendance_status', 'present')
        ->assertJsonPath('data.entries.0.escrow_status', 'released')
        ->assertJsonPath('data.entries.0.released_at', fn ($value): bool => is_string($value) && $value !== '')
        ->assertJsonPath('data.entries.0.refunded_at', null)
        ->assertJsonPath('data.entries.1.attendance_status', 'present')
        ->assertJsonPath('data.entries.1.escrow_status', 'released')
        ->assertJsonPath('data.entries.1.released_at', fn ($value): bool => is_string($value) && $value !== '');

    $this->assertSame(90000, $faces[0]['faceUser']->refresh()->balance);
    $this->assertSame(90000, $faces[1]['faceUser']->refresh()->balance);
    Mail::assertQueued(MissionCompletedMail::class, 2);
    $this->assertSame(2, FinancialEvent::where('type', FinancialEventType::EscrowRelease)->count());
    $this->assertTrue(
        Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'mission_completed_producer')
            ->exists(),
    );
    ```
  - `test_validate_attendance_keeps_absent_locked_and_mission_pending` (AC #10) :
    ```php
    [$mission, $faces] = $this->createPaidMissionWithFaces(3);

    $response = $this->actingAs($this->producerUser)->postJson(
        "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
        ['entries' => [
            ['entry_id' => $faces[0]['entry']->id, 'status' => 'present'],
            ['entry_id' => $faces[1]['entry']->id, 'status' => 'absent'],
            ['entry_id' => $faces[2]['entry']->id, 'status' => 'present'],
        ]],
    );

    $response->assertOk()
        ->assertJsonPath('data.mission.status', 'pending_attendance_validation')
        ->assertJsonPath('data.entries.1.attendance_status', 'absent')
        ->assertJsonPath('data.entries.1.escrow_status', 'locked')
        ->assertJsonPath('data.entries.1.released_at', null)
        ->assertJsonPath('data.entries.1.refunded_at', null)
        ->assertJsonPath('data.entries.1.notified_at', fn ($value): bool => is_string($value) && $value !== '');

    $entry1 = $faces[1]['entry']->fresh();
    $this->assertSame(EscrowStatus::Locked, $entry1->escrow_status);
    $this->assertSame(AttendanceStatus::Absent, $entry1->attendance_status);
    $this->assertNotNull($entry1->notified_at);

    $this->assertSame(0, $faces[1]['faceUser']->refresh()->balance);
    Mail::assertQueued(MissionCompletedMail::class, 2);
    $this->assertSame(0, FinancialEvent::where('type', FinancialEventType::Refund)->count());
    ```
  - `test_validate_attendance_with_partial_payload_returns_full_snapshot` (AC #11) :
    ```php
    [$mission, $faces] = $this->createPaidMissionWithFaces(3);

    $response = $this->actingAs($this->producerUser)->postJson(
        "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
        ['entries' => [['entry_id' => $faces[0]['entry']->id, 'status' => 'present']]],
    );

    $response->assertOk()
        ->assertJsonPath('data.mission.status', 'pending_attendance_validation')
        ->assertJsonCount(3, 'data.entries')
        ->assertJsonPath('data.entries.0.attendance_status', 'present')
        ->assertJsonPath('data.entries.1.attendance_status', 'pending')
        ->assertJsonPath('data.entries.2.attendance_status', 'pending');
    ```
  - `test_validate_attendance_works_on_pending_attendance_validation_status` (cohérence) :
    ```php
    [$mission, $faces] = $this->createPaidMissionWithFaces(2);
    $mission->update(['status' => MissionStatus::PendingAttendanceValidation]);

    $response = $this->actingAs($this->producerUser)->postJson(
        "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
        ['entries' => [
            ['entry_id' => $faces[0]['entry']->id, 'status' => 'present'],
            ['entry_id' => $faces[1]['entry']->id, 'status' => 'present'],
        ]],
    );

    $response->assertOk()->assertJsonPath('data.mission.status', 'completed');
    ```
- [x] 6.5 Tests **POST validate-attendance auth/auth boundary** (≈ 3 tests) :
  - `test_validate_attendance_returns_403_for_non_owner_producer` (AC #12) :
    ```php
    [$mission, $faces] = $this->createPaidMissionWithFaces(1);
    $otherProducer = Producer::factory()->create();
    $otherProducerUser = User::factory()->create([
        'userable_type' => Producer::class,
        'userable_id' => $otherProducer->id,
    ]);

    $this->actingAs($otherProducerUser)->postJson(
        "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
        ['entries' => [['entry_id' => $faces[0]['entry']->id, 'status' => 'present']]],
    )->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');

    $this->assertSame(AttendanceStatus::Pending, $faces[0]['entry']->fresh()->attendance_status);
    ```
  - `test_validate_attendance_returns_401_for_unauthenticated` (AC #13) :
    ```php
    [$mission, $faces] = $this->createPaidMissionWithFaces(1);

    $this->postJson(
        "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
        ['entries' => [['entry_id' => $faces[0]['entry']->id, 'status' => 'present']]],
    )->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    ```
  - `test_validate_attendance_returns_404_for_unknown_uuid` (AC #14) :
    ```php
    $this->actingAs($this->producerUser)->postJson(
        '/api/v1/producer/missions/00000000-0000-0000-0000-000000000000/validate-attendance',
        ['entries' => [['entry_id' => 1, 'status' => 'present']]],
    )->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
    ```
- [x] 6.6 Tests **POST validate-attendance payload validation** (≈ 5 tests) :
  - `test_validate_attendance_returns_422_for_invalid_mission_status` (AC #15) :
    ```php
    $publishedMission = Mission::factory()->published()->create([
        'producer_id' => $this->producer->id,
    ]);

    $this->actingAs($this->producerUser)->postJson(
        "/api/v1/producer/missions/{$publishedMission->uuid}/validate-attendance",
        ['entries' => [['entry_id' => 1, 'status' => 'present']]],
    )->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
    ```
  - `test_validate_attendance_returns_422_for_empty_entries` (AC #16) :
    ```php
    [$mission] = $this->createPaidMissionWithFaces(1);

    $this->actingAs($this->producerUser)->postJson(
        "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
        ['entries' => []],
    )->assertStatus(422)
      ->assertJsonValidationErrors(['entries']);
    ```
  - `test_validate_attendance_returns_422_for_missing_entries_key` (AC #17) :
    ```php
    [$mission] = $this->createPaidMissionWithFaces(1);

    $this->actingAs($this->producerUser)->postJson(
        "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
        [],
    )->assertStatus(422)->assertJsonValidationErrors(['entries']);
    ```
  - `test_validate_attendance_returns_422_for_non_integer_entry_id` (AC #18) :
    ```php
    [$mission] = $this->createPaidMissionWithFaces(1);

    $this->actingAs($this->producerUser)->postJson(
        "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
        ['entries' => [['entry_id' => 'abc', 'status' => 'present']]],
    )->assertStatus(422)->assertJsonValidationErrors(['entries.0.entry_id']);
    ```
  - `test_validate_attendance_returns_422_for_invalid_status_value` (AC #19) :
    ```php
    [$mission, $faces] = $this->createPaidMissionWithFaces(1);

    $this->actingAs($this->producerUser)->postJson(
        "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
        ['entries' => [['entry_id' => $faces[0]['entry']->id, 'status' => 'disputed']]],
    )->assertStatus(422)->assertJsonValidationErrors(['entries.0.status']);
    ```
  - `test_validate_attendance_returns_422_for_entry_belonging_to_another_mission` (AC #20) :
    ```php
    [$mission1] = $this->createPaidMissionWithFaces(1);
    [, $faces2] = $this->createPaidMissionWithFaces(1);

    $this->actingAs($this->producerUser)->postJson(
        "/api/v1/producer/missions/{$mission1->uuid}/validate-attendance",
        ['entries' => [['entry_id' => $faces2[0]['entry']->id, 'status' => 'present']]],
    )->assertStatus(422)->assertJsonValidationErrors(['entries']);

    $this->assertSame(AttendanceStatus::Pending, $faces2[0]['entry']->fresh()->attendance_status);
    ```
- [x] 6.7 Test **idempotence/replay** (AC #21) :
  - `test_replay_on_completed_mission_returns_422` :
    ```php
    [$mission, $faces] = $this->createPaidMissionWithFaces(1);

    $this->actingAs($this->producerUser)->postJson(
        "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
        ['entries' => [['entry_id' => $faces[0]['entry']->id, 'status' => 'present']]],
    )->assertOk();

    $this->assertSame(MissionStatus::Completed, $mission->fresh()->status);

    $response = $this->actingAs($this->producerUser)->postJson(
        "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
        ['entries' => [['entry_id' => $faces[0]['entry']->id, 'status' => 'present']]],
    );

    $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

    $this->assertSame(90000, $faces[0]['faceUser']->refresh()->balance);
    $this->assertSame(1, FinancialEvent::where('type', FinancialEventType::EscrowRelease)->count());
    Mail::assertQueued(MissionCompletedMail::class, 1);
    ```
- [x] 6.8 Lancer `cd backend && php -l tests/Feature/Mission/ProducerAttendanceEndpointsTest.php` → 0 erreur.
- [x] 6.9 Lancer la suite ciblée `cd backend && php artisan test --filter=ProducerAttendanceEndpointsTest --env=testing` → tous tests verts. **Compter les tests** : 8 (GET) + 4 (POST happy) + 3 (POST auth) + 6 (POST payload validation) + 1 (replay) = **22 nouveaux tests**.
- [x] 6.10 Vérifier non-régression : `cd backend && php artisan test --env=testing --testsuite=Feature` → suite complète exécutée. Résultat : 1950 passed, 1 failure transitoire non liée (`NotificationBroadcastTest > event is dispatched only after transaction commit`) ; le test échoué passe en rerun isolé. Baseline FIX-26.3 = 1951 tests → +22 nouveaux + 0 retiré → 1973 tests attendus.

### Task 7 — Validation finale et conformité projet

- [x] 7.1 Lancer `cd backend && ./vendor/bin/pint` (sans `--test`) sur les **6 fichiers du scope** :
  ```
  ./vendor/bin/pint app/Http/Controllers/Api/V1/Producer/MissionAttendanceController.php \
                    app/Http/Requests/Mission/ValidateMissionAttendanceRequest.php \
                    app/Http/Resources/MissionAttendanceEntryResource.php \
                    app/Services/MissionService.php \
                    routes/api/producer.php \
                    tests/Feature/Mission/ProducerAttendanceEndpointsTest.php
  ```
  → output `{"result":"pass"}` ou auto-fix sans diff résiduel.
- [x] 7.2 Lancer `cd backend && ./vendor/bin/phpstan analyse --level=max` sur le projet → 0 nouvelle erreur sur les 6 fichiers du scope (PHPStan ciblé `--debug` vert). Le PHPStan projet complet reporte 793 erreurs pré-existantes hors scope ; aucun finding sur les fichiers FIX-26.4.
- [x] 7.3 Lancer `cd backend && php artisan route:cache && php artisan route:list --path=api/v1/producer/missions` → vérifier que les 2 nouvelles routes apparaissent et que le cache se construit sans warning.
- [x] 7.4 Lancer `cd backend && php artisan event:list | grep -E "AttendanceMarked|AttendanceDisputed"` → **vide** (FIX-26.4 ne crée aucun event ; cohérent décision retenue § Architecture, scope strict zéro événement).

### Task 8 — Commit + sprint-status update

- [x] 8.1 Vérifier le scope du commit — `cd backend && git status` doit lister exactement **6 fichiers modifiés/ajoutés** (vs `main`) :
  - `backend/app/Http/Controllers/Api/V1/Producer/MissionAttendanceController.php` (NEW)
  - `backend/app/Http/Requests/Mission/ValidateMissionAttendanceRequest.php` (NEW)
  - `backend/app/Http/Resources/MissionAttendanceEntryResource.php` (NEW)
  - `backend/app/Services/MissionService.php` (MODIFIED — comment 1-line update)
  - `backend/routes/api/producer.php` (MODIFIED — +2 routes + 1 import)
  - `backend/tests/Feature/Mission/ProducerAttendanceEndpointsTest.php` (NEW)
  - **+ 2 fichiers workflow non-code** : `_bmad-output/implementation-artifacts/sprint-status.yaml` et `_bmad-output/implementation-artifacts/fix-26-4-producer-attendance-endpoints.md`.
  - **NE PAS stager** : `.codex/` (snapshot session pré-existant), `scripts/codex-notify.sh` (pré-existant — cf. status courant ligne `?? .codex` et `?? scripts/codex-notify.sh`).
- [x] 8.2 Commit avec message FR explicite (cohérent style FIX-26.3) :
  ```
  feat(missions): add Producer attendance HTTP endpoints (FIX-26.4)

  - GET /api/v1/producer/missions/{mission}/attendance-form returns
    selected Faces + montants (server-side, never from frontend).
  - POST /api/v1/producer/missions/{mission}/validate-attendance
    delegates to MissionAttendanceService::markAttendance.

  - new MissionAttendanceController, ValidateMissionAttendanceRequest,
    MissionAttendanceEntryResource.
  - 2 new routes (throttle:ui-read on GET, throttle:60,1 on POST).
  - 1-line bridge comment update in MissionService::completeMission
    repointing FIX-26.4 → FIX-26.10 for retirement.
  ```
- [x] 8.3 Mettre à jour `_bmad-output/implementation-artifacts/sprint-status.yaml` :
  - Changer `fix-26-4-producer-attendance-endpoints: backlog` (ligne 498) en `fix-26-4-producer-attendance-endpoints: ready-for-dev`.
  - Mettre à jour `last_updated:` à la date courante (`2026-04-28`).

### Review Findings

- [x] [Review][Patch] Reject duplicate attendance entry IDs before folding payload into a map [backend/app/Http/Requests/Mission/ValidateMissionAttendanceRequest.php:41]

## Dev Notes

### Architecture du flux HTTP attendance Producer

```
Producer client (Vue UI / curl / Postman)
        │ Bearer token
        ▼
    ┌─────────────────────────────────────────────────────────┐
    │ Route: GET /api/v1/producer/missions/{mission}/         │
    │       attendance-form  (throttle:ui-read)               │
    │       POST /api/v1/producer/missions/{mission}/         │
    │       validate-attendance  (throttle:60,1)              │
    └────────────────────┬────────────────────────────────────┘
                         │ Mission resolved by uuid (HasRouteUuid)
                         ▼
        ┌────────────────────────────────────────────┐
        │ MissionAttendanceController                │
        │   show($req, Mission)        : JsonResp   │
        │   validate(VMARequest, Miss) : JsonResp   │
        └─────┬──────────────────────────┬──────────┘
              │                          │
              ▼                          ▼
   inline guards               ValidateMissionAttendance
   (auth + status              Request:
   + payment)                    authorize() : Producer + owner
                                 rules() : payload shape
                                 withValidator() : status +
                                                  payment +
                                                  ownership entry_ids
                                          │
                                          ▼
                            controller transforms
                            [{entry_id, status}, ...]
                            → [int => 'present'|'absent', ...]
                                          │
                                          ▼
                          MissionAttendanceService::markAttendance
                          (livré FIX-26.3, intact)
                                          │
                                          ▼
                  releaseToFace / refundToProducer / Locked+absent
                  + tryCompleteIfReady
                  + Mail::queue + Notification + FinancialEvent
                                          │
                                          ▼
              controller re-charge entries with('face') + Resource serialize
                                          │
                                          ▼
                     { data: { mission, payment?, entries }, message? }
```

### Contrats HTTP — résumé

| Endpoint | HTTP | Auth | Body | Response 200 | Response erreurs |
|----------|------|------|------|--------------|------------------|
| `GET attendance-form` | GET | Producer + owner | none | `{ data: { mission, payment, entries }}` | 401 UNAUTHENTICATED, 403 FORBIDDEN, 404 NOT_FOUND, 422 VALIDATION_ERROR |
| `POST validate-attendance` | POST | Producer + owner | `{ entries: [{entry_id:int, status:'present'\|'absent'}] }` | `{ data: { mission, entries }, message }` | 401 UNAUTHENTICATED, 403 FORBIDDEN, 404 NOT_FOUND, 422 VALIDATION_ERROR, 500 INTERNAL_ERROR (théorique) |

### Pourquoi pas un seul endpoint mixte (GET-or-POST sur la même URL)

Cohérence REST : la lecture (state-querying, idempotente, cacheable) appartient à GET ; la mutation (state-changing, non-idempotente côté HTTP) appartient à POST. Couper en deux URLs (`attendance-form` vs `validate-attendance`) clarifie l'intention pour les caches HTTP, les middlewares de rate limiting, et les logs nginx (les `POST /validate-attendance` sont les actions Producer sensibles à monitorer). Pattern voisin : `GET .../payment-status` + `POST .../confirm-selection` dans le même controller `MissionPaymentController` (`backend/app/Http/Controllers/Api/V1/Producer/MissionPaymentController.php:32,94`).

### Pourquoi le snapshot complet en réponse POST plutôt que seulement les entries soumises

UX : le frontend FIX-26.7 affiche un tableau de **toutes** les Faces sélectionnées avec leur état. Après un POST partiel (ex : Producer marque seulement la Face 1, laisse Face 2 et Face 3 pour plus tard), le front a besoin de l'état frais des 3 entries — pas seulement de la Face 1. Renvoyer le snapshot complet économise un GET supplémentaire et garantit la cohérence (single round-trip). Coût : 1 SELECT supplémentaire avec `with('face')`, négligeable.

### Pourquoi pas de middleware `producer` sur les nouvelles routes

Le middleware `producer` (`EnsureUserIsProducer`, `backend/app/Http/Middleware/EnsureUserIsProducer.php:12-25`) abort 403 avec un message HTML-y — pas via l'enveloppe FIX-22.2 normalisée. Il est utilisé sur les routes `conversations/index` et `candidatures/{candidature}/rate` mais pas sur les actions Mission. Le pattern « auth Producer dans `FormRequest::authorize()` » est plus précis car il combine ownership ET role, retournant le 403 normalisé directement via le path AccessDeniedHttpException → handler global. Suivre la convention `complete` (pas de middleware `producer`).

### Comportement face à une mission `Completed` côté HTTP

Le service accepte `Completed` dans son whitelist pour la défense en profondeur sous lock concurrent (cf. AC #3 + test `test_mark_attendance_revalidates_mission_status_under_lock` FIX-26.3). Côté HTTP, la garde `ValidateMissionAttendanceRequest::withValidator` restreint à `{Closed, PendingAttendanceValidation}` — un Producer qui POSTerait sur une mission déjà `Completed` reçoit un 422 propre plutôt qu'un 200 mutation no-op. La défense service-level reste atteignable uniquement par les autres callers internes (cron FIX-26.6, futures stories), pas par HTTP. Cohérent avec l'invariant 11 epic « Garde-fou autorization à enforcer dans le service ET dans la request validation » — les deux niveaux peuvent diverger sur les détails, c'est voulu.

### Tests — ce qui est nouveau vs augmenté

**Nouveaux** : `ProducerAttendanceEndpointsTest` 22 tests dédiés (8 GET + 4 POST happy + 3 POST auth + 6 POST payload validation + 1 replay-on-completed).

**Augmentés** : aucun fichier de test existant n'est modifié. Aucune assertion existante n'est ajoutée à `CompleteMissionTest`, `MissionAttendanceServiceTest`, `MissionAttendanceReleaseFundsTest`, `AutoReleaseMissionFundsCommandTest`, `MissionPaymentCandidatureSchemaTest`, `MissionSchemaTest`. Tous restent verts par construction.

**Pourquoi pas de test feature `test_complete_mission_legacy_path_remains_unchanged` augmenté** : ce test existe déjà dans `MissionAttendanceServiceTest` (FIX-26.3 ligne 629) et verrouille le comportement bridge ; aucune raison de l'augmenter. La non-régression du bridge est prouvée par le fait qu'on ne touche pas à `MissionService::completeMission` autre que le commentaire 1-ligne.

### Project Structure Notes

- **Alignement structure unifiée** :
  - Controllers Producer sous `App\Http\Controllers\Api\V1\Producer\` (✓ convention).
  - FormRequests sous `App\Http\Requests\Mission\` (✓ convention pour actions Mission — cohérent avec `CompleteMissionRequest`, `CloseMissionRequest`, etc.).
  - Resources sous `App\Http\Resources\` (✓ convention plate sans sous-namespaces).
  - Tests sous `Tests\Feature\Mission\` (✓ convention domain-namespace).
- **Variances** : aucune.
- **Naming** :
  - `MissionAttendanceController` — nouveau pattern « domain action controller » (vs `MissionController` qui est CRUD-style). Justifié par le fait que ce controller n'est pas du CRUD sur Mission — il expose un sous-domaine (attendance) avec son propre cycle. Pattern voisin : `MissionPaymentController` (Mission's payment domain, séparé de `MissionController`).
  - `validate()` méthode du controller — Laravel autorise les noms de méthodes qui matchent un mot-clé interne (ex : `validate` appelle Laravel's built-in `Validator` si le controller hérite des trait `ValidatesRequests`, ce qui est le cas via `Controller`). Mais ici on ne `use ValidatesRequests` pas explicitement et on n'appelle pas la méthode parente — Laravel injecte le FormRequest directement. **Sécurité** : vérifier qu'aucune méthode `validate(Request $request)` héritée n'est masquée. Audit `Illuminate\Foundation\Bus\DispatchesJobs` + `Illuminate\Foundation\Validation\ValidatesRequests` montre que `ValidatesRequests::validate` existe — notre signature `validate(ValidateMissionAttendanceRequest $request, Mission $mission)` est différente (extra param Mission). PHP autorise l'override en élargissant les params. **Décision validée** par lecture de `Illuminate\Routing\Controller::__call` qui dispatche la méthode publique du controller avec les params binding. Aucun conflit.
- **Si le naming `validate` pose un risque (Pint warning, IDE warning, dev anxiety)** : alternative `submit()` ou `markAttendance()` sont valides. Préférence retenue : `validate()` car il match exactement le verbe URL (`validate-attendance`) et le wording Producer-facing (« valider les présences »). Si l'implémentation expose un conflit, basculer en `submit` est trivial (rename method + update route).

### References

- **Epic** : `_bmad-output/planning-artifacts/epics-postlaunch-fixes-10.md`#FIX-26.4 (lignes 208-222), invariants epic lignes 71-77 (10/11/12), décisions produit lignes 38-48.
- **Service backend déjà livré** : `backend/app/Services/MissionAttendanceService.php` (FIX-26.3, statut `done`, 26 tests verts).
- **Pattern controller voisin** : `backend/app/Http/Controllers/Api/V1/Producer/MissionController.php:174-182` (action `complete`), `backend/app/Http/Controllers/Api/V1/Producer/MissionPaymentController.php:94-153` (action `paymentStatus` avec inline guards GET-only).
- **Pattern FormRequest voisin** : `backend/app/Http/Requests/Mission/CompleteMissionRequest.php:1-133` (auth + status guards via `withValidator`).
- **Pattern Resource voisin** : `backend/app/Http/Resources/MissionResource.php:1-48`, `backend/app/Http/Resources/CandidatureResource.php:1-35`.
- **Pattern test voisin** : `backend/tests/Feature/Mission/MissionAttendanceServiceTest.php:1-642` (helper `createPaidMissionWithFaces`), `backend/tests/Feature/Mission/CompleteMissionTest.php:1-455` (e2e POST happy paths + auth/auth boundary).
- **Handler global error envelope** : `backend/bootstrap/app.php:69-228` (FIX-22.2).
- **Stories précédentes** : `_bmad-output/implementation-artifacts/fix-26-1-attendance-status-schema.md`, `_bmad-output/implementation-artifacts/fix-26-2-release-funds-routing-by-attendance.md`, `_bmad-output/implementation-artifacts/fix-26-3-mission-attendance-service.md`.

## Dev Agent Record

### Agent Model Used

claude-opus-4-7[1m] (BMM dev-story workflow)

### Debug Log References

- 2026-04-28: Started dev-story workflow; loaded config, sprint status, project context, and complete story file.
- 2026-04-28: `mgrep` unavailable due monthly quota exceeded; used local file reads/searches for implementation context.
- 2026-04-28: Added 22-test `ProducerAttendanceEndpointsTest`; initial sandboxed run was blocked before assertions by MySQL connection failure to `127.0.0.1:3306` / `weact_test`, then reran outside sandbox.
- 2026-04-28: Syntax checks passed for all six scoped backend files.
- 2026-04-28: Pint passed for all six scoped backend files.
- 2026-04-28: Targeted PHPStan on the six scoped files passed with `--debug`; non-debug PHPStan hit existing local worker socket issue `Failed to listen on tcp://127.0.0.1:0`.
- 2026-04-28: Full project PHPStan reports pre-existing project-wide findings outside this story scope (793 errors); scoped files are clean.
- 2026-04-28: `php artisan route:cache` passed; route list shows both attendance endpoints.
- 2026-04-28: Targeted endpoint suite passed: `ProducerAttendanceEndpointsTest` 22 tests / 105 assertions.
- 2026-04-28: Code review patch applied: duplicate `entry_id` values are rejected via `distinct`; targeted endpoint suite now passes with 23 tests / 111 assertions; Pint and scoped PHPStan clean for patched files.
- 2026-04-28: Targeted regressions passed sequentially: `CompleteMissionTest` 11/58, `MissionAttendanceServiceTest` 26/102, `MissionAttendanceReleaseFundsTest` 7/55, `AutoReleaseMissionFundsCommandTest` 1/7.
- 2026-04-28: Full Feature suite executed after `php artisan migrate:fresh --env=testing`: 1950 passed, 1 unrelated transient failure, 8895 assertions. Failed test: `NotificationBroadcastTest > event is dispatched only after transaction commit`; isolated rerun passed (1 test / 2 assertions).

### Completion Notes List

- Implemented `MissionAttendanceEntryResource` with server-side amount exposure, face snapshot, attendance/escrow labels, and escrow timestamps.
- Implemented `ValidateMissionAttendanceRequest` with Producer ownership authorization, payload validation, status/payment guards, and entry ownership validation.
- Implemented `MissionAttendanceController` with `GET attendance-form` and `POST validate-attendance`, delegating mutation to `MissionAttendanceService::markAttendance`.
- Registered the two Producer routes with `ui-read` and `60,1` throttles.
- Updated the legacy `/complete` bridge comment to defer bridge retirement to FIX-26.10 while leaving behavior unchanged.
- Added 23 endpoint tests covering GET/POST success, auth boundaries, validation errors, duplicate entry guard, foreign entry guard, and replay-on-completed behavior.
- Story completed after code review patch. DB-backed targeted endpoint and regression suites passed. Full Feature suite had one unrelated transient notification broadcast failure that passed on isolated rerun.

### File List

**À créer** (4 fichiers) :
- `backend/app/Http/Controllers/Api/V1/Producer/MissionAttendanceController.php`
- `backend/app/Http/Requests/Mission/ValidateMissionAttendanceRequest.php`
- `backend/app/Http/Resources/MissionAttendanceEntryResource.php`
- `backend/tests/Feature/Mission/ProducerAttendanceEndpointsTest.php`

**À modifier** (2 fichiers) :
- `backend/routes/api/producer.php` (ajout 1 import + 2 routes)
- `backend/app/Services/MissionService.php` (mise à jour 1 commentaire bridge)

**Workflow metadata** (2 fichiers, hors scope code) :
- `_bmad-output/implementation-artifacts/sprint-status.yaml` (status flip)
- `_bmad-output/implementation-artifacts/fix-26-4-producer-attendance-endpoints.md` (ce fichier)
