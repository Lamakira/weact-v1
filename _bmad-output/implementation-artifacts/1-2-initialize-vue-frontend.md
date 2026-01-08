# Story 1.2: Initialize Vue Frontend

Status: done

## Story

As a **developer**,
I want **a Vue 3 frontend project with TypeScript, Pinia, and Tailwind CSS 4.1**,
So that **I can build the SPA with modern tooling**.

## Acceptance Criteria

1. **Given** the backend is initialized
   **When** I run the Vue initialization commands
   **Then** a `/frontend` directory is created with Vue 3 + TypeScript

2. **Given** the Vue project is created
   **When** I check the installed packages
   **Then** Pinia and Vue Router are installed and configured

3. **Given** the project dependencies are installed
   **When** I verify Tailwind CSS configuration
   **Then** Tailwind CSS 4.1 is configured with @tailwindcss/vite plugin

4. **Given** Tailwind is configured
   **When** I check the design system
   **Then** the primary color #198496 is defined and usable

5. **Given** all dependencies are installed
   **When** I run `npm run dev`
   **Then** the development server starts successfully on port 5173

## Tasks / Subtasks

- [x] **Task 1: Create Vue 3 Project with TypeScript** (AC: #1, #2)
  - [x] 1.1 Navigate to project root directory
  - [x] 1.2 Run `npm create vue@latest frontend -- --typescript --router --pinia`
  - [x] 1.3 Navigate to `/frontend` directory
  - [x] 1.4 Run `npm install` to install dependencies
  - [x] 1.5 Verify Vue 3 + TypeScript is configured

- [x] **Task 2: Install and Configure Tailwind CSS 4.1** (AC: #3, #4)
  - [x] 2.1 Install Tailwind CSS and Vite plugin: `npm install -D tailwindcss @tailwindcss/vite`
  - [x] 2.2 Update `vite.config.ts` to include Tailwind plugin
  - [x] 2.3 Create `src/assets/main.css` with Tailwind import
  - [x] 2.4 Configure design system primary color #198496

- [x] **Task 3: Configure Project Structure** (AC: #1)
  - [x] 3.1 Create feature-based directory structure
  - [x] 3.2 Create empty directories for future code organization
  - [x] 3.3 Configure path aliases in `tsconfig.json`

- [x] **Task 4: Configure Environment Variables** (AC: #5)
  - [x] 4.1 Create `.env.example` with required variables
  - [x] 4.2 Set `VITE_API_BASE_URL=http://localhost:8000/api/v1`
  - [x] 4.3 Configure TypeScript types for env variables

- [x] **Task 5: Verify Installation** (AC: #5)
  - [x] 5.1 Run `npm run dev` and verify server starts on port 5173
  - [x] 5.2 Verify Tailwind styles are working
  - [x] 5.3 Verify Vue Router navigation works
  - [x] 5.4 Verify Pinia store is accessible

## Dev Notes

### Critical Architecture Compliance

**From Architecture Document** [Source: docs/planning-artifacts/architecture.md]:

1. **Technology Stack:**
   - Vue 3 with Composition API ONLY (no Options API)
   - TypeScript in strict mode
   - Pinia for state management
   - Vue Router for routing
   - Tailwind CSS 4.1 with @tailwindcss/vite plugin (CSS-based config, NO tailwind.config.js)
   - Vite 6.x for build

2. **Vue 3 Critical Rules:**
   - Use `<script setup lang="ts">` in ALL components
   - Composables must start with `use` prefix
   - Use `defineProps<T>()` and `defineEmits<T>()` with TypeScript generics
   - Use `ref()` for primitives, `reactive()` for objects
   - Use `computed()` for derived state, never compute in template

3. **Design System:**
   - Primary color: #198496
   - Font: Inter (from Figma)

### Project Structure Requirements

**Frontend Directory Structure to Create:**
```
frontend/
├── src/
│   ├── assets/
│   │   └── main.css              # Tailwind imports + custom CSS
│   ├── components/
│   │   └── ui/                   # Shared UI components (Button, Input, Card, etc.)
│   ├── composables/              # Shared composables (useAuth, useApi, etc.)
│   ├── features/                 # Feature modules
│   │   └── .gitkeep
│   ├── layouts/                  # Page layouts (Default, Auth, Dashboard)
│   │   └── .gitkeep
│   ├── pages/                    # Route-level views
│   │   └── .gitkeep
│   ├── router/                   # Vue Router configuration
│   │   └── index.ts
│   ├── stores/                   # Pinia stores
│   │   └── .gitkeep
│   ├── services/                 # API client (Axios)
│   │   └── .gitkeep
│   ├── types/                    # Shared TypeScript types
│   │   └── .gitkeep
│   ├── utils/                    # Utility functions
│   │   └── .gitkeep
│   ├── App.vue
│   └── main.ts
├── .env.example
├── index.html
├── package.json
├── tsconfig.json
├── tsconfig.app.json
├── tsconfig.node.json
└── vite.config.ts
```

### Implementation Commands

```bash
# Step 1: Create Vue project (from project root)
npm create vue@latest frontend -- --typescript --router --pinia

# Step 2: Navigate and install dependencies
cd frontend
npm install

# Step 3: Install Tailwind CSS 4.1 with Vite plugin
npm install -D tailwindcss @tailwindcss/vite
```

### Key Configuration Files

**vite.config.ts** - Add Tailwind plugin:
```typescript
import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      }
    }
  }
})
```

**src/assets/main.css** - Tailwind imports with design system:
```css
@import "tailwindcss";

/* WEACT Design System */
@theme {
  --color-primary: #198496;
  --color-primary-50: #e6f4f6;
  --color-primary-100: #c0e3e8;
  --color-primary-200: #96d1da;
  --color-primary-300: #6cbfcb;
  --color-primary-400: #4db0bf;
  --color-primary-500: #198496;
  --color-primary-600: #167686;
  --color-primary-700: #126676;
  --color-primary-800: #0e5565;
  --color-primary-900: #083d4a;
  
  --font-family-sans: 'Inter', sans-serif;
}
```

**.env.example** - Required environment variables:
```env
# API Configuration
VITE_API_BASE_URL=http://localhost:8000/api/v1
VITE_APP_NAME=WEACT

# Feature Flags (for future use)
VITE_ENABLE_WALLET=false
```

**src/env.d.ts** - TypeScript environment types:
```typescript
/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE_URL: string
  readonly VITE_APP_NAME: string
  readonly VITE_ENABLE_WALLET: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
```

### Previous Story Learnings (Story 1.1)

From the completed Laravel backend story:
- Backend API is available at `http://localhost:8000`
- Health check endpoint: `GET /api/v1/health`
- CORS is configured to allow `localhost:5173`
- Sanctum stateful domains include `localhost:5173`
- API response format uses envelope: `{ data, meta, message }`

### Testing Verification

After completing all tasks, verify:
1. `npm run dev` starts without errors on port 5173
2. Browser shows Vue app at `http://localhost:5173`
3. Tailwind classes work (e.g., `bg-primary-500` shows #198496)
4. Vue Router navigates between routes
5. Pinia store can be created and accessed
6. TypeScript compilation has no errors

### Future Story Dependencies

This story is the **foundation** for:
- **Story 1.3**: Monorepo configuration (will orchestrate this with backend)
- **Story 2.6**: Authentication frontend integration
- **All frontend stories**: Every UI feature depends on this setup

### Anti-Patterns to Avoid

From [Source: _bmad-output/project-context.md]:
- ❌ Do NOT use Options API - only Composition API with `<script setup>`
- ❌ Do NOT use `tailwind.config.js` - Tailwind 4.1 uses CSS-based config
- ❌ Do NOT call API directly in components - use composables/services
- ❌ Do NOT use `any` type - prefer `unknown` with type guards
- ❌ Do NOT hardcode API URLs - use environment variables

### Critical Rules

1. **Composition API Only**: Use `<script setup lang="ts">` in ALL Vue components
2. **TypeScript Strict**: Enable strict mode, no `any` types
3. **Tailwind 4.1 CSS Config**: Use `@theme` directive, not JS config file
4. **Environment Variables**: All `VITE_` prefixed for Vite to expose

## References

- [Source: docs/planning-artifacts/architecture.md#Starter-Template-Evaluation] - Initialization commands
- [Source: docs/planning-artifacts/architecture.md#Frontend-Architecture] - Vue architecture decisions
- [Source: docs/planning-artifacts/architecture.md#Project-Structure-&-Boundaries] - Directory structure
- [Source: _bmad-output/project-context.md#Frontend] - Technology versions
- [Source: _bmad-output/project-context.md#Vue-3-(Frontend)] - Framework rules
- [Source: _bmad-output/planning-artifacts/epics.md#Story-1.2] - Original story definition

## Dev Agent Record

### Agent Model Used

Claude 3.5 Sonnet (GitHub Copilot)

### Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-01-07 | Story created with comprehensive context | SM Agent (Bob) |
| 2026-01-07 | Implementation completed | Dev Agent |

### Completion Notes

**Implementation Summary:**
- Vue 3 project created with TypeScript, Vue Router, and Pinia
- Tailwind CSS 4.1 installed with @tailwindcss/vite plugin
- Design system configured with primary color #198496 using @theme directive
- Feature-based directory structure created (components/ui, composables, features, layouts, pages, services, types, utils)
- Environment variables configured with TypeScript types
- App.vue and views updated to use Tailwind classes and Composition API only
- Pinia counter store updated with increment/decrement functions
- Build passes with no TypeScript errors
- Dev server starts successfully on port 5173

**Verification Results:**
- `npm run build` completes without errors ✅
- `npm run dev` starts on port 5173 ✅
- TypeScript compilation passes ✅
- Tailwind CSS classes work correctly ✅
- Vue Router navigation functional ✅
- Pinia store accessible ✅

### Files Created/Modified

- [x] `frontend/` - New Vue 3 project directory
- [x] `frontend/vite.config.ts` - Added Tailwind plugin and API proxy
- [x] `frontend/src/assets/main.css` - Replaced with Tailwind + WEACT design system
- [x] `frontend/src/env.d.ts` - Created for TypeScript env types
- [x] `frontend/.env.example` - Created with required variables
- [x] `frontend/.env` - Created for local development
- [x] `frontend/src/App.vue` - Updated with Tailwind classes and Composition API
- [x] `frontend/src/views/HomeView.vue` - Updated with design system demo and Pinia test
- [x] `frontend/src/views/AboutView.vue` - Updated with Tailwind classes
- [x] `frontend/src/stores/counter.ts` - Added decrement function
- [x] `frontend/src/components/ui/.gitkeep` - Created empty directory
- [x] `frontend/src/composables/.gitkeep` - Created empty directory
- [x] `frontend/src/features/.gitkeep` - Created empty directory
- [x] `frontend/src/layouts/.gitkeep` - Created empty directory
- [x] `frontend/src/pages/.gitkeep` - Created empty directory
- [x] `frontend/src/services/.gitkeep` - Created empty directory
- [x] `frontend/src/types/.gitkeep` - Created empty directory
- [x] `frontend/src/utils/.gitkeep` - Created empty directory

