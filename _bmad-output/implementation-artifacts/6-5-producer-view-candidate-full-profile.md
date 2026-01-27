# Story 6.5: Producer View Candidate Full Profile

Status: done

## Story

As a **Producer**,
I want **to view a candidate's complete profile including videos**,
so that **I can evaluate their talent before accepting or rejecting their candidature**.

## Acceptance Criteria

1. **Given** I am viewing candidatures for my mission **When** I click on a Face's name or photo **Then** I navigate to the Face's full profile page

2. **Given** I am viewing a candidate's profile **When** the page loads **Then** I see: profile photo, name, category, niche, location (city, neighborhood, country), bio, physical characteristics (height, weight), hourly rate, daily rate, availability status

3. **Given** the candidate has a presentation video **When** I view their profile **Then** I can play the video directly on the page

4. **Given** the candidate has an acting video **When** I view their profile **Then** I can play the video directly on the page

5. **Given** the candidate has portfolio photos **When** I view their profile **Then** I see their photo album (up to 4 photos) in a gallery

6. **Given** the candidate has professional experiences **When** I view their profile **Then** I see their experience list

7. **Given** I am a Face (not Producer) **When** I try to access this endpoint **Then** I get a 403 error

8. **Given** I am a Producer but the Face has not applied to any of my missions **When** I try to view their profile **Then** I get a 403 error (can only view profiles of candidates who applied to their missions)

9. **Given** the Face does not exist **When** I try to view their profile **Then** I get a 404 error

**(FR37)**

## Tasks / Subtasks

