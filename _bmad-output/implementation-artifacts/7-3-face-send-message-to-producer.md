# Story 7.3: Face Send Message to Producer

Status: done

## Story

As a **Face**,
I want **to send messages to a Producer after my candidature is accepted**,
so that **I can coordinate details about the mission**.

## Acceptance Criteria

1. **Given** my candidature has been accepted **When** I access the conversation **Then** I see the chat interface with the conversation history

2. **Given** I am in an active conversation **When** I type a message and click "Envoyer" **Then** the message is saved and appears in the conversation

3. **Given** I send a message **When** it is saved **Then** it includes my user ID as sender, the content, and timestamp

4. **Given** my candidature is NOT accepted (pending, rejected) **When** I try to access the chat **Then** I receive an error message (chat not unlocked)

5. **Given** I have sent messages **When** I view the conversation **Then** my messages are visually distinguished from the Producer's messages (my messages on the right, theirs on the left)

6. **Given** I am viewing the conversation **When** there are unread messages from the Producer **Then** they are marked as read when I view them

7. **Given** I submit an empty message **When** I click "Envoyer" **Then** the message is not sent and I see a validation error

**(FR42 - Une Face peut échanger des messages avec un Producteur après acceptation de sa candidature)**

## Tasks / Subtasks

### Backend Tasks

