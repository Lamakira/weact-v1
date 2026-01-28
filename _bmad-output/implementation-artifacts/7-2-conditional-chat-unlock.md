# Story 7.2: Conditional Chat Unlock

Status: done

## Story

As a **system**,
I want **chat to be automatically unlocked when a candidature is accepted**,
so that **only matched parties can communicate**.

## Acceptance Criteria

1. **Given** a Producer accepts a candidature **When** the status changes to "accepted" **Then** a conversation record is automatically created

2. **Given** a conversation is created **When** the candidature already has a conversation **Then** no duplicate conversation is created (idempotent)

3. **Given** a candidature is accepted **When** the conversation is created **Then** both Face and Producer can access the conversation

4. **Given** a candidature is NOT accepted (pending, rejected) **When** a user tries to access messaging **Then** they receive a 403 error

5. **Given** a candidature status changes from accepted to another status **When** queried **Then** the existing conversation remains accessible (no deletion on status change)

6. **Given** a Face confirms their candidature **When** the status changes to "confirmed" **Then** the conversation remains accessible (chat unlocked at "accepted" not "confirmed")

7. **Given** the conversation is created **When** queried **Then** it links correctly to the candidature with all relationships working

**(FR42, FR43 prerequisite - enables chat functionality)**

## Tasks / Subtasks

