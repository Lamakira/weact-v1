# Migration UUID : Documentation complète pour review

## Objectif

Remplacer les IDs numériques séquentiels dans les URLs par des UUIDs non-devinables pour empêcher l'énumération des ressources. L'autorisation était déjà solide (policies + checks manuels), mais les IDs séquentiels révélaient des métriques business et facilitaient le scraping.

## Décision d'architecture

**Colonne `uuid` séparée** au lieu de `HasUuids` (qui remplace la PK) :
- Les PKs integer sont conservées pour les JOINs/FKs (performance MySQL)
- Le UUID sert uniquement au routage externe (route model binding)
- Le champ JSON `"id"` de l'API contient maintenant le UUID (string) au lieu de l'entier

## Modèles concernés (12)

Booking, Mission, Candidature, Conversation, Experience, FacePhoto, Notification, Face, Producer, Article, Admin, WithdrawalRequest

## Modèles NON concernés

User, Message, BookingMessage, BookingRating, Rating, WalletTransaction, EscrowTransaction, FinancialEvent, MissionPayment, MissionPaymentCandidature, FedapayWebhookEvent, Report

---

## Fichiers créés (6)

### Backend

| Fichier | Description |
|---------|-------------|
| `backend/app/Concerns/HasRouteUuid.php` | Trait qui auto-génère un UUID sur `creating`, override `getRouteKeyName()` pour retourner `'uuid'` et garde un fallback de résolution par ID numérique legacy |
| `backend/app/Console/Commands/BackfillUuidsCommand.php` | Commande artisan pour backfill les UUIDs sur les records existants (par chunks de 500) |
| `backend/app/Console/Commands/BackfillProducerSlugsCommand.php` | Commande pour backfill les slugs producers depuis `display_name` |
| `backend/database/migrations/2026_04_09_100000_add_uuid_to_route_bound_tables.php` | Ajoute colonne `uuid` nullable + unique à 12 tables |
| `backend/database/migrations/2026_04_09_100001_add_slug_to_producers_table.php` | Ajoute colonne `slug` nullable + unique à `producers` et backfill les slugs existants |
| `backend/database/migrations/2026_04_09_100002_make_uuid_columns_not_nullable.php` | Backfill les `uuid` manquants puis rend les colonnes `uuid` NOT NULL pendant le `migrate` normal |

---

## Fichiers modifiés — Backend

### Modèles (12 fichiers)

Ajout de `use HasRouteUuid;` dans chaque modèle :

| Fichier | Changement spécifique |
|---------|----------------------|
| `backend/app/Models/Booking.php` | +trait |
| `backend/app/Models/Mission.php` | +trait |
| `backend/app/Models/Candidature.php` | +trait |
| `backend/app/Models/Conversation.php` | +trait |
| `backend/app/Models/Experience.php` | +trait |
| `backend/app/Models/FacePhoto.php` | +trait |
| `backend/app/Models/Notification.php` | +trait |
| `backend/app/Models/Face.php` | +trait |
| `backend/app/Models/Producer.php` | +trait + `boot()` method pour auto-génération slug sur `creating`/`updating` |
| `backend/app/Models/Article.php` | +trait |
| `backend/app/Models/Admin.php` | +trait |
| `backend/app/Models/WithdrawalRequest.php` | +trait |

### Factories (11 fichiers)

Ajout de `'uuid' => fake()->uuid()` dans chaque factory definition :

- `BookingFactory.php`, `MissionFactory.php`, `CandidatureFactory.php`, `ConversationFactory.php`
- `ExperienceFactory.php`, `FacePhotoFactory.php`, `FaceFactory.php`
- `ProducerFactory.php` (+`'slug' => fake()->unique()->slug(3)`)
- `ArticleFactory.php`, `AdminFactory.php`, `WithdrawalRequestFactory.php`

### Resources API (23+ fichiers)

Changement `'id' => $this->id` -> `'id' => $this->uuid` dans :

