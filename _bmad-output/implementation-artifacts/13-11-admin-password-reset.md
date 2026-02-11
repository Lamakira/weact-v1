# Story 13.11: Admin Password Reset via Email

Status: done

## Story

As an **admin who forgot my password**,
I want **to reset my password via a secure email link**,
so that **I can regain access to my admin account without requiring another admin to set my password**.

**(FR-NEW)** — Un administrateur peut reinitialiser son mot de passe via email, comme les utilisateurs classiques mais sur un flux dedié.

## Context

Currently there is no password recovery flow for admin accounts. If an admin (or superadmin) loses their password, there is no way to regain access. This story adds a self-service password reset flow for the `admins` table, mirroring the existing user flow (Story 2.5) but using a **separate password broker** to keep admin and user auth completely isolated.

Additionally, a superadmin should be able to trigger a password reset email for any admin from the admin management UI, without ever seeing or setting the password.

## Acceptance Criteria

1. **AC1 — Forgot Password Endpoint**: Given I am on the admin forgot-password page, When I submit a registered admin email to `POST /api/v1/admin/forgot-password`, Then a password reset email is sent with a secure token and I see a confirmation message "Email de reinitialisation envoyé".

2. **AC2 — Reset Password Endpoint**: Given I click the reset link in my email, When I submit a new valid password to `POST /api/v1/admin/reset-password`, Then my admin password is updated and I am redirected to the admin login page with a success message.

3. **AC3 — Invalid/Expired Token**: Given I try to use an expired or invalid reset token, When I submit a new password, Then I see an error message "Lien expire ou invalide".

4. **AC4 — Separate Password Broker**: The admin password reset uses a dedicated `admins` password broker configured in `config/auth.php`, completely isolated from user password resets. Tokens are stored in a separate `admin_password_reset_tokens` table.

5. **AC5 — Frontend Forgot Password Page**: Given I am on `/admin/login`, When I click "Mot de passe oublie ?", Then I am navigated to `/admin/forgot-password` where I can submit my email. On success, a `toast.success("Email de reinitialisation envoye")` is shown. On error, a `toast.error()` with the API error message is shown.

6. **AC6 — Frontend Reset Password Page**: Given I click the reset link from the email, When I land on `/admin/reset-password/:token?email=...`, Then I can submit a new password with confirmation. On success, a `toast.success("Mot de passe mis a jour avec succes")` is shown and I am redirected to admin login. On invalid/expired token, a `toast.error("Lien expire ou invalide")` is shown.

7. **AC7 — SuperAdmin Trigger Reset**: Given I am a superadmin on the admin edit page, When I click "Envoyer un lien de reinitialisation", Then a password reset email is sent to that admin's email address. A `toast.success("Lien de reinitialisation envoye a {email}")` confirms the action. On error, a `toast.error()` with the API error message is shown. I never see or set their password.

8. **AC8 — Rate Limiting**: The forgot-password endpoint is rate-limited to prevent abuse (throttle:5,1 — same as login).

9. **AC9 — Backend Tests**: Feature tests cover: forgot-password sends email, reset-password updates password, expired token returns 422, invalid email returns 422, rate limiting, admin can login with new password after reset, superadmin trigger reset sends email.

10. **AC10 — Frontend Tests**: Unit tests for composable: forgot-password API call, reset-password API call, error handling for both flows.

## Tasks / Subtasks

### Backend — Database & Configuration

- [x] Task 1 — Create `admin_password_reset_tokens` migration (AC: 4)
  - [x] 1.1 — Create migration: `php artisan make:migration create_admin_password_reset_tokens_table`
  - [x] 1.2 — Schema: `email` (string, index), `token` (string), `created_at` (nullable timestamp) — same structure as `password_reset_tokens`
  - [x] 1.3 — Run migration and verify table exists

- [x] Task 2 — Configure admin password broker in `config/auth.php` (AC: 4)
  - [x] 2.1 — Add `admins` provider: `'admins' => ['driver' => 'eloquent', 'model' => Admin::class]`
  - [x] 2.2 — Add `admins` password broker: `'admins' => ['provider' => 'admins', 'table' => 'admin_password_reset_tokens', 'expire' => 60, 'throttle' => 60]`

