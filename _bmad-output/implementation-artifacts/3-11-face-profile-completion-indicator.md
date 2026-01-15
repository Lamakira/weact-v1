# Story 3.11: Face Profile Completion Indicator

Status: done

## Story

As a **Face**,
I want **to see how complete my profile is**,
So that **I know what information is missing to attract producers**.

## Acceptance Criteria

1. **Given** I am on my dashboard, **When** I view my profile completion status, **Then** I see a percentage or progress bar

2. **Given** my profile is missing required fields, **When** I view the completion indicator, **Then** I see which fields are incomplete (e.g., "Ajoutez une vidéo de présentation")

3. **Given** all required fields are filled (photo, 2 videos, bio, location, category, tarifs), **When** I view completion, **Then** I see "Profil complet" (100%)

4. **Given** I view the profile edit page, **When** the indicator is visible, **Then** I see both the percentage and a list of missing items

5. **Given** I complete a missing field, **When** the field is saved, **Then** the completion percentage updates automatically

**(FR21)**

## Tasks / Subtasks

### Task 1: Define Completion Rules (AC: #1, #2, #3)

- [x] 1.1 Document required fields for profile completion:
  - **Required (counted):**
    - `profile_photo` - Photo de profil
    - `presentation_video` - Vidéo de présentation
    - `acting_video` - Vidéo d'acting
    - `bio` - Bio courte
    - `ville` - Ville (location)
    - `categorie` - Catégorie
    - `tarif_horaire` OR `tarif_journalier` - Au moins un tarif
  - **Optional (not counted):**
    - `photos` (album)
    - `quartier`, `pays` (additional location)
    - `taille`, `poids` (physical characteristics)
    - `niche`
    - `is_available` (availability toggle)
    - `experiences` (professional experiences)
- [x] 1.2 Total required fields: **7 items** for 100% completion

### Task 2: Add Profile Completion Accessors to Face Model (AC: #1, #2, #3)

- [x] 2.1 Add `profile_completion_percentage` accessor to `app/Models/Face.php`:
  ```php
  protected function profileCompletionPercentage(): Attribute
  {
      return Attribute::make(
          get: function (): int {
              $completed = 0;
              $total = 7;

              if ($this->profile_photo) $completed++;
              if ($this->presentation_video) $completed++;
              if ($this->acting_video) $completed++;
              if ($this->bio) $completed++;
              if ($this->ville) $completed++;
              if ($this->categorie) $completed++;
              if ($this->tarif_horaire || $this->tarif_journalier) $completed++;

              return (int) round(($completed / $total) * 100);
          },
      );
  }
  ```
- [x] 2.2 Add `profile_completion_missing` accessor to return array of missing items:
  ```php
  protected function profileCompletionMissing(): Attribute
  {
      return Attribute::make(
          get: function (): array {
              $missing = [];

              if (!$this->profile_photo) $missing[] = ['key' => 'profile_photo', 'label' => 'Ajoutez une photo de profil'];
              if (!$this->presentation_video) $missing[] = ['key' => 'presentation_video', 'label' => 'Ajoutez une vidéo de présentation'];
              if (!$this->acting_video) $missing[] = ['key' => 'acting_video', 'label' => "Ajoutez une vidéo d'acting"];
              if (!$this->bio) $missing[] = ['key' => 'bio', 'label' => 'Ajoutez une bio'];
              if (!$this->ville) $missing[] = ['key' => 'ville', 'label' => 'Ajoutez votre ville'];
              if (!$this->categorie) $missing[] = ['key' => 'categorie', 'label' => 'Sélectionnez votre catégorie'];
              if (!$this->tarif_horaire && !$this->tarif_journalier) $missing[] = ['key' => 'tarifs', 'label' => 'Ajoutez vos tarifs'];

              return $missing;
          },
      );
  }
  ```
- [x] 2.3 Add `profile_completion_is_complete` accessor (boolean):
  ```php
  protected function profileCompletionIsComplete(): Attribute
  {
      return Attribute::make(
          get: fn (): bool => $this->profile_completion_percentage === 100,
      );
  }
  ```
