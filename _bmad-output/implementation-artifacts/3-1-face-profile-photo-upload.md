# Story 3.1: Face Profile Photo Upload

Status: ready-for-dev

## Story

As a **Face**,
I want **to upload and manage my profile photo**,
So that **producers can see my face when browsing talents**.

## Acceptance Criteria

1. **Given** I am logged in as a Face on my profile page, **When** I upload a profile photo (JPG, PNG, max 5MB), **Then** the image is stored and associated with my profile

2. **Given** I upload a valid photo, **When** the upload completes, **Then** a thumbnail (150x150) is generated

3. **Given** I upload a valid photo, **When** I view my profile, **Then** my profile displays the new photo

4. **Given** I upload an invalid file type, **When** the upload is processed, **Then** I see an error "Format non supporté (JPG, PNG uniquement)"

5. **Given** I upload a file larger than 5MB, **When** the upload is processed, **Then** I see an error "Fichier trop volumineux (max 5MB)"

6. **Given** I already have a profile photo, **When** I upload a new one, **Then** the old photo is replaced (old file deleted from storage)

**(FR9)**

## Tasks / Subtasks

### Task 1: Add Profile Photo Column to Faces Table (AC: #1)

- [ ] 1.1 Create migration `add_profile_photo_to_faces_table`
- [ ] 1.2 Add `profile_photo` column (nullable string, max 255)
- [ ] 1.3 Add `profile_photo_thumbnail` column (nullable string, max 255)
- [ ] 1.4 Run migration and update Face model fillable array

### Task 2: Install and Configure Intervention Image (AC: #2)

- [ ] 2.1 Install Intervention Image package: `composer require intervention/image`
- [ ] 2.2 Install Intervention Image Laravel integration: `composer require intervention/image-laravel`
- [ ] 2.3 Publish config: `php artisan vendor:publish --provider="Intervention\Image\Laravel\ServiceProvider"`
- [ ] 2.4 Configure GD driver in `config/image.php`

### Task 3: Create Profile Photo Storage Structure (AC: #1, #6)

- [ ] 3.1 Create storage directories: `storage/app/public/avatars/faces/`, `storage/app/public/avatars/faces/thumbnails/`
- [ ] 3.2 Ensure storage symlink exists: `php artisan storage:link`
- [ ] 3.3 Add `getProfilePhotoUrlAttribute` accessor to Face model
- [ ] 3.4 Add `getThumbnailUrlAttribute` accessor to Face model

### Task 4: Create Profile Photo Form Request (AC: #4, #5)

- [ ] 4.1 Create `app/Http/Requests/Face/UpdateProfilePhotoRequest.php`
- [ ] 4.2 Implement validation rules:
  - `photo` required, file, image (jpg,jpeg,png), max:5120 (5MB)
  - Use Laravel 12 `File::image()` fluent rule
- [ ] 4.3 Implement French error messages in `messages()` method
- [ ] 4.4 Add authorization check (user must be Face owner)

### Task 5: Create Profile Photo Service (AC: #1, #2, #6)

- [ ] 5.1 Create `app/Services/ProfilePhotoService.php`
- [ ] 5.2 Implement `uploadProfilePhoto(Face $face, UploadedFile $photo): array` method:
  - Generate unique filename with UUID
  - Store original photo in `avatars/faces/`
  - Generate thumbnail (150x150) using Intervention Image
  - Store thumbnail in `avatars/faces/thumbnails/`
  - Delete old photo if exists
  - Return paths array `['photo' => path, 'thumbnail' => thumbnail_path]`
- [ ] 5.3 Implement `deleteProfilePhoto(Face $face): bool` method
- [ ] 5.4 Add service provider binding if needed

### Task 6: Create Face Profile Controller (AC: #1, #3, #6)

- [ ] 6.1 Create `app/Http/Controllers/Api/V1/Face/ProfileController.php`
- [ ] 6.2 Implement `updatePhoto(UpdateProfilePhotoRequest $request): JsonResponse` method
- [ ] 6.3 Implement `deletePhoto(): JsonResponse` method
- [ ] 6.4 Use ProfilePhotoService for business logic
- [ ] 6.5 Return standard API envelope response

### Task 7: Create Face API Resource (AC: #3)

- [ ] 7.1 Create `app/Http/Resources/FaceResource.php`
- [ ] 7.2 Include `profile_photo_url` and `thumbnail_url` in response
- [ ] 7.3 Include basic Face fields (id, nom, prenom, username)