- [x] Task 3 — Make Admin model compatible with password resets (AC: 1, 2)
  - [x] 3.1 — Ensure `Admin` model implements `CanResetPassword` (via `Notifiable` trait + `CanResetPassword` trait if not already present)
  - [x] 3.2 — Verify `Admin` has `email` field and `getEmailForPasswordReset()` works

### Backend — Controllers & Requests

- [x] Task 4 — Create `AdminForgotPasswordController` (AC: 1, 8)
  - [x] 4.1 — Create `app/Http/Controllers/Api/V1/Admin/AdminForgotPasswordController.php`
  - [x] 4.2 — Implement `__invoke(AdminForgotPasswordRequest $request)` using `Password::broker('admins')->sendResetLink()`
  - [x] 4.3 — Return 200 with message "Email de reinitialisation envoyé" on success
  - [x] 4.4 — Return 422 with appropriate error on failure

- [x] Task 5 — Create `AdminForgotPasswordRequest` (AC: 1)
  - [x] 5.1 — Create `app/Http/Requests/Admin/AdminForgotPasswordRequest.php`
  - [x] 5.2 — Validate `email`: required, email format, exists in `admins` table

- [x] Task 6 — Create `AdminResetPasswordController` (AC: 2, 3)
  - [x] 6.1 — Create `app/Http/Controllers/Api/V1/Admin/AdminResetPasswordController.php`
  - [x] 6.2 — Implement `__invoke(AdminResetPasswordRequest $request)` using `Password::broker('admins')->reset()`
  - [x] 6.3 — Hash new password, save admin, fire `PasswordReset` event
  - [x] 6.4 — Return 200 with success message on valid token
  - [x] 6.5 — Return 422 with "Lien expire ou invalide" on invalid/expired token

- [x] Task 7 — Create `AdminResetPasswordRequest` (AC: 2)
  - [x] 7.1 — Create `app/Http/Requests/Admin/AdminResetPasswordRequest.php`
  - [x] 7.2 — Validate `token` (required string), `email` (required, email), `password` (required, min:8, confirmed)

- [x] Task 8 — Add SuperAdmin trigger reset endpoint (AC: 7)
  - [x] 8.1 — Add method `sendPasswordReset(Admin $admin)` to `AdminController`
  - [x] 8.2 — Use `Password::broker('admins')->sendResetLink(['email' => $admin->email])`
  - [x] 8.3 — Wrap in superadmin middleware (existing)
  - [x] 8.4 — Return 200 with message "Lien de reinitialisation envoyé à {email}"

### Backend — Routes

- [x] Task 9 — Register admin password reset routes (AC: 1, 2, 7, 8)
  - [x] 9.1 — In `backend/routes/api/admin.php`, add public routes (no auth):
    - `POST /v1/admin/forgot-password` → `AdminForgotPasswordController` (throttle:5,1)
    - `POST /v1/admin/reset-password` → `AdminResetPasswordController` (throttle:5,1)
  - [x] 9.2 — In superadmin group, add:
    - `POST /v1/admin/admins/{admin}/send-reset-link` → `AdminController::sendPasswordReset` (throttle:30,1)

### Backend — Email Notification

- [x] Task 10 — Create admin-specific reset password notification (AC: 1)
  - [x] 10.1 — Create `app/Notifications/AdminResetPasswordNotification.php`
  - [x] 10.2 — Override `toMail()` to generate link pointing to `/admin/reset-password/{token}?email={email}` (admin frontend route, not user route)
  - [x] 10.3 — Override `sendPasswordResetNotification($token)` in Admin model to use this notification

### Backend — Feature Tests

- [x] Task 11 — Write backend feature tests (AC: 9)
  - [x] 11.1 — Create `backend/tests/Feature/Admin/AdminPasswordResetTest.php`
  - [x] 11.2 — `test_admin_forgot_password_sends_email` — submit valid admin email, assert Notification sent
  - [x] 11.3 — `test_admin_forgot_password_invalid_email_returns_422` — non-existent email
  - [x] 11.4 — `test_admin_reset_password_with_valid_token` — reset succeeds, can login with new password
  - [x] 11.5 — `test_admin_reset_password_with_invalid_token_returns_422`
  - [x] 11.6 — `test_admin_reset_password_validation` — missing fields, short password, mismatch confirmation
  - [x] 11.7 — `test_superadmin_can_trigger_reset_for_admin` — sends email notification
  - [x] 11.8 — `test_non_superadmin_cannot_trigger_reset` — admin/editor gets 403
  - [x] 11.9 — `test_forgot_password_is_rate_limited`

