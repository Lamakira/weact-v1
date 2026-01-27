# Story 6.8: Face Confirm Mission

Status: done

## Story

As a **Face**,
I want **to confirm my participation after my candidature is accepted**,
so that **the Producer knows I'm committed to the mission**.

## Acceptance Criteria

1. **Given** my candidature has status "accepted" **When** I view my candidatures list **Then** I see a "Confirmer ma participation" button on that candidature card

2. **Given** I click "Confirmer ma participation" **When** the action is processed **Then** the candidature status changes to "confirmed" **And** I see a success message "Participation confirmée"

3. **Given** I have confirmed a candidature **When** I view my candidatures list **Then** this candidature shows status "Confirmée" with green badge

4. **Given** a candidature is "pending", "confirmed", "in_progress", "completed", or "rejected" **When** I view it **Then** I do not see the "Confirmer ma participation" button

5. **Given** I am a Producer (not Face) **When** I try to confirm a candidature **Then** I get a 403 error

6. **Given** the candidature does not belong to me **When** I try to confirm it **Then** I get a 403 error

7. **Given** the candidature does not exist **When** I try to confirm it **Then** I get a 404 error

8. **Given** I am not authenticated **When** I try to confirm a candidature **Then** I get a 401 error

**(FR40)**

## Tasks / Subtasks

