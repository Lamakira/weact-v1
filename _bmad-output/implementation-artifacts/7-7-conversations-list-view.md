# Story 7.7: Conversations List View

Status: done

## Story

As a **user** (Face or Producer),
I want **to see all my conversations in one place**,
so that **I can easily navigate between different chats**.

## Acceptance Criteria

1. **Given** I navigate to "Messages" **When** the page loads **Then** I see a list of all my conversations

2. **Given** I have multiple conversations **When** I view the list **Then** each conversation shows: participant name, participant photo, mission title, last message preview, unread count badge, and timestamp

3. **Given** I have unread messages in a conversation **When** I view the list **Then** that conversation displays an unread count badge (e.g., "3")

4. **Given** I have no conversations **When** I navigate to Messages **Then** I see an empty state message ("Aucune conversation" with helpful guidance)

5. **Given** I click on a conversation in the list **When** the navigation completes **Then** I am taken to that conversation's chat view

6. **Given** my conversations list is loading **When** the API call is in progress **Then** I see a loading skeleton

7. **Given** a network error occurs **When** fetching conversations **Then** I see an error state with retry button

## Tasks / Subtasks

### Backend Tasks

- [x] Task 1: Add index endpoint to Face ConversationController (AC: #1, #2, #3)
  - [x] Create `index()` method returning all Face's conversations
  - [x] Query via candidature where face_id matches authenticated user
  - [x] Include: other_participant, mission_title, latest_message, unread_count
  - [x] Order by latest message timestamp (most recent first)
  - [x] Include pagination (15 per page)

- [x] Task 2: Add index endpoint to Producer ConversationController (AC: #1, #2, #3)
  - [x] Create `index()` method returning all Producer's conversations
  - [x] Query via candidature->mission where producer_id matches
  - [x] Same fields as Face endpoint
  - [x] Order by latest message timestamp
  - [x] Include pagination

- [x] Task 3: Create ConversationListResource (AC: #2, #3)
  - [x] Lighter resource than ConversationResource (no full messages array)
  - [x] Include: id, other_participant, mission_title, latest_message, unread_count, updated_at
  - [x] latest_message should include: content (truncated), sender_name, created_at

- [x] Task 4: Add backend routes (AC: #1)
  - [x] Add `GET /api/v1/face/conversations` route
  - [x] Add `GET /api/v1/producer/conversations` route
  - [x] Apply throttle and auth middleware

- [x] Task 5: Add backend tests (AC: #1-7)
  - [x] Test Face can list own conversations only
  - [x] Test Producer can list own conversations only
  - [x] Test unread count is accurate
  - [x] Test pagination works correctly
  - [x] Test empty state returns empty array
  - [x] Test unauthorized access returns 401/403

### Frontend Tasks

- [x] Task 6: Add API methods to messagingApi.ts (AC: #1)
  - [x] Add `getConversations()` for Face
  - [x] Add `getProducerConversations()` for Producer
  - [x] Define `ConversationListItem` type
  - [x] Define `ConversationsListResponse` type

- [x] Task 7: Create useConversationsList composable (AC: #1, #6, #7)
  - [x] Implement `loadConversations()` method
  - [x] Track `isLoading`, `error`, `conversations` state
  - [x] Add `refreshConversations()` for manual refresh
  - [x] Add `hasConversations` computed

- [x] Task 8: Create useProducerConversationsList composable (AC: #1, #6, #7)
  - [x] Mirror Face composable for Producer
  - [x] Use Producer-specific API endpoint

- [x] Task 9: Create ConversationListItem component (AC: #2, #3, #5)
  - [x] Props: conversation item data
  - [x] Display: avatar, name, mission title, last message preview, unread badge, time
  - [x] Clickable to navigate to conversation
  - [x] Visual distinction for unread conversations

- [x] Task 10: Create FaceConversationsPage.vue (AC: #1, #2, #3, #4, #5, #6, #7)
  - [x] Use useConversationsList composable
  - [x] Show loading skeleton while fetching
  - [x] Show empty state when no conversations
  - [x] Show error state with retry on failure
  - [x] List ConversationListItem components
  - [x] Navigate to face-conversation on click

- [x] Task 11: Create ProducerConversationsPage.vue (AC: #1-7)
  - [x] Mirror Face page for Producer
  - [x] Navigate to producer-conversation on click

- [x] Task 12: Add frontend routes (AC: #5)
  - [x] Add `/face/messages` route → FaceConversationsPage
  - [x] Add `/producer/messages` route → ProducerConversationsPage

- [x] Task 13: Update navigation to include Messages link
  - [x] Add "Messages" link to Face sidebar/nav
  - [x] Add "Messages" link to Producer sidebar/nav

## Dev Notes

### MANDATORY: Use Gemini MCP for UI/UX Tasks

**All frontend component creation/modification MUST use the `gemini-mcp` tools:**

- **Creating ConversationListItem component:**
  - Use `create_frontend` or `snippet_frontend` tool
  - Provide existing design system context (Tailwind 4.1 theme)

- **Creating ConversationsPage:**
  - Use `create_frontend` tool
  - Reference existing page patterns (FaceCandidaturesPage.vue)

- **Workflow:**
  1. Call appropriate gemini-mcp tool with design requirements
  2. Gemini returns the code
  3. YOU write the returned code to disk
  4. Verify consistency with existing design system

---

### Architecture Analysis

**Backend Pattern (from existing ConversationController):**
```php
// Existing show() method pattern:
Gate::authorize('view', $conversation);
$conversation->load(['messages', 'messages.sender.userable', ...]);
return response()->json(['data' => new ConversationResource($conversation)]);
```

**For index(), follow this pattern:**
```php
public function index(Request $request): JsonResponse
{
    $user = $request->user();
    $face = $user->userable;

    // Get conversations where this Face is the candidate
    $conversations = Conversation::whereHas('candidature', function ($query) use ($face) {
        $query->where('face_id', $face->id);
    })
    ->with(['candidature.mission.producer', 'candidature.face', 'latestMessage.sender'])
    ->orderByDesc(Message::select('created_at')
        ->whereColumn('conversation_id', 'conversations.id')
        ->latest()
        ->limit(1)
    )
    ->paginate(15);

    return response()->json([
        'data' => ConversationListResource::collection($conversations),
        'meta' => [
            'current_page' => $conversations->currentPage(),
            'last_page' => $conversations->lastPage(),
            'per_page' => $conversations->perPage(),
            'total' => $conversations->total(),
        ],
    ]);
}
```

### ConversationListResource Structure

```php
public function toArray(Request $request): array
{
    $currentUser = $request->user();
    $latestMessage = $this->latestMessage;

    return [
        'id' => $this->id,
        'candidature_id' => $this->candidature_id,
        'mission_title' => $this->candidature?->mission?->titre ?? '',
        'other_participant' => $this->getOtherParticipant($currentUser),
        'latest_message' => $latestMessage ? [
            'content' => Str::limit($latestMessage->content, 50),
            'sender_name' => $latestMessage->sender?->userable?->display_name ?? 'Unknown',
            'is_mine' => $latestMessage->sender_id === $currentUser->id,
            'created_at' => $latestMessage->created_at->toIso8601String(),
        ] : null,
        'unread_count' => $currentUser ? $this->unreadCountFor($currentUser) : 0,
        'updated_at' => $this->updated_at->toIso8601String(),
    ];
}
```

### Frontend Types

```typescript
// In features/messaging/types/index.ts
export interface ConversationListItem {
  id: number
  candidature_id: number
  mission_title: string
  other_participant: {
    id: number
    name: string
    photo_url: string | null
    type: 'face' | 'producer'
  }
  latest_message: {
    content: string
    sender_name: string
    is_mine: boolean
    created_at: string
  } | null
  unread_count: number
  updated_at: string
}

export interface ConversationsListResponse {
  data: ConversationListItem[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
```

### Key Files to Create

**Backend:**
```
backend/app/Http/Controllers/Api/V1/Face/ConversationController.php (modify - add index)
backend/app/Http/Controllers/Api/V1/Producer/ConversationController.php (modify - add index)
backend/app/Http/Resources/ConversationListResource.php (create)
backend/routes/api/face.php (modify - add route)
backend/routes/api/producer.php (modify - add route)
backend/tests/Feature/Messaging/ConversationsListTest.php (create)
```

**Frontend:**
```
frontend/src/features/messaging/types/index.ts (modify - add list types)
frontend/src/features/messaging/services/messagingApi.ts (modify - add list endpoints)
frontend/src/features/messaging/composables/useConversationsList.ts (create)
frontend/src/features/messaging/composables/useProducerConversationsList.ts (create)
frontend/src/features/messaging/components/ConversationListItem.vue (create)
frontend/src/pages/face/messaging/FaceConversationsPage.vue (create)
frontend/src/pages/producer/messaging/ProducerConversationsPage.vue (create)
frontend/src/router/index.ts (modify - add routes)
```

### Existing Code Patterns (from Story 7-6)

**useConversation composable pattern:**
```typescript
export function useConversation() {
  const conversation = ref<Conversation | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function loadConversation(conversationId: number): Promise<boolean> {
    isLoading.value = true
    error.value = null
    try {
      const response = await messagingApi.getConversation(conversationId)
      conversation.value = response.data
      return true
    } catch (err: unknown) {
      // Handle error...
    } finally {
      isLoading.value = false
    }
  }

  return { conversation, isLoading, error, loadConversation }
}
```

### UI/UX Requirements

**Conversations List Layout:**
- Header: "Messages" title with optional refresh button
- List: Vertical stack of ConversationListItem components
- Empty state: Centered icon + text
- Loading: Skeleton cards (3-5)

**ConversationListItem Design:**
- Height: ~72px
- Layout: [Avatar (48px)] [Content Area] [Time/Badge]
- Avatar: Rounded, border if unread
- Content: Name (bold), Mission title (muted), Last message preview (truncated)
- Right side: Relative time ("il y a 2h"), Unread badge (red circle with count)
- Hover: Background highlight
- Unread: Bolder text, highlighted background

**Empty State:**
- Icon: MessageSquare from lucide-vue-next
- Title: "Aucune conversation"
- Subtitle: "Les discussions apparaîtront ici lorsqu'une candidature sera acceptée"

**Error State:**
- Icon: AlertCircle
- Title: "Une erreur est survenue"
- Subtitle: Error message
- Button: "Réessayer"

### Dependencies

- **Depends on:** Story 7-6 (Manual message refresh) ✓ COMPLETED
- **Depends on:** Story 7-2 (Conditional chat unlock) ✓ COMPLETED
- **Blocks:** Epic 7 completion

### Edge Cases to Handle

1. **No conversations** - Show empty state, not empty list
2. **Conversation without messages yet** - Show "Nouvelle conversation" as last message
3. **Very long last message** - Truncate to 50 chars with ellipsis
4. **Pagination** - Load more on scroll (if implementing infinite scroll) or pagination controls
5. **Real-time updates** - For MVP, user can manually refresh; WebSockets deferred to V2

### Testing Standards

**Backend (Pest):**
```php
it('lists conversations for authenticated Face', function () {
    $face = Face::factory()->create();
    $user = $face->user;

    // Create conversations via candidatures
    $candidature = Candidature::factory()
        ->for($face)
        ->accepted()
        ->create();
    $conversation = Conversation::factory()
        ->for($candidature)
        ->hasMessages(3)
        ->create();

    actingAs($user)
        ->getJson('/api/v1/face/conversations')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'mission_title',
                    'other_participant' => ['id', 'name', 'photo_url', 'type'],
                    'latest_message',
                    'unread_count',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('returns empty array when Face has no conversations', function () {
    $face = Face::factory()->create();

    actingAs($face->user)
        ->getJson('/api/v1/face/conversations')
        ->assertOk()
        ->assertJson(['data' => []]);
});
```

### References

**Backend:**
- [Source: backend/app/Http/Controllers/Api/V1/Face/ConversationController.php]
- [Source: backend/app/Http/Controllers/Api/V1/Producer/ConversationController.php]
- [Source: backend/app/Http/Resources/ConversationResource.php]
- [Source: backend/app/Models/Conversation.php]
- [Source: backend/routes/api/face.php]
- [Source: backend/routes/api/producer.php]

**Frontend:**
- [Source: frontend/src/features/messaging/services/messagingApi.ts]
- [Source: frontend/src/features/messaging/composables/useConversation.ts]
- [Source: frontend/src/features/messaging/composables/useProducerConversation.ts]
- [Source: frontend/src/features/messaging/components/ConversationView.vue]
- [Source: frontend/src/router/index.ts]
- [Source: frontend/src/pages/face/candidature/FaceCandidaturesPage.vue] - reference for list page pattern

**Business Rules:**
- [Source: _bmad-output/project-context.md#Critical Business Rules - Chat access only after acceptance]
- [Source: _bmad-output/planning-artifacts/epics.md#Epic 7: Messaging System]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

None - implementation was straightforward.

### Completion Notes List

1. **Task 1-2 (Backend Controllers):** Added `index()` method to both Face and Producer ConversationController. Queries conversations via candidature relationships, orders by latest message timestamp, includes pagination (15 per page).

2. **Task 3 (ConversationListResource):** Created lightweight resource that returns: id, candidature_id, mission_title, other_participant, latest_message (truncated to 50 chars with is_mine flag), unread_count, updated_at. Reuses `getOtherParticipant()` logic from ConversationResource.

3. **Task 4 (Routes):** Added `GET /api/v1/face/conversations` and `GET /api/v1/producer/conversations` routes with auth and throttle middleware.

4. **Task 5 (Backend Tests):** Created comprehensive test file with 22 tests covering: Face/Producer can list own conversations only, unread count accuracy, pagination, empty state, ordering by latest message, authorization (401/403).

5. **Task 6 (API Methods & Types):** Added `getConversations()` and `getProducerConversations()` to messagingApi.ts. Created `ConversationListItem`, `LatestMessagePreview`, `PaginationMeta`, and `ConversationsListResponse` types.

6. **Task 7-8 (Composables):** Created `useConversationsList` and `useProducerConversationsList` composables with: loadConversations, refreshConversations, loadMoreConversations, isLoading, isRefreshing, error, hasConversations, hasMorePages.

7. **Task 9 (ConversationListItem):** Created reusable component with avatar (with fallback initials), participant name, mission title, last message preview with "Vous:" prefix for own messages, relative time formatting, unread badge with 99+ cap, visual distinction for unread conversations.

8. **Task 10-11 (Pages):** Created FaceConversationsPage and ProducerConversationsPage with loading state, error state with retry, empty state with helpful guidance, refresh button, and conversation list.

9. **Task 12 (Routes):** Added `/face/messages` and `/producer/messages` routes to router.

10. **Task 13 (Navigation):** Added Messages card to both Face and Producer dashboards with chat icon.

### Implementation Plan

- Created ConversationListResource first as dependency for controllers
- Backend implemented before frontend to ensure API contracts
- Used existing patterns from ConversationResource and useConversation composable
- French localization for all user-facing strings

### File List

#### Created

- backend/app/Http/Resources/ConversationListResource.php
- backend/app/Http/Middleware/EnsureUserIsProducer.php
- backend/tests/Feature/Messaging/ConversationsListTest.php
- frontend/src/features/messaging/composables/useConversationsList.ts
- frontend/src/features/messaging/composables/useProducerConversationsList.ts
- frontend/src/features/messaging/components/ConversationListItem.vue
- frontend/src/pages/face/messaging/FaceConversationsPage.vue
- frontend/src/pages/producer/messaging/ProducerConversationsPage.vue

#### Modified

- backend/app/Http/Controllers/Api/V1/Face/ConversationController.php
- backend/app/Http/Controllers/Api/V1/Producer/ConversationController.php
- backend/bootstrap/app.php
- backend/routes/api/face.php
- backend/routes/api/producer.php
- frontend/src/features/messaging/types/index.ts
- frontend/src/features/messaging/services/messagingApi.ts
- frontend/src/router/index.ts
- frontend/src/pages/dashboard/FaceDashboardPage.vue
- frontend/src/pages/dashboard/ProducerDashboardPage.vue
- _bmad-output/implementation-artifacts/sprint-status.yaml

## Senior Developer Review (AI)

**Reviewed by:** Claude Opus 4.5
**Date:** 2026-01-28

### Issues Found & Fixed

| Severity | Issue | Resolution |
|----------|-------|------------|
| HIGH | H1: Missing test for Face accessing Producer endpoint | Added `test_face_cannot_access_producer_conversations_endpoint` test |
| HIGH | H2: Dead code in watch callbacks | Removed empty setTimeout and unused watch import |
| MEDIUM | M2: Bidirectional authorization not tested | Added producer middleware + test coverage |
| MEDIUM | M4: Ordering undefined for conversations without messages | Changed to COALESCE ordering with fallback to updated_at |
| LOW | L2: Missing data-testid attributes | Added data-testid to page components |

### Additional Files Created/Modified During Review

- `backend/app/Http/Middleware/EnsureUserIsProducer.php` (created)
- `backend/bootstrap/app.php` (registered producer middleware)
- `backend/routes/api/producer.php` (applied producer middleware to list endpoint)

### OWASP Security Review: ✅ PASSED

All A01, A03, A04, A05, A07 checks passed.

### Test Results After Fixes

- Backend: 774 tests passed (3127 assertions)
- TypeScript: No errors
- New test: `test_face_cannot_access_producer_conversations_endpoint` passes

### Review Outcome: APPROVED ✅

