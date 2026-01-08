# Story 1.3: Configure Monorepo Development Environment

Status: done

## Story

As a **developer**,
I want **concurrent development scripts and shared configuration**,
So that **I can run both servers simultaneously during development**.

## Acceptance Criteria

1. **Given** both backend and frontend are initialized
   **When** I configure the root package.json
   **Then** `npm run dev` starts both Laravel and Vue dev servers concurrently

2. **Given** the monorepo is configured
   **When** I check the root directory
   **Then** `.env.example` exists at root with shared environment documentation

3. **Given** the project has git initialized
   **When** I check `.gitignore`
   **Then** it properly excludes `node_modules/`, `vendor/`, `.env` files, and build artifacts

4. **Given** a new developer clones the repository
   **When** they read `README.md`
   **Then** they have clear instructions to set up and run the project

5. **Given** both servers are running
   **When** I make changes to frontend or backend
   **Then** hot reload works correctly on both sides

## Tasks / Subtasks

- [x] **Task 1: Create Root package.json with Concurrent Scripts** (AC: #1, #5)
  - [x] 1.1 Create `package.json` at project root
  - [x] 1.2 Install `concurrently` package for running multiple commands
  - [x] 1.3 Configure `npm run dev` to start both servers
  - [x] 1.4 Configure `npm run dev:backend` for Laravel only
  - [x] 1.5 Configure `npm run dev:frontend` for Vue only
  - [x] 1.6 Configure `npm run build` to build frontend

- [x] **Task 2: Create Root Environment Configuration** (AC: #2)
  - [x] 2.1 Create `.env.example` at root with documentation
  - [x] 2.2 Document all required environment variables
  - [x] 2.3 Create `.env` from `.env.example` for local development

- [x] **Task 3: Configure Git Ignore** (AC: #3)
  - [x] 3.1 Create comprehensive `.gitignore` at project root
  - [x] 3.2 Ensure `node_modules/`, `vendor/`, `.env` are excluded
  - [x] 3.3 Exclude build artifacts and IDE files

- [x] **Task 4: Create Project README** (AC: #4)
  - [x] 4.1 Create `README.md` at project root
  - [x] 4.2 Document prerequisites (PHP, Node, MySQL)
  - [x] 4.3 Document installation steps
  - [x] 4.4 Document development commands
  - [x] 4.5 Document project structure

- [x] **Task 5: Verify Configuration** (AC: #1, #5)
  - [x] 5.1 Run `npm run dev` and verify both servers start
  - [x] 5.2 Test hot reload on frontend
  - [x] 5.3 Test backend API access from frontend

## Dev Notes

### Critical Architecture Compliance

**From Architecture Document** [Source: docs/planning-artifacts/architecture.md]:

1. **Monorepo Structure:**
   - Root directory orchestrates both projects
   - `/backend` - Laravel 12 API
   - `/frontend` - Vue 3 SPA
   - Shared tooling via root-level configuration

2. **Development Workflow:**
   - Run backend and frontend dev servers concurrently
   - Backend: `php artisan serve` on port 8000
   - Frontend: `npm run dev` (Vite) on port 5173
   - Frontend proxies API requests to backend

3. **Environment Variables:**
   - Backend: `.env` in `/backend` (Laravel standard)
   - Frontend: `.env` in `/frontend` (Vite `VITE_` prefix)
   - Root: `.env.example` for documentation only

### Project Structure Requirements

**Root Directory Structure:**
```
weact-project/
├── README.md                 # Project documentation
├── package.json              # Root scripts for development
├── .gitignore                # Comprehensive gitignore
├── .env.example              # Environment documentation
├── backend/                  # Laravel 12 API (Story 1.1 ✅)
│   ├── .env
│   ├── .env.example
│   └── ...
└── frontend/                 # Vue 3 SPA (Story 1.2 ✅)
    ├── .env
    ├── .env.example
    └── ...
```

### Implementation Details

**Root package.json:**
```json
{
  "name": "weact",
  "version": "1.0.0",
  "description": "WEACT - Platform connecting Faces with Producers",
  "private": true,
  "scripts": {
    "dev": "concurrently -n backend,frontend -c blue,green \"npm run dev:backend\" \"npm run dev:frontend\"",
    "dev:backend": "cd backend && php artisan serve",
    "dev:frontend": "cd frontend && npm run dev",
    "build": "cd frontend && npm run build",
    "build:check": "cd frontend && npm run build && npm run type-check",
    "install:all": "cd backend && composer install && cd ../frontend && npm install",
    "lint": "cd frontend && npm run lint",
    "test:backend": "cd backend && php artisan test",
    "test:frontend": "cd frontend && npm run test:unit"
  },
  "devDependencies": {
    "concurrently": "^8.2.2"
  }
}
```

**Root .gitignore:**
```gitignore
# Dependencies
node_modules/
vendor/

# Environment files
.env
.env.local
.env.*.local

# Build outputs
dist/
build/

# IDE
.idea/
.vscode/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db

# Logs
*.log
npm-debug.log*

# Laravel specific
backend/storage/*.key
backend/storage/logs/*
backend/bootstrap/cache/*

# Frontend specific
frontend/dist/
frontend/coverage/
```

**Root .env.example:**
```env
# WEACT Environment Configuration
# ================================
# This file documents all required environment variables.
# Copy this to .env and fill in your values.
# Note: Actual .env files are in /backend and /frontend directories.

# Database (configure in backend/.env)
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=weact
# DB_USERNAME=root
# DB_PASSWORD=

# API Configuration (configure in frontend/.env)
# VITE_API_BASE_URL=http://localhost:8000/api/v1
# VITE_APP_NAME=WEACT

# See backend/.env.example and frontend/.env.example for full configuration.
```

**README.md Structure:**
```markdown
# WEACT

Platform connecting Faces (talents) with Producers for creative missions.

## Prerequisites

- PHP 8.2+
- Composer
- Node.js 20+
- MySQL 8.0+

## Installation

1. Clone the repository
2. Install dependencies: `npm run install:all`
3. Configure environment files
4. Run migrations: `cd backend && php artisan migrate`
5. Start development: `npm run dev`

## Development

- `npm run dev` - Start both backend and frontend
- `npm run dev:backend` - Start Laravel only
- `npm run dev:frontend` - Start Vue only
- `npm run build` - Build frontend for production

## Project Structure

- `/backend` - Laravel 12 REST API
- `/frontend` - Vue 3 SPA with Tailwind CSS
```

### Previous Story Learnings

**From Story 1.1 (Laravel Backend):**
- Backend runs on `http://localhost:8000`
- API health check: `GET /api/v1/health`
- CORS configured for `localhost:5173`

**From Story 1.2 (Vue Frontend):**
- Frontend runs on `http://localhost:5173`
- Vite proxy configured for `/api` requests
- Environment: `VITE_API_BASE_URL=http://localhost:8000/api/v1`

### Testing Verification

After completing all tasks, verify:
1. `npm run dev` starts both servers simultaneously
2. Frontend accessible at `http://localhost:5173`
3. Backend accessible at `http://localhost:8000`
4. API calls from frontend reach backend (test with health endpoint)
5. Hot reload works on both frontend and backend changes

### Future Story Dependencies

This story completes the **project initialization** foundation for:
- **Story 1.4**: Database schema (will use `npm run dev:backend`)
- **All subsequent stories**: Will use the monorepo development workflow

### Anti-Patterns to Avoid

- ❌ Do NOT put actual secrets in root `.env.example` - documentation only
- ❌ Do NOT commit `.env` files - only `.env.example`
- ❌ Do NOT run frontend build in development - use Vite dev server
- ❌ Do NOT hardcode ports - use configuration

## References

- [Source: docs/planning-artifacts/architecture.md#Project-Structure-&-Boundaries] - Monorepo structure
- [Source: docs/planning-artifacts/architecture.md#Infrastructure-&-Deployment] - Development workflow
- [Source: _bmad-output/project-context.md#Development-Workflow-Rules] - Git workflow
- [Source: _bmad-output/planning-artifacts/epics.md#Story-1.3] - Original story definition

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
- Root `package.json` created with `concurrently` for running both servers
- Scripts configured: `dev`, `dev:backend`, `dev:frontend`, `build`, `install:all`, `lint`, `test:backend`, `test:frontend`
- Comprehensive `.gitignore` created covering dependencies, env files, build artifacts, IDE files, OS files, and project-specific exclusions
- Root `.env.example` created with documentation for all environment variables
- Detailed `README.md` created with prerequisites, installation steps, development commands, project structure, and code style guidelines

**Verification Results:**
- `npm run dev` starts both servers concurrently ✅
- Backend starts on http://localhost:8000 ✅
- Frontend starts on http://localhost:5173 ✅
- Color-coded output shows [backend] in blue and [frontend] in green ✅

### Files Created/Modified

- [x] `package.json` - Root package with concurrent scripts and concurrently dependency
- [x] `node_modules/` - Dependencies installed (concurrently ^8.2.2)
- [x] `package-lock.json` - Lock file for npm dependencies
- [x] `.gitignore` - Comprehensive gitignore for monorepo
- [x] `.env.example` - Environment documentation with all variables
- [x] `README.md` - Complete project documentation