- [x] 2.4 Add to `$appends` array: `profile_completion_percentage`, `profile_completion_missing`, `profile_completion_is_complete`

### Task 3: Create ProfileCompletionController (AC: #1, #2, #3)

- [x] 3.1 Create `app/Http/Controllers/Api/V1/Face/ProfileCompletionController.php`:
  - `show()` - return current completion status with percentage, missing items, and is_complete boolean
- [x] 3.2 Follow established controller pattern from `AvailabilityController`

### Task 4: Add API Route (AC: #1)

- [x] 4.1 Add route to `routes/api/face.php`:
  ```php
  // Profile completion route
  Route::get('/profile-completion', [ProfileCompletionController::class, 'show'])
      ->middleware('throttle:60,1');
  ```

### Task 5: Update FaceResource (AC: #1, #2, #3)

- [x] 5.1 Add completion fields to `app/Http/Resources/FaceResource.php`:
  - `profile_completion_percentage` (integer: 0-100)
  - `profile_completion_missing` (array of {key, label} objects)
  - `profile_completion_is_complete` (boolean)

### Task 6: Create Backend Tests (AC: #1, #2, #3)

- [x] 6.1 Create `tests/Feature/Face/ProfileCompletionTest.php`:
  - Test completion percentage for new empty Face (0%)
  - Test completion percentage for partially filled profile
  - Test completion percentage for fully filled profile (100%)
  - Test missing items list is correct
  - Test tarif validation (either hourly OR daily satisfies requirement)
  - Test unauthenticated access denied
  - Test non-Face user access denied
- [x] 6.2 Run tests with `php artisan test --filter=ProfileCompletionTest`

### Task 7: Update Frontend Types (AC: #1, #2, #3)

- [x] 7.1 Update `frontend/src/features/face/types.ts`:
  ```typescript
  // Add to Face interface
  profile_completion_percentage: number
  profile_completion_missing: ProfileCompletionMissingItem[]
  profile_completion_is_complete: boolean

  // Add new interface
  interface ProfileCompletionMissingItem {
    key: string
    label: string
  }

  // Add ProfileCompletionData interface for API response
  interface ProfileCompletionData {
    profile_completion_percentage: number
    profile_completion_missing: ProfileCompletionMissingItem[]
    profile_completion_is_complete: boolean
  }
  ```

### Task 8: Update faceApi Service (AC: #1)

- [x] 8.1 Add profile completion endpoint to `frontend/src/features/face/services/faceApi.ts`:
  ```typescript
  getProfileCompletion(): Promise<ApiResponse<ProfileCompletionData>>
  ```

### Task 9: Create useProfileCompletion Composable (AC: #1, #2, #3, #4, #5)

- [x] 9.1 Create `frontend/src/features/face/composables/useProfileCompletion.ts`:
  - `completionInfo` reactive ref (ProfileCompletionData)
  - `isLoading`, `error` refs
  - `fetchCompletion()` function
  - `percentage` computed (0-100)
  - `missingItems` computed (array)
  - `isComplete` computed (boolean)
  - Handle API errors with French messages
- [x] 9.2 Follow pattern from `useAvailability.ts`

### Task 10: Create ProfileCompletionIndicator Component (AC: #1, #2, #3, #4)

