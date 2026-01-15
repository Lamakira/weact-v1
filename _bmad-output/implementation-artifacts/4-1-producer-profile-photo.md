# Story 4.1: Producer Profile Photo

Status: ready-for-dev

## Story

As a **Producer**,
I want **to upload my profile photo**,
So that **Faces can identify me and my brand**.

## Acceptance Criteria

1. **Given** I am logged in as a Producer on my profile page, **When** I upload a profile photo (JPG, PNG, max 5MB), **Then** the image is stored and associated with my profile

2. **Given** a valid photo is uploaded, **When** the upload completes, **Then** a thumbnail is generated

3. **Given** a valid photo is uploaded, **When** the upload completes, **Then** my profile displays the new photo

4. **Given** I already have a profile photo, **When** I upload a new photo, **Then** the old photo and thumbnail are deleted

5. **Given** I want to remove my photo, **When** I click delete, **Then** my profile photo and thumbnail are removed

6. **Given** I am not logged in or not a Producer, **When** I try to upload a photo, **Then** I receive a 401/403 error

**(FR22)**

## Tasks / Subtasks

### Task 1: Create Database Migration (AC: #1)

- [ ] 1.1 Create migration to add `profile_photo` and `profile_photo_thumbnail` columns to `producers` table
- [ ] 1.2 Both columns should be `string()->nullable()`
- [ ] 1.3 Run migration and verify schema

### Task 2: Update Producer Model (AC: #1, #2, #3)

- [ ] 2.1 Add `profile_photo` and `profile_photo_thumbnail` to `$fillable` array
- [ ] 2.2 Add `profile_photo_url` accessor using `Attribute::make()` pattern
- [ ] 2.3 Add `thumbnail_url` accessor using `Attribute::make()` pattern
- [ ] 2.4 Add to `$appends` array: `profile_photo_url`, `thumbnail_url`

### Task 3: Create ProducerProfilePhotoService (AC: #1, #2, #4, #5)

- [ ] 3.1 Create `app/Services/ProducerProfilePhotoService.php`
- [ ] 3.2 Implement `uploadProfilePhoto(Producer $producer, UploadedFile $photo)` method:
  - Delete old photos if exist
  - Generate UUID filename
  - Store in `avatars/producers/` path
  - Generate thumbnail in `avatars/producers/thumbnails/`
  - Update Producer model
- [ ] 3.3 Implement `deleteProfilePhoto(Producer $producer)` method
- [ ] 3.4 Use Intervention Image for thumbnail generation (150x150, quality 85)
- [ ] 3.5 Follow `ProfilePhotoService` pattern exactly

### Task 4: Create Form Request (AC: #1, #6)

- [ ] 4.1 Create `app/Http/Requests/Producer/UpdateProfilePhotoRequest.php`
- [ ] 4.2 Implement `authorize()` - must be authenticated Producer
- [ ] 4.3 Implement validation rules:
  - `photo` required, image, mimes:jpg,jpeg,png, max:5120
- [ ] 4.4 Add French error messages

### Task 5: Create Producer Profile Controller (AC: #1, #2, #3, #5)

- [ ] 5.1 Create `app/Http/Controllers/Api/V1/Producer/ProfileController.php`
- [ ] 5.2 Add `getAuthenticatedProducer(Request $request)` helper (follow Face pattern)
- [ ] 5.3 Implement `show()` - return current Producer profile
- [ ] 5.4 Implement `updatePhoto(UpdateProfilePhotoRequest $request)` - upload profile photo
- [ ] 5.5 Implement `deletePhoto(Request $request)` - delete profile photo
- [ ] 5.6 Inject `ProducerProfilePhotoService` via constructor

### Task 6: Create ProducerResource (AC: #3)

- [ ] 6.1 Create `app/Http/Resources/ProducerResource.php`
- [ ] 6.2 Include all fields: id, type, agency_name, first_name, last_name, display_name, profile_photo_url, thumbnail_url, created_at, updated_at

### Task 7: Add API Routes (AC: #1, #5, #6)

- [ ] 7.1 Create `routes/api/producer.php` file
- [ ] 7.2 Add routes inside `auth:sanctum` middleware group:
  ```php
  Route::prefix('producer')->group(function () {
      Route::get('/profile', [ProfileController::class, 'show']);
      Route::post('/profile/photo', [ProfileController::class, 'updatePhoto']);
      Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto']);
  });
  ```