### Task 8: Add API Routes (AC: #1)

- [ ] 8.1 Create `routes/api/face.php` for Face-specific routes
- [ ] 8.2 Add route: `POST /api/v1/face/profile/photo` (updatePhoto)
- [ ] 8.3 Add route: `DELETE /api/v1/face/profile/photo` (deletePhoto)
- [ ] 8.4 Add route: `GET /api/v1/face/profile` (show current Face profile)
- [ ] 8.5 Apply `auth:sanctum` and `role:face` middleware
- [ ] 8.6 Include routes file in `routes/api.php`

### Task 9: Backend Tests (AC: #1, #2, #4, #5, #6)

- [ ] 9.1 Create `tests/Feature/Face/ProfilePhotoTest.php`
- [ ] 9.2 Test successful photo upload with valid JPG
- [ ] 9.3 Test successful photo upload with valid PNG
- [ ] 9.4 Test thumbnail generation
- [ ] 9.5 Test rejection of invalid file types (GIF, PDF, etc.)
- [ ] 9.6 Test rejection of oversized files (>5MB)
- [ ] 9.7 Test photo replacement (old file deleted)
- [ ] 9.8 Test unauthorized access (non-Face user)
- [ ] 9.9 Test photo deletion endpoint

### Task 10: Create Frontend Face Profile Feature Structure (AC: #1, #3)

- [ ] 10.1 Create `frontend/src/features/face/` directory structure:
  - `components/`
  - `composables/`
  - `services/`
  - `types.ts`
- [ ] 10.2 Create `frontend/src/features/face/types.ts` with Face interface
- [ ] 10.3 Create `frontend/src/features/face/services/faceApi.ts` with API client

### Task 11: Create Profile Photo Upload Component (AC: #1, #3, #4, #5)

- [ ] 11.1 Create `frontend/src/features/face/components/ProfilePhotoUpload.vue`
- [ ] 11.2 Implement drag-and-drop zone with click fallback
- [ ] 11.3 Implement client-side validation (file type, size)
- [ ] 11.4 Show upload progress indicator
- [ ] 11.5 Display current photo with delete option
- [ ] 11.6 Handle error states with French messages
- [ ] 11.7 Emit `upload-success` and `upload-error` events

### Task 12: Create useProfilePhoto Composable (AC: #1, #3, #6)

- [ ] 12.1 Create `frontend/src/features/face/composables/useProfilePhoto.ts`
- [ ] 12.2 Implement `uploadPhoto(file: File): Promise<void>`
- [ ] 12.3 Implement `deletePhoto(): Promise<void>`
- [ ] 12.4 Track loading states: `isUploading`, `isDeleting`
- [ ] 12.5 Handle errors with toast notifications

### Task 13: Create Face Profile Edit Page (AC: #1, #3)

- [ ] 13.1 Create `frontend/src/pages/face/ProfileEdit.vue`
- [ ] 13.2 Include ProfilePhotoUpload component
- [ ] 13.3 Add page header with breadcrumb
- [ ] 13.4 Add route: `/face/profile/edit` in router

### Task 14: Frontend Tests (AC: #1, #4, #5)

- [ ] 14.1 Create `frontend/src/features/face/components/__tests__/ProfilePhotoUpload.spec.ts`
- [ ] 14.2 Test file selection triggers upload
- [ ] 14.3 Test invalid file type shows error
- [ ] 14.4 Test oversized file shows error
- [ ] 14.5 Test current photo displays correctly
- [ ] 14.6 Test delete button triggers deletion
- [ ] 14.7 Test loading states during upload/delete

## Dev Notes

### Laravel 12 File Validation Pattern

**Use the fluent File rule builder (Laravel 12 style):**
```php
use Illuminate\Validation\Rules\File;

public function rules(): array
{
    return [
        'photo' => [
            'required',
            File::image()
                ->types(['jpg', 'jpeg', 'png'])
                ->max(5 * 1024), // 5MB in KB
        ],
    ];
}
```

### Intervention Image Thumbnail Generation

**Generate thumbnail with Intervention Image 3.x:**
```php
use Intervention\Image\Laravel\Facades\Image;

// Read and resize to thumbnail
$thumbnail = Image::read($photoPath)
    ->cover(150, 150)
    ->toJpg(quality: 85);

// Save thumbnail
$thumbnail->save(storage_path('app/public/avatars/faces/thumbnails/' . $filename));
```

