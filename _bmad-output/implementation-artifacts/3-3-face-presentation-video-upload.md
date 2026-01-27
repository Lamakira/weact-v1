# Story 3.3: Face Presentation Video Upload

Status: done

## Story

As a **Face**,
I want **to upload a presentation video where I introduce myself**,
So that **producers can see my personality and speaking ability**.

## Acceptance Criteria

1. **Given** I am on my profile edit page, **When** I upload a presentation video (MP4/MOV/AVI, max 50MB, max 2 min), **Then** the video is stored with a progress bar showing upload status and a thumbnail is auto-generated from the first frame and the video is playable on my profile

2. **Given** I upload a video larger than 50MB, **When** the validation runs, **Then** I see an error "Vidéo trop volumineuse (max 50MB)"

3. **Given** I upload a video longer than 2 minutes, **When** the validation runs, **Then** I see an error "Vidéo trop longue (max 2 minutes)"

4. **Given** the upload is in progress, **When** I view the upload status, **Then** I see a progress bar with percentage

5. **Given** I already have a presentation video, **When** I upload a new one, **Then** the old video is replaced

6. **Given** I upload an invalid file format, **When** the validation runs, **Then** I see an error "Format non supporté (MP4, MOV, AVI uniquement)"

**(FR11)**

## Tasks / Subtasks

### Task 1: Update Face Model and Database Schema (AC: #1, #5)

- [x] 1.1 Create migration `add_presentation_video_to_faces`:
  - `presentation_video` (string, max 255, nullable) - filename
  - `presentation_video_thumbnail` (string, max 255, nullable) - thumbnail filename
- [x] 1.2 Update Face model:
  - Add to fillable: `presentation_video`, `presentation_video_thumbnail`
  - Add accessor `presentationVideoUrl()` returning full URL
  - Add accessor `presentationVideoThumbnailUrl()` returning full URL
  - Add to appends: `presentation_video_url`, `presentation_video_thumbnail_url`
- [x] 1.3 Run migration and verify schema

### Task 2: Install FFmpeg PHP Wrapper (AC: #1)

- [x] 2.1 Install php-ffmpeg package: `composer require php-ffmpeg/php-ffmpeg`
- [x] 2.2 Verify FFmpeg is installed on the system (required dependency)
- [x] 2.3 Configure FFmpeg binaries path in `.env` if not in system PATH:
  ```
  FFMPEG_BINARY=/usr/bin/ffmpeg
  FFPROBE_BINARY=/usr/bin/ffprobe
  ```
- [x] 2.4 Create `config/ffmpeg.php` configuration file if needed

### Task 3: Create PresentationVideoService (AC: #1, #2, #3, #5)

- [x] 3.1 Create `app/Services/PresentationVideoService.php`
- [x] 3.2 Define storage constants:
  ```php
  private const STORAGE_PATH = 'videos/faces/presentation';
  private const THUMBNAIL_PATH = 'videos/faces/presentation/thumbnails';
  private const MAX_SIZE_BYTES = 50 * 1024 * 1024; // 50MB
  private const MAX_DURATION_SECONDS = 120; // 2 minutes
  private const THUMBNAIL_WIDTH = 640;
  private const THUMBNAIL_HEIGHT = 360;
  ```
- [x] 3.3 Implement `uploadPresentationVideo(Face $face, UploadedFile $video): array`:
  - Delete old video if exists
  - Generate unique filename with UUID
  - Store video in storage path
  - Generate thumbnail from first frame using FFmpeg
  - Update Face model
  - Return `['video' => filename, 'thumbnail' => thumbnailFilename]`
- [x] 3.4 Implement `deletePresentationVideo(Face $face): bool`:
  - Delete video file from storage
  - Delete thumbnail file from storage
  - Clear database fields
- [x] 3.5 Implement `generateThumbnail(string $videoPath): string`:
  - Use FFmpeg to extract first frame
  - Save as JPEG with quality 85
  - Return thumbnail filename
- [x] 3.6 Implement `getVideoDuration(UploadedFile $video): float`:
  - Use FFprobe to get video duration in seconds
  - Return duration for validation