| Resource | Changements additionnels |
|----------|-------------------------|
| `BookingResource.php` | `'id' => $this->uuid`, ajout de `realtime_channel_key => $this->id` pour les channels booking Reverb. `face_id`/`producer_id` restent internes |
| `MissionResource.php` | `'id' => $this->uuid` |
| `CandidatureResource.php` | `'id' => $this->uuid` |
| `FaceCandidatureResource.php` | `'id' => $this->uuid`, `'conversation_id' => $this->conversation?->uuid`, `mission.id => $this->mission->uuid`, `producer.id => $this->mission->producer->uuid` |
| `ProducerCandidatureResource.php` | `'id' => $this->uuid`, `'conversation_id' => $this->conversation?->uuid`, `face.id => $this->face->uuid` |
| `ConversationResource.php` | `'id' => $this->uuid`, `other_participant.id => ->uuid` |
| `ConversationListResource.php` | `'id' => $this->uuid`, `other_participant.id => ->uuid` |
| `ExperienceResource.php` | `'id' => $this->uuid` |
| `FacePhotoResource.php` | `'id' => $this->uuid` |
| `NotificationResource.php` | `'id' => $this->uuid` |
| `FaceResource.php` | `'id' => $this->uuid` |
| `ProducerResource.php` | `'id' => $this->uuid` |
| `ArticleResource.php` | `'id' => $this->uuid` |
| `AdminResource.php` | `'id' => $this->uuid` |
| `WithdrawalRequestResource.php` | `'id' => $this->uuid` |
| `PublicProducerResource.php` | `'id' => $this->uuid` |
| `PublicFaceResource.php` | `'id' => $this->uuid` |
| `PublicFaceProfileResource.php` | `'id' => $this->uuid` |
| `MissionSummaryResource.php` | `'id' => $this->uuid` |
| `BookingRatingResource.php` | `'id' => $this->uuid` |
| `PublicMissionResource.php` | `'id' => $this->uuid` |
| `PublicArticleResource.php` | `'id' => $this->uuid` |
| `PublicArticleDetailResource.php` | `'id' => $this->uuid` |
| `RatingResource.php` | `rated.id => $this->rated->uuid` |

**Resources NON modifiées** (modèles sans UUID) : `UserResource.php`, `WalletTransactionResource.php`, `MessageResource.php`, `BookingMessageResource.php`

### Routes publiques

**Fichier** : `backend/routes/api/public.php`

| Avant | Après |
|-------|-------|
| `/producers/{id}` + `whereNumber('id')` | `/producers/{producer:slug}` |
| `/producers/{id}/reviews` + `whereNumber('id')` | `/producers/{producer:slug}/reviews` |
| `/faces/{id}/reviews` + `whereNumber('id')` | `/faces/{face:username}/reviews` |

### Controllers publics modifiés

| Fichier | Changement |
|---------|-----------|
| `Public/ProducerController.php` | `show(int $id)` -> `show(Producer $producer)` (résolution implicite par slug) |
| `Public/ProducerReviewController.php` | `index(int $id)` -> `index(Producer $producer)` |
| `Public/FaceReviewController.php` | `index(Request $request, int $id)` -> `index(Request $request, Face $face)` (résolution par username) |

### FormRequests modifiés

| Fichier | Changement |
|---------|-----------|
| `Requests/Booking/CreateBookingRequest.php` | `face_id` : validation changée de `'integer', 'exists:users,id'` à `'string', 'uuid'` + résolution Face par UUID. Le `withValidator` résout maintenant le Face UUID vers un User ID pour le check d'overlap. |
| `Requests/Producer/ConfirmMissionSelectionRequest.php` | `candidature_ids.*` : changé de `'integer', 'exists:candidatures,id'` à `'string', 'uuid', 'exists:candidatures,uuid'` |

### Services modifiés

