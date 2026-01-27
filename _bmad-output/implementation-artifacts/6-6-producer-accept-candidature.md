# Story 6.6: Producer Accept Candidature

Status: done

## Story

As a **Producer**,
I want **to accept a Face's candidature**,
so that **they know they are selected and can confirm their participation**.

## Acceptance Criteria

1. **Given** I am viewing candidatures for my mission **When** a candidature has status "pending" **Then** I see an "Accepter" button

2. **Given** I click "Accepter" on a pending candidature **When** the action completes **Then** the candidature status changes to "accepted" **And** I see a success message "Candidature acceptée"

3. **Given** I have accepted a candidature **When** I view the candidatures list **Then** this candidature shows status "Acceptée" with appropriate styling

4. **Given** a candidature is already "accepted", "confirmed", "in_progress", "completed", or "rejected" **When** I view it **Then** I do not see the "Accepter" button

5. **Given** I am a Face (not Producer) **When** I try to accept a candidature **Then** I get a 403 error

6. **Given** I am a Producer but the mission doesn't belong to me **When** I try to accept a candidature **Then** I get a 403 error

7. **Given** the candidature does not exist **When** I try to accept it **Then** I get a 404 error

8. **Given** I accept a candidature **When** the status changes **Then** a notification is triggered for the Face (prep for Story 6-9)

**(FR38)**

## Tasks / Subtasks