### Task 4: Create Video Validation Form Request (AC: #2, #3, #6)

- [x] 4.1 Create `app/Http/Requests/Face/UploadPresentationVideoRequest.php`
- [x] 4.2 Implement validation rules using Laravel 12 fluent File rule:
  ```php
  use Illuminate\Validation\Rules\File;

  return [
      'video' => [
          'required',
          File::types(['mp4', 'mov', 'avi'])
              ->max(50 * 1024), // 50MB in KB
      ],
  ];
  ```
- [x] 4.3 Add MIME type validation: `'video' => 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/avi'`
- [x] 4.4 Implement custom validation rule for duration (max 2 minutes):
  - Use `withValidator()` method to add after hook
  - Call `PresentationVideoService::getVideoDuration()`
  - Fail if duration > 120 seconds
- [x] 4.5 Implement French error messages:
  - `video.required` => "La vidéo est requise"
  - `video.mimetypes` => "Format non supporté (MP4, MOV, AVI uniquement)"
  - `video.max` => "Vidéo trop volumineuse (max 50MB)"
  - Custom duration error => "Vidéo trop longue (max 2 minutes)"
- [x] 4.6 Implement authorization check (user must be Face owner)

### Task 5: Create Presentation Video Controller (AC: #1, #5)

- [x] 5.1 Create `app/Http/Controllers/Api/V1/Face/PresentationVideoController.php`
- [x] 5.2 Implement `show(): JsonResponse` - get current video info (URL, thumbnail)
- [x] 5.3 Implement `store(UploadPresentationVideoRequest $request): JsonResponse`:
  - Use PresentationVideoService for business logic
  - Return standard API envelope with video URLs
- [x] 5.4 Implement `destroy(): JsonResponse`:
  - Delete video and thumbnail
  - Return success message
- [x] 5.5 Use API envelope format for all responses

### Task 6: Update FaceResource API Resource (AC: #1)

- [x] 6.1 Update `app/Http/Resources/FaceResource.php` to include:
  - `presentation_video_url`
  - `presentation_video_thumbnail_url`

### Task 7: Add API Routes (AC: #1)

- [x] 7.1 Add routes to `routes/api/face.php`:
  - `GET /api/v1/face/presentation-video` - get current video info
  - `POST /api/v1/face/presentation-video` - upload video (rate limited: 10/min)
  - `DELETE /api/v1/face/presentation-video` - delete video
- [x] 7.2 Apply `auth:sanctum` middleware
- [x] 7.3 Apply rate limiting to upload endpoint: `throttle:10,1`

### Task 8: Backend Tests (AC: #1, #2, #3, #5, #6)

- [x] 8.1 Create `tests/Feature/Face/PresentationVideoTest.php`
- [x] 8.2 Test successful video upload (mock FFmpeg for CI)
- [x] 8.3 Test thumbnail generation on upload
- [x] 8.4 Test video replacement (old files deleted)
- [x] 8.5 Test rejection of videos > 50MB
- [x] 8.6 Test rejection of videos > 2 minutes duration
- [x] 8.7 Test rejection of invalid file types (PDF, TXT, etc.)
- [x] 8.8 Test successful video deletion
- [x] 8.9 Test unauthorized access (non-owner)
- [x] 8.10 Test rate limiting on upload endpoint
- [x] 8.11 Test get video info endpoint
- [x] 8.12 Create test video fixtures or use mocking strategy

### Task 9: Create Frontend Types (AC: #1)

- [x] 9.1 Add to `frontend/src/features/face/types.ts`:
  ```typescript
  interface PresentationVideo {
    presentation_video_url: string | null;
    presentation_video_thumbnail_url: string | null;
  }

  interface VideoUploadProgress {
    loaded: number;
    total: number;
    percentage: number;
  }

  interface VideoUploadResult {
    success: boolean;
    message?: string;
    video_url?: string;
    thumbnail_url?: string;
  }
  ```

### Task 10: Create Presentation Video API Service (AC: #1, #4)