- [ ] 7.3 Register the route file in `bootstrap/app.php` or `routes/api.php`
- [ ] 7.4 Add throttle middleware: `throttle:60,1`

### Task 8: Create Backend Tests (AC: #1, #2, #3, #4, #5, #6)

- [ ] 8.1 Create `tests/Feature/Producer/ProfilePhotoTest.php`
- [ ] 8.2 Test successful upload returns 200 with photo URLs
- [ ] 8.3 Test thumbnail is generated
- [ ] 8.4 Test old photo is deleted on re-upload
- [ ] 8.5 Test delete photo removes files
- [ ] 8.6 Test validation errors (wrong format, too large)
- [ ] 8.7 Test unauthenticated user gets 401
- [ ] 8.8 Test Face user cannot access Producer endpoints (403)
- [ ] 8.9 Use `Storage::fake('public')` for file assertions
- [ ] 8.10 Run tests with `php artisan test --filter=Producer`

### Task 9: Update Frontend Types (AC: #1, #3)

- [ ] 9.1 Create/update `frontend/src/features/producer/types.ts`:
  - Add `Producer` interface with all fields
  - Add `ProducerProfileResponse` interface
- [ ] 9.2 Export types from feature index

### Task 10: Create producerApi Service (AC: #1, #5)

- [ ] 10.1 Create `frontend/src/features/producer/services/producerApi.ts`
- [ ] 10.2 Implement `getProfile()` method
- [ ] 10.3 Implement `uploadProfilePhoto(photo: File)` method
- [ ] 10.4 Implement `deleteProfilePhoto()` method
- [ ] 10.5 Follow `faceApi.ts` patterns

### Task 11: Create useProducerProfilePhoto Composable (AC: #1, #3, #5)

- [ ] 11.1 Create `frontend/src/features/producer/composables/useProducerProfilePhoto.ts`
- [ ] 11.2 Include refs: `profile`, `isLoading`, `isUploading`, `isDeleting`, `error`
- [ ] 11.3 Implement `fetchProfile()`, `uploadPhoto(file)`, `deletePhoto()` functions
- [ ] 11.4 Return success messages in French
- [ ] 11.5 Follow `useProfilePhoto.ts` pattern

### Task 12: Create ProducerProfilePhotoUpload Component (AC: #1, #3, #5)

- [ ] 12.1 Create `frontend/src/features/producer/components/ProducerProfilePhotoUpload.vue`
- [ ] 12.2 Use Gemini MCP `snippet_frontend` for UI design
- [ ] 12.3 Props: `profile`, `isUploading`, `isDeleting`, `error`
- [ ] 12.4 Events: `@upload`, `@delete`
- [ ] 12.5 Show current photo with thumbnail
- [ ] 12.6 File input for upload (JPG, PNG)
- [ ] 12.7 Delete button when photo exists

### Task 13: Create ProducerProfileEditPage (AC: #1, #3, #5)

- [ ] 13.1 Create `frontend/src/pages/producer/ProducerProfileEditPage.vue`
- [ ] 13.2 Import useProducerProfilePhoto composable
- [ ] 13.3 Add ProducerProfilePhotoUpload component section
- [ ] 13.4 Add header with back button and logout
- [ ] 13.5 Register route in router as `producer-profile`

### Task 14: Create Frontend Tests (AC: #1, #3, #5)

- [ ] 14.1 Create `frontend/src/features/producer/composables/__tests__/useProducerProfilePhoto.spec.ts`
- [ ] 14.2 Create `frontend/src/features/producer/components/__tests__/ProducerProfilePhotoUpload.spec.ts`
- [ ] 14.3 Test upload, delete, error states
- [ ] 14.4 Run tests with `npm run test:run`

## Dev Notes

### Critical Implementation Patterns

This is the FIRST story for Producer profile features. You MUST establish consistent patterns that will be followed by Stories 4.2, 4.3, and 4.4.

### Backend Pattern - Follow Face Implementation

The Face profile photo implementation is the reference:

```
app/
├── Http/
│   ├── Controllers/Api/V1/Face/ProfileController.php  → Copy pattern for Producer
│   ├── Requests/Face/UpdateProfilePhotoRequest.php    → Copy pattern for Producer
│   └── Resources/FaceResource.php                     → Copy pattern for ProducerResource
├── Models/Face.php                                    → Add accessors to Producer.php
└── Services/ProfilePhotoService.php                   → Create ProducerProfilePhotoService
```

