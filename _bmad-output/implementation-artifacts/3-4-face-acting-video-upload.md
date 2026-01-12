# Story 3.4: Face Acting Video Upload

Status: done

## Story

As a **Face**,
I want **to upload an acting video demonstrating my talent**,
So that **producers can evaluate my acting capabilities**.

## Acceptance Criteria

1. **Given** I am on my profile edit page, **When** I upload an acting video (MP4/MOV/AVI, max 50MB, max 2 min), **Then** the video is stored separately from the presentation video and a thumbnail is auto-generated and both videos are clearly labeled on my profile

2. **Given** I want to replace my acting video, **When** I upload a new one, **Then** the old video is replaced

3. **Given** I upload a video larger than 50MB, **When** the validation runs, **Then** I see an error "Vidéo trop volumineuse (max 50MB)"

4. **Given** I upload a video longer than 2 minutes, **When** the validation runs, **Then** I see an error "Vidéo trop longue (max 2 minutes)"

5. **Given** I upload an invalid file format, **When** the validation runs, **Then** I see an error "Format non supporté (MP4, MOV, AVI uniquement)"

6. **Given** the upload is in progress, **When** I view the upload status, **Then** I see a progress bar with percentage

7. **Given** I want to delete my acting video, **When** I click delete and confirm via modal, **Then** the video and thumbnail are removed and I see a toast notification

**(FR12)**

## Tasks / Subtasks

### Task 1: Update Face Model and Database Schema (AC: #1, #2)

- [x] 1.1 Create migration `add_acting_video_to_faces`:
  - `acting_video` (string, max 255, nullable) - filename
  - `acting_video_thumbnail` (string, max 255, nullable) - thumbnail filename
- [x] 1.2 Update Face model:
  - Add to fillable: `acting_video`, `acting_video_thumbnail`
  - Add accessor `actingVideoUrl()` returning full URL
  - Add accessor `actingVideoThumbnailUrl()` returning full URL
  - Add to appends: `acting_video_url`, `acting_video_thumbnail_url`
- [x] 1.3 Run migration and verify schema

### Task 2: Create ActingVideoService (AC: #1, #2, #3, #4)

- [x] 2.1 Create `app/Services/ActingVideoService.php`
- [x] 2.2 Define storage constants (REUSE pattern from PresentationVideoService):
  ```php
  private const STORAGE_PATH = 'videos/faces/acting';
  private const THUMBNAIL_PATH = 'videos/faces/acting/thumbnails';
  private const MAX_SIZE_BYTES = 50 * 1024 * 1024; // 50MB
  private const MAX_DURATION_SECONDS = 120; // 2 minutes
  ```
- [x] 2.3 Implement `uploadActingVideo(Face $face, UploadedFile $video): array`:
  - Delete old video if exists (use DB::transaction for consistency)
  - Generate unique filename with UUID
  - Store video in storage path
  - Generate thumbnail from first frame using FFmpeg
  - Update Face model
  - Return `['video' => filename, 'thumbnail' => thumbnailFilename]`
- [x] 2.4 Implement `deleteActingVideo(Face $face): bool`:
  - Wrap in DB::transaction
  - Delete video file from storage
  - Delete thumbnail file from storage
  - Clear database fields
- [x] 2.5 Reuse `generateThumbnail()` and `getVideoDuration()` from PresentationVideoService (consider extracting to a shared VideoThumbnailService or trait)

### Task 3: Create Acting Video Validation Form Request (AC: #3, #4, #5)

- [x] 3.1 Create `app/Http/Requests/Face/UploadActingVideoRequest.php`
- [x] 3.2 COPY validation rules from `UploadPresentationVideoRequest.php` (same constraints):
  ```php
  use Illuminate\Validation\Rules\File;

  return [
      'video' => [
          'required',
          File::types(['mp4', 'mov', 'avi'])
              ->max(50 * 1024), // 50MB in KB
          'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/avi',
      ],
  ];
  ```
- [x] 3.3 Implement custom validation rule for duration (max 2 minutes)
- [x] 3.4 Implement French error messages (same as presentation video)
- [x] 3.5 Implement authorization check (user must be Face owner)