### Frontend — API & Composable

- [x] Task 12 — Add API methods for admin password reset (AC: 5, 6)
  - [x] 12.1 — In `adminAuthApi.ts` (or new `adminPasswordResetApi.ts`), add `forgotPassword(email: string)` → `POST /admin/forgot-password`
  - [x] 12.2 — Add `resetPassword(data: { token, email, password, password_confirmation })` → `POST /admin/reset-password`

- [x] Task 13 — Create `useAdminPasswordReset` composable (AC: 5, 6)
  - [x] 13.1 — Expose `forgotPassword(email)` and `resetPassword(data)` with loading/error/success state
  - [x] 13.2 — Follow existing `useAdminAuth` composable patterns

- [x] Task 14 — Add SuperAdmin trigger to admin API (AC: 7)
  - [x] 14.1 — In `adminApi.ts`, add `sendPasswordResetLink(adminId: number)` → `POST /admin/admins/${id}/send-reset-link`

### Frontend — Pages

- [x] Task 15 — Create `AdminForgotPasswordPage.vue` (AC: 5)
  - [x] 15.1 — Route: `/admin/forgot-password` (admin guest route)
  - [x] 15.2 — Form with email field, submit button
  - [x] 15.3 — On success: `toast.success("Email de reinitialisation envoye")` + reset form
  - [x] 15.4 — On error: `toast.error()` with API error message
  - [x] 15.5 — Same visual style as `AdminLoginPage.vue` (centered card, logo, Shield icon)
  - [x] 15.6 — Link back to admin login

- [x] Task 16 — Create `AdminResetPasswordPage.vue` (AC: 6)
  - [x] 16.1 — Route: `/admin/reset-password/:token` with `email` query param
  - [x] 16.2 — Form with password + password_confirmation fields
  - [x] 16.3 — On success: `toast.success("Mot de passe mis a jour avec succes")` + redirect to admin login
  - [x] 16.4 — On invalid/expired token: `toast.error("Lien expire ou invalide")` + link back to forgot-password

- [x] Task 17 — Add "Mot de passe oublie ?" link to `AdminLoginPage.vue` (AC: 5)
  - [x] 17.1 — Add `RouterLink` to `/admin/forgot-password` below the login form

- [x] Task 18 — Add "Envoyer un lien de reinitialisation" button to admin edit page (AC: 7)
  - [x] 18.1 — In `AdminEditPage.vue`, add button visible to superadmin only
  - [x] 18.2 — On success: `toast.success("Lien de reinitialisation envoye a {email}")`
  - [x] 18.3 — On error: `toast.error()` with API error message

### Frontend — Router

- [x] Task 19 — Register new admin routes (AC: 5, 6)
  - [x] 19.1 — Add `/admin/forgot-password` route (adminGuest meta)
  - [x] 19.2 — Add `/admin/reset-password/:token` route (no auth required — token is the auth)

### Frontend — Tests

- [x] Task 20 — Write frontend composable tests (AC: 10)
  - [x] 20.1 — Test `forgotPassword` calls API correctly, handles success/error
  - [x] 20.2 — Test `resetPassword` calls API correctly, handles success/error/validation

### Completion

- [x] Task 21 — TypeScript build check (`npm run build` clean)
- [x] Task 22 — All backend tests pass (no regressions)

## Dev Notes

### Architecture Decisions

1. **Separate password broker**: Admin password resets MUST use a dedicated `admins` broker, not the default `users` broker. This prevents cross-contamination (a user email could theoretically match an admin email on a different table).

2. **Separate tokens table**: `admin_password_reset_tokens` keeps admin tokens isolated from `password_reset_tokens` (user tokens).

3. **Custom notification**: The reset link must point to `/admin/reset-password/{token}` (admin frontend), not `/reset-password/{token}` (user frontend). Override `sendPasswordResetNotification()` on the Admin model.

4. **SuperAdmin trigger**: The "send reset link" feature is a convenience — it uses the exact same `Password::broker('admins')->sendResetLink()` mechanism. The superadmin never sets or sees the password.

5. **No force-set-password**: Deliberately excluded. The most secure pattern is that only the account owner sets their own password via a time-limited, single-use email token.