- [x] 10.1 Add to `frontend/src/features/face/services/faceApi.ts`:
  ```typescript
  getPresentationVideo(): Promise<PresentationVideo>
  uploadPresentationVideo(
    video: File,
    onProgress?: (progress: VideoUploadProgress) => void
  ): Promise<VideoUploadResult>
  deletePresentationVideo(): Promise<void>
  ```
- [x] 10.2 Implement upload with Axios onUploadProgress for progress tracking:
  - Use `axios.onUploadProgress` event
  - Calculate percentage: `(loaded / total) * 100`

### Task 11: Create usePresentationVideo Composable (AC: #1, #2, #3, #4, #5, #6)

- [x] 11.1 Create `frontend/src/features/face/composables/usePresentationVideo.ts`
- [x] 11.2 Implement reactive state:
  ```typescript
  const videoUrl = ref<string | null>(null);
  const thumbnailUrl = ref<string | null>(null);
  const isLoading = ref(false);
  const isUploading = ref(false);
  const uploadProgress = ref<VideoUploadProgress | null>(null);
  const error = ref<string | null>(null);
  ```
- [x] 11.3 Implement `fetchVideo(): Promise<void>` - get current video info
- [x] 11.4 Implement `uploadVideo(file: File): Promise<VideoUploadResult>`:
  - Client-side validation (type, size)
  - Call API with progress callback
  - Update local state on success
- [x] 11.5 Implement `deleteVideo(): Promise<void>`
- [x] 11.6 Implement client-side validation `validateVideo(file: File)`:
  - Check MIME type (video/mp4, video/quicktime, video/x-msvideo)
  - Check file size < 50MB
  - Note: Duration validation happens server-side (requires FFprobe)
- [x] 11.7 Add computed: `hasVideo`, `canUpload`

### Task 12: Create PresentationVideoUpload Component (AC: #1, #2, #3, #4, #6)

- [x] 12.1 Create `frontend/src/features/face/components/PresentationVideoUpload.vue`
- [x] 12.2 Display current video thumbnail if exists (with play icon overlay)
- [x] 12.3 Show upload button/zone when no video
- [x] 12.4 Show upload progress bar during upload:
  - Use Tailwind progress bar styling
  - Display percentage text
- [x] 12.5 Handle drag-and-drop for video files
- [x] 12.6 Display error messages (type, size, duration)
- [x] 12.7 Show delete button when video exists
- [x] 12.8 Props: `videoUrl`, `thumbnailUrl`, `isUploading`, `uploadProgress`, `error`
- [x] 12.9 Emits: `upload`, `delete`

### Task 13: Create VideoPlayer Component (AC: #1)

- [x] 13.1 Video player integrated directly into PresentationVideoUpload.vue (simpler architecture)
- [x] 13.2 Use HTML5 `<video>` element with controls
- [x] 13.3 Show thumbnail as poster image
- [x] 13.4 Support responsive aspect ratio (16:9)
- [x] 13.5 Props handled via parent component props
- [x] 13.6 Style with Tailwind for consistent look

### Task 14: Integrate Video Upload into ProfileEditPage (AC: #1)

- [x] 14.1 Update `frontend/src/pages/face/ProfileEditPage.vue`
- [x] 14.2 Add "Vidéo de Présentation" section after Album Photos section
- [x] 14.3 Include PresentationVideoUpload component
- [x] 14.4 Use usePresentationVideo composable for state management
- [x] 14.5 Handle all events and display success/error feedback with toast notifications

### Task 15: Frontend Tests (AC: #1, #2, #3, #4, #5, #6)

- [x] 15.1 Create `frontend/src/features/face/composables/__tests__/usePresentationVideo.spec.ts`
- [x] 15.2 Test initial state (no video)
- [x] 15.3 Test fetchVideo success and loading states
- [x] 15.4 Test uploadVideo success with progress updates
- [x] 15.5 Test uploadVideo rejection for invalid type
- [x] 15.6 Test uploadVideo rejection for oversized file
- [x] 15.7 Test deleteVideo success
- [x] 15.8 Test validateVideo function