- [x] 10.1 Create `frontend/src/features/face/components/ProfileCompletionIndicator.vue`:
  - Props: `percentage: number`, `missingItems: ProfileCompletionMissingItem[]`, `isComplete: boolean`, `variant: 'compact' | 'full'`
  - **Compact variant** (for dashboard card):
    - Circular progress ring with percentage in center
    - "Profil complet" text when 100%
    - Primary color (#198496) for progress fill
  - **Full variant** (for profile edit page):
    - Horizontal progress bar
    - Percentage text
    - List of missing items with icons and clickable labels
  - Loading skeleton state
- [x] 10.2 Use Gemini MCP `snippet_frontend` for visual design
- [x] 10.3 Style consistently with Tailwind 4 (use CSS variables)

### Task 11: Integrate into ProfileEditPage (AC: #4, #5)

- [x] 11.1 Import ProfileCompletionIndicator in `frontend/src/pages/face/ProfileEditPage.vue`
- [x] 11.2 Add completion indicator below the Availability section (second section)
- [x] 11.3 Use `variant="full"` for detailed view with missing items list
- [x] 11.4 Re-fetch completion when any profile section is saved (subscribe to save events)

### Task 12: Create ProfileCompletionCard for Dashboard (AC: #1, #2)

- [x] 12.1 Create `frontend/src/features/face/components/ProfileCompletionCard.vue`:
  - Card wrapper with title "Complétion du profil"
  - Uses ProfileCompletionIndicator with `variant="compact"`
  - Shows count of missing items: "3 éléments manquants"
  - Link to profile edit page
- [x] 12.2 Use Gemini MCP for card design

### Task 13: Create Frontend Tests (AC: #1, #2, #3, #4)

- [x] 13.1 Create `frontend/src/features/face/composables/__tests__/useProfileCompletion.spec.ts`:
  - Test initial state
  - Test fetching completion
  - Test computed properties
  - Test error handling
- [x] 13.2 Create `frontend/src/features/face/components/__tests__/ProfileCompletionIndicator.spec.ts`:
  - Test compact variant rendering
  - Test full variant rendering
  - Test 0% state
  - Test 100% state
  - Test missing items display
  - Test accessibility
- [x] 13.3 Create `frontend/src/features/face/components/__tests__/ProfileCompletionCard.spec.ts`:
  - Test card rendering
  - Test link to profile edit
  - Test loading state
- [x] 13.4 Run tests with `npm run test:run`

## Dev Notes

### Completion Calculation Logic

| Field | Required | Weight |
|-------|----------|--------|
| profile_photo | Yes | 1/7 ≈ 14.3% |
| presentation_video | Yes | 1/7 ≈ 14.3% |
| acting_video | Yes | 1/7 ≈ 14.3% |
| bio | Yes | 1/7 ≈ 14.3% |
| ville | Yes | 1/7 ≈ 14.3% |
| categorie | Yes | 1/7 ≈ 14.3% |
| tarif_horaire OR tarif_journalier | Yes (either) | 1/7 ≈ 14.3% |

**Total: 7 required items = 100%**

### API Endpoint

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/face/profile-completion` | Get profile completion status |

### API Response Example

```json
{
  "data": {
    "profile_completion_percentage": 57,
    "profile_completion_missing": [
      {"key": "acting_video", "label": "Ajoutez une vidéo d'acting"},
      {"key": "ville", "label": "Ajoutez votre ville"},
      {"key": "tarifs", "label": "Ajoutez vos tarifs"}
    ],
    "profile_completion_is_complete": false
  }
}
```

### French Labels for Missing Items

| Key | French Label |
|-----|--------------|
| profile_photo | "Ajoutez une photo de profil" |
| presentation_video | "Ajoutez une vidéo de présentation" |
| acting_video | "Ajoutez une vidéo d'acting" |
| bio | "Ajoutez une bio" |
| ville | "Ajoutez votre ville" |
| categorie | "Sélectionnez votre catégorie" |
| tarifs | "Ajoutez vos tarifs" |

### UI Design - Compact Variant (Dashboard)

```
┌─────────────────────────────────────────┐
│  ┌──────┐                               │
│  │ 71%  │  Complétion du profil         │
│  │  ○   │  2 éléments manquants         │
│  └──────┘  [Compléter mon profil →]     │
└─────────────────────────────────────────┘
```

### UI Design - Full Variant (Profile Edit)

```
┌─────────────────────────────────────────┐
│  Complétion du profil                   │
│  ████████████░░░░░░░░░  71%             │
│                                         │
│  Éléments manquants:                    │
│  ⚠ Ajoutez une vidéo d'acting          │
│  ⚠ Ajoutez vos tarifs                  │
└─────────────────────────────────────────┘
```

### Project Structure Notes

#### Backend Files to Create/Modify

```
backend/
├── app/Models/Face.php (MODIFY - add completion accessors, appends)
├── app/Http/Controllers/Api/V1/Face/ProfileCompletionController.php (CREATE)
├── app/Http/Resources/FaceResource.php (MODIFY - add completion fields)
├── routes/api/face.php (MODIFY - add profile-completion route)
└── tests/Feature/Face/ProfileCompletionTest.php (CREATE)
```

#### Frontend Files to Create/Modify

```
frontend/src/features/face/
├── types.ts (MODIFY - add completion interfaces)
├── services/faceApi.ts (MODIFY - add completion endpoint)
├── composables/useProfileCompletion.ts (CREATE)
├── composables/__tests__/useProfileCompletion.spec.ts (CREATE)
├── components/ProfileCompletionIndicator.vue (CREATE)
├── components/ProfileCompletionCard.vue (CREATE)
├── components/__tests__/ProfileCompletionIndicator.spec.ts (CREATE)
└── components/__tests__/ProfileCompletionCard.spec.ts (CREATE)

frontend/src/pages/face/
└── ProfileEditPage.vue (MODIFY - add ProfileCompletionIndicator section)
```

### Previous Story Intelligence (from 3-10-face-availability-toggle)

- Optimistic UI updates with rollback pattern works well
- Accessors appended to model work correctly with FaceResource
- ProfileEditPage section-based layout is consistent
- Component tests use data-testid attributes for reliable selection
- Gemini MCP used effectively for toggle and badge components
- Error display added inline in components for better UX

### Git Intelligence

Recent commits show established patterns:
- Feature commits: `feat(face): description`
- Test commits: `test(face): description`
- Documentation commits: `docs(story): description`
- Controllers follow show-only pattern for read endpoints
- Accessors use computed properties with proper typing

### Alignment with Project Patterns

- **Controller Pattern**: Follow `AvailabilityController` structure (show-only)
- **Accessor Pattern**: Use `Attribute::make()` for computed properties
- **Composable Pattern**: Follow `useAvailability.ts` structure
- **Component Pattern**: Props-based with variants for different contexts
- **API Response**: Use envelope format `{data}` for success
- **Testing**: Use PHPUnit with RefreshDatabase, Vitest with Vue Test Utils

### Frontend UI Design - Gemini MCP Delegation

**IMPORTANT**: All frontend UI/design work MUST be delegated to Gemini MCP.

#### Decision Tree for Components

| Scenario | Tool to Use |
|----------|-------------|
| NEW visual component (ProfileCompletionIndicator, ProfileCompletionCard) | `snippet_frontend` or `create_frontend` |
| REDESIGN existing element | `modify_frontend` |
| Just text/logic/trivial changes | Do it yourself |

#### Example Call Pattern

```
snippet_frontend({
  request: "A profile completion indicator with circular progress ring showing percentage, compact variant for dashboard cards",
  targetFile: "src/features/face/components/ProfileCompletionIndicator.vue",
  techStack: "Vue 3 + TypeScript + Tailwind CSS 4",
  insertionContext: "Inside ProfileCompletionIndicator component. Use project tokens from globals.css. Primary color: #198496"
})
```

### UX Considerations

1. **Motivational Design**: Progress indicator should encourage completion without being nagging
2. **Clear Actions**: Missing items should link directly to relevant sections
3. **Instant Feedback**: Percentage should update immediately when fields are saved
4. **Accessible**: Progress bar and percentage should be readable by screen readers
5. **Responsive**: Both variants should work well on mobile

### Edge Cases to Handle

1. **Empty Profile**: New Face should show 0% with all 7 items missing
2. **Partial Tarifs**: Only one of hourly/daily rate needed - check both
3. **Null vs Empty**: Empty string should be treated as missing (use `!!value` check)
4. **Network Error**: Show last known completion or loading skeleton

### References

- [Source: epics.md#Story 3.11 - Face Profile Completion Indicator]
- [Source: project-context.md#Technology Stack]
- [Source: Face.php - Model structure]
- [Source: AvailabilityController - Controller pattern]
- [Source: useAvailability.ts - Composable pattern]
- [Source: 3-10-face-availability-toggle.md - Previous story learnings]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

- Backend tests: 257 tests passed (1127 assertions)
- Frontend tests: 592 tests passed
- Fixed FaceCategory enum case error: `FaceCategory::Mannequin` → `FaceCategory::MANNEQUIN`

### Completion Notes List

- All 13 tasks completed successfully
- Backend: Model accessors, controller, resource, route, and 12 tests created
- Frontend: Types, API service, composable, 2 components (with variants), and 3 test suites created
- ProfileCompletionIndicator supports compact (circular ring) and full (progress bar + list) variants
- ProfileCompletionCard provides dashboard integration with link to profile edit
- Auto-refresh on save implemented via fetchCompletion() calls in all save handlers
- Gemini MCP used for UI design of both visual components

### File List

**Backend (Created)**
- `app/Http/Controllers/Api/V1/Face/ProfileCompletionController.php`
- `tests/Feature/Face/ProfileCompletionTest.php`

**Backend (Modified)**
- `app/Models/Face.php` - Added 3 completion accessors and appends
- `app/Http/Resources/FaceResource.php` - Added completion fields
- `routes/api/face.php` - Added profile-completion route

**Frontend (Created)**
- `src/features/face/composables/useProfileCompletion.ts`
- `src/features/face/composables/__tests__/useProfileCompletion.spec.ts`
- `src/features/face/components/ProfileCompletionIndicator.vue`
- `src/features/face/components/__tests__/ProfileCompletionIndicator.spec.ts`
- `src/features/face/components/ProfileCompletionCard.vue`
- `src/features/face/components/__tests__/ProfileCompletionCard.spec.ts`

**Frontend (Modified)**
- `src/features/face/types.ts` - Added completion interfaces
- `src/features/face/services/faceApi.ts` - Added getProfileCompletion method
- `src/pages/face/ProfileEditPage.vue` - Integrated ProfileCompletionIndicator section, added click-item scroll navigation
- `src/pages/dashboard/FaceDashboardPage.vue` - Integrated ProfileCompletionCard

## Senior Developer Review (AI)

**Reviewer:** Claude Opus 4.5
**Date:** 2026-01-15

### Review Findings

| Severity | Issue | Resolution |
|----------|-------|------------|
| HIGH | ProfileCompletionCard not integrated into FaceDashboardPage | Fixed: Added import and card to dashboard grid |
| HIGH | Missing dashboard page integration for AC #1 | Fixed: FaceDashboardPage now displays ProfileCompletionCard |
| HIGH | Missing click-item handler in ProfileEditPage | Fixed: Added handleCompletionItemClick function with scroll-to-section |
| MEDIUM | Unused ProfileCompletionResult type | Fixed: Removed from types.ts |
| MEDIUM | Missing error display for ProfileCompletion section | Fixed: Added completionError display in ProfileEditPage |
| MEDIUM | Zero percentage (0%) without context in compact variant | Fixed: Added "Commencez à compléter" text |
| LOW | Type duplication in ProfileCompletionIndicator | Fixed: Import ProfileCompletionMissingItem from types.ts |

### Additional Changes Made

1. Added section IDs to ProfileEditPage for scroll navigation:
   - `section-profile-photo`
   - `section-presentation-video`
   - `section-acting-video`
   - `section-bio-location`
   - `section-category-niche`
   - `section-tarifs`

2. Added `data-testid="start-text"` for 0% state in compact variant

### Test Results After Fixes

- Backend: 12 tests passed (48 assertions)
- Frontend: 46 tests passed (3 test files)