### Task 4: Create Acting Video Controller (AC: #1, #2, #7)

- [x] 4.1 Create `app/Http/Controllers/Api/V1/Face/ActingVideoController.php`
- [x] 4.2 Implement `show(): JsonResponse` - get current acting video info
- [x] 4.3 Implement `store(UploadActingVideoRequest $request): JsonResponse`:
  - Use ActingVideoService for business logic
  - Return standard API envelope with video URLs
- [x] 4.4 Implement `destroy(): JsonResponse`:
  - Delete video and thumbnail
  - Return success message
- [x] 4.5 Use API envelope format for all responses

### Task 5: Update FaceResource API Resource (AC: #1)

- [x] 5.1 Update `app/Http/Resources/FaceResource.php` to include:
  - `acting_video_url`
  - `acting_video_thumbnail_url`

### Task 6: Add API Routes (AC: #1)

- [x] 6.1 Add routes to `routes/api/face.php`:
  - `GET /api/v1/face/acting-video` - get current video info
  - `POST /api/v1/face/acting-video` - upload video (rate limited: 10/min)
  - `DELETE /api/v1/face/acting-video` - delete video
- [x] 6.2 Apply `auth:sanctum` middleware
- [x] 6.3 Apply rate limiting to upload endpoint: `throttle:10,1`

### Task 7: Backend Tests (AC: #1, #2, #3, #4, #5, #7)

- [x] 7.1 Create `tests/Feature/Face/ActingVideoTest.php`
- [x] 7.2 Test successful video upload (mock FFmpeg for CI)
- [x] 7.3 Test thumbnail generation on upload
- [x] 7.4 Test video replacement (old files deleted)
- [x] 7.5 Test rejection of videos > 50MB
- [x] 7.6 Test rejection of videos > 2 minutes duration
- [x] 7.7 Test rejection of invalid file types
- [x] 7.8 Test successful video deletion
- [x] 7.9 Test unauthorized access (non-owner)
- [x] 7.10 Test rate limiting on upload endpoint
- [x] 7.11 Test get video info endpoint

### Task 8: Update Frontend Types (AC: #1)

- [x] 8.1 Add to `frontend/src/features/face/types.ts`:
  ```typescript
  interface ActingVideoInfo {
    acting_video_url: string | null;
    acting_video_thumbnail_url: string | null;
  }
  ```
- [x] 8.2 Ensure `VideoUploadProgress` and result types are reusable (already exist from 3-3)

### Task 9: Create Acting Video API Service (AC: #1, #6)

- [x] 9.1 Add to `frontend/src/features/face/services/faceApi.ts`:
  ```typescript
  getActingVideo(): Promise<ActingVideoInfo>
  uploadActingVideo(
    video: File,
    onProgress?: (progress: VideoUploadProgress) => void
  ): Promise<VideoUploadResult>
  deleteActingVideo(): Promise<void>
  ```

### Task 10: Create useActingVideo Composable (AC: #1, #2, #3, #4, #5, #6)

- [x] 10.1 Create `frontend/src/features/face/composables/useActingVideo.ts`
- [x] 10.2 COPY structure from `usePresentationVideo.ts` - same patterns:
  - Reactive state: `videoInfo`, `isLoading`, `isUploading`, `isDeleting`, `uploadProgress`, `error`
  - `fetchVideoInfo()`, `uploadVideo()`, `deleteVideo()`
  - Client-side validation (type, size)
- [x] 10.3 Ensure return type matches presentation video composable

### Task 11: Create ActingVideoUpload Component (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 11.1 Create `frontend/src/features/face/components/ActingVideoUpload.vue`
- [x] 11.2 COPY structure from `PresentationVideoUpload.vue` - same UI patterns:
  - Display video thumbnail with play overlay
  - Upload zone with drag-and-drop
  - Progress bar during upload
  - Delete button with ConfirmModal
  - Error message display
- [x] 11.3 Props: `videoInfo`, `isUploading`, `isDeleting`, `error`, `uploadProgress`
- [x] 11.4 Emits: `upload`, `delete`

### Task 12: Integrate Acting Video into ProfileEditPage (AC: #1)