- [x] 15.9 Create `frontend/src/features/face/components/__tests__/PresentationVideoUpload.spec.ts`
- [x] 15.10 Test renders upload zone when no video
- [x] 15.11 Test renders thumbnail and delete when video exists
- [x] 15.12 Test progress bar display during upload
- [x] 15.13 Test error message display
- [x] 15.14 Test file selection triggers upload event

- [x] 15.15 VideoPlayer tests integrated into PresentationVideoUpload tests (component combined)
- [x] 15.16 Test renders video element with correct src
- [x] 15.17 Test renders poster image
- [x] 15.18 Test controls are visible

## Dev Notes

### FFmpeg Dependency

**CRITICAL:** This story requires FFmpeg to be installed on the server for thumbnail generation.

**Installation (Ubuntu/Debian):**
```bash
sudo apt-get update
sudo apt-get install ffmpeg
```

**Installation (macOS with Homebrew):**
```bash
brew install ffmpeg
```

**PHP-FFMpeg Package:**
```bash
composer require php-ffmpeg/php-ffmpeg
```

**Basic Usage for Thumbnail Generation:**
```php
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;

$ffmpeg = FFMpeg::create([
    'ffmpeg.binaries'  => config('ffmpeg.ffmpeg_binary', '/usr/bin/ffmpeg'),
    'ffprobe.binaries' => config('ffmpeg.ffprobe_binary', '/usr/bin/ffprobe'),
]);

$video = $ffmpeg->open($videoPath);
$video
    ->frame(TimeCode::fromSeconds(0)) // First frame
    ->save($thumbnailPath);
```

**Alternative: Direct FFmpeg Command:**
```bash
ffmpeg -i in.avi -frames:v 1 -f image2 thumbnail.jpg
```

### Storage Path Convention

```
storage/app/public/
├── avatars/
│   └── faces/
│       ├── {uuid}.jpg              # Profile photos (from 3-1)
│       ├── thumbnails/
│       ├── albums/                 # Album photos (from 3-2)
│       └── albums/thumbnails/
├── videos/
│   └── faces/
│       ├── presentation/
│       │   ├── {uuid}.mp4          # Presentation videos
│       │   └── thumbnails/
│       │       └── {uuid}.jpg      # Video thumbnails
│       └── acting/                 # (For 3-4)
│           └── ...
```

### Video Validation Strategy

**Client-side (Frontend):**
- File type (MIME): `video/mp4`, `video/quicktime`, `video/x-msvideo`
- File size: < 50MB
- Duration: Cannot validate without reading video (server-side only)

**Server-side (Laravel):**
- MIME type validation using Laravel's `mimetypes` rule
- File size using `max` rule (50 * 1024 KB)
- Duration using FFprobe via custom validation rule

**Laravel 12 File Validation (from context7):**
```php
use Illuminate\Validation\Rules\File;

return [
    'video' => [
        'required',
        File::types(['mp4', 'mov', 'avi'])
            ->max('50mb'), // Supports unit suffixes
        'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/avi',
    ],
];
```

### Progress Tracking Implementation

**Frontend XMLHttpRequest approach:**
```typescript
async function uploadWithProgress(
  file: File,
  onProgress: (progress: VideoUploadProgress) => void
): Promise<Response> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    const formData = new FormData();
    formData.append('video', file);

    xhr.upload.addEventListener('progress', (event) => {
      if (event.lengthComputable) {
        onProgress({
          loaded: event.loaded,
          total: event.total,
          percentage: Math.round((event.loaded / event.total) * 100),
        });
      }
    });

    xhr.addEventListener('load', () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve(JSON.parse(xhr.responseText));
      } else {
        reject(new Error(xhr.responseText));
      }
    });

    xhr.addEventListener('error', () => reject(new Error('Upload failed')));

    xhr.open('POST', `${API_BASE_URL}/api/v1/face/presentation-video`);
    xhr.setRequestHeader('Authorization', `Bearer ${token}`);
    xhr.send(formData);
  });
}
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/face/presentation-video` | Get current video info |
| POST | `/api/v1/face/presentation-video` | Upload video (rate limited) |
| DELETE | `/api/v1/face/presentation-video` | Delete video |

