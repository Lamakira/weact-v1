# Story 3.2: Face Photo Album Management

Status: ready-for-dev

## Story

As a **Face**,
I want **to manage an album of up to 4 portfolio photos**,
So that **I can showcase my versatility to producers**.

## Acceptance Criteria

1. **Given** I am on my profile edit page, **When** I upload portfolio photos, **Then** I can add up to 4 photos to my album and each photo is validated (JPG/PNG, max 5MB) and photos display in a grid on my profile

2. **Given** I have 4 photos and try to add another, **When** I attempt the upload, **Then** I see an error "Maximum 4 photos atteint"

3. **Given** I want to remove a photo, **When** I click delete on a portfolio photo, **Then** the photo is removed and the slot becomes available

4. **Given** I upload photos, **When** the upload completes, **Then** thumbnails are generated for each photo (same as profile photo pattern)

5. **Given** I view my profile, **When** photos are displayed, **Then** they appear in a grid layout with the ability to reorder them

**(FR10)**

## Tasks / Subtasks

### Task 1: Add Photo Album Table to Database (AC: #1)

- [ ] 1.1 Create migration `create_face_photos_table`:
  - `id` (primary key)
  - `face_id` (foreign key to faces)
  - `filename` (string, max 255)
  - `thumbnail` (string, max 255, nullable)
  - `position` (integer, for ordering, default 0)
  - `timestamps`
- [ ] 1.2 Add unique constraint on `(face_id, position)` to prevent duplicate positions
- [ ] 1.3 Run migration and verify schema

### Task 2: Create FacePhoto Model (AC: #1, #3)

- [ ] 2.1 Create `app/Models/FacePhoto.php`
- [ ] 2.2 Define fillable: `face_id`, `filename`, `thumbnail`, `position`
- [ ] 2.3 Add `photo_url` and `thumbnail_url` accessors (same pattern as Face model)
- [ ] 2.4 Add relationship: `face()` belongsTo Face
- [ ] 2.5 Update Face model: add `photos()` hasMany FacePhoto (ordered by position)

### Task 3: Create Photo Album Service (AC: #1, #2, #3, #4, #5)

- [ ] 3.1 Create `app/Services/PhotoAlbumService.php`
- [ ] 3.2 Implement `addPhoto(Face $face, UploadedFile $photo): FacePhoto`:
  - Check if face already has 4 photos, throw exception if limit reached
  - Generate unique filename with UUID
  - Store photo in `avatars/faces/albums/`
  - Generate thumbnail (150x150) in `avatars/faces/albums/thumbnails/`
  - Set position to next available (count + 1)
  - Return created FacePhoto model
- [ ] 3.3 Implement `deletePhoto(FacePhoto $photo): bool`:
  - Delete files from storage
  - Delete database record
  - Reorder remaining photos to fill gaps
- [ ] 3.4 Implement `reorderPhotos(Face $face, array $order): void`:
  - Accept array of photo IDs in desired order
  - Validate all IDs belong to face
  - Update positions accordingly
- [ ] 3.5 Implement `getPhotos(Face $face): Collection`:
  - Return photos ordered by position

### Task 4: Create Photo Album Form Request (AC: #1, #2)

- [ ] 4.1 Create `app/Http/Requests/Face/AddAlbumPhotoRequest.php`
- [ ] 4.2 Implement validation rules:
  - `photo` required, file, image (jpg,jpeg,png), max:5120 (5MB)
  - Use Laravel 12 `File::image()` fluent rule
- [ ] 4.3 Implement French error messages
- [ ] 4.4 Add authorization check (user must be Face owner)
- [ ] 4.5 Add custom validation to check photo count limit (4 max)

### Task 5: Create Photo Album API Resource (AC: #1)

- [ ] 5.1 Create `app/Http/Resources/FacePhotoResource.php`
- [ ] 5.2 Include: `id`, `photo_url`, `thumbnail_url`, `position`

### Task 6: Create Album Controller (AC: #1, #2, #3, #5)

- [ ] 6.1 Create `app/Http/Controllers/Api/V1/Face/AlbumController.php`
- [ ] 6.2 Implement `index(): JsonResponse` - list all album photos
- [ ] 6.3 Implement `store(AddAlbumPhotoRequest $request): JsonResponse` - add photo
- [ ] 6.4 Implement `destroy(FacePhoto $photo): JsonResponse` - delete photo
- [ ] 6.5 Implement `reorder(Request $request): JsonResponse` - reorder photos
- [ ] 6.6 Use PhotoAlbumService for all business logic
- [ ] 6.7 Return standard API envelope responses

### Task 7: Add API Routes (AC: #1)

- [ ] 7.1 Add routes to `routes/api/face.php`:
  - `GET /api/v1/face/album` - list album photos
  - `POST /api/v1/face/album` - add photo (rate limited: 10/min)
  - `DELETE /api/v1/face/album/{photo}` - delete photo
  - `PUT /api/v1/face/album/reorder` - reorder photos
- [ ] 7.2 Apply `auth:sanctum` middleware
- [ ] 7.3 Add route model binding for FacePhoto with scope validation

