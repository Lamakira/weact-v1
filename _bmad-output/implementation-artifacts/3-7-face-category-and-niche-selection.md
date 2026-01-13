# Story 3.7: Face Category and Niche Selection

Status: done

## Story

As a **Face**,
I want **to select my category and niche**,
So that **producers can find me based on my specialization**.

## Acceptance Criteria

1. **Given** I am on my profile edit page, **When** I select my category from: acteur, influenceur, créateur, mannequin, figurant, **Then** my category is saved and displayed

2. **Given** I am on my profile edit page, **When** I select my niche from: beauté, nourriture, découverte, mode, **Then** my niche is saved and displayed

3. **Given** these are dropdown/select fields, **When** I view my public profile, **Then** category and niche are shown as badges or tags

4. **Given** I change my category or niche, **When** I save, **Then** I see a success toast notification

5. **Given** I clear my category or niche selection, **When** I save, **Then** the values are cleared from my profile

6. **Given** I am viewing my profile, **When** the page loads, **Then** I see my category and niche displayed (if previously saved)

**(FR16, FR17)**

## Tasks / Subtasks

### Task 1: Update Face Model and Database Schema (AC: #1, #2, #5, #6)

- [x] 1.1 Create migration `add_category_and_niche_to_faces`:
  - `categorie` (enum, nullable) - acteur, influenceur, createur, mannequin, figurant
  - `niche` (enum, nullable) - beaute, nourriture, decouverte, mode
- [x] 1.2 Create PHP Enum `app/Enums/FaceCategory.php`:
  - Cases: ACTEUR, INFLUENCEUR, CREATEUR, MANNEQUIN, FIGURANT
  - Add label() method for French display names
- [x] 1.3 Create PHP Enum `app/Enums/FaceNiche.php`:
  - Cases: BEAUTE, NOURRITURE, DECOUVERTE, MODE
  - Add label() method for French display names
- [x] 1.4 Update Face model:
  - Add to fillable: `categorie`, `niche`
  - Add casts: `'categorie' => FaceCategory::class, 'niche' => FaceNiche::class`
- [x] 1.5 Run migration and verify schema

### Task 2: Create Category/Niche Form Request (AC: #1, #2)

- [x] 2.1 Create `app/Http/Requests/Face/UpdateCategoryNicheRequest.php`
- [x] 2.2 Define validation rules:
  ```php
  return [
      'categorie' => ['nullable', new Enum(FaceCategory::class)],
      'niche' => ['nullable', new Enum(FaceNiche::class)],
  ];
  ```
- [x] 2.3 Implement French error messages:
  - `categorie.enum` → "La catégorie sélectionnée n'est pas valide"
  - `niche.enum` → "La niche sélectionnée n'est pas valide"
- [x] 2.4 Implement authorization check (user must be Face owner)

### Task 3: Create Category/Niche Service (AC: #1, #2, #4, #5)

- [x] 3.1 Create `app/Services/CategoryNicheService.php`
- [x] 3.2 Implement `updateCategoryNiche(Face $face, array $data): Face`:
  - Update categorie, niche fields
  - Use `array_key_exists()` pattern for nullable updates
  - Return updated Face model
- [x] 3.3 Handle nullable fields (allow clearing values)

### Task 4: Create Category/Niche Controller (AC: #1, #2, #4, #5, #6)

- [x] 4.1 Create `app/Http/Controllers/Api/V1/Face/CategoryNicheController.php`
- [x] 4.2 Implement `show(): JsonResponse` - get current category and niche
- [x] 4.3 Implement `update(UpdateCategoryNicheRequest $request): JsonResponse`:
  - Use CategoryNicheService for business logic
  - Return standard API envelope with updated data
- [x] 4.4 Use private `getAuthenticatedFace()` method pattern from previous controllers
- [x] 4.5 Use API envelope format for all responses

### Task 5: Update FaceResource API Resource (AC: #3, #6)

- [x] 5.1 Update `app/Http/Resources/FaceResource.php` to include:
  - `categorie` (value as string)
  - `categorie_label` (French display name)
  - `niche` (value as string)
  - `niche_label` (French display name)

### Task 6: Add API Routes (AC: #1)

- [x] 6.1 Add routes to `routes/api/face.php`:
  - `GET /api/v1/face/category-niche` - get current category and niche
  - `PUT /api/v1/face/category-niche` - update category and niche
- [x] 6.2 Apply `auth:sanctum` middleware
- [x] 6.3 Apply rate limiting: `throttle:60,1`

### Task 7: Create Options Endpoint for Dropdowns (AC: #1, #2)