**Upload Response:**
```json
{
  "data": {
    "presentation_video_url": "https://example.com/storage/videos/faces/presentation/abc123.mp4",
    "presentation_video_thumbnail_url": "https://example.com/storage/videos/faces/presentation/thumbnails/abc123.jpg"
  },
  "message": "Vidéo de présentation uploadée avec succès"
}
```

### Error Messages (French)

| Scenario | Message |
|----------|---------|
| Video required | "La vidéo est requise" |
| Invalid type | "Format non supporté (MP4, MOV, AVI uniquement)" |
| Too large (>50MB) | "Vidéo trop volumineuse (max 50MB)" |
| Too long (>2min) | "Vidéo trop longue (max 2 minutes)" |
| Upload failed | "Échec de l'upload de la vidéo" |
| Delete failed | "Échec de la suppression de la vidéo" |
| Delete success | "Vidéo supprimée avec succès" |

### Previous Story Intelligence (from 3-1 and 3-2)

**Patterns to reuse:**
- **Service pattern**: Same structure as `ProfilePhotoService` and `PhotoAlbumService`
- **Form Request authorization**: Pattern established for Face ownership check using `Face::class` constant
- **Storage disk usage**: Use `Storage::disk('public')` with symlink
- **Rate limiting**: Already configured in `face.php` routes - extend pattern
- **UUID filenames**: Same pattern for unique filename generation
- **API envelope format**: Same response structure

**Learnings from 3-2:**
- Use `DB::transaction()` for file + DB operations to ensure consistency
- Use `Face::class` constant instead of hardcoded strings like `'App\\Models\\Face'`
- Add `aria-label` attributes for accessibility on action buttons

### Test Mocking Strategy

**FFmpeg mocking for tests:**
```php
// In test setup
$this->mock(FFMpeg::class, function ($mock) {
    $mock->shouldReceive('open')
         ->andReturnSelf();
    $mock->shouldReceive('frame')
         ->andReturnSelf();
    $mock->shouldReceive('save')
         ->andReturn(true);
});
```

**Test video file creation:**
```php
// Create a minimal MP4 file for testing
$testVideo = UploadedFile::fake()->create('video.mp4', 1024, 'video/mp4');
```

### Security Considerations

- Validate MIME type server-side (not just extension)
- Generate unique filenames (UUID) to prevent path traversal
- Rate limit upload endpoint (10/min) to prevent abuse
- Clean up orphaned files if database update fails (use transactions)
- Scan uploaded files for basic malware signatures (optional for MVP)
- Verify file is actually a video using FFprobe before processing

### Performance Considerations

- Target upload time < 60s for 50MB file on reasonable connection
- Progress bar updates should be smooth (not jumpy)
- Thumbnail generation should happen async if possible (or be fast enough)
- Consider chunked upload for V2 (better resume capability)
- Store videos outside web root and serve via Laravel for access control

### Project Structure Notes

**Backend files to create:**
```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/Face/
│   │   │   └── PresentationVideoController.php
│   │   └── Requests/Face/
│   │       └── UploadPresentationVideoRequest.php
│   └── Services/
│       └── PresentationVideoService.php
├── config/
│   └── ffmpeg.php (optional)
├── database/migrations/
│   └── xxxx_add_presentation_video_to_faces.php
└── tests/Feature/Face/
    └── PresentationVideoTest.php
```