- [x] 12.1 Update `frontend/src/pages/face/ProfileEditPage.vue`
- [x] 12.2 Add "Vidéo d'Acting" section AFTER "Vidéo de Présentation" section
- [x] 12.3 Import and use `useActingVideo` composable
- [x] 12.4 Import and use `ActingVideoUpload` component
- [x] 12.5 Handle upload and delete events with toast notifications
- [x] 12.6 Fetch acting video info on mount

### Task 13: Frontend Tests (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 13.1 Create `frontend/src/features/face/composables/__tests__/useActingVideo.spec.ts`
- [x] 13.2 COPY tests from `usePresentationVideo.spec.ts` - same test cases
- [x] 13.3 Test initial state, fetch, upload, delete, validation

- [x] 13.4 Create `frontend/src/features/face/components/__tests__/ActingVideoUpload.spec.ts`
- [x] 13.5 COPY tests from `PresentationVideoUpload.spec.ts` - same test cases
- [x] 13.6 Test upload zone, progress bar, delete modal, error display

## Dev Notes

### CRITICAL: Reuse Pattern from Story 3-3

This story is **nearly identical** to Story 3-3 (Presentation Video Upload). The only differences are:
- Different storage path (`acting/` instead of `presentation/`)
- Different field names (`acting_video` instead of `presentation_video`)
- Different section label ("Vidéo d'Acting" instead of "Vidéo de Présentation")

**Implementation strategy: COPY, RENAME, and ADAPT** - Do not reinvent patterns!

### Storage Path Convention

```
storage/app/public/videos/faces/
├── presentation/
│   ├── {uuid}.mp4
│   └── thumbnails/
│       └── {uuid}.jpg
└── acting/                     # NEW for this story
    ├── {uuid}.mp4
    └── thumbnails/
        └── {uuid}.jpg
```

**Create directories:**
```bash
mkdir -p storage/app/public/videos/faces/acting/thumbnails
```

### FFmpeg Dependency

FFmpeg is already installed and configured from Story 3-3. The `config/ffmpeg.php` file exists.

### Code Reuse Strategy

**Option 1: Direct Copy (Quick)**
- Copy files, rename classes, change constants
- Fastest but duplicates code

**Option 2: Extract Shared Logic (Better for maintenance)**
- Extract `VideoUploadService` with shared methods:
  - `generateThumbnail()`
  - `getVideoDuration()`
  - `storeVideo()`
  - `deleteVideoFile()`
- Both `PresentationVideoService` and `ActingVideoService` use it

**Recommended for MVP:** Option 1 (Direct Copy) to maintain momentum, with a TODO for future refactoring.

### Learnings from Story 3-3 (MUST FOLLOW)

1. **DB Transactions**: Always wrap file + database operations in `DB::transaction()`
2. **Authorization**: Use `Face::class` constant, not hardcoded string
3. **Accessibility**: Add `aria-label` to delete buttons
4. **Error Harmonization**: Keep frontend/backend error messages consistent
5. **Confirmation Modal**: Use `ConfirmModal.vue` for destructive actions
6. **Toast Notifications**: Use toast for success feedback on deletion
7. **Test Mocking**: Use `Object.defineProperty` to mock file.size for large file tests
8. **Async Tests**: Use `vi.waitFor()` for timing-sensitive tests
9. **PHP Upload Limits**: Run dev server with higher limits:
   ```bash
   php -d upload_max_filesize=64M -d post_max_size=64M artisan serve
   ```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/face/acting-video` | Get current video info |
| POST | `/api/v1/face/acting-video` | Upload video (rate limited) |
| DELETE | `/api/v1/face/acting-video` | Delete video |

### Error Messages (French) - Same as Presentation Video

| Scenario | Message |
|----------|---------|
| Video required | "La vidéo est requise" |
| Invalid type | "Format non supporté (MP4, MOV, AVI uniquement)" |
| Too large (>50MB) | "Vidéo trop volumineuse (max 50MB)" |
| Too long (>2min) | "Vidéo trop longue (max 2 minutes)" |
| Upload failed | "Échec de l'upload de la vidéo" |
| Delete failed | "Échec de la suppression de la vidéo" |
| Delete success | "Vidéo supprimée avec succès" |

### Project Structure Notes

**Backend files to create:**
```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/Face/
│   │   │   └── ActingVideoController.php
│   │   └── Requests/Face/
│   │       └── UploadActingVideoRequest.php
│   └── Services/
│       └── ActingVideoService.php
├── database/migrations/
│   └── xxxx_add_acting_video_to_faces.php
└── tests/Feature/Face/
    └── ActingVideoTest.php
