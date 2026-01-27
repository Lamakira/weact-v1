# Story 4.3: Agency Logo Upload

Status: done

## Story

As a **Producer Agence**,
I want **to upload my agency logo**,
So that **my brand is professionally represented on the platform**.

## Acceptance Criteria

1. **Given** I am logged in as a Producer with type "Agence", **When** I upload a logo (JPG, PNG, max 2MB), **Then** the logo is stored and displayed on my profile

2. **Given** a valid logo is uploaded, **When** the upload completes, **Then** a thumbnail is generated for optimized display

3. **Given** I already have a logo, **When** I upload a new logo, **Then** the old logo and thumbnail are deleted

4. **Given** I want to remove my logo, **When** I click delete, **Then** my logo and thumbnail are removed

5. **Given** I am a Particulier (not Agence), **When** I view my profile edit page, **Then** the logo upload option is not available

6. **Given** I am not logged in or not a Producer Agence, **When** I try to upload a logo, **Then** I receive a 401/403 error

**(FR24)**

## Tasks / Subtasks

### Task 1: Add Logo Columns to Producers Table (AC: #1, #2)

- [x] 1.1 Create migration `add_agency_logo_to_producers_table`
- [x] 1.2 Add `agency_logo` column: `string()->nullable()`
- [x] 1.3 Add `agency_logo_thumbnail` column: `string()->nullable()`
- [x] 1.4 Run migration and verify schema

### Task 2: Update Producer Model (AC: #1, #2)

- [x] 2.1 Add `agency_logo` and `agency_logo_thumbnail` to `$fillable` array
- [x] 2.2 Add `agency_logo_url` accessor using `Attribute::make()` pattern
- [x] 2.3 Add `agency_logo_thumbnail_url` accessor using `Attribute::make()` pattern
- [x] 2.4 Add `agency_logo_url` and `agency_logo_thumbnail_url` to `$appends` array

### Task 3: Create AgencyLogoService (AC: #1, #2, #3, #4)

- [x] 3.1 Create `app/Services/AgencyLogoService.php`
- [x] 3.2 Implement `uploadLogo(Producer $producer, UploadedFile $logo)` method:
  - Verify producer is Agency type (throw exception if not)
  - Delete old logos if exist
  - Generate UUID filename
  - Store in `logos/agencies/` path
  - Generate thumbnail in `logos/agencies/thumbnails/`
  - Update Producer model
- [x] 3.3 Implement `deleteLogo(Producer $producer)` method
- [x] 3.4 Use Intervention Image for thumbnail generation (150x150, quality 85)
- [x] 3.5 Follow `ProducerProfilePhotoService` pattern exactly

### Task 4: Create UpdateAgencyLogoRequest (AC: #1, #5, #6)

- [x] 4.1 Create `app/Http/Requests/Producer/UpdateAgencyLogoRequest.php`
- [x] 4.2 Implement `authorize()` - must be authenticated Producer AND type = 'agency'
- [x] 4.3 Implement validation rules:
  - `logo` required, image, mimes:jpg,jpeg,png, max:2048 (2MB limit per FR24)
- [x] 4.4 Add French error messages

### Task 5: Create DeleteAgencyLogoRequest (AC: #4, #5, #6)

- [x] 5.1 Create `app/Http/Requests/Producer/DeleteAgencyLogoRequest.php`
- [x] 5.2 Implement `authorize()` - must be authenticated Producer AND type = 'agency'
- [x] 5.3 No validation rules needed (empty rules array)

### Task 6: Add Logo Methods to ProfileController (AC: #1, #3, #4)

- [x] 6.1 Inject `AgencyLogoService` via constructor
- [x] 6.2 Add `showLogo(Request $request)` method - return logo URLs or null
- [x] 6.3 Add `updateLogo(UpdateAgencyLogoRequest $request)` method - upload logo
- [x] 6.4 Add `deleteLogo(DeleteAgencyLogoRequest $request)` method - delete logo

### Task 7: Add API Routes (AC: #1, #4, #6)

