# Story 7.6: Manual Message Refresh

Status: done

## Story

As a **user** (Face or Producer),
I want **to manually refresh my messages**,
so that **I can see new messages without page reload**.

## Acceptance Criteria

1. **Given** I am in a conversation **When** I click "Actualiser" **Then** new messages are fetched and displayed

2. **Given** I click the refresh button **When** the API call is in progress **Then** I see a loading indicator on the button

3. **Given** new messages exist from the other participant **When** I refresh **Then** the new messages appear in chronological order

4. **Given** no new messages exist **When** I refresh **Then** the conversation state remains unchanged (no visual glitch)

5. **Given** a network error occurs during refresh **When** the API call fails **Then** I see an error toast or feedback message

6. **Given** I just sent a message **When** I refresh immediately after **Then** my sent message is not duplicated

**(FR45 - Un utilisateur peut actualiser manuellement ses messages)**

## Tasks / Subtasks

### Backend Tasks

- [x] Task 1: Verify backend already supports re-fetching (AC: #1, #3)
  - [x] Confirm GET /api/v1/face/conversations/{id} returns current messages
  - [x] Confirm GET /api/v1/producer/conversations/{id} returns current messages
  - [x] No new backend code needed - existing endpoints suffice

### Frontend Tasks

- [x] Task 2: Add refresh button to ConversationView (Face) (AC: #1, #2)
  - [x] Add refresh button to ConversationHeader or message area
  - [x] Style: Small icon button (RefreshCw from lucide-vue-next)
  - [x] Position: In header, after participant info
  - [x] Use existing `loadConversation()` from useConversation composable

- [x] Task 3: Add refresh functionality to useConversation composable (AC: #3, #4, #6)
  - [x] Add `refreshConversation()` method that calls `loadConversation()`
  - [x] Add `isRefreshing` state separate from `isLoading`
  - [x] Ensure message array updates without duplicates

- [x] Task 4: Add refresh button to ProducerConversationView (AC: #1, #2)
  - [x] Mirror Task 2 implementation for Producer
  - [x] Use existing `loadConversation()` from useProducerConversation composable

- [x] Task 5: Add refresh functionality to useProducerConversation composable (AC: #3, #4, #6)
  - [x] Add `refreshConversation()` method
  - [x] Add `isRefreshing` state
  - [x] Same logic as Face composable

- [x] Task 6: Add error handling for refresh (AC: #5)
  - [x] Show toast notification on network error
  - [x] Reset loading state on error
  - [x] Don't clear existing messages on refresh error

- [N/A] Task 7: Add tests for refresh functionality (AC: #1-6)
  - [N/A] Frontend tests optional per project patterns (focus on backend tests)
  - [N/A] UI is straightforward and verified via TypeScript type-check
  - [N/A] Backend endpoints already fully tested (131 tests pass)

## Dev Notes

### MANDATORY: Use Gemini MCP for UI/UX Tasks

**All frontend component modifications MUST use the `gemini-mcp` tools:**

- **Modifying existing components** (adding refresh button):
  - Use `modify_frontend` tool
  - Provide the existing ConversationHeader.vue or ConversationView.vue as context

- **Workflow:**
  1. Call appropriate gemini-mcp tool with design requirements
  2. Gemini returns the code
  3. YOU write the returned code to disk
  4. Verify consistency with existing design system

---

### Current Implementation Analysis

**What Already Exists (from Stories 7-3, 7-4, 7-5):**

1. **ConversationView.vue (Face)** - Has `loadConversation()` call in `onMounted`
2. **ProducerConversationView.vue** - Same pattern for Producer
3. **useConversation.ts** - `loadConversation(conversationId)` fetches and replaces `conversation.value`
4. **useProducerConversation.ts** - Same pattern for Producer
5. **messagingApi.ts** - `getConversation()` and `getProducerConversation()` endpoints ready

**What Needs to Be Added:**

1. **Refresh button UI** in ConversationHeader.vue (or ConversationView.vue)
2. **Separate `isRefreshing` state** to avoid showing full loading skeleton
3. **Error toast** for refresh failures

### Implementation Approach

**Option A (Recommended): Button in Header**
- Add refresh icon button next to participant name in ConversationHeader
- Clean, doesn't clutter message area
- Follows WhatsApp/Telegram patterns

**Option B: Floating button in message area**
- Pull-to-refresh on mobile, button on desktop
- More complex implementation
- Defer to post-MVP

**For MVP, use Option A:**

```vue
<!-- In ConversationHeader.vue -->
<button
  type="button"
  :disabled="isRefreshing"
  class="h-9 w-9 rounded-full text-muted-foreground hover:bg-primary/5 hover:text-foreground"
  aria-label="Actualiser"
  @click="emit('refresh')"
>
  <RefreshCw :class="['size-5', isRefreshing && 'animate-spin']" />
</button>
```

### Composable Enhancement

```typescript
// In useConversation.ts - add refresh functionality
const isRefreshing = ref(false)

async function refreshConversation(conversationId: number): Promise<boolean> {
  if (isRefreshing.value) return false

  isRefreshing.value = true
  error.value = null

  try {
    const response = await messagingApi.getConversation(conversationId)
    conversation.value = response.data
    return true
  } catch (err: unknown) {
    // Don't clear existing messages on refresh failure
    error.value = 'Impossible de rafraîchir les messages'
    return false
  } finally {
    isRefreshing.value = false
  }
}

return {
  // ... existing
  isRefreshing,
  refreshConversation,
}
```

### Key Files to Modify

**Frontend:**
```
frontend/src/features/messaging/components/ConversationHeader.vue
frontend/src/features/messaging/components/ConversationView.vue
frontend/src/features/messaging/components/ProducerConversationView.vue
frontend/src/features/messaging/composables/useConversation.ts
frontend/src/features/messaging/composables/useProducerConversation.ts
```

### Existing Code Patterns (from Story 7-5)

**ConversationView handles loading:**
```typescript
const retryLoading = () => {
  loadConversation(getConversationId())
}
```

**useConversation loadConversation:**
```typescript
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
```

### Testing Standards

**Frontend (Vitest - if adding tests):**
```typescript
// Optional: Test refresh button exists and works
describe('ConversationView', () => {
  it('shows refresh button in header', () => {
    // Mount component, check button exists
  })

  it('calls refresh when button clicked', async () => {
    // Mock api, click button, verify call
  })

  it('shows spinner during refresh', async () => {
    // Check loading class applied
  })
})
```

**Note:** Frontend component tests are optional per project patterns. Focus on backend tests for critical functionality. The UI is straightforward enough to verify manually.

### Dependencies

- **Depends on:** Story 7-5 (View conversation history) ✓ COMPLETED
- **Blocks:** Story 7-7 (Conversations list view)

### Edge Cases to Handle

1. **Double-click prevention** - Disable button while refreshing
2. **No duplicates** - Replace entire conversation.messages array, don't append
3. **Scroll position** - Keep scroll at bottom after refresh if user was at bottom
4. **Empty refresh** - No visual change if no new messages
5. **Error recovery** - Keep existing messages if refresh fails

### UI/UX Requirements

**Refresh Button:**
- Icon: `RefreshCw` from lucide-vue-next
- Size: 36x36px touch target
- Color: `text-muted-foreground`, `hover:text-foreground`
- Position: In ConversationHeader, to the right of participant info
- Animation: `animate-spin` class while refreshing

**Loading State:**
- Show spinning icon on button (not full skeleton)
- Keep existing messages visible during refresh
- User can still type while refresh is happening

**Error State:**
- Show toast notification: "Impossible de rafraîchir les messages"
- Button returns to normal state
- Existing messages preserved

### References

**Backend:**
- [Source: backend/app/Http/Controllers/Api/V1/Face/ConversationController.php]
- [Source: backend/app/Http/Controllers/Api/V1/Producer/ConversationController.php]

**Frontend:**
- [Source: frontend/src/features/messaging/components/ConversationView.vue]
- [Source: frontend/src/features/messaging/components/ProducerConversationView.vue]
- [Source: frontend/src/features/messaging/components/ConversationHeader.vue]
- [Source: frontend/src/features/messaging/composables/useConversation.ts]
- [Source: frontend/src/features/messaging/composables/useProducerConversation.ts]

**Business Rules:**
- [Source: _bmad-output/project-context.md#Critical Business Rules - Chat access only after acceptance]
- [Source: _bmad-output/planning-artifacts/epics.md#FR45]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

None - implementation was straightforward.

### Completion Notes List

1. **Task 1 (Backend Verification):** Confirmed existing endpoints `GET /api/v1/face/conversations/{id}` and `GET /api/v1/producer/conversations/{id}` are idempotent and support re-fetching. No backend changes needed.

2. **Task 2 & 4 (UI - Refresh Button):** Added refresh button to ConversationHeader.vue with:
   - RefreshCw icon from lucide-vue-next
   - Spin animation while refreshing (`animate-spin` class)
   - Disabled state to prevent double-clicks
   - Positioned in header after participant info

3. **Task 3 & 5 (Composable Enhancement):** Added to both `useConversation.ts` and `useProducerConversation.ts`:
   - `isRefreshing` state separate from `isLoading` (avoids showing full skeleton)
   - `refreshError` state for refresh-specific errors
   - `refreshConversation()` method with double-click prevention
   - `clearRefreshError()` helper function

4. **Task 6 (Error Handling):** Added refresh error toast notification:
   - Shows "Impossible de rafraîchir les messages" on error
   - Preserves existing messages on refresh failure
   - Positioned below header with z-30 for visibility

5. **Task 7 (Tests):** Marked N/A - frontend component tests are optional per project patterns. The UI changes are type-checked and backend endpoints are already fully tested (131 messaging tests pass).

### Implementation Plan

- Used Option A from Dev Notes: Button in ConversationHeader
- Added `isRefreshing` separate from `isLoading` to avoid full loading skeleton
- Error toast positioned below header for non-intrusive feedback
- Scroll to bottom after successful refresh

### File List

#### Modified

- frontend/src/features/messaging/components/ConversationHeader.vue
- frontend/src/features/messaging/components/ConversationView.vue
- frontend/src/features/messaging/components/ProducerConversationView.vue
- frontend/src/features/messaging/composables/useConversation.ts
- frontend/src/features/messaging/composables/useProducerConversation.ts
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

**MEDIUM-2: Error toast has no auto-dismiss** ✅ FIXED
- Refresh error toast stayed visible indefinitely
- Resolution: Added watch() with setTimeout to auto-clear after 5 seconds in both ConversationView.vue and ProducerConversationView.vue

**LOW-1: No data-testid on refresh button** (not fixed)
- Refresh button lacks data-testid attribute
- Impact: Low - frontend tests are optional per project patterns

**LOW-2: Story file not in File List** (not fixed)
- Story file itself not listed
- Impact: Low - story files typically excluded from File List

### Verification Results
- All 6 Acceptance Criteria: ✅ VERIFIED
- All [x] Tasks: ✅ VERIFIED
- Test Suite: 751 tests pass
- TypeScript: ✅ No errors
- OWASP Security: ✅ A01, A03, A04 verified

## Change Log

| Date       | Author       | Change                                  |
|------------|--------------|----------------------------------------|
| 2026-01-28 | SM Agent     | Story created - ready-for-dev          |
| 2026-01-28 | Dev Agent    | Implementation complete, all tasks done |
| 2026-01-28 | Review Agent | Code review: 2 MEDIUM fixed, status → done |
