# Story FIX-26.3: Service `MissionAttendanceService` + transitions Mission

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As a **développeur backend qui exposera ensuite les endpoints Producer (FIX-26.4), la notification + endpoint Face (FIX-26.5), les crons (FIX-26.6) et la résolution admin (FIX-26.8)**,
I want **un service `MissionAttendanceService` qui orchestre les transitions de présence** : `markAttendance` (batch Producer, crédit immédiat présents, absents restent en attente), `disputeAttendance` (Face conteste son absence) et `resolveDispute` (admin tranche), avec gestion des transitions Mission `Closed → PendingAttendanceValidation → Completed`,
so that **les couches HTTP/cron/admin futures appellent une API métier unique et idempotente, et la mission ne passe en `Completed` que lorsque toutes les entries sont financièrement tranchées (Released, Refunded ou Locked+disputed)**.

## Acceptance Criteria

**Contexte produit** : FIX-26.3 est la story qui matérialise l'orchestrateur métier prévu par l'epic. Elle dépend de FIX-26.1 (schéma `attendance_status` + `MissionStatus::PendingAttendanceValidation`) et FIX-26.2 (helpers `releaseToFace` / `refundToProducer` + bridge backward-compat). Toutes les stories applicatives postérieures (FIX-26.4 endpoints Producer, FIX-26.5 notification + endpoint Face, FIX-26.6 crons, FIX-26.8 admin) consomment ce service. Source : `_bmad-output/planning-artifacts/epics-postlaunch-fixes-10.md` § FIX-26.3 (lignes 184-205) + § « Nouveaux invariants financiers » (lignes 54-77) + § « Décisions produit » (lignes 38-48).

**Pattern Prove It non applicable** : ce n'est pas un bug-fix, c'est une feature add (nouveau service métier). Pattern test-first quand même : le `MissionAttendanceServiceTest` est écrit avant chaque méthode, échoue (méthode inexistante), puis le service est implémenté, puis les tests passent.

**Architecture retenue — service dédié, settlement async pour absents** :

- ✅ **Nouveau service `App\Services\MissionAttendanceService`** dans `backend/app/Services/`. Pas d'extension de `MissionService` ou `MissionPaymentService` — séparation claire des responsabilités (`MissionService` = lifecycle mission, `MissionPaymentService` = primitives financières par-entry, `MissionAttendanceService` = orchestration présence).
- ✅ **Settlement asynchrone des absents** (interprétation 2 de l'epic, retenue) : `markAttendance` crédite IMMÉDIATEMENT le wallet Face pour chaque entry `present`, mais NE TOUCHE PAS au wallet Producer pour les `absent` — l'entry reste `Locked + absent` jusqu'à expiration de la fenêtre de contestation 72h (réglée par FIX-26.6 cron `settle-disputed-attendance`) ou résolution admin (FIX-26.8 via `resolveDispute`). Cela respecte l'invariant 2 de l'epic « `absent` et **hors fenêtre de contestation** peut passer à `Refunded` » (lignes 60-61 de l'epic).
- ✅ **Pas d'appel à `MissionPaymentService::releaseFunds`** depuis `markAttendance` — `releaseFunds` traite `present + absent` inline (cf. FIX-26.2 `MissionPaymentService.php:710-713`), ce qui violerait la fenêtre de contestation 72h. À la place, `markAttendance` itère sur les entries `present` uniquement et appelle `MissionPaymentService::releaseToFace($entry, $mission)` — méthode FIX-26.2 actuellement `private`, **rendue `public` par cette story** (cf. Task 1) sans modifier le comportement.
- ✅ **`MissionPaymentService::releaseToFace` et `refundToProducer` deviennent `public`** avec un paramètre optionnel `string $reason` (default `'attendance_present'` pour `releaseToFace`, `'attendance_absent'` pour `refundToProducer`). Le default préserve à 100 % le comportement FIX-26.2 (rétro-compat). MissionAttendanceService les appelle avec des reasons spécifiques (`'disputed_resolved_face'` / `'disputed_resolved_producer'`) pour les cas `resolveDispute`.
- ✅ **`MissionService::notifyProducerOnCompletion` devient `public`** (1-ligne de visibilité, aucune modification de comportement). MissionAttendanceService injecte MissionService et appelle cette méthode quand la mission transitionne en `Completed`.
- ✅ **Nouveau enum `App\Enums\DisputeResolutionOutcome`** (style minimaliste miroir `EscrowStatus`/`AttendanceStatus`) avec 2 cases : `FavorFace = 'favor_face'`, `FavorProducer = 'favor_producer'`. Utilisé par `resolveDispute(entry, DisputeResolutionOutcome $outcome, User $admin)` pour la lisibilité et le type-safety.
- ✅ **Bridge backward-compat dans `MissionService::completeMission` (FIX-26.2) RESTE EN PLACE** dans cette story. Sa retrait planifiée FIX-26.4 (quand le nouveau endpoint Producer prendra le relais via `markAttendance`) — confirmé par le commentaire actuel `MissionService.php:246-250` (« This bridge will be REMOVED in FIX-26.3 once MissionAttendanceService takes over via the new endpoints »). **Cet objectif est repoussé à FIX-26.4** car FIX-26.3 est service-only (aucun endpoint, aucun changement de routing HTTP), donc le legacy `/api/v1/producer/missions/{uuid}/complete` (qui passe par `completeMission`) doit continuer à fonctionner sans le nouveau service. Mise à jour du commentaire de bridge pour pointer FIX-26.4.
- ✅ **Nouvelle colonne `notified_at` (timestamp nullable) sur `mission_payment_candidatures`** ajoutée par migration dans cette story. Set par `markAttendance` au moment où l'entry transitionne `pending → absent` (1ère fois seulement — jamais écrasé par une re-call idempotente). Sert de point de départ stable et immutable pour la fenêtre de contestation Face 72h (consommée par FIX-26.5 endpoint dispute) et pour la finalisation du refund (consommée par FIX-26.6 cron `settle-disputed-attendance`). **Décision déplacée de FIX-26.5 vers FIX-26.3** suite review : FIX-26.3 est l'écrivain de la transition `→ absent`, donc la colonne doit exister au moment où la transition est faite. Sinon les absences créées entre FIX-26.3 et FIX-26.5 deploy auraient un timestamp instable (Eloquent `updated_at` peut être ré-écrit par `disputeAttendance` ou `resolveDispute`).

**Décisions retenues contre alternatives** :

