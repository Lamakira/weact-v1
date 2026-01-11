# Story 2.5: Password Reset

Status: review

## Story

As a **user who forgot my password**,
I want **to reset my password via email**,
So that **I can regain access to my account**.

## Acceptance Criteria

1. **Given** I am on the forgot password page, **When** I submit my registered email, **Then** a password reset email is sent with a secure token **And** I see a confirmation message "Email envoyé"

2. **Given** I click the reset link in my email, **When** I submit a new valid password, **Then** my password is updated **And** I am redirected to the login page with success message

3. **Given** I try to use an expired or invalid reset token, **When** I submit a new password, **Then** I see an error message "Lien expiré ou invalide"

**(FR7 - Un utilisateur peut réinitialiser son mot de passe via email)**

## Tasks / Subtasks

### Backend Tasks

- [x] **Task 1: Create ForgotPasswordController** (AC: #1)
  - [x] 1.1 Create `app/Http/Controllers/Api/V1/Auth/ForgotPasswordController.php`
  - [x] 1.2 Implement `__invoke(ForgotPasswordRequest $request)` method
  - [x] 1.3 Use `Password::sendResetLink($request->only('email'))`
  - [x] 1.4 Return 200 with message "Email envoyé" on success
  - [x] 1.5 Return 422 with error message on failure (user not found, throttled)

- [x] **Task 2: Create ForgotPasswordRequest** (AC: #1)
  - [x] 2.1 Create `app/Http/Requests/Auth/ForgotPasswordRequest.php`
  - [x] 2.2 Validate `email` as required, email format, exists in users table
  - [x] 2.3 Follow existing request pattern from LoginRequest/RegisterFaceRequest

- [x] **Task 3: Create ResetPasswordController** (AC: #2, #3)
  - [x] 3.1 Create `app/Http/Controllers/Api/V1/Auth/ResetPasswordController.php`
  - [x] 3.2 Implement `__invoke(ResetPasswordRequest $request)` method
  - [x] 3.3 Use `Password::reset()` with closure to update password
  - [x] 3.4 Hash new password with `Hash::make()`
  - [x] 3.5 Fire `PasswordReset` event after successful reset
  - [x] 3.6 Return 200 with success message on valid token
  - [x] 3.7 Return 422 with "Lien expiré ou invalide" on invalid/expired token

- [x] **Task 4: Create ResetPasswordRequest** (AC: #2, #3)
  - [x] 4.1 Create `app/Http/Requests/Auth/ResetPasswordRequest.php`
  - [x] 4.2 Validate `token` as required string
  - [x] 4.3 Validate `email` as required, email format
  - [x] 4.4 Validate `password` as required, min:8, confirmed (password_confirmation)
  - [x] 4.5 Add password complexity rules: 1 uppercase, 1 number (per NFR-S2)

- [x] **Task 5: Register API Routes** (AC: #1, #2)
  - [x] 5.1 Add route `POST /api/v1/auth/forgot-password` → ForgotPasswordController
  - [x] 5.2 Add route `POST /api/v1/auth/reset-password` → ResetPasswordController
  - [x] 5.3 Apply rate limiting: 5 requests/minute for forgot-password (throttle:5,1)
  - [x] 5.4 Both routes should be public (guest middleware)
  - [x] 5.5 Name routes `auth.forgot-password` and `auth.reset-password`

- [x] **Task 6: Configure Password Reset Email** (AC: #1)
  - [x] 6.1 Verify `config/auth.php` has correct `passwords.users` broker config
  - [x] 6.2 Set password reset token expiration to 60 minutes (default)
  - [x] 6.3 Customize reset email notification for French content (optional: create custom notification)
  - [x] 6.4 Ensure email contains link to frontend reset page: `{FRONTEND_URL}/reset-password/{token}?email={email}`

- [x] **Task 7: Backend Tests** (AC: #1, #2, #3)
  - [x] 7.1 Create `tests/Feature/Auth/ForgotPasswordTest.php`
  - [x] 7.2 Test forgot-password with valid email sends email (use Notification::fake())
  - [x] 7.3 Test forgot-password with invalid email returns 422
  - [x] 7.4 Test forgot-password rate limiting (6th request within 1 min fails)
  - [x] 7.5 Create `tests/Feature/Auth/ResetPasswordTest.php`
  - [x] 7.6 Test reset-password with valid token updates password
  - [x] 7.7 Test reset-password with expired token returns 422
  - [x] 7.8 Test reset-password with invalid token returns 422
  - [x] 7.9 Test reset-password enforces password complexity

### Frontend Tasks

- [x] **Task 8: Create ForgotPasswordPage** (AC: #1)
  - [x] 8.1 Create `frontend/src/pages/auth/ForgotPasswordPage.vue`
  - [x] 8.2 Form with email input field
  - [x] 8.3 Submit button with loading state
  - [x] 8.4 Success message display: "Email envoyé"
  - [x] 8.5 Error message display for validation errors
  - [x] 8.6 Link back to login page

- [x] **Task 9: Create ResetPasswordPage** (AC: #2, #3)
  - [x] 9.1 Create `frontend/src/pages/auth/ResetPasswordPage.vue`
  - [x] 9.2 Extract `token` from route params, `email` from query string
  - [x] 9.3 Form with password and password_confirmation inputs
  - [x] 9.4 Show password requirements hint
  - [x] 9.5 Submit button with loading state
  - [x] 9.6 Redirect to login on success with success message
  - [x] 9.7 Display error for invalid/expired token

- [x] **Task 10: Update authApi Service** (AC: #1, #2)
  - [x] 10.1 Add `forgotPassword(email: string): Promise<void>` method
  - [x] 10.2 Add `resetPassword(data: ResetPasswordData): Promise<void>` method
  - [x] 10.3 Define `ResetPasswordData` type: `{ token, email, password, password_confirmation }`
  - [x] 10.4 Both methods should call `getCsrfCookie()` before POST

- [x] **Task 11: Create Password Reset Composable** (AC: #1, #2, #3)
  - [x] 11.1 Create `frontend/src/features/auth/composables/usePasswordReset.ts`
  - [x] 11.2 Expose `forgotPassword(email)` with loading and error state
  - [x] 11.3 Expose `resetPassword(data)` with loading and error state
  - [x] 11.4 Handle success/error toasts
  - [x] 11.5 Handle redirect on successful reset

- [x] **Task 12: Add Routes** (AC: #1, #2)
  - [x] 12.1 Add route `/forgot-password` → ForgotPasswordPage (guest only)
  - [x] 12.2 Add route `/reset-password/:token` → ResetPasswordPage (guest only)
  - [x] 12.3 Add "Mot de passe oublié ?" link on LoginPage

- [x] **Task 13: Frontend Tests** (AC: #1, #2, #3)
  - [x] 13.1 Create `ForgotPasswordPage.spec.ts` test file
  - [x] 13.2 Test form submission calls API
  - [x] 13.3 Test success message display
  - [x] 13.4 Test error handling
  - [x] 13.5 Create `ResetPasswordPage.spec.ts` test file
  - [x] 13.6 Test token extraction from URL
  - [x] 13.7 Test form submission and redirect
  - [x] 13.8 Test error message for invalid token

## Dev Notes

### Backend Architecture

**Laravel Password Broker Pattern:**
```php
// Forgot Password - sends email
$status = Password::sendResetLink($request->only('email'));

// Reset Password - validates token and updates password
$status = Password::reset(
    $request->only('email', 'password', 'password_confirmation', 'token'),
    function (User $user, string $password) {
        $user->forceFill([
            'password' => Hash::make($password)
        ])->setRememberToken(Str::random(60));
        $user->save();
        event(new PasswordReset($user));
    }
);
```

**Password Broker Status Constants:**
- `Password::RESET_LINK_SENT` - Email sent successfully
- `Password::PASSWORD_RESET` - Password reset successfully
- `Password::INVALID_TOKEN` - Token is invalid
- `Password::INVALID_USER` - User not found
- `Password::RESET_THROTTLED` - Too many attempts

### Frontend Architecture

**URL Structure:**
- Forgot Password: `POST /api/v1/auth/forgot-password` with `{ email }`
- Reset Password: `POST /api/v1/auth/reset-password` with `{ token, email, password, password_confirmation }`

**Reset Link Format:**
The email sent by Laravel will contain a link. Configure it to point to the frontend:
`{FRONTEND_URL}/reset-password/{token}?email={email}`

### API Response Format

```json
// Forgot Password Success (200)
{
  "data": null,
  "message": "Email envoyé",
  "meta": {}
}

// Forgot Password Error (422) - user not found
{
  "error": {
    "message": "Aucun compte associé à cet email",
    "code": "USER_NOT_FOUND"
  }
}

// Reset Password Success (200)
{
  "data": null,
  "message": "Mot de passe réinitialisé avec succès",
  "meta": {}
}

// Reset Password Error (422) - invalid token
{
  "error": {
    "message": "Lien expiré ou invalide",
    "code": "INVALID_TOKEN"
  }
}
```

### Security Requirements (NFR-S2)

Password complexity rules:
- Minimum 8 characters
- At least 1 uppercase letter
- At least 1 number

Laravel validation rule:
```php
'password' => [
    'required',
    'min:8',
    'confirmed',
    'regex:/[A-Z]/',      // At least one uppercase
    'regex:/[0-9]/',      // At least one number
],
```

### Rate Limiting

- Forgot Password: 5 requests per minute per IP (throttle:5,1)
- This prevents email bombing attacks

### Project Structure Notes

**New Files to Create:**
- `backend/app/Http/Controllers/Api/V1/Auth/ForgotPasswordController.php`
- `backend/app/Http/Controllers/Api/V1/Auth/ResetPasswordController.php`
- `backend/app/Http/Requests/Auth/ForgotPasswordRequest.php`
- `backend/app/Http/Requests/Auth/ResetPasswordRequest.php`
- `backend/tests/Feature/Auth/ForgotPasswordTest.php`
- `backend/tests/Feature/Auth/ResetPasswordTest.php`
- `frontend/src/pages/auth/ForgotPasswordPage.vue`
- `frontend/src/pages/auth/ResetPasswordPage.vue`
- `frontend/src/features/auth/composables/usePasswordReset.ts`

**Files to Modify:**
- `backend/routes/api.php` - Add forgot-password and reset-password routes
- `frontend/src/features/auth/services/authApi.ts` - Add forgotPassword and resetPassword methods
- `frontend/src/router/index.ts` - Add forgot-password and reset-password routes
- `frontend/src/pages/auth/LoginPage.vue` - Add "Mot de passe oublié ?" link

### Previous Story Intelligence

From Story 2-4 (User Logout) code review:
- **User persistence fix applied:** User data is now persisted to localStorage. Auth state should work correctly across page refreshes.
- **401 handler fixed:** Both token and user are cleared on 401 response.
- **authApi pattern:** Use simple async/await, let caller handle errors (don't swallow in service layer).
- **Loading state:** Use `isLoading` from auth store/composable for button disabled state.
- **Route nesting:** Place auth routes inside `prefix('auth')` group.

### References

- [Source: docs/planning-artifacts/architecture.md#Authentication & Security]
- [Source: _bmad-output/planning-artifacts/epics.md#Story 2.5: Password Reset]
- [Source: Laravel 12.x Password Reset Documentation]
- [Source: config/auth.php - password_reset_tokens configuration]

## File List

### Backend Files Created
- backend/app/Http/Controllers/Api/V1/Auth/ForgotPasswordController.php
- backend/app/Http/Controllers/Api/V1/Auth/ResetPasswordController.php
- backend/app/Http/Requests/Auth/ForgotPasswordRequest.php
- backend/app/Http/Requests/Auth/ResetPasswordRequest.php
- backend/app/Notifications/ResetPasswordNotification.php
- backend/tests/Feature/Auth/ForgotPasswordTest.php
- backend/tests/Feature/Auth/ResetPasswordTest.php

### Backend Files Modified
- backend/routes/api.php
- backend/app/Models/User.php
- backend/config/app.php

### Frontend Files Created
- frontend/src/pages/auth/ForgotPasswordPage.vue
- frontend/src/pages/auth/ResetPasswordPage.vue
- frontend/src/features/auth/composables/usePasswordReset.ts
- frontend/src/pages/auth/__tests__/ForgotPasswordPage.spec.ts
- frontend/src/pages/auth/__tests__/ResetPasswordPage.spec.ts

### Frontend Files Modified
- frontend/src/features/auth/services/authApi.ts
- frontend/src/features/auth/types.ts
- frontend/src/router/index.ts
- frontend/src/pages/auth/LoginPage.vue

## Dev Agent Record

### Agent Model Used
Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References
N/A

### Completion Notes List
- All 13 tasks completed successfully
- Backend tests: 51 passing (275 assertions)
- Frontend tests: 51 passing
- Custom ResetPasswordNotification created for French email content
- Password complexity validation: min 8 chars, 1 uppercase, 1 number
- Rate limiting applied: 5 requests/minute for forgot-password endpoint
- Frontend password requirements UI with real-time validation feedback
- Routes added with guest-only meta for proper auth redirects

