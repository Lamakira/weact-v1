# Story RT-1.4: Fallback et non-régression

Status: done

## Story

As a **Face or Producer**,
I want **the notification system to stay reliable even when the WebSocket connection drops — with automatic refetch on tab focus, reconnection handling, and a slow safety poll**,
so that **my unread badge is always accurate and the notification list self-heals after connectivity gaps, regardless of network conditions**.

## Acceptance Criteria

1. **Given** the user returns to the browser tab (window.focus) **Then** `fetchUnreadCount()` is called to reconcile the badge, and if the notification list was previously loaded (`hasFetchedItems`), `fetchNotifications()` is also called to refresh the list
2. **Given** the Echo WebSocket transport reconnects after a disconnection **Then** the store reconciles state by calling `fetchUnreadCount()` for the badge, and if the notification list was previously loaded (`hasFetchedItems`), also calls `fetchNotifications()` to refresh the list. _(Note: Pusher handles channel re-subscription automatically on transport reconnect — the store does NOT need to call `subscribe()` again or rebind the `.listen()` handler. Only state reconciliation is needed.)_
3. **Given** the user is authenticated and the app is running **Then** a slow safety poll calls `fetchUnreadCount()` every 2 minutes as a background safety net
4. **Given** the safety poll is running **Then** it is stopped on logout (`unsubscribe()`) and not started for unauthenticated users
5. **Given** a Face user receives a booking notification (booking_received) or a candidature acceptance (candidature_accepted) via Echo push **Then** the store's reactive path works: badge increments and the notification is prepended to `items` if `hasFetchedItems` is true — no regression from RT-1.3. _(Note: this tests the Echo→store→UI reactive chain, not fetch-timing robustness — the known knownIds race from RT-1.2 is out of scope, see Dev Notes.)_
6. **Given** a Producer user receives a booking acceptance (booking_accepted) or any other Producer-targeted notification via Echo push **Then** the store's reactive path works identically — no regression from RT-1.3. _(Same note as AC #5. The store handles all notification types generically via the `.notification.created` event — it does not distinguish by role or type constant.)_
7. **Given** the existing notification UX **Then** mark as read, mark all as read, dropdown navigation, badge count, animated transitions all work identically to before RT-1.4
8. **Given** a 401 response from the API (session expired) **Then** `notificationStore.unsubscribe()` is called before `authStore.clearAuth()`, so the Echo channel is properly cleaned up and `isSubscribed` is reset — allowing re-subscription on the next login

## Tasks / Subtasks

