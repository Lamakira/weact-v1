# Story 2.6: Authentication Frontend Integration

Status: done

## Story

As a **user**,
I want **the frontend to properly handle authentication state**,
So that **I have a seamless login/logout experience with protected routes**.

## Acceptance Criteria

1. **Given** I am not logged in, **When** I try to access a protected route (e.g., /dashboard), **Then** I am redirected to the login page

2. **Given** I am logged in as a Face, **When** I try to access a Producer-only route, **Then** I am redirected to my Face dashboard

3. **Given** my token expires during a session, **When** I make an API request, **Then** I am redirected to login with message "Session expirée"

4. The auth store (Pinia) properly tracks login state

5. The navigation shows appropriate menu items based on role

## Tasks / Subtasks

### Task 1: Add 401 Response Interceptor with Redirect (AC: #3)

- [x] 1.1 Update `frontend/src/services/apiClient.ts` to import router
- [x] 1.2 Update 401 interceptor to redirect to login page with `?message=session-expired`
- [x] 1.3 Clear auth store state (not just localStorage) on 401
- [x] 1.4 Ensure redirect only happens for non-auth endpoints (skip for login/register)

### Task 2: Update LoginPage to Handle Session Expired Message (AC: #3)

- [x] 2.1 Update `frontend/src/pages/auth/LoginPage.vue` to check for `?message=session-expired` query param
- [x] 2.2 Display "Session expirée, veuillez vous reconnecter" alert/toast when param is present
- [x] 2.3 Clear the query param after displaying message to prevent repeat

### Task 3: Create AppHeader Component with Role-Based Navigation (AC: #5)

- [x] 3.1 Create `frontend/src/components/layout/AppHeader.vue` component
- [x] 3.2 Import and use `useAuthStore` to get authentication state
- [x] 3.3 Import WEACT logo from `@/assets/images/logonoir.svg`
- [x] 3.4 **Guest navigation (left to right):**
  - Logo (links to home)
  - "Trouver des faces" → `/faces`
  - "Missions" → `/missions`
  - "Ressources" → `/ressources`
  - "Poster une mission" button (filled primary) → `/register/producer`
  - "Devenir une face" button (outline) → `/register/face`
  - "Se connecter" link → `/login`
- [x] 3.5 **Face authenticated navigation:**
  - Same public links + "Mes candidatures" → `/face/candidatures`
  - Dashboard link → `/face/dashboard`
  - Logout button
- [x] 3.6 **Producer authenticated navigation:**
  - Same public links + "Mes missions" → `/producer/missions`
  - Dashboard link → `/producer/dashboard`
  - Logout button
- [x] 3.7 Style with white background, gray border-bottom, responsive design

### Task 4: Update App.vue to Use AppHeader (AC: #5)

- [x] 4.1 Import `AppHeader` component
- [x] 4.2 Replace inline header with `<AppHeader />`
- [x] 4.3 Ensure proper layout structure

### Task 5: Add Logout Functionality to Header (AC: #5)

- [x] 5.1 Create `useLogout` composable or use existing `useAuth` composable
- [x] 5.2 Implement logout click handler that:
  - Calls logout API
  - Clears auth store
  - Redirects to home page
  - Shows success toast
- [x] 5.3 Handle logout errors gracefully (still clear local state)

### Task 6: Verify Router Guards Work Correctly (AC: #1, #2)

- [x] 6.1 Test protected route redirect to login (already implemented, verify)
- [x] 6.2 Test role-based route redirect (already implemented, verify)
- [x] 6.3 Ensure `redirect` query param is preserved for post-login redirect
- [x] 6.4 Update login flow to redirect to original destination after successful login

### Task 7: Frontend Tests (AC: #1, #2, #3, #5)

- [x] 7.1 Create `frontend/src/components/layout/__tests__/AppHeader.spec.ts`
- [x] 7.2 Test guest navigation displays login/register links
- [x] 7.3 Test authenticated navigation displays dashboard/logout
- [x] 7.4 Test Face user sees Face dashboard link
- [x] 7.5 Test Producer user sees Producer dashboard link
- [x] 7.6 Update `LoginPage.spec.ts` to test session-expired message display
- [x] 7.7 Test 401 interceptor redirect behavior

## Dev Notes

### Token Expiration Handling Architecture

**Current Implementation (incomplete):**
```typescript
// apiClient.ts line 80-91 - Currently only clears localStorage
if (error.response?.status === 401) {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem('auth_user')
}
```

**Required Update:**
The 401 interceptor must:
1. Clear auth store state (not just localStorage)
2. Redirect to login page with `?message=session-expired`
3. Skip redirect for auth endpoints (login, register, forgot-password)