- [x] 7.1 Create `app/Http/Controllers/Api/V1/Face/CategoryNicheOptionsController.php`
- [x] 7.2 Implement `categories(): JsonResponse` - return list of categories with labels
- [x] 7.3 Implement `niches(): JsonResponse` - return list of niches with labels
- [x] 7.4 Add public routes (no auth required):
  - `GET /api/v1/face/options/categories`
  - `GET /api/v1/face/options/niches`

### Task 8: Backend Tests (AC: #1, #2, #3, #4, #5, #6)

- [x] 8.1 Create `tests/Feature/Face/CategoryNicheTest.php`
- [x] 8.2 Test successful categorie update
- [x] 8.3 Test successful niche update
- [x] 8.4 Test successful categorie and niche update together
- [x] 8.5 Test rejection of invalid categorie enum value
- [x] 8.6 Test rejection of invalid niche enum value
- [x] 8.7 Test clearing categorie (setting to null)
- [x] 8.8 Test clearing niche (setting to null)
- [x] 8.9 Test get category/niche endpoint
- [x] 8.10 Test unauthorized access (non-owner)
- [x] 8.11 Test options endpoints return correct values

### Task 9: Update Frontend Types (AC: #1, #2, #3)

- [x] 9.1 Add to `frontend/src/features/face/types.ts`:
  ```typescript
  // Category enum values
  type FaceCategory = 'acteur' | 'influenceur' | 'createur' | 'mannequin' | 'figurant'

  // Niche enum values
  type FaceNiche = 'beaute' | 'nourriture' | 'decouverte' | 'mode'

  interface CategoryNicheInfo {
    categorie: FaceCategory | null
    categorie_label: string | null
    niche: FaceNiche | null
    niche_label: string | null
  }

  interface CategoryNicheResponse {
    data: CategoryNicheInfo
    message?: string
  }

  interface CategoryNicheResult {
    success: boolean
    data?: CategoryNicheInfo
    errors?: Record<string, string[]>
    message?: string
  }

  interface CategoryOption {
    value: FaceCategory
    label: string
  }

  interface NicheOption {
    value: FaceNiche
    label: string
  }
  ```

### Task 10: Create Category/Niche API Service (AC: #1, #2)

- [x] 10.1 Add to `frontend/src/features/face/services/faceApi.ts`:
  ```typescript
  getCategoryNiche(): Promise<CategoryNicheResponse>
  updateCategoryNiche(data: {
    categorie?: FaceCategory | null
    niche?: FaceNiche | null
  }): Promise<CategoryNicheResponse>
  getCategoryOptions(): Promise<{ data: CategoryOption[] }>
  getNicheOptions(): Promise<{ data: NicheOption[] }>
  ```

### Task 11: Create useCategoryNiche Composable (AC: #1, #2, #3, #4, #5, #6)

- [x] 11.1 Create `frontend/src/features/face/composables/useCategoryNiche.ts`
- [x] 11.2 Implement reactive state:
  - `categoryNicheInfo`, `isLoading`, `isSaving`, `error`
  - `categoryOptions`, `nicheOptions`
- [x] 11.3 Implement methods:
  - `fetchCategoryNiche()`, `updateCategoryNiche()`, `clearError()`
  - `fetchCategoryOptions()`, `fetchNicheOptions()`
- [x] 11.4 Implement validation helpers: `validateCategory()`, `validateNiche()`

### Task 12: Create CategoryNicheForm Component (AC: #1, #2, #3, #4, #5, #6)

- [x] 12.1 Create `frontend/src/features/face/components/CategoryNicheForm.vue`
- [x] 12.2 Implement form with:
  - Select dropdown for categorie with French labels
  - Select dropdown for niche with French labels
  - Save button
  - Loading states
  - Error display
- [x] 12.3 Props: `categoryNicheInfo`, `categoryOptions`, `nicheOptions`, `isSaving`, `error`
- [x] 12.4 Emits: `save`
- [x] 12.5 Include "Sélectionnez..." placeholder option for nullable selection
- [x] 12.6 Display current selection with badges/tags styling

### Task 13: Integrate Category/Niche into ProfileEditPage (AC: #1, #4, #6)

- [x] 13.1 Update `frontend/src/pages/face/ProfileEditPage.vue`
- [x] 13.2 Add "Catégorie et Niche" section AFTER "Caractéristiques physiques" section
- [x] 13.3 Import and use `useCategoryNiche` composable
- [x] 13.4 Import and use `CategoryNicheForm` component
- [x] 13.5 Handle save event with toast notifications
- [x] 13.6 Fetch category/niche and options on mount

### Task 14: Frontend Tests (AC: #1, #2, #3, #4, #5, #6)

