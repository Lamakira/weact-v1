# Story 2.2: Producer Registration

Status: done

## Story

As a **visitor**,
I want **to create a Producer account choosing Agency or Individual type**,
so that **I can access the platform to find talents for my projects**.

## Acceptance Criteria

1. **Given** I am on the Producer registration page, **When** I select "Agence" and submit email, password, and agency name, **Then** a User record is created with userable_type = 'Producer' **And** a Producer record is created with type = 'agency' and agency_name populated **And** I receive an authentication token **And** I am redirected to the Producer dashboard

2. **Given** I select "Particulier" and submit email, password, first name, and last name, **When** the form is submitted, **Then** a Producer record is created with type = 'particulier' and first_name/last_name populated **And** I receive an authentication token

3. **Given** I try to register with an email already used by a Face or another Producer, **When** the form is submitted, **Then** I see an error message "Cet email est déjà utilisé"

4. **Given** I submit a password with less than 8 characters or missing uppercase/number, **When** the form is submitted, **Then** I see an error message about password requirements (8+ chars, 1 uppercase, 1 number)

5. **Given** I select "Agence" but leave agency name empty, **When** I try to submit, **Then** I see a validation error for the agency name field

6. **Given** I select "Particulier" but leave first name or last name empty, **When** I try to submit, **Then** I see validation errors for the missing fields