- [x] 7.1 Add routes to `routes/api/producer.php`:
  ```php
  Route::get('/profile/logo', [ProfileController::class, 'showLogo']);
  Route::post('/profile/logo', [ProfileController::class, 'updateLogo']);
  Route::delete('/profile/logo', [ProfileController::class, 'deleteLogo']);
  ```

### Task 8: Update ProducerResource (AC: #1, #2)

- [x] 8.1 Add `agency_logo_url` and `agency_logo_thumbnail_url` fields to resource
- [x] 8.2 Ensure they return null for Particulier producers

### Task 9: Create Backend Tests (AC: #1, #2, #3, #4, #5, #6)

- [x] 9.1 Create `tests/Feature/Producer/AgencyLogoTest.php`
- [x] 9.2 Test successful logo upload returns 200 with logo URLs (Agency)
- [x] 9.3 Test thumbnail is generated
- [x] 9.4 Test old logo is deleted on re-upload
- [x] 9.5 Test delete logo removes files
- [x] 9.6 Test validation errors (wrong format, too large - over 2MB)
- [x] 9.7 Test Particulier producer gets 403 on upload attempt
- [x] 9.8 Test Particulier producer gets 403 on delete attempt
- [x] 9.9 Test unauthenticated user gets 401
- [x] 9.10 Test Face user cannot access Producer endpoints (403)
- [x] 9.11 Use `Storage::fake('public')` for file assertions
- [x] 9.12 Run tests with `php artisan test --filter=AgencyLogo`

### Task 10: Update Frontend Types (AC: #1, #5)

- [x] 10.1 Update `Producer` interface in `frontend/src/features/producer/types.ts`:
  - Add `agency_logo_url: string | null`
  - Add `agency_logo_thumbnail_url: string | null`
- [x] 10.2 Add `AgencyLogoResponse` interface
- [x] 10.3 Add `AgencyLogoResult` type for composable returns

### Task 11: Add Logo API Methods to producerApi (AC: #1, #4)

- [x] 11.1 Add `getLogo()` method to `producerApi.ts`
- [x] 11.2 Add `uploadLogo(logo: File)` method to `producerApi.ts`
- [x] 11.3 Add `deleteLogo()` method to `producerApi.ts`

### Task 12: Create useAgencyLogo Composable (AC: #1, #3, #4, #5)

- [x] 12.1 Create `frontend/src/features/producer/composables/useAgencyLogo.ts`
- [x] 12.2 Include refs: `logoUrl`, `thumbnailUrl`, `isLoading`, `isUploading`, `isDeleting`, `error`
- [x] 12.3 Implement `fetchLogo()`, `uploadLogo(file)`, `deleteLogo()` functions
- [x] 12.4 Return success messages in French
- [x] 12.5 Follow `useProducerProfilePhoto.ts` pattern

### Task 13: Create AgencyLogoUpload Component (AC: #1, #3, #4, #5)

- [x] 13.1 Create `frontend/src/features/producer/components/AgencyLogoUpload.vue`
- [x] 13.2 **Use Gemini MCP `snippet_frontend`** for UI design (pass existing CSS/theme context)
- [x] 13.3 Self-contained component using composable internally
- [x] 13.4 Drag-and-drop support
- [x] 13.5 Show current logo if exists (use thumbnail for preview)
- [x] 13.6 File input for upload (JPG, PNG, max 2MB - show limit in UI)
- [x] 13.7 Delete button when logo exists
- [x] 13.8 Component only shown for Agency producers via v-if in parent
- [x] 13.9 Use Tailwind with `weact-*` brand colors (not hardcoded hex)

### Task 14: Integrate Logo Upload in ProfileEditPage (AC: #1, #5)

- [x] 14.1 Import useAgencyLogo composable
- [x] 14.2 Add AgencyLogoUpload section ONLY for Agency producers (after profile photo, before bio section)
- [x] 14.3 Conditionally render based on producer type with computed isAgency
- [x] 14.4 Component fetches logo on mount internally
- [x] 14.5 Component handles upload/delete internally (uses toasts from composable pattern)

