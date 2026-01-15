# Story 3.10: Face Availability Toggle

Status: done

## Story

As a **Face**,
I want **to toggle my availability status**,
So that **I can indicate when I'm open to new opportunities**.

## Acceptance Criteria

1. **Given** I am on my profile edit page, **When** I toggle my availability to "Disponible", **Then** my status is saved as `true` in the database

2. **Given** I am on my profile edit page, **When** I toggle my availability to "Indisponible", **Then** my status is saved as `false` in the database

3. **Given** I view my profile with availability "Disponible", **When** the badge is displayed, **Then** it shows a green "Disponible" badge

4. **Given** I view my profile with availability "Indisponible", **When** the badge is displayed, **Then** it shows a grey "Indisponible" badge

5. **Given** I am a new Face, **When** I have not set my availability, **Then** the default is `true` (Disponible)

6. **Given** my availability is "Disponible", **When** a producer searches for available Faces, **Then** I appear in the search results

7. **Given** my availability is "Indisponible", **When** a producer searches for available Faces, **Then** my visibility in search results is affected accordingly (future story scope)

**(FR20)**

## Tasks / Subtasks

### Task 1: Create Database Migration (AC: #1, #2, #5)

- [x] 1.1 Create migration `add_is_available_to_faces_table`:
  ```php
  $table->boolean('is_available')->default(true);
  ```
- [x] 1.2 Run migration and verify schema with `php artisan migrate`

### Task 2: Update Face Model (AC: #1, #2, #3, #4, #5)

- [x] 2.1 Add `is_available` to `$fillable` array
- [x] 2.2 Add to `$casts` array: `'is_available' => 'boolean'`
- [x] 2.3 Add accessor `availability_badge` - returns formatted badge text ("Disponible" or "Indisponible")
- [x] 2.4 Add accessor `availability_badge_color` - returns badge color class ("green" or "grey")
- [x] 2.5 Add to `$appends` array: `availability_badge`, `availability_badge_color`

### Task 3: Create AvailabilityController (AC: #1, #2)

- [x] 3.1 Create `app/Http/Controllers/Api/V1/Face/AvailabilityController.php`:
  - `show()` - return current availability status
  - `update()` - toggle availability with Form Request validation
- [x] 3.2 Follow established controller pattern from `TarifsController` or `BioLocationController`

### Task 4: Create Form Request (AC: #1, #2)

- [x] 4.1 Create `app/Http/Requests/Face/UpdateAvailabilityRequest.php`:
  ```php
  public function rules(): array
  {
      return [
          'is_available' => ['required', 'boolean'],
      ];
  }
  ```
- [x] 4.2 Add French error messages:
  - `is_available.required` -> "Le statut de disponibilité est requis"
  - `is_available.boolean` -> "Le statut de disponibilité doit être vrai ou faux"

### Task 5: Add API Routes (AC: #1, #2)

- [x] 5.1 Add routes to `routes/api/face.php`:
  ```php
  // Availability routes
  Route::get('/availability', [AvailabilityController::class, 'show'])
      ->middleware('throttle:60,1');
  Route::put('/availability', [AvailabilityController::class, 'update'])
      ->middleware('throttle:60,1');
  ```

### Task 6: Update FaceResource (AC: #3, #4)

- [x] 6.1 Add availability fields to `app/Http/Resources/FaceResource.php`:
  - `is_available` (boolean)
  - `availability_badge` (string: "Disponible" or "Indisponible")
  - `availability_badge_color` (string: "green" or "grey")

### Task 7: Create Backend Tests (AC: #1, #2, #3, #4, #5)

- [x] 7.1 Create `tests/Feature/Face/AvailabilityTest.php`:
  - Test getting availability (default true for new Face)
  - Test toggling availability to false
  - Test toggling availability to true
  - Test validation: non-boolean values rejected
  - Test validation: missing value rejected
  - Test unauthenticated access denied
  - Test non-Face user access denied
  - Test badge values are correct for each status
- [x] 7.2 Run tests with `php artisan test --filter=AvailabilityTest` (12 tests passed)

### Task 8: Update Frontend Types (AC: #1, #2, #3, #4)