- [x] Task 1: Create SendMessageRequest Form Request for validation (AC: #7)
  - [x] Create `app/Http/Requests/Face/SendMessageRequest.php`
  - [x] Validate `content` field: required, string, max:5000 characters
  - [x] Return French error messages

- [x] Task 2: Create MessageResource API Resource (AC: #3)
  - [x] Create `app/Http/Resources/MessageResource.php`
  - [x] Include: id, content, sender_id, sender_type, sender_name, is_own_message, read_at, created_at
  - [x] Add `is_own_message` computed field based on current user

- [x] Task 3: Create ConversationResource API Resource (AC: #1, #5)
  - [x] Create `app/Http/Resources/ConversationResource.php`
  - [x] Include: id, candidature_id, mission_title, other_participant (name, photo), messages, unread_count
  - [x] Eager load messages with sender relationship

- [x] Task 4: Create Face MessageController with store action (AC: #2, #3, #4, #7)
  - [x] Create `app/Http/Controllers/Api/V1/Face/MessageController.php`
  - [x] Add `store(SendMessageRequest $request, Conversation $conversation)` method
  - [x] Use ConversationPolicy::sendMessage for authorization
  - [x] Create Message with sender as authenticated User
  - [x] Return MessageResource

- [x] Task 5: Create Face ConversationController with show action (AC: #1, #5, #6)
  - [x] Create `app/Http/Controllers/Api/V1/Face/ConversationController.php`
  - [x] Add `show(Conversation $conversation)` method
  - [x] Use ConversationPolicy::view for authorization
  - [x] Mark unread messages from other participant as read
  - [x] Return ConversationResource with messages

- [x] Task 6: Add Face messaging routes (AC: #1, #2)
  - [x] Add routes to `routes/api/face.php`
  - [x] GET `/face/conversations/{conversation}` - show conversation
  - [x] POST `/face/conversations/{conversation}/messages` - send message

- [x] Task 7: Extend FaceCandidatureResource to include conversation_id (AC: #1)
  - [x] Add `conversation_id` to FaceCandidatureResource when candidature has conversation
  - [x] This allows frontend to build chat route link

- [x] Task 8: Create backend feature tests (AC: #1-7)
  - [x] Test Face can view conversation after candidature accepted
  - [x] Test Face can send message to conversation
  - [x] Test message includes correct sender data
  - [x] Test Face cannot access conversation if candidature not accepted
  - [x] Test messages ordered chronologically
  - [x] Test unread messages marked as read on view
  - [x] Test empty message returns validation error
  - [x] Test Face cannot send message to other Face's conversation

### Frontend Tasks

- [x] Task 9: Create messaging feature module structure (AC: #1-7)
  - [x] Create `frontend/src/features/messaging/` directory structure
  - [x] Create `types/index.ts` with Message, Conversation, ConversationResponse types
  - [x] Create `services/index.ts` and `messagingApi.ts`
  - [x] Create `composables/index.ts`
  - [x] Create `components/index.ts`
  - [x] Create `index.ts` main export file

- [x] Task 10: Create messaging types (AC: #3, #5)
  - [x] `Message` interface: id, content, sender_id, sender_type, sender_name, is_own_message, read_at, created_at
  - [x] `Conversation` interface: id, candidature_id, mission_title, other_participant, messages, unread_count
  - [x] `OtherParticipant` interface: id, name, photo_url, type
  - [x] `SendMessageData` interface: content
  - [x] `ConversationResponse` and `MessageResponse` API response types

- [x] Task 11: Create messagingApi service (AC: #1, #2)
  - [x] Create `frontend/src/features/messaging/services/messagingApi.ts`
  - [x] `getConversation(conversationId: number)` - GET /face/conversations/{id}
  - [x] `sendMessage(conversationId: number, data: SendMessageData)` - POST /face/conversations/{id}/messages

- [x] Task 12: Create useConversation composable (AC: #1, #5, #6)
  - [x] Create `frontend/src/features/messaging/composables/useConversation.ts`
  - [x] State: conversation, messages, isLoading, error
  - [x] Method: loadConversation(conversationId)
  - [x] Computed: otherParticipant, missionTitle

- [x] Task 13: Create useSendMessage composable (AC: #2, #7)
  - [x] Create `frontend/src/features/messaging/composables/useSendMessage.ts`
  - [x] State: isSending, error
  - [x] Method: sendMessage(conversationId, content)
  - [x] Return: success boolean, new message data
  - [x] Handle validation errors

- [x] Task 14: Create MessageBubble component (AC: #5)
  - [x] Create `frontend/src/features/messaging/components/MessageBubble.vue`
  - [x] Props: message (Message type)
  - [x] Display message content with timestamp
  - [x] Style: own messages (right, primary color) vs other's (left, muted)
  - [x] Show read status indicator for own messages

- [x] Task 15: Create MessageInput component (AC: #2, #7)
  - [x] Create `frontend/src/features/messaging/components/MessageInput.vue`
  - [x] Text input field with "Envoyer" button
  - [x] Emit: submit(content: string)
  - [x] Validation: disable button if empty, show error message
  - [x] Clear input after successful send
  - [x] Loading state during send

- [x] Task 16: Create ConversationHeader component (AC: #1)
  - [x] Create `frontend/src/features/messaging/components/ConversationHeader.vue`
  - [x] Props: participant (OtherParticipant), missionTitle
  - [x] Display other participant's name, photo, mission context
  - [x] Back button to return to candidatures list

- [x] Task 17: Create ConversationView page component (AC: #1, #2, #5, #6)
  - [x] Create `frontend/src/features/messaging/components/ConversationView.vue`
  - [x] Use useConversation and useSendMessage composables
  - [x] Display ConversationHeader at top
  - [x] Display MessageBubble list (scrollable, newest at bottom)
  - [x] Display MessageInput at bottom (sticky)
  - [x] Auto-scroll to bottom on new messages
  - [x] Handle loading and error states

- [x] Task 18: Add conversation route for Face (AC: #1)
  - [x] Add route to `frontend/src/router/index.ts`
  - [x] Route: `/face/conversations/:conversationId`
  - [x] Name: `face-conversation`
  - [x] Component: lazy-loaded ConversationView
  - [x] Meta: requiresAuth, requiresFace

- [x] Task 19: Add chat button to CandidatureCard (AC: #1, #4)
  - [x] Modify `CandidatureCard.vue` to accept conversation_id prop
  - [x] Add computed `canChat` - true if status in [accepted, confirmed, in_progress, completed]
  - [x] Add "Discuter" button with MessageCircle icon
  - [x] RouterLink to face-conversation route with conversationId param
  - [x] Style: secondary button next to confirm button or standalone

- [x] Task 20: Update FaceCandidature type to include conversation_id (AC: #1)
  - [x] Add `conversation_id: number | null` to FaceCandidature interface in types/index.ts

## Dev Notes

### 🎨 MANDATORY: Use Gemini MCP for UI/UX Tasks

**All frontend component creation MUST use the `gemini-mcp` tools:**

- **New components** (MessageBubble, MessageInput, ConversationHeader, ConversationView):
  - Use `snippet_frontend` or `create_frontend` tool
  - Pass existing CSS/theme files in `context` parameter for consistency

- **Modifying existing components** (CandidatureCard chat button):
  - Use `modify_frontend` tool
  - Provide the existing component file as context

- **Workflow:**
  1. Call appropriate gemini-mcp tool with design requirements
  2. Gemini returns the code
  3. YOU write the returned code to disk
  4. Verify consistency with existing design system

**Do NOT manually write Vue component templates/styles without using Gemini MCP.**

---

### CRITICAL BUSINESS RULES (from project-context.md)

**Chat Access Rule**: Chat is ONLY unlocked AFTER candidature is accepted (FR42, FR43)
- Story 7-2 already implemented conversation creation on accept
- Story 7-2 created ConversationPolicy with view() and sendMessage() methods
- This story MUST use those policies for authorization

**Message Flow:**
1. Face's candidature gets accepted → Conversation created (Story 7-2)
2. Face accesses conversation → Sees empty chat
3. Face sends message → Message saved with Face's User as sender
4. Producer sees message, replies → (Story 7-4)
5. Face refreshes → Sees Producer's reply marked as read

### Implementation Patterns from Previous Stories

**From Story 7-1 (Messaging Schema):**
- Message model has `sender_id`, `sender_type` (polymorphic to User)
- Message has `content` (text), `read_at` (nullable datetime)
- Conversation has `candidature_id` linking to Candidature
- Use `Message::factory()` and `Conversation::factory()` for tests

**From Story 7-2 (Chat Unlock):**
- ConversationPolicy already exists at `app/Policies/ConversationPolicy.php`
- `view()` - checks if user is Face or Producer of the candidature
- `sendMessage()` - view + candidature.status.allowsChatAccess()
- Use `$user->can('view', $conversation)` or `$user->can('sendMessage', $conversation)`

**Controller Pattern (from existing controllers):**
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\SendMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    /**
     * Send a message in a conversation.
     */
    public function store(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        // Authorization via policy
        $this->authorize('sendMessage', $conversation);

        $user = $request->user();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => get_class($user),
            'content' => $request->validated('content'),
        ]);

        return response()->json([
            'data' => new MessageResource($message),
            'message' => 'Message envoyé',
        ], 201);
    }
}
```

**ConversationController Pattern:**
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /**
     * Show a conversation with its messages.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        // Authorization via policy
        $this->authorize('view', $conversation);

        $user = $request->user();

        // Mark unread messages from other participant as read
        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->update(['read_at' => now()]);

        // Load relationships
        $conversation->load(['messages.sender', 'candidature.mission', 'candidature.face.user']);

        return response()->json([
            'data' => new ConversationResource($conversation),
        ]);
    }
}
```

**MessageResource Pattern:**
```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();

        return [
            'id' => $this->id,
            'content' => $this->content,
            'sender_id' => $this->sender_id,
            'sender_type' => $this->sender_type,
            'sender_name' => $this->sender?->userable?->getDisplayNameAttribute() ?? 'Utilisateur',
            'is_own_message' => $currentUser && $this->sender_id === $currentUser->id,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

### Files to Create

**Backend:**
```
backend/app/Http/Requests/Face/SendMessageRequest.php
backend/app/Http/Resources/MessageResource.php
backend/app/Http/Resources/ConversationResource.php
backend/app/Http/Controllers/Api/V1/Face/MessageController.php
backend/app/Http/Controllers/Api/V1/Face/ConversationController.php
backend/tests/Feature/Messaging/FaceSendMessageTest.php
```

**Frontend:**
```
frontend/src/features/messaging/index.ts
frontend/src/features/messaging/types/index.ts
frontend/src/features/messaging/services/index.ts
frontend/src/features/messaging/services/messagingApi.ts
frontend/src/features/messaging/composables/index.ts
frontend/src/features/messaging/composables/useConversation.ts
frontend/src/features/messaging/composables/useSendMessage.ts
frontend/src/features/messaging/components/index.ts
frontend/src/features/messaging/components/MessageBubble.vue
frontend/src/features/messaging/components/MessageInput.vue
frontend/src/features/messaging/components/ConversationHeader.vue
frontend/src/features/messaging/components/ConversationView.vue
```

### Files to Modify

**Backend:**
```
backend/routes/api/face.php                                    # Add messaging routes
backend/app/Http/Resources/FaceCandidatureResource.php         # Add conversation_id field
```

**Frontend:**
```
frontend/src/router/index.ts                                   # Add conversation route
frontend/src/features/candidature/types/index.ts               # Add conversation_id to FaceCandidature
frontend/src/features/candidature/components/CandidatureCard.vue  # Add chat button
```

### Frontend Patterns

**Messaging Types (frontend/src/features/messaging/types/index.ts):**
```typescript
export interface Message {
  id: number
  content: string
  sender_id: number
  sender_type: string
  sender_name: string
  is_own_message: boolean
  read_at: string | null
  created_at: string
}

export interface OtherParticipant {
  id: number
  name: string
  photo_url: string | null
  type: 'face' | 'producer'
}

export interface Conversation {
  id: number
  candidature_id: number
  mission_title: string
  other_participant: OtherParticipant
  messages: Message[]
  unread_count: number
}

export interface ConversationResponse {
  data: Conversation
}

export interface MessageResponse {
  data: Message
  message?: string
}

export interface SendMessageData {
  content: string
}
```

**Messaging API Service Pattern:**
```typescript
import apiClient from '@/services/apiClient'
import type { ConversationResponse, MessageResponse, SendMessageData } from '../types'

export const messagingApi = {
  async getConversation(conversationId: number): Promise<ConversationResponse> {
    const response = await apiClient.get<ConversationResponse>(
      `/face/conversations/${conversationId}`,
    )
    return response.data
  },

  async sendMessage(conversationId: number, data: SendMessageData): Promise<MessageResponse> {
    const response = await apiClient.post<MessageResponse>(
      `/face/conversations/${conversationId}/messages`,
      data,
    )
    return response.data
  },
}
```

**useConversation Composable Pattern:**
```typescript
import { ref, computed } from 'vue'
import { messagingApi } from '../services/messagingApi'
import type { Conversation, Message } from '../types'

export function useConversation() {
  const conversation = ref<Conversation | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const messages = computed(() => conversation.value?.messages ?? [])
  const otherParticipant = computed(() => conversation.value?.other_participant)
  const missionTitle = computed(() => conversation.value?.mission_title ?? '')

  async function loadConversation(conversationId: number): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await messagingApi.getConversation(conversationId)
      conversation.value = response.data
    } catch (err: unknown) {
      error.value = 'Impossible de charger la conversation'
      console.error('Failed to load conversation:', err)
    } finally {
      isLoading.value = false
    }
  }

  function addMessage(message: Message): void {
    if (conversation.value) {
      conversation.value.messages.push(message)
    }
  }

  return {
    conversation,
    messages,
    otherParticipant,
    missionTitle,
    isLoading,
    error,
    loadConversation,
    addMessage,
  }
}
```

**CandidatureCard Chat Button Integration:**
```vue
<script setup lang="ts">
// Add to existing imports
import { MessageCircle } from 'lucide-vue-next'

// Add computed for chat access
const canChat = computed(() => {
  const chatStatuses = ['accepted', 'confirmed', 'in_progress', 'completed']
  return chatStatuses.includes(props.candidature.status) && props.candidature.conversation_id
})
</script>

<template>
  <!-- Add after confirm button section -->
  <div v-if="canChat" class="mt-4 pt-4 border-t border-border">
    <RouterLink
      :to="{ name: 'face-conversation', params: { conversationId: candidature.conversation_id } }"
      class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
      @click.stop
    >
      <MessageCircle class="h-4 w-4" />
      Discuter avec le producteur
    </RouterLink>
  </div>
</template>
```

**Router Configuration:**
```typescript
// Add to face routes in router/index.ts
{
  path: 'conversations/:conversationId',
  name: 'face-conversation',
  component: () => import('@/features/messaging/components/ConversationView.vue'),
  meta: { requiresAuth: true, requiresFace: true },
}
```

### Testing Standards

**Backend:**
- Use `RefreshDatabase` trait
- Use PHPUnit class-based syntax (project standard, NOT Pest)
- Test all 7 acceptance criteria
- Test authorization via ConversationPolicy
- Test with accepted, pending, and rejected candidatures
- All existing tests must pass (currently 672 backend tests)

**Frontend:**
- Test composables with vitest
- Test components with Vue Test Utils
- Mock messagingApi for unit tests
- Test message display order (chronological)
- Test own message vs other's message styling

### Dependencies

- **Depends on**: Story 7-1 (Message model) ✓ COMPLETED
- **Depends on**: Story 7-2 (ConversationPolicy, conversation creation) ✓ COMPLETED
- **Blocks**: Story 7-5 (view conversation history - will extend ConversationController)

### Edge Cases to Handle

**Backend:**
1. **Conversation not found**: Return 404 (handled by route model binding)
2. **User not participant**: Return 403 via ConversationPolicy
3. **Candidature not accepted**: Return 403 via ConversationPolicy::sendMessage
4. **Empty message**: Return 422 with validation error
5. **Very long message**: Enforce 5000 char limit in validation
6. **Message with only whitespace**: Trim and validate as empty

**Frontend:**
7. **Candidature without conversation_id**: Don't show chat button (conversation not created yet)
8. **API error on send**: Show error toast, don't clear input
9. **API error on load**: Show error state with retry button
10. **Empty conversation**: Show "Commencez la discussion" placeholder
11. **Long message content**: Word-wrap in bubble, consider max-width
12. **Network failure**: Handle gracefully with retry option

### UI/UX Design Notes

**ConversationView Layout:**
```
┌─────────────────────────────────────┐
│  ← Retour   [Photo] Producer Name   │  ← ConversationHeader
│            Mission: "Titre..."       │
├─────────────────────────────────────┤
│                                     │
│         Bonjour, je suis...         │  ← Other's message (left, muted bg)
│                          14:32      │
│                                     │
│    Merci pour votre candidature!    │  ← Own message (right, primary bg)
│    14:35                      ✓✓    │
│                                     │
│ (scrollable message area)           │
│                                     │
├─────────────────────────────────────┤
│  [Type your message...     ] [Send] │  ← MessageInput (sticky bottom)
└─────────────────────────────────────┘
```

**Message Bubble Styling:**
- Own messages: Right-aligned, `bg-primary text-primary-foreground`, rounded-l-lg
- Other's messages: Left-aligned, `bg-muted text-foreground`, rounded-r-lg
- Timestamp: Small, muted text below bubble
- Read status: Double check (✓✓) for read, single check (✓) for sent

**Chat Button on CandidatureCard:**
- Appears below confirm button (or alone if already confirmed)
- Uses `MessageCircle` icon from lucide-vue-next
- Primary button style: `bg-primary text-primary-foreground`
- Label: "Discuter avec le producteur"

**Mobile Considerations:**
- Full-width input on mobile
- Message bubbles max-width: 80%
- Touch-friendly send button (min 44x44px)
- Keyboard-aware: input should stay visible when keyboard opens

### API Endpoints

**GET /api/v1/face/conversations/{conversation}**
- Auth: Bearer token (Face)
- Authorization: ConversationPolicy::view
- Response: ConversationResource with messages
- Side effect: Marks unread messages as read

**POST /api/v1/face/conversations/{conversation}/messages**
- Auth: Bearer token (Face)
- Authorization: ConversationPolicy::sendMessage
- Body: `{ "content": "string" }`
- Response: MessageResource (201 Created)

### References

**Backend:**
- [Source: _bmad-output/planning-artifacts/epics.md#Story 7.3 - Face Send Message to Producer (FR42)]
- [Source: _bmad-output/implementation-artifacts/7-1-create-messaging-database-schema.md - Message model]
- [Source: _bmad-output/implementation-artifacts/7-2-conditional-chat-unlock.md - ConversationPolicy]
- [Source: backend/app/Policies/ConversationPolicy.php - view() and sendMessage() methods]
- [Source: backend/app/Models/Message.php - Message model with sender relationship]
- [Source: backend/app/Models/Conversation.php - Conversation model with messages relationship]

**Frontend Patterns:**
- [Source: frontend/src/features/candidature/services/candidatureApi.ts - API service pattern]
- [Source: frontend/src/features/candidature/composables/useConfirmCandidature.ts - Composable pattern]
- [Source: frontend/src/features/candidature/components/CandidatureCard.vue - Card component pattern]
- [Source: frontend/src/features/candidature/types/index.ts - Types organization pattern]
- [Source: frontend/src/router/index.ts - Route configuration]

**Business Rules:**
- [Source: _bmad-output/project-context.md#Critical Business Rules - Chat access only after acceptance]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

- Fixed Gate::authorize usage (base Controller doesn't have AuthorizesRequests trait)
- Fixed prepareForValidation trim() null handling in SendMessageRequest
- Added display_name accessor to Face model for consistency with Producer
- Fixed TypeScript type error in useSendMessage composable

### Completion Notes List

**Backend (Tasks 1-8):**
- Created SendMessageRequest Form Request with French validation messages
- Created MessageResource with is_own_message computed field and sender_name resolution
- Created ConversationResource with other_participant and messages
- Created Face MessageController with store action using Gate::authorize
- Created Face ConversationController with show action, marks unread messages as read
- Added messaging routes to routes/api/face.php
- Extended FaceCandidatureResource to include conversation_id
- Created 31 comprehensive feature tests covering all 7 ACs
- All 703 backend tests pass (2895 assertions)

**Frontend (Tasks 9-20):**
- Created messaging feature module structure (types, services, composables, components)
- Created Message, Conversation, OtherParticipant TypeScript interfaces
- Created messagingApi service with getConversation and sendMessage methods
- Created useConversation composable for loading conversation state
- Created useSendMessage composable for message sending with error handling
- Created MessageBubble component with own/other styling and read status
- Created MessageInput component with textarea auto-resize and validation
- Created ConversationHeader component with back button and participant info
- Created ConversationView page component with loading/error/empty states
- Added face-conversation route to router
- Added chat button to CandidatureCard for accepted+ candidatures
- Updated FaceCandidature type to include conversation_id
- Frontend type-check and build pass successfully

### Change Log

| Date       | Author       | Change                                       |
|------------|--------------|----------------------------------------------|
| 2026-01-28 | SM Agent     | Story created - ready-for-dev (backend only) |
| 2026-01-28 | SM Agent     | Added frontend tasks (Tasks 9-20)            |
| 2026-01-28 | Dev Agent    | All 20 tasks implemented - status: review    |
| 2026-01-28 | Code Review  | Fixed 3 issues: rate limiting, route param typing, code cleanup |

### File List

**Backend Created:**
- `backend/app/Http/Requests/Face/SendMessageRequest.php`
- `backend/app/Http/Resources/MessageResource.php`
- `backend/app/Http/Resources/ConversationResource.php`
- `backend/app/Http/Controllers/Api/V1/Face/MessageController.php`
- `backend/app/Http/Controllers/Api/V1/Face/ConversationController.php`
- `backend/tests/Feature/Messaging/FaceSendMessageTest.php`

**Backend Modified:**
- `backend/routes/api/face.php` - Added messaging routes
- `backend/app/Http/Resources/FaceCandidatureResource.php` - Added conversation_id
- `backend/app/Models/Face.php` - Added display_name accessor
- `backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php` - Added conversation eager loading

**Frontend Created:**
- `frontend/src/features/messaging/index.ts`
- `frontend/src/features/messaging/types/index.ts`
- `frontend/src/features/messaging/services/index.ts`
- `frontend/src/features/messaging/services/messagingApi.ts`
- `frontend/src/features/messaging/composables/index.ts`
- `frontend/src/features/messaging/composables/useConversation.ts`
- `frontend/src/features/messaging/composables/useSendMessage.ts`
- `frontend/src/features/messaging/components/index.ts`
- `frontend/src/features/messaging/components/MessageBubble.vue`
- `frontend/src/features/messaging/components/MessageInput.vue`
- `frontend/src/features/messaging/components/ConversationHeader.vue`
- `frontend/src/features/messaging/components/ConversationView.vue`

**Frontend Modified:**
- `frontend/src/router/index.ts` - Added face-conversation route
- `frontend/src/features/candidature/types/index.ts` - Added conversation_id to FaceCandidature
- `frontend/src/features/candidature/components/CandidatureCard.vue` - Added chat button
