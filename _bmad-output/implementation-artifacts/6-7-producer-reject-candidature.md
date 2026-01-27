# Story 6.7: Producer Reject Candidature

Status: done

## Story

As a **Producer**,
I want **to reject a Face's candidature**,
so that **they know they were not selected and can apply to other missions**.

## Acceptance Criteria

1. **Given** I am viewing candidatures for my mission **When** a candidature has status "pending" **Then** I see a "Refuser" button

2. **Given** I click "Refuser" on a pending candidature **When** a confirmation dialog appears and I confirm **Then** the candidature status changes to "rejected" **And** I see a success message "Candidature refusée"

3. **Given** I have rejected a candidature **When** I view the candidatures list **Then** this candidature shows status "Refusée" with appropriate styling (red badge)

4. **Given** a candidature is already "accepted", "confirmed", "in_progress", "completed", or "rejected" **When** I view it **Then** I do not see the "Refuser" button

5. **Given** I am a Face (not Producer) **When** I try to reject a candidature **Then** I get a 403 error

6. **Given** I am a Producer but the mission doesn't belong to me **When** I try to reject a candidature **Then** I get a 403 error

7. **Given** the candidature does not exist **When** I try to reject it **Then** I get a 404 error

8. **Given** I reject a candidature **When** the status changes **Then** a notification is triggered for the Face (prep for Story 6-9)

**(FR39)**

## Tasks / Subtasks