- [x] 8.1 Update `frontend/src/features/face/types.ts`:
  ```typescript
  // Add to Face interface
  is_available: boolean
  availability_badge: string
  availability_badge_color: 'green' | 'grey'

  // Add AvailabilityFormData interface
  interface AvailabilityFormData {
    is_available: boolean
  }

  // Add AvailabilityData interface for API response
  interface AvailabilityData {
    is_available: boolean
    availability_badge: string
    availability_badge_color: 'green' | 'grey'
  }
  ```

### Task 9: Update faceApi Service (AC: #1, #2)

- [x] 9.1 Add availability endpoints to `frontend/src/features/face/services/faceApi.ts`:
  ```typescript
  getAvailability(): Promise<ApiResponse<AvailabilityData>>
  updateAvailability(data: AvailabilityFormData): Promise<ApiResponse<AvailabilityData>>
  ```

### Task 10: Create useAvailability Composable (AC: #1, #2, #3, #4)

- [x] 10.1 Create `frontend/src/features/face/composables/useAvailability.ts`:
  - `availability` reactive ref (AvailabilityData)
  - `isLoading`, `isSaving`, `error` refs
  - `fetchAvailability()` function
  - `toggleAvailability()` function - inverts current status
  - Handle API errors with French messages
  - Optimistic UI updates with rollback on error
- [x] 10.2 Follow pattern from `useTarifs.ts`

### Task 11: Create AvailabilityToggle Component (AC: #1, #2, #3, #4)

- [x] 11.1 Create `frontend/src/features/face/components/AvailabilityToggle.vue`:
  - Toggle switch with labels:
    - "Disponible" (green) when ON
    - "Indisponible" (grey) when OFF
  - Visual badge preview showing current status
  - Loading state during save
  - Immediate visual feedback on toggle
  - Success/error toast notifications
- [x] 11.2 Style consistently with Tailwind 4 (use CSS variables)
- [x] 11.3 Add smooth transition animations for toggle

### Task 12: Integrate into ProfileEditPage (AC: #3, #4)

- [x] 12.1 Import AvailabilityToggle component in `frontend/src/pages/face/ProfileEditPage.vue`
- [x] 12.2 Add "Disponibilité" section at the TOP of the profile edit page (prominent placement)
- [x] 12.3 Section title: "Disponibilité" with status icon

### Task 13: Create AvailabilityBadge Component for Display (AC: #3, #4)

- [x] 13.1 Create `frontend/src/features/face/components/AvailabilityBadge.vue`:
  - Reusable badge component for profile displays
  - Props: `isAvailable: boolean`
  - Shows appropriate color and text based on status
  - Compact design suitable for cards and headers
- [x] 13.2 Export from components/index.ts if exists (N/A - no index file)

### Task 14: Create Frontend Tests (AC: #1, #2, #3, #4)

- [x] 14.1 Create `frontend/src/features/face/composables/__tests__/useAvailability.spec.ts`:
  - Test initial state
  - Test fetching availability
  - Test toggling availability
  - Test error handling
  - Test optimistic updates and rollback
- [x] 14.2 Create `frontend/src/features/face/components/__tests__/AvailabilityToggle.spec.ts`:
  - Test rendering with available status
  - Test rendering with unavailable status
  - Test toggle interaction
  - Test loading state
  - Test accessibility (aria-checked)
- [x] 14.3 Create `frontend/src/features/face/components/__tests__/AvailabilityBadge.spec.ts`:
  - Test green badge when available
  - Test grey badge when unavailable
  - Test styling classes
- [x] 14.4 Run tests with `npm run test:run` (43 tests passed)

## Dev Notes

### Database Schema Change

```
faces table (AFTER):
├── ... (existing columns)
└── is_available (boolean, default true)
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/face/availability` | Get current availability status |
| PUT | `/api/v1/face/availability` | Update availability status |

### API Response Example

```json
{
  "data": {
    "is_available": true,
    "availability_badge": "Disponible",
    "availability_badge_color": "green"
  },
  "message": "Disponibilité mise à jour avec succès"
}
```

### Badge Styling

| Status | Badge Text | Background Color | Text Color |
|--------|------------|-----------------|------------|
| Available | "Disponible" | Green (bg-green-100) | Green (text-green-800) |
| Unavailable | "Indisponible" | Grey (bg-gray-100) | Grey (text-gray-600) |