- [x] 14.1 Create `frontend/src/features/face/composables/__tests__/useCategoryNiche.spec.ts`
- [x] 14.2 Test initial state
- [x] 14.3 Test fetch category/niche
- [x] 14.4 Test update categorie only
- [x] 14.5 Test update niche only
- [x] 14.6 Test update both categorie and niche
- [x] 14.7 Test clear categorie
- [x] 14.8 Test clear niche
- [x] 14.9 Test fetch options
- [x] 14.10 Test error handling

- [x] 14.11 Create `frontend/src/features/face/components/__tests__/CategoryNicheForm.spec.ts`
- [x] 14.12 Test form rendering with initial values
- [x] 14.13 Test form rendering with null values
- [x] 14.14 Test save button disabled when saving
- [x] 14.15 Test error message display
- [x] 14.16 Test save event emission with form data
- [x] 14.17 Test save event emission with null for empty values
- [x] 14.18 Test dropdown options rendering

### Review Follow-ups (AI)

- [ ] [AI-Review][LOW] Add ProfileEditPage integration test for CategoryNicheForm section [ProfileEditPage.vue]

## Dev Notes

### Database Schema

```
faces table:
├── (existing columns)
├── categorie (enum, nullable) - acteur, influenceur, createur, mannequin, figurant
└── niche (enum, nullable) - beaute, nourriture, decouverte, mode
```

### PHP Enums

```php
// FaceCategory.php
enum FaceCategory: string
{
    case ACTEUR = 'acteur';
    case INFLUENCEUR = 'influenceur';
    case CREATEUR = 'createur';
    case MANNEQUIN = 'mannequin';
    case FIGURANT = 'figurant';

    public function label(): string
    {
        return match($this) {
            self::ACTEUR => 'Acteur',
            self::INFLUENCEUR => 'Influenceur',
            self::CREATEUR => 'Créateur',
            self::MANNEQUIN => 'Mannequin',
            self::FIGURANT => 'Figurant',
        };
    }
}

// FaceNiche.php
enum FaceNiche: string
{
    case BEAUTE = 'beaute';
    case NOURRITURE = 'nourriture';
    case DECOUVERTE = 'decouverte';
    case MODE = 'mode';

    public function label(): string
    {
        return match($this) {
            self::BEAUTE => 'Beauté',
            self::NOURRITURE => 'Nourriture',
            self::DECOUVERTE => 'Découverte',
            self::MODE => 'Mode',
        };
    }
}
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/face/category-niche` | Get current category and niche |
| PUT | `/api/v1/face/category-niche` | Update category and niche |
| GET | `/api/v1/face/options/categories` | Get category options (public) |
| GET | `/api/v1/face/options/niches` | Get niche options (public) |

### Error Messages (French)

| Scenario | Message |
|----------|---------|
| Invalid category | "La catégorie sélectionnée n'est pas valide" |
| Invalid niche | "La niche sélectionnée n'est pas valide" |
| Update failed | "Échec de la mise à jour du profil" |
| Update success | "Profil mis à jour avec succès" |

### Enum Values

| Field | Values | French Labels |
|-------|--------|---------------|
| categorie | acteur, influenceur, createur, mannequin, figurant | Acteur, Influenceur, Créateur, Mannequin, Figurant |
| niche | beaute, nourriture, decouverte, mode | Beauté, Nourriture, Découverte, Mode |

### Project Structure Notes

**Backend files to create:**
```
backend/
├── app/
│   ├── Enums/
│   │   ├── FaceCategory.php
│   │   └── FaceNiche.php
│   ├── Http/
│   │   ├── Controllers/Api/V1/Face/
│   │   │   ├── CategoryNicheController.php
│   │   │   └── CategoryNicheOptionsController.php
│   │   └── Requests/Face/
│   │       └── UpdateCategoryNicheRequest.php
│   └── Services/
│       └── CategoryNicheService.php
├── database/migrations/
│   └── xxxx_add_category_and_niche_to_faces.php
└── tests/Feature/Face/
    └── CategoryNicheTest.php
```

**Frontend files to create:**
```
frontend/src/
├── features/face/
│   ├── components/
│   │   ├── CategoryNicheForm.vue
│   │   └── __tests__/
│   │       └── CategoryNicheForm.spec.ts
│   ├── composables/
│   │   ├── useCategoryNiche.ts
│   │   └── __tests__/
│   │       └── useCategoryNiche.spec.ts
│   └── types.ts (update existing)
│   └── services/faceApi.ts (update existing)
└── pages/face/
    └── ProfileEditPage.vue (update existing)
```

### Learnings from Previous Stories (MUST FOLLOW)