| Fichier | Changement |
|---------|-----------|
| `Services/BookingService.php` | `create()` : résout Face par UUID (`Face::where('uuid', ...)`) au lieu de `User::findOrFail($data['face_id'])`. Le `face_id` dans `Booking::create()` utilise maintenant `$faceUser->id` au lieu de `$data['face_id']`. |
| `Services/MissionPaymentService.php` | `confirmSelection()` : accepte `string[] $candidatureUuids` au lieu de `int[] $candidatureIds`. Résout les candidatures par `whereIn('uuid', ...)` au lieu de `whereIn('id', ...)`. |
| `Http/Controllers/Api/V1/ReportController.php` | Accepte maintenant les `reportable_id` UUID ou numériques legacy et les résout vers l'ID interne avant création du report |

---

## Fichiers modifiés — Frontend

### Types (~15 fichiers)

Changement principal : `id: number` -> `id: string` pour tous les modèles avec UUID.

| Fichier | Champs changés |
|---------|---------------|
| `features/booking/types/booking.ts` | `Booking.id`, `BookingFaceUserable.id`, `BookingProducerUserable.id`, `BookingRating.booking_id`, `BookingMessage.booking_id`, `BookingMessageBroadcast.booking_id`, `CreateBookingData.face_id` |
| `features/booking/schemas/booking.ts` | `face_id` : `z.number().int().positive()` -> `z.string().uuid()` |
| `features/mission/types/mission.ts` | `Mission.id`, `MissionProducer.id`, `MissionCandidature.id`, `.mission_id`, `.face_id` |
| `features/candidature/types/index.ts` | `Candidature.id`, `.mission_id`, `.face_id`, `FaceCandidature.id`, `.conversation_id`, `ProducerCandidature.id`, `.conversation_id`, `MissionSummary.id`, `ProducerSummary.id`, `FaceSummary.id`, `FacePhoto.id`, `FaceExperience.id`, `CandidateFullProfile.id` |
| `features/messaging/types/index.ts` | `Conversation.id`, `.candidature_id`, `ConversationListItem.id`, `.candidature_id`, `OtherParticipant.id` |
| `features/notification/types/index.ts` | `Notification.id`, `NotificationData.mission_id`, `.candidature_id`, `.booking_id` |
| `features/auth/types.ts` | `Face.id`, `Producer.id` |
| `features/face/types.ts` | `FaceProfile.id`, `FacePhoto.id`, `Experience.id` |
| `features/producer/types.ts` | `Producer.id` |
| `features/landing/types.ts` | `LandingMissionProducer.id`, `LandingMission.id`, `LandingFace.id` |
| `features/public/types.ts` | `PublicProducer.id` |
| `features/wallet/types/wallet.ts` | `WalletWithdrawalRequest.id`, `WalletTransaction.booking_id` (number|null -> string|null) |
| `features/admin/services/*.ts` | Tous les types inline avec `id: number` |
| `stores/adminAuth.ts` | `AdminUser.id` |

### Services API (~13 fichiers)

Changement : paramètres `id: number` -> `id: string`

| Fichier | Méthodes modifiées |
|---------|-------------------|
| `features/booking/services/bookingApi.ts` | `fetchBooking`, `acceptBooking`, `refuseBooking`, `cancelBooking`, `confirmBooking`, `rateBooking`, `reportNoShow`, `checkPaymentStatus`, `payBooking` |
| `features/booking/services/bookingChatApi.ts` | `fetchMessages`, `sendMessage` |
| `features/candidature/services/candidatureApi.ts` | `applyToMission(missionId)`, `getMissionCandidatures(missionId)`, `getCandidateProfile(faceId)`, `acceptCandidature`, `rejectCandidature`, `confirmCandidature`, `cancelCandidature` |
| `features/mission/services/missionApi.ts` | `getMission`, `updateMission`, `deleteMission`, `closeMission`, `reopenMission`, `completeMission`, `getPaymentStatus`, `confirmSelection(missionId: string, candidatureIds: string[])` |
| `features/mission/services/faceMissionApi.ts` | `getMissionDetail` |
| `features/messaging/services/messagingApi.ts` | `getConversation`, `sendMessage`, `getProducerConversation`, `sendProducerMessage` |
| `features/notification/services/notificationApi.ts` | `markAsRead` |
| `features/admin/services/adminsApi.ts` | `getAdmin`, `updateAdmin`, `deleteAdmin`, `sendPasswordResetLink` |
| `features/admin/services/adminFacesApi.ts` | `getFace`, `updateFace`, `toggleActive`, `deleteFace` |
| `features/admin/services/adminProducersApi.ts` | `getProducer`, `updateProducer`, `toggleActive`, `deleteProducer` |
| `features/admin/services/adminMissionsApi.ts` | `getMission` |
| `features/admin/services/adminArticlesApi.ts` | `getArticle`, `updateArticle`, `deleteArticle`, `updateArticleStatus`, `updateArticleCategory` |
| `features/admin/services/adminFinanceApi.ts` | `approveWithdrawalRequest`, `rejectWithdrawalRequest` |
| `features/public/services/publicApi.ts` | `getProducer(slug: string)`, `getProducerReviews(slug: string)`, `getFaceReviews(username: string)` — URLs mises à jour aussi |