**Implementation Pattern:**
```typescript
// Import router at module level or use a getter pattern
import router from '@/router'
import { useAuthStore } from '@/stores/auth'

// In 401 handler:
if (error.response?.status === 401) {
  const authStore = useAuthStore()
  authStore.clearAuth()

  // Don't redirect if already on auth page
  const currentPath = router.currentRoute.value.path
  const authPaths = ['/login', '/register', '/forgot-password', '/reset-password']
  const isAuthPage = authPaths.some(path => currentPath.startsWith(path))

  if (!isAuthPage) {
    router.push({
      name: 'login',
      query: { message: 'session-expired' }
    })
  }
}
```

### Header Design Specification (from Figma)

**Logo Files (copied to project):**
- `frontend/src/assets/images/logonoir.svg` - Black logo (for light backgrounds)
- `frontend/src/assets/images/logoblanc.svg` - White logo (for dark backgrounds)

**Guest Header Layout:**
```
┌─────────────────────────────────────────────────────────────────────────────┐
│ [WEACT Logo]     Trouver des faces   Missions   Ressources     [Poster une mission] [Devenir une face] Se connecter │
└─────────────────────────────────────────────────────────────────────────────┘
```

- **Left:** WEACT logo (logonoir.svg)
- **Center:** Navigation links
  - "Trouver des faces" → `/faces` (public)
  - "Missions" → `/missions` (public)
  - "Ressources" → `/ressources` (blog)
- **Right:** CTAs + Login
  - "Poster une mission" → `/register/producer` (filled primary button)
  - "Devenir une face" → `/register/face` (outline primary button)
  - "Se connecter" → `/login` (text link)

**Authenticated Header (Face):**
```
┌─────────────────────────────────────────────────────────────────────────────┐
│ [WEACT Logo]     Trouver des faces   Missions   Ressources   Mes candidatures   Dashboard   [Avatar] [Déconnexion] │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Authenticated Header (Producer):**
```
┌─────────────────────────────────────────────────────────────────────────────┐
│ [WEACT Logo]     Trouver des faces   Missions   Ressources   Mes missions   Dashboard   [Avatar] [Déconnexion] │
└─────────────────────────────────────────────────────────────────────────────┘
```

### AppHeader Component Structure

```vue
<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAuth } from '@/features/auth/composables/useAuth'
import { Button } from '@/components/ui/button'
import logoNoir from '@/assets/images/logonoir.svg'

const authStore = useAuthStore()
const { logout, isLoading } = useAuth()
const router = useRouter()

async function handleLogout() {
  await logout()
  router.push({ name: 'home' })
}
</script>

<template>
  <header class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 py-4">
      <div class="flex items-center justify-between">
        <!-- Logo -->
        <RouterLink to="/" class="flex-shrink-0">
          <img :src="logoNoir" alt="WEACT" class="h-8" />
        </RouterLink>

        <!-- Center Navigation -->
        <nav class="hidden md:flex items-center gap-8">
          <RouterLink to="/faces" class="text-gray-700 hover:text-primary transition-colors">
            Trouver des faces
          </RouterLink>
          <RouterLink to="/missions" class="text-gray-700 hover:text-primary transition-colors">
            Missions
          </RouterLink>
          <RouterLink to="/ressources" class="text-gray-700 hover:text-primary transition-colors">
            Ressources
          </RouterLink>

          <!-- Face-specific links -->
          <RouterLink
            v-if="authStore.isAuthenticated && authStore.isFace"
            to="/face/candidatures"
            class="text-gray-700 hover:text-primary transition-colors"
          >
            Mes candidatures
          </RouterLink>

          <!-- Producer-specific links -->
          <RouterLink
            v-if="authStore.isAuthenticated && authStore.isProducer"
            to="/producer/missions"
            class="text-gray-700 hover:text-primary transition-colors"
          >
            Mes missions
          </RouterLink>
        </nav>

        <!-- Right Actions -->
        <div class="flex items-center gap-4">
          <!-- Guest Navigation -->
          <template v-if="!authStore.isAuthenticated">
            <Button as-child>
              <RouterLink to="/register/producer">Poster une mission</RouterLink>
            </Button>
            <Button variant="outline" as-child>
              <RouterLink to="/register/face">Devenir une face</RouterLink>
            </Button>
            <RouterLink to="/login" class="text-gray-700 hover:text-primary transition-colors">
              Se connecter
            </RouterLink>
          </template>

          <!-- Authenticated Navigation -->
          <template v-else>
            <RouterLink
              :to="authStore.isFace ? '/face/dashboard' : '/producer/dashboard'"
              class="text-gray-700 hover:text-primary transition-colors"
            >
              Dashboard
            </RouterLink>
            <Button @click="handleLogout" :disabled="isLoading" variant="outline" size="sm">
              Déconnexion
            </Button>
          </template>
        </div>
      </div>
    </div>
  </header>