- [x] Task 1: Add confirm method to Face CandidatureController (AC: #1-8)
  - [x] Create `confirm(Candidature $candidature)` method
  - [x] Verify authenticated user is a Face
  - [x] Verify candidature belongs to this Face
  - [x] Verify candidature status is "accepted"
  - [x] Update status to "confirmed"
  - [x] Return updated candidature with success message

- [x] Task 2: Add route for confirm candidature (AC: #2)
  - [x] Add `POST /v1/face/candidatures/{candidature}/confirm` route
  - [x] Apply existing face middleware

- [x] Task 3: Create backend feature tests (AC: #1-8)
  - [x] Create `tests/Feature/Candidature/FaceConfirmCandidatureTest.php`
  - [x] Test Face can confirm accepted candidature
  - [x] Test status changes from accepted to confirmed
  - [x] Test cannot confirm non-accepted candidature (pending/confirmed/rejected/etc.)
  - [x] Test Producer cannot confirm (403)
  - [x] Test Face cannot confirm another Face's candidature (403)
  - [x] Test non-existent candidature (404)
  - [x] Test unauthenticated (401)

- [x] Task 4: Add confirmCandidature method to candidatureApi (AC: #2)
  - [x] Add `confirmCandidature(candidatureId: number)` method
  - [x] Return updated candidature data

- [x] Task 5: Create useConfirmCandidature composable (AC: #2, #3)
  - [x] Create `frontend/src/features/candidature/composables/useConfirmCandidature.ts`
  - [x] Handle API call, loading, error states
  - [x] Emit event on success to refresh parent list
  - [x] Export from composables/index.ts

- [x] Task 6: Update CandidatureCard to show Confirm button (AC: #1, #4)
  - [x] Add "Confirmer ma participation" button visible only for accepted candidatures
  - [x] Add loading state during confirm action
  - [x] Style button with success/primary color (green)
  - [x] Position button prominently in the card

- [x] Task 7: Update Face candidatures page to handle confirm (AC: #3)
  - [x] Wire up confirm event handler in parent page
  - [x] Refresh candidatures list after confirmation
  - [x] Show toast notification on success/error

- [x] Task 8: TypeScript and test verification
  - [x] TypeScript type checking passes
  - [x] All backend tests pass (no regressions)

## Dev Notes

### CRITICAL: Status Transition Rules

**Valid status transitions:**
- `pending` → `accepted` (story 6-6, Producer action) ✅ DONE
- `pending` → `rejected` (story 6-7, Producer action) ✅ DONE
- `accepted` → `confirmed` (THIS STORY, Face action)
- `confirmed` → `in_progress` (automatic when mission starts, future story)
- `in_progress` → `completed` (when mission marked complete, future story)

**This story ONLY handles:** `accepted` → `confirmed`

**CRITICAL:** This is the 2-step validation system:
1. Producer accepts candidature → status = "accepted"
2. Face confirms participation → status = "confirmed"

Only after Face confirms can the mission proceed. This ensures mutual commitment.

### Architecture Patterns

**Backend:**
- Add `confirm` method to existing `Face/CandidatureController`
- Use Candidature model binding for route
- Authorization: Face owns the candidature (face_id matches)
- Status validation: Only confirm accepted candidatures

**Frontend:**
- Add confirm button to existing `CandidatureCard` (Face's candidature view)
- Unlike Producer accept (immediate), Face confirm could be immediate (no confirmation dialog needed - positive action)
- Toast notification for feedback
- Refresh list after success

### API Endpoint

```
POST /api/v1/face/candidatures/{candidature_id}/confirm
Authorization: Bearer {token}

Response (200 OK):
{
  "data": {
    "id": 15,
    "mission_id": 3,
    "face_id": 7,
    "status": "confirmed",
    "status_label": "Confirmée",
    "message_motivation": "Je suis très motivé...",
    "created_at": "2026-01-20T10:30:00Z",
    "updated_at": "2026-01-27T14:00:00Z"
  },
  "message": "Participation confirmée"
}

Response (400): Candidature is not accepted
Response (401): Unauthenticated
Response (403): Not a Face OR Candidature doesn't belong to Face
Response (404): Candidature not found
```

### Controller Implementation Pattern

```php
// Face\CandidatureController - add to existing file
use App\Models\Face;

public function confirm(Request $request, Candidature $candidature): JsonResponse
{
    $user = $request->user();

    // Verify user is a Face
    if ($user->userable_type !== Face::class) {
        abort(403, 'Accès réservé aux Faces');
    }

    $face = $user->userable;

    // Verify candidature belongs to this Face
    if ($candidature->face_id !== $face->id) {
        abort(403, 'Cette candidature ne vous appartient pas');
    }

    // Verify candidature is accepted
    if ($candidature->status !== CandidatureStatus::Accepted) {
        return response()->json([
            'error' => [
                'code' => 'INVALID_STATUS',
                'message' => 'Seules les candidatures acceptées peuvent être confirmées',
            ]
        ], 400);
    }

    // Update status
    $candidature->status = CandidatureStatus::Confirmed;
    $candidature->save();

    return response()->json([
        'data' => new CandidatureResource($candidature),
        'message' => 'Participation confirmée',
    ]);
}
```

### Frontend Button Pattern

```vue
<!-- In CandidatureCard.vue - add confirm button for accepted status -->
<div v-if="candidature.status === 'accepted'" class="mt-4 pt-4 border-t border-border">
  <button
    type="button"
    class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
    :disabled="isConfirming"
    @click.prevent="handleConfirm"
  >
    <Loader2 v-if="isConfirming" class="h-4 w-4 animate-spin" />
    <Check v-else class="h-4 w-4" />
    {{ isConfirming ? 'Confirmation...' : 'Confirmer ma participation' }}
  </button>
</div>
```

### Status Badge Colors (existing patterns)

```typescript
const CandidatureStatusColor: Record<CandidatureStatusType, string> = {
  pending: 'yellow',    // En attente
  accepted: 'blue',     // Acceptée - shows confirm button
  confirmed: 'green',   // Confirmée <-- After this story
  in_progress: 'purple', // En cours
  completed: 'emerald', // Terminée
  rejected: 'red',      // Refusée
}
```

### Existing Files to Modify

**Backend:**
- `app/Http/Controllers/Api/V1/Face/CandidatureController.php` - Add confirm method
- `routes/api/face.php` - Add POST candidatures/{candidature}/confirm route

**Backend to Create:**
- `tests/Feature/Candidature/FaceConfirmCandidatureTest.php`

**Frontend:**
- `src/features/candidature/services/candidatureApi.ts` - Add confirmCandidature method
- `src/features/candidature/composables/index.ts` - Export new composable
- `src/features/candidature/components/CandidatureCard.vue` - Add confirm button

**Frontend to Create:**
- `src/features/candidature/composables/useConfirmCandidature.ts`

### Test Scenarios

| Scenario | Input | Expected |
|----------|-------|----------|
| Confirm accepted candidature | POST as candidature owner | 200, status = confirmed |
| Confirm pending candidature | POST on pending | 400, error message |
| Confirm already confirmed | POST on confirmed | 400, error message |
| Confirm rejected | POST on rejected | 400, error message |
| Producer tries to confirm | POST as Producer | 403 |
| Wrong Face | POST on other Face's candidature | 403 |
| Non-existent | Invalid candidature_id | 404 |
| Unauthenticated | No token | 401 |

### Previous Story Intelligence

**From Story 6-6 (Producer Accept Candidature):**
- Producer accept pattern established (POST /producer/candidatures/{id}/accept)
- Status changes from pending → accepted
- No confirmation dialog for accept (positive action)
- Toast notification system

**From Story 6-7 (Producer Reject Candidature):**
- Reject pattern with confirmation dialog (destructive action)
- useRejectCandidature composable pattern
- ProducerCandidatureCard button pattern

**From Story 6-3 (Face View My Candidatures):**
- CandidatureCard component exists showing Face's candidatures
- useFaceCandidatures composable exists for loading candidatures
- Status filter component exists
- Pagination implemented

**Key Similarities to Accept (6-6):**
1. Positive action → No confirmation dialog needed
2. Status changes to a positive state
3. Button is green/success
4. Immediate action on click

**Key Differences from Producer Actions:**
1. Different controller (Face/CandidatureController instead of Producer/CandidatureController)
2. Different authorization (Face owns candidature instead of Producer owns mission)
3. Different route prefix (/face/ instead of /producer/)
4. CandidatureCard currently is a RouterLink - need to convert to div with button

### Important: CandidatureCard Structure Change

**Current:** CandidatureCard is a `<RouterLink>` that navigates to mission detail.
**Needed:** For accepted candidatures, need a confirm button that doesn't trigger navigation.

**Solution:**
- Use `@click.prevent` on the button to prevent RouterLink navigation
- OR change card structure to have the link only on title/content, not wrapping button
- RECOMMENDED: Keep RouterLink but add button inside with `@click.stop.prevent`

### Git Patterns from Recent Commits

```
572ba06 docs: complete story 6-7 producer reject candidature
f1ce327 feat(candidature): add reject button with confirmation dialog
ea53eb2 feat(candidature): add reject candidature service and composable
3f2c029 test(candidature): add producer reject candidature tests
4c9836d feat(producer): add reject candidature endpoint
```

Follow same patterns:
- `feat(face):` for backend API endpoint
- `test(candidature):` for test files
- `feat(candidature):` for frontend features
- Separate commits for backend API, tests, frontend types/API, components

### Dependencies

- **Depends on**: Story 6-6 (Accept candidature - must be accepted first), Story 6-3 (Face view candidatures - CandidatureCard exists)
- **Blocks**: Nothing directly (future stories will handle in_progress transition)
- **Related**: Story 6-6 (Accept), Story 6-7 (Reject) - similar patterns
- **Enables**: Chat access (already unlocked at accept), but this confirms commitment

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 6.8 - Face Confirm Mission, FR40]
- [Source: _bmad-output/planning-artifacts/epics.md#Candidature Status Flow]
- [Source: backend/app/Enums/CandidatureStatus.php - Status enum]
- [Source: backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php - Existing controller]
- [Source: frontend/src/features/candidature/components/CandidatureCard.vue - Card to modify]
- [Source: frontend/src/features/candidature/composables/useAcceptCandidature.ts - Pattern to follow]
- [Source: _bmad-output/implementation-artifacts/6-7-producer-reject-candidature.md - Previous story]
- [Source: _bmad-output/implementation-artifacts/6-6-producer-accept-candidature.md - Accept pattern]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

- No significant issues encountered during implementation

### Completion Notes List

- Backend: Added `confirm` method to Face CandidatureController with proper authorization checks
- Backend: Added POST `/v1/face/candidatures/{candidature}/confirm` route with face middleware
- Backend: Created 13 comprehensive tests (43 assertions) covering all acceptance criteria
- Frontend: Added `confirmCandidature` method to candidatureApi service
- Frontend: Created `useConfirmCandidature` composable with loading/error states
- Frontend: Updated CandidatureCard with Confirm button (green, only visible for accepted candidatures)
- Frontend: Used @click.stop.prevent to prevent RouterLink navigation when clicking button
- Frontend: Updated FaceCandidaturesPage to handle confirm action with toast notifications
- All 598 backend tests pass (2627 assertions)
- TypeScript type checking passes

### File List

**Backend Files Modified:**
- `backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php` - Added confirm method
- `backend/routes/api/face.php` - Added confirm candidature route

**Backend Files Created:**
- `backend/tests/Feature/Candidature/FaceConfirmCandidatureTest.php` - 13 tests

**Frontend Files Created:**
- `frontend/src/features/candidature/composables/useConfirmCandidature.ts`

**Frontend Files Modified:**
- `frontend/src/features/candidature/services/candidatureApi.ts` - Added confirmCandidature method
- `frontend/src/features/candidature/composables/index.ts` - Exported useConfirmCandidature
- `frontend/src/features/candidature/components/CandidatureCard.vue` - Added Confirm button + emit
- `frontend/src/pages/face/candidature/FaceCandidaturesPage.vue` - Added confirm handling with toast