### Existing Patterns to Follow

- **User password reset** (Story 2.5): `ForgotPasswordController`, `ResetPasswordController`, `Password::sendResetLink()`, `Password::reset()` — mirror this exactly but with `Password::broker('admins')`.
- **Admin login page styling**: `AdminLoginPage.vue` — centered card, WEACT logo, Shield icon, same form component patterns.
- **Admin auth API pattern**: `adminAuthApi.ts` with `adminApiClient` (or public axios for unauthenticated endpoints).
- **Rate limiting**: `throttle:5,1` for password reset endpoints (same as login).
- **Toast notifications**: Use `useToast` composable from `@/composables/useToast` (wraps `vue-toastification`). Call `toast.success(msg)` for success feedback and `toast.error(msg)` for errors. No inline success/error divs — all transient feedback goes through toasts.

### Key Files

**Backend (new):**
- `backend/database/migrations/xxxx_create_admin_password_reset_tokens_table.php`
- `backend/app/Http/Controllers/Api/V1/Admin/AdminForgotPasswordController.php`
- `backend/app/Http/Controllers/Api/V1/Admin/AdminResetPasswordController.php`
- `backend/app/Http/Requests/Admin/AdminForgotPasswordRequest.php`
- `backend/app/Http/Requests/Admin/AdminResetPasswordRequest.php`
- `backend/app/Notifications/AdminResetPasswordNotification.php`
- `backend/tests/Feature/Admin/AdminPasswordResetTest.php`

**Backend (modified):**
- `backend/config/auth.php` — add admins provider + password broker
- `backend/app/Models/Admin.php` — add CanResetPassword trait + override sendPasswordResetNotification
- `backend/routes/api/admin.php` — add forgot-password, reset-password, send-reset-link routes

**Frontend (new):**
- `frontend/src/pages/admin/AdminForgotPasswordPage.vue`
- `frontend/src/pages/admin/AdminResetPasswordPage.vue`
- `frontend/src/features/admin/composables/useAdminPasswordReset.ts`

**Frontend (modified):**
- `frontend/src/features/admin/services/adminAuthApi.ts` — add forgotPassword + resetPassword methods
- `frontend/src/pages/admin/AdminLoginPage.vue` — add "Mot de passe oublie ?" link
- `frontend/src/pages/admin/AdminEditPage.vue` — add "send reset link" button (superadmin only)
- `frontend/src/router/index.ts` — add 2 new admin routes

### References

- [Source: _bmad-output/implementation-artifacts/2-5-password-reset.md — user password reset story pattern]
- [Source: backend/app/Http/Controllers/Api/V1/Auth/ForgotPasswordController.php — user forgot password controller]
- [Source: backend/app/Http/Controllers/Api/V1/Auth/ResetPasswordController.php — user reset password controller]
- [Source: backend/config/auth.php — auth guards, providers, password brokers config]
- [Source: backend/app/Models/Admin.php — admin model with Notifiable trait]
- [Source: frontend/src/pages/admin/AdminLoginPage.vue — admin login page styling reference]
- [Source: frontend/src/features/admin/services/adminAuthApi.ts — admin API service pattern]

---

## File List

### New Files
- `backend/database/migrations/2026_02_11_135347_create_admin_password_reset_tokens_table.php`
- `backend/app/Notifications/AdminResetPasswordNotification.php`
- `backend/app/Http/Controllers/Api/V1/Admin/AdminForgotPasswordController.php`
- `backend/app/Http/Controllers/Api/V1/Admin/AdminResetPasswordController.php`
- `backend/app/Http/Requests/Admin/AdminForgotPasswordRequest.php`
- `backend/app/Http/Requests/Admin/AdminResetPasswordRequest.php`
- `backend/tests/Feature/Admin/AdminPasswordResetTest.php`
- `frontend/src/features/admin/composables/useAdminPasswordReset.ts`
- `frontend/src/features/admin/composables/__tests__/useAdminPasswordReset.spec.ts`
- `frontend/src/pages/admin/AdminForgotPasswordPage.vue`
- `frontend/src/pages/admin/AdminResetPasswordPage.vue`