</template>
```

### Post-Login Redirect Implementation

The router already stores the original destination in `query.redirect`. Update the login flow:

```typescript
// In login success handler
const redirect = route.query.redirect as string
if (redirect) {
  router.push(redirect)
} else {
  // Default redirect based on role
  router.push(authStore.isFace ? '/face/dashboard' : '/producer/dashboard')
}
```

### Session Expired Message on LoginPage

```vue
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const toast = useToast()

onMounted(() => {
  if (route.query.message === 'session-expired') {
    toast.warning('Session expirée, veuillez vous reconnecter')
    // Clear query param without triggering navigation
    router.replace({ query: {} })
  }
})
</script>
```

### Project Structure Notes

**New Files to Create:**
- `frontend/src/components/layout/AppHeader.vue`
- `frontend/src/components/layout/__tests__/AppHeader.spec.ts`

**Files to Modify:**
- `frontend/src/services/apiClient.ts` - Add router redirect on 401
- `frontend/src/App.vue` - Replace inline header with AppHeader component
- `frontend/src/pages/auth/LoginPage.vue` - Add session-expired message handling
- `frontend/src/pages/auth/__tests__/LoginPage.spec.ts` - Add session-expired tests

### Previous Story Intelligence

From Story 2-5 (Password Reset):
- **Toast integration added:** vue-toastification is now configured with `useToast` composable
- **Query param messaging pattern:** Already implemented `?message=password-reset-success` pattern on LoginPage - extend this for `session-expired`
- **Auth store persistence:** User is persisted to localStorage, auth state works across page refreshes
- **401 handler partial:** Already clears token AND user from localStorage on 401

From Story 2-4 (User Logout):
- **useAuth composable:** Has `logout()` function that handles API call and store clearing
- **Logout flow:** Clears auth store even if API call fails (graceful degradation)

### Git Intelligence

Recent commits (from current branch):
- `46dc044` - Integration: shadcn-vue and vue-toastification
- `a7a3c39` - test(auth): add LoginPage tests for password reset success message

Key observations:
- shadcn-vue Button component is available at `@/components/ui/button`
- vue-toastification is configured with `useToast` composable
- LoginPage already has pattern for query param message display

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 2.6]
- [Source: _bmad-output/project-context.md#Frontend Architecture]
- [Source: docs/planning-artifacts/architecture.md#Frontend Architecture]
- [Source: frontend/src/router/index.ts - existing navigation guards]
- [Source: frontend/src/stores/auth.ts - auth store implementation]
- [Source: frontend/src/services/apiClient.ts - 401 handling]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

N/A - No debug issues encountered

### Completion Notes List

1. **401 Interceptor**: Implemented lazy-loaded router/pinia pattern in apiClient.ts to handle 401 responses. Clears auth store and redirects to login with `?message=session-expired` query param. Skips redirect for auth pages.

2. **Session Expired Message**: LoginPage now displays both success (password-reset) and warning (session-expired) messages. Uses both inline alert and toast notification for session-expired.

3. **AppHeader Component**: Created role-based navigation component matching Figma design. Shows guest CTAs (Poster une mission, Devenir une face, Se connecter) for unauthenticated users. Shows role-specific links (Mes candidatures for Face, Mes missions for Producer) and logout for authenticated users.

4. **Post-Login Redirect**: Updated handleLoginSuccess() to check for redirect query param and navigate there first, then fallback to role-based dashboard.

5. **Tests**: Created 17 new tests for AppHeader component covering guest/Face/Producer navigation states. Added 3 tests for session-expired message handling in LoginPage tests.

### File List

**New Files:**
- frontend/src/components/layout/AppHeader.vue
- frontend/src/components/layout/__tests__/AppHeader.spec.ts
- frontend/src/assets/images/logonoir.svg (copied from Downloads)
- frontend/src/assets/images/logoblanc.svg (copied from Downloads)

**Modified Files:**
- frontend/src/services/apiClient.ts - Added router/pinia lazy loading, 401 redirect logic
- frontend/src/main.ts - Added setRouter/setPinia calls after app creation
- frontend/src/pages/auth/LoginPage.vue - Added session-expired handling, post-login redirect
- frontend/src/pages/auth/__tests__/LoginPage.spec.ts - Added session-expired tests
- frontend/src/App.vue - Replaced inline header with AppHeader component

## Change Log

- 2026-01-11: Story 2.6 implementation completed - all 7 tasks finished, 75 tests passing
- 2026-01-11: Code review completed - 6 issues found (1 medium, 4 low, 1 info). Fixed issues #2, #4, #5:
  - Added missing routes to AppHeader test (fixed router warnings)
  - Refactored LoginPage onMounted to use else-if for mutually exclusive message handling
  - Added 2 new tests for redirect param preservation (now 77 tests passing)
  - Issue #1 (mobile nav) deferred to future story
  - Issue #3 (vite warning) accepted as technical debt (necessary for avoiding circular deps)
  - Issue #6 (named routes) noted for future consistency improvements
