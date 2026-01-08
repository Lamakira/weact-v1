# Story 2.1: Face Registration

Status: done

## Story

As a **visitor**,
I want **to create a Face account with my name, username, email, and password**,
so that **I can access the platform as a talent seeking opportunities**.

## Acceptance Criteria

1. **Given** I am on the Face registration page, **When** I submit valid registration data (nom, prénom, username, email, password), **Then** a User record is created with userable_type = 'Face' **And** a Face record is created and linked to the User **And** I receive an authentication token **And** I am redirected to the Face dashboard **And** my password is hashed with bcrypt

2. **Given** I submit an email that already exists, **When** the form is submitted, **Then** I see an error message "Cet email est déjà utilisé"

3. **Given** I submit a password with less than 8 characters, **When** the form is submitted, **Then** I see an error message about password requirements (8+ chars, 1 uppercase, 1 number)

4. **Given** I submit a username that already exists, **When** the form is submitted, **Then** I see an error message "Ce nom d'utilisateur est déjà pris"

5. **Given** I leave required fields empty, **When** I try to submit, **Then** I see validation errors for each missing field

**(FR1 - Un visiteur peut créer un compte Face avec nom, prénom, username, email et mot de passe)**

## Tasks / Subtasks

### Backend Tasks

- [x] **Task 1: Create Face Model Enhancement** (AC: #1)
  - [x] 1.1 Add Face model fillable attributes if not present (nom, prenom, username, etc.)
  - [x] 1.2 Ensure Face belongs to User via polymorphic relationship
  - [x] 1.3 Add username unique index to faces table migration

- [x] **Task 2: Create RegisterFaceRequest Form Request** (AC: #1, #2, #3, #4, #5)
  - [x] 2.1 Create `app/Http/Requests/Auth/RegisterFaceRequest.php`
  - [x] 2.2 Define validation rules:
    - nom: required, string, max:255
    - prenom: required, string, max:255
    - username: required, string, max:50, unique:faces
    - email: required, email, unique:users
    - password: required, min:8, regex:/^(?=.*[A-Z])(?=.*\d)/, confirmed
  - [x] 2.3 Add French error messages in messages() method

- [x] **Task 3: Create FaceRegistrationService** (AC: #1)
  - [x] 3.1 Create `app/Services/Auth/FaceRegistrationService.php`
  - [x] 3.2 Implement `register(array $validated): array` method
  - [x] 3.3 Use DB transaction for atomic User + Face creation
  - [x] 3.4 Create User with userable_type = Face::class
  - [x] 3.5 Create Face record with nom, prenom, username
  - [x] 3.6 Link User to Face via userable morph
  - [x] 3.7 Generate Sanctum token via `$user->createToken('auth-token')`
  - [x] 3.8 Return ['user' => UserResource, 'token' => string]

- [x] **Task 4: Create RegisterFaceController** (AC: #1, #2, #3, #4)
  - [x] 4.1 Create `app/Http/Controllers/Api/V1/Auth/RegisterFaceController.php`
  - [x] 4.2 Implement `__invoke(RegisterFaceRequest $request)` method
  - [x] 4.3 Inject FaceRegistrationService
  - [x] 4.4 Return API envelope format with 201 status

- [x] **Task 5: Create UserResource & FaceResource** (AC: #1)
  - [x] 5.1 Create `app/Http/Resources/UserResource.php`
  - [x] 5.2 Create `app/Http/Resources/FaceResource.php`
  - [x] 5.3 Include userable data in UserResource when loaded

- [x] **Task 6: Register API Route** (AC: #1)
  - [x] 6.1 Add route in `routes/api.php`: `POST /api/v1/auth/register/face`
  - [x] 6.2 Ensure route is public (no auth middleware)
  - [x] 6.3 Apply throttle:5,1 middleware for rate limiting

- [x] **Task 7: Backend Tests** (AC: #1, #2, #3, #4, #5)
  - [x] 7.1 Create `tests/Feature/Auth/FaceRegistrationTest.php`
  - [x] 7.2 Test successful registration returns 201 with token
  - [x] 7.3 Test duplicate email returns 422 with error
  - [x] 7.4 Test duplicate username returns 422 with error
  - [x] 7.5 Test weak password returns 422 with requirements
  - [x] 7.6 Test missing fields return 422 with field errors
  - [x] 7.7 Test Face record is linked to User via polymorphic

### Frontend Tasks

- [x] **Task 8: Create Registration Types** (AC: #1)
  - [x] 8.1 Add types in `frontend/src/features/auth/types.ts`:
    ```typescript
    interface FaceRegistrationForm {
      nom: string;
      prenom: string;
      username: string;
      email: string;
      password: string;
      password_confirmation: string;
    }
    ```

- [x] **Task 9: Create Zod Validation Schema** (AC: #3, #5)
  - [x] 9.1 Create `frontend/src/features/auth/schemas/faceRegistration.ts`
  - [x] 9.2 Define Zod schema matching backend rules
  - [x] 9.3 Add French error messages

- [x] **Task 10: Create authApi Service** (AC: #1)
  - [x] 10.1 Create `frontend/src/features/auth/services/authApi.ts`
  - [x] 10.2 Implement `registerFace(data: FaceRegistrationForm): Promise<AuthResponse>`
  - [x] 10.3 POST to `/api/v1/auth/register/face`
  - [x] 10.4 Handle error envelope response

- [x] **Task 11: Update useAuth Composable** (AC: #1)
  - [x] 11.1 Add `registerFace(data: FaceRegistrationForm)` method
  - [x] 11.2 Store token in auth store on success
  - [x] 11.3 Set user data in auth store
  - [x] 11.4 Return success/error for component handling

- [x] **Task 12: Create FaceRegistrationForm Component** (AC: #1, #2, #3, #4, #5)
  - [x] 12.1 Create `frontend/src/features/auth/components/FaceRegistrationForm.vue`
  - [x] 12.2 Use VeeValidate with Zod schema
  - [x] 12.3 Include fields: nom, prenom, username, email, password, password_confirmation
  - [x] 12.4 Display inline validation errors
  - [x] 12.5 Display API errors (email taken, username taken)
  - [x] 12.6 Show loading state on submit button
  - [x] 12.7 Emit success event on registration

- [x] **Task 13: Create FaceRegistrationPage** (AC: #1)
  - [x] 13.1 Create `frontend/src/pages/auth/RegisterFacePage.vue`
  - [x] 13.2 Include FaceRegistrationForm component
  - [x] 13.3 Handle success: redirect to /face/dashboard
  - [x] 13.4 Include link to Producer registration
  - [x] 13.5 Include link to Login page

- [x] **Task 14: Add Route & Guard** (AC: #1)
  - [x] 14.1 Add route `/register/face` in router
  - [x] 14.2 Apply guest guard (redirect if already logged in)
  - [x] 14.3 Lazy load the page component

- [x] **Task 15: Frontend Tests** (AC: #1, #3, #5)
  - [x] 15.1 Create `FaceRegistrationForm.spec.ts`
  - [x] 15.2 Test form renders all fields
  - [x] 15.3 Test validation errors display
  - [x] 15.4 Test successful submission calls API
  - [x] 15.5 Test loading state during submission

## Dev Notes

### Architecture Compliance

**Polymorphic User Architecture (CRITICAL):**
```php
// User Model
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public function userable(): MorphTo
    {
        return $this->morphTo();
    }
}

// Face Model
class Face extends Model
{
    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'userable');
    }
}
```

**Registration Flow:**
1. Validate input via Form Request
2. Create Face record first (gets ID)
3. Create User with `userable_id = face.id`, `userable_type = Face::class`
4. Generate Sanctum token
5. Return wrapped response

### API Response Format (MANDATORY)

**Success (201):**
```json
{
  "data": {
    "user": {
      "id": 1,
      "email": "face@example.com",
      "userable_type": "Face",
      "userable": {
        "id": 1,
        "nom": "Doe",
        "prenom": "John",
        "username": "johndoe"
      }
    },
    "token": "1|abc123..."
  },
  "message": "Inscription réussie"
}
```

**Error (422):**
```json
{
  "error": {
    "code": "validation_error",
    "message": "Les données fournies ne sont pas valides",
    "details": {
      "email": ["Cet email est déjà utilisé"],
      "password": ["Le mot de passe doit contenir au moins 8 caractères, une majuscule et un chiffre"]
    }
  }
}
```

### Password Validation Regex

```php
// Backend (RegisterFaceRequest)
'password' => [
    'required',
    'string',
    'min:8',
    'regex:/^(?=.*[A-Z])(?=.*\d).+$/',
    'confirmed'
],
```

```typescript
// Frontend (Zod)
password: z.string()
  .min(8, 'Le mot de passe doit contenir au moins 8 caractères')
  .regex(/[A-Z]/, 'Le mot de passe doit contenir au moins une majuscule')
  .regex(/\d/, 'Le mot de passe doit contenir au moins un chiffre'),
```

### Rate Limiting

Apply `throttle:5,1` middleware (5 attempts per minute) to prevent abuse:
```php
Route::post('/auth/register/face', RegisterFaceController::class)
    ->middleware('throttle:5,1');
```

### Token Storage (Frontend)

Store token in Pinia auth store. For MVP, use localStorage (not ideal but acceptable):
```typescript
// useAuth composable
const storeToken = (token: string) => {
  localStorage.setItem('auth_token', token);
  authStore.setToken(token);
};
```

### Project Structure Notes

**Backend Files to Create:**
- `app/Http/Controllers/Api/V1/Auth/RegisterFaceController.php`
- `app/Http/Requests/Auth/RegisterFaceRequest.php`
- `app/Http/Resources/UserResource.php`
- `app/Http/Resources/FaceResource.php`
- `app/Services/Auth/FaceRegistrationService.php`
- `tests/Feature/Auth/FaceRegistrationTest.php`

**Frontend Files to Create:**
- `src/features/auth/types.ts`
- `src/features/auth/schemas/faceRegistration.ts`
- `src/features/auth/services/authApi.ts`
- `src/features/auth/composables/useAuth.ts`
- `src/features/auth/components/FaceRegistrationForm.vue`
- `src/pages/auth/RegisterFacePage.vue`
- `tests/unit/features/auth/FaceRegistrationForm.spec.ts`

### Existing Code References

**From Epic 1 implementations:**
- User model exists at `backend/app/Models/User.php` with polymorphic setup
- Face model exists at `backend/app/Models/Face.php`
- API routes base in `backend/routes/api.php`
- Frontend router base in `frontend/src/router/index.ts`

### References

- [Source: docs/planning-artifacts/architecture.md#Authentication & Security]
- [Source: docs/planning-artifacts/architecture.md#Naming Patterns]
- [Source: docs/planning-artifacts/architecture.md#API & Communication Patterns]
- [Source: _bmad-output/project-context.md#Critical Implementation Rules]
- [Source: _bmad-output/planning-artifacts/epics.md#Story 2.1: Face Registration]

## Dev Agent Record

### Agent Model Used

Claude 3.5 Sonnet (GitHub Copilot)

### Debug Log References

None - implementation completed without issues.

### Completion Notes List

- All backend tasks completed: Face model, RegisterFaceRequest, FaceRegistrationService, RegisterFaceController, UserResource, FaceResource
- API route registered at POST /api/v1/auth/register/face with throttle middleware
- Backend tests: 8 tests passing (successful registration, duplicate email/username, weak password, missing fields, polymorphic relationship, password hashing, password confirmation)
- All frontend tasks completed: types, Zod schema, authApi service, useAuth composable, FaceRegistrationForm component, RegisterFacePage
- Router configured with guest guard and lazy loading
- Frontend tests: 7 tests passing (form rendering, validation errors, password validation, autocomplete attributes)
- API response format follows project envelope standard

### File List

**Backend Files Created/Modified:**
- `backend/app/Models/Face.php` - Face model with polymorphic relationship
- `backend/app/Models/User.php` - User model with userable morph
- `backend/app/Http/Controllers/Api/V1/Auth/RegisterFaceController.php` - Registration controller
- `backend/app/Http/Requests/Auth/RegisterFaceRequest.php` - Form request with French validation messages
- `backend/app/Services/Auth/FaceRegistrationService.php` - Registration service with DB transaction
- `backend/app/Http/Resources/UserResource.php` - User API resource
- `backend/app/Http/Resources/FaceResource.php` - Face API resource
- `backend/routes/api.php` - API routes with throttle middleware
- `backend/tests/Feature/Auth/FaceRegistrationTest.php` - Feature tests

**Frontend Files Created/Modified:**
- `frontend/src/features/auth/types.ts` - TypeScript interfaces
- `frontend/src/features/auth/schemas/faceRegistration.ts` - Zod validation schema
- `frontend/src/features/auth/services/authApi.ts` - API service
- `frontend/src/features/auth/composables/useAuth.ts` - Auth composable
- `frontend/src/features/auth/components/FaceRegistrationForm.vue` - Registration form component
- `frontend/src/features/auth/components/__tests__/FaceRegistrationForm.spec.ts` - Component tests
- `frontend/src/pages/auth/RegisterFacePage.vue` - Registration page
- `frontend/src/stores/auth.ts` - Pinia auth store
- `frontend/src/services/apiClient.ts` - Axios API client
- `frontend/src/router/index.ts` - Vue Router with guards

