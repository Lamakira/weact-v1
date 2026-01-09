# Story 2.4: User Logout

Status: review

## Story

As a **logged-in user**,
I want **to logout from my account**,
So that **my session is securely terminated**.

## Acceptance Criteria

1. **Given** I am logged in, **When** I click the logout button, **Then** my Sanctum token is revoked on the server **And** my local token storage is cleared **And** I am redirected to the login page

2. **Given** I have logged out, **When** I try to access a protected route, **Then** I am redirected to the login page

3. **Given** I am logged out, **When** I try to use my old token for API requests, **Then** I receive a 401 Unauthorized response

**(FR6 - Un utilisateur connecté peut se déconnecter)**

## Tasks / Subtasks

### Backend Tasks

- [x] **Task 1: Create LogoutController** (AC: #1, #3)
  - [x] 1.1 Create `app/Http/Controllers/Api/V1/Auth/LogoutController.php`
  - [x] 1.2 Implement `__invoke(Request $request)` method
  - [x] 1.3 Revoke current user's token via `$request->user()->currentAccessToken()->delete()`
  - [x] 1.4 Return 200 with success message "Déconnexion réussie"

- [x] **Task 2: Register API Route** (AC: #1)
  - [x] 2.1 Add route in `routes/api.php`: `POST /api/v1/auth/logout`
  - [x] 2.2 Protect route with `auth:sanctum` middleware
  - [x] 2.3 Name route `auth.logout`

- [x] **Task 3: Backend Tests** (AC: #1, #2, #3)
  - [x] 3.1 Create `tests/Feature/Auth/LogoutTest.php`
  - [x] 3.2 Test successful logout returns 200 and revokes token
  - [x] 3.3 Test revoked token cannot access protected routes (401)
  - [x] 3.4 Test unauthenticated user cannot call logout endpoint (401)

### Frontend Tasks

- [x] **Task 4: Update authApi Service** (AC: #1)
  - [x] 4.1 Add `logout(): Promise<void>` method in `authApi.ts`
  - [x] 4.2 POST to `/auth/logout` with auth token
  - [x] 4.3 Handle errors gracefully (clear local state even if API fails)

- [x] **Task 5: Update useAuth Composable** (AC: #1)
  - [x] 5.1 Update `logout()` method to call API before clearing local state
  - [x] 5.2 Clear token from localStorage via authStore
  - [x] 5.3 Clear user data from authStore
  - [x] 5.4 Redirect to login page

- [x] **Task 6: Add Logout Button to UI** (AC: #1)
  - [x] 6.1 Add logout button to Face dashboard header (placeholder)
  - [x] 6.2 Add logout button to Producer dashboard header (placeholder)
  - [x] 6.3 Style button with appropriate icon and text "Déconnexion"
  - [x] 6.4 Wire button click to `useAuth().logout()`

- [x] **Task 7: Frontend Tests** (AC: #1, #2)
  - [x] 7.1 Create `useAuth.spec.ts` to test logout functionality
  - [x] 7.2 Test logout clears token from store
  - [x] 7.3 Test logout clears user from store
  - [x] 7.4 Test logout redirects to login page

## Technical Notes

### Backend Architecture
- Use Sanctum's token revocation: `$request->user()->currentAccessToken()->delete()`
- Only revokes current token, not all user tokens (allows multi-device sessions)
- Route must be protected by `auth:sanctum` middleware

### Frontend Architecture
- Call API logout first, then clear local state regardless of API response
- This ensures local state is cleared even if network fails
- Use existing authStore.clearAuth() method

### API Response Format
```json
// Success (200)
{
  "data": null,
  "message": "Déconnexion réussie",
  "meta": {}
}

// Unauthenticated (401)
{
  "error": {
    "message": "Unauthenticated",
    "code": "UNAUTHENTICATED"
  }
}
```

## Dev Notes

- Logout should work gracefully even if API call fails (clear local state anyway)
- Consider implementing "logout all devices" in future iteration
- Session timeout (2h inactivity per NFR-S9) will be handled separately

## File List

- backend/app/Http/Controllers/Api/V1/Auth/LogoutController.php
- backend/routes/api.php
- backend/database/factories/UserFactory.php
- backend/tests/Feature/Auth/LogoutTest.php
- frontend/src/features/auth/composables/useAuth.ts
- frontend/src/features/auth/composables/__tests__/useAuth.spec.ts
- frontend/src/features/auth/services/authApi.ts
- frontend/src/router/index.ts
