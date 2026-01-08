---
project_name: 'WEACT'
user_name: 'Amakira'
date: '2026-01-06'
sections_completed: ['technology_stack', 'language_rules', 'framework_rules', 'testing_rules', 'code_quality', 'workflow_rules', 'critical_rules']
status: 'complete'
rule_count: 75
optimized_for_llm: true
---

# Project Context for AI Agents

_This file contains critical rules and patterns that AI agents must follow when implementing code in this project. Focus on unobvious details that agents might otherwise miss._

---

## Technology Stack & Versions

### Backend
- **PHP** 8.2+ (required for Laravel 12)
- **Laravel** 12 (REST API, not Inertia)
- **MySQL** 8.0+
- **Laravel Sanctum** 4.x (token-based, not cookie-based)

### Frontend
- **Vue** 3 with Composition API only (no Options API)
- **TypeScript** (strict mode)
- **Pinia** for state management
- **Vue Router** for routing
- **Tailwind CSS** 4.1 with `@tailwindcss/vite` plugin (CSS-based config, no tailwind.config.js)
- **Vite** 6.x for build

### Forms & Validation
- **VeeValidate** + **Zod** (frontend)
- **Laravel Form Requests** (backend)

### Testing
- **PHPUnit** + **Pest** (backend)
- **Vitest** + **Vue Test Utils** (frontend)

---

## Critical Implementation Rules

### Language-Specific Rules

#### TypeScript (Frontend)
- Use `<script setup lang="ts">` in all Vue components
- Define explicit return types on all functions and composables
- Use `interface` for object shapes, `type` for unions/intersections
- Never use `any` - prefer `unknown` with type guards if type is uncertain
- Use `as const` for literal types (enums, status values)
- Import types with `import type { ... }` when only used for typing

#### PHP (Backend)
- Add `declare(strict_types=1);` to all PHP files
- Use PHP 8.2+ features: readonly properties, enums, named arguments
- Type hint all method parameters and return types
- Use PHP Enums for status values (UserType, MissionStatus, CandidatureStatus)
- Prefer constructor property promotion for DTOs and value objects

### Framework-Specific Rules

#### Vue 3 (Frontend)
- **Composition API only** - never use Options API
- Use `<script setup lang="ts">` syntax in all components
- Composables must start with `use` prefix: `useMissions()`, `useAuth()`
- Feature modules structure: `features/{domain}/components/`, `features/{domain}/composables/`, `features/{domain}/services/`
- Shared UI components in `components/ui/` - must be domain-agnostic
- Use `defineProps<T>()` and `defineEmits<T>()` with TypeScript generics
- Reactive refs: `ref()` for primitives, `reactive()` for objects
- Use `computed()` for derived state, never compute in template

#### Laravel (Backend)
- **REST API only** - no Blade views, no Inertia
- Controllers in `app/Http/Controllers/Api/V1/{Domain}/`
- Every endpoint must use a **Form Request** for validation
- Every response must use an **API Resource** for transformation
- **Polymorphic User Architecture**: `User` model has `userable` morphTo relationship pointing to `Face` or `Producer`
- Business logic in **Services**, not Controllers
- Use **Policies** for resource authorization, **Gates** for role-based checks
- Route model binding with explicit model: `Route::get('/missions/{mission}', ...)`

### Testing Rules

#### Backend (PHPUnit + Pest)
- Test files in `tests/Feature/` organized by domain
- Use Pest syntax for cleaner tests: `it()`, `test()`, `expect()`
- Name test files: `{Feature}Test.php` (e.g., `MissionCreationTest.php`)
- Use database transactions: `use RefreshDatabase;`
- Factory states for test data: `Mission::factory()->published()->create()`
- Test API endpoints with `actingAs($user)->getJson('/api/v1/...')`
- Assert JSON structure with `assertJsonStructure(['data' => [...]])`

#### Frontend (Vitest + Vue Test Utils)
- Test files colocated with components: `ComponentName.spec.ts`
- Use `mount()` for full component tests, `shallowMount()` for isolation
- Mock API calls with `vi.mock()` - never hit real API in unit tests
- Test composables in isolation with `@vue/test-utils` renderHook pattern
- Use `data-testid` attributes for selecting elements in tests
- Snapshot tests only for static UI components

#### Coverage Requirements
- Minimum 80% coverage for business logic (Services, Composables)
- All API endpoints must have at least one happy path and one error test
- Critical workflows (candidature, mission lifecycle) require integration tests

### Code Quality & Style Rules

#### Naming Conventions

**Database (snake_case):**
- Tables: plural (`users`, `missions`, `candidatures`)
- Columns: `snake_case` (`created_at`, `is_available`)
- Foreign keys: `{singular}_id` (`user_id`, `mission_id`)
- Pivot tables: alphabetical singular (`face_mission`)