**(FR2 - Un visiteur peut créer un compte Producteur avec email, mot de passe et catégorie)**
**(FR3 - Un Producteur Agence peut renseigner le nom de son agence lors de l'inscription)**
**(FR4 - Un Producteur Particulier peut renseigner ses noms et prénoms lors de l'inscription)**

## Tasks / Subtasks

### Backend Tasks

- [x] **Task 1: Update Producer Model** (AC: #1, #2)
  - [x] 1.1 Ensure Producer model has fillable attributes: type, agency_name, first_name, last_name
  - [x] 1.2 Verify Producer belongs to User via polymorphic relationship (already from Epic 1)
  - [x] 1.3 Create ProducerType PHP enum: `agency`, `particulier`

- [x] **Task 2: Create RegisterProducerRequest Form Request** (AC: #1, #2, #3, #4, #5, #6)
  - [x] 2.1 Create `app/Http/Requests/Auth/RegisterProducerRequest.php`
  - [x] 2.2 Define base validation rules:
    - type: required, in:agency,particulier
    - email: required, email, unique:users
    - password: required, min:8, regex:/^(?=.*[A-Z])(?=.*\d)/, confirmed
  - [x] 2.3 Add conditional validation in `withValidator()`:
    - If type = 'agency': agency_name required, string, max:255
    - If type = 'particulier': first_name required, string, max:255; last_name required, string, max:255
  - [x] 2.4 Add French error messages in `messages()` method

- [x] **Task 3: Create ProducerRegistrationService** (AC: #1, #2)
  - [x] 3.1 Create `app/Services/Auth/ProducerRegistrationService.php`
  - [x] 3.2 Implement `register(array $validated): array` method
  - [x] 3.3 Use DB transaction for atomic User + Producer creation
  - [x] 3.4 Create Producer with type-specific fields:
    - Agency: type='agency', agency_name
    - Particulier: type='particulier', first_name, last_name
  - [x] 3.5 Create User with userable_type = Producer::class, link via userable morph
  - [x] 3.6 Generate Sanctum token via `$user->createToken('auth-token')`
  - [x] 3.7 Return ['user' => UserResource, 'token' => string]

- [x] **Task 4: Create RegisterProducerController** (AC: #1, #2, #3, #4)
  - [x] 4.1 Create `app/Http/Controllers/Api/V1/Auth/RegisterProducerController.php`
  - [x] 4.2 Implement `__invoke(RegisterProducerRequest $request)` method
  - [x] 4.3 Inject ProducerRegistrationService
  - [x] 4.4 Return API envelope format with 201 status

- [x] **Task 5: Create ProducerResource** (AC: #1, #2)
  - [x] 5.1 Create `app/Http/Resources/ProducerResource.php`
  - [x] 5.2 Include fields: id, type, agency_name (if agency), first_name/last_name (if particulier), display_name computed
  - [x] 5.3 Update UserResource to include producer data when userable_type is Producer

- [x] **Task 6: Register API Route** (AC: #1)
  - [x] 6.1 Add route in `routes/api.php`: `POST /api/v1/auth/register/producer`
  - [x] 6.2 Ensure route is public (no auth middleware)
  - [x] 6.3 Apply throttle:5,1 middleware for rate limiting

- [x] **Task 7: Backend Tests** (AC: #1, #2, #3, #4, #5, #6)
  - [x] 7.1 Create `tests/Feature/Auth/ProducerRegistrationTest.php`
  - [x] 7.2 Test successful Agency registration returns 201 with token
  - [x] 7.3 Test successful Particulier registration returns 201 with token
  - [x] 7.4 Test duplicate email returns 422 with error
  - [x] 7.5 Test weak password returns 422 with requirements
  - [x] 7.6 Test Agency without agency_name returns 422
  - [x] 7.7 Test Particulier without first_name/last_name returns 422
  - [x] 7.8 Test Producer record is linked to User via polymorphic

### Frontend Tasks

- [x] **Task 8: Create Registration Types** (AC: #1, #2)
  - [x] 8.1 Add types in `frontend/src/features/auth/types.ts`:
    ```typescript
    type ProducerType = 'agency' | 'particulier';

    interface ProducerRegistrationFormBase {
      type: ProducerType;
      email: string;
      password: string;
      password_confirmation: string;
    }

    interface AgencyRegistrationForm extends ProducerRegistrationFormBase {
      type: 'agency';
      agency_name: string;
    }

    interface ParticulierRegistrationForm extends ProducerRegistrationFormBase {
      type: 'particulier';
      first_name: string;
      last_name: string;
    }

    type ProducerRegistrationForm = AgencyRegistrationForm | ParticulierRegistrationForm;
    ```

- [x] **Task 9: Create Zod Validation Schema** (AC: #4, #5, #6)
  - [x] 9.1 Create `frontend/src/features/auth/schemas/producerRegistration.ts`
  - [x] 9.2 Define discriminated union Zod schema with type field
  - [x] 9.3 Agency schema: type='agency', email, password, password_confirmation, agency_name required
  - [x] 9.4 Particulier schema: type='particulier', email, password, password_confirmation, first_name, last_name required
  - [x] 9.5 Add French error messages matching backend

- [x] **Task 10: Update authApi Service** (AC: #1, #2)
  - [x] 10.1 Add to `frontend/src/features/auth/services/authApi.ts`:
    ```typescript
    registerProducer(data: ProducerRegistrationForm): Promise<AuthResponse>
    ```
  - [x] 10.2 POST to `/api/v1/auth/register/producer`
  - [x] 10.3 Handle error envelope response

- [x] **Task 11: Update useAuth Composable** (AC: #1, #2)
  - [x] 11.1 Add `registerProducer(data: ProducerRegistrationForm)` method
  - [x] 11.2 Store token in auth store on success
  - [x] 11.3 Set user data in auth store (with producer role)
  - [x] 11.4 Return success/error for component handling

- [x] **Task 12: Create ProducerRegistrationForm Component** (AC: #1, #2, #3, #4, #5, #6)
  - [x] 12.1 Create `frontend/src/features/auth/components/ProducerRegistrationForm.vue`
  - [x] 12.2 Use VeeValidate with Zod discriminated union schema
  - [x] 12.3 Include type selector (toggle or radio: Agence / Particulier)
  - [x] 12.4 Conditionally render fields based on type:
    - Agency: agency_name field
    - Particulier: first_name, last_name fields
  - [x] 12.5 Common fields: email, password, password_confirmation
  - [x] 12.6 Display inline validation errors
  - [x] 12.7 Display API errors (email taken, etc.)
  - [x] 12.8 Show loading state on submit button
  - [x] 12.9 Emit success event on registration

- [x] **Task 13: Create ProducerRegistrationPage** (AC: #1, #2)
  - [x] 13.1 Create `frontend/src/pages/auth/RegisterProducerPage.vue`
  - [x] 13.2 Include ProducerRegistrationForm component
  - [x] 13.3 Handle success: redirect to /producer/dashboard
  - [x] 13.4 Include link to Face registration
  - [x] 13.5 Include link to Login page

- [x] **Task 14: Add Route & Guard** (AC: #1)
  - [x] 14.1 Add route `/register/producer` in router
  - [x] 14.2 Apply guest guard (redirect if already logged in)
  - [x] 14.3 Lazy load the page component

- [x] **Task 15: Frontend Tests** (AC: #1, #4, #5, #6)
  - [x] 15.1 Create `ProducerRegistrationForm.spec.ts`
  - [x] 15.2 Test form renders type selector
  - [x] 15.3 Test Agency fields shown when type='agency'
  - [x] 15.4 Test Particulier fields shown when type='particulier'
  - [x] 15.5 Test validation errors display for each type
  - [x] 15.6 Test successful submission calls API
  - [x] 15.7 Test loading state during submission

## Dev Notes

### Architecture Compliance

**Polymorphic User Architecture (CRITICAL):**
```php
// Producer Model (already exists from Epic 1)
class Producer extends Model
{
    protected $fillable = ['type', 'agency_name', 'first_name', 'last_name'];

    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'userable');
    }

    // Computed display name
    public function getDisplayNameAttribute(): string
    {
        return $this->type === 'agency'
            ? $this->agency_name
            : "{$this->first_name} {$this->last_name}";
    }
}
```

**ProducerType Enum:**
```php
// app/Enums/ProducerType.php
declare(strict_types=1);

namespace App\Enums;

enum ProducerType: string
{
    case Agency = 'agency';
    case Particulier = 'particulier';
}
```

**Registration Flow (mirror Face registration pattern):**
1. Validate input via Form Request (with conditional rules)
2. Create Producer record first (gets ID)
3. Create User with `userable_id = producer.id`, `userable_type = Producer::class`
4. Generate Sanctum token
5. Return wrapped response

### API Response Format (MANDATORY)

**Success (201) - Agency:**
```json
{
  "data": {
    "user": {
      "id": 2,
      "email": "agency@example.com",
      "userable_type": "Producer",
      "userable": {
        "id": 1,
        "type": "agency",
        "agency_name": "Studio Pro",
        "display_name": "Studio Pro"
      }
    },
    "token": "2|xyz789..."
  },
  "message": "Inscription réussie"
}
```

**Success (201) - Particulier:**
```json
{
  "data": {
    "user": {
      "id": 3,
      "email": "producer@example.com",
      "userable_type": "Producer",
      "userable": {
        "id": 2,
        "type": "particulier",
        "first_name": "Jean",
        "last_name": "Dupont",
        "display_name": "Jean Dupont"
      }
    },
    "token": "3|abc456..."
  },
  "message": "Inscription réussie"
}
```

**Error (422) - Validation:**
```json
{
  "error": {
    "code": "validation_error",
    "message": "Les données fournies ne sont pas valides",
    "details": {
      "agency_name": ["Le nom de l'agence est obligatoire pour ce type de compte"]
    }
  }
}
```

### Conditional Validation Pattern (Backend)

```php
// RegisterProducerRequest.php
public function rules(): array
{
    return [
        'type' => ['required', 'string', 'in:agency,particulier'],
        'email' => ['required', 'email', 'unique:users,email'],
        'password' => [
            'required',
            'string',
            'min:8',
            'regex:/^(?=.*[A-Z])(?=.*\d).+$/',
            'confirmed'
        ],
        'agency_name' => ['required_if:type,agency', 'nullable', 'string', 'max:255'],
        'first_name' => ['required_if:type,particulier', 'nullable', 'string', 'max:255'],
        'last_name' => ['required_if:type,particulier', 'nullable', 'string', 'max:255'],
    ];
}

public function messages(): array
{
    return [
        'type.required' => 'Veuillez choisir un type de compte',
        'type.in' => 'Type de compte invalide',
        'email.unique' => 'Cet email est déjà utilisé',
        'password.min' => 'Le mot de passe doit contenir au moins 8 caractères',
        'password.regex' => 'Le mot de passe doit contenir au moins une majuscule et un chiffre',
        'agency_name.required_if' => 'Le nom de l\'agence est obligatoire',
        'first_name.required_if' => 'Le prénom est obligatoire',
        'last_name.required_if' => 'Le nom est obligatoire',
    ];
}
```

### Discriminated Union Schema (Frontend)

```typescript
// producerRegistration.ts
import { z } from 'zod';

const baseSchema = z.object({
  email: z.string()
    .email('Adresse email invalide'),
  password: z.string()
    .min(8, 'Le mot de passe doit contenir au moins 8 caractères')
    .regex(/[A-Z]/, 'Le mot de passe doit contenir au moins une majuscule')
    .regex(/\d/, 'Le mot de passe doit contenir au moins un chiffre'),
  password_confirmation: z.string(),
}).refine((data) => data.password === data.password_confirmation, {
  message: 'Les mots de passe ne correspondent pas',
  path: ['password_confirmation'],
});

const agencySchema = z.object({
  type: z.literal('agency'),
  agency_name: z.string().min(1, 'Le nom de l\'agence est obligatoire'),
}).merge(baseSchema);

const particulierSchema = z.object({
  type: z.literal('particulier'),
  first_name: z.string().min(1, 'Le prénom est obligatoire'),
  last_name: z.string().min(1, 'Le nom est obligatoire'),
}).merge(baseSchema);

export const producerRegistrationSchema = z.discriminatedUnion('type', [
  agencySchema,
  particulierSchema,
]);
```

### Rate Limiting

Apply `throttle:5,1` middleware (same as Face registration):
```php
Route::post('/auth/register/producer', RegisterProducerController::class)
    ->middleware('throttle:5,1');
```

### UI Design Specifications (FROM DESIGN SCREENSHOT)

**CRITICAL: Layout Structure (Split Screen)**
```
┌─────────────────────────────────────────────────────────────────────┐
│  LEFT PANEL (white, scrollable)    │  RIGHT PANEL (dark, fixed)    │
│  ≈50% width                        │  ≈50% width                   │
│                                    │                               │
│  [WEACT Logo]                      │  "Trouvez les talents         │
│                                    │   parfaits pour vos           │
│  Inscription Producteur            │   productions" (teal accent)  │
│  ─────────────────────             │                               │
│                                    │  Subtitle text                │
│  [Type Selector: Agence/Particulier]│                              │
│                                    │  ┌─────────┐ ┌─────────┐     │
│  "Ou avec email" divider           │  │ 500+    │ │ 200+    │     │
│                                    │  │ Faces   │ │Missions │     │
│  [Form Fields - scrollable]        │  └─────────┘ └─────────┘     │
│                                    │                               │
│  [Terms Checkbox]                  │  ✓ Feature 1                 │
│                                    │  ✓ Feature 2                 │
│  [Submit Button]                   │  ✓ Feature 3                 │
│                                    │                               │
│  "Inscrivez-vous comme face" link  │                               │
└─────────────────────────────────────────────────────────────────────┘
```

**Left Panel - Form Section:**
- Background: white (`bg-white`)
- Overflow: scrollable (`overflow-y-auto`)
- Padding: generous spacing (`p-8` to `p-12`)
- Max-width for form content: ~450px centered

**Right Panel - Branding Section:**
- Background: dark with subtle image overlay (`bg-slate-800` or darker)
- Position: fixed, doesn't scroll
- Content vertically centered
- Hidden on mobile (`hidden lg:flex`)

**Form Elements Styling:**

1. **Type Selector (Agency/Particulier)** - ADD THIS (not in original design):
   - Two pill/toggle buttons side by side
   - Active state: primary color (#198496) background, white text
   - Inactive state: light gray background, dark text
   - Full-width toggle or centered pills
   - Position: FIRST element after title

2. **Input Fields:**
   - Border: light gray (`border-gray-300`)
   - Border-radius: rounded (`rounded-lg` or `rounded-xl`)
   - Padding: comfortable (`px-4 py-3`)
   - Focus state: primary color border (#198496)
   - Labels above inputs with required asterisk in red
   - Placeholder text in light gray

3. **Conditional Fields Layout:**
   - **Agency mode**: Single "Nom de l'agence" field (full width)
   - **Particulier mode**: "Prénom" and "Nom" side by side (2-column grid)

4. **Password Fields:**
   - Include show/hide toggle icon (eye icon) on right side
   - Same styling as other inputs

5. **Terms Checkbox:**
   - Text: "J'accepte les [conditions générales] et la [politique de confidentialité]"
   - Links in primary color (#198496)

6. **Submit Button:**
   - Full width
   - Background: primary color (#198496)
   - Text: white, "Créer mon compte producteur"
   - Border-radius: rounded (`rounded-lg`)
   - Hover: slightly darker shade
   - Loading state: spinner + "Création en cours..."

7. **Bottom Link:**
   - Text: "Vous êtes un talent ? [Inscrivez-vous comme face]"
   - Link in primary color

**Right Panel Content:**
- Headline: "Trouvez les talents parfaits pour vos **productions**" (last word in #198496)
- Subtitle: "Accédez à une communauté de faces talentueuses au Bénin"
- Stats cards (dark cards with white text):
  - "500+" / "Faces disponibles"
  - "200+" / "Missions complétées"
- Feature list with icons:
  - "Base de talents diversifiée"
  - "Gestion simplifiée"
  - "Paiements sécurisés"

**Responsive Behavior:**
- Desktop (lg+): Split screen layout
- Mobile/Tablet: Full-width form only, hide right branding panel

**CSS Enhancement Freedom:**
- Can improve shadows, border-radius, typography, spacing
- Keep primary color #198496
- Maintain split-screen structure on desktop
- Make it modern and aesthetic

### Component Structure

```vue
<!-- RegisterProducerPage.vue -->
<template>
  <div class="min-h-screen flex">
    <!-- LEFT: Form Panel -->
    <div class="w-full lg:w-1/2 overflow-y-auto bg-white">
      <div class="max-w-md mx-auto px-8 py-12">
        <!-- Logo -->
        <img src="@/assets/logo.svg" alt="WEACT" class="h-8 mb-8" />

        <!-- Title -->
        <h1 class="text-3xl font-bold text-gray-900 mb-8">
          Inscription Producteur
        </h1>

        <!-- Form Component -->
        <ProducerRegistrationForm @success="handleSuccess" />

        <!-- Bottom Link -->
        <p class="text-center mt-6 text-gray-600">
          Vous êtes un talent ?
          <router-link to="/register/face" class="text-primary hover:underline">
            Inscrivez-vous comme face
          </router-link>
        </p>
      </div>
    </div>

    <!-- RIGHT: Branding Panel (hidden on mobile) -->
    <div class="hidden lg:flex lg:w-1/2 bg-slate-800 relative">
      <div class="absolute inset-0 bg-black/40" />
      <div class="relative z-10 flex flex-col justify-center px-12 text-white">
        <h2 class="text-4xl font-bold mb-4">
          Trouvez les talents parfaits pour vos
          <span class="text-primary">productions</span>
        </h2>
        <p class="text-lg text-gray-300 mb-8">
          Accédez à une communauté de faces talentueuses au Bénin
        </p>

        <!-- Stats Cards -->
        <div class="flex gap-4 mb-8">
          <div class="bg-slate-700/50 rounded-xl p-6">
            <div class="text-3xl font-bold">500+</div>
            <div class="text-gray-400">Faces disponibles</div>
          </div>
          <div class="bg-slate-700/50 rounded-xl p-6">
            <div class="text-3xl font-bold">200+</div>
            <div class="text-gray-400">Missions complétées</div>
          </div>
        </div>

        <!-- Features -->
        <div class="space-y-4">
          <FeatureItem icon="users" text="Base de talents diversifiée" />
          <FeatureItem icon="clipboard" text="Gestion simplifiée" />
          <FeatureItem icon="shield" text="Paiements sécurisés" />
        </div>
      </div>
    </div>
  </div>
</template>
```

```vue
<!-- ProducerRegistrationForm.vue - Form fields only -->
<template>
  <form @submit="onSubmit" class="space-y-6">
    <!-- Type Selector -->
    <div class="flex rounded-lg bg-gray-100 p-1">
      <button
        type="button"
        :class="[
          'flex-1 py-3 rounded-md text-sm font-medium transition-all',
          form.type === 'agency'
            ? 'bg-primary text-white shadow-sm'
            : 'text-gray-600 hover:text-gray-900'
        ]"
        @click="form.type = 'agency'"
      >
        Agence
      </button>
      <button
        type="button"
        :class="[
          'flex-1 py-3 rounded-md text-sm font-medium transition-all',
          form.type === 'particulier'
            ? 'bg-primary text-white shadow-sm'
            : 'text-gray-600 hover:text-gray-900'
        ]"
        @click="form.type = 'particulier'"
      >
        Particulier
      </button>
    </div>

    <!-- Divider -->
    <div class="relative">
      <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-gray-300" />
      </div>
      <div class="relative flex justify-center text-sm">
        <span class="bg-white px-4 text-gray-500">Informations</span>
      </div>
    </div>

    <!-- Conditional: Agency Name -->
    <div v-if="form.type === 'agency'">
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Nom de l'agence<span class="text-red-500">*</span>
      </label>
      <input
        v-model="form.agency_name"
        type="text"
        placeholder="Ex: WeAct Productions"
        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
      />
    </div>

    <!-- Conditional: First/Last Name (2 columns) -->
    <div v-else class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Prénom<span class="text-red-500">*</span>
        </label>
        <input
          v-model="form.first_name"
          type="text"
          placeholder="Jean"
          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Nom<span class="text-red-500">*</span>
        </label>
        <input
          v-model="form.last_name"
          type="text"
          placeholder="Dupont"
          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
        />
      </div>
    </div>

    <!-- Email -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Email professionnel<span class="text-red-500">*</span>
      </label>
      <input
        v-model="form.email"
        type="email"
        placeholder="contact@votreentreprise.com"
        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
      />
    </div>

    <!-- Password with toggle -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Mot de passe<span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <input
          v-model="form.password"
          :type="showPassword ? 'text' : 'password'"
          placeholder="••••••••"
          class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
        />
        <button
          type="button"
          @click="showPassword = !showPassword"
          class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
        >
          <EyeIcon v-if="!showPassword" class="w-5 h-5" />
          <EyeOffIcon v-else class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- Password Confirmation with toggle -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Confirmer le mot de passe<span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <input
          v-model="form.password_confirmation"
          :type="showPasswordConfirm ? 'text' : 'password'"
          placeholder="••••••••"
          class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
        />
        <button
          type="button"
          @click="showPasswordConfirm = !showPasswordConfirm"
          class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
        >
          <EyeIcon v-if="!showPasswordConfirm" class="w-5 h-5" />
          <EyeOffIcon v-else class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- Terms Checkbox -->
    <div class="flex items-start gap-2">
      <input
        v-model="form.acceptTerms"
        type="checkbox"
        class="mt-1 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
      />
      <label class="text-sm text-gray-600">
        J'accepte les
        <a href="/terms" class="text-primary hover:underline">conditions générales</a>
        et la
        <a href="/privacy" class="text-primary hover:underline">politique de confidentialité</a>
      </label>
    </div>

    <!-- Submit Button -->
    <button
      type="submit"
      :disabled="isSubmitting"
      class="w-full py-4 bg-primary text-white font-medium rounded-lg hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
    >
      <span v-if="!isSubmitting">Créer mon compte producteur</span>
      <span v-else class="flex items-center justify-center gap-2">
        <Spinner class="w-5 h-5" />
        Création en cours...
      </span>
    </button>
  </form>
</template>
```

**NOTE:** The design shows "Continuer avec Google" - this is NOT in MVP scope. Skip OAuth for now.

### Project Structure Notes

**Backend Files to Create:**
- `app/Enums/ProducerType.php` - PHP enum for producer types
- `app/Http/Controllers/Api/V1/Auth/RegisterProducerController.php`
- `app/Http/Requests/Auth/RegisterProducerRequest.php`
- `app/Http/Resources/ProducerResource.php`
- `app/Services/Auth/ProducerRegistrationService.php`
- `tests/Feature/Auth/ProducerRegistrationTest.php`

**Frontend Files to Create:**
- `src/features/auth/schemas/producerRegistration.ts`
- `src/features/auth/components/ProducerRegistrationForm.vue`
- `src/features/auth/components/__tests__/ProducerRegistrationForm.spec.ts`
- `src/pages/auth/RegisterProducerPage.vue`

**Frontend Files to Modify:**
- `src/features/auth/types.ts` - Add ProducerRegistrationForm types
- `src/features/auth/services/authApi.ts` - Add registerProducer method
- `src/features/auth/composables/useAuth.ts` - Add registerProducer method
- `src/router/index.ts` - Add /register/producer route

### Previous Story Intelligence (Story 2.1)

**Key Patterns Established in Face Registration:**
- Service class pattern: `FaceRegistrationService` → follow same for `ProducerRegistrationService`
- Form Request with French messages → same pattern for RegisterProducerRequest
- API envelope response format → must match exactly
- VeeValidate + Zod integration → same pattern but with discriminated union
- Component structure in `features/auth/components/` → same location
- Test patterns in `tests/Feature/Auth/` → same location

**Files Created in Story 2.1 to Reuse:**
- `UserResource.php` - Update to handle Producer userable
- `authApi.ts` - Extend with registerProducer
- `useAuth.ts` - Extend with registerProducer
- `types.ts` - Extend with Producer types

**Testing Patterns from Story 2.1:**
- Backend: Use `RefreshDatabase`, test happy path + all error cases
- Frontend: Test form rendering, validation, API calls, loading states

### Existing Code References

**From Story 2.1 implementations:**
- `backend/app/Models/Producer.php` - Producer model exists (verify fillable)
- `backend/app/Models/User.php` - Polymorphic userable relationship ready
- `backend/routes/api.php` - Route pattern established: `/api/v1/auth/register/face`
- `backend/app/Http/Resources/UserResource.php` - Needs update to handle Producer
- `frontend/src/features/auth/services/authApi.ts` - Extend with Producer
- `frontend/src/features/auth/composables/useAuth.ts` - Extend with Producer
- `frontend/src/router/index.ts` - Route pattern: `/register/face`

### Critical Rules from Project Context

1. **REST API only** - No Blade views, pure JSON responses
2. **Form Request validation** - Every endpoint MUST use Form Request
3. **API Resource transformation** - Never return Eloquent models directly
4. **Business logic in Services** - Not in Controllers
5. **Composition API only** - `<script setup lang="ts">` in all Vue components
6. **PHP strict types** - `declare(strict_types=1);` in all PHP files
7. **PHP Enums** - Use for status values (ProducerType enum)
8. **Token storage** - localStorage for MVP (same as Face registration)

### References

- [Source: docs/planning-artifacts/architecture.md#Authentication & Security]
- [Source: docs/planning-artifacts/architecture.md#Naming Patterns]
- [Source: docs/planning-artifacts/architecture.md#API & Communication Patterns]
- [Source: _bmad-output/project-context.md#Critical Implementation Rules]
- [Source: _bmad-output/planning-artifacts/epics.md#Story 2.2: Producer Registration]
- [Source: _bmad-output/implementation-artifacts/2-1-face-registration.md#Dev Notes]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

- Backend tests: 12 tests passed, 99 assertions
- Frontend tests: 19 tests passed (12 ProducerRegistrationForm + 7 FaceRegistrationForm)

### Completion Notes List

1. Created ProducerType PHP enum with Agency/Particulier values
2. Added display_name accessor to Producer model for computed display name
3. RegisterProducerRequest uses conditional validation (required_if) for type-specific fields
4. ProducerRegistrationService follows FaceRegistrationService pattern with DB transactions
5. ProducerResource includes display_name computed field
6. Frontend uses dynamic Zod schema based on selected producer type
7. ProducerRegistrationForm component includes type selector toggle, password visibility toggles
8. RegisterProducerPage matches design with split-screen layout (form left, branding right)
9. Route added with guest guard (meta: { guest: true })

### File List

**Backend Files Created:**
- `backend/app/Enums/ProducerType.php`
- `backend/app/Http/Controllers/Api/V1/Auth/RegisterProducerController.php`
- `backend/app/Http/Requests/Auth/RegisterProducerRequest.php`
- `backend/app/Services/Auth/ProducerRegistrationService.php`
- `backend/tests/Feature/Auth/ProducerRegistrationTest.php`

**Backend Files Modified:**
- `backend/app/Models/Producer.php` (added display_name accessor, type cast to enum)
- `backend/app/Http/Resources/ProducerResource.php` (added display_name field)
- `backend/routes/api.php` (added producer registration route)

**Frontend Files Created:**
- `frontend/src/features/auth/schemas/producerRegistration.ts`
- `frontend/src/features/auth/components/ProducerRegistrationForm.vue`
- `frontend/src/features/auth/components/__tests__/ProducerRegistrationForm.spec.ts`
- `frontend/src/pages/auth/RegisterProducerPage.vue`

**Frontend Files Modified:**
- `frontend/src/features/auth/types.ts` (added Producer types)
- `frontend/src/features/auth/services/authApi.ts` (added registerProducer method)
- `frontend/src/features/auth/composables/useAuth.ts` (added registerProducer method)
- `frontend/src/router/index.ts` (updated /register/producer route)