### Modified Files
- `backend/config/auth.php` — added `admins` password broker
- `backend/app/Models/Admin.php` — added CanResetPassword trait + sendPasswordResetNotification override
- `backend/app/Http/Controllers/Api/V1/Admin/AdminController.php` — added sendPasswordReset method
- `backend/routes/api/admin.php` — added forgot-password, reset-password, send-reset-link routes
- `frontend/src/features/admin/services/adminAuthApi.ts` — added forgotPassword + resetPassword methods + AdminResetPasswordForm interface
- `frontend/src/features/admin/services/adminsApi.ts` — added sendPasswordResetLink method
- `frontend/src/pages/admin/AdminLoginPage.vue` — added "Mot de passe oublié ?" link
- `frontend/src/pages/admin/AdminEditPage.vue` — added send reset link button (superadmin only)
- `frontend/src/router/index.ts` — added forgot-password + reset-password admin routes

## Dev Agent Record

- All 22 tasks implemented and verified
- 15 backend feature tests pass (AdminPasswordResetTest)
- 10 frontend composable tests pass (useAdminPasswordReset.spec.ts)
- TypeScript build check: clean (vue-tsc --noEmit)
- Full backend test suite: 1434 passed, 0 failed
- OWASP anti-enumeration: forgot-password always returns 200 regardless of email existence
- Rate limiting: throttle:5,1 on public endpoints, throttle:30,1 on superadmin trigger
- Admin password broker fully isolated from user password broker (separate table, separate config)

## Change Log

| Change | Files | Reason |
|--------|-------|--------|
| Created admin_password_reset_tokens migration | migration file | AC4 — isolated token storage |
| Configured admins password broker | config/auth.php | AC4 — separate broker |
| Added CanResetPassword to Admin model | Admin.php | AC1, AC2 — enable password reset |
| Created AdminResetPasswordNotification | notification file | AC1 — admin-specific reset URL |
| Created AdminForgotPasswordController | controller + request | AC1, AC8 — forgot-password endpoint |
| Created AdminResetPasswordController | controller + request | AC2, AC3 — reset-password endpoint |
| Added sendPasswordReset to AdminController | AdminController.php | AC7 — superadmin trigger |
| Registered password reset routes | admin.php routes | AC1, AC2, AC7, AC8 |
| Created useAdminPasswordReset composable | composable file | AC5, AC6 — frontend state management |
| Added API methods to adminAuthApi | adminAuthApi.ts | AC5, AC6 — API integration |
| Added sendPasswordResetLink to adminsApi | adminsApi.ts | AC7 — superadmin API |
| Created AdminForgotPasswordPage | Vue page | AC5 — forgot-password UI |
| Created AdminResetPasswordPage | Vue page | AC6 — reset-password UI |
| Added forgot-password link to login | AdminLoginPage.vue | AC5 — navigation |
| Added reset link button to edit page | AdminEditPage.vue | AC7 — superadmin trigger UI |
| Registered new admin routes | router/index.ts | AC5, AC6 — frontend routing |
| Backend tests (15 tests) | AdminPasswordResetTest.php | AC9 — full coverage |
| Frontend tests (10 tests) | useAdminPasswordReset.spec.ts | AC10 — composable tests |
| [Review] Added adminGuest meta to reset-password route | router/index.ts | Consistency — redirect authenticated admins away from reset page |
| [Review] Removed inline success div from forgot-password page | AdminForgotPasswordPage.vue | Toast-only pattern compliance |

## Senior Developer Review (AI)

**Reviewer:** Lamakira on 2026-02-11
**Outcome:** Approved with fixes applied

**Issues Found:** 1 HIGH (documented debt, not blocked), 2 MEDIUM (fixed), 3 LOW (accepted)

**Fixes Applied:**
1. Added `adminGuest: true` to reset-password route meta for consistency
2. Removed inline success div from `AdminForgotPasswordPage.vue` to comply with toast-only feedback pattern, removed orphaned `emailSent` ref

**Accepted (not fixed):**
- HIGH #1: Business logic in controllers instead of Services — known architectural debt, consistent with existing user password reset pattern. Not a blocker.
- MEDIUM #4: Missing `string` type hint on `sendPasswordResetNotification($token)` — cannot add due to PHP covariance rules (parent class doesn't type-hint). Framework constraint.
- LOW #6: Test naming could be more descriptive — cosmetic.
- LOW #7: Missing friendly error for empty token/email on reset page — edge case, low priority.

**OWASP Security:** All checks passed (A01, A02, A03, A04, A05, A07).