- [x] Task 1: Modify Producer CandidatureController::accept() to create conversation (AC: #1, #2)
  - [x] Import Conversation model
  - [x] After status update, create Conversation::firstOrCreate(['candidature_id' => $candidature->id])
  - [x] Use firstOrCreate to ensure idempotency (no duplicates)
  - [x] ~~Log conversation creation for debugging~~ (not needed, idempotent operation)

- [x] Task 2: Add CandidatureStatus helper method for chat access (AC: #3, #4)
  - [x] Add `allowsChatAccess()` method to CandidatureStatus enum (ALREADY EXISTS from previous story)
  - [x] Return true for: Accepted, Confirmed, InProgress, Completed
  - [x] Return false for: Pending, Rejected

- [x] Task 3: Create ConversationPolicy for authorization (AC: #3, #4)
  - [x] Create `app/Policies/ConversationPolicy.php`
  - [x] Add `view()` method - user must be Face or Producer of the candidature
  - [x] Add `sendMessage()` method - same as view + candidature.status.allowsChatAccess()
  - [x] ~~Register policy in AuthServiceProvider~~ (Laravel 11+ auto-discovers policies)

- [x] Task 4: Add conversation access helper to Candidature model (AC: #3, #4, #5, #6)
  - [x] Add `canAccessChat()` method that checks status.allowsChatAccess()
  - [x] Add `getConversationOrFail()` method that returns conversation or throws 404

- [x] Task 5: Create backend feature tests (AC: #1-7)
  - [x] Test conversation created when candidature accepted
  - [x] Test no duplicate conversation on multiple accepts (idempotent)
  - [x] Test Face can access conversation after acceptance
  - [x] Test Producer can access conversation after acceptance
  - [x] Test 403 when candidature is pending
  - [x] Test 403 when candidature is rejected
  - [x] Test conversation persists when status changes to confirmed
  - [x] Test conversation persists when status changes to in_progress
  - [x] Test CandidatureStatus::allowsChatAccess() returns correct values

- [x] Task 6: Verify Face confirm action preserves conversation (AC: #5, #6)
  - [x] Ensure FaceCandidatureController::confirm() doesn't affect conversation (verified - no conversation code)
  - [x] Add test to verify conversation accessible after confirm

## Dev Notes

### CRITICAL BUSINESS RULES

**Chat Access Flow:**
1. Face applies to mission → candidature created (pending) → NO CHAT
2. Producer accepts → candidature (accepted) → CHAT UNLOCKED (conversation created)
3. Face confirms → candidature (confirmed) → CHAT STILL ACCESSIBLE
4. Mission in progress → candidature (in_progress) → CHAT STILL ACCESSIBLE
5. Mission completed → candidature (completed) → CHAT STILL ACCESSIBLE (for reference)

**Key Decision: Create conversation on ACCEPT, not CONFIRM**
- Rationale: Producer and Face may need to coordinate before Face confirms
- The 2-step validation (accept → confirm) shouldn't block communication

### Implementation Pattern

**Modify accept() in Producer CandidatureController:**
```php
// After status update and notification creation...
use App\Models\Conversation;

// Create conversation (idempotent - won't duplicate if already exists)
Conversation::firstOrCreate(
    ['candidature_id' => $candidature->id]
);
```

**CandidatureStatus Enum Enhancement:**
```php
// In App\Enums\CandidatureStatus

/**
 * Check if this status allows chat access.
 * Chat is unlocked when candidature is accepted or beyond.
 */
public function allowsChatAccess(): bool
{
    return in_array($this, [
        self::Accepted,
        self::Confirmed,
        self::InProgress,
        self::Completed,
    ], true);
}
```

**Candidature Model Enhancement:**
```php
/**
 * Check if chat can be accessed for this candidature.
 */
public function canAccessChat(): bool
{
    return $this->status->allowsChatAccess();
}

/**
 * Get the conversation or fail with 404.
 */
public function getConversationOrFail(): Conversation
{
    $conversation = $this->conversation;

    if (!$conversation) {
        abort(404, 'Aucune conversation pour cette candidature');
    }

    return $conversation;
}
```

**ConversationPolicy:**
```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine if user can view the conversation.
     * Must be either the Face who applied or the Producer who owns the mission.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        $candidature = $conversation->candidature;

        // Check if user is the Face
        if ($user->userable_type === Face::class) {
            return $candidature->face_id === $user->userable_id;
        }

        // Check if user is the Producer
        if ($user->userable_type === Producer::class) {
            return $candidature->mission->producer_id === $user->userable_id;
        }

        return false;
    }

    /**
     * Determine if user can send messages in the conversation.
     * Must be able to view AND candidature must allow chat access.
     */
    public function sendMessage(User $user, Conversation $conversation): bool
    {
        if (!$this->view($user, $conversation)) {
            return false;
        }

        return $conversation->candidature->canAccessChat();
    }
}
```

### Files to Create

```
backend/app/Policies/ConversationPolicy.php
backend/tests/Feature/Messaging/ConditionalChatUnlockTest.php
```

### Files to Modify

```
backend/app/Http/Controllers/Api/V1/Producer/CandidatureController.php  # Add conversation creation
backend/app/Enums/CandidatureStatus.php                                  # Add allowsChatAccess()
backend/app/Models/Candidature.php                                       # Add canAccessChat(), getConversationOrFail()
backend/app/Providers/AuthServiceProvider.php                            # Register ConversationPolicy
```

### Testing Standards

- Use `RefreshDatabase` trait
- Use PHPUnit class-based syntax (project standard)
- Test all 7 acceptance criteria
- Test idempotency (multiple accepts don't create duplicates)
- Test authorization for both Face and Producer
- Test denial for non-participants
- All existing tests must pass (currently 647 backend tests)

### Dependencies

- **Depends on**: Story 7-1 (Conversation model must exist) ✓ COMPLETED
- **Blocks**: Stories 7-3, 7-4 (sending messages requires conversation access)

### Edge Cases to Handle

1. **Race condition**: Two simultaneous accept calls → firstOrCreate handles this
2. **Candidature without conversation**: Should return 404, not error
3. **Deleted candidature**: Cascade delete removes conversation (already handled by FK)
4. **Status rollback**: If candidature goes from accepted → pending (shouldn't happen but handle gracefully)

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 7.2 - Conditional Chat Unlock (FR42, FR43)]
- [Source: _bmad-output/implementation-artifacts/7-1-create-messaging-database-schema.md - Conversation model]
- [Source: backend/app/Http/Controllers/Api/V1/Producer/CandidatureController.php - Accept action]
- [Source: backend/app/Enums/CandidatureStatus.php - Status enum]
- [Source: backend/app/Models/Candidature.php - Candidature model with conversation relationship]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

No issues encountered during implementation.

### Completion Notes List

- Modified Producer CandidatureController::accept() to create conversation using Conversation::firstOrCreate() for idempotency
- CandidatureStatus::allowsChatAccess() already existed from previous implementation
- Created ConversationPolicy with view() and sendMessage() authorization methods
- Added canAccessChat() and getConversationOrFail() helper methods to Candidature model
- Laravel 11+ auto-discovers policies, no manual registration needed in AuthServiceProvider
- Created comprehensive test suite with 23 tests covering all 7 acceptance criteria
- All 670 backend tests pass (2805 assertions) - no regressions
- FaceCandidatureController::confirm() verified to not affect conversation (no conversation-related code)

### Change Log

| Date       | Author       | Change                                       |
|------------|--------------|----------------------------------------------|
| 2026-01-28 | SM Agent     | Story created - ready-for-dev                |
| 2026-01-28 | Dev Agent    | All tasks implemented - status: review       |
| 2026-01-28 | Review Agent | Code review - 2 MEDIUM issues fixed - done   |

## Senior Developer Review (AI)

**Review Date:** 2026-01-28
**Reviewer:** Claude Opus 4.5 (claude-opus-4-5-20251101)
**Outcome:** ✅ Approved (after fixes)

### Issues Found: 0 HIGH, 2 MEDIUM, 2 LOW

#### Action Items

- [x] [MEDIUM] Added tests using Gate facade to verify Laravel policy auto-discovery
- [x] [MEDIUM] Added test to verify Gate denies unauthorized users (defense in depth)
- [ ] [LOW] project-context.md states "Pest syntax" but project uses PHPUnit - documentation inconsistency (not blocking, separate PR)
- [ ] [LOW] No test for Admin user type - acceptable as Admins are separate model, not User role

### Review Notes

- All 7 acceptance criteria properly implemented
- All tasks verified as actually complete
- 25 comprehensive tests covering all ACs (2 new tests added for Gate facade verification)
- ConversationPolicy correctly implements view/sendMessage authorization
- Candidature model helpers (canAccessChat, getConversationOrFail) work correctly
- OWASP security review: No issues found (A01, A03, A04 checked)
- No N+1 query issues for single policy checks
- All 672 backend tests pass (2811 assertions)

### File List

**Created:**
- `backend/app/Policies/ConversationPolicy.php` - Authorization policy for conversations
- `backend/tests/Feature/Messaging/ConditionalChatUnlockTest.php` - 23 tests for all ACs

**Modified:**
- `backend/app/Http/Controllers/Api/V1/Producer/CandidatureController.php` - Added conversation creation on accept
- `backend/app/Models/Candidature.php` - Added canAccessChat() and getConversationOrFail() methods