### Task 8: Backend Tests (AC: #1, #2, #3, #4, #5)

- [ ] 8.1 Create `tests/Feature/Face/AlbumTest.php`
- [ ] 8.2 Test list album photos (empty and with photos)
- [ ] 8.3 Test successful photo upload
- [ ] 8.4 Test thumbnail generation on upload
- [ ] 8.5 Test rejection when album is full (4 photos)
- [ ] 8.6 Test rejection of invalid file types
- [ ] 8.7 Test rejection of oversized files
- [ ] 8.8 Test successful photo deletion
- [ ] 8.9 Test photo reordering
- [ ] 8.10 Test unauthorized access (non-owner)
- [ ] 8.11 Test rate limiting on upload endpoint

### Task 9: Create Frontend Types (AC: #1)

- [ ] 9.1 Add to `frontend/src/features/face/types.ts`:
  - `FacePhoto` interface: `id`, `photo_url`, `thumbnail_url`, `position`
  - `AlbumPhotoResult` type for upload/delete operations

### Task 10: Create Album API Service (AC: #1)

- [ ] 10.1 Add to `frontend/src/features/face/services/faceApi.ts`:
  - `getAlbumPhotos(): Promise<FacePhoto[]>`
  - `addAlbumPhoto(photo: File): Promise<FacePhotoResponse>`
  - `deleteAlbumPhoto(photoId: number): Promise<void>`
  - `reorderAlbumPhotos(order: number[]): Promise<void>`

### Task 11: Create usePhotoAlbum Composable (AC: #1, #2, #3, #5)

- [ ] 11.1 Create `frontend/src/features/face/composables/usePhotoAlbum.ts`
- [ ] 11.2 Implement reactive state: `photos`, `isLoading`, `isUploading`, `error`
- [ ] 11.3 Implement `fetchPhotos(): Promise<void>`
- [ ] 11.4 Implement `addPhoto(file: File): Promise<AlbumPhotoResult>`:
  - Client-side validation (type, size)
  - Check count limit before API call
  - Call API and update local state
- [ ] 11.5 Implement `deletePhoto(photoId: number): Promise<AlbumPhotoResult>`
- [ ] 11.6 Implement `reorderPhotos(newOrder: number[]): Promise<void>`
- [ ] 11.7 Implement `validateFile(file: File)` with same logic as profile photo
- [ ] 11.8 Add computed: `canAddMore`, `photoCount`, `isFull`

### Task 12: Create PhotoAlbumGrid Component (AC: #1, #3, #5)

- [ ] 12.1 Create `frontend/src/features/face/components/PhotoAlbumGrid.vue`
- [ ] 12.2 Display photos in 2x2 grid layout
- [ ] 12.3 Show empty slots as placeholders (up to 4 total)
- [ ] 12.4 Each photo has delete button (icon overlay on hover)
- [ ] 12.5 Implement drag-and-drop reordering (optional: use vue-draggable-plus)
- [ ] 12.6 Show loading state during operations
- [ ] 12.7 Props: `photos`, `isLoading`, `canAddMore`
- [ ] 12.8 Emits: `delete`, `reorder`, `add-click`

### Task 13: Create AlbumPhotoUpload Component (AC: #1, #2)

- [ ] 13.1 Create `frontend/src/features/face/components/AlbumPhotoUpload.vue`
- [ ] 13.2 Reuse upload logic from ProfilePhotoUpload (or extract shared logic)
- [ ] 13.3 Show "+" button when under 4 photos
- [ ] 13.4 Disable when `isFull`
- [ ] 13.5 Display error message when limit reached
- [ ] 13.6 Handle drag-and-drop for uploading
- [ ] 13.7 Props: `isFull`, `isUploading`, `error`
- [ ] 13.8 Emits: `upload`

### Task 14: Integrate Album into ProfileEditPage (AC: #1)

- [ ] 14.1 Update `frontend/src/pages/face/ProfileEditPage.vue`
- [ ] 14.2 Add "Album Photos" section below profile photo
- [ ] 14.3 Include PhotoAlbumGrid and AlbumPhotoUpload components
- [ ] 14.4 Use usePhotoAlbum composable for state management
- [ ] 14.5 Handle all events and display success/error feedback

### Task 15: Frontend Tests (AC: #1, #2, #3, #5)

- [ ] 15.1 Create `frontend/src/features/face/composables/__tests__/usePhotoAlbum.spec.ts`
- [ ] 15.2 Test initial state (empty photos)
- [ ] 15.3 Test fetchPhotos success and loading states
- [ ] 15.4 Test addPhoto success
- [ ] 15.5 Test addPhoto rejection when full (4 photos)
- [ ] 15.6 Test addPhoto validation (file type, size)
- [ ] 15.7 Test deletePhoto success
- [ ] 15.8 Test reorderPhotos success

- [ ] 15.9 Create `frontend/src/features/face/components/__tests__/PhotoAlbumGrid.spec.ts`
- [ ] 15.10 Test renders empty grid with placeholders
- [ ] 15.11 Test renders photos in grid
- [ ] 15.12 Test delete button emits event
- [ ] 15.13 Test loading state display

