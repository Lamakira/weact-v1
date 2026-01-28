# Story 7.5: View Conversation History

Status: done

## Story

As a **user** (Face or Producer),
I want **to view the complete history of my conversations**,
so that **I can reference past discussions and track mission coordination**.

## Acceptance Criteria

1. **Given** I have an active conversation **When** I open the chat **Then** I see all previous messages in chronological order (oldest first)

2. **Given** I have a conversation with many messages **When** I scroll up **Then** I can view older messages (pagination or infinite scroll)

3. **Given** I close and reopen the chat **When** I return to the conversation **Then** all previous messages are still visible

4. **Given** I am viewing messages **When** they are displayed **Then** each message shows the sender, content, and timestamp

5. **Given** I am a Face viewing messages **When** the other participant is a Producer **Then** I see the Producer's name and photo in the header

6. **Given** I am a Producer viewing messages **When** the other participant is a Face **Then** I see the Face's name and photo in the header

**(FR44 - Un utilisateur peut consulter l'historique de ses conversations)**

## Tasks / Subtasks

### Backend Tasks

- [x] Task 1: Ensure messages are ordered chronologically (AC: #1)
  - [x] Verify Message query in ConversationController orders by created_at ASC
  - [x] Add explicit ordering if not present
  - [x] Added orderBy('created_at', 'asc')->orderBy('id', 'asc') to both Face and Producer controllers

- [N/A] Task 2: Add message pagination for long conversations (AC: #2) - **DEFERRED TO POST-MVP**
  - [N/A] Per Dev Notes: "Keep current 'load all messages' approach... Defer pagination to post-MVP"
  - [N/A] Current MVP approach loads all messages (acceptable for typical conversation sizes)

- [x] Task 3: Add backend tests for conversation history (AC: #1-6)
  - [x] Test messages returned in chronological order
  - [x] Test conversation persists across multiple requests
  - [x] Test other_participant data varies by user type
  - [x] Created ConversationHistoryTest.php with 14 tests

### Frontend Tasks

- [N/A] Task 4: Implement "Load More" for older messages (AC: #2) - **DEFERRED TO POST-MVP**
  - [N/A] Depends on Task 2 (backend pagination) which is deferred
  - [N/A] Current MVP loads all messages at once

- [N/A] Task 5: Add loading indicator for history loading (AC: #2) - **DEFERRED TO POST-MVP**
  - [N/A] Depends on Task 4 which is deferred

- [x] Task 6: Verify message display includes all required fields (AC: #4)
  - [x] Confirmed MessageBubble shows sender name (line 38-40)
  - [x] Confirmed MessageBubble shows timestamp (line 61-63)
  - [x] Confirmed MessageBubble shows content (line 51)
  - [x] Already complete from Story 7-3/7-4

- [N/A] Task 7: Add frontend tests for conversation history (AC: #1-6) - **NO NEW FRONTEND CODE**
  - [N/A] No new frontend functionality added in this story
  - [N/A] Existing ConversationView components already tested by Stories 7-3, 7-4

## Dev Notes

### MANDATORY: Use Gemini MCP for UI/UX Tasks

**All frontend component creation MUST use the `gemini-mcp` tools:**

- **Modifying existing components** (adding load more button):
  - Use `modify_frontend` tool
  - Provide the existing ConversationView.vue as context

- **Workflow:**
  1. Call appropriate gemini-mcp tool with design requirements
  2. Gemini returns the code
  3. YOU write the returned code to disk
  4. Verify consistency with existing design system

---

### Current Implementation Analysis

**What's Already Done (from Stories 7-3, 7-4):**

1. **Face ConversationView.vue** - Displays messages, handles send
2. **Producer ProducerConversationView.vue** - Same for Producer
3. **ConversationResource.php** - Returns messages, other_participant, mission_title
4. **MessageResource.php** - Returns id, content, created_at, is_own_message, sender
5. **ConversationController (Face/Producer)** - Loads conversation with messages

**What May Be Missing:**

1. **Explicit message ordering** - Need to verify `orderBy('created_at', 'asc')` is applied
2. **Pagination** - Currently loads all messages at once (will not scale)
3. **Load more UI** - No "load older messages" button exists

### Implementation Approach

**Backend Changes:**

1. **Update ConversationController::show() (both Face and Producer):**
```php
// Add explicit ordering when loading messages
$conversation->load([
    'messages' => function ($query) {
        $query->orderBy('created_at', 'asc');
    },
    'messages.sender.userable',
    'candidature.mission.producer',
    'candidature.face',
]);
```

2. **For pagination (optional in MVP, recommended for scalability):**
```php
// Option A: Return all messages (current approach - OK for MVP)
// Option B: Cursor-based pagination for production scale
$perPage = min($request->integer('per_page', 50), 100);
$beforeId = $request->integer('before_id');

$messagesQuery = $conversation->messages()
    ->with('sender.userable')
    ->orderBy('created_at', 'asc');

if ($beforeId) {
    $messagesQuery->where('id', '<', $beforeId);
}

$messages = $messagesQuery->take($perPage)->get();
```

**Frontend Changes (if pagination added):**

```typescript
// In useConversation composable
const loadMoreMessages = async (conversationId: number, beforeId: number) => {
  const response = await messagingApi.getConversation(conversationId, { before_id: beforeId })
  // Prepend older messages to beginning of array
  conversation.value?.messages.unshift(...response.data.messages)
}
```

### Key Files to Modify

**Backend:**
```
backend/app/Http/Controllers/Api/V1/Face/ConversationController.php
backend/app/Http/Controllers/Api/V1/Producer/ConversationController.php
backend/app/Http/Resources/ConversationResource.php (if adding pagination meta)
```

**Frontend:**
```
frontend/src/features/messaging/components/ConversationView.vue
frontend/src/features/messaging/components/ProducerConversationView.vue
frontend/src/features/messaging/composables/useConversation.ts
frontend/src/features/messaging/composables/useProducerConversation.ts
```

### Existing Code Patterns

**ConversationController Pattern (from Story 7-4):**
```php
public function show(Request $request, Conversation $conversation): JsonResponse
{
    Gate::authorize('view', $conversation);

    $user = $request->user();

    // Mark unread messages as read
    $conversation->messages()
        ->whereNull('read_at')
        ->where('sender_id', '!=', $user->id)
        ->update(['read_at' => now()]);

    // Load relationships - ADD explicit ordering here
    $conversation->load([
        'messages' => fn($q) => $q->orderBy('created_at', 'asc'),
        'messages.sender.userable',
        'candidature.mission.producer',
        'candidature.face',
    ]);

    return response()->json([
        'data' => new ConversationResource($conversation),
    ]);
}
```

**MessageBubble Data (already shows):**
- `message.content` - The message text
- `message.created_at` - Timestamp (formatted in component)
- `message.is_own_message` - Determines alignment
- `message.sender.name` - Sender name (may need to add to display)

### Testing Standards

**Backend (PHPUnit class-based - project standard):**
```php
// tests/Feature/Messaging/ConversationHistoryTest.php

class ConversationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_messages_are_returned_in_chronological_order(): void
    {
        // Create messages out of order
        $message2 = Message::factory()->create([...], 'created_at' => now());
        $message1 = Message::factory()->create([...], 'created_at' => now()->subHour());

        // Fetch conversation
        $response = $this->actingAs($user)->getJson("/api/v1/face/conversations/{$conversation->id}");

        // Assert order
        $messages = $response->json('data.messages');
        $this->assertEquals($message1->id, $messages[0]['id']);
        $this->assertEquals($message2->id, $messages[1]['id']);
    }

    public function test_conversation_history_persists_across_requests(): void
    {
        // Send message
        $this->actingAs($user)->postJson("...messages", ['content' => 'Test']);

        // Fetch conversation twice
        $response1 = $this->actingAs($user)->getJson("...conversation");
        $response2 = $this->actingAs($user)->getJson("...conversation");

        // Assert same messages returned
        $this->assertEquals(
            $response1->json('data.messages'),
            $response2->json('data.messages')
        );
    }

    public function test_face_sees_producer_as_other_participant(): void
    {
        $response = $this->actingAs($faceUser)->getJson("...conversation");

        $response->assertJsonPath('data.other_participant.type', 'producer');
    }

    public function test_producer_sees_face_as_other_participant(): void
    {
        $response = $this->actingAs($producerUser)->getJson("...conversation");

        $response->assertJsonPath('data.other_participant.type', 'face');
    }
}
```

### Dependencies

- **Depends on:** Story 7-1 (Messaging schema) ✓ COMPLETED
- **Depends on:** Story 7-2 (Chat unlock) ✓ COMPLETED
- **Depends on:** Story 7-3 (Face messaging) ✓ COMPLETED
- **Depends on:** Story 7-4 (Producer messaging) ✓ COMPLETED
- **Blocks:** Story 7-6 (Manual refresh - will build on this)
- **Blocks:** Story 7-7 (Conversations list)

### MVP Scope Decision

**For MVP (recommended):**
- Keep current "load all messages" approach
- Add explicit ordering
- Verify existing implementation meets ACs
- Defer pagination to post-MVP optimization

**For Production Scale (future):**
- Add cursor-based pagination
- "Load more" button at top of chat
- Consider lazy loading on scroll

### Edge Cases to Handle

1. **Empty conversation** - Show "Commencez la discussion" (already handled)
2. **Very long conversations** - Without pagination, may be slow (acceptable for MVP)
3. **Messages created at exact same timestamp** - Order by id as secondary sort
4. **Deleted messages** - Not implemented yet (no soft delete on messages)
5. **Network failure during load** - Show retry button (already handled)

### UI Verification Checklist

Verify existing UI already displays:
- [x] Message content (MessageBubble)
- [x] Timestamp (MessageBubble - "14:32" format)
- [x] Sender alignment (is_own_message - right/left)
- [x] Other participant name in header (ConversationHeader)
- [x] Other participant photo in header (ConversationHeader)
- [ ] Sender name per message (may be missing - verify)

### References

**Backend:**
- [Source: backend/app/Http/Controllers/Api/V1/Face/ConversationController.php]
- [Source: backend/app/Http/Controllers/Api/V1/Producer/ConversationController.php]
- [Source: backend/app/Http/Resources/ConversationResource.php]
- [Source: backend/app/Http/Resources/MessageResource.php]

**Frontend:**
- [Source: frontend/src/features/messaging/components/ConversationView.vue]
- [Source: frontend/src/features/messaging/components/ProducerConversationView.vue]
- [Source: frontend/src/features/messaging/components/MessageBubble.vue]
- [Source: frontend/src/features/messaging/components/ConversationHeader.vue]

**Business Rules:**
- [Source: _bmad-output/project-context.md#Critical Business Rules - Chat access only after acceptance]
- [Source: _bmad-output/planning-artifacts/epics.md#FR44]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

None - implementation was straightforward.

### Completion Notes List

1. **Task 1 (Chronological Ordering):** Added explicit `orderBy('created_at', 'asc')->orderBy('id', 'asc')` to both Face and Producer ConversationController::show() methods. This ensures reliable ordering regardless of data insertion order.

2. **Task 3 (Backend Tests):** Created `ConversationHistoryTest.php` with 14 comprehensive tests covering:
   - Message chronological ordering (AC #1)
   - Messages with same timestamp ordered by ID
   - Conversation persistence across requests (AC #3)
   - Message content, timestamp, sender info (AC #4)
   - Face sees Producer as other_participant (AC #5)
   - Producer sees Face as other_participant (AC #6)
   - Empty conversation handling
   - Mission title inclusion

3. **Task 6 (UI Verification):** Verified MessageBubble.vue already shows all required fields:
   - Content (line 51)
   - Timestamp in "HH:mm" format (lines 61-63)
   - Sender name for other's messages (lines 38-40)

4. **MVP Scope:** Deferred pagination (Tasks 2, 4, 5, 7) to post-MVP per Dev Notes. Current "load all messages" approach meets AC requirements for typical conversation sizes.

### Implementation Plan

- Added explicit message ordering to ensure chronological display
- Verified existing UI components already meet AC requirements
- Created comprehensive backend tests for history functionality
- Deferred pagination to post-MVP optimization phase

### File List

#### Created

- backend/tests/Feature/Messaging/ConversationHistoryTest.php

#### Modified

- backend/app/Http/Controllers/Api/V1/Face/ConversationController.php
- backend/app/Http/Controllers/Api/V1/Producer/ConversationController.php
- _bmad-output/implementation-artifacts/sprint-status.yaml

## Code Review

### Review Date
2026-01-28

### Reviewer
Claude Opus 4.5 (Adversarial Code Review)

### Findings Summary
- **HIGH:** 0
- **MEDIUM:** 2 (both fixed)
- **LOW:** 2 (noted, not blocking)

### Issues Found and Resolution

**MEDIUM-1: sprint-status.yaml not in File List** ✅ FIXED
- Modified file was not documented in story's Dev Agent Record → File List
- Resolution: Added to File List under "Modified"

**MEDIUM-2: Missing authorization tests** ✅ FIXED
- ConversationHistoryTest lacked authorization tests for security coverage
- Resolution: Added 3 tests:
  - `test_unauthenticated_user_cannot_view_conversation_history`
  - `test_other_face_cannot_view_conversation_history`
  - `test_other_producer_cannot_view_conversation_history`

**LOW-1: PHPUnit vs Pest style** (not fixed)
- Test uses PHPUnit class-based style, but consistent with other messaging tests
- Recommendation: Document actual practice in project-context.md

**LOW-2: Pagination deferred without tracking** (not fixed)
- Tasks 2, 4, 5, 7 deferred but no tech debt ticket created
- Recommendation: Consider future backlog item for pagination

### Verification Results
- All 6 Acceptance Criteria: ✅ VERIFIED
- All [x] Tasks: ✅ VERIFIED
- Test Suite: 751 tests pass (3 new)
- TypeScript: ✅ No errors
- OWASP Security: ✅ A01, A03, A04 verified

## Change Log

| Date       | Author       | Change                                       |
|------------|--------------|----------------------------------------------|
| 2026-01-28 | SM Agent     | Story created - ready-for-dev                |
| 2026-01-28 | Dev Agent    | Tasks 1, 3, 6 complete; 2, 4, 5, 7 deferred  |
| 2026-01-28 | Review Agent | Code review: 2 MEDIUM fixed, status → done   |