- [x] Task 1: Add `window.focus` refetch to the notification store (AC: #1)
  - [x] In `frontend/src/stores/notification.ts`, add an internal helper `startFocusListener()` (NOT returned from `defineStore`) that registers a `window.addEventListener('focus', ...)` handler calling `fetchUnreadCount()` and, if `hasFetchedItems.value` is true, also calling `fetchNotifications()`
  - [x] Add an internal helper `stopFocusListener()` that removes the event listener
  - [x] Call `startFocusListener()` inside `subscribe()` — unconditionally, even before `.subscribed()` fires (focus fallback is useful regardless of channel state)
  - [x] Call `stopFocusListener()` in `unsubscribe()` (before `$reset()`)
  - [x] **Also call `stopFocusListener()` in the existing `channel.error` handler** (line ~147) to prevent leaked listeners when channel subscription fails
  - [x] Store the handler reference in a module-level `let` variable (not reactive state) for proper cleanup

- [x] Task 2: Add Echo reconnect handling (AC: #2)
  - [x] In `frontend/src/stores/notification.ts`, inside `subscribe()`, after the `.listen()` call, bind a handler on `echo.connector.pusher.connection` for the `connected` event (Pusher fires this on transport reconnect — Pusher handles channel re-subscription automatically, we only need to reconcile state)
  - [x] On `connected`: call `fetchUnreadCount()` and, if `hasFetchedItems.value` is true, also call `fetchNotifications()` to reconcile any missed events during disconnection
  - [x] Add an internal helper `stopReconnectListener()` that unbinds the handler via `echo.connector.pusher.connection.unbind('connected', handler)`
  - [x] Call `stopReconnectListener()` in `unsubscribe()` (before `$reset()`)
  - [x] **Also call `stopReconnectListener()` in the existing `channel.error` handler** to prevent leaked handlers when channel subscription fails
  - [x] Store the handler reference in a module-level `let` variable

- [x] Task 3: Add slow safety polling (AC: #3, #4)
  - [x] In `frontend/src/stores/notification.ts`, add an internal helper `startSafetyPoll()` (NOT returned from `defineStore`) that creates a `setInterval(fetchUnreadCount, 120_000)` (2 minutes)
  - [x] Add an internal helper `stopSafetyPoll()` that clears the interval
  - [x] Call `startSafetyPoll()` inside `subscribe()` — unconditionally, even before `.subscribed()` fires (the safety poll is the last-resort fallback and should work regardless)
  - [x] Call `stopSafetyPoll()` in `unsubscribe()` (before `$reset()`)
  - [x] **Also call `stopSafetyPoll()` in the existing `channel.error` handler** to prevent leaked intervals when channel subscription fails
  - [x] Store the interval ID in a module-level `let` variable (not reactive state)

- [x] Task 4: Fix 401 interceptor to clean up notification subscription (AC: #8)
  - [x] In `frontend/src/services/apiClient.ts`, in the 401 response interceptor (line ~118), add a dynamic import for the notification store **before** the existing auth store import: `import('@/stores/notification').then(({ useNotificationStore }) => { ... })`
  - [x] Call `useNotificationStore(piniaInstance!).unsubscribe()` **before** `useAuthStore(piniaInstance!).clearAuth()` — the interceptor runs outside component setup, so the Pinia instance must be passed explicitly (same pattern as the existing `useAuthStore(piniaInstance!)` on line 121)
  - [x] Verify that `unsubscribe()` reads `userId` from auth store before `clearAuth()` wipes it — both calls must happen in the same `.then()` block to guarantee ordering
  - [x] Add a test: 401 interceptor calls `notificationStore.unsubscribe()` before `authStore.clearAuth()`, and the unsubscribe cleans up all fallback handlers (focus listener, reconnect handler, safety poll interval)

- [x] Task 5: Verify no regression on existing notification UX (AC: #5, #6, #7)
  - [x] Run existing notification store tests (23 tests in `frontend/src/stores/__tests__/notification.spec.ts`) — all must pass
  - [x] Run existing component tests (14 tests in `frontend/src/features/notification/components/__tests__/`) — all must pass
  - [x] Manually verify (or write tests for): mark as read via store, mark all as read via store, dropdown close emit, dropdown navigation (`router.push` on notification click), badge reactivity from store

- [x] Task 6: Add tests for fallback mechanisms (AC: #1, #2, #3, #4, #8)
  - [x] Test: `subscribe()` registers a window focus listener
  - [x] Test: window focus event triggers `fetchUnreadCount()`
  - [x] Test: window focus event also triggers `fetchNotifications()` when `hasFetchedItems` is true
  - [x] Test: window focus event does NOT trigger `fetchNotifications()` when `hasFetchedItems` is false
  - [x] Test: `unsubscribe()` removes the window focus listener
  - [x] Test: `subscribe()` starts the safety poll interval
  - [x] Test: `unsubscribe()` clears the safety poll interval
  - [x] Test: safety poll does not start if user is not authenticated
  - [x] Test: reconnect (`connected`) event triggers `fetchUnreadCount()`
  - [x] Test: reconnect event also triggers `fetchNotifications()` when `hasFetchedItems` is true
  - [x] Test: `unsubscribe()` unbinds the reconnect handler
  - [x] Test: channel error handler cleans up focus listener, reconnect handler, and safety poll (no leaked handlers/intervals)

## Dev Notes

### Architecture: All Fallbacks Live in the Store

All three fallback mechanisms (window.focus, reconnect, safety poll) belong in `useNotificationStore` — not in components. This keeps the store as the single source of truth and avoids lifecycle issues with component mount/unmount.

The store's `subscribe()` already handles Echo channel setup. Extend it to also set up focus listener, reconnect handler, and safety poll. Symmetrically, `unsubscribe()` cleans up everything.

### Window Focus Handler — Implementation Pattern

```ts
// Module-level (outside defineStore return), not reactive
let focusHandler: (() => void) | null = null

function startFocusListener(): void {
  if (focusHandler) return
  focusHandler = () => {
    fetchUnreadCount()
    if (hasFetchedItems.value) fetchNotifications()
  }
  window.addEventListener('focus', focusHandler)
}

function stopFocusListener(): void {
  if (focusHandler) {
    window.removeEventListener('focus', focusHandler)
    focusHandler = null
  }
}
```

The `hasFetchedItems` check ensures we only refresh the list if the dropdown was previously opened. If the user never opened the dropdown, we only reconcile the badge count (cheap).

Call `startFocusListener()` inside `subscribe()` unconditionally, before `.subscribed()` fires if needed — the focus fallback should work even if the WebSocket channel never fully subscribes. Call `stopFocusListener()` at the beginning of `unsubscribe()`.

### Echo Reconnect — Pusher Protocol

Laravel Echo with Reverb uses the Pusher protocol. The underlying Pusher connection exposes state change events. Access the reconnect event via:

```ts
echo.connector.pusher.connection.bind('connected', handler)
```

This fires on initial connection AND on reconnection. To avoid a redundant fetch on first connect (we already call `fetchUnreadCount()` at login), either:
- Track that the first `connected` event is the initial one and skip it
- Or simply let it refetch — `fetchUnreadCount()` is cheap (single count endpoint) and an extra call at login is harmless

**Recommended: let it fire on every `connected` event** — simplest and most robust. The cost is one extra API call at login, which is negligible.

Unbind on cleanup:
```ts
echo.connector.pusher.connection.unbind('connected', handler)
```

### Safety Poll — Why 2 Minutes

The epic specifies "2-5 minutes". Use 2 minutes (`120_000ms`) — it's the most responsive end of the range and the API call is extremely lightweight (a single `SELECT COUNT(*)` with a `WHERE read_at IS NULL`).

The safety poll calls `fetchUnreadCount()` only (not `fetchNotifications()`). This is intentional — the poll fires every 2 minutes regardless of user activity, and fetching the full list that often would be wasteful. The list self-heals via the focus/reconnect handlers (which do refresh `items` if `hasFetchedItems` is true) or on next dropdown open.

### Channel Error Path — Cleanup Is Critical

The current `subscribe()` has a `channel.error` handler (line ~147) that resets `isSubscribing`/`isSubscribed` flags and calls `echo.leave()`. After RT-1.4, this error handler **must also** call `stopFocusListener()`, `stopReconnectListener()`, and `stopSafetyPoll()`. Otherwise, if the channel subscription fails, the fallback handlers/intervals leak and run indefinitely with no way to clean them up (since `isSubscribed` is false, the user can't trigger `unsubscribe()`).

The fallbacks are started unconditionally in `subscribe()` (before `.subscribed()` fires) because they're useful even without a WebSocket channel. But the error handler must mirror `unsubscribe()` for cleanup.

### What NOT to Do

- **Do NOT add polling back to `NotificationBell.vue`** — all polling logic lives in the store. RT-1.3 removed component-level polling for good reason.
- **Do NOT call `fetchNotifications()` in the safety poll** — only `fetchUnreadCount()`. Fetching the full list every 2 minutes would be wasteful. The focus/reconnect handlers DO refresh items when `hasFetchedItems` is true — that's the correct place for list reconciliation.
- **Do NOT expose `startFocusListener`, `stopFocusListener`, `startSafetyPoll`, `stopSafetyPoll`, `stopReconnectListener` in the store's return object** — in a Pinia setup store, only values in the `return {}` block are public. These helpers are internal functions defined inside `defineStore` but NOT returned. Test them indirectly through `subscribe()`/`unsubscribe()` behavior (e.g., verify that after `subscribe()`, a focus event triggers `fetchUnreadCount`).

### 401 Interceptor — Session Expiry Cleanup

The 401 response interceptor in `frontend/src/services/apiClient.ts` currently calls `authStore.clearAuth()` without first calling `notificationStore.unsubscribe()`. This means:
1. Echo channel stays subscribed with stale credentials
2. `isSubscribed` remains `true`
3. On next login, `subscribe()` is a no-op because `isSubscribed` is still `true` → notifications silently stop working

The fix: in the 401 interceptor, call `useNotificationStore(piniaInstance!).unsubscribe()` **before** `useAuthStore(piniaInstance!).clearAuth()`. The `unsubscribe()` function reads `userId` from the auth store to call `echo.leave()`, so it must run while auth state is still intact.

**Critical:** The interceptor runs outside Vue component setup context, so `useNotificationStore()` without a Pinia instance will fail. The existing interceptor already uses `useAuthStore(piniaInstance!)` — follow the exact same pattern. Both calls must be in the same `.then()` block to guarantee ordering:

```ts
if (piniaInstance) {
  Promise.all([
    import('@/stores/notification'),
    import('@/stores/auth'),
  ]).then(([{ useNotificationStore }, { useAuthStore }]) => {
    useNotificationStore(piniaInstance!).unsubscribe()  // must be first
    useAuthStore(piniaInstance!).clearAuth()
  })
}
```

The logout flow in `useAuth.ts:135` already has the correct ordering (`unsubscribe()` then `clearAuth()`). The 401 interceptor must mirror this.

### Deferred Item from RT-1.3 Review: Reconciliation Path

The RT-1.3 code review identified that after removing polling, if Echo misses an event, `unreadCount` can drift indefinitely. This story directly addresses that with three layers:
1. **Window.focus** — catches up when user returns to tab
2. **Echo reconnect** — catches up after connection drops
3. **Safety poll** — catches up every 2 minutes regardless

### Deferred Item from RT-1.3 Review: fetchNotifications Race Condition

The `fetchNotifications()` race condition (knownIds cleared on fetch, realtime event lost in-flight) is a pre-existing store bug from RT-1.2. It is NOT in scope for RT-1.4. If addressed, it should be a separate store fix.

### Store Initialization Flow (Current State)

The store is initialized in two places:
1. **`App.vue:16-19`** — `onMounted`: if authenticated and not yet subscribed → `subscribe()` + `fetchUnreadCount()`
2. **`useAuth.ts:51-52`** — after login/register: `subscribe()` + `fetchUnreadCount()`
3. **`useAuth.ts:135`** — logout: `unsubscribe()`

After this story, `subscribe()` will internally set up focus listener, reconnect handler, and safety poll. No changes to `App.vue` or `useAuth.ts` needed.

### Test Pattern for Window Events

Use `vi.spyOn(window, 'addEventListener')` and `vi.spyOn(window, 'removeEventListener')` to verify focus listener registration. Alternatively, dispatch a focus event: `window.dispatchEvent(new Event('focus'))`.

For the safety poll, use `vi.useFakeTimers()` and `vi.advanceTimersByTime(120_000)` to verify `fetchUnreadCount` is called.

For the reconnect handler, extract the handler from `echo.connector.pusher.connection.bind` mock and invoke it.

### Previous Story Intelligence (RT-1.3)

- Store actions `fetchNotifications`, `markAsRead`, `markAllAsRead` return `Promise<boolean>` (changed in RT-1.3)
- `fetchUnreadCount` still returns `Promise<void>` — no need to change this for fallback purposes
- Components read all state via `storeToRefs` — no component changes needed for RT-1.4
- All notification domain tests pass (37 tests across store + components)

### References

- [Source: frontend/src/stores/notification.ts] — Store to extend with fallback mechanisms
- [Source: frontend/src/stores/__tests__/notification.spec.ts] — Existing store tests (23 tests)
- [Source: frontend/src/features/notification/components/__tests__/] — Component tests (14 tests)
- [Source: frontend/src/plugins/echo.ts] — Echo/Reverb configuration
- [Source: frontend/src/App.vue:16-19] — Store bootstrap on app reload
- [Source: frontend/src/features/auth/composables/useAuth.ts:51-52] — Store init on login
- [Source: frontend/src/features/auth/composables/useAuth.ts:135] — Store cleanup on logout
- [Source: frontend/src/services/apiClient.ts] — 401 interceptor that needs notification cleanup
- [Source: _bmad-output/planning-artifacts/epics-realtime-notifications.md#RT-1.4]
- [Source: _bmad-output/implementation-artifacts/rt-1-3-refactor-ui-bell-dropdown.md] — Previous story with review findings

## Dev Agent Record

### Agent Model Used

GPT-5 Codex

### Implementation Plan

- Extend `frontend/src/stores/notification.ts` with three internal fallback mechanisms that are started from `subscribe()` and fully cleaned up from `unsubscribe()` and the channel error path:
  - `window.focus` reconciliation for unread count and previously loaded items
  - Pusher `connected` reconciliation without resubscribing the channel
  - 2-minute unread-count safety polling
- Fix `frontend/src/services/apiClient.ts` so the 401 interceptor unsubscribes the notification store before clearing auth
- Add store-level and interceptor-level tests to cover focus/reconnect/polling cleanup and 401 ordering

### Debug Log

- `npm run test:frontend -- src/stores/__tests__/notification.spec.ts src/features/notification/components/__tests__/NotificationBell.spec.ts src/features/notification/components/__tests__/NotificationsDropdown.spec.ts src/services/__tests__/apiClient.spec.ts` ✅
- `npm run lint -- src/stores/notification.ts src/stores/__tests__/notification.spec.ts src/services/apiClient.ts src/services/__tests__/apiClient.spec.ts` ✅
- `npm run test:frontend` ❌ blocked by unrelated pre-existing failures outside RT-1.4 scope (`16` files failing, `71` tests total), mostly in existing Face/Producer composable suites such as `useAvailability.spec.ts`, `useBasicInfo.spec.ts`, `useBioLocation.spec.ts`, `useProducerBio.spec.ts`, `useProducerProfilePhoto.spec.ts`

### Completion Notes List

- Implemented notification-store fallback reconciliation on tab focus, Echo reconnect, and a 2-minute unread-count safety poll, with cleanup on unsubscribe and channel error
- Updated the API 401 interceptor to call `notificationStore.unsubscribe()` before `authStore.clearAuth()` so stale Echo subscriptions do not survive session expiry
- Added targeted regression coverage for fallback registration/cleanup, reconnect reconciliation, safety polling, and 401 unsubscribe ordering
- Targeted RT-1.4 tests and lint pass; the full frontend regression suite remains red outside this story's touched area due to unrelated shared-cache baseline failures

### File List

- _bmad-output/implementation-artifacts/rt-1-4-fallback-non-regression.md
- _bmad-output/implementation-artifacts/sprint-status.yaml
- frontend/src/services/__tests__/apiClient.spec.ts
- frontend/src/services/apiClient.ts
- frontend/src/stores/__tests__/notification.spec.ts
- frontend/src/stores/notification.ts

### Review Findings

- [x] [Review][Defer] In-flight fallback fetches can repopulate the notification store after logout or reset [frontend/src/stores/notification.ts:31] — deferred, low impact race: only already in-flight requests can write stale badge state, the window is brief, and the next login reconciliation overwrites it
- [x] [Review][Defer] 401 cleanup can skip `clearAuth()` if the dynamic import chain rejects [frontend/src/services/apiClient.ts:120] — deferred, pre-existing best-effort cleanup pattern: localStorage is cleared and login redirect happens synchronously even if the dynamic import chain fails

## Change Log

- 2026-04-11: Implemented RT-1.4 notification fallbacks (focus, reconnect, safety poll), added 401 cleanup ordering, expanded notification regression tests, and moved the story to `review` with the unrelated frontend baseline failures explicitly noted as out of scope
