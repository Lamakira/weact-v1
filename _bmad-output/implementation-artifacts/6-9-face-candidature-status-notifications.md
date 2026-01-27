# Story 6.9: Face Candidature Status Notifications

Status: done

## Story

As a **Face**,
I want **to be notified when my candidature status changes**,
so that **I can respond quickly to opportunities**.

## Acceptance Criteria

1. **Given** a Producer accepts my candidature **When** the status changes to "accepted" **Then** I see an in-app notification with message "Votre candidature a été acceptée"

2. **Given** a Producer rejects my candidature **When** the status changes to "rejected" **Then** I see an in-app notification with message "Votre candidature a été refusée"

3. **Given** I have unread notifications **When** I view my notifications **Then** I see them listed with mission title and timestamp

4. **Given** I click on a notification **When** I view it **Then** it is marked as read

5. **Given** I have unread notifications **When** I view the navigation bar **Then** I see a notification badge with the count

6. **Given** I am a Producer **When** I access the Face notifications endpoint **Then** I get a 403 error

7. **Given** I am not authenticated **When** I access the notifications endpoint **Then** I get a 401 error

**(FR41)**

## Tasks / Subtasks

- [x] Task 1: Create notifications database migration (AC: #1-5)
  - [x] Create `notifications` table with: id, user_id, type, data (JSON), read_at, created_at
  - [x] Add foreign key to users table
  - [x] Run migration

- [x] Task 2: Create Notification model (AC: #1-5)
  - [x] Create `App\Models\Notification` model
  - [x] Define fillable, casts (data as array)
  - [x] Add `user()` relationship
  - [x] Add `markAsRead()` method
  - [x] Add `scopeUnread()` scope

- [x] Task 3: Create notification when candidature status changes (AC: #1, #2)
  - [x] Modify Producer `CandidatureController::accept()` to create notification
  - [x] Modify Producer `CandidatureController::reject()` to create notification
  - [x] Include mission title in notification data

- [x] Task 4: Create Face NotificationController (AC: #3-7)
  - [x] Create `index()` method to list notifications (paginated)
  - [x] Create `markAsRead()` method for single notification
  - [x] Create `markAllAsRead()` method
  - [x] Create `unreadCount()` method for badge
  - [x] Apply Face middleware for authorization

- [x] Task 5: Add notification routes (AC: #3-7)
  - [x] Add `GET /v1/face/notifications` - list notifications
  - [x] Add `POST /v1/face/notifications/{notification}/read` - mark as read
  - [x] Add `POST /v1/face/notifications/read-all` - mark all as read
  - [x] Add `GET /v1/face/notifications/unread-count` - get badge count

- [x] Task 6: Create backend feature tests (AC: #1-7)
  - [x] Create `tests/Feature/Notification/FaceNotificationTest.php`
  - [x] Test notification created on accept
  - [x] Test notification created on reject
  - [x] Test list notifications
  - [x] Test mark as read
  - [x] Test unread count
  - [x] Test Producer cannot access (403)
  - [x] Test unauthenticated (401)

- [x] Task 7: Create frontend notification types and API (AC: #3-5)
  - [x] Create `frontend/src/features/notification/types/index.ts`
  - [x] Create `frontend/src/features/notification/services/notificationApi.ts`
  - [x] Add getNotifications, markAsRead, markAllAsRead, getUnreadCount methods

- [x] Task 8: Create useNotifications composable (AC: #3-5)
  - [x] Create `frontend/src/features/notification/composables/useNotifications.ts`
  - [x] Handle fetching, pagination, mark as read
  - [x] Export from composables/index.ts

- [x] Task 9: Create notification bell component for navbar (AC: #5)
  - [x] Create `frontend/src/features/notification/components/NotificationBell.vue`
  - [x] Show bell icon with badge count
  - [x] Fetch unread count on mount
  - [x] Poll for updates every 30 seconds (MVP approach)

- [x] Task 10: Create notifications dropdown/panel (AC: #3, #4)
  - [x] Create `frontend/src/features/notification/components/NotificationsDropdown.vue`
  - [x] List recent notifications
  - [x] Show unread indicator
  - [x] Mark as read on click
  - [x] Link to relevant page (candidatures list)

- [x] Task 11: Integrate notification bell in Face layout (AC: #5)
  - [x] Add NotificationBell to Face navigation/header
  - [x] Only show for authenticated Face users

- [x] Task 12: TypeScript and test verification
  - [x] TypeScript type checking passes
  - [x] All backend tests pass (no regressions)

## Dev Notes

### CRITICAL: MVP Notification Approach

**MVP (This Story):**
- Database-based notifications (Laravel's built-in pattern)
- Manual polling every 30 seconds for badge updates
- In-app notification bell in navbar
- Simple dropdown to show recent notifications

**V2 (Future):**
- WebSockets with Laravel Echo + Pusher for real-time push
- Browser push notifications
- Email notifications for important events

### Architecture Patterns

**Backend:**
- Use Laravel's `notifications` table pattern (standard approach)
- Create notifications directly in controller (MVP) - no events yet
- Notification data stored as JSON for flexibility

**Frontend:**
- New `notification` feature folder under `src/features/`
- Polling approach for MVP (30s interval)
- Dropdown component in navbar

### CRITICAL: Use Gemini MCP for Frontend UI

**Per CLAUDE.md rules, Gemini MCP must be used for all frontend UI/design work:**

- **NotificationBell.vue** → Use `snippet_frontend` or `create_frontend`
- **NotificationsDropdown.vue** → Use `snippet_frontend` or `create_frontend`
- **Navbar integration** → Use `modify_frontend` if redesigning existing element

**Decision tree:**
- NEW visual component? → `snippet_frontend` or `create_frontend`
- REDESIGN existing element? → `modify_frontend`
- Just TypeScript types/logic/API services? → Do it yourself

**Important:** Always pass existing CSS/theme files in `context` parameter when calling Gemini MCP.

### Database Schema

```sql
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(255) NOT NULL, -- 'candidature_accepted', 'candidature_rejected'
    data JSON NOT NULL, -- { mission_title, candidature_id, message }
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (user_id, read_at)
);
```

### API Endpoints

```
GET /api/v1/face/notifications
Authorization: Bearer {token}

Response (200 OK):
{
  "data": [
    {
      "id": 1,
      "type": "candidature_accepted",
      "data": {
        "mission_title": "Tournage pub cosmétiques",
        "candidature_id": 15,
        "message": "Votre candidature a été acceptée"
      },
      "read_at": null,
      "created_at": "2026-01-27T14:30:00Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}

GET /api/v1/face/notifications/unread-count
Response: { "count": 3 }

POST /api/v1/face/notifications/{id}/read
Response: { "data": { ... }, "message": "Notification marquée comme lue" }

POST /api/v1/face/notifications/read-all
Response: { "message": "Toutes les notifications marquées comme lues" }
```

### Notification Types

```php
// Notification types enum or constants
const CANDIDATURE_ACCEPTED = 'candidature_accepted';
const CANDIDATURE_REJECTED = 'candidature_rejected';
```

### Frontend Types

```typescript
// types/index.ts
export interface Notification {
  id: number
  type: 'candidature_accepted' | 'candidature_rejected'
  data: {
    mission_title: string
    candidature_id: number
    message: string
  }
  read_at: string | null
  created_at: string
}

export interface NotificationListResponse {
  data: Notification[]
  links: { ... }
  meta: { ... }
}

export interface UnreadCountResponse {
  count: number
}
```

### Notification Creation Pattern

```php
// In Producer CandidatureController::accept()
use App\Models\Notification;

// After updating candidature status...
$candidature->loadMissing('mission', 'face.user');

Notification::create([
    'user_id' => $candidature->face->user->id,
    'type' => 'candidature_accepted',
    'data' => [
        'mission_title' => $candidature->mission->titre,
        'candidature_id' => $candidature->id,
        'message' => 'Votre candidature a été acceptée',
    ],
]);
```

### NotificationBell Component Pattern

```vue
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { Bell } from 'lucide-vue-next'
import { notificationApi } from '../services/notificationApi'

const unreadCount = ref(0)
const showDropdown = ref(false)
let pollInterval: ReturnType<typeof setInterval> | null = null

async function fetchUnreadCount() {
  try {
    const response = await notificationApi.getUnreadCount()
    unreadCount.value = response.count
  } catch {
    // Silently fail - badge will just not update
  }
}

onMounted(() => {
  fetchUnreadCount()
  // Poll every 30 seconds for MVP
  pollInterval = setInterval(fetchUnreadCount, 30000)
})

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
})
</script>

<template>
  <div class="relative">
    <button @click="showDropdown = !showDropdown">
      <Bell class="h-5 w-5" />
      <span
        v-if="unreadCount > 0"
        class="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-red-500 text-xs text-white"
      >
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </button>

    <NotificationsDropdown v-if="showDropdown" @close="showDropdown = false" />
  </div>
</template>
```

### Existing Patterns to Follow

**From Story 6-6/6-7 (Accept/Reject):**
- Producer CandidatureController already has accept/reject methods
- Add notification creation after status update

**From Story 6-3 (Face Candidatures):**
- Pagination pattern for listing notifications
- Resource transformation pattern

**From Story 6-8 (Confirm):**
- Toast notification pattern for immediate feedback (still use for actions)
- This story adds persistent notifications for async events

### Files to Create

**Backend:**
- `database/migrations/xxxx_create_notifications_table.php`
- `app/Models/Notification.php`
- `app/Http/Controllers/Api/V1/Face/NotificationController.php`
- `app/Http/Resources/NotificationResource.php`
- `tests/Feature/Notification/FaceNotificationTest.php`

**Backend to Modify:**
- `routes/api/face.php` - Add notification routes
- `app/Http/Controllers/Api/V1/Producer/CandidatureController.php` - Create notifications

**Frontend to Create:**
- `src/features/notification/` folder structure
- `src/features/notification/types/index.ts`
- `src/features/notification/services/notificationApi.ts`
- `src/features/notification/composables/useNotifications.ts`
- `src/features/notification/composables/index.ts`
- `src/features/notification/components/NotificationBell.vue`
- `src/features/notification/components/NotificationsDropdown.vue`
- `src/features/notification/components/index.ts`
- `src/features/notification/index.ts`

**Frontend to Modify:**
- Face layout/navbar to include NotificationBell

### Test Scenarios

| Scenario | Input | Expected |
|----------|-------|----------|
| Notification on accept | Producer accepts candidature | Notification created for Face |
| Notification on reject | Producer rejects candidature | Notification created for Face |
| List notifications | GET as Face | 200, paginated list |
| Mark as read | POST read for unread | 200, read_at set |
| Unread count | GET unread-count | 200, count integer |
| Producer access | GET as Producer | 403 |
| Unauthenticated | GET without token | 401 |

### Important Considerations

1. **Performance:** Badge polling every 30s is acceptable for MVP. Don't over-engineer.

2. **Data Cleanup:** Consider adding a job to clean old notifications (> 30 days) in future.

3. **No Email Yet:** This story is in-app only. Email notifications are V2.

4. **Integration Point:** This ties into Producer accept/reject actions - be careful with those controllers.

### Dependencies

- **Depends on**: Story 6-6 (Accept), Story 6-7 (Reject) - controllers to modify
- **Blocks**: Nothing directly
- **Related**: Story 7.x (Messaging) may use similar notification patterns

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 6.9 - Face Candidature Status Notifications, FR41]
- [Source: docs/planning-artifacts/architecture.md - Notification patterns and naming]
- [Source: docs/planning-artifacts/prd.md - MVP vs V2 notification approach]
- [Source: backend/app/Models/User.php - Notifiable trait already included]
- [Source: _bmad-output/implementation-artifacts/6-6-producer-accept-candidature.md - Accept controller]
- [Source: _bmad-output/implementation-artifacts/6-7-producer-reject-candidature.md - Reject controller]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

- Test ordering issue fixed by using DB::table() for custom created_at timestamps

### Completion Notes List

- Backend: Created notifications database migration with proper indexes
- Backend: Created Notification model with markAsRead(), scopeUnread(), scopeRead() methods
- Backend: Modified Producer CandidatureController to create notifications on accept/reject
- Backend: Created Face NotificationController with index, unreadCount, markAsRead, markAllAsRead
- Backend: Added 4 notification routes under /v1/face/notifications
- Backend: Created 22 comprehensive tests (78 assertions) covering all acceptance criteria
- Frontend: Created notification feature folder with types, services, composables, components
- Frontend: Created NotificationBell component with polling (30s interval) for badge updates
- Frontend: Created NotificationsDropdown with mark as read functionality (using Gemini MCP)
- Frontend: Integrated NotificationBell in AppHeader (Face only)
- All 620 backend tests pass (2705 assertions)
- TypeScript type checking passes

### File List

**Backend Files Created:**
- `database/migrations/2026_01_27_221455_create_notifications_table.php`
- `app/Models/Notification.php`
- `app/Http/Controllers/Api/V1/Face/NotificationController.php`
- `app/Http/Resources/NotificationResource.php`
- `tests/Feature/Notification/FaceNotificationTest.php` - 22 tests

**Backend Files Modified:**
- `app/Http/Controllers/Api/V1/Producer/CandidatureController.php` - Added notification creation
- `routes/api/face.php` - Added notification routes

**Frontend Files Created:**
- `src/features/notification/types/index.ts`
- `src/features/notification/services/notificationApi.ts`
- `src/features/notification/composables/useNotifications.ts`
- `src/features/notification/composables/index.ts`
- `src/features/notification/components/NotificationBell.vue`
- `src/features/notification/components/NotificationsDropdown.vue`
- `src/features/notification/components/index.ts`
- `src/features/notification/index.ts`

**Frontend Files Modified:**
- `src/components/layout/AppHeader.vue` - Added NotificationBell for Face users