### Toggle Switch Design

```
┌─────────────────────────────────────┐
│  ○═══════════════●  Disponible     │  <- Green, switch ON
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│  ●═══════════════○  Indisponible   │  <- Grey, switch OFF
└─────────────────────────────────────┘
```

### French Labels

| Context | French Text |
|---------|-------------|
| Section Title | "Disponibilité" |
| Available Status | "Disponible" |
| Unavailable Status | "Indisponible" |
| Success Message | "Disponibilité mise à jour avec succès" |
| Error - Required | "Le statut de disponibilité est requis" |
| Error - Invalid | "Le statut de disponibilité doit être vrai ou faux" |

### Project Structure Notes

#### Backend Files to Create/Modify

```
backend/
├── database/migrations/
│   └── YYYY_MM_DD_HHMMSS_add_is_available_to_faces_table.php (CREATE)
├── app/Models/Face.php (MODIFY - add fillable, casts, accessors, appends)
├── app/Http/Controllers/Api/V1/Face/AvailabilityController.php (CREATE)
├── app/Http/Requests/Face/UpdateAvailabilityRequest.php (CREATE)
├── app/Http/Resources/FaceResource.php (MODIFY - add availability fields)
├── routes/api/face.php (MODIFY - add availability routes)
└── tests/Feature/Face/AvailabilityTest.php (CREATE)
```

#### Frontend Files to Create/Modify

```
frontend/src/features/face/
├── types.ts (MODIFY - add availability interfaces)
├── services/faceApi.ts (MODIFY - add availability endpoints)
├── composables/useAvailability.ts (CREATE)
├── composables/__tests__/useAvailability.spec.ts (CREATE)
├── components/AvailabilityToggle.vue (CREATE)
├── components/AvailabilityBadge.vue (CREATE)
├── components/__tests__/AvailabilityToggle.spec.ts (CREATE)
└── components/__tests__/AvailabilityBadge.spec.ts (CREATE)

frontend/src/pages/face/
└── ProfileEditPage.vue (MODIFY - add AvailabilityToggle section)
```

### Alignment with Project Patterns

- **Controller Pattern**: Follow `TarifsController` structure (show/update methods)
- **Form Request Pattern**: Follow `UpdateTarifsRequest` structure
- **Composable Pattern**: Follow `useTarifs.ts` structure
- **Component Pattern**: Follow existing toggle/switch components in project
- **API Response**: Use envelope format `{data, message}` for success
- **Error Response**: Use `{error: {code, message, details}}` for errors

### Frontend UI Design - Gemini MCP Delegation

**IMPORTANT**: All frontend UI/design work MUST be delegated to Gemini MCP.

#### Decision Tree for Components

| Scenario | Tool to Use |
|----------|-------------|
| NEW visual component (AvailabilityToggle, AvailabilityBadge) | `snippet_frontend` or `create_frontend` |
| REDESIGN existing element | `modify_frontend` |
| Just text/logic/trivial changes | Do it yourself |

#### Implementation Flow

1. **For AvailabilityToggle.vue**: Use `snippet_frontend` to generate the toggle switch JSX with premium design
2. **For AvailabilityBadge.vue**: Use `snippet_frontend` to generate the badge component JSX
3. **For ProfileEditPage integration**: Use `snippet_frontend` to generate the new section JSX

#### Critical Rules

- **ALWAYS pass CSS/theme files in `context` parameter** when calling Gemini tools
- **Pass design tokens in `insertionContext`**: Include project CSS variables, Tailwind classes
- **YOU write the code to disk**: Gemini returns code, you apply it with Write/Edit tools
- **Logic stays with you**: useState, handlers, API calls - you write these yourself
- **Gemini handles JSX/styling**: The visual markup and Tailwind classes come from Gemini

#### Example Call Pattern

```
snippet_frontend({
  request: "A toggle switch component for availability status with Disponible/Indisponible labels",
  targetFile: "src/features/face/components/AvailabilityToggle.vue",
  techStack: "Vue 3 + TypeScript + Tailwind CSS 4",
  insertionContext: "Inside AvailabilityToggle component. Use project tokens from globals.css"
})
```

### Previous Story Intelligence (from 3-9-face-tarifs)