### Pages (10 fichiers)

| Fichier | Changement |
|---------|-----------|
| `pages/producer/candidature/ProducerMissionCandidaturesPage.vue` | `Number(route.params.id)` -> `route.params.id as string` |
| `pages/producer/candidature/CandidateProfilePage.vue` | idem |
| `pages/producer/mission/EditMissionPage.vue` | idem |
| `pages/admin/AdminEditPage.vue` | idem |
| `pages/admin/AdminMissionDetailPage.vue` | idem |
| `pages/admin/AdminProducerDetailPage.vue` | idem |
| `pages/admin/AdminFaceDetailPage.vue` | idem |
| `pages/admin/AdminArticleEditPage.vue` | idem |
| `pages/public/ProducerProfilePage.vue` | `route.params.id` -> `route.params.slug` |
| `router/index.ts` | Route `/producers/:id` -> `/producers/:slug` |

### Composables (~25+ fichiers)

Changement : types internes `number` -> `string` pour tous les IDs de modèles avec UUID.

Domaines touchés : `booking/composables/`, `candidature/composables/`, `mission/composables/`, `messaging/composables/`, `admin/composables/`, `public/composables/`, `face/composables/`

---

## Tests modifiés

### Backend (~40 fichiers)

Pattern de changement dans tous les tests :
1. **URLs** : `$model->id` -> `$model->uuid` dans les strings d'URL API
2. **Assertions** : `$model->id` comparé à `response['id']` -> `$model->uuid`
3. **Fake IDs pour 404** : `99999` -> `'00000000-0000-0000-0000-000000000000'`
4. **Routes publiques** : `/producers/{id}` -> `/producers/{$producer->slug}`, `/faces/{id}/reviews` -> `/faces/{$face->username}/reviews`
5. **Raw DB inserts** : ajout de `'uuid' => Str::uuid()` dans les tests qui font des insertions brutes

Répertoires touchés :
- `tests/Feature/Booking/` (12 fichiers)
- `tests/Feature/Candidature/` (10 fichiers)
- `tests/Feature/Messaging/` (5 fichiers)
- `tests/Feature/Mission/` (8 fichiers)
- `tests/Feature/Admin/` (15 fichiers)
- `tests/Feature/Rating/` (6 fichiers)
- `tests/Feature/Public/` (7 fichiers)
- `tests/Feature/Notification/` (1 fichier)
- `tests/Feature/Face/` (2 fichiers)
- `tests/Feature/Auth/` (2 fichiers)
- `tests/Feature/Wallet/` (1 fichier)

### Frontend (~3 fichiers)

- `pages/public/__tests__/ProducerProfilePage.spec.ts` — route `:id` -> `:slug`, IDs numériques -> strings
- `pages/admin/__tests__/AdminFaceDetailPage.spec.ts` — `toHaveBeenCalledWith(1, ...)` -> `('1', ...)`
- `pages/face/mission/__tests__/FaceMissionDetailPage.spec.ts` — déjà corrigé avant cette migration

---

## Points d'attention pour la review