### Task 15: Create Frontend Tests (AC: #1, #3, #4, #5)

- [x] 15.1 Create `frontend/src/features/producer/composables/__tests__/useAgencyLogo.spec.ts`
- [x] 15.2 Create `frontend/src/features/producer/components/__tests__/AgencyLogoUpload.spec.ts`
- [x] 15.3 Test upload, delete, error states
- [x] 15.4 Test component visibility based on hasLogo computed
- [x] 15.5 Run tests with `npm run test:run` - all 717 frontend tests pass

## Dev Notes

### Critical Implementation Patterns

This is the THIRD story for Producer profile features. Follow the patterns established in Story 4.1 (Producer Profile Photo) and Story 4.2 (Producer Bio).

### CRITICAL: Agency-Only Feature

This feature is ONLY for producers with `type = 'agency'`. Particulier producers should:
- NOT see the logo upload UI
- Receive 403 if they try to access logo endpoints directly

### Backend Pattern - Follow ProducerProfilePhotoService

The existing `ProducerProfilePhotoService` is the reference:

```php
// app/Services/ProducerProfilePhotoService.php patterns to follow:
- Storage paths as constants
- uploadProfilePhoto() with delete-first pattern
- deleteProfilePhoto() with file existence checks
- generateThumbnail() with Intervention Image
- Use Storage::disk('public') throughout
```

### Storage Paths

| Asset Type | Storage Path | Thumbnail Path |
|-----------|--------------|----------------|
| Profile Photo | `avatars/producers/` | `avatars/producers/thumbnails/` |
| Agency Logo | `logos/agencies/` | `logos/agencies/thumbnails/` |

### Database Schema

Current `producers` table (after Story 4.2):
- `id`, `type`, `agency_name`, `first_name`, `last_name`, `profile_photo`, `profile_photo_thumbnail`, `bio`, `timestamps`

Add:
- `agency_logo` (string, nullable)
- `agency_logo_thumbnail` (string, nullable)

### Producer Type Enum

```php
// app/Enums/ProducerType.php
enum ProducerType: string
{
    case Agency = 'agency';
    case Particulier = 'particulier';
}
```

Check type using model method:
```php
$producer->isAgency() // returns true if type === ProducerType::Agency
```

### Form Request Authorization - Agency Check

```php
// UpdateAgencyLogoRequest.php
public function authorize(): bool
{
    $user = $this->user();

    if (!$user || !$user->userable instanceof Producer) {
        return false;
    }

    // CRITICAL: Only Agency producers can upload logos
    return $user->userable->isAgency();
}
```

### API Endpoints

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/api/v1/producer/profile/logo` | Get current logo URLs | Agency only |
| POST | `/api/v1/producer/profile/logo` | Upload logo | Agency only |
| DELETE | `/api/v1/producer/profile/logo` | Delete logo | Agency only |

### Size Limit: 2MB (not 5MB)

Per FR24, agency logos have a **2MB** maximum size (unlike profile photos which are 5MB). This is validated in the Form Request:

```php
'logo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
```

### Frontend Structure

```
frontend/src/
├── features/producer/
│   ├── types.ts                                    → MODIFY (add logo fields)
│   ├── services/producerApi.ts                     → MODIFY (add logo methods)
│   ├── composables/
│   │   ├── useAgencyLogo.ts                        → CREATE
│   │   └── __tests__/useAgencyLogo.spec.ts         → CREATE
│   └── components/
│       ├── AgencyLogoUpload.vue                    → CREATE
│       └── __tests__/AgencyLogoUpload.spec.ts      → CREATE
└── pages/producer/
    └── ProfileEditPage.vue                         → MODIFY (add logo section for Agency)