- [x] Task 1: Create Producer FaceController with show method (AC: #1, #7, #8, #9)
  - [x] Create `app/Http/Controllers/Api/V1/Producer/FaceController.php`
  - [x] Add `show(Face $face)` method
  - [x] Verify authenticated user is a Producer
  - [x] Verify Face has applied to at least one of this Producer's missions
  - [x] Eager load: photos, experiences
  - [x] Return FaceResource (reuse existing)

- [x] Task 2: Add route for Producer view candidate profile (AC: #1)
  - [x] Add `GET /v1/producer/candidates/{face}` route to `routes/api/producer.php`
  - [x] Apply `producer` and `throttle:60,1` middleware

- [x] Task 3: Create backend feature tests (AC: #1-9)
  - [x] Create `tests/Feature/Candidature/ProducerViewCandidateProfileTest.php`
  - [x] Test Producer can view profile of Face who applied to their mission
  - [x] Test response includes all profile fields (bio, location, characteristics, rates)
  - [x] Test response includes both videos (presentation, acting) URLs
  - [x] Test response includes photo album
  - [x] Test response includes experiences
  - [x] Test Face cannot access this endpoint (403)
  - [x] Test Producer cannot view Face who didn't apply to their missions (403)
  - [x] Test non-existent Face returns 404
  - [x] Test unauthenticated returns 401

- [x] Task 4: Create CandidateFullProfile type for frontend (AC: #2-6)
  - [x] Add `CandidateFullProfile` interface to `frontend/src/features/candidature/types/index.ts`
  - [x] Include all Face fields: id, nom, prenom, username, display_name, profile_photo_url, bio, ville, quartier, pays, formatted_location, taille, poids, categorie, categorie_label, niche, niche_label, tarif_horaire, tarif_journalier, formatted_tarif_horaire, formatted_tarif_journalier, is_available, availability_badge
  - [x] Include video fields: presentation_video_url, presentation_video_thumbnail_url, acting_video_url, acting_video_thumbnail_url
  - [x] Include photos array and experiences array

- [x] Task 5: Add getCandidateProfile to candidatureApi (AC: #1)
  - [x] Add `getCandidateProfile(faceId: number)` method
  - [x] Return `ApiResponse<CandidateFullProfile>` type

- [x] Task 6: Create useCandidateProfile composable (AC: #1, #2)
  - [x] Create `frontend/src/features/candidature/composables/useCandidateProfile.ts`
  - [x] Accept faceId as parameter
  - [x] Manage loading, error states
  - [x] Export from composables/index.ts

- [x] Task 7: Create CandidateProfilePage component (AC: #2)
  - [x] Create `frontend/src/pages/producer/candidature/CandidateProfilePage.vue`
  - [x] Replace the placeholder created in Story 6-4
  - [x] Display all profile information in organized sections
  - [x] Back button to return to candidatures list
  - [x] Loading and error states
  - [x] Use Gemini MCP for UI design

- [x] Task 8: Create CandidateProfileHeader component (AC: #2)
  - [x] Create `frontend/src/features/candidature/components/CandidateProfileHeader.vue`
  - [x] Show: large profile photo, name, category badge, niche badge, location, availability badge
  - [x] Use Gemini MCP for UI design

- [x] Task 9: Create CandidateVideosSection component (AC: #3, #4)
  - [x] Create `frontend/src/features/candidature/components/CandidateVideosSection.vue`
  - [x] Show presentation video with thumbnail and play button
  - [x] Show acting video with thumbnail and play button
  - [x] Handle cases where one or both videos are missing
  - [x] Use native HTML5 video player
  - [x] Use Gemini MCP for UI design

- [x] Task 10: Create CandidatePhotoGallery component (AC: #5)
  - [x] Create `frontend/src/features/candidature/components/CandidatePhotoGallery.vue`
  - [x] Display up to 4 portfolio photos in a responsive grid
  - [x] Click to view larger (lightbox or modal)
  - [x] Handle empty state if no photos
  - [x] Use Gemini MCP for UI design

- [x] Task 11: Create CandidateInfoSection component (AC: #2)
  - [x] Create `frontend/src/features/candidature/components/CandidateInfoSection.vue`
  - [x] Show bio, physical characteristics (height/weight), pricing
  - [x] Format pricing as XOF currency
  - [x] Use Gemini MCP for UI design

- [x] Task 12: Create CandidateExperiencesSection component (AC: #6)
  - [x] Create `frontend/src/features/candidature/components/CandidateExperiencesSection.vue`
  - [x] List professional experiences with title, description, dates
  - [x] Handle empty state if no experiences
  - [x] Use Gemini MCP for UI design

- [x] Task 13: Update router to use real CandidateProfilePage
  - [x] Update `producer-candidate-profile` route to use CandidateProfilePage instead of placeholder
  - [x] Ensure route passes faceId correctly

- [x] Task 14: Export new components from index files
  - [x] Export all new components from `frontend/src/features/candidature/components/index.ts`

- [x] Task 15: TypeScript types and verification
  - [x] TypeScript type checking passes
  - [x] All backend tests pass (no regressions)

## Dev Notes

### CRITICAL: Use Gemini MCP for Frontend UI

**You MUST use the Gemini MCP tools for all frontend UI development:**
- `create_frontend` - For new components and pages
- `modify_frontend` - For modifying existing components
- `snippet_frontend` - For smaller UI pieces

**Always pass the existing CSS/theme files in the `context` parameter** to ensure design consistency.

### This story completes the candidature review flow

After Story 6-4 (Producer can see candidature list), this story allows Producers to view the full profile of any candidate before making accept/reject decisions. This is critical for evaluation.

### Architecture Patterns

**Backend:**
- New controller: `Producer/FaceController` (not to be confused with Face's own profile controller)
- Authorization: Producer role + Face must have applied to Producer's mission
- Reuse existing `FaceResource` - no need for a new resource
- Need to check `Candidature` table to verify Face applied to Producer's mission

**Frontend:**
- Dedicated page for candidate profile view (Producer-only)
- Multiple section components for clean organization
- Video player components with fallback for missing videos
- Photo gallery with lightbox capability

### API Endpoint

```
GET /api/v1/producer/candidates/{face_id}
Authorization: Bearer {token}

Response (200 OK):
{
  "data": {
    "id": 5,
    "nom": "Dupont",
    "prenom": "Marie",
    "username": "marie_dupont",
    "profile_photo_url": "https://...",
    "thumbnail_url": "https://...",
    "presentation_video_url": "https://...",
    "presentation_video_thumbnail_url": "https://...",
    "acting_video_url": "https://...",
    "acting_video_thumbnail_url": "https://...",
    "bio": "Je suis une actrice passionnee...",
    "ville": "Cotonou",
    "quartier": "Akpakpa",
    "pays": "Benin",
    "formatted_location": "Cotonou, Akpakpa, Benin",
    "taille": 175,
    "poids": 65,
    "categorie": "acteur",
    "categorie_label": "Acteur",
    "niche": "beaute",
    "niche_label": "Beaute",
    "tarif_horaire": 25000,
    "tarif_journalier": 150000,
    "formatted_tarif_horaire": "25 000 XOF",
    "formatted_tarif_journalier": "150 000 XOF",
    "is_available": true,
    "availability_badge": "Disponible",
    "availability_badge_color": "green",
    "profile_completion_percentage": 100,
    "profile_completion_is_complete": true,
    "experiences": [
      {
        "id": 1,
        "title": "Publicite Moov Africa",
        "description": "Actrice principale dans le spot TV",
        "start_date": "2024-06",
        "end_date": "2024-07"
      }
    ],
    "photos": [
      {
        "id": 1,
        "photo_url": "https://...",
        "thumbnail_url": "https://..."
      }
    ]
  }
}

Response (401): Unauthenticated
Response (403): Not a Producer OR Face didn't apply to any of Producer's missions
Response (404): Face not found
```

### Controller Authorization Pattern

```php
// Producer\FaceController
public function show(Request $request, Face $face): FaceResource
{
    $user = $request->user();

    // Verify user is a Producer
    if ($user->userable_type !== Producer::class) {
        abort(403, 'Acces reserve aux Producteurs');
    }

    $producer = $user->userable;

    // Check if this Face has applied to at least one of this Producer's missions
    $hasApplied = Candidature::whereHas('mission', function ($query) use ($producer) {
            $query->where('producer_id', $producer->id);
        })
        ->where('face_id', $face->id)
        ->exists();

    if (!$hasApplied) {
        abort(403, 'Vous ne pouvez consulter que les profils des candidats ayant postule a vos missions');
    }

    // Load relationships for full profile
    $face->load(['photos', 'experiences']);

    return new FaceResource($face);
}
```

### Page Layout Design

```
+-----------------------------------------------------------------+
|  <- Retour aux candidatures                                       |
+-----------------------------------------------------------------+
|                                                                  |
|  +-----------------------------------------------------------+  |
|  |                    HEADER SECTION                          |  |
|  |  +---------+  Marie Dupont                    Disponible   |  |
|  |  |         |  Acteur - Beaute                              |  |
|  |  | [Photo] |  Cotonou, Akpakpa, Benin                      |  |
|  |  |         |                                               |  |
|  |  +---------+                                               |  |
|  +-----------------------------------------------------------+  |
|                                                                  |
|  +-----------------------------------------------------------+  |
|  |                    VIDEOS SECTION                          |  |
|  |  +-----------------+  +-----------------+                 |  |
|  |  |  Presentation   |  |     Acting      |                 |  |
|  |  |    [> Play]     |  |    [> Play]     |                 |  |
|  |  +-----------------+  +-----------------+                 |  |
|  +-----------------------------------------------------------+  |
|                                                                  |
|  +-----------------------------------------------------------+  |
|  |                    PHOTO GALLERY                           |  |
|  |  +-----+  +-----+  +-----+  +-----+                       |  |
|  |  |     |  |     |  |     |  |     |                       |  |
|  |  +-----+  +-----+  +-----+  +-----+                       |  |
|  +-----------------------------------------------------------+  |
|                                                                  |
|  +----------------------+  +--------------------------------+  |
|  |       BIO            |  |    INFORMATIONS                |  |
|  |                      |  |                                |  |
|  |  Je suis une actrice |  |  Taille: 175 cm                |  |
|  |  passionnee avec 5   |  |  Poids: 65 kg                  |  |
|  |  ans d'experience... |  |                                |  |
|  |                      |  |  Tarif horaire: 25 000 XOF     |  |
|  |                      |  |  Tarif journalier: 150 000     |  |
|  +----------------------+  +--------------------------------+  |
|                                                                  |
|  +-----------------------------------------------------------+  |
|  |                    EXPERIENCES                             |  |
|  |                                                            |  |
|  |  Publicite Moov Africa (Juin 2024 - Juillet 2024)         |  |
|  |     Actrice principale dans le spot TV national            |  |
|  |                                                            |  |
|  |  Court-metrage "Les Reves" (Mars 2024)                    |  |
|  |     Role secondaire, selectionne au FESPACO               |  |
|  |                                                            |  |
|  +-----------------------------------------------------------+  |
|                                                                  |
+-----------------------------------------------------------------+
```

### Existing Files Reference

**Backend files to create:**
- `app/Http/Controllers/Api/V1/Producer/FaceController.php`
- `tests/Feature/Candidature/ProducerViewCandidateProfileTest.php`

**Backend files to modify:**
- `routes/api/producer.php` - Add GET candidates/{face} route

**Frontend files to create:**
- `src/features/candidature/components/CandidateProfileHeader.vue`
- `src/features/candidature/components/CandidateVideosSection.vue`
- `src/features/candidature/components/CandidatePhotoGallery.vue`
- `src/features/candidature/components/CandidateInfoSection.vue`
- `src/features/candidature/components/CandidateExperiencesSection.vue`
- `src/features/candidature/composables/useCandidateProfile.ts`

**Frontend files to modify:**
- `src/pages/producer/candidature/CandidateProfilePlaceholder.vue` -> Replace with `CandidateProfilePage.vue`
- `src/features/candidature/types/index.ts` - Add CandidateFullProfile type
- `src/features/candidature/services/candidatureApi.ts` - Add getCandidateProfile
- `src/features/candidature/composables/index.ts` - Export new composable
- `src/features/candidature/components/index.ts` - Export new components
- `src/router/index.ts` - Update route to use real component

### Existing Resources to Reuse

**FaceResource (backend/app/Http/Resources/FaceResource.php):**
- Already includes all fields needed
- Need to ensure photos and experiences are loaded

**FacePhotoResource (if exists) or inline in FaceResource:**
- Photo album fields: id, photo_url, thumbnail_url

**ExperienceResource:**
- Experience fields: id, title, description, start_date, end_date

### Previous Story Intelligence

**From Story 6-4 (Producer View Mission Candidatures):**
- `ProducerCandidatureCard` links to this profile page
- Route `producer-candidate-profile` already exists with placeholder
- Face ID is passed via route params

**From Story 3-1 to 3-11 (Face Profile):**
- Face model has all profile fields
- FaceResource already transforms all data
- Video upload patterns established
- Photo album with FacePhoto model

**From Story 4-4 (Producer Profile Display):**
- Profile display patterns for layout
- Card/section organization patterns

### Video Player Notes

- Use native HTML5 `<video>` element with controls
- Show thumbnail as poster
- Allow fullscreen playback
- Handle case where video URL is null (show "No video uploaded" message)

### Photo Gallery Notes

- Grid of up to 4 photos
- Click to enlarge (can use simple modal or dedicated lightbox library)
- Responsive layout for mobile

### Test Scenarios

| Scenario | Input | Expected |
|----------|-------|----------|
| View candidate profile | GET as mission owner | 200, full profile data |
| Profile includes videos | GET | presentation_video_url, acting_video_url |
| Profile includes photos | GET with photos loaded | photos array with URLs |
| Profile includes experiences | GET with experiences loaded | experiences array |
| Face access | GET as Face | 403 |
| Producer, Face didn't apply | GET for unrelated Face | 403 |
| Non-existent Face | Invalid face_id | 404 |
| Unauthenticated | No token | 401 |
| Partial profile | Face missing some fields | 200, null for missing fields |

### Dependencies

- **Depends on**: Story 6-4 (Candidature list with links), Story 3-1 to 3-11 (Face profile data), Story 6-1 (Candidature schema)
- **Blocks**: Story 6-6 (Accept candidature), Story 6-7 (Reject candidature) - Producer should view profile before deciding

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 6.5 - Producer View Candidate Full Profile, FR37]
- [Source: _bmad-output/project-context.md#API Response Format]
- [Source: backend/app/Http/Resources/FaceResource.php - Existing Face resource with all fields]
- [Source: backend/app/Models/Face.php - Face model with relationships]
- [Source: _bmad-output/implementation-artifacts/6-4-producer-view-mission-candidatures.md - Previous story with link to profile]
- [Source: _bmad-output/implementation-artifacts/3-2-face-photo-album-management.md - FacePhoto model]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

- Fixed FaceCategory enum: Used ACTEUR (UPPERCASE) instead of Acteur (PascalCase) in tests

### Completion Notes List

- Backend: Created Producer FaceController with authorization checks (Producer role + Face must have applied to Producer's mission)
- Backend: Added photos to FaceResource (was missing, now includes FacePhotoResource collection)
- Backend: Added route GET /v1/producer/candidates/{face} with producer middleware
- Backend: 13 comprehensive tests covering all acceptance criteria (99 assertions)
- Frontend: Added FacePhoto, FaceExperience, CandidateFullProfile types
- Frontend: Added getCandidateProfile method to candidatureApi service
- Frontend: Created useCandidateProfile composable with reactive faceId watching
- Frontend: Created CandidateProfileHeader component (photo, name, badges, location)
- Frontend: Created CandidateVideosSection component (video modal with HTML5 player)
- Frontend: Created CandidatePhotoGallery component (lightbox with keyboard navigation)
- Frontend: Created CandidateInfoSection component (bio, physical characteristics, pricing)
- Frontend: Created CandidateExperiencesSection component (timeline-style experiences list)
- Frontend: Created CandidateProfilePage as main page component
- Frontend: Updated router to use real CandidateProfilePage instead of placeholder
- Frontend: Exported all new components from index.ts
- All TypeScript checks pass
- All 559 backend tests pass

### File List

**Backend Files Created:**
- `backend/app/Http/Controllers/Api/V1/Producer/FaceController.php`
- `backend/tests/Feature/Candidature/ProducerViewCandidateProfileTest.php`

**Backend Files Modified:**
- `backend/routes/api/producer.php` - Added candidates/{face} route
- `backend/app/Http/Resources/FaceResource.php` - Added photos and photos_count fields

**Frontend Files Created:**
- `frontend/src/features/candidature/components/CandidateProfileHeader.vue`
- `frontend/src/features/candidature/components/CandidateVideosSection.vue`
- `frontend/src/features/candidature/components/CandidatePhotoGallery.vue`
- `frontend/src/features/candidature/components/CandidateInfoSection.vue`
- `frontend/src/features/candidature/components/CandidateExperiencesSection.vue`
- `frontend/src/features/candidature/composables/useCandidateProfile.ts`
- `frontend/src/pages/producer/candidature/CandidateProfilePage.vue`

**Frontend Files Modified:**
- `frontend/src/features/candidature/types/index.ts` - Added FacePhoto, FaceExperience, CandidateFullProfile types
- `frontend/src/features/candidature/services/candidatureApi.ts` - Added getCandidateProfile method
- `frontend/src/features/candidature/composables/index.ts` - Exported useCandidateProfile
- `frontend/src/features/candidature/components/index.ts` - Exported new components
- `frontend/src/router/index.ts` - Updated producer-candidate-profile route