- [x] Task 1: Add reject method to Producer CandidatureController (AC: #1-7)
  - [x] Create `reject(Candidature $candidature)` method
  - [x] Verify authenticated user is a Producer
  - [x] Verify candidature's mission belongs to this Producer
  - [x] Verify candidature status is "pending"
  - [x] Update status to "rejected"
  - [x] Return updated candidature with success message

- [x] Task 2: Add route for reject candidature (AC: #2)
  - [x] Add `POST /v1/producer/candidatures/{candidature}/reject` route
  - [x] Apply existing producer middleware

- [x] Task 3: Create backend feature tests (AC: #1-8)
  - [x] Create `tests/Feature/Candidature/ProducerRejectCandidatureTest.php`
  - [x] Test Producer can reject pending candidature
  - [x] Test status changes from pending to rejected
  - [x] Test cannot reject non-pending candidature (already accepted/rejected/etc.)
  - [x] Test Face cannot reject (403)
  - [x] Test Producer cannot reject candidature for another Producer's mission (403)
  - [x] Test non-existent candidature (404)
  - [x] Test unauthenticated (401)

- [x] Task 4: Add rejectCandidature method to candidatureApi (AC: #2)
  - [x] Add `rejectCandidature(candidatureId: number)` method
  - [x] Return updated candidature data

- [x] Task 5: Update ProducerCandidatureCard to show Reject button (AC: #1, #4)
  - [x] Add "Refuser" button visible only for pending candidatures
  - [x] Add loading state during reject action
  - [x] Style button with destructive/danger color (red)
  - [x] Position alongside Accept button

- [x] Task 6: Create useRejectCandidature composable (AC: #2, #3)
  - [x] Create `frontend/src/features/candidature/composables/useRejectCandidature.ts`
  - [x] Handle API call, loading, error states
  - [x] Emit event on success to refresh parent list
  - [x] Export from composables/index.ts

- [x] Task 7: Add confirmation dialog before rejection (AC: #2)
  - [x] Create confirmation modal or use existing pattern
  - [x] Show "Êtes-vous sûr de vouloir refuser cette candidature ?" message
  - [x] Require explicit confirmation before API call

- [x] Task 8: Update ProducerCandidaturesSection to handle reject (AC: #3)
  - [x] Wire up reject event handler
  - [x] Refresh candidature list after rejection
  - [x] Show toast notification on success/error

- [x] Task 9: TypeScript and test verification
  - [x] TypeScript type checking passes
  - [x] All backend tests pass (no regressions)

## Dev Notes

### CRITICAL: Status Transition Rules

**Valid status transitions:**
- `pending` → `accepted` (story 6-6) ✅ DONE
- `pending` → `rejected` (THIS STORY)
- `accepted` → `confirmed` (story 6-8, Face action)
- `confirmed` → `in_progress` (automatic when mission starts)
- `in_progress` → `completed` (when mission marked complete)

**This story ONLY handles:** `pending` → `rejected`

**CRITICAL:** Once rejected, a candidature CANNOT be un-rejected or changed to any other status. This is a terminal state.

### Architecture Patterns

**Backend:**
- Add method to existing `Producer/CandidatureController` (same file as accept)
- Use Candidature model binding for route
- Authorization: Producer owns the mission (same pattern as accept)
- Status validation: Only reject pending candidatures

**Frontend:**
- Add reject functionality to existing `ProducerCandidatureCard`
- MUST include confirmation dialog before rejection (unlike accept which is immediate)
- Toast notification for feedback
- Refresh list after success

### API Endpoint

```
POST /api/v1/producer/candidatures/{candidature_id}/reject
Authorization: Bearer {token}

Response (200 OK):
{
  "data": {
    "id": 15,
    "mission_id": 3,
    "face_id": 7,
    "status": "rejected",
    "status_label": "Refusée",
    "message_motivation": "Je suis très motivé...",
    "created_at": "2026-01-20T10:30:00Z",
    "updated_at": "2026-01-27T14:00:00Z"
  },
  "message": "Candidature refusée"
}

Response (400): Candidature is not pending
Response (401): Unauthenticated
Response (403): Not a Producer OR Mission doesn't belong to Producer
Response (404): Candidature not found
```

### Controller Implementation Pattern

```php
// Producer\CandidatureController - add to existing file
public function reject(Request $request, Candidature $candidature): JsonResponse
{
    $user = $request->user();

    // Verify user is a Producer
    if ($user->userable_type !== Producer::class) {
        abort(403, 'Accès réservé aux Producteurs');
    }

    $producer = $user->userable;

    // Eager load mission to avoid N+1 query
    $candidature->loadMissing('mission');

    // Verify candidature's mission belongs to this Producer
    if ($candidature->mission->producer_id !== $producer->id) {
        abort(403, 'Cette candidature ne concerne pas une de vos missions');
    }

    // Verify candidature is pending
    if ($candidature->status !== CandidatureStatus::Pending) {
        return response()->json([
            'error' => [
                'code' => 'INVALID_STATUS',
                'message' => 'Seules les candidatures en attente peuvent être refusées',
            ]
        ], 400);
    }

    // Update status
    $candidature->status = CandidatureStatus::Rejected;
    $candidature->save();

    return response()->json([
        'data' => new CandidatureResource($candidature),
        'message' => 'Candidature refusée',
    ]);
}
```

### Frontend Button Pattern

```vue
<!-- In ProducerCandidatureCard.vue - alongside Accept button -->
<div v-if="candidature.status === 'pending'" class="flex gap-2">
  <!-- Accept button (already exists from 6-6) -->
  <button
    type="button"
    class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
    :disabled="isAccepting"
    @click="handleAccept"
  >
    <Loader2 v-if="isAccepting" class="h-4 w-4 animate-spin" />
    <Check v-else class="h-4 w-4" />
    {{ isAccepting ? 'Acceptation...' : 'Accepter' }}
  </button>

  <!-- Reject button (NEW) -->
  <button
    type="button"
    class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
    :disabled="isRejecting"
    @click="showRejectConfirmation"
  >
    <Loader2 v-if="isRejecting" class="h-4 w-4 animate-spin" />
    <X v-else class="h-4 w-4" />
    {{ isRejecting ? 'Refus...' : 'Refuser' }}
  </button>
</div>
```

### Confirmation Dialog Pattern

```vue
<!-- Simple inline confirmation or modal -->
<div v-if="showConfirmReject" class="...">
  <p>Êtes-vous sûr de vouloir refuser cette candidature ?</p>
  <p class="text-sm text-muted-foreground">Cette action est irréversible.</p>
  <div class="flex gap-2 mt-4">
    <button @click="cancelReject">Annuler</button>
    <button @click="confirmReject" class="bg-red-600 text-white">Confirmer le refus</button>
  </div>
</div>
```

### Status Badge Colors (from existing patterns)

```typescript
const CandidatureStatusColor: Record<CandidatureStatusType, string> = {
  pending: 'yellow',    // En attente
  accepted: 'blue',     // Acceptée
  confirmed: 'green',   // Confirmée
  in_progress: 'purple', // En cours
  completed: 'emerald', // Terminée
  rejected: 'red',      // Refusée  <-- This story
}
```

### Existing Files to Modify

**Backend:**
- `app/Http/Controllers/Api/V1/Producer/CandidatureController.php` - Add reject method
- `routes/api/producer.php` - Add POST candidatures/{candidature}/reject route

**Backend to Create:**
- `tests/Feature/Candidature/ProducerRejectCandidatureTest.php`

**Frontend:**
- `src/features/candidature/components/ProducerCandidatureCard.vue` - Add reject button + confirmation
- `src/features/candidature/services/candidatureApi.ts` - Add rejectCandidature method
- `src/features/candidature/composables/index.ts` - Export new composable
- `src/features/candidature/components/ProducerCandidaturesSection.vue` - Handle reject event

**Frontend to Create:**
- `src/features/candidature/composables/useRejectCandidature.ts`

### Test Scenarios

| Scenario | Input | Expected |
|----------|-------|----------|
| Reject pending candidature | POST as mission owner | 200, status = rejected |
| Reject already accepted | POST on accepted | 400, error message |
| Reject already rejected | POST on rejected | 400, error message |
| Face tries to reject | POST as Face | 403 |
| Wrong Producer | POST on other's mission | 403 |
| Non-existent | Invalid candidature_id | 404 |
| Unauthenticated | No token | 401 |

### Previous Story Intelligence

**From Story 6-6 (Producer Accept Candidature) - CRITICAL REFERENCE:**
- Controller already has `accept` method - copy pattern for `reject`
- Route file already has accept route - add reject route next to it
- `useAcceptCandidature` composable exists - create similar `useRejectCandidature`
- Toast notification system already in place in ProducerCandidaturesSection
- ProducerCandidatureCard has Accept button - add Reject button next to it
- All authorization patterns are identical

**Key differences from Accept:**
1. Status changes to `rejected` instead of `accepted`
2. MUST show confirmation dialog before action (accept is immediate)
3. Button is red/destructive instead of green/success
4. Message is "Candidature refusée" instead of "Candidature acceptée"

**From Story 6-4 (Producer View Mission Candidatures):**
- Candidatures list page exists
- ProducerCandidatureCard component exists
- Status badges and colors defined
- CandidatureResource returns all needed data

**From Story 6-1 (Candidature Schema):**
- Status enum with `rejected` value already exists
- Candidature model with mission relationship

### Git Patterns from Recent Commits

```
3135d80 docs: complete story 6-6 producer accept candidature
b25684f feat(candidature): add accept button and toast notifications
d2ef7e2 feat(candidature): add accept candidature service and composable
0e545cd test(candidature): add producer accept candidature tests
cc804c5 feat(producer): add accept candidature endpoint
```

Follow same patterns:
- `feat(producer):` for backend API endpoint
- `test(candidature):` for test files
- `feat(candidature):` for frontend features
- Separate commits for backend API, tests, frontend types/API, components

### Dependencies

- **Depends on**: Story 6-4 (Candidature list with card), Story 6-1 (Candidature schema), Story 6-6 (Accept - for UI pattern)
- **Blocks**: Nothing directly
- **Related**: Story 6-6 (Accept candidature - similar pattern)
- **Parallel with**: Story 6-8 (Face confirm mission)

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 6.7 - Producer Reject Candidature, FR39]
- [Source: _bmad-output/planning-artifacts/epics.md#Candidature Status Flow]
- [Source: backend/app/Enums/CandidatureStatus.php - Status enum]
- [Source: backend/app/Http/Controllers/Api/V1/Producer/CandidatureController.php - Existing controller with accept method]
- [Source: frontend/src/features/candidature/components/ProducerCandidatureCard.vue - Card to modify]
- [Source: frontend/src/features/candidature/composables/useAcceptCandidature.ts - Pattern to follow]
- [Source: _bmad-output/implementation-artifacts/6-6-producer-accept-candidature.md - Previous story (CRITICAL)]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

- No significant issues encountered during implementation

### Completion Notes List

- Backend: Added `reject` method to Producer CandidatureController with proper authorization checks
- Backend: Added POST `/v1/producer/candidatures/{candidature}/reject` route
- Backend: Created 13 comprehensive tests (44 assertions) covering all acceptance criteria
- Frontend: Added `rejectCandidature` method to candidatureApi service
- Frontend: Created `useRejectCandidature` composable with loading/error states
- Frontend: Updated ProducerCandidatureCard with Reject button (red, only visible for pending)
- Frontend: Added confirmation dialog before rejection (irréversible warning)
- Frontend: Updated ProducerCandidaturesSection to handle reject action with toast notifications
- All 585 backend tests pass (2584 assertions)
- TypeScript type checking passes

### File List

**Backend Files Modified:**
- `backend/app/Http/Controllers/Api/V1/Producer/CandidatureController.php` - Added reject method
- `backend/routes/api/producer.php` - Added reject candidature route

**Backend Files Created:**
- `backend/tests/Feature/Candidature/ProducerRejectCandidatureTest.php` - 13 tests

**Frontend Files Created:**
- `frontend/src/features/candidature/composables/useRejectCandidature.ts`

**Frontend Files Modified:**
- `frontend/src/features/candidature/services/candidatureApi.ts` - Added rejectCandidature method
- `frontend/src/features/candidature/composables/index.ts` - Exported useRejectCandidature
- `frontend/src/features/candidature/components/ProducerCandidatureCard.vue` - Added Reject button + confirmation dialog
- `frontend/src/features/candidature/components/ProducerCandidaturesSection.vue` - Added reject handling with toast
