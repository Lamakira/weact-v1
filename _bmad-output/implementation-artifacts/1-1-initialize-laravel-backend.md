# Story 1.1: Initialize Laravel Backend

Status: done

## Story

As a **developer**,
I want **a Laravel 12 backend project with Sanctum configured**,
So that **I can build the API with proper authentication support**.

## Acceptance Criteria

1. **Given** an empty project directory
   **When** I run the Laravel initialization commands
   **Then** a `/backend` directory is created with Laravel 12

2. **Given** the Laravel project is created
   **When** I check the installed packages
   **Then** Laravel Sanctum is installed and configured

3. **Given** Sanctum is installed
   **When** I check the configuration
   **Then** the `.env.example` contains required environment variables for database, app URL, and Sanctum

4. **Given** a `.env` file is created from `.env.example`
   **When** I configure database credentials
   **Then** the database connection is configurable via environment variables

5. **Given** Sanctum is published
   **When** I verify the middleware configuration
   **Then** Sanctum middleware is properly registered for API routes

## Tasks / Subtasks

- [x] **Task 1: Create Laravel 12 Project** (AC: #1)
  - [x] 1.1 Navigate to project root directory
  - [x] 1.2 Run `composer create-project laravel/laravel backend`
  - [x] 1.3 Verify Laravel 12 is installed (`php artisan --version`)

- [x] **Task 2: Install and Configure Laravel Sanctum** (AC: #2, #5)
  - [x] 2.1 Navigate to `/backend` directory
  - [x] 2.2 Run `composer require laravel/sanctum`
  - [x] 2.3 Run `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`
  - [x] 2.4 Verify `config/sanctum.php` exists
  - [x] 2.5 Add `Laravel\Sanctum\HasApiTokens` trait to User model
  - [x] 2.6 Configure Sanctum middleware in `bootstrap/app.php` for API routes

- [x] **Task 3: Configure Environment Variables** (AC: #3, #4)
  - [x] 3.1 Update `.env.example` with required variables
  - [x] 3.2 Set `APP_URL=http://localhost:8000`
  - [x] 3.3 Configure database variables (DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
  - [x] 3.4 Add `SANCTUM_STATEFUL_DOMAINS=localhost:5173`
  - [x] 3.5 Add `SESSION_DOMAIN=localhost`

- [x] **Task 4: Configure CORS for SPA** (AC: #5)
  - [x] 4.1 Update `config/cors.php` to allow frontend origin
  - [x] 4.2 Set `supports_credentials` to `true`
  - [x] 4.3 Configure allowed headers and methods

- [x] **Task 5: Verify Installation** (AC: #1-5)
  - [x] 5.1 Run `php artisan serve` and verify Laravel welcome page loads
  - [x] 5.2 Verify Sanctum configuration is correct
  - [x] 5.3 Verify API routes are accessible

## Dev Notes

### Critical Architecture Compliance

**From Architecture Document** [Source: docs/planning-artifacts/architecture.md]:

1. **Technology Stack:**
   - PHP 8.2+ required for Laravel 12
   - Laravel 12 (REST API only - NO Blade views, NO Inertia)
   - Laravel Sanctum 4.x (TOKEN-BASED authentication, not cookie-based)

2. **Sanctum Configuration:**
   - Use token-based mode for API authentication
   - Cross-domain ready for mobile future expansion
   - Stateful domains include Vue dev server (localhost:5173)

3. **API Response Format (for future endpoints):**
   - Success: `{ data: {...}, meta: {...}, message: "..." }`
   - Error: `{ error: { code: "...", message: "...", details: {...} } }`

### Project Structure Requirements

**Backend Directory Structure to Create:**
```
backend/
├── app/
│   ├── Enums/                     # (empty, for future PHP enums)
│   ├── Http/
│   │   ├── Controllers/Api/V1/    # (empty, for versioned controllers)
│   │   ├── Requests/              # (empty, for Form Requests)
│   │   ├── Resources/             # (empty, for API Resources)
│   │   └── Middleware/            # (exists from Laravel)
│   ├── Models/                    # User.php exists
│   ├── Policies/                  # (empty, for authorization)
│   └── Services/                  # (empty, for business logic)
├── database/migrations/           # Default Laravel migrations
├── routes/
│   └── api.php                    # API routes (exists)
├── config/
│   ├── sanctum.php                # Published by Sanctum
│   └── cors.php                   # CORS configuration
├── .env.example
└── composer.json
```

### Implementation Commands

```bash
# Step 1: Create Laravel project
composer create-project laravel/laravel backend

# Step 2: Navigate and install Sanctum
cd backend
composer require laravel/sanctum

# Step 3: Publish Sanctum configuration
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

### Key Configuration Files

**app/Models/User.php** - Add HasApiTokens trait:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    // ... rest of model
}
```

**bootstrap/app.php** - Configure Sanctum middleware (Laravel 12 style):
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->statefulApi();
})
```

**config/cors.php** - Key settings:
```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => ['http://localhost:5173'],
'supports_credentials' => true,
```

**.env.example** - Required variables:
```env
APP_NAME=WEACT
APP_ENV=local
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=weact
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DOMAIN=localhost
```

### Testing Verification

After completing all tasks, verify:
1. `php artisan serve` starts without errors
2. `curl http://localhost:8000` returns Laravel welcome response
3. `php artisan route:list` shows Sanctum routes
4. Configuration files exist: `config/sanctum.php`, `config/cors.php`

### Future Story Dependencies

This story is the **foundation** for:
- **Story 1.2**: Vue frontend initialization (will connect to this backend)
- **Story 1.3**: Monorepo configuration (will orchestrate this with frontend)
- **Story 1.4**: Database schema (will use this Laravel installation)
- **Story 2.x**: All authentication stories depend on Sanctum setup

### Anti-Patterns to Avoid

From [Source: _bmad-output/project-context.md]:
- ❌ Do NOT install Inertia.js - this is a pure REST API backend
- ❌ Do NOT create any Blade views - API-only responses
- ❌ Do NOT use cookie-based Sanctum - use token-based mode
- ❌ Do NOT skip `declare(strict_types=1);` in PHP files

### Critical Rules

1. **PHP Strict Types**: Add `declare(strict_types=1);` to ALL PHP files you create
2. **No Views**: This is an API-only backend - no Blade templates
3. **Sanctum Mode**: Token-based authentication for cross-domain SPA support
4. **Directory Structure**: Pre-create empty directories for future organization

## References

- [Source: docs/planning-artifacts/architecture.md#Starter-Template-Evaluation] - Initialization commands
- [Source: docs/planning-artifacts/architecture.md#Core-Architectural-Decisions] - Authentication decisions
- [Source: docs/planning-artifacts/architecture.md#Project-Structure-&-Boundaries] - Directory structure
- [Source: _bmad-output/project-context.md#Backend] - Technology versions
- [Source: _bmad-output/project-context.md#Laravel-(Backend)] - Framework rules
- [Source: _bmad-output/planning-artifacts/epics.md#Story-1.1] - Original story definition

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
- Laravel 12.45.2 installed successfully via Composer
- Laravel Sanctum 4.2.2 installed and configured for token-based authentication
- `statefulApi()` middleware configured in `bootstrap/app.php`
- CORS configured for Vue frontend at localhost:5173
- Environment variables set for MySQL database and Sanctum stateful domains
- Empty directory structure created for future code organization (Enums, Controllers/Api/V1, Services, Policies)
- API routes file created with health check endpoint following envelope response format
- All PHP files include `declare(strict_types=1);`

**Verification Results:**
- `php artisan serve` starts without errors ✅
- `/api/v1/health` endpoint returns correct envelope format ✅
- Sanctum routes visible in `php artisan route:list` ✅
- All configuration files exist and are properly configured ✅

### Files Created/Modified

- [x] `backend/` - New Laravel 12.45.2 project directory
- [x] `backend/app/Models/User.php` - Added `declare(strict_types=1);` and `HasApiTokens` trait
- [x] `backend/bootstrap/app.php` - Added `declare(strict_types=1);`, API routing, and `statefulApi()` middleware
- [x] `backend/config/cors.php` - Added `declare(strict_types=1);`, configured origins and `supports_credentials`
- [x] `backend/routes/api.php` - Created with health check endpoint and protected user route
- [x] `backend/.env.example` - Updated with WEACT config, MySQL, Sanctum domains
- [x] `backend/.env` - Updated with local development configuration
- [x] `backend/app/Enums/.gitkeep` - Created empty directory
- [x] `backend/app/Http/Controllers/Api/V1/.gitkeep` - Created empty directory
- [x] `backend/app/Http/Requests/.gitkeep` - Created empty directory
- [x] `backend/app/Http/Resources/.gitkeep` - Created empty directory
- [x] `backend/app/Policies/.gitkeep` - Created empty directory
- [x] `backend/app/Services/.gitkeep` - Created empty directory