- ❌ **`markAttendance` appelle `MissionPaymentService::releaseFunds(mission)`** (interprétation 1 / Design C synchronous full-routing) : rejetée. `releaseFunds` actuel route `absent → refundToProducer` inline (cf. `MissionPaymentService.php:712`), ce qui débite immédiatement le solde escrow vers le Producer SANS attendre la fenêtre 72h Face. Or l'invariant 2 de l'epic exige `absent + hors fenêtre → Refunded`. Si la Face conteste APRÈS coup, `resolveDispute` devrait reverser le refund (débit Producer + crédit Face), mécanique non spécifiée par l'epic et plus complexe que le settlement async. Choix produit acté en faveur de l'asynchrone.
- ❌ **Dupliquer la logique `releaseToFace` (creditDirect + entry.update + recordFinancialEvent + notif + mail) dans `MissionAttendanceService`** : rejeté. ~30 lignes de duplication critique (financière) avec deux sources de vérité. Promotion de visibilité `private → public` est moins coûteuse et conserve une source unique pour la mécanique « release une entry vers la Face ».
- ❌ **Ajouter une nouvelle méthode publique `MissionPaymentService::releasePresentEntries(Mission)` (filtre par attendance_status=present)** : rejeté. Forke l'API (deux entrypoints concurrents `releaseFunds` et `releasePresentEntries`), risque de drift dans le futur. La promotion à public des helpers existants `releaseToFace`/`refundToProducer` est plus économe.
- ❌ **Étendre `MissionService::completeMission` avec un mode « partial-attendance »** : rejeté. `completeMission` est l'API legacy (avec bridge backward-compat) — la mélanger avec le nouveau flow ferait diverger les deux chemins. Service dédié est plus clair.
- ❌ **`disputeAttendance` crée un `FinancialEvent` (audit)** : rejeté. Le dispute est un changement d'état métier (`attendance_status: absent → disputed`) sans mouvement financier (l'entry reste `Locked`, escrow_status inchangé). `FinancialEvent` est explicitement réservé aux mouvements financiers (cf. table définition `2026_02_28_100000_create_financial_events_table.php`). Audit du dispute via `Log::info` + Eloquent `updated_at` sur l'entry (built-in timestamp).
- ❌ **`disputeAttendance` notifie l'admin via `Notification::create`** : rejeté pour cette story. La notification admin (« nouvelle entry en litige à résoudre ») est plus naturellement co-localisée avec le back-office admin (FIX-26.8) qui définira le mécanisme de notification admin (broadcast à tous les Users `userable_type=Admin` ? broadcast à un canal Slack ? simple log ?). FIX-26.3 logge via `Log::info` avec un tag clair pour traçabilité. **Repoussé à FIX-26.8**.
- ❌ **Reposer sur `updated_at` Eloquent comme point de départ des 72h fenêtre de contestation** : rejeté suite review. `updated_at` est ré-écrit à chaque `update()` (par `disputeAttendance` qui flip `absent → disputed`, par `resolveDispute` qui flip `escrow_status`) et ne représente donc pas spécifiquement « moment de notification d'absence ». La colonne dédiée `notified_at` (ajoutée par cette story, cf. § Architecture retenue) est immutable une fois set — point de départ stable pour FIX-26.5 (window 72h) et FIX-26.6 (cron). Ce choix supersede la décision originale FIX-26.5 — la colonne migre vers FIX-26.3 dès la story de service.
- ❌ **Auto-bascule `Closed → PendingAttendanceValidation` via cron au passage de `date_tournage`** : rejeté pour cette story. Aucun flow utilisateur n'observe l'état intermédiaire `PendingAttendanceValidation` avant le premier appel `markAttendance` (les pages Producer/Face listent les missions par `mission_id` avec leurs candidatures, sans filtrer sur ce status). La transition `Closed → PendingAttendanceValidation` est faite en début de `markAttendance` (juste avant les updates d'entries) pour matérialiser l'intention « le Producer a commencé à valider les présences ». Si le Producer ne fait jamais le markAttendance, FIX-26.6 cron le fera à sa place après 72h. Cohérent avec les décisions produit § 4 (« Statut nouveau sur Mission : `PendingAttendanceValidation` entre `Closed` et `Completed` »).
- ❌ **Restreindre `markAttendance` à un input couvrant TOUTES les entries `pending`** : rejeté. Pour la robustesse opérationnelle (Producer pourrait soumettre par lots si l'UI le permet plus tard), `markAttendance` accepte un sous-ensemble. Si après l'application il reste des entries en `pending`, la mission stagne en `PendingAttendanceValidation` (ne transitionne pas en `Completed`). FIX-26.6 cron `auto-validate-attendance` finalisera après 72h. Implémenté via le helper `tryCompleteIfReady` qui ne complète la mission QUE si plus aucune entry `Locked + (pending|absent)`.

**Découvertes audit codebase à respecter** :

> 1. **`MissionAttendanceService` n'existe pas** — seul `MissionService.php:249` mentionne ce nom dans un commentaire `// This bridge will be REMOVED in FIX-26.3 once MissionAttendanceService takes over` (vérifié via `grep -rn "MissionAttendanceService\|markAttendance\|disputeAttendance\|resolveDispute" backend`). Terrain vierge — FIX-26.3 est le PREMIER consommateur.
> 2. **`MissionPaymentService::releaseToFace` actuelle est `private`** (`MissionPaymentService.php:723-802`). Sa promotion à `public` n'a pas d'impact sur les appelants existants (1 seul appelant interne : `releaseFunds` ligne 711). L'ajout du paramètre optionnel `string $reason = 'attendance_present'` n'est pas breaking (default préserve la métadonnée actuelle).
> 3. **`MissionPaymentService::refundToProducer` actuelle est `private`** (`MissionPaymentService.php:812-848`). Idem — 1 seul appelant interne (`releaseFunds` ligne 712). Promotion à `public` + paramètre optionnel `string $reason = 'attendance_absent'` non breaking.
> 4. **`MissionService::notifyProducerOnCompletion` actuelle est `private`** (`MissionService.php:271-299`). Promotion à `public` non breaking — 1 seul appelant interne (`completeMission` ligne 265).
> 5. **`MissionService::completeMission` reste inchangée dans cette story** (sauf mise à jour du commentaire de bridge `MissionService.php:246-250` qui pointait FIX-26.3 → repointé FIX-26.4). Le bridge backward-compat reste en place car le legacy `/api/v1/producer/missions/{uuid}/complete` continue d'y passer (les nouveaux endpoints arrivent en FIX-26.4).
> 6. **Le `match` de `MissionStatus::label()` (`MissionStatus.php:21-28`) couvre déjà `PendingAttendanceValidation`** depuis FIX-26.1 — aucune patch enum requis dans cette story. La transition Mission `Closed → PendingAttendanceValidation → Completed` est un simple `$mission->update(['status' => MissionStatus::PendingAttendanceValidation])` puis `=> MissionStatus::Completed`.
> 7. **Aucun `match` exhaustif sur `AttendanceStatus` n'existe hors `MissionPaymentService::releaseFunds` ligne 710** (vérifié via `grep -rn "match.*AttendanceStatus" backend`). Les nouveaux helpers de `MissionAttendanceService` peuvent comparer par `===` sans risque de UnhandledMatchError.
> 8. **`MissionPaymentService` est injecté via constructor sur `MissionService`** (`MissionService.php:21-23`). Le pattern d'injection est `private readonly` — `MissionAttendanceService` suit le même pattern.
> 9. **`WalletService::creditDirect(int $userId, int $amount, string $description)`** (`WalletService.php:37-49`) est sémantiquement neutre vis-à-vis du userable type (Face/Producer) — réutilisable directement par `resolveDispute` pour les crédits Face/Producer. Pas besoin d'ajouter `creditFace` ni `creditProducer`.
> 10. **`User::where('userable_type', Face::class)->where('userable_id', $faceId)->first()` et `User::where('userable_type', Producer::class)->where('userable_id', $producerId)->first()` sont les patterns de résolution déjà utilisés** par `MissionPaymentService::getUserIdForFace:883-891` et `getUserIdForProducer:896-904`. `MissionAttendanceService` REPRODUIT ces patterns en helpers privés (sans factoriser dans une classe utilitaire — duplication mineure acceptée pour préserver l'isolation du service).
> 11. **Pattern d'authorization Service-level via comparaison directe `User->id` ou `User->userable_id`** : utilisé par `BookingService::reportNoShow:305` (`if ($reporter->id !== $lockedBooking->producer_id) { throw ValidationException::withMessages(['reporter' => ['Seul le producteur peut signaler une absence.']]); }`). FIX-26.3 reproduit ce pattern (Producer = mission.producer_id, Face = entry.face_id, Admin = user->userable_type === Admin::class).
> 12. **Pattern transaction-wrap dans Service** : `BookingService::reportNoShow:287-333` enveloppe tout dans `DB::transaction(function () use (...): Booking { ... })` retournant le booking frais. Les 3 méthodes publiques de `MissionAttendanceService` suivront ce pattern (`DB::transaction` + `lockForUpdate` sur Mission/entry).
> 13. **La table `mission_payment_candidatures` n'a PAS de colonne `mission_id` directe** (cf. `2026_03_20_100002_create_mission_payment_candidatures_table.php`). Pour vérifier qu'une entry appartient à une mission donnée, il faut passer par `entry.mission_payment.mission_id`. Pattern : `$entry->loadMissing('missionPayment')` puis `$entry->missionPayment->mission_id !== $mission->id`. Alternative : `MissionPaymentCandidature::whereHas('missionPayment', fn ($q) => $q->where('mission_id', $missionId))`.
> 14. **`MissionPaymentCandidature` (`backend/app/Models/MissionPaymentCandidature.php:50-53`) a une relation `missionPayment(): BelongsTo`** mais PAS de relation directe `mission()`. Il faut chaîner : `$entry->missionPayment->mission` ou faire `$entry->missionPayment()->with('mission')->first()`. Pour `disputeAttendance` qui reçoit l'entry brute, on charge la mission via cette chaîne.
> 15. **Aucune factory `MissionPaymentCandidature::factory()`** (vérifié dans `database/factories/`). Les tests existants instancient via `MissionPaymentCandidature::create([...])` — pattern utilisé aussi dans `MissionAttendanceReleaseFundsTest` (FIX-26.2). FIX-26.3 suit le même pattern dans son test (helper local `createPaidMissionWithFaces`).
> 16. **Le test `MissionAttendanceReleaseFundsTest` (FIX-26.2) a déjà un helper `createPaidMissionWithFaces(int $faceCount): array{Mission, list<array{candidature, entry, faceUser, face}>}`** dans `backend/tests/Feature/Mission/MissionAttendanceReleaseFundsTest.php:setUp+helper`. **Ne PAS le factoriser dans une classe TestCase parente dans cette story** (hors scope) — `MissionAttendanceServiceTest` duplique le pattern localement. Si la duplication s'avère pénalisante, story de refactoring dédiée plus tard.
> 17. **Le bridge backward-compat dans `MissionService::completeMission` doit rester intact** (cf. décision retenue). Modifier le commentaire de TEMPORARY/REMOVED pour pointer FIX-26.4 au lieu de FIX-26.3 — c'est la seule modification non-trivial du fichier. Aucun changement de logique.
> 18. **Le test `CompleteMissionTest` (`backend/tests/Feature/Mission/CompleteMissionTest.php:1-300+`) doit RESTER vert sans modification de fixture**. Le bridge legacy (qui auto-marque pending → present) couvre tous les tests existants. Aucune modification du test dans cette story.
> 19. **Les tests `MissionAttendanceReleaseFundsTest` (FIX-26.2, 7 tests, 55 assertions) doivent RESTER verts sans modification**. Ils testent `releaseFunds` directement (pas le nouveau service). Aucune modification dans cette story.
> 20. **`Admin` model existe** (`backend/app/Models/Admin.php`) et est référencé via `userable_type` dans `users` (cf. migration `2026_02_10_193318_add_role_to_admins_table.php`). Pattern de check admin dans `resolveDispute` : `if ($admin->userable_type !== Admin::class) { throw ValidationException::withMessages(['admin' => ['Seul un administrateur peut résoudre un litige.']]); }`.

**ACs en BDD format Given/When/Then** :

### Bloc A — `markAttendance(mission, entryUuidToStatus, actor)` — orchestrateur Producer

1. **Given** un Producer authentifié = propriétaire de la mission (`actor.userable_type === Producer::class && actor.userable_id === mission.producer_id`), une mission `Closed` avec payment `Paid` et N entries `Locked + attendance_status = pending`, et un input `[entry1.id => 'present', entry2.id => 'absent', ..., entryN.id => 'present']`, **When** `MissionAttendanceService::markAttendance(mission, $input, $actor)` est appelée **Then** :
   - Inside `DB::transaction`, mission est lockForUpdate.
   - Pour chaque pair `($entryId, $status)` du input :
     - L'entry est lockForUpdate, vérifiée appartenir à la mission via `entry.missionPayment.mission_id === mission.id` (sinon RuntimeException « entry does not belong to mission »).
     - L'entry actuelle est `Locked + attendance_status = pending` (sinon update is no-op silently — idempotence).
     - L'entry est mise à jour : `attendance_status = $status` (où `$status` ∈ `{present, absent}`).
   - La mission est transitionnée `Closed → PendingAttendanceValidation` (UPDATE inconditionnel si status courant = Closed ; no-op si déjà `PendingAttendanceValidation`).
   - Pour chaque entry maintenant en `Locked + present`, le service appelle `MissionPaymentService::releaseToFace($entry, $mission, 'attendance_present')` (méthode rendue publique par cette story) → l'entry passe `Released`, le wallet Face est crédité, un `FinancialEvent` `EscrowRelease` est créé, la candidature passe `Completed`, notif `mission_completed` envoyée, mail `MissionCompletedMail` queue.
   - Pour chaque entry en `Locked + absent`, **AUCUN** mouvement financier n'est appliqué (l'entry reste `Locked + absent`, attendant FIX-26.5 endpoint dispute ou FIX-26.6 cron settle-disputed-attendance).
   - Pour chaque entry déjà en `Locked + disputed` (préexistante avant le call), elle est **non touchée** (no-op).
   - Le helper `tryCompleteIfReady($mission)` est appelé : si la mission n'a plus AUCUNE entry `Locked + (pending|absent)` (i.e., plus que des entries Released, Refunded, ou Locked+disputed), la mission est transitionnée `PendingAttendanceValidation → Completed` ET `MissionService::notifyProducerOnCompletion($mission)` est appelée (méthode rendue publique). Sinon la mission stagne en `PendingAttendanceValidation`.
   - La méthode retourne le `Mission $freshMission` (`$mission->fresh()`).

2. **Given** un actor qui n'est PAS le Producer propriétaire de la mission (par ex. `actor.userable_type === Face::class`, ou `actor.userable_type === Producer::class` mais `actor.userable_id !== mission.producer_id`), **When** `markAttendance` est appelée **Then** :
   - Une `ValidationException::withMessages(['actor' => ['Seul le producteur de la mission peut valider les présences.']])` est levée AVANT toute écriture DB.
   - Aucune mission ni entry n'est modifiée (transaction non démarrée ou rollback).

3. **Given** une mission dans un statut `≠ {Closed, PendingAttendanceValidation, Completed}` (par exemple `Published`, `PendingPayment`, `Draft`), **When** `markAttendance` est appelée **Then** :
   - Une `ValidationException::withMessages(['mission' => ['La validation des présences n'est possible que sur une mission clôturée ou en attente de validation.']])` est levée.
   - Aucune entry n'est modifiée.
   - Note d'arbitrage review D1=A : `Completed` est autorisé uniquement pour permettre le replay idempotent d'un batch déjà tranché (AC #11). Les entries déjà `Released`/`Refunded`/non-`Pending` restent no-op ; aucun nouveau mouvement financier n'est déclenché.

4. **Given** un input contenant un `entry_id` qui n'appartient PAS à la mission (entry valide en DB mais sa `missionPayment.mission_id` est différent de `$mission->id`), **When** `markAttendance` est appelée **Then** :
   - Une `RuntimeException` est levée avec message explicite (« Entry {$entry->id} does not belong to mission {$mission->id}. »).
   - La transaction rollback : aucune autre entry du batch n'est modifiée non plus (atomicité).

5. **Given** un input contenant un `status` invalide (différent de `'present'` ou `'absent'`, par exemple `'pending'` ou `'disputed'` ou `'invalid'`), **When** `markAttendance` est appelée **Then** :
   - Une `ValidationException::withMessages(['entries' => ['Les statuts acceptés sont : present, absent.']])` est levée AVANT toute écriture DB.
   - L'idée : `disputed` n'est PAS settable par le Producer (c'est la prérogative de la Face via `disputeAttendance`). `pending` est l'état initial, jamais re-settable.

6. **Given** une mission `Closed` SANS `MissionPayment` ou avec `MissionPayment.status !== Paid`, **When** `markAttendance` est appelée **Then** :
   - Une `ValidationException::withMessages(['mission' => ['La validation des présences requiert un paiement confirmé sur la mission.']])` est levée AVANT toute écriture DB.
   - Le guard est appliqué DEUX fois : (a) avant l'ouverture de la transaction (fail-fast sur l'objet `$mission` passé en paramètre), (b) re-vérifié sous `lockForUpdate` à l'intérieur de la transaction (défense contre une condition de course où `MissionPayment.status` change entre les deux gates).
   - Aucune entry n'est modifiée. Le helper `tryCompleteIfReady` n'est PAS appelé. Mission status inchangé.

7. **Given** une mission valide (Closed/Paid) mais un input `$entryIdToStatus = []` (vide), **When** `markAttendance` est appelée **Then** :
   - Une `ValidationException::withMessages(['entries' => ['Au moins une entry doit être fournie.']])` est levée AVANT toute écriture DB.
   - Aucune entry n'est modifiée. Mission status inchangé.

8. **Given** une mission valide à T0 (status `Closed`, payment `Paid`), mais entre l'ouverture de la transaction et le `lockForUpdate` une requête concurrente (cron, autre Producer, admin) fait passer `mission.status` à un statut invalide (`Published`, `Draft`, `PendingPayment`) ou `mission.payment.status` à autre chose que `Paid`, **When** la re-validation sous lock s'exécute **Then** :
   - Une `ValidationException` est levée (message identique au guard pre-transaction).
   - La transaction rollback intégralement (atomicité préservée — aucune entry partiellement modifiée).
   - **Justification** : ce test démontre que le guard sous lock est NÉCESSAIRE et FONCTIONNEL. Sans la re-validation, une race pourrait corrompre une mission terminée (ou financièrement non-confirmée).

9. **Given** une mission avec 3 entries (1 marquée `present`, 1 marquée `absent`, 1 marquée `disputed` AVANT le call) et un input `[entry_present.id => 'present', entry_absent.id => 'absent']` (note : entry_disputed N'EST PAS dans l'input), **When** `markAttendance` est appelée **Then** :
   - Le batch traite uniquement les 2 entries présentes dans l'input.
   - L'entry `disputed` est **non touchée** (no-op silently — pas d'erreur).
   - Les 2 entries du batch étaient DÉJÀ tranchées avant le call (statut `present`/`absent`, pas `pending`) → la guard d'idempotence `if ($entry->attendance_status !== Pending) continue;` les ignore. Aucune `releaseToFace` n'est déclenchée, aucun mouvement financier n'a lieu.
   - Mission reste en `PendingAttendanceValidation` ou `Closed` selon son état d'entrée (non transitionnée vers Completed car l'entry `Locked + absent` n'est PAS finalisée).
   - **Note de scénario** : ce cas teste la robustesse du `continue` d'idempotence quand le batch arrive sur des entries déjà traitées par un appel précédent. Il ne re-déclenche pas de release.

10. **Given** une mission `Closed` avec 2 entries TOUTES `present` après markAttendance, **When** `markAttendance` est appelée **Then** :
    - Les 2 entries deviennent `Released` via `releaseToFace`.
    - Mission transitionne `Closed → PendingAttendanceValidation → Completed` (les 2 transitions UPDATE successives au sein de la même `DB::transaction`).
    - `MissionService::notifyProducerOnCompletion($mission)` est appelée.
    - Le test asserte `$mission->fresh()->status === MissionStatus::Completed`.

11. **Given** `markAttendance` est appelée DEUX FOIS de suite avec le même input (1 entry → `'present'`), **When** la deuxième invocation s'exécute **Then** :
    - Au 2ème call, l'entry est déjà `Released + present` (post-1er call). La guard `if ($entry->attendance_status !== Pending) continue;` filtre l'entry → pas de re-release, pas de double crédit, pas de double `FinancialEvent`.
    - Balance Face inchangée (90000, pas 180000), `FinancialEvent::count() === 1`, mission status inchangé (Completed).

12. **Given** une mission valide avec une entry `Locked + pending`, **When** `markAttendance($mission, [$entry->id => 'absent'], $producerUser)` est appelée **Then** :
    - L'entry est mise à jour : `attendance_status = absent`, `notified_at = now()` (set 1ère fois).
    - L'entry reste `escrow_status = Locked`, pas de `released_at`/`refunded_at`.
    - Aucune `releaseToFace` ni `refundToProducer` (absents non finalisés financièrement dans cette story).
    - Mission transitionne `Closed → PendingAttendanceValidation` mais PAS Completed (entry `Locked + absent` reste à régler par FIX-26.6 cron ou via dispute).
    - **Idempotence du `notified_at`** : un 2ème call `markAttendance($mission, [$entry->id => 'absent'], $producerUser)` ne ré-écrit PAS `notified_at` (la guard `if (... attendance_status !== Pending) continue;` filtre l'entry → l'UPDATE n'est jamais émis). Le timestamp original reste stable.

### Bloc B — `disputeAttendance(entry, actor)` — Face conteste son absence

13. **Given** une entry `Locked + attendance_status = absent` et un `actor` Face propriétaire (`actor.userable_type === Face::class && actor.userable_id === entry.face_id`), **When** `MissionAttendanceService::disputeAttendance($entry, $actor)` est appelée **Then** :
    - Inside `DB::transaction`, l'entry est lockForUpdate.
    - L'entry est mise à jour : `attendance_status = disputed` (`escrow_status` reste `Locked` — aucune autre colonne touchée, `notified_at` reste set).
    - **AUCUN** `FinancialEvent` créé (pas de mouvement financier — cf. § Dev Notes « Interprétation invariant 9 epic »).
    - Audit : `Log::info('MissionAttendanceService::disputeAttendance — entry disputed by Face', ['entry_id' => $entry->id, 'face_id' => $entry->face_id, 'mission_id' => $entry->missionPayment->mission_id])`. La trace immutable (audit durable) est garantie par la combinaison `attendance_status = disputed` (state machine forward-only sur cette colonne) + `notified_at` (timestamp préservé).
    - La méthode retourne le `MissionPaymentCandidature $freshEntry` (`$entry->fresh()`).
    - **Pas de notification admin** dans cette story (déféré FIX-26.8).

14. **Given** un actor qui n'est PAS la Face propriétaire de l'entry (par ex. autre Face, Producer, Admin), **When** `disputeAttendance` est appelée **Then** :
    - Une `ValidationException::withMessages(['actor' => ['Seule la Face concernée peut contester sa propre absence.']])` est levée AVANT toute écriture DB.
    - L'entry n'est pas modifiée.

15. **Given** une entry dans un état `≠ Locked + attendance_status = absent` (par exemple `Released + present`, `Refunded + absent` (cas hypothétique futur), `Locked + disputed`, `Locked + pending`), **When** `disputeAttendance` est appelée **Then** :
    - Une `ValidationException::withMessages(['entry' => ['La contestation n\'est possible que sur une absence non encore tranchée.']])` est levée.
    - L'entry n'est pas modifiée.
    - Note : l'enforcement de la fenêtre 72h `notified_at + 72h > now()` est laissée à FIX-26.5 (endpoint level). FIX-26.3 vérifie uniquement le state machine. La colonne `notified_at` (ajoutée par cette story, cf. AC #12 + Task 1) fournit le timestamp stable que FIX-26.5 utilisera.

(Note : les cases « 2 fois disputeAttendance de suite » sont implicitement couverts — au 2ème appel l'AC #15 lève parce que l'entry n'est plus en `Locked + absent`. Pas d'AC dédié.)

### Bloc C — `resolveDispute(entry, outcome, admin)` — admin tranche

16. **Given** une entry `Locked + attendance_status = disputed`, un `admin` valide (`admin.userable_type === Admin::class`), et un outcome `DisputeResolutionOutcome::FavorFace`, **When** `MissionAttendanceService::resolveDispute($entry, $outcome, $admin)` est appelée **Then** :
    - Inside `DB::transaction`, l'entry est lockForUpdate.
    - `MissionPaymentService::releaseToFace($entry, $mission, 'disputed_resolved_face')` est appelée (méthode publique avec custom reason) → l'entry passe `Released`, le wallet Face est crédité, un `FinancialEvent` `EscrowRelease` est créé avec `metadata.reason = 'disputed_resolved_face'`.
    - L'`attendance_status` de l'entry **reste `disputed`** (audit historique préservé — la dispute a eu lieu, l'outcome est gravé dans le `FinancialEvent.metadata`).
    - Le helper `tryCompleteIfReady($mission)` est appelé : si la mission n'a plus aucune entry `Locked + (pending|absent)`, transitionne `PendingAttendanceValidation → Completed` + `notifyProducerOnCompletion`.
    - La méthode retourne le `MissionPaymentCandidature $freshEntry`.

17. **Given** une entry `Locked + disputed` et `outcome = DisputeResolutionOutcome::FavorProducer`, **When** `resolveDispute` est appelée **Then** :
    - `MissionPaymentService::refundToProducer($entry, $mission, 'disputed_resolved_producer')` est appelée (méthode publique avec custom reason) → l'entry passe `Refunded`, le wallet Producer est crédité, un `FinancialEvent` `Refund` est créé avec `metadata.reason = 'disputed_resolved_producer', refund_percentage = 100`.
    - L'`attendance_status` reste `disputed`.
    - Le helper `tryCompleteIfReady($mission)` est appelé.

18. **Given** un admin invalide (`admin.userable_type !== Admin::class`), **When** `resolveDispute` est appelée **Then** :
    - Une `ValidationException::withMessages(['admin' => ['Seul un administrateur peut résoudre un litige.']])` est levée AVANT toute écriture DB.

19. **Given** une entry dans un état `≠ Locked + disputed` (par exemple `Locked + absent`, `Released`, `Refunded`), **When** `resolveDispute` est appelée **Then** :
    - Une `ValidationException::withMessages(['entry' => ['La résolution de litige n\'est possible que sur une entry contestée non encore tranchée.']])` est levée.
    - L'entry n'est pas modifiée.

20. **Given** `resolveDispute` est appelée DEUX FOIS de suite avec le même outcome, **When** la deuxième invocation s'exécute **Then** :
    - L'entry est maintenant `Released` ou `Refunded` (plus `Locked`).
    - Le guard AC #19 (resolveDispute exige `Locked + disputed`) lève `ValidationException` au 2ème appel (l'entry n'est plus en `Locked + disputed`). Comportement idempotent par défense.
    - Aucun double crédit, aucun double `FinancialEvent` (les idempotency keys uniques de `releaseToFace`/`refundToProducer` la deuxième fois ne seraient même pas atteints — la guard les bloque en amont).

21. **Given** une mission `PendingAttendanceValidation` avec UNE seule entry `Locked + disputed` (toutes les autres déjà Released ou Refunded), **When** `resolveDispute(entry, FavorFace)` est appelée **Then** :
    - Après le `releaseToFace`, l'entry est `Released + disputed`.
    - `tryCompleteIfReady` détecte qu'il n'y a plus d'entry `Locked + (pending|absent)` → transitionne mission `PendingAttendanceValidation → Completed` + `notifyProducerOnCompletion`.
    - Test asserte `$mission->fresh()->status === MissionStatus::Completed` après le call.

### Bloc D — Invariants transversaux

22. **Given** le scope strict de la story, **Then** ces invariants sont respectés :
    - **Aucun** controller, FormRequest, route Producer/Face/Admin n'est créé/modifié.
    - **Aucun** mailable, listener, event, notification template n'est créé.
    - **Une seule** migration ajoutée (la colonne `notified_at`). Aucune autre migration, factory, ni seed.
    - **Aucun** front Vue n'est touché.
    - **Le bridge backward-compat dans `MissionService::completeMission`** reste fonctionnel (le commentaire est mis à jour pour pointer FIX-26.4).
    - **Tous les tests existants** (suite complète FIX-26.2 = 1925 verts, dont `CompleteMissionTest` 11 tests + `MissionAttendanceReleaseFundsTest` 7 tests + `AutoReleaseMissionFundsCommandTest` 1 test + `MissionPaymentCandidatureSchemaTest` 5 tests) **restent verts SANS modification** — la nouvelle colonne `notified_at` étant nullable, elle n'affecte aucune assertion existante.
    - Le `match` exhaustif `MissionPaymentService::releaseFunds:710-713` (Present/Absent) reste **intact** — l'ajout de cas de routing dans le service n'affecte pas le routing physique de `releaseFunds`.

23. **Given** la non-régression de l'invariant 5 epic (« Mission `Completed` peut contenir Locked+disputed »), **When** une mission a passe `Closed → PendingAttendanceValidation → Completed` via `markAttendance` avec une entry pré-existante en `Locked + disputed`, **Then** :
    - La mission est bien `Completed` après le call.
    - L'entry `Locked + disputed` est **inchangée** par `tryCompleteIfReady` (l'invariant 5 dit qu'elle peut rester ainsi).
    - Le helper `tryCompleteIfReady` filtre par `Locked + (pending|absent)` — `Locked + disputed` n'est PAS bloquant.

## Tasks / Subtasks

### Task 1 — Migration `notified_at` + model patch `MissionPaymentCandidature` (AC: #6, #7, audit invariant 9 epic)

- [x] 1.1 Créer `backend/database/migrations/2026_04_28_HHMMSS_add_notified_at_to_mission_payment_candidatures_table.php` (le timestamp `HHMMSS` doit être postérieur aux 2 migrations FIX-26.1 `2026_04_27_120000` et `2026_04_27_120100`) :
  ```php
  <?php

  declare(strict_types=1);

  use Illuminate\Database\Migrations\Migration;
  use Illuminate\Database\Schema\Blueprint;
  use Illuminate\Support\Facades\Schema;

  return new class extends Migration
  {
      public function up(): void
      {
          Schema::table('mission_payment_candidatures', function (Blueprint $table): void {
              $table->timestamp('notified_at')->nullable()->after('attendance_status');
          });
      }

      public function down(): void
      {
          Schema::table('mission_payment_candidatures', function (Blueprint $table): void {
              $table->dropColumn('notified_at');
          });
      }
  };
  ```
  - **Justification** : strict miroir de la migration FIX-26.1 `2026_04_27_120000_add_attendance_status_to_mission_payment_candidatures_table.php` (même imports, même style `Schema::table` + `Blueprint`). `nullable()` car la colonne n'a de sens que pour les entries `absent` ; les entries `present`/`disputed`/`pending` la laissent à `null`. `after('attendance_status')` pour grouper visuellement les colonnes liées à la présence.
- [x] 1.2 Patcher `backend/app/Models/MissionPaymentCandidature.php` :
  - Ajouter `'notified_at',` au tableau `$fillable` (ligne 26-36), inséré à la fin (après `'refunded_at'`) — préserve l'ordre logique escrow → present/absent settlement.
  - Ajouter `'notified_at' => 'datetime',` au tableau retourné par `casts()` (ligne 38-48), inséré entre `'attendance_status' => AttendanceStatus::class` et `'montant_face_recoit' => 'integer'` pour grouper avec les autres datetime casts (`locked_at`, `released_at`, `refunded_at`).
  - Augmenter le PHPDoc de classe (lignes 12-23) avec `@property \Carbon\Carbon|null $notified_at`, inséré entre `@property \App\Enums\AttendanceStatus $attendance_status` et `@property-read \App\Models\MissionPayment|null $missionPayment`.
- [x] 1.3 Depuis `backend/`, valider la réversibilité **explicitement sur la DB test** (`weact_test`) — l'usage de `--env=testing` est obligatoire pour éviter de toucher la DB dev locale par mégarde : lancer `cd backend && php artisan migrate --env=testing` puis `cd backend && php artisan migrate:rollback --step=1 --env=testing` puis `cd backend && php artisan migrate --env=testing` à nouveau.
- [x] 1.4 Lancer `cd backend && php -l app/Models/MissionPaymentCandidature.php` → 0 erreur de syntaxe.
- [x] 1.5 Lancer `cd backend && ./vendor/bin/phpstan analyse app/Models/MissionPaymentCandidature.php --level=max` → 0 erreur.
- [x] 1.6 Étendre le test FIX-26.1 `backend/tests/Feature/Mission/MissionPaymentCandidatureSchemaTest.php` est **HORS SCOPE** (la non-régression de ce test est garantie par le default `null` de la nouvelle colonne — aucune assertion existante n'est cassée). Les tests dédiés au comportement de `notified_at` sont écrits dans `MissionAttendanceServiceTest` (cf. Task 6).

### Task 2 — Promouvoir les helpers de `MissionPaymentService` à `public` + paramétrer le reason (AC: support pour markAttendance et resolveDispute)

- [x] 2.1 Patcher `backend/app/Services/MissionPaymentService.php` :
  - Changer la signature de `releaseToFace` de `private function releaseToFace(MissionPaymentCandidature $entry, Mission $mission): void` à `public function releaseToFace(MissionPaymentCandidature $entry, Mission $mission, string $reason = 'attendance_present'): void`.
  - À l'intérieur du corps, modifier l'appel à `recordMissionAttendanceFinancialEvent(FinancialEventType::EscrowRelease, $entry, ..., ['status' => 'completed', 'metadata' => ['reason' => 'attendance_present']])` pour utiliser `'reason' => $reason` au lieu de `'attendance_present'` hard-codé. Le default `'attendance_present'` du paramètre préserve le comportement FIX-26.2 quand `releaseFunds` appelle sans explicit reason.
  - Changer la signature de `refundToProducer` de `private function refundToProducer(MissionPaymentCandidature $entry, Mission $mission): void` à `public function refundToProducer(MissionPaymentCandidature $entry, Mission $mission, string $reason = 'attendance_absent'): void`.
  - Modifier l'appel à `recordMissionAttendanceFinancialEvent(FinancialEventType::Refund, $entry, ..., ['status' => 'completed', 'metadata' => ['reason' => 'attendance_absent', 'refund_percentage' => 100]])` pour utiliser `'reason' => $reason` au lieu de `'attendance_absent'` hard-codé. `refund_percentage => 100` reste hard-codé (la story actuelle ne change pas le pourcentage de refund — toujours 100%).
- [x] 2.2 Vérifier que l'appel interne dans `releaseFunds` (lignes 711-712) continue de fonctionner sans paramètre explicit (le default kick in) :
  ```php
  match ($entry->attendance_status) {
      AttendanceStatus::Present => $this->releaseToFace($entry, $mission),
      AttendanceStatus::Absent => $this->refundToProducer($entry, $mission),
  };
  ```
- [x] 2.3 Lancer `cd backend && php -l app/Services/MissionPaymentService.php` → 0 erreur de syntaxe.
- [x] 2.4 Lancer `cd backend && ./vendor/bin/phpstan analyse app/Services/MissionPaymentService.php --level=max` → 0 nouvelle erreur (les 3 erreurs pré-existantes lignes 140 / 207 / 603 documentées en FIX-26.2 § Debug Log restent inchangées).

### Task 3 — Promouvoir `MissionService::notifyProducerOnCompletion` à `public` (AC: support pour markAttendance + resolveDispute final transition)

- [x] 3.1 Patcher `backend/app/Services/MissionService.php` :
  - Changer la signature de `notifyProducerOnCompletion` de `private function notifyProducerOnCompletion(Mission $mission): void` à `public function notifyProducerOnCompletion(Mission $mission): void`. Aucun changement de corps.
- [x] 3.2 Mettre à jour le commentaire du bridge backward-compat lignes 246-250 :
  - Remplacer la ligne `// This bridge will be REMOVED in FIX-26.3 once MissionAttendanceService` par `// This bridge will be REMOVED in FIX-26.4 once the new attendance endpoint`.
  - Remplacer la ligne `// takes over via the new endpoints.` par `// (POST /api/v1/producer/missions/{uuid}/validate-attendance) takes over.`
  - **Justification** : FIX-26.3 ajoute le service mais NE remplace PAS le legacy endpoint `/complete`. C'est FIX-26.4 qui introduit le nouveau endpoint et permet de retirer le bridge.
- [x] 3.3 Lancer `cd backend && php -l app/Services/MissionService.php` → 0 erreur.
- [x] 3.4 Lancer `cd backend && ./vendor/bin/phpstan analyse app/Services/MissionService.php --level=max` → 0 nouvelle erreur.

### Task 4 — Créer l'enum `DisputeResolutionOutcome` (AC: #16, #17, type-safety pour resolveDispute)

- [x] 4.1 Créer `backend/app/Enums/DisputeResolutionOutcome.php` :
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Enums;

  enum DisputeResolutionOutcome: string
  {
      case FavorFace = 'favor_face';
      case FavorProducer = 'favor_producer';
  }
  ```
- [x] 4.2 Style minimaliste strict miroir `EscrowStatus` (`backend/app/Enums/EscrowStatus.php`) : pas de méthode `label()`, pas de `values()`. Ajouts si nécessaire dans une story future (non-scope FIX-26.3).
- [x] 4.3 Lancer `cd backend && php -l app/Enums/DisputeResolutionOutcome.php` → 0 erreur.

### Task 5 — Créer `MissionAttendanceService` (AC: #1-#23)

- [x] 5.1 Créer `backend/app/Services/MissionAttendanceService.php` avec :
  - `declare(strict_types=1);`, namespace `App\Services`.
  - Imports requis :
    ```php
    use App\Enums\AttendanceStatus;
    use App\Enums\DisputeResolutionOutcome;
    use App\Enums\EscrowStatus;
    use App\Enums\MissionPaymentStatus;
    use App\Enums\MissionStatus;
    use App\Models\Admin;
    use App\Models\Face;
    use App\Models\Mission;
    use App\Models\MissionPayment;
    use App\Models\MissionPaymentCandidature;
    use App\Models\Producer;
    use App\Models\User;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Validation\ValidationException;
    ```
  - Constructor par injection :
    ```php
    public function __construct(
        private readonly MissionPaymentService $missionPaymentService,
        private readonly MissionService $missionService,
    ) {}
    ```
- [x] 5.2 Implémenter `markAttendance(Mission $mission, array $entryIdToStatus, User $actor): Mission` :
  - Squelette PHP complet (à coller tel quel) :
    ```php
    /**
     * Apply a Producer batch of presence/absence decisions on a mission.
     * Present entries are released to Face wallets immediately. Absent entries
     * stay Locked, awaiting the 72h dispute window (FIX-26.5) or auto-settle cron (FIX-26.6).
     * Disputed entries (already in Locked+disputed) are not touched.
     *
     * @param  array<int, 'present'|'absent'>  $entryIdToStatus  Map entry_id => target status
     */
    public function markAttendance(Mission $mission, array $entryIdToStatus, User $actor): Mission
    {
        // ── Guards (pre-transaction, fail-fast) ──────────────────────────────
        if ($actor->userable_type !== Producer::class || $actor->userable_id !== $mission->producer_id) {
            throw ValidationException::withMessages([
                'actor' => ['Seul le producteur de la mission peut valider les présences.'],
            ]);
        }

        if (! in_array($mission->status, [MissionStatus::Closed, MissionStatus::PendingAttendanceValidation], true)) {
            throw ValidationException::withMessages([
                'mission' => ['La validation des présences n\'est possible que sur une mission clôturée ou en attente de validation.'],
            ]);
        }

        if ($entryIdToStatus === []) {
            throw ValidationException::withMessages([
                'entries' => ['Au moins une entry doit être fournie.'],
            ]);
        }

        foreach ($entryIdToStatus as $entryId => $status) {
            if (! in_array($status, ['present', 'absent'], true)) {
                throw ValidationException::withMessages([
                    'entries' => ['Les statuts acceptés sont : present, absent.'],
                ]);
            }
        }

        $mission->loadMissing('payment');
        if (
            ! $mission->payment instanceof MissionPayment
            || $mission->payment->status !== MissionPaymentStatus::Paid
        ) {
            throw ValidationException::withMessages([
                'mission' => ['La validation des présences requiert un paiement confirmé sur la mission.'],
            ]);
        }

        return DB::transaction(function () use ($mission, $entryIdToStatus, $actor): Mission {
            /** @var Mission $lockedMission */
            $lockedMission = Mission::lockForUpdate()->findOrFail($mission->id);
            $lockedMission->loadMissing('payment');

            // ── Re-validate under lock (race condition defense) ──────────────
            // A concurrent request (admin action, cron, parallel Producer call) could have
            // mutated the mission/payment between the pre-transaction guards and the lock.

            // Re-check ownership against the locked mission (defense-in-depth; symmetrical
            // with disputeAttendance/resolveDispute). producer_id is treated as immutable in
            // the domain but we re-verify against the locked snapshot before mutating entries.
            if ($actor->userable_type !== Producer::class || $actor->userable_id !== $lockedMission->producer_id) {
                throw ValidationException::withMessages([
                    'actor' => ['Seul le producteur de la mission peut valider les présences.'],
                ]);
            }

            if (! in_array($lockedMission->status, [MissionStatus::Closed, MissionStatus::PendingAttendanceValidation], true)) {
                throw ValidationException::withMessages([
                    'mission' => ['La validation des présences n\'est possible que sur une mission clôturée ou en attente de validation.'],
                ]);
            }

            if (
                ! $lockedMission->payment instanceof MissionPayment
                || $lockedMission->payment->status !== MissionPaymentStatus::Paid
            ) {
                throw ValidationException::withMessages([
                    'mission' => ['La validation des présences requiert un paiement confirmé sur la mission.'],
                ]);
            }

            $entriesToRelease = [];

            foreach ($entryIdToStatus as $entryId => $status) {
                /** @var MissionPaymentCandidature|null $entry */
                $entry = MissionPaymentCandidature::lockForUpdate()->find((int) $entryId);

                if (! $entry) {
                    throw new \RuntimeException("MissionAttendanceService::markAttendance — entry {$entryId} not found.");
                }

                $entry->loadMissing('missionPayment');

                if (! $entry->missionPayment || $entry->missionPayment->mission_id !== $lockedMission->id) {
                    throw new \RuntimeException(
                        "Entry {$entry->id} does not belong to mission {$lockedMission->id}."
                    );
                }

                // Idempotence: only update entries currently Locked + pending.
                // Already-tranched entries (present, absent, disputed) are no-op silently.
                if (
                    $entry->escrow_status !== EscrowStatus::Locked
                    || $entry->attendance_status !== AttendanceStatus::Pending
                ) {
                    continue;
                }

                $targetStatus = AttendanceStatus::from($status);

                // Set notified_at ONLY on the absent transition, and only the FIRST time
                // (column is nullable; we don't overwrite a non-null value on idempotent re-call).
                $updatePayload = ['attendance_status' => $targetStatus];
                if ($targetStatus === AttendanceStatus::Absent && $entry->notified_at === null) {
                    $updatePayload['notified_at'] = now();
                }

                $entry->update($updatePayload);

                if ($targetStatus === AttendanceStatus::Present) {
                    $entriesToRelease[] = $entry->refresh();
                }
                // Absent entries stay Locked, no financial movement here.
            }

            // Transition mission state to PendingAttendanceValidation if it was Closed.
            if ($lockedMission->status === MissionStatus::Closed) {
                $lockedMission->update(['status' => MissionStatus::PendingAttendanceValidation]);
            }

            // Release present entries to Face wallets (immediate payment).
            foreach ($entriesToRelease as $entry) {
                $this->missionPaymentService->releaseToFace($entry, $lockedMission, 'attendance_present');
            }

            $this->tryCompleteIfReady($lockedMission->refresh());

            /** @var Mission $freshMission */
            $freshMission = $lockedMission->fresh();

            return $freshMission;
        });
    }
    ```
  - **Justification design** :
    - Authorization + state machine validés AVANT l'ouverture de la transaction (rapidité fail-fast, pas d'overhead transactionnel pour les rejets prévus).
    - `lockForUpdate` sur Mission ET sur chaque entry — protection race conditions (Producer multi-tab, cron concurrent, etc.).
    - `loadMissing('missionPayment')` charge la relation pour la vérification d'appartenance (cf. découverte audit point 13).
    - L'idempotence est gérée par le `continue` si entry n'est plus en `Locked + Pending` — ré-exécution = no-op sans erreur.
    - `releaseToFace` est appelée APRÈS la transition mission → `PendingAttendanceValidation`, pour que le state machine soit cohérent au moment du release (la mission n'est plus `Closed` quand le wallet Face reçoit le crédit).
    - `tryCompleteIfReady` est appelé après le release pour finaliser la transition `→ Completed` si toutes les entries sont tranchées.
- [x] 5.3 Implémenter `disputeAttendance(MissionPaymentCandidature $entry, User $actor): MissionPaymentCandidature` :
  - Squelette :
    ```php
    /**
     * A Face contests being marked absent. Flips attendance_status: absent → disputed.
     * Escrow_status stays Locked. No financial movement; admin will resolve via resolveDispute().
     */
    public function disputeAttendance(MissionPaymentCandidature $entry, User $actor): MissionPaymentCandidature
    {
        if ($actor->userable_type !== Face::class || $actor->userable_id !== $entry->face_id) {
            throw ValidationException::withMessages([
                'actor' => ['Seule la Face concernée peut contester sa propre absence.'],
            ]);
        }

        return DB::transaction(function () use ($entry, $actor): MissionPaymentCandidature {
            /** @var MissionPaymentCandidature $lockedEntry */
            $lockedEntry = MissionPaymentCandidature::lockForUpdate()->findOrFail($entry->id);

            // Re-validate ownership under lock (defense-in-depth; symmetrical with the
            // state guard below). face_id is immutable in practice, but re-checking against
            // the locked snapshot keeps the auth consistent with the data we are about to mutate.
            if ($actor->userable_type !== Face::class || $actor->userable_id !== $lockedEntry->face_id) {
                throw ValidationException::withMessages([
                    'actor' => ['Seule la Face concernée peut contester sa propre absence.'],
                ]);
            }

            if (
                $lockedEntry->escrow_status !== EscrowStatus::Locked
                || $lockedEntry->attendance_status !== AttendanceStatus::Absent
            ) {
                throw ValidationException::withMessages([
                    'entry' => ['La contestation n\'est possible que sur une absence non encore tranchée.'],
                ]);
            }

            $lockedEntry->update(['attendance_status' => AttendanceStatus::Disputed]);

            $lockedEntry->loadMissing('missionPayment');
            Log::info('MissionAttendanceService::disputeAttendance — entry disputed by Face', [
                'entry_id' => $lockedEntry->id,
                'face_id' => $lockedEntry->face_id,
                'mission_id' => $lockedEntry->missionPayment?->mission_id,
            ]);

            /** @var MissionPaymentCandidature $freshEntry */
            $freshEntry = $lockedEntry->fresh();

            return $freshEntry;
        });
    }
    ```
  - **Justification** : pas de FinancialEvent (pas de mouvement). Pas de notification admin (déférée FIX-26.8). `Log::info` suffit pour audit traçable via stack centralisé.
- [x] 5.4 Implémenter `resolveDispute(MissionPaymentCandidature $entry, DisputeResolutionOutcome $outcome, User $admin): MissionPaymentCandidature` :
  - Squelette :
    ```php
    /**
     * Admin resolves a disputed entry: either credits Face (favor face) or refunds Producer
     * (favor producer). The attendance_status stays `disputed` for audit history; only
     * escrow_status changes.
     *
     * Triggers tryCompleteIfReady() afterwards in case this was the last Locked entry.
     */
    public function resolveDispute(
        MissionPaymentCandidature $entry,
        DisputeResolutionOutcome $outcome,
        User $admin,
    ): MissionPaymentCandidature {
        if ($admin->userable_type !== Admin::class) {
            throw ValidationException::withMessages([
                'admin' => ['Seul un administrateur peut résoudre un litige.'],
            ]);
        }

        return DB::transaction(function () use ($entry, $outcome): MissionPaymentCandidature {
            /** @var MissionPaymentCandidature $lockedEntry */
            $lockedEntry = MissionPaymentCandidature::lockForUpdate()->findOrFail($entry->id);

            if (
                $lockedEntry->escrow_status !== EscrowStatus::Locked
                || $lockedEntry->attendance_status !== AttendanceStatus::Disputed
            ) {
                throw ValidationException::withMessages([
                    'entry' => ['La résolution de litige n\'est possible que sur une entry contestée non encore tranchée.'],
                ]);
            }

            $lockedEntry->loadMissing('missionPayment.mission');
            /** @var Mission|null $mission */
            $mission = $lockedEntry->missionPayment?->mission;

            if (! $mission) {
                throw new \RuntimeException("MissionAttendanceService::resolveDispute — mission not found for entry {$lockedEntry->id}.");
            }

            match ($outcome) {
                DisputeResolutionOutcome::FavorFace => $this->missionPaymentService->releaseToFace(
                    $lockedEntry,
                    $mission,
                    'disputed_resolved_face',
                ),
                DisputeResolutionOutcome::FavorProducer => $this->missionPaymentService->refundToProducer(
                    $lockedEntry,
                    $mission,
                    'disputed_resolved_producer',
                ),
            };

            // attendance_status stays `disputed` for audit; only escrow_status flipped by helper.
            $this->tryCompleteIfReady($mission->refresh());

            /** @var MissionPaymentCandidature $freshEntry */
            $freshEntry = $lockedEntry->fresh();

            return $freshEntry;
        });
    }
    ```
- [x] 5.5 Implémenter le helper privé `tryCompleteIfReady(Mission $mission): void` :
  - Squelette :
    ```php
    /**
     * Transition mission to Completed if all per-Face escrow is settled.
     * "Settled" = no entry currently in (Locked + (pending|absent)). Locked+disputed is allowed
     * to remain (invariant 5: a Completed mission can carry disputed entries pending admin).
     *
     * Defensive: if no payment is attached or payment isn't Paid, this method is a no-op.
     * The mission cannot logically be `Completed` without a paid payment + at least one entry.
     */
    private function tryCompleteIfReady(Mission $mission): void
    {
        if ($mission->status === MissionStatus::Completed) {
            return; // already complete, nothing to do
        }

        $mission->loadMissing('payment');

        if (
            ! $mission->payment instanceof MissionPayment
            || $mission->payment->status !== MissionPaymentStatus::Paid
        ) {
            // Refuse to complete a mission without a confirmed payment. This guards against
            // a hypothetical bug path where tryCompleteIfReady is called on a mission whose
            // payment was never created (defensive — should never happen, but a missed guard
            // upstream would otherwise mark the mission Completed by accident).
            return;
        }

        // Refuse to complete a mission with zero entries. Without this check, a mission whose
        // payment exists but has no candidatures would pass the "no unsettled" test trivially
        // and be marked Completed — which violates the invariant that a Completed mission carries
        // at least one settled (Released/Refunded) or disputed entry. Defensive guard matching
        // the comment contract.
        if (! $mission->payment->entries()->exists()) {
            return;
        }

        $hasUnsettled = $mission->payment->entries()
            ->where('escrow_status', EscrowStatus::Locked)
            ->whereIn('attendance_status', [AttendanceStatus::Pending, AttendanceStatus::Absent])
            ->exists();

        if ($hasUnsettled) {
            return; // mission stays in PendingAttendanceValidation
        }

        $mission->update(['status' => MissionStatus::Completed]);
        $this->missionService->notifyProducerOnCompletion($mission->fresh());
    }
    ```
  - **Justification** : la condition de complétion exclut explicitement `Locked + disputed` (invariant 5 epic ligne 67). Si la mission est déjà `Completed`, no-op silencieux (idempotence — `resolveDispute` peut être appelée APRÈS coup sur une mission déjà complète, c'est OK).
- [x] 5.6 Lancer `cd backend && php -l app/Services/MissionAttendanceService.php` → 0 erreur.
- [x] 5.7 Lancer `cd backend && ./vendor/bin/phpstan analyse app/Services/MissionAttendanceService.php --level=max` → 0 erreur.
- [x] 5.8 Lancer `cd backend && ./vendor/bin/pint --test app/Services/MissionAttendanceService.php` → `{"result":"pass"}`.

### Task 6 — Créer `MissionAttendanceServiceTest` (AC: #1-#23)

- [x] 6.1 Créer `backend/tests/Feature/Mission/MissionAttendanceServiceTest.php` avec :
  - Imports requis :
    ```php
    use App\Enums\AttendanceStatus;
    use App\Enums\CandidatureStatus;
    use App\Enums\DisputeResolutionOutcome;
    use App\Enums\EscrowStatus;
    use App\Enums\FinancialEventType;
    use App\Enums\MissionPaymentStatus;
    use App\Enums\MissionStatus;
    use App\Models\Admin;
    use App\Models\Candidature;
    use App\Models\Face;
    use App\Mail\MissionCompletedMail;
    use App\Models\FinancialEvent;
    use App\Models\Mission;
    use App\Models\MissionPayment;
    use App\Models\MissionPaymentCandidature;
    use App\Models\Notification;
    use App\Models\Producer;
    use App\Models\User;
    use App\Services\MissionAttendanceService;
    use Illuminate\Foundation\Testing\RefreshDatabase;
    use Illuminate\Support\Facades\Mail;
    use Illuminate\Validation\ValidationException;
    use Tests\TestCase;
    ```
  - `class MissionAttendanceServiceTest extends TestCase` avec `use RefreshDatabase;`.
  - Properties privées : `MissionAttendanceService $service`, `Producer $producer`, `User $producerUser`.
  - `setUp()` :
    ```php
    parent::setUp();
    $this->service = app(MissionAttendanceService::class);
    $this->producer = Producer::factory()->create();
    $this->producerUser = User::factory()->create([
        'userable_type' => Producer::class,
        'userable_id' => $this->producer->id,
    ]);
    ```
- [x] 6.2 Helper local `createPaidMissionWithFaces(int $faceCount, MissionStatus $missionStatus = MissionStatus::Closed): array{0: Mission, 1: list<array{candidature: Candidature, entry: MissionPaymentCandidature, faceUser: User, face: Face}>}` :
  ```php
  private function createPaidMissionWithFaces(
      int $faceCount,
      MissionStatus $missionStatus = MissionStatus::Closed,
  ): array {
      $factoryMethod = match ($missionStatus) {
          MissionStatus::Closed => 'closed',
          MissionStatus::PendingAttendanceValidation => 'closed', // factory only has up-to-Closed; we override status below
          default => throw new \InvalidArgumentException("Unsupported mission status for fixture: {$missionStatus->value}"),
      };
      $mission = Mission::factory()->{$factoryMethod}()->create(['producer_id' => $this->producer->id]);

      if ($missionStatus === MissionStatus::PendingAttendanceValidation) {
          $mission->update(['status' => MissionStatus::PendingAttendanceValidation]);
          $mission->refresh();
      }

      $payment = MissionPayment::create([
          'mission_id' => $mission->id,
          'producer_id' => $this->producer->id,
          'nombre_faces_retenues' => $faceCount,
          'budget_par_face' => 100000,
          'montant_sous_total' => 100000 * $faceCount,
          'commission_producteur' => 10000 * $faceCount,
          'montant_total_producteur' => 110000 * $faceCount,
          'commission_faces_total' => 10000 * $faceCount,
          'montant_total_faces' => 90000 * $faceCount,
          'status' => MissionPaymentStatus::Paid,
          'paid_at' => now(),
      ]);

      $faces = [];

      for ($i = 0; $i < $faceCount; $i++) {
          $face = Face::factory()->create(['prenom' => "Face{$i}"]);
          $faceUser = User::factory()->create([
              'userable_type' => Face::class,
              'userable_id' => $face->id,
              'email' => "face{$i}@example.test",
          ]);
          $candidature = Candidature::factory()->create([
              'mission_id' => $mission->id,
              'face_id' => $face->id,
              'status' => CandidatureStatus::Confirmed,
          ]);
          $entry = MissionPaymentCandidature::create([
              'mission_payment_id' => $payment->id,
              'candidature_id' => $candidature->id,
              'face_id' => $face->id,
              'montant_face_recoit' => 90000,
              'escrow_status' => EscrowStatus::Locked,
              'locked_at' => now(),
              // attendance_status defaults to 'pending' (DB default from FIX-26.1)
          ]);
          $faces[] = [
              'candidature' => $candidature,
              'entry' => $entry,
              'faceUser' => $faceUser,
              'face' => $face,
          ];
      }

      return [$mission, $faces];
  }
  ```
- [x] 6.3 Tests pour `markAttendance` (AC #1-#12) — minimum 13 tests :

  - **`test_mark_attendance_releases_present_entries_and_keeps_absent_locked`** (AC #1, #9) :
    - Mission Closed avec 3 entries (toutes en `pending`).
    - `Mail::fake()`.
    - Call `markAttendance($mission, [$f0->entry->id => 'present', $f1->entry->id => 'absent', $f2->entry->id => 'present'], $this->producerUser)`.
    - Asserte mission status = `PendingAttendanceValidation` (1 entry `absent` en Locked bloque la transition vers Completed).
    - Asserte `$f0->entry->fresh()` : `escrow_status === Released`, `attendance_status === Present`, `released_at !== null`.
    - Asserte `$f1->entry->fresh()` : `escrow_status === Locked`, `attendance_status === Absent`, `released_at === null`, `refunded_at === null`, `notified_at !== null` (set par markAttendance au flip → absent).
    - Asserte `$f2->entry->fresh()` : `escrow_status === Released`, `attendance_status === Present`.
    - Asserte `$f0->faceUser->refresh()->balance === 90000` ; `$f1->faceUser->refresh()->balance === 0` ; `$f2->faceUser->refresh()->balance === 90000` ; `$this->producerUser->refresh()->balance === 0` (pas de refund encore).
    - Asserte `FinancialEvent::count() === 2` (2 `EscrowRelease`, 0 `Refund`).
    - Asserte `assertDatabaseHas('financial_events', ['idempotency_key' => "mission_attendance_escrow_release:{$f0->entry->id}", 'type' => 'escrow_release'])`.
    - `Mail::assertQueued(MissionCompletedMail::class, 2)`.

  - **`test_mark_attendance_completes_mission_when_all_entries_present`** (AC #10) :
    - Mission Closed avec 2 entries.
    - Call `markAttendance($mission, [$f0->entry->id => 'present', $f1->entry->id => 'present'], $this->producerUser)`.
    - Asserte `$mission->fresh()->status === MissionStatus::Completed`.
    - Asserte les 2 entries `Released`.
    - Asserte `Notification::where('user_id', $this->producerUser->id)->where('type', 'mission_completed_producer')->exists() === true` (notif Producer envoyée par `notifyProducerOnCompletion`).

  - **`test_mark_attendance_rejects_non_owner_actor`** (AC #2) :
    - Mission Closed avec 1 entry.
    - Crée un autre Producer `$otherProducer` + `$otherProducerUser`.
    - **Pattern try/catch obligatoire** — `$this->expectException(...)` interrompt l'exécution du test à la ligne qui throw (les assertions post-call ne s'exécuteraient JAMAIS). On capture explicitement :
      ```php
      try {
          $this->service->markAttendance($mission, [$f0->entry->id => 'present'], $otherProducerUser);
          $this->fail('Expected ValidationException was not thrown');
      } catch (ValidationException $e) {
          // exception correctly raised before any DB write
      }
      // Now post-exception assertions:
      $this->assertSame(AttendanceStatus::Pending, $f0->entry->fresh()->attendance_status);
      $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
      ```

  - **`test_mark_attendance_rejects_invalid_mission_status`** (AC #3) :
    - Mission Published (pas Closed/PendingAttendanceValidation).
    - Crée fixtures via `createPaidMissionWithFaces(1, MissionStatus::Closed)` puis `$mission->update(['status' => MissionStatus::Published])`.
    - `$this->expectException(ValidationException::class);`
    - Call `markAttendance(...)`.
    - Aucune entry modifiée.

  - **`test_mark_attendance_rejects_entry_not_belonging_to_mission`** (AC #4) :
    - Crée 2 missions séparées avec 1 entry chacune.
    - Call `markAttendance($mission1, [$entry_of_mission2->id => 'present'], $this->producerUser)`.
    - `$this->expectException(\RuntimeException::class);` avec message matchant `/does not belong to mission/`.

  - **`test_mark_attendance_rejects_invalid_status_value`** (AC #5) :
    - Mission Closed avec 1 entry.
    - `$this->expectException(ValidationException::class);`
    - Call `markAttendance($mission, [$f0->entry->id => 'disputed'], $this->producerUser)` (ou `'pending'`, ou `'foo'`).
    - L'AC précis lance avec `disputed` — testez `disputed` ET `'foo'` (string libre) dans le même test ou tests séparés.

  - **`test_mark_attendance_rejects_mission_without_paid_payment`** (AC #6) :
    - Crée fixtures via `createPaidMissionWithFaces(1, MissionStatus::Closed)`. Forcer `$mission->payment->update(['status' => MissionPaymentStatus::Pending])` après création.
    - **Pattern try/catch** (les assertions post-call doivent s'exécuter — `expectException` les couperait) :
      ```php
      try {
          $this->service->markAttendance($mission, [$f0->entry->id => 'present'], $this->producerUser);
          $this->fail('Expected ValidationException was not thrown');
      } catch (ValidationException $e) {
          // expected
      }
      $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
      $this->assertSame(AttendanceStatus::Pending, $f0->entry->fresh()->attendance_status);
      $this->assertSame(0, $f0->faceUser->refresh()->balance);
      $this->assertSame(0, FinancialEvent::count());
      ```
    - **NB** : tester aussi le cas où la mission n'a PAS de payment du tout — dans ce cas, retire le payment via `$mission->payment->delete()` puis `$mission->refresh()`. Même exception attendue, même pattern try/catch.

  - **`test_mark_attendance_rejects_empty_input`** (AC #7) :
    - Mission Closed valide avec 1 entry.
    - **Pattern try/catch** :
      ```php
      try {
          $this->service->markAttendance($mission, [], $this->producerUser);
          $this->fail('Expected ValidationException was not thrown');
      } catch (ValidationException $e) {
          // expected
      }
      $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
      $this->assertSame(AttendanceStatus::Pending, $f0->entry->fresh()->attendance_status);
      ```

  - **`test_mark_attendance_revalidates_mission_status_under_lock`** (AC #8) :
    - Pattern : on simule la race en mockant le `lockForUpdate()` pour qu'il retourne une mission MUTÉE (status=Published/Draft) — alternative pragmatique : ouvrir une 2ème connexion DB et muter le status pendant la transaction. La forme la plus simple et robuste à utiliser (sans concurrence réelle) :
    - Crée fixtures Closed/Paid normaux.
    - Avant le call `markAttendance`, set up un listener Eloquent qui mute `$mission->status` à `Published` JUSTE après la création initiale. OU plus simple : utiliser un `partialMock` du model Mission avec `->lockForUpdate()` qui retourne une instance pré-mutée.
    - Approche pratique sans mocks lourds : muter `Mission::where('id', $mission->id)->update(['status' => Published])` JUSTE AVANT d'invoquer le service (mais APRÈS avoir construit l'instance `$mission` côté caller). Cela simule l'état où la pré-validation passe (sur `$mission` cached) mais le `lockForUpdate()` voit la version DB mutée.
    - **Pattern try/catch** (les assertions de rollback doivent s'exécuter — `expectException` les couperait) :
      ```php
      try {
          $this->service->markAttendance($mission, [$f0->entry->id => 'present'], $this->producerUser);
          $this->fail('Expected ValidationException raised under lock');
      } catch (ValidationException $e) {
          // expected — re-validation under lock detected the race
      }
      $this->assertSame(AttendanceStatus::Pending, $f0->entry->fresh()->attendance_status);
      $this->assertSame(0, FinancialEvent::count());
      ```
    - **Justification** : ce test verrouille le comportement défensif sous lock. Si un dev refactorise le squelette en retirant la re-validation post-lock, ce test devient rouge.

  - **`test_mark_attendance_replays_batch_on_already_tranched_entries_is_no_op`** (AC #9) :
    - Mission `PendingAttendanceValidation` avec 3 entries préexistantes :
      - `$f_present` : `Locked + present` (déjà tranchée par un précédent appel — note : pour cette story, on positionne directement `attendance_status = present` via `update()` sans passer par `releaseToFace`, afin de ne PAS pré-créditer le wallet et garder l'asserte `balance === 0` lisible — cf. justification dans le commentaire ci-dessous).
      - `$f_absent` : `Locked + absent` avec `notified_at = now()->subHours(1)`.
      - `$f_disputed` : `Locked + disputed` (hors-input).
    - Input batch : `[$f_present->entry->id => 'present', $f_absent->entry->id => 'absent']` — l'entry disputed est volontairement omise.
    - Call `markAttendance(...)`.
    - **Asserts** :
      - `$f_present->entry->fresh()` : inchangée — `attendance_status === Present`, `escrow_status === Locked` (pas reReleased).
      - `$f_absent->entry->fresh()` : inchangée — `attendance_status === Absent`, `escrow_status === Locked`, `notified_at` égal à la valeur préexistante (pas écrasé).
      - `$f_disputed->entry->fresh()` : inchangée — `Locked + disputed`.
      - `FinancialEvent::count() === 0` (aucun mouvement déclenché par ce batch — la guard `if attendance_status !== Pending continue;` filtre les 3 entries).
      - `$mission->fresh()->status === MissionStatus::PendingAttendanceValidation` (pas Completed — l'entry `Locked + absent` n'est pas finalisée).
    - **Justification du fixture `present` sans crédit** : on ne passe pas par `releaseToFace` pour pré-établir le `present` car cela créerait un `FinancialEvent` parasite faussant l'assertion `count() === 0`. La story teste ici la robustesse de la guard d'idempotence `continue` en isolation, pas la cohérence end-to-end d'un re-batch après release. L'idempotence post-release est déjà couverte par `test_mark_attendance_is_idempotent` (AC #11).

  - **`test_mark_attendance_completes_mission_with_locked_disputed_present`** (couverture invariant 5 via markAttendance — supplément du test AC #23 qui démarre depuis Closed) :
    - Mission `PendingAttendanceValidation` avec 2 entries : 1 préexistante en `Locked + disputed`, 1 en `Locked + pending`.
    - Le batch contient seulement l'entry `pending` : `[$entry_pending->id => 'present']`. L'entry `disputed` n'est PAS dans l'input.
    - Call `markAttendance(...)`.
    - Asserte `$entry_disputed->fresh()` inchangée (`Locked + disputed`).
    - Asserte `$entry_pending->fresh()` → `Released + present`.
    - Asserte mission `Completed` (car aucune entry `Locked + (pending|absent)` ne reste — l'entry disputed est tolérée par invariant 5).
    - **Note** : ce test était précédemment nommé `test_mark_attendance_skips_already_tranched_entries` mais ne testait pas AC #9 — il valide en réalité l'invariant 5 (Mission Completed peut tolérer une entry Locked+disputed) sur le chemin d'entrée `markAttendance`. Renommé pour clarté. Il complète `test_mission_can_complete_with_locked_disputed_entries_remaining` (AC #23) qui démarre depuis `Closed`.

  - **`test_mark_attendance_is_idempotent`** (AC #11) :
    - Mission Closed avec 1 entry.
    - Call `markAttendance($mission, [$f0->entry->id => 'present'], $this->producerUser)` une 1ère fois.
    - Asserte `$f0->faceUser->refresh()->balance === 90000`, `FinancialEvent::count() === 1`.
    - Call `markAttendance(...)` une 2ème fois avec le même input.
    - Asserte `$f0->faceUser->refresh()->balance === 90000` (pas 180000), `FinancialEvent::count() === 1` (pas 2). Exception non levée (aucun double release).

  - **`test_mark_attendance_sets_notified_at_for_absent_and_does_not_overwrite_on_idempotent_call`** (AC #12) :
    - Mission Closed avec 1 entry `Locked + pending`.
    - Call `markAttendance($mission, [$f0->entry->id => 'absent'], $this->producerUser)` une 1ère fois.
    - Asserte `$f0->entry->fresh()->notified_at !== null`, capture la valeur dans `$firstNotifiedAt = $f0->entry->fresh()->notified_at;`.
    - Asserte `$f0->entry->fresh()->attendance_status === Absent`, `escrow_status === Locked`.
    - Avancer le temps simulé : `$this->travel(10)->seconds();` (utilisation de `Illuminate\Support\Facades\Date::setTestNow` ou `$this->travel()`).
    - Call `markAttendance(...)` une 2ème fois avec le même input.
    - Asserte `$f0->entry->fresh()->notified_at->equalTo($firstNotifiedAt)` (le timestamp original est préservé — l'entry ayant déjà `attendance_status = absent`, la guard `if !== Pending continue;` filtre le 2ème UPDATE).
    - **Note** : pas besoin de `expectException` — la 2ème call est silencieusement no-op.

- [x] 6.4 Tests pour `disputeAttendance` (AC #13-#15) — minimum 4 tests :

  - **`test_dispute_attendance_flips_absent_to_disputed`** (AC #13) :
    - Mission `PendingAttendanceValidation` avec 1 entry `Locked + absent` (créée via `createPaidMissionWithFaces(1)` puis `$f0->entry->update(['attendance_status' => Absent])`).
    - Call `disputeAttendance($f0->entry, $f0->faceUser)`.
    - Asserte `$f0->entry->fresh()->attendance_status === Disputed`, `escrow_status === Locked` (inchangé), `released_at === null`, `refunded_at === null`.
    - Asserte `FinancialEvent::count() === 0` (pas de mouvement).

  - **`test_dispute_attendance_rejects_non_owner_face`** (AC #14) :
    - 2 Faces `$f0` (propriétaire) et `$f1` (squatter). Entry à `$f0`.
    - **Pattern try/catch** :
      ```php
      try {
          $this->service->disputeAttendance($f0->entry, $f1->faceUser);
          $this->fail('Expected ValidationException was not thrown');
      } catch (ValidationException $e) {
          // expected
      }
      $this->assertSame(AttendanceStatus::Absent, $f0->entry->fresh()->attendance_status);
      ```

  - **`test_dispute_attendance_rejects_non_face_actor`** (AC #14) :
    - Entry valide.
    - `$this->expectException(ValidationException::class);`
    - Call `disputeAttendance($f0->entry, $this->producerUser)` (Producer essaie de contester).

  - **`test_dispute_attendance_rejects_entry_not_in_absent_state`** (AC #15) :
    - Crée 1 entry, set `attendance_status = pending` (default).
    - `$this->expectException(ValidationException::class);`
    - Call `disputeAttendance($f0->entry, $f0->faceUser)`.
    - **Note** : tester aussi avec `attendance_status = present` (cas hypothétique) et avec `escrow_status = Released` (entry déjà finalisée). Possible de regrouper en 1 test multi-scénario via dataProvider PHPUnit, ou 1 test par scénario (préférence projet : tests isolés).

- [x] 6.5 Tests pour `resolveDispute` (AC #16-#21) — minimum 6 tests :

  - **`test_resolve_dispute_favor_face_releases_to_face_with_correct_metadata`** (AC #16) :
    - Mission `PendingAttendanceValidation` avec 1 entry `Locked + disputed`.
    - Crée admin : `$admin = Admin::factory()->create(); $adminUser = User::factory()->create(['userable_type' => Admin::class, 'userable_id' => $admin->id])`.
    - `Mail::fake();`
    - Call `resolveDispute($f0->entry, DisputeResolutionOutcome::FavorFace, $adminUser)`.
    - Asserte `$f0->entry->fresh()->escrow_status === Released`, `attendance_status === Disputed` (audit préservé, pas changé), `released_at !== null`.
    - Asserte `$f0->faceUser->refresh()->balance === 90000` ; `$this->producerUser->refresh()->balance === 0`.
    - Asserte `assertDatabaseHas('financial_events', ['idempotency_key' => "mission_attendance_escrow_release:{$f0->entry->id}", 'type' => 'escrow_release'])` — la métadonnée doit contenir `'reason' => 'disputed_resolved_face'`. Pattern d'assertion JSON metadata :
      ```php
      $event = FinancialEvent::where('idempotency_key', "mission_attendance_escrow_release:{$f0->entry->id}")->firstOrFail();
      $this->assertSame('disputed_resolved_face', $event->metadata['reason']);
      ```
    - Asserte `$mission->fresh()->status === MissionStatus::Completed` (le fixture ne contient qu'une seule entry — après son release par `resolveDispute`, plus aucune entry `Locked + (pending|absent)` ne subsiste, `tryCompleteIfReady` finalise donc la mission).

  - **`test_resolve_dispute_favor_producer_refunds_with_correct_metadata`** (AC #17) :
    - Idem mais avec `DisputeResolutionOutcome::FavorProducer`.
    - Asserte `$f0->entry->fresh()->escrow_status === Refunded`, `attendance_status === Disputed`, `refunded_at !== null`.
    - Asserte `$this->producerUser->refresh()->balance === 90000` ; `$f0->faceUser->refresh()->balance === 0`.
    - Asserte `FinancialEvent` `idempotency_key` = `"mission_attendance_refund:{$f0->entry->id}"`, `type === 'refund'`, `metadata.reason === 'disputed_resolved_producer'`, `metadata.refund_percentage === 100`.

  - **`test_resolve_dispute_rejects_non_admin_actor`** (AC #18) :
    - `$this->expectException(ValidationException::class);`
    - Call `resolveDispute($f0->entry, FavorFace, $f0->faceUser)` ou `$this->producerUser`.

  - **`test_resolve_dispute_rejects_entry_not_in_disputed_state`** (AC #19) :
    - 1 entry `Locked + absent` (pas disputed).
    - `$this->expectException(ValidationException::class);`
    - Call `resolveDispute($f0->entry, FavorFace, $adminUser)`.

  - **`test_resolve_dispute_is_idempotent_via_state_guard`** (AC #20) :
    - Mission `PendingAttendanceValidation` avec 1 entry `Locked + disputed`.
    - 1er call `resolveDispute(FavorFace)` → success, entry Released.
    - **Pattern try/catch sur le 2ème call** (les assertions de balance/count doivent s'exécuter APRÈS l'exception) :
      ```php
      // 1st call — should succeed
      $this->service->resolveDispute($f0->entry, DisputeResolutionOutcome::FavorFace, $adminUser);
      $this->assertSame(EscrowStatus::Released, $f0->entry->fresh()->escrow_status);

      // 2nd call — should throw because entry is no longer Locked+Disputed
      try {
          $this->service->resolveDispute($f0->entry->fresh(), DisputeResolutionOutcome::FavorFace, $adminUser);
          $this->fail('Expected ValidationException on second resolveDispute call');
      } catch (ValidationException $e) {
          // expected — state guard rejects
      }
      $this->assertSame(90000, $f0->faceUser->refresh()->balance);
      $this->assertSame(1, FinancialEvent::count());
      ```

  - **`test_resolve_dispute_completes_mission_when_last_locked_entry_resolved`** (AC #21) :
    - Mission `PendingAttendanceValidation` avec 3 entries : 1 déjà `Released + present`, 1 déjà `Refunded + absent`, 1 `Locked + disputed`.
    - Call `resolveDispute($f_disputed->entry, FavorFace, $adminUser)`.
    - Asserte mission `Completed` après le call.
    - Asserte notif `mission_completed_producer` envoyée.

- [x] 6.6 Tests pour invariants (AC #22-#23) — minimum 2 tests :

  - **`test_mission_can_complete_with_locked_disputed_entries_remaining`** (AC #23, invariant 5) :
    - Mission Closed avec 2 entries : 1 `pending`, 1 préexistante `Locked + disputed`.
    - Call `markAttendance($mission, [$f_pending->entry->id => 'present'], $producerUser)`.
    - Asserte `$mission->fresh()->status === Completed` (car plus aucune entry `Locked + (pending|absent)` ; l'entry `Locked + disputed` est tolérée par invariant 5).
    - Asserte `$f_disputed->entry->fresh()` est inchangée (`Locked + disputed`).

  - **`test_complete_mission_legacy_path_remains_unchanged`** (AC #22) :
    - Mission Closed avec 1 entry `Locked + pending`.
    - Call `MissionService::completeMission($mission)` (via injection `$this->app->make(MissionService::class)`).
    - Asserte le bridge marche (entry → present → released → mission Completed). Comportement identique à FIX-26.2.
    - **But** : vérifier qu'aucune modification dans cette story ne casse le legacy.

- [x] 6.7 Lancer ciblé : `cd backend && php artisan test --filter=MissionAttendanceServiceTest` → attendu **>= 26 tests verts** (14 markAttendance + 4 disputeAttendance + 6 resolveDispute + 2 invariants = 26).

### Task 7 — Validation non-régression sur les suites existantes (AC: #22)

- [x] 7.1 Lancer ciblé : `cd backend && php artisan test --filter=CompleteMissionTest` → attendu **11/11 verts** (10 existants + 1 ajouté en FIX-26.2, **AUCUNE modification dans cette story**).
- [x] 7.2 Lancer ciblé : `cd backend && php artisan test --filter=MissionAttendanceReleaseFundsTest` → attendu **7/7 verts** (FIX-26.2, AUCUNE modification dans cette story).
- [x] 7.3 Lancer ciblé : `cd backend && php artisan test --filter=AutoReleaseMissionFundsCommandTest` → attendu **1/1 vert** (cron legacy inchangé).
- [x] 7.4 Lancer ciblé : `cd backend && php artisan test --filter=MissionPaymentCandidatureSchemaTest` → attendu **5/5 verts** (la nouvelle colonne `notified_at` étant nullable n'affecte aucune assertion existante).
- [x] 7.5 Lancer la suite complète : `cd backend && php artisan test` → attendu **>= 1951+ tests verts** (baseline FIX-26.2 = 1925, + 26 nouveaux dans `MissionAttendanceServiceTest` = 1951). **AUCUNE RÉGRESSION**.

### Task 8 — PHPStan + Pint final (AC: #22)

- [x] 8.1 Lancer `cd backend && ./vendor/bin/phpstan analyse --no-progress` (project config) → 0 nouvelle erreur. Les 3 erreurs pré-existantes `MissionPaymentService.php` lignes 140 / 207 / 603 documentées en FIX-26.2 restent inchangées (hors scope).
- [x] 8.2 Lancer `cd backend && ./vendor/bin/pint --test` sur les 7 fichiers du scope (chemins relatifs depuis `backend/`) :
  - `app/Services/MissionPaymentService.php`
  - `app/Services/MissionService.php`
  - `app/Services/MissionAttendanceService.php`
  - `app/Enums/DisputeResolutionOutcome.php`
  - `app/Models/MissionPaymentCandidature.php`
  - `database/migrations/2026_04_28_HHMMSS_add_notified_at_to_mission_payment_candidatures_table.php`
  - `tests/Feature/Mission/MissionAttendanceServiceTest.php`
  - Attendu : `{"result":"pass"}`.

### Task 9 — Vérification anti-régression scope strict (AC: #22)

- [x] 9.1 `git status --short` après commit doit lister **exactement 7 fichiers backend modifiés/créés** :
  - **Créés** (4) : `backend/database/migrations/2026_04_28_HHMMSS_add_notified_at_to_mission_payment_candidatures_table.php`, `backend/app/Services/MissionAttendanceService.php`, `backend/app/Enums/DisputeResolutionOutcome.php`, `backend/tests/Feature/Mission/MissionAttendanceServiceTest.php`.
  - **Modifiés** (3) : `backend/app/Services/MissionPaymentService.php` (visibilité `releaseToFace`/`refundToProducer` + paramètre `$reason`), `backend/app/Services/MissionService.php` (visibilité `notifyProducerOnCompletion` + commentaire bridge), `backend/app/Models/MissionPaymentCandidature.php` (`notified_at` ajoutée à `$fillable` + `casts()` + PHPDoc).
- [x] 9.2 Confirmer qu'aucun fichier ailleurs n'est touché : `backend/app/Http/Controllers/`, `backend/app/Http/Requests/`, `backend/routes/`, `backend/app/Listeners/`, `backend/app/Mail/`, `backend/app/Events/`, `backend/resources/views/`, `frontend/`, `backend/database/factories/`, `backend/database/seeders/`.
- [x] 9.3 `grep -rn "MissionAttendanceService" backend/app backend/tests` après commit doit retourner **uniquement** :
  - `MissionAttendanceService.php` (déclaration)
  - `MissionAttendanceServiceTest.php` (tests)
  - `MissionService.php` (commentaire bridge mis à jour, ne mentionne plus FIX-26.3 directement mais peut référer le service par nom dans une explication)
- [x] 9.4 `grep -rn "DisputeResolutionOutcome" backend/app backend/tests` doit retourner **uniquement** : `DisputeResolutionOutcome.php` (déclaration), `MissionAttendanceService.php` (signature + match), `MissionAttendanceServiceTest.php` (tests).
- [x] 9.5 `grep -rn "tryCompleteIfReady\|markAttendance\|disputeAttendance\|resolveDispute" backend/app backend/tests` doit retourner **uniquement** ces 3 fichiers + tests + commentaire bridge (`MissionService.php` ne mentionne plus `MissionAttendanceService takes over`, mais le commentaire de bridge est mis à jour pour pointer FIX-26.4).
- [x] 9.6 `grep -rn "notified_at" backend/app backend/tests backend/database/migrations` après commit doit retourner **uniquement** : la migration `2026_04_28_*_add_notified_at_*.php`, `MissionPaymentCandidature.php` (fillable + cast + PHPDoc), `MissionAttendanceService.php` (set au flip → absent), `MissionAttendanceServiceTest.php` (assertions).

**Note scope post-implémentation** (à inclure dans le commit/PR) :

- ❎ `_bmad-output/implementation-artifacts/sprint-status.yaml` — métadonnée bmad-dev-story workflow (transition `ready-for-dev → in-progress → review`). À inclure dans le commit story.
- ❎ `_bmad-output/implementation-artifacts/fix-26-3-mission-attendance-service.md` — la story file elle-même (Tasks/Subtasks cochées + Dev Agent Record + Status). À inclure dans le commit story.
- ❎ `.codex` (untracked) et `scripts/codex-notify.sh` (untracked) — pré-existants au snapshot session, **ne pas stager**.

Le commit/PR FIX-26.3 doit donc contenir : 7 fichiers backend (4 créés + 3 modifiés) + 2 fichiers métadonnée workflow. Total = 9 fichiers stagés.

### Review Findings

Code review du 2026-04-28 — 3 layers (Blind Hunter, Edge Case Hunter, Acceptance Auditor) + relecture humaine. Triage : 1 décision, 5 patches, 7 défers, 11 dismiss.

**Decision-needed (à arbitrer avant patches)**

- [x] [Review][Decision] Whitelist `MissionStatus::Completed` dans `markAttendance` viole AC #3 mais est requise par AC #11 (idempotence replay) — `backend/app/Services/MissionAttendanceService.php:45,86,119-127`. **Décision D1=A appliquée** : `Completed` reste autorisé pour le replay idempotent, AC #3 est amendé pour exclure `Completed`, la branche conditionnelle `Completed + Pending → throw` a été retirée, et `test_mark_attendance_revalidates_mission_status_under_lock` simule désormais une race vers `Published` (statut réellement invalide) plutôt que `Completed`.

**Patches (à appliquer après décision)**

- [x] [Review][Patch] `userable_type` non re-vérifié sous lock dans `markAttendance` (defense-in-depth dégradée vs. squelette spec Task 5.2) [`backend/app/Services/MissionAttendanceService.php:80`] — résolu via helper `isProducerMissionOwner()` appelé avant transaction et sous lock.
- [x] [Review][Patch] `userable_type` non re-vérifié sous lock dans `disputeAttendance` (defense-in-depth dégradée vs. squelette spec Task 5.3) [`backend/app/Services/MissionAttendanceService.php:179`] — résolu via helper `isFaceEntryOwner()` appelé avant transaction et sous lock.
- [x] [Review][Patch] Validation manquante des clés `$entryIdToStatus` — `(int) "abc"` se coerce silencieusement à 0 et retourne `RuntimeException` 500 au lieu de `ValidationException` 422 ; ajouter check explicite que chaque clé est un entier positif avant la boucle de lock [`backend/app/Services/MissionAttendanceService.php:103-108`] — résolu par validation `is_int($entryId) && $entryId > 0` + test `test_mark_attendance_rejects_non_integer_entry_keys`.
- [x] [Review][Patch] Sortir les entries par `entry_id` ascendant avant lock acquisition pour ordre déterministe (élimine soft-contention si un Producer émet 2 calls concurrents avec même set d'entries) [`backend/app/Services/MissionAttendanceService.php:103`] — résolu via `ksort($entryIdToStatus, SORT_NUMERIC)` après validation.
- [x] [Review][Patch] Couverture de test manquante : `Log::info` du `disputeAttendance` (audit invariant 9) non verrouillé — ajouter `Log::shouldReceive('info')` ou `Log::spy()` dans `test_dispute_attendance_flips_absent_to_disputed` [`backend/tests/Feature/Mission/MissionAttendanceServiceTest.php:410-426`] — résolu via expectation `Log::shouldReceive('info')->once()` dans le test de dispute.

**Deferred (réels mais hors scope ou pré-existants)**

- [x] [Review][Defer] `releaseToFace`/`refundToProducer` promus public sans guard runtime `DB::transactionLevel() > 0` [`backend/app/Services/MissionPaymentService.php:723,815`] — deferred, contrat documenté inchangé, MissionAttendanceService respecte la convention
- [x] [Review][Defer] `markAttendance` retourne `Mission` via `fresh()` sans relations eager-loaded [`backend/app/Services/MissionAttendanceService.php:161`] — deferred, responsabilité du caller (controller HTTP arrive en FIX-26.4)
- [x] [Review][Defer] `tryCompleteIfReady` early-return silencieux sur `payment->entries()->exists() === false` — devrait `Log::warning` en cas d'anomalie data [`backend/app/Services/MissionAttendanceService.php:279-281`] — deferred, instrumentation défensive non requise par spec
- [x] [Review][Defer] `MissionCompletedMail` queued par `releaseToFace` quand appelé depuis `resolveDispute` (FavorFace) — Face reçoit un mail « mission terminée » alors que la mission est complétée depuis des semaines [`backend/app/Services/MissionPaymentService.php:790-796`] — deferred, UX à traiter dans FIX-26.5/26.8
- [x] [Review][Defer] Migration `notified_at` sans index — la cron FIX-26.6 `settle-disputed-attendance` fera un full-scan sur `(attendance_status, notified_at)` [`backend/database/migrations/2026_04_28_130000_add_notified_at_to_mission_payment_candidatures_table.php:14`] — deferred, ajouter l'index dans FIX-26.6 où il sera consommé
- [x] [Review][Defer] Race possible : admin `resolveDispute` et Producer `markAttendance` concurrents peuvent tous deux appeler `tryCompleteIfReady` → double notification `mission_completed_producer` (Notification table sans contrainte UNIQUE sur user_id+type+mission_id) [`backend/app/Services/MissionAttendanceService.php:221-262`] — deferred, ajouter `Mission::lockForUpdate()` dans `resolveDispute` (rare en pratique : admin agit a posteriori, fenêtre étroite)
- [x] [Review][Defer] `markAttendance` flippe mission `Closed → PendingAttendanceValidation` ligne 150-152 même quand toutes les entries du batch sont déjà tranchées (aucune entry réellement modifiée) — état atteignable uniquement via anomalie data [`backend/app/Services/MissionAttendanceService.php:150-152`] — deferred, edge unreachable en flux normal

## Dev Notes

### Interprétation de l'invariant 9 epic (audit `financial_events`)

L'epic `_bmad-output/planning-artifacts/epics-postlaunch-fixes-10.md:73-74` énonce : « Toutes les opérations financières (release, refund, mark present/absent, contestation) doivent être idempotentes et journalisées dans `financial_events` avec une idempotency key dérivée de l'entry et de l'opération. »

Interprétation retenue par cette story (alignée avec le design async settlement) :

- **`mark present`** (markAttendance flip → present + immediate `releaseToFace`) → opération financière directe → **JOURNALISÉE** : 1 `FinancialEvent` `EscrowRelease` créé inline avec idempotency_key déterministe `mission_attendance_escrow_release:{entry_id}` et `metadata.reason = 'attendance_present'`.
- **`mark absent`** (markAttendance flip → absent, **PAS** de mouvement financier immédiat) → opération financière différée → journalisée **plus tard** par FIX-26.6 cron `settle-disputed-attendance` quand le refund effectif aura lieu (`FinancialEvent` `Refund` avec idempotency_key `mission_attendance_refund:{entry_id}` et `metadata.reason = 'attendance_absent'`). **Le timestamp stable du flip vers absent est préservé via la nouvelle colonne `notified_at`** (cf. Task 1) — c'est le contrat d'audit durable de cette story pour la transition « mark absent ».
- **`contestation`** (disputeAttendance flip → disputed, **PAS** de mouvement financier) → opération non-financière (l'entry reste `Locked`, escrow_status inchangé) → **PAS de FinancialEvent créé**. L'audit est garanti par : (a) la transition `attendance_status = disputed` (state machine forward-only depuis `absent`, guard `if !== absent` empêche la régression), (b) le timestamp `notified_at` stable et non-écrasé (inchangé par disputeAttendance), (c) le `Log::info` synchrone. Quand l'admin résout via `resolveDispute`, le mouvement financier RÉEL (release ou refund) crée alors le `FinancialEvent` final avec `metadata.reason = 'disputed_resolved_*'` — fermeture du cycle d'audit.
- **`release` / `refund` (resolveDispute admin)** → opération financière directe → **JOURNALISÉE** inline via les helpers `releaseToFace`/`refundToProducer` (FIX-26.2) avec custom `$reason`.

**Conclusion** : l'invariant 9 est respecté pour toute opération qui MEUT physiquement de l'argent. Pour les opérations qui ne meuvent pas d'argent (mark absent en attente de cron, contestation en attente d'admin), l'audit est assuré par le couple `(attendance_status forward-only state machine, notified_at timestamp immutable)` + log synchrone, ET le `FinancialEvent` final est créé au moment du mouvement réel. Ce design préserve la sémantique de `financial_events` (table d'événements **financiers**) sans diluer son contrat avec des entrées sans amount.

Si un audit plus strict (« every state flip writes an immutable trace ») devient nécessaire, story dédiée pour ajouter une table `mission_attendance_audit_logs` (entry_id, from_status, to_status, by_user_id, created_at) — explicitement HORS scope FIX-26.3.

### Patterns clés

#### Service injection

```php
class MissionAttendanceService
{
    public function __construct(
        private readonly MissionPaymentService $missionPaymentService,
        private readonly MissionService $missionService,
    ) {}
}
```

Aucun binding manuel requis dans `AppServiceProvider` — Laravel auto-resolve via le container.

#### Authorization helpers

- **Producer = mission owner** : `$actor->userable_type === Producer::class && $actor->userable_id === $mission->producer_id`
- **Face = entry owner** : `$actor->userable_type === Face::class && $actor->userable_id === $entry->face_id`
- **Admin** : `$admin->userable_type === Admin::class`

Pattern miroir `BookingService::reportNoShow:305` (`if ($reporter->id !== $lockedBooking->producer_id)`).

#### Idempotence par filtre WHERE

`markAttendance` : la condition `if ($entry->escrow_status !== Locked || $entry->attendance_status !== Pending) continue;` permet de re-jouer le batch sans effets secondaires. Les entries déjà tranchées sont silencieusement ignorées.

#### Respect du contrat `MissionPaymentService::releaseToFace` / `refundToProducer`

Les helpers (rendus publics par cette story) restent documentés « MUST be called inside an existing DB::transaction() ». `MissionAttendanceService::markAttendance` / `resolveDispute` les appellent toujours INSIDE leur propre `DB::transaction(fn () => ...)`, satisfaisant le contrat.

### Squelette FixturePattern multi-Faces (test setup)

Le helper `createPaidMissionWithFaces(int $faceCount)` (cf. Task 5.2) reproduit le pattern de FIX-26.2 (`MissionAttendanceReleaseFundsTest`) localement. Variants :

```php
// Cas standard : 3 entries en pending
[$mission, $faces] = $this->createPaidMissionWithFaces(3);
$f0 = $faces[0]; // ['candidature' => ..., 'entry' => ..., 'faceUser' => ..., 'face' => ...]
$f1 = $faces[1];
$f2 = $faces[2];

// Cas avec entry pré-disputée
[$mission, $faces] = $this->createPaidMissionWithFaces(2);
$faces[1]['entry']->update(['attendance_status' => AttendanceStatus::Disputed]);

// Cas mission déjà en PendingAttendanceValidation
[$mission, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);
```

Note : la factory `Mission::factory()->closed()` ne couvre pas `PendingAttendanceValidation` (pas de `pendingAttendanceValidation()` state factory dans `MissionFactory.php`). Le helper doit créer en `Closed` puis bumper le status manuellement avec `$mission->update(['status' => MissionStatus::PendingAttendanceValidation])`.

### Squelette de test resolveDispute avec Admin

```php
$admin = \App\Models\Admin::factory()->create();
$adminUser = User::factory()->create([
    'userable_type' => \App\Models\Admin::class,
    'userable_id' => $admin->id,
]);

[$mission, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);
$faces[0]['entry']->update(['attendance_status' => AttendanceStatus::Disputed]);

$this->service->resolveDispute(
    $faces[0]['entry'],
    DisputeResolutionOutcome::FavorFace,
    $adminUser,
);
```

Note : vérifier que `Admin::factory()` existe — sinon créer avec `Admin::factory()->create()`. Pattern miroir des tests admin existants (cf. `backend/tests/Feature/Admin/`).

### Idempotency keys (rappel FIX-26.2)

Pour une entry id=42 :
- Release via markAttendance : `mission_attendance_escrow_release:42`, `metadata.reason = 'attendance_present'`
- Refund via markAttendance : NE SE PRODUIT PAS dans cette story (absents stay Locked).
- Release via resolveDispute (favor face) : `mission_attendance_escrow_release:42`, `metadata.reason = 'disputed_resolved_face'`
- Refund via resolveDispute (favor producer) : `mission_attendance_refund:42`, `metadata.reason = 'disputed_resolved_producer'`

Le `metadata.reason` distingue les chemins d'entrée pour le reporting financier ultérieur.

### Références complètes (vérifiées via Read direct)

- Epic source : `_bmad-output/planning-artifacts/epics-postlaunch-fixes-10.md` § FIX-26.3 (lignes 184-205) + § « Nouveaux invariants financiers » (lignes 54-77) + § « Décisions produit » (lignes 38-48).
- Story précédente FIX-26.2 (releaseFunds routing) : `_bmad-output/implementation-artifacts/fix-26-2-release-funds-routing-by-attendance.md` § Completion Notes (refactor in-place + bridge backward-compat).
- Story précédente FIX-26.1 (schéma) : `_bmad-output/implementation-artifacts/fix-26-1-attendance-status-schema.md` § Completion Notes (enum + cast + migration).
- `MissionPaymentService::releaseToFace` actuelle (à promouvoir public) : `backend/app/Services/MissionPaymentService.php:723-802`.
- `MissionPaymentService::refundToProducer` actuelle (à promouvoir public) : `backend/app/Services/MissionPaymentService.php:812-848`.
- `MissionService::completeMission` (bridge legacy à laisser intact) : `backend/app/Services/MissionService.php:235-269`.
- `MissionService::notifyProducerOnCompletion` (à promouvoir public) : `backend/app/Services/MissionService.php:271-299`.
- `BookingService::reportNoShow` (pattern de référence pour authorization service-level) : `backend/app/Services/BookingService.php:285-359`.
- `WalletService::creditDirect` (utilisé indirectement via les helpers) : `backend/app/Services/WalletService.php:37-49`.
- `RecordsFinancialEvent::recordMissionAttendanceFinancialEvent` (utilisée dans les helpers `releaseToFace`/`refundToProducer`) : `backend/app/Concerns/RecordsFinancialEvent.php:53-74`.
- `AttendanceStatus` enum : `backend/app/Enums/AttendanceStatus.php:1-13`.
- `MissionStatus` enum (avec `PendingAttendanceValidation` ligne 13) : `backend/app/Enums/MissionStatus.php:1-40`.
- `EscrowStatus` enum (style minimaliste de référence pour `DisputeResolutionOutcome`) : `backend/app/Enums/EscrowStatus.php`.
- `MissionPaymentCandidature` model : `backend/app/Models/MissionPaymentCandidature.php:1-65`.
- `MissionPolicy` (pas modifié dans cette story — l'authorization est faite au service-level) : `backend/app/Policies/MissionPolicy.php:42-58`.
- Helper fixtures de référence : `backend/tests/Feature/Mission/CompleteMissionTest.php:66-98` (`createPaidSelection`) + `backend/tests/Feature/Mission/MissionAttendanceReleaseFundsTest.php` (helper `createPaidMissionWithFaces` à reproduire localement).
- Memory `reference_test_db_setup` : DB test `weact_test` opérationnelle, suite ~85-95s (extension prévue +20 tests = ~95-100s).
- Memory `feedback_accents_francais` : libellés FR de l'exception `ValidationException` doivent porter les accents corrects (« n'est », « contestée », « administrateur »).
- Memory `feedback_story_skeletons_must_parse` : tous les skeletons PHP de cette story ont été mental-parse pour validité syntaxique avant ready-for-dev.

### Hors scope explicitement

Les éléments suivants sont **explicitement hors scope** de FIX-26.3 et seront livrés dans des stories ultérieures :

- ❌ **Endpoints HTTP Producer** (`GET /api/v1/producer/missions/{uuid}/attendance-form`, `POST /api/v1/producer/missions/{uuid}/validate-attendance`) → FIX-26.4. Les FormRequest, contrôleurs, routes seront créés à ce moment-là.
- ❌ **Retrait du bridge backward-compat dans `MissionService::completeMission`** → FIX-26.4. Le bridge est conservé tant que le legacy `/complete` endpoint existe.
- ❌ **Notification email + in-app `FaceMarkedAbsentMail`** → FIX-26.5. La méthode `markAttendance` ne dispatch ni event ni mail dans cette story.
- ❌ **Endpoint Face de contestation** (`POST /api/v1/face/missions/{uuid}/dispute-attendance`) → FIX-26.5. La méthode `disputeAttendance` du service est testée directement, pas via HTTP.
- ✅ ~~**Colonne `notified_at` sur `mission_payment_candidatures`** → FIX-26.5~~ — **DÉCISION RÉVISÉE suite review** : la colonne est ajoutée DANS CETTE STORY (cf. § Architecture retenue + Task 1) car FIX-26.3 est l'écrivain de la transition `→ absent`. La consommation (window 72h) reste FIX-26.5 (endpoint dispute) et FIX-26.6 (cron settle).
- ❌ **Notification interne admin** sur dispute (« nouvelle entry en litige ») → FIX-26.8. `disputeAttendance` log seulement (`Log::info`).
- ❌ **Crons `auto-validate-attendance` et `settle-disputed-attendance`** → FIX-26.6.
- ❌ **UI Vue `AttendanceValidationView.vue`** → FIX-26.7.
- ❌ **Endpoint admin résolution litiges** (`GET /api/v1/admin/attendance-disputes`, `POST .../resolve`) + page admin Vue → FIX-26.8. La méthode `resolveDispute` du service est testée directement.
- ❌ **Commande `missions:legacy-attendance-settlement` avec date pivot** → FIX-26.9.
- ❌ **Désactivation/repurpose du cron `missions:auto-release-funds`** → FIX-26.10.
- ❌ **Modification du `match` exhaustif `MissionPaymentService::releaseFunds:710-713`** : non-applicable — le `match` reste tel quel, FIX-26.3 contourne `releaseFunds` en appelant directement les helpers `releaseToFace`/`refundToProducer` avec un reason custom.
- ❌ **Refactor de `releaseFunds` pour ne traiter que les `present`** : rejeté (cf. § Décisions retenues — forke l'API).
- ❌ **Ajout d'une méthode publique `releasePresentEntries(Mission)` sur `MissionPaymentService`** : rejeté (cf. § Décisions retenues — promotion à public des helpers existants est plus économe).
- ❌ **Ajout d'une factory `MissionPaymentCandidature::factory()`** : non-applicable. Pattern d'instanciation directe via `MissionPaymentCandidature::create([...])` conservé.
- ❌ **Modification de `MissionPolicy`** : non-applicable. L'authorization du service est faite inline dans chaque méthode (`actor->userable_type === ...`). Si une politique unifiée est désirée plus tard, story dédiée.
- ❌ **Modification de `CompleteMissionTest`** : non-applicable. Le bridge legacy est intact, le test reste vert sans changement.
- ❌ **Modification de `MissionAttendanceReleaseFundsTest`** : non-applicable. `releaseFunds` n'est pas touchée — le test reste vert.

### Risque de régression et garde-fous

**Risque MOYEN** vu la nature financière (releaseToFace + refundToProducer désormais exposés publiquement). Garde-fous :

1. **Default reason préservé** : `releaseToFace($entry, $mission)` sans 3ème argument utilise `'attendance_present'` (default), garantissant que `MissionPaymentService::releaseFunds` continue de produire des `FinancialEvent` avec `metadata.reason = 'attendance_present'` exactement comme avant FIX-26.3.
2. **MissionAttendanceServiceTest** couvre 20+ cas dont les invariants 5 (Mission Completed peut contenir Locked+disputed), authorization (4 angles : Producer non-owner, Face non-owner, Admin requis, état entry incompatible) et idempotence (markAttendance + resolveDispute).
3. **Aucune modification de la logique releaseFunds** ni du bridge `completeMission` — les paths legacy sont préservés intacts.
4. **DB::transaction wrap** sur les 3 méthodes publiques garantit l'atomicité et permet à `releaseToFace`/`refundToProducer` (qui exigent un transaction parent) de fonctionner correctement.
5. **lockForUpdate sur Mission ET sur chaque entry** dans `markAttendance` — protection contre les races (Producer multi-tab + cron concurrent).
6. **Style `match($outcome)` exhaustif sur DisputeResolutionOutcome** dans `resolveDispute` — un futur case (ex: `FavorAdmin`) déclencherait `UnhandledMatchError` (défensif).

### Self-review obligatoire avant `ready-for-dev`

Cf. CLAUDE.md § « Story Files: Implementation-Ready Discipline » :

- [x] Chaque ligne/fichier/méthode citée a été ouverte (Read) ou greppée pour confirmation : `MissionPaymentService.php:710-713,723-802,812-848,883-904`, `MissionService.php:235-269,271-299`, `BookingService.php:285-359`, `WalletService.php:37-49`, `RecordsFinancialEvent.php:53-74`, `AttendanceStatus.php:1-13`, `MissionStatus.php:1-40`, `MissionPaymentCandidature.php:1-65` (target pour patch fillable + casts + PHPDoc), `MissionPolicy.php:42-58`, `AutoReleaseMissionFundsCommand.php`, migration de référence FIX-26.1 `2026_04_27_120000_add_attendance_status_to_mission_payment_candidatures_table.php` (target pour pattern strict miroir de la nouvelle migration `notified_at`).
- [x] Chaque test multi-entités a son fixture squelette concret en Dev Notes (cf. § « Squelette FixturePattern » + Task 5.2).
- [x] Chaque nouveau lookup est justifié face aux données déjà en scope (`tryCompleteIfReady` lit `$mission->payment->entries()` directement ; `disputeAttendance` charge `entry.missionPayment` via `loadMissing` ; `resolveDispute` charge `entry.missionPayment.mission`).
- [x] Chaque flow adjacent (FIX-26.4 endpoint, FIX-26.5 notification, FIX-26.6 crons, FIX-26.8 admin) est explicitement listé en non-scope.
- [x] Chaque test à modifier est désigné par nom (aucun — cette story n'augmente pas de tests existants, elle CRÉE un nouveau fichier de test).
- [x] Chaque choix de design ambigu a été tranché avec raison (settlement async vs sync ; promotion public des helpers vs duplication ; transition mission auto vs cron ; bridge laissé intact vs retiré ; admin notif déférée FIX-26.8).
- [x] Chaque skeleton code (markAttendance, disputeAttendance, resolveDispute, tryCompleteIfReady, helper test) appelle des méthodes/colonnes/types vérifiés dans le codebase actuel post-FIX-26.2.
- [x] Chaque skeleton implémente la règle de l'AC qu'il est censé démontrer (markAttendance crédite seulement présents et stagne à PendingAttendanceValidation si absents restants ; resolveDispute laisse attendance_status=disputed pour audit ; tryCompleteIfReady filtre par Locked + (pending|absent) excluant disputed).
- [x] Chaque règle de vérification (grep, count, regex Task 9) est compatible avec les conventions choisies ailleurs dans la même story.
- [x] Chaque count numérique a été recompté : « 7 fichiers » = 4 créés + 3 modifiés ; « 26 tests » = 14 markAttendance + 4 disputeAttendance + 6 resolveDispute + 2 invariants (post review patch : ajout du test de validation des clés non entières) ; « 3 méthodes publiques » = markAttendance, disputeAttendance, resolveDispute ; « 23 ACs » = 12 Bloc A markAttendance + 3 Bloc B disputeAttendance + 6 Bloc C resolveDispute + 2 Bloc D invariants.
- [x] Chaque snippet PHP a été mental-parsé pour validité syntaxique (cf. memory `feedback_story_skeletons_must_parse`) — `match` retourne via attribution implicite valid, `loadMissing` chaîné OK, `lockForUpdate` chaining OK, `DB::transaction(fn () => ...)` retourne `Mission`/`MissionPaymentCandidature` OK.

### Project Structure Notes

- Alignement total avec la structure existante : services dans `backend/app/Services/`, enums dans `backend/app/Enums/`, tests Feature dans `backend/tests/Feature/Mission/`.
- Naming `MissionAttendanceService` cohérent avec les autres services Mission (`MissionService`, `MissionPaymentService`).
- Naming `DisputeResolutionOutcome` cohérent avec les autres enums (PascalCase, suffix optionnel selon sémantique — pas de `Status` car ce n'est pas un état mais un outcome).
- Naming `MissionAttendanceServiceTest` cohérent avec le naming des autres tests Feature Mission (`CompleteMissionTest`, `MissionAttendanceReleaseFundsTest`).
- Aucune nouvelle dépendance Composer ni npm. Une migration backend ajoutée (colonne `notified_at` sur `mission_payment_candidatures` — cf. Task 1, décision révisée en review-r1). Aucune route. Aucun front.

## Dev Agent Record

### Agent Model Used

GPT-5 Codex

### Debug Log References

- 2026-04-28: `mgrep` code retrieval unavailable due quota 429; used direct file inspection (`sed`/`rg`) after recording the blocker.
- 2026-04-28: Initial migration command inside sandbox could not connect to local MySQL; reran with approved escalation for local `weact_test` access.
- 2026-04-28: Parallel regression filters collided on shared MySQL `RefreshDatabase`; reset `weact_test` with `php artisan migrate:fresh --env=testing` and reran required filters sequentially.
- 2026-04-28: PHPStan local worker socket needed escalation; project analysis later passed cleanly with `./vendor/bin/phpstan analyse --no-progress`.

### Completion Notes List

- Added `notified_at` to `mission_payment_candidatures` and model metadata/casts so absent notifications have a stable timestamp.
- Added `DisputeResolutionOutcome` and `MissionAttendanceService` with `markAttendance`, `disputeAttendance`, `resolveDispute`, DB transactions, row locks, idempotent replay guards, and `Closed → PendingAttendanceValidation → Completed` transitions.
- Promoted `MissionPaymentService::releaseToFace` and `refundToProducer` to public helpers with backward-compatible default reasons and custom dispute-resolution reasons.
- Promoted `MissionService::notifyProducerOnCompletion` to public and updated the legacy bridge comment to defer removal to FIX-26.4.
- Added `MissionAttendanceServiceTest` with 26 tests covering producer marking, invalid entry-key validation, absent-notified timestamp stability, Face dispute audit logging, admin dispute resolution, idempotence, and legacy completion non-regression.
- Resolved review D1 with option A: keep `Completed` replay support for idempotence, amend AC #3, remove the hybrid `Completed + Pending` branch, and make the race test use `Published` as the invalid under-lock status.
- Resolved review patches P1-P5: ownership type rechecks under lock, positive integer entry key validation, deterministic lock ordering, and `Log::info` dispute audit coverage.
- Validation completed: migration apply/rollback/apply on `weact_test`; targeted regression filters; PHPStan project analysis; Pint scoped check; full backend suite `1951 passed (8856 assertions)`.

### File List

- backend/app/Enums/DisputeResolutionOutcome.php
- backend/app/Models/MissionPaymentCandidature.php
- backend/app/Services/MissionAttendanceService.php
- backend/app/Services/MissionPaymentService.php
- backend/app/Services/MissionService.php
- backend/database/migrations/2026_04_28_130000_add_notified_at_to_mission_payment_candidatures_table.php
- backend/tests/Feature/Mission/MissionAttendanceServiceTest.php
- _bmad-output/implementation-artifacts/fix-26-3-mission-attendance-service.md
- _bmad-output/implementation-artifacts/sprint-status.yaml

### Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-04-28 | Implemented FIX-26.3: MissionAttendanceService, dispute outcome enum, notified_at migration/model support, public payment/notification helpers, 25 service tests, and validation gates. Story status moved to review. | GPT-5 Codex |
| 2026-04-28 | Addressed code review findings: D1=A documented, P1-P5 patched, MissionAttendanceServiceTest expanded to 26 tests. | GPT-5 Codex |
| 2026-04-28 | Story file créée par bmad-create-story workflow (claude-opus-4-7). Audit codebase exhaustif post-FIX-26.2 : `MissionAttendanceService` n'existe pas (seul commentaire `MissionService.php:249`), `releaseToFace`/`refundToProducer` actuels sont private, `notifyProducerOnCompletion` actuel est private. Design retenu : settlement asynchrone des absents (interprétation 2 de l'epic), promotion à public des 3 helpers FIX-26.2 (avec paramètre optionnel `$reason` pour les 2 helpers MissionPaymentService), nouveau service avec 3 méthodes publiques (markAttendance, disputeAttendance, resolveDispute) + 1 helper privé (tryCompleteIfReady), nouveau enum `DisputeResolutionOutcome` (FavorFace, FavorProducer), bridge backward-compat dans `completeMission` LAISSÉ INTACT (retrait reporté à FIX-26.4), commentaire de bridge mis à jour pour pointer FIX-26.4. Scope strict 5 fichiers = 3 créés + 2 modifiés. Non-scope 14 items énumérés (FIX-26.4..10, notif admin différée, colonne notified_at différée, modif MissionPolicy hors-scope, etc.). | bmad-create-story |
| 2026-04-28 | 4 patches review-r1 appliqués suite review LLM externe (5 findings traités) : (1) **bloquant — markAttendance pouvait compléter mission sans paiement/entries** : ajout guards explicites pre-transaction `payment instanceof MissionPayment && payment->status === Paid`, input non-vide, ET re-validation sous `lockForUpdate` à l'intérieur de la transaction (race condition defense) ; tryCompleteIfReady durci avec early-return si payment === null (ne plus auto-compléter par mégarde) ; ajout ACs #6 (no-paid-payment rejette), #7 (empty input rejette), #8 (race-revalidation), tests dédiés correspondants. (2) **bloquant — validation status mission faite avant lock puis non revalidée** : guard de status mission re-vérifié sous lock dans le transaction body (cf. squelette markAttendance) ; test `test_mark_attendance_revalidates_mission_status_under_lock` ajouté pour verrouiller le comportement. (3) **major — contradiction invariant 9 epic** : ajout d'une section dédiée Dev Notes « Interprétation de l'invariant 9 epic » qui clarifie que les opérations financières directes (release/refund) sont journalisées inline dans `financial_events`, tandis que les flips d'état non-financiers (mark absent en attente de cron, contestation en attente d'admin) sont audités via la combinaison `attendance_status` (state machine forward-only) + nouvelle colonne `notified_at` (timestamp immutable) + Log::info synchrone ; la table `financial_events` préserve sa sémantique stricte (table d'événements **financiers** avec amount). (4) **major — point de départ des 72h non stable** : nouvelle colonne `notified_at` (timestamp nullable) ajoutée à `mission_payment_candidatures` via migration `2026_04_28_HHMMSS_add_notified_at_*` (Task 1 NEW) + patch model `MissionPaymentCandidature` (fillable + casts + PHPDoc) ; markAttendance set `notified_at = now()` au flip → absent (1ère fois seulement, jamais écrasé) ; AC #12 + test `test_mark_attendance_sets_notified_at_for_absent_and_does_not_overwrite_on_idempotent_call` ajoutés. (5) **medium — trace d'auto-correction « wait... » dans test #6** : nettoyée, le test renommé `test_mark_attendance_skips_already_tranched_entries` (AC #9) avec assertion claire `Completed` (sans monologue). Renumérotation : ACs 8 → 11 (Bloc A) ; Bloc B 12-15 → 13-15 (3 ACs, le « 2 fois disputeAttendance » devient implicite via parenthèse) ; Bloc C 13-18 → 16-21 ; Bloc D 19-20 → 22-23. Tasks renumérotées 1-8 → 1-9 (nouveau Task 1 = migration + model patch). Scope mis à jour : 5 fichiers → 7 fichiers (4 créés + 3 modifiés ; ajout migration + MissionPaymentCandidature.php aux modifiés). Nombre de tests attendu : ~20 → ~24 (12 markAttendance + 4 disputeAttendance + 6 resolveDispute + 2 invariants). Suite complète passée de baseline FIX-26.2 + ~20 = ~1945 → baseline + ~24 = ~1949. | review feedback round 1 |
| 2026-04-28 | 6 patches review-r2 appliqués suite review LLM externe round 2 (6 findings traités) : (1) **bloquant — assertions post-`expectException` PHPUnit jamais exécutées** : 5 tests réécrits en pattern `try / fail / catch ValidationException` pour permettre aux assertions d'état post-exception de tourner — `test_mark_attendance_rejects_non_owner_actor` (AC #2), `test_mark_attendance_rejects_mission_without_paid_payment` (AC #6), `test_mark_attendance_rejects_empty_input` (AC #7), `test_mark_attendance_revalidates_mission_status_under_lock` (AC #8), `test_dispute_attendance_rejects_non_owner_face` (AC #14), `test_resolve_dispute_is_idempotent_via_state_guard` (AC #20). (2) **bloquant — import manquant `MissionCompletedMail`** : ajout `use App\Mail\MissionCompletedMail;` dans Task 6.1 imports (le test AC #1 utilise `Mail::assertQueued(MissionCompletedMail::class, 2)`). (3) **major — chemins de commandes incohérents (`backend/...` vs `app/...`)** : normalisation systémique en `cd backend && <command>` avec chemins relatifs `app/...`/`tests/...`/`database/...`/`vendor/...` partout (Tasks 1.3-1.5, 2.3-2.4, 3.3-3.4, 4.3, 5.6-5.8, 6.7, 7.1-7.5, 8.1-8.2). (4) **major — `disputeAttendance` ne re-valide pas l'ownership Face sous lock** : ajout d'un re-check `$actor->userable_id !== $lockedEntry->face_id` à l'intérieur de la transaction (defense-in-depth, symétrique avec le guard d'état). Mêmre patch appliqué à `markAttendance` pour symétrie (re-check producer ownership contre `$lockedMission->producer_id`). (5) **medium — AC #9 et test ne couvraient pas le même scénario** : nouveau test dédié `test_mark_attendance_replays_batch_on_already_tranched_entries_is_no_op` aligné sur AC #9 (3 entries préexistantes present/absent/disputed, batch replay → no-op, mission stays NOT-Completed) ; l'ancien test renommé `test_mark_attendance_completes_mission_with_locked_disputed_present` et clarifié comme couverture supplémentaire de l'invariant 5 via `markAttendance` (complément à `test_mission_can_complete_with_locked_disputed_entries_remaining` AC #23 qui démarre de Closed). (6) **medium — `tryCompleteIfReady` n'enforce pas explicitement « au moins une entry »** : ajout du guard `if (! $mission->payment->entries()->exists()) return;` avec commentaire dédié, alignant le code sur le contrat documenté. Compteur tests : 24 → 25 (13 markAttendance + 4 + 6 + 2). Aucun nouvel AC ajouté (le test AC #9 nouveau utilise un AC existant, et le défense ownership sous lock n'est pas user-facing). | review feedback round 2 |
| 2026-04-28 | 2 patches review-r3 appliqués suite review LLM externe round 3 (2 findings résiduels traités) : (1) **medium — la migration ne forçait pas explicitement l'environnement test** : Task 1.3 enrichie avec `--env=testing` sur les 3 commandes (`migrate`, `migrate:rollback --step=1`, `migrate`) pour cibler explicitement `weact_test` et éviter de toucher la DB dev locale par mégarde ; commentaire d'intention ajouté. (2) **mineur — contradiction résiduelle « Aucune migration » dans Project Structure Notes** : section corrigée — remplacement de `Aucune migration` par `Une migration backend ajoutée (colonne notified_at sur mission_payment_candidatures — cf. Task 1, décision révisée en review-r1)`, alignée avec le scope effectif depuis la décision review-r1 de migrer la colonne dans cette story. Aucun changement de scope, aucune renumérotation, aucun nouveau test. | review feedback round 3 |