- [ ] 15.14 Create `frontend/src/features/face/components/__tests__/AlbumPhotoUpload.spec.ts`
- [ ] 15.15 Test shows add button when not full
- [ ] 15.16 Test disabled state when full
- [ ] 15.17 Test file selection triggers upload event
- [ ] 15.18 Test error message display

## Dev Notes

### Reuse from Story 3-1

**This story heavily reuses patterns from Story 3-1 (Face Profile Photo Upload):**
- Same file validation rules (JPG/PNG, max 5MB)
- Same thumbnail generation (150x150 with Intervention Image)
- Same storage path pattern (just use `/albums/` subdirectory)
- Same API response envelope format
- Same error message translations

**Key difference:** This is a one-to-many relationship (Face has many photos) vs one-to-one (Face has one profile photo).

### Storage Path Convention

```
storage/app/public/
├── avatars/
│   └── faces/
│       ├── {uuid}.jpg              # Profile photos (from 3-1)
│       ├── thumbnails/
│       │   └── {uuid}.jpg          # Profile photo thumbnails
│       ├── albums/
│       │   └── {uuid}.jpg          # Album photos
│       └── albums/thumbnails/
│           └── {uuid}.jpg          # Album photo thumbnails
```

### Database Schema

```sql
CREATE TABLE face_photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    face_id BIGINT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    thumbnail VARCHAR(255) NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (face_id) REFERENCES faces(id) ON DELETE CASCADE,
    UNIQUE KEY unique_position (face_id, position)
);
```

### Position Reordering Logic

When a photo is deleted, reorder remaining photos:
```php
// After deleting position 2 from [1,2,3,4]:
// Result: [1,2,3] (positions shift down)

public function reorderAfterDelete(Face $face): void
{
    $photos = $face->photos()->orderBy('position')->get();
    foreach ($photos as $index => $photo) {
        $photo->update(['position' => $index + 1]);
    }
}
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/face/album` | List all album photos |
| POST | `/api/v1/face/album` | Add photo (rate limited) |
| DELETE | `/api/v1/face/album/{photo}` | Delete photo |
| PUT | `/api/v1/face/album/reorder` | Reorder photos |

**Reorder Request Body:**
```json
{
  "order": [3, 1, 4, 2]  // Array of photo IDs in new order
}
```

### Frontend Grid Layout

Use CSS Grid for 2x2 layout:
```css
.album-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.album-slot {
  aspect-ratio: 1;
  border-radius: 0.5rem;
  overflow: hidden;
}
```

### Error Messages (French)

| Scenario | Message |
|----------|---------|
| Album full | "Maximum 4 photos atteint" |
| Invalid type | "Le fichier doit être au format JPG ou PNG" |
| Too large | "Le fichier ne doit pas dépasser 5 Mo" |
| Upload failed | "Échec de l'upload de la photo" |
| Delete failed | "Échec de la suppression de la photo" |

### Previous Story Intelligence (from 3-1)

- **ProfilePhotoService pattern:** Reuse the same service structure
- **Intervention Image 3.x:** Use `Image::read()->cover(150, 150)->toJpg(quality: 85)`
- **Storage paths:** Use `Storage::disk('public')` with symlink
- **Rate limiting:** Already configured in face.php routes
- **FormRequest authorization:** Pattern established for Face ownership check

### Security Considerations

- Validate MIME type server-side (not just extension)
- Generate unique filenames (UUID) to prevent path traversal
- Scope route model binding to authenticated Face's photos only
- Rate limit upload endpoint (10/min)
- Clean up orphaned files if database update fails

### Project Structure Notes

**Backend files to create:**
```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/Face/
│   │   │   └── AlbumController.php
│   │   ├── Requests/Face/
│   │   │   └── AddAlbumPhotoRequest.php
│   │   └── Resources/
│   │       └── FacePhotoResource.php
│   ├── Models/
│   │   └── FacePhoto.php
│   └── Services/
│       └── PhotoAlbumService.php
├── database/migrations/
│   └── xxxx_create_face_photos_table.php
└── tests/Feature/Face/
    └── AlbumTest.php
```

**Frontend files to create:**
```
frontend/src/
├── features/face/
│   ├── components/
│   │   ├── PhotoAlbumGrid.vue
│   │   ├── AlbumPhotoUpload.vue
│   │   └── __tests__/
│   │       ├── PhotoAlbumGrid.spec.ts
│   │       └── AlbumPhotoUpload.spec.ts
│   ├── composables/
│   │   ├── usePhotoAlbum.ts
│   │   └── __tests__/
│   │       └── usePhotoAlbum.spec.ts
│   └── types.ts (update existing)
└── pages/face/
    └── ProfileEditPage.vue (update existing)
```

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 3.2]
- [Source: _bmad-output/project-context.md#Framework Rules]
- [Source: _bmad-output/implementation-artifacts/3-1-face-profile-photo-upload.md]
- [Source: Laravel 12 File Validation - context7]
- [Source: Intervention Image 3.x Documentation - context7]

## Dev Agent Record

### Agent Model Used

{{agent_model_name_version}}

### Debug Log References

### Completion Notes List

### File List