1. **Authorization Pattern**: Use `getAuthenticatedFace()` private method pattern from BioLocationController/PhysicalCharacteristicsController
2. **Form Request**: Always use Form Request for validation, never validate in controller
3. **API Envelope**: Follow standard `{data, message}` envelope format
4. **French Messages**: All user-facing messages must be in French
5. **Accessibility**: Add proper labels, aria attributes, and focus states
6. **Toast Notifications**: Use toast for success feedback
7. **Test Coverage**: Aim for comprehensive test coverage (composable + component)
8. **Service Pattern**: Use `array_key_exists()` for nullable field updates (not null coalescing `??`)
9. **Form novalidate**: Add `novalidate` attribute to forms to allow custom validation messages
10. **PHP Enums**: Use Laravel's Enum cast for type-safe enum handling

### UI Guidelines

- **Use Gemini MCP** for all frontend UI component generation (snippet_frontend, create_frontend, modify_frontend)
- **Use shadcn/ui components** when available (Select, Label, Button, etc.)
- Select dropdowns should have proper placeholder: "Sélectionnez une catégorie"
- Display selected values as badge/tag style elements on profile view
- Follow existing project design tokens and Tailwind classes from globals.css
- Single save button for the entire form
- Loading spinner on button during save

### Test Count Expectations

Based on previous story patterns:
- Backend: ~12-15 tests (CategoryNicheTest.php)
- Frontend Composable: ~10-14 tests (useCategoryNiche.spec.ts)
- Frontend Component: ~8-10 tests (CategoryNicheForm.spec.ts)

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 3.7 - FR16, FR17]
- [Source: _bmad-output/project-context.md#Technology Stack]
- [Source: _bmad-output/implementation-artifacts/3-6-face-physical-characteristics.md - Pattern reference]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

N/A - No debugging issues encountered

### Completion Notes List

- All 14 tasks completed successfully
- Backend: 16 new tests in CategoryNicheTest.php (176 total backend tests pass)
- Frontend: 24 tests in useCategoryNiche.spec.ts + 11 tests in CategoryNicheForm.spec.ts (354 total frontend tests pass)
- Followed established patterns from Story 3.6 (PhysicalCharacteristics)
- Used PHP Backed Enums with label() method for French display names
- Used array_key_exists() pattern for nullable field updates in service
- All acceptance criteria covered

### Code Review Fixes (2026-01-13)

**Fixed:**
- M1: Added rate limiting (`throttle:60,1`) to public options endpoints
- M2: Removed frontend enum validation (delegated to backend as source of truth)
- M4: Added error handling for options fetch in onMounted
- M5: Added loading state for category/niche section in ProfileEditPage

**Action Item:**
- M3: ProfileEditPage integration test (added as future task)

### File List

**Created:**
- `backend/database/migrations/2026_01_13_154310_add_category_and_niche_to_faces_table.php`
- `backend/app/Enums/FaceCategory.php`
- `backend/app/Enums/FaceNiche.php`
- `backend/app/Http/Requests/Face/UpdateCategoryNicheRequest.php`
- `backend/app/Services/CategoryNicheService.php`
- `backend/app/Http/Controllers/Api/V1/Face/CategoryNicheController.php`
- `backend/app/Http/Controllers/Api/V1/Face/CategoryNicheOptionsController.php`
- `backend/tests/Feature/Face/CategoryNicheTest.php`
- `frontend/src/features/face/composables/useCategoryNiche.ts`
- `frontend/src/features/face/components/CategoryNicheForm.vue`
- `frontend/src/features/face/composables/__tests__/useCategoryNiche.spec.ts`
- `frontend/src/features/face/components/__tests__/CategoryNicheForm.spec.ts`

**Modified:**
- `backend/app/Models/Face.php` - Added fillable fields and enum casts
- `backend/app/Http/Resources/FaceResource.php` - Added categorie/niche with labels
- `backend/routes/api/face.php` - Added category-niche and options routes
- `frontend/src/features/face/types.ts` - Added FaceCategory, FaceNiche, CategoryNicheInfo types
- `frontend/src/features/face/services/faceApi.ts` - Added API methods
- `frontend/src/pages/face/ProfileEditPage.vue` - Integrated CategoryNicheForm

## Change Log

| Date       | Change                                                | Author          |
|------------|-------------------------------------------------------|-----------------|
| 2026-01-13 | Story 3.7 created - Face Category and Niche Selection | Claude Opus 4.5 |
| 2026-01-13 | Story 3.7 implementation completed - All 14 tasks     | Claude Opus 4.5 |
| 2026-01-13 | Code review completed - 4 MEDIUM issues fixed         | Claude Opus 4.5 |