**API (snake_case for JSON, kebab-case for URLs):**
- Endpoints: `/api/v1/missions`, `/api/v1/face-profiles`
- JSON fields: `snake_case` (`created_at`, `min_budget`)
- Query params: `snake_case` (`?min_budget=1000`)
- Actions: verb suffix (`/candidatures/{id}/accept`)

**Frontend (camelCase/PascalCase):**
- Components: `PascalCase` (`MissionCard.vue`)
- Composables: `useCamelCase` (`useMissions`)
- Services: `camelCaseApi` (`missionsApi`)
- Variables: `camelCase` (`missionList`, `isLoading`)
- Types/Interfaces: `PascalCase` (`Mission`, `ApiResponse<T>`)
- Constants: `SCREAMING_SNAKE` (`API_BASE_URL`)

#### API Response Format

**Success Response:**
```json
{ "data": {...}, "meta": {...}, "message": "..." }
```

**Error Response:**
```json
{ "error": { "code": "...", "message": "...", "details": {...} } }
```

#### Data Formats
- Dates (API): ISO 8601 (`"2026-01-06T14:30:00Z"`)
- Dates (UI): French locale (`"6 janvier 2026"`)
- Currency: Integer XOF (`75000`, not `"75,000 XOF"`)

### Development Workflow Rules

#### Project Structure
- **Monorepo**: `/backend` (Laravel) + `/frontend` (Vue)
- Run backend and frontend dev servers concurrently
- Shared `.env.example` at root for environment variables

#### Git Workflow
- Branch from `main` for features: `feature/{domain}/{description}`
- Branch for fixes: `fix/{description}`
- Commit messages: imperative mood ("Add mission creation endpoint")
- PR required for all changes to `main`

#### Code Organization
- Backend routes in `routes/api/` (modular by domain)
- Frontend routes in `router/` with lazy-loaded views
- Shared types: Keep API response types in `frontend/src/types/api.ts`

#### Environment Variables
- Backend: `.env` in `/backend` (Laravel standard)
- Frontend: `.env` in `/frontend` (Vite `VITE_` prefix required)
- Never commit `.env` files - use `.env.example` templates

#### CI/CD (GitHub Actions)
- Run linting (ESLint, PHP CS Fixer) on every PR
- Run tests (Pest, Vitest) on every PR
- Build check before merge
- Deploy to DigitalOcean VPS on merge to `main`

### Critical Don't-Miss Rules

#### WEACT-Specific Business Rules
- **Chat access**: Only unlocked AFTER candidature is accepted (never before)
- **Ratings**: Only possible AFTER mission is marked "Terminée" (completed)
- **Mission confirmation**: Face must confirm after Producer accepts (2-step validation)
- **Wallet**: UI present but functionality inactive for MVP (architecture ready for V2)

#### Anti-Patterns to Avoid

**Backend:**
- ❌ Never put business logic in Controllers - use Services
- ❌ Never return Eloquent models directly - always use API Resources
- ❌ Never skip Form Request validation - even for simple endpoints
- ❌ Never use `$request->all()` - explicitly list allowed fields
- ❌ Never expose internal IDs in URLs without authorization checks

**Frontend:**
- ❌ Never use Options API - only Composition API with `<script setup>`
- ❌ Never mutate props directly - emit events to parent
- ❌ Never call API directly in components - use composables/services
- ❌ Never store sensitive data in Pinia (tokens go in httpOnly cookies or secure storage)
- ❌ Never hardcode API URLs - use environment variables

#### Security Rules
- All file uploads must validate: type, size (50MB max for video), and scan for malicious content
- Rate limit auth endpoints: 5 requests/minute
- Rate limit upload endpoints: 10 requests/minute
- Sanitize all user input before storage
- Use `$request->validated()` not `$request->all()`

#### Performance Rules
- Lazy load all route components: `() => import('./pages/...')`
- Use pagination for all list endpoints (default 15 items)
- Eager load relationships to avoid N+1: `Mission::with('producer', 'candidatures')`
- Compress images client-side before upload
- Target <300KB initial bundle, <2s load on 4G

#### Edge Cases
- User can be either Face OR Producer, never both (polymorphic exclusive)
- Admin is a separate model, not a User role
- Mission can have multiple accepted candidatures (up to `nombre_faces_voulu`)
- Face availability toggle affects visibility in search results

---

## Usage Guidelines

**For AI Agents:**
- Read this file before implementing any code
- Follow ALL rules exactly as documented
- When in doubt, prefer the more restrictive option
- Refer to `docs/planning-artifacts/architecture.md` for detailed architectural decisions

**For Humans:**
- Keep this file lean and focused on agent needs
- Update when technology stack changes
- Review quarterly for outdated rules
- Remove rules that become obvious over time

---

**Last Updated:** 2026-01-06