### Storage Path Convention

```
storage/app/public/
├── avatars/
│   └── faces/
│       ├── {uuid}.jpg          # Original photos
│       └── thumbnails/
│           └── {uuid}.jpg      # 150x150 thumbnails
```

**URL Access:** `/storage/avatars/faces/{uuid}.jpg`

### Face Model Accessors

```php
// app/Models/Face.php

protected function profilePhotoUrl(): Attribute
{
    return Attribute::make(
        get: fn () => $this->profile_photo
            ? asset('storage/avatars/faces/' . $this->profile_photo)
            : null,
    );
}

protected function thumbnailUrl(): Attribute
{
    return Attribute::make(
        get: fn () => $this->profile_photo_thumbnail
            ? asset('storage/avatars/faces/thumbnails/' . $this->profile_photo_thumbnail)
            : null,
    );
}
```

### API Response Format

**Success Response:**
```json
{
  "data": {
    "id": 1,
    "nom": "Doe",
    "prenom": "John",
    "username": "johndoe",
    "profile_photo_url": "https://weact.app/storage/avatars/faces/abc123.jpg",
    "thumbnail_url": "https://weact.app/storage/avatars/faces/thumbnails/abc123.jpg"
  },
  "message": "Photo de profil mise à jour"
}
```

**Error Response:**
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Les données fournies sont invalides",
    "details": {
      "photo": ["Format non supporté (JPG, PNG uniquement)"]
    }
  }
}
```

### Frontend Upload Component Pattern

```vue
<script setup lang="ts">
import { ref, computed } from 'vue'
import { useToast } from '@/composables/useToast'

interface Props {
  currentPhotoUrl?: string | null
}

interface Emits {
  (e: 'upload-success', url: string): void
  (e: 'upload-error', error: string): void
  (e: 'delete-success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const fileInput = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)
const isUploading = ref(false)

const MAX_SIZE = 5 * 1024 * 1024 // 5MB
const ALLOWED_TYPES = ['image/jpeg', 'image/png']

function validateFile(file: File): string | null {
  if (!ALLOWED_TYPES.includes(file.type)) {
    return 'Format non supporté (JPG, PNG uniquement)'
  }
  if (file.size > MAX_SIZE) {
    return 'Fichier trop volumineux (max 5MB)'
  }
  return null
}
</script>
```

### Project Structure Notes

**Backend structure to create:**
```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/Face/
│   │   │   └── ProfileController.php
│   │   ├── Requests/Face/
│   │   │   └── UpdateProfilePhotoRequest.php
│   │   └── Resources/
│   │       └── FaceResource.php
│   └── Services/
│       └── ProfilePhotoService.php
├── routes/api/
│   └── face.php
└── tests/Feature/Face/
    └── ProfilePhotoTest.php
```

**Frontend structure to create:**
```
frontend/src/
├── features/face/
│   ├── components/
│   │   ├── ProfilePhotoUpload.vue
│   │   └── __tests__/
│   │       └── ProfilePhotoUpload.spec.ts
│   ├── composables/
│   │   └── useProfilePhoto.ts
│   ├── services/
│   │   └── faceApi.ts
│   └── types.ts
└── pages/face/
    └── ProfileEdit.vue
```

### Previous Story Intelligence

From Story 2-6 (Authentication Frontend Integration):
- **AppHeader component:** Already has Face-specific navigation
- **Auth store:** Has `isFace` computed property for role checking
- **apiClient:** Configured with 401 interceptor and auth token handling
- **Router guards:** Already implemented for `/face/*` routes

### Security Considerations

- Validate MIME type server-side (not just extension)
- Generate unique filenames (UUID) to prevent path traversal
- Store outside public web root and serve via Laravel
- Consider rate limiting upload endpoint (10/min)
- Clean up orphaned files if database update fails

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 3.1]
- [Source: _bmad-output/project-context.md#Framework Rules]
- [Source: docs/planning-artifacts/architecture.md#Project Structure]
- [Source: Laravel 12 File Validation - context7]
- [Source: Intervention Image 3.x Documentation - context7]

## Dev Agent Record

### Agent Model Used

{{agent_model_name_version}}

### Debug Log References

### Completion Notes List

### File List

## Change Log

- 2026-01-11: Story 3.1 created and marked ready-for-dev