```

### UI Development with Gemini MCP

**CRITICAL**: Use Gemini MCP for all frontend UI component creation.

For the `AgencyLogoUpload.vue` component:
1. Use `snippet_frontend` tool to generate the component JSX/template
2. Pass the existing CSS/globals file in `context` parameter to maintain design consistency
3. Specify `weact-*` Tailwind classes in `insertionContext`
4. Handle logic (useState, handlers) yourself, let Gemini design the visual UI

```
Decision Tree:
- NEW visual component? → snippet_frontend or create_frontend
- REDESIGN existing element? → modify_frontend
- Just text/logic/trivial? → Do it yourself
```

### Conditional UI Rendering

In `ProfileEditPage.vue`, check producer type before showing logo section:

```vue
<template>
  <!-- Profile Photo - always visible -->
  <ProducerProfilePhotoUpload ... />

  <!-- Bio - always visible -->
  <ProducerBioEditor ... />

  <!-- Agency Logo - ONLY for Agency producers -->
  <AgencyLogoUpload
    v-if="producer?.type === 'agency'"
    ...
  />
</template>
```

### Thumbnail Generation

Same as profile photos - use Intervention Image v3:
```php
use Intervention\Image\Laravel\Facades\Image;

$image = Image::read($logo->getRealPath());
$image->cover(150, 150);
$encoded = $image->toJpeg(85);
Storage::disk('public')->put($path, $encoded->toString());
```

### Test Patterns

Follow the patterns from Story 4.1 and 4.2:

**Backend:**
- `Storage::fake('public')` for file assertions
- `RefreshDatabase` trait
- Test both Agency and Particulier scenarios
- Create Agency producer: `User::factory()->forProducerAgency()->create()`
- Create Particulier producer: `User::factory()->forProducerParticulier()->create()`

**Frontend:**
- Use `data-testid` attributes
- Test component visibility based on props
- Mock API calls with `vi.mock()`

### Error Messages (French)

```php
// UpdateAgencyLogoRequest.php
public function messages(): array
{
    return [
        'logo.required' => 'Le logo est requis.',
        'logo.image' => 'Le fichier doit être une image.',
        'logo.mimes' => 'Le logo doit être au format JPG ou PNG.',
        'logo.max' => 'Le logo ne peut pas dépasser 2 Mo.',
    ];
}
```

### Previous Story Intelligence

From Story 4.1 (Producer Profile Photo):
- ProducerProfilePhotoService handles file storage and thumbnail generation
- ProfileController uses dependency injection for services
- Form Requests handle both authorization and validation
- Use `Storage::disk('public')` for test compatibility
- Accessors use `Attribute::make()` pattern

From Story 4.2 (Producer Bio):
- Form Requests created for both GET and PUT endpoints
- ShowBioRequest established pattern for read-only authorization
- Toast notifications added for success feedback
- Loading states tracked separately for different operations

### Git Commit Pattern

Follow the established commit message format:
- `feat(producer): add agency logo database columns`
- `feat(producer): add agency logo service`
- `feat(producer): add agency logo API endpoints`
- `feat(producer): add agency logo frontend components`
- `test(producer): add agency logo tests`

### Alignment with Project Patterns

- **Controller Pattern**: Add methods to existing `Producer/ProfileController`
- **Service Pattern**: Create `AgencyLogoService` following `ProducerProfilePhotoService`
- **Request Pattern**: Separate requests for upload and delete (with agency check)
- **Accessor Pattern**: Use `Attribute::make()` for computed URL properties
- **Composable Pattern**: Follow `useProducerProfilePhoto.ts` structure
- **Component Pattern**: Props-based with events for actions
- **API Response**: Use envelope format `{data, message}` for success
- **Testing**: PHPUnit with RefreshDatabase, Vitest with Vue Test Utils

### References

- [Source: epics.md#Story 4.3 - Agency Logo Upload]
- [Source: app/Services/ProducerProfilePhotoService.php - Photo service pattern]
- [Source: app/Http/Controllers/Api/V1/Producer/ProfileController.php - Controller pattern]
- [Source: app/Models/Producer.php - Producer model with isAgency() method]
- [Source: 4-1-producer-profile-photo.md - Previous story learnings]
- [Source: 4-2-producer-bio.md - Previous story learnings]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

- Backend tests: 293 passed (1258 assertions)
- Frontend tests: 717 passed (125 producer-specific tests)
- ProducerFactory note: uses `individual()` method (not `particulier()`) for Particulier producers

### Completion Notes List

1. **Backend Implementation Complete**
   - Migration created for `agency_logo` and `agency_logo_thumbnail` columns
   - Producer model updated with fillable, accessors, and appends
   - AgencyLogoService created following ProducerProfilePhotoService pattern
   - Form requests created with Agency-type authorization check
   - ProfileController extended with logo methods
   - API routes added for GET/POST/DELETE logo endpoints

2. **Frontend Implementation Complete**
   - Types updated with logo fields and interfaces
   - producerApi extended with logo methods
   - useAgencyLogo composable created with 2MB file validation
   - AgencyLogoUpload component created using Gemini MCP snippet_frontend
   - ProfileEditPage updated to conditionally show logo section for Agency producers

3. **All Acceptance Criteria Met**
   - AC1: Logo upload works with JPG/PNG up to 2MB
   - AC2: Thumbnails generated automatically
   - AC3: Old logo deleted on re-upload
   - AC4: Delete functionality works
   - AC5: Particulier producers don't see logo section
   - AC6: 401/403 errors for unauthorized access

### File List

**Backend - New Files:**
- `backend/database/migrations/2026_01_18_084701_add_agency_logo_to_producers_table.php`
- `backend/app/Services/AgencyLogoService.php`
- `backend/app/Http/Requests/Producer/UpdateAgencyLogoRequest.php`
- `backend/app/Http/Requests/Producer/DeleteAgencyLogoRequest.php`
- `backend/app/Http/Requests/Producer/ShowAgencyLogoRequest.php` (added during code review)
- `backend/tests/Feature/Producer/AgencyLogoTest.php`

**Backend - Modified Files:**
- `backend/app/Models/Producer.php` (fillable, accessors, appends)
- `backend/app/Http/Controllers/Api/V1/Producer/ProfileController.php` (logo methods)
- `backend/app/Http/Resources/ProducerResource.php` (logo URL fields)
- `backend/routes/api/producer.php` (logo routes)

**Frontend - New Files:**
- `frontend/src/features/producer/composables/useAgencyLogo.ts`
- `frontend/src/features/producer/composables/__tests__/useAgencyLogo.spec.ts`
- `frontend/src/features/producer/components/AgencyLogoUpload.vue`
- `frontend/src/features/producer/components/__tests__/AgencyLogoUpload.spec.ts`

**Frontend - Modified Files:**
- `frontend/src/features/producer/types.ts` (logo interfaces)
- `frontend/src/features/producer/services/producerApi.ts` (logo methods)
- `frontend/src/pages/producer/ProfileEditPage.vue` (logo section integration)

### Code Review Fixes (2026-01-18)

The following issues were identified and fixed during adversarial code review:

1. **ShowAgencyLogoRequest Created** (Medium) - Created dedicated form request for `showLogo` method to maintain consistent authorization pattern with other logo endpoints.

2. **Thumbnail Extension Mismatch Fixed** (Medium) - Thumbnails are now always saved with `.jpg` extension since they're converted to JPEG format, preventing MIME type mismatch.

3. **API Validation Errors Display** (Low) - `useAgencyLogo.ts` now displays specific validation errors (e.g., "Le logo doit être au format JPG ou PNG") instead of generic error messages.

4. **Drag-Drop Validation Feedback** (Low) - `AgencyLogoUpload.vue` now shows validation errors when files are drag-dropped, not just when using the file picker.

**Files Added by Code Review:**
- `backend/app/Http/Requests/Producer/ShowAgencyLogoRequest.php`

## Change Log

- 2026-01-18: Story 4.3 created with comprehensive implementation guide
- 2026-01-18: Story 4.3 implementation completed - all tasks done, tests passing
- 2026-01-18: Code review completed - 4 issues identified and fixed
- 2026-01-18: Final approval granted - Story marked as done