```

**Frontend files to create:**
```
frontend/src/
├── features/face/
│   ├── components/
│   │   ├── ActingVideoUpload.vue
│   │   └── __tests__/
│   │       └── ActingVideoUpload.spec.ts
│   ├── composables/
│   │   ├── useActingVideo.ts
│   │   └── __tests__/
│   │       └── useActingVideo.spec.ts
│   └── types.ts (update existing)
│   └── services/faceApi.ts (update existing)
└── pages/face/
    └── ProfileEditPage.vue (update existing)
```

### Test Count Expectations

Based on Story 3-3 patterns:
- Backend: ~11-17 tests (ActingVideoTest.php)
- Frontend Composable: ~20-25 tests (useActingVideo.spec.ts)
- Frontend Component: ~15-20 tests (ActingVideoUpload.spec.ts)

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 3.4 - FR12]
- [Source: _bmad-output/project-context.md#Technology Stack]
- [Source: _bmad-output/implementation-artifacts/3-3-face-presentation-video-upload.md - Complete reference implementation]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

N/A - No significant debug issues encountered.

### Completion Notes List

1. **Implementation Strategy**: Followed the "COPY, RENAME, and ADAPT" approach from Story 3-3 as recommended. All patterns mirror the PresentationVideoService implementation.

2. **Backend Tests**: Created 19 comprehensive tests covering all acceptance criteria. Tests include upload, delete, validation (size, type, duration, WMV/WebM rejection), authorization, and rate limiting. All tests passing (118 total backend tests).

3. **Frontend Tests**:
   - useActingVideo.spec.ts: 23 tests covering composable functionality
   - ActingVideoUpload.spec.ts: 16 tests covering component behavior
   - All 39 new tests passing (233 total frontend tests)

4. **TypeScript**: All type definitions properly exported and type-check passing.

5. **DB Transactions**: All file + database operations wrapped in `DB::transaction()` for data consistency.

6. **FFmpeg**: Reused existing FFmpeg configuration from Story 3-3 for thumbnail generation.

### File List

**Backend - Created:**
- `backend/database/migrations/2026_01_12_005738_add_acting_video_to_faces.php`
- `backend/app/Services/ActingVideoService.php`
- `backend/app/Http/Requests/Face/UploadActingVideoRequest.php`
- `backend/app/Http/Controllers/Api/V1/Face/ActingVideoController.php`
- `backend/tests/Feature/Face/ActingVideoTest.php`

**Backend - Modified:**
- `backend/app/Models/Face.php` - Added acting_video fields, accessors, and appends
- `backend/app/Http/Resources/FaceResource.php` - Added acting video URLs
- `backend/routes/api/face.php` - Added acting video routes

**Frontend - Created:**
- `frontend/src/features/face/composables/useActingVideo.ts`
- `frontend/src/features/face/components/ActingVideoUpload.vue`
- `frontend/src/features/face/composables/__tests__/useActingVideo.spec.ts`
- `frontend/src/features/face/components/__tests__/ActingVideoUpload.spec.ts`

**Frontend - Modified:**
- `frontend/src/features/face/types.ts` - Added ActingVideoInfo, ActingVideoResponse, ActingVideoResult types
- `frontend/src/features/face/services/faceApi.ts` - Added acting video API methods
- `frontend/src/pages/face/ProfileEditPage.vue` - Integrated ActingVideoUpload component

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-01-12 | Story 3.4 implemented - Face Acting Video Upload feature complete | Claude Opus 4.5 |
| 2026-01-12 | Code review fixes: directory creation, FFmpeg error handling, authorization refactor, removed non-standard MIME type, added WMV/WebM rejection tests, accessibility improvements | Claude Opus 4.5 |