**Frontend files to create:**
```
frontend/src/
├── features/face/
│   ├── components/
│   │   ├── PresentationVideoUpload.vue
│   │   ├── VideoPlayer.vue
│   │   └── __tests__/
│   │       ├── PresentationVideoUpload.spec.ts
│   │       └── VideoPlayer.spec.ts
│   ├── composables/
│   │   ├── usePresentationVideo.ts
│   │   └── __tests__/
│   │       └── usePresentationVideo.spec.ts
│   └── types.ts (update existing)
└── pages/face/
    └── ProfileEditPage.vue (update existing)
```

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 3.3 - FR11]
- [Source: _bmad-output/project-context.md#Technology Stack]
- [Source: _bmad-output/implementation-artifacts/3-1-face-profile-photo-upload.md - ProfilePhotoService pattern]
- [Source: _bmad-output/implementation-artifacts/3-2-face-photo-album-management.md - Patterns and learnings]
- [Source: docs/planning-artifacts/architecture.md#Media Pipeline]
- [Source: Laravel 12 File Validation - context7]
- [Source: FFmpeg Thumbnail Generation - context7]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5

### Debug Log References

- Fixed frontend test for large file validation - used Object.defineProperty to mock file.size instead of creating actual 50MB files
- Fixed async timing test for upload state by using vi.waitFor() to handle async duration validation

### Completion Notes List

- ✅ Task 8 Complete: All 17 backend tests pass (PresentationVideoTest.php)
- ✅ Task 9 Complete: Frontend types defined in types.ts (PresentationVideoInfo, VideoUploadProgress, PresentationVideoResult)
- ✅ Task 10 Complete: API service methods in faceApi.ts (getPresentationVideo, uploadPresentationVideo, deletePresentationVideo)
- ✅ Task 11 Complete: usePresentationVideo composable with full state management, validation, and error handling
- ✅ Task 12 Complete: PresentationVideoUpload.vue component with drag-drop, progress bar, and video preview
- ✅ Task 13 Complete: Video player integrated directly into PresentationVideoUpload (simpler architecture)
- ✅ Task 14 Complete: ProfileEditPage.vue updated with video section after Album Photos
- ✅ Task 15 Complete: All 38 frontend tests pass (23 composable + 15 component)

### File List

**Backend (Created/Modified):**
- app/Http/Controllers/Api/V1/Face/PresentationVideoController.php (new)
- app/Http/Requests/Face/UploadPresentationVideoRequest.php (new)
- app/Services/PresentationVideoService.php (new)
- app/Models/Face.php (modified - added video fields)
- app/Http/Resources/FaceResource.php (modified - added video URLs)
- config/ffmpeg.php (new)
- database/migrations/2026_01_11_230516_add_presentation_video_to_faces_table.php (new)
- routes/api/face.php (modified - added video routes)
- tests/Feature/Face/PresentationVideoTest.php (new)
- composer.json (modified - php-ffmpeg dependency)
- composer.lock (modified)

**Frontend (Created/Modified):**
- frontend/src/features/face/types.ts (modified - added video types)
- frontend/src/features/face/services/faceApi.ts (modified - added video methods)
- frontend/src/features/face/composables/usePresentationVideo.ts (new)
- frontend/src/features/face/components/PresentationVideoUpload.vue (new)
- frontend/src/pages/face/ProfileEditPage.vue (modified - added video section)
- frontend/src/features/face/composables/__tests__/usePresentationVideo.spec.ts (new)
- frontend/src/features/face/components/__tests__/PresentationVideoUpload.spec.ts (new)

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-01-12 | Story implementation completed - all 15 tasks done | Claude Opus 4.5 |
| 2026-01-12 | Backend: Added video upload/delete endpoints with FFmpeg thumbnail generation | Claude Opus 4.5 |
| 2026-01-12 | Frontend: Added video upload component with progress bar and drag-drop | Claude Opus 4.5 |
| 2026-01-12 | Tests: 17 backend + 38 frontend tests passing | Claude Opus 4.5 |
| 2026-01-12 | Code Review: Fixed 5 MEDIUM issues, 2 LOW issues | Claude Opus 4.5 |
| 2026-01-12 | - Added DB transaction to deletePresentationVideo() | Claude Opus 4.5 |
| 2026-01-12 | - Harmonized error messages between frontend and backend | Claude Opus 4.5 |
| 2026-01-12 | - Added deletion confirmation dialog | Claude Opus 4.5 |
| 2026-01-12 | - Added aria-label to delete button for accessibility | Claude Opus 4.5 |
| 2026-01-12 | Tests: 17 backend + 39 frontend tests passing (99 backend total, 194 frontend total) | Claude Opus 4.5 |