### 1. Cohérence des FKs dans les responses API
Vérifier que partout où un FK est utilisé pour la **navigation frontend** (router-link, API call), il expose le UUID du modèle lié et non l'integer. Les cas identifiés et traités :
- `FaceCandidatureResource` : `conversation_id`, `mission.id`, `producer.id` -> UUID
- `ProducerCandidatureResource` : `conversation_id`, `face.id` -> UUID
- `ConversationResource` / `ConversationListResource` : `other_participant.id` -> UUID
- `RatingResource` : `rated.id` -> UUID

### 2. FKs internes laissées en integer
Ces champs restent en integer dans les responses car ils ne servent pas à la navigation :
- `Booking.face_id`, `Booking.producer_id` (le frontend utilise les objets `face`/`producer` embedded)
- `CandidatureResource.mission_id`, `.face_id` (le resource générique, pas le FaceCandidature/ProducerCandidature)

**Question pour le reviewer** : Y a-t-il des endroits où le frontend utilise ces FKs integer pour construire des URLs ? Si oui, il faut les convertir en UUID.

### 3. Notifications existantes
La colonne JSON `data` des notifications historiques peut encore contenir des IDs numériques (`booking_id: 5`, `mission_id: 3`). Les nouvelles notifications sont maintenant émises avec des UUIDs, et les anciens liens restent tolérés via le fallback numérique de `HasRouteUuid`.

### 4. WebSocket events
Les channels booking Reverb restent volontairement branchés sur l'ID interne `bookings.id`. Le frontend consomme donc `booking.id` (UUID) pour les appels API et `booking.realtime_channel_key` (int) pour Echo/Reverb.

### 5. Email verification
Les routes `/email/verify/{id}/{hash}` utilisent toujours User.id (integer) — c'est correct car User n'a pas de UUID.

### 6. Liens dans les emails/notifications
Les nouveaux liens booking émis par les listeners/notifications ont été migrés vers les UUIDs. Les anciens liens numériques restent acceptés par le route binding backend.

### 8. Signalement de contenu public
Le bouton de signalement public (`face`, `mission`) envoie désormais des UUIDs. L'API `/reports` les résout vers l'ID interne morphique avant insertion, tout en gardant la compatibilité avec un ancien `reportable_id` numérique.

### 7. MissionResource query interne
`MissionResource` ligne 38 contient `where('mission_id', $this->id)` — c'est une query DB interne qui utilise l'ID integer, c'est correct car les FKs restent en integer.

---

## Procédure de déploiement

```bash
# 1. Déployer le backend, puis exécuter les migrations une seule fois
php artisan migrate --force

# 2. Optionnel: vérifier qu'aucun slug producer n'est resté null
php artisan app:backfill-producer-slugs

# 3. Déployer / builder le frontend dans la même fenêtre de déploiement
```

Notes:
- `2026_04_09_100001` backfill maintenant les slugs producers existants pendant la migration.
- `2026_04_09_100002` backfill maintenant les UUIDs manquants avant de passer les colonnes en `NOT NULL`.
- Le point sensible n'est plus un “double migrate”, mais le déploiement non atomique backend/frontend.

---

## Vérifications exécutées

- `cd frontend && npm run type-check` : passe
- `cd frontend && npm run lint` : passe
- `cd frontend && npm run build` : passe
- `cd backend && php artisan test tests/Feature/Report/StoreReportTest.php` : passe
- `cd backend && php artisan test tests/Feature/Booking/BookingShowAcceptRefuseTest.php tests/Feature/Booking/BookingNotificationTest.php tests/Feature/Booking/BookingChatTest.php` : passe

## Statut réel

- TypeScript est actuellement propre sur le frontend.
- Le build frontend passe.
- Les flows UUID corrigés et revérifiés incluent bookings, candidatures/mission producer, profils candidats, messaging, admin CRUD UUID et signalement public.
- La suite complète backend/frontend n'a pas été rejouée intégralement dans cette passe; seules les vérifications ci-dessus sont garanties par cette doc.