### Storage Paths

| User Type | Photo Path | Thumbnail Path |
|-----------|------------|----------------|
| Face | `avatars/faces/` | `avatars/faces/thumbnails/` |
| Producer | `avatars/producers/` | `avatars/producers/thumbnails/` |

### Database Schema

Current `producers` table has:
- `id`, `type` (enum: agency/particulier), `agency_name`, `first_name`, `last_name`, `timestamps`

Add:
- `profile_photo` (string, nullable)
- `profile_photo_thumbnail` (string, nullable)

### Producer Types (ProducerType Enum)

```php
enum ProducerType: string
{
    case Agency = 'agency';
    case Particulier = 'particulier';
}
```

Producer display name logic:
- Agency → `agency_name`
- Particulier → `first_name last_name`

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/producer/profile` | Get current producer profile |
| POST | `/api/v1/producer/profile/photo` | Upload profile photo |
| DELETE | `/api/v1/producer/profile/photo` | Delete profile photo |

### Frontend Structure

```
frontend/src/
├── features/producer/
│   ├── types.ts                                    → CREATE
│   ├── services/producerApi.ts                     → CREATE
│   ├── composables/
│   │   ├── useProducerProfilePhoto.ts              → CREATE
│   │   └── __tests__/useProducerProfilePhoto.spec.ts → CREATE
│   └── components/
│       ├── ProducerProfilePhotoUpload.vue          → CREATE
│       └── __tests__/ProducerProfilePhotoUpload.spec.ts → CREATE
└── pages/producer/
    └── ProducerProfileEditPage.vue                 → CREATE
```

### Polymorphic User Architecture

Remember the User→Face/Producer relationship:
```php
// User model
public function userable(): MorphTo
{
    return $this->morphTo();
}

// In controller, get authenticated producer:
$user = $request->user();
if (!$user || !$user->userable instanceof Producer) {
    return response()->json(['message' => 'Utilisateur non autorisé'], 403);
}
$producer = $user->userable;
```

### Thumbnail Generation

Use Intervention Image v3 (already installed):
```php
use Intervention\Image\Laravel\Facades\Image;

$image = Image::read($photo->getRealPath());
$image->cover(150, 150);
$encoded = $image->toJpeg(85);
Storage::disk('public')->put($path, $encoded->toString());
```

### Test Patterns

Use these established patterns:
- `Storage::fake('public')` for file assertions
- `RefreshDatabase` trait
- Create test users via factory: `User::factory()->forProducer()->create()`
- Assert file existence: `Storage::disk('public')->assertExists($path)`

### Previous Story Intelligence

From Story 3-11 (Face Profile Completion):
- Accessors use `Attribute::make()` with computed `get` function
- Add accessors to `$appends` array for JSON serialization
- Controllers follow show-only or CRUD patterns
- Composables follow `useAvailability` / `useProfilePhoto` structure
- Tests use `data-testid` attributes for reliable selection
- Gemini MCP used for UI component design
- French messages for all user-facing text

### Git Commit Pattern

Follow the established commit message format:
- `feat(producer): description` for features
- `test(producer): description` for tests
- `docs(story): description` for documentation

### Alignment with Project Patterns

- **Controller Pattern**: Follow `Face/ProfileController` structure
- **Service Pattern**: Follow `ProfilePhotoService` structure
- **Accessor Pattern**: Use `Attribute::make()` for computed properties
- **Composable Pattern**: Follow `useProfilePhoto.ts` structure
- **Component Pattern**: Props-based with events for actions
- **API Response**: Use envelope format `{data, message}` for success
- **Testing**: Use PHPUnit with RefreshDatabase, Vitest with Vue Test Utils

### References

- [Source: epics.md#Story 4.1 - Producer Profile Photo]
- [Source: app/Services/ProfilePhotoService.php - Photo service pattern]
- [Source: app/Http/Controllers/Api/V1/Face/ProfileController.php - Controller pattern]
- [Source: app/Models/Producer.php - Producer model structure]
- [Source: database/migrations/2026_01_07_171749_create_producers_table.php - Current schema]
- [Source: 3-11-face-profile-completion-indicator.md - Previous story learnings]

## Dev Agent Record

### Agent Model Used

{{agent_model_name_version}}

### Debug Log References

### Completion Notes List

### File List
