# Story 2.3: User Login

Status: done

## Story

As a **registered user**,
I want **to login with my email and password**,
So that **I can access my account and platform features**.

## Acceptance Criteria

1. **Given** I am on the login page, **When** I submit valid email and password, **Then** I receive a Sanctum authentication token **And** I am redirected to my role-specific dashboard (Face or Producer) **And** the token is stored securely in the frontend

2. **Given** I submit incorrect credentials, **When** the form is submitted, **Then** I see an error message "Email ou mot de passe incorrect" **And** I remain on the login page

3. **Given** I have exceeded 5 login attempts in 1 minute, **When** I try to login again, **Then** I see a rate limit message and must wait

**(FR5 - Un utilisateur enregistré peut se connecter avec email et mot de passe)**

## Tasks / Subtasks

### Backend Tasks

- [x] **Task 1: Create LoginRequest Form Request** (AC: #1, #2)
  - [x] 1.1 Create `app/Http/Requests/Auth/LoginRequest.php`
  - [x] 1.2 Define validation rules:
    - email: required, email
    - password: required, string
  - [x] 1.3 Add French error messages in `messages()` method

- [x] **Task 2: Create LoginService** (AC: #1, #2)
  - [x] 2.1 Create `app/Services/Auth/LoginService.php`
  - [x] 2.2 Implement `login(string $email, string $password): ?array` method
  - [x] 2.3 Use `Hash::check()` to validate credentials
  - [x] 2.4 Load user with userable relationship (Face or Producer)
  - [x] 2.5 Generate Sanctum token via `$user->createToken('auth-token')`
  - [x] 2.6 Return ['user' => User, 'token' => string] or null if failed

- [x] **Task 3: Create LoginController** (AC: #1, #2)
  - [x] 3.1 Create `app/Http/Controllers/Api/V1/Auth/LoginController.php`
  - [x] 3.2 Implement `__invoke(LoginRequest $request)` method
  - [x] 3.3 Inject LoginService
  - [x] 3.4 Return 200 with user data and token on success
  - [x] 3.5 Return 401 with "Email ou mot de passe incorrect" on failure

- [x] **Task 4: Register API Route** (AC: #1, #3)
  - [x] 4.1 Add route in `routes/api.php`: `POST /api/v1/auth/login`
  - [x] 4.2 Ensure route is public (no auth middleware)
  - [x] 4.3 Apply throttle:5,1 middleware for rate limiting (5 attempts per minute)

- [x] **Task 5: Backend Tests** (AC: #1, #2, #3)
  - [x] 5.1 Create `tests/Feature/Auth/LoginTest.php`
  - [x] 5.2 Test successful Face login returns 200 with token and user data
  - [x] 5.3 Test successful Producer login returns 200 with token and user data
  - [x] 5.4 Test invalid email returns 401
  - [x] 5.5 Test invalid password returns 401
  - [x] 5.6 Test non-existent user returns 401
  - [x] 5.7 Test rate limiting after 5 attempts returns 429
  - [x] 5.8 Test response includes user role (userable_type)

### Frontend Tasks

- [x] **Task 6: Create Login Types** (AC: #1)
  - [x] 6.1 Add `LoginForm` interface in `frontend/src/features/auth/types.ts`

- [x] **Task 7: Create Login Validation Schema** (AC: #1, #2)
  - [x] 7.1 Create `frontend/src/features/auth/schemas/login.ts`
  - [x] 7.2 Define Zod schema with French validation messages:
    - email: required, valid email format
    - password: required

- [x] **Task 8: Update authApi Service** (AC: #1, #2)
  - [x] 8.1 Add `login(data: LoginForm): Promise<LoginResponse>` method in `authApi.ts`
  - [x] 8.2 Call CSRF cookie endpoint before login
  - [x] 8.3 POST to `/auth/login`

- [x] **Task 9: Update useAuth Composable** (AC: #1)
  - [x] 9.1 Add `login(data: LoginForm): Promise<AuthResult>` method
  - [x] 9.2 Store token in authStore on success
  - [x] 9.3 Store user data in authStore on success
  - [x] 9.4 Return errors with user-friendly messages

- [x] **Task 10: Create LoginForm Component** (AC: #1, #2)
  - [x] 10.1 Create `frontend/src/features/auth/components/LoginForm.vue`
  - [x] 10.2 Implement form with VeeValidate + Zod schema
  - [x] 10.3 Include email and password fields
  - [x] 10.4 Add password visibility toggle
  - [x] 10.5 Display validation errors inline
  - [x] 10.6 Display API error message (401, 429) in alert
  - [x] 10.7 Show loading state during submission
  - [x] 10.8 Emit 'success' event on successful login

- [x] **Task 11: Create LoginPage** (AC: #1)
  - [x] 11.1 Create `frontend/src/pages/auth/LoginPage.vue`
  - [x] 11.2 Include LoginForm component
  - [x] 11.3 Add links to Face and Producer registration pages
  - [x] 11.4 Add link to forgot password page
  - [x] 11.5 Redirect to role-specific dashboard on success:
    - Face → /dashboard/face
    - Producer → /dashboard/producer

- [x] **Task 12: Add Login Route** (AC: #1)
  - [x] 12.1 Add route in `frontend/src/router/index.ts`: `/login`
  - [x] 12.2 Configure as public route (no auth guard)
  - [x] 12.3 Redirect authenticated users away from login page

- [x] **Task 13: Frontend Tests** (AC: #1, #2)
  - [x] 13.1 Create `frontend/src/features/auth/components/__tests__/LoginForm.spec.ts`
  - [x] 13.2 Test renders email and password fields
  - [x] 13.3 Test displays validation errors for empty fields
  - [x] 13.4 Test password visibility toggle works
  - [x] 13.5 Test loading state during submission
  - [x] 13.6 Test emits success event on successful login

## Technical Notes

### Backend Architecture
- Use existing UserResource with userable relationship loading
- LoginService should use `Auth::attempt()` for credential validation
- Rate limiting via Laravel's built-in throttle middleware (5 attempts/minute)
- Return 401 status for authentication failures (not 422)

### Frontend Architecture
- Reuse existing apiClient with CSRF handling
- Store token in localStorage via authStore
- Determine user role from `userable_type` field in response
- Dashboard redirects:
  - Face users → `/dashboard/face`
  - Producer users → `/dashboard/producer`

### API Response Format
```json
// Success (200)
{
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "userable_type": "App\\Models\\Face",
      "userable": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        // ... role-specific fields
      }
    },
    "token": "1|abc123..."
  },
  "message": "Connexion réussie",
  "meta": {}
}

// Error (401)
{
  "error": {
    "message": "Email ou mot de passe incorrect",
    "code": "AUTH_FAILED"
  }
}

// Rate Limited (429)
{
  "error": {
    "message": "Trop de tentatives. Veuillez réessayer dans quelques instants.",
    "code": "RATE_LIMITED"
  }
}
```

## Dev Notes

- Ensure CSRF cookie is fetched before login POST (same pattern as registration)
- The login endpoint should NOT return which field is wrong (email vs password) for security
- Consider adding "Remember me" functionality in a future iteration