- [x] Task 1: Add accept method to Producer CandidatureController (AC: #1-7)
  - [x] Create `accept(Candidature $candidature)` method
  - [x] Verify authenticated user is a Producer
  - [x] Verify candidature's mission belongs to this Producer
  - [x] Verify candidature status is "pending"
  - [x] Update status to "accepted"
  - [x] Return updated candidature with success message

- [x] Task 2: Add route for accept candidature (AC: #2)
  - [x] Add `POST /v1/producer/candidatures/{candidature}/accept` route
  - [x] Apply existing producer middleware

- [x] Task 3: Create backend feature tests (AC: #1-8)
  - [x] Create `tests/Feature/Candidature/ProducerAcceptCandidatureTest.php`
  - [x] Test Producer can accept pending candidature
  - [x] Test status changes from pending to accepted
  - [x] Test cannot accept non-pending candidature (already accepted/rejected/etc.)
  - [x] Test Face cannot accept (403)
  - [x] Test Producer cannot accept candidature for another Producer's mission (403)
  - [x] Test non-existent candidature (404)
  - [x] Test unauthenticated (401)

- [x] Task 4: Add acceptCandidature method to candidatureApi (AC: #2)
  - [x] Add `acceptCandidature(candidatureId: number)` method
  - [x] Return updated candidature data

- [x] Task 5: Update ProducerCandidatureCard to show Accept button (AC: #1, #4)
  - [x] Add "Accepter" button visible only for pending candidatures
  - [x] Add loading state during accept action
  - [x] Style button with primary/success color

- [x] Task 6: Create useAcceptCandidature composable (AC: #2, #3)
  - [x] Create `frontend/src/features/candidature/composables/useAcceptCandidature.ts`
  - [x] Handle API call, loading, error states
  - [x] Emit event on success to refresh parent list
  - [x] Export from composables/index.ts

- [x] Task 7: Update ProducerCandidaturesSection to handle accept (AC: #3)
  - [x] Refresh candidature list after acceptance
  - [x] Show toast notification on success

- [x] Task 8: Add toast/notification for success feedback (AC: #2)
  - [x] Show "Candidature acceptée" toast on success
  - [x] Show error toast on failure

- [x] Task 9: Update candidature types for accept response (AC: #2)
  - [x] Ensure CandidatureResponse type covers accept response

- [x] Task 10: TypeScript and test verification
  - [x] TypeScript type checking passes
  - [x] All backend tests pass (no regressions)

## Dev Notes

### CRITICAL: Status Transition Rules

**Valid status transitions:**
- `pending` → `accepted` (this story)
- `pending` → `rejected` (story 6-7)
- `accepted` → `confirmed` (story 6-8, Face action)
- `confirmed` → `in_progress` (automatic when mission starts)
- `in_progress` → `completed` (when mission marked complete)

**This story ONLY handles:** `pending` → `accepted`

### Architecture Patterns

**Backend:**
- Add method to existing `Producer/CandidatureController`
- Use Candidature model binding for route
- Authorization: Producer owns the mission
- Status validation: Only accept pending candidatures

**Frontend:**
- Add accept functionality to existing `ProducerCandidatureCard`
- Optimistic UI update or refetch after success
- Toast notification for feedback

### API Endpoint

```
POST /api/v1/producer/candidatures/{candidature_id}/accept
Authorization: Bearer {token}

Response (200 OK):
{
  "data": {
    "id": 15,
    "mission_id": 3,
    "face_id": 7,
    "status": "accepted",
    "status_label": "Acceptée",
    "message_motivation": "Je suis très motivé...",
    "created_at": "2026-01-20T10:30:00Z",
    "updated_at": "2026-01-27T14:00:00Z"
  },
  "message": "Candidature acceptée avec succès"
}

Response (400): Candidature is not pending
Response (401): Unauthenticated
Response (403): Not a Producer OR Mission doesn't belong to Producer
Response (404): Candidature not found
```

### Controller Implementation Pattern

```php
// Producer\CandidatureController
public function accept(Candidature $candidature): JsonResponse
{
    $user = request()->user();

    // Verify user is a Producer
    if ($user->userable_type !== Producer::class) {
        abort(403, 'Accès réservé aux Producteurs');
    }

    $producer = $user->userable;

    // Verify candidature's mission belongs to this Producer
    if ($candidature->mission->producer_id !== $producer->id) {
        abort(403, 'Cette candidature ne concerne pas une de vos missions');
    }

    // Verify candidature is pending
    if ($candidature->status !== CandidatureStatus::Pending) {
        return response()->json([
            'error' => [
                'code' => 'INVALID_STATUS',
                'message' => 'Seules les candidatures en attente peuvent être acceptées',
            ]
        ], 400);
    }

    // Update status
    $candidature->status = CandidatureStatus::Accepted;
    $candidature->save();

    return response()->json([
        'data' => new CandidatureResource($candidature),
        'message' => 'Candidature acceptée avec succès',
    ]);
}
```

### Frontend Button Pattern

```vue
<!-- In ProducerCandidatureCard.vue -->
<button
  v-if="candidature.status === 'pending'"
  type="button"
  class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
  :disabled="isAccepting"
  @click="handleAccept"
>
  <Loader2 v-if="isAccepting" class="h-4 w-4 animate-spin" />
  <Check v-else class="h-4 w-4" />
  {{ isAccepting ? 'Acceptation...' : 'Accepter' }}
</button>
```

### Status Badge Colors (from existing patterns)

```typescript
const CandidatureStatusColor: Record<CandidatureStatusType, string> = {
  pending: 'yellow',    // En attente
  accepted: 'blue',     // Acceptée
  confirmed: 'green',   // Confirmée
  in_progress: 'purple', // En cours
  completed: 'emerald', // Terminée
  rejected: 'red',      // Refusée
}
```

### Existing Files to Modify

**Backend:**
- `app/Http/Controllers/Api/V1/Producer/CandidatureController.php` - Add accept method
- `routes/api/producer.php` - Add POST candidatures/{candidature}/accept route

**Backend to Create:**
- `tests/Feature/Candidature/ProducerAcceptCandidatureTest.php`

**Frontend:**
- `src/features/candidature/components/ProducerCandidatureCard.vue` - Add accept button
- `src/features/candidature/services/candidatureApi.ts` - Add acceptCandidature method
- `src/features/candidature/composables/index.ts` - Export new composable

**Frontend to Create:**
- `src/features/candidature/composables/useAcceptCandidature.ts`

### Test Scenarios

| Scenario | Input | Expected |
|----------|-------|----------|
| Accept pending candidature | POST as mission owner | 200, status = accepted |
| Accept already accepted | POST on accepted | 400, error message |
| Accept rejected | POST on rejected | 400, error message |
| Face tries to accept | POST as Face | 403 |
| Wrong Producer | POST on other's mission | 403 |
| Non-existent | Invalid candidature_id | 404 |
| Unauthenticated | No token | 401 |

### Existing Code Reference

**CandidatureController (from Story 6-4):**
```php
// backend/app/Http/Controllers/Api/V1/Producer/CandidatureController.php
public function index(Mission $mission, Request $request): CandidatureCollection
```
- Already has Producer authorization pattern
- Add `accept` method following same pattern

**CandidatureStatus Enum:**
```php
// backend/app/Enums/CandidatureStatus.php
enum CandidatureStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';
}
```

**ProducerCandidatureCard (from Story 6-4):**
- Already displays candidature with status
- Add accept/reject action buttons

### Previous Story Intelligence

**From Story 6-5 (Producer View Candidate Profile):**
- Producer can view full profile before accepting
- Profile link in candidature card works
- Authorization pattern for Producer → Face relationship

**From Story 6-4 (Producer View Mission Candidatures):**
- Candidatures list page exists
- ProducerCandidatureCard component exists
- Status badges and colors defined
- CandidatureResource returns all needed data

**From Story 6-1 (Candidature Schema):**
- Status enum with all values
- Candidature model with mission relationship

### Git Patterns from Recent Commits

```
feat(candidature): add candidate profile section components
feat(candidature): add candidate profile page and composable
feat(candidature): add candidate profile types and API
test(producer): add candidate profile viewing tests
feat(producer): add candidate profile viewing endpoint
```

Follow same patterns:
- `feat(candidature):` for new features
- `test(producer):` for test files
- Separate commits for backend API, tests, frontend types/API, components

### Dependencies

- **Depends on**: Story 6-4 (Candidature list with card), Story 6-1 (Candidature schema)
- **Blocks**: Story 6-8 (Face confirm mission - needs accepted status first)
- **Related**: Story 6-7 (Reject candidature - similar pattern)
- **Enables**: Epic 7 (Messaging) - chat unlocked after acceptance

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 6.6 - Producer Accept Candidature, FR38]
- [Source: _bmad-output/planning-artifacts/epics.md#Candidature Status Flow]
- [Source: backend/app/Enums/CandidatureStatus.php - Status enum]
- [Source: backend/app/Http/Controllers/Api/V1/Producer/CandidatureController.php - Existing controller]
- [Source: frontend/src/features/candidature/components/ProducerCandidatureCard.vue - Card to modify]
- [Source: _bmad-output/implementation-artifacts/6-4-producer-view-mission-candidatures.md - Previous story]
- [Source: _bmad-output/implementation-artifacts/6-5-producer-view-candidate-full-profile.md - Previous story]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

- No significant issues encountered during implementation

### Completion Notes List

- Backend: Added `accept` method to Producer CandidatureController with proper authorization checks
- Backend: Added POST `/v1/producer/candidatures/{candidature}/accept` route
- Backend: Created 13 comprehensive tests (44 assertions) covering all acceptance criteria
- Frontend: Added `acceptCandidature` method to candidatureApi service
- Frontend: Created `useAcceptCandidature` composable with loading/error states
- Frontend: Updated ProducerCandidatureCard with Accept button (only visible for pending)
- Frontend: Updated ProducerCandidaturesSection to handle accept action with toast notifications
- All 572 backend tests pass
- TypeScript type checking passes

### File List

**Backend Files Modified:**
- `backend/app/Http/Controllers/Api/V1/Producer/CandidatureController.php` - Added accept method
- `backend/routes/api/producer.php` - Added accept candidature route

**Backend Files Created:**
- `backend/tests/Feature/Candidature/ProducerAcceptCandidatureTest.php` - 13 tests

**Frontend Files Created:**
- `frontend/src/features/candidature/composables/useAcceptCandidature.ts`

**Frontend Files Modified:**
- `frontend/src/features/candidature/services/candidatureApi.ts` - Added acceptCandidature method
- `frontend/src/features/candidature/composables/index.ts` - Exported useAcceptCandidature
- `frontend/src/features/candidature/components/ProducerCandidatureCard.vue` - Added Accept button
- `frontend/src/features/candidature/components/ProducerCandidaturesSection.vue` - Added accept handling with toast