- Controller with show/update pattern is well-established
- Form Request validation with French messages works well
- Composable pattern with reactive refs and error handling is solid
- ProfileEditPage integration follows section-based layout
- Tests follow established patterns with good coverage

### Git Intelligence

Recent commits show established patterns:
- Migration naming: `add_X_to_faces_table.php`
- Feature commits use: `feat(domain): description`
- Test commits use: `test(domain): description`
- Documentation commits use: `docs(domain): description`

### UX Considerations

1. **Prominent Placement**: Availability toggle should be at the top of profile edit page since it's the most impactful setting for being discovered
2. **Immediate Feedback**: Toggle should provide instant visual feedback before API confirmation
3. **Optimistic UI**: Update UI immediately, roll back on error
4. **Clear Visual States**: Green/grey colors provide universal understanding of on/off state

### Future Story Connection (Story 3.11)

This story prepares for Story 3.11 (Profile Completion Indicator) by:
- Adding a boolean field that can be checked for profile completeness
- Note: Availability is NOT a required field for profile completion (optional feature)

### Edge Cases to Handle

1. **Network Failure**: Roll back toggle state and show error toast
2. **Concurrent Updates**: Last write wins (simple boolean, no conflict risk)
3. **Initial Load**: Default to `true` if database value is null (migration default handles this)

### References

- [Source: epics.md#Story 3.10 - Face Availability Toggle]
- [Source: project-context.md#Edge Cases - Face availability toggle affects visibility]
- [Source: TarifsController - controller pattern]
- [Source: useTarifs.ts - composable pattern]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

- Backend tests: `php artisan test --filter=AvailabilityTest` - 12 tests passed
- Frontend tests: `npm run test -- --run` - 47 tests passed (useAvailability: 14, AvailabilityToggle: 21, AvailabilityBadge: 12)

### Completion Notes List

1. **Optimistic UI**: Implemented optimistic updates in useAvailability composable with automatic rollback on API error
2. **Gemini MCP**: Used snippet_frontend for AvailabilityToggle and AvailabilityBadge component UI design
3. **Prominent Placement**: AvailabilityToggle placed at the TOP of ProfileEditPage as the first section after loading
4. **Test Coverage**: Comprehensive test coverage for composable (14 tests) and components (33 tests combined)
5. **Accessibility**: Toggle button uses role="switch" with aria-checked and aria-label for screen reader support

### Code Review Fixes Applied

1. **M1 & M4**: Fixed AvailabilityBadge.vue color inconsistency - changed from weact/zinc to green/gray to match AvailabilityToggle
2. **M2**: Added aria-label="Basculer la disponibilité" to toggle button for accessibility
3. **M3**: Fixed optimistic update in useAvailability.ts to work on first-load scenario (when availabilityInfo is null)
4. **M5**: Added error prop and display to AvailabilityToggle.vue for inline error feedback
5. **Tests**: Updated AvailabilityBadge.spec.ts for new color classes and added error display tests to AvailabilityToggle.spec.ts

### File List

**Backend Files Created:**
- `database/migrations/2026_01_14_202611_add_is_available_to_faces_table.php`
- `app/Http/Controllers/Api/V1/Face/AvailabilityController.php`
- `app/Http/Requests/Face/UpdateAvailabilityRequest.php`
- `tests/Feature/Face/AvailabilityTest.php`

**Backend Files Modified:**
- `app/Models/Face.php` - Added is_available field, casts, accessors
- `app/Http/Resources/FaceResource.php` - Added availability fields
- `routes/api/face.php` - Added availability routes

**Frontend Files Created:**
- `src/features/face/composables/useAvailability.ts`
- `src/features/face/composables/__tests__/useAvailability.spec.ts`
- `src/features/face/components/AvailabilityToggle.vue`
- `src/features/face/components/AvailabilityBadge.vue`
- `src/features/face/components/__tests__/AvailabilityToggle.spec.ts`
- `src/features/face/components/__tests__/AvailabilityBadge.spec.ts`

**Frontend Files Modified:**
- `src/features/face/types.ts` - Added availability interfaces
- `src/features/face/services/faceApi.ts` - Added availability API methods
- `src/pages/face/ProfileEditPage.vue` - Integrated AvailabilityToggle section
