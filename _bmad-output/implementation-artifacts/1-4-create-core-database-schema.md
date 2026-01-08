# Story 1.4: Create Core Database Schema

Status: done

## Story

As a **developer**,
I want **the core database migrations for User, Face, Producer, and Admin models**,
So that **the polymorphic authentication system has its foundation**.

## Acceptance Criteria

1. **Given** Laravel is configured with database connection
   **When** I run `php artisan migrate`
   **Then** `users` table is created with: id, email, password, userable_type, userable_id, timestamps

2. **Given** the users migration runs
   **When** I check the users table structure
   **Then** it has proper indexes on email (unique) and userable (morphs index)

3. **Given** the migrations run
   **When** I check the `faces` table
   **Then** it is created with: id, timestamps (basic columns for polymorphic relationship)

4. **Given** the migrations run
   **When** I check the `producers` table
   **Then** it is created with: id, type (enum: agency/particulier), agency_name, first_name, last_name, timestamps

5. **Given** the migrations run
   **When** I check the `admins` table
   **Then** it is created with: id, name, email, password, timestamps (separate from User model)

6. **Given** all migrations complete
   **When** I check the Eloquent models
   **Then** User has `userable` morphTo relationship, Face/Producer have `user` morphOne relationship

## Tasks / Subtasks

- [x] **Task 1: Modify Users Migration** (AC: #1, #2)
  - [x] 1.1 Update existing users migration to add polymorphic columns
  - [x] 1.2 Add `userable_type` and `userable_id` columns
  - [x] 1.3 Remove `name` column (will be on Face/Producer)
  - [x] 1.4 Add unique index on email
  - [x] 1.5 Add composite index on userable_type + userable_id

- [x] **Task 2: Create Faces Migration** (AC: #3)
  - [x] 2.1 Create migration: `php artisan make:migration create_faces_table`
  - [x] 2.2 Add id and timestamps (basic structure)
  - [x] 2.3 Note: Profile fields will be added in Epic 3

- [x] **Task 3: Create Producers Migration** (AC: #4)
  - [x] 3.1 Create migration: `php artisan make:migration create_producers_table`
  - [x] 3.2 Add id, type (enum), agency_name, first_name, last_name, timestamps
  - [x] 3.3 Add index on type column

- [x] **Task 4: Create Admins Migration** (AC: #5)
  - [x] 4.1 Create migration: `php artisan make:migration create_admins_table`
  - [x] 4.2 Add id, name, email (unique), password, timestamps
  - [x] 4.3 Add unique index on email

- [x] **Task 5: Create/Update Eloquent Models** (AC: #6)
  - [x] 5.1 Update User model with `userable` morphTo relationship
  - [x] 5.2 Create Face model with `user` morphOne relationship
  - [x] 5.3 Create Producer model with `user` morphOne relationship and type enum
  - [x] 5.4 Create Admin model (standalone, not polymorphic)
  - [x] 5.5 Create ProducerType enum

- [x] **Task 6: Run and Verify Migrations** (AC: #1-6)
  - [x] 6.1 Create MySQL database `weact` if not exists
  - [x] 6.2 Run `php artisan migrate`
  - [x] 6.3 Verify all tables created with correct structure
  - [x] 6.4 Test model relationships work

## Dev Notes

### Critical Architecture Compliance

**From Architecture Document** [Source: docs/planning-artifacts/architecture.md]:

1. **Polymorphic User Architecture:**
   - `User` model has `userable` morphTo relationship
   - Points to either `Face` or `Producer` model
   - Admin is a SEPARATE model (not a User role)
   - User can be EITHER Face OR Producer, never both

2. **Database Naming Conventions:**
   - Tables: snake_case, plural (`users`, `faces`, `producers`, `admins`)
   - Columns: snake_case (`userable_type`, `userable_id`, `created_at`)
   - Foreign Keys: `{singular}_id`
   - Indexes: `{table}_{column}_index`

3. **PHP Requirements:**
   - `declare(strict_types=1);` in ALL PHP files
   - Use PHP 8.2+ Enums for type values
   - Type hint all method parameters and return types

### Database Schema Design

**users table:**
```sql
id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
email           VARCHAR(255) UNIQUE NOT NULL
password        VARCHAR(255) NOT NULL
userable_type   VARCHAR(255) NULL  -- 'App\Models\Face' or 'App\Models\Producer'
userable_id     BIGINT UNSIGNED NULL
email_verified_at TIMESTAMP NULL
remember_token  VARCHAR(100) NULL
created_at      TIMESTAMP NULL
updated_at      TIMESTAMP NULL

INDEX users_userable_type_userable_id_index (userable_type, userable_id)
UNIQUE INDEX users_email_unique (email)
```

**faces table:**
```sql
id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
created_at      TIMESTAMP NULL
updated_at      TIMESTAMP NULL

-- Note: Profile fields (photo, bio, videos, etc.) will be added in Epic 3
```

**producers table:**
```sql
id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
type            ENUM('agency', 'particulier') NOT NULL
agency_name     VARCHAR(255) NULL  -- Only for type='agency'
first_name      VARCHAR(255) NULL  -- Only for type='particulier'
last_name       VARCHAR(255) NULL  -- Only for type='particulier'
created_at      TIMESTAMP NULL
updated_at      TIMESTAMP NULL

INDEX producers_type_index (type)
```

**admins table:**
```sql
id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
name            VARCHAR(255) NOT NULL
email           VARCHAR(255) UNIQUE NOT NULL
password        VARCHAR(255) NOT NULL
remember_token  VARCHAR(100) NULL
created_at      TIMESTAMP NULL
updated_at      TIMESTAMP NULL

UNIQUE INDEX admins_email_unique (email)
```

### Eloquent Model Relationships

**User Model:**
```php
// User → Face or Producer (polymorphic)
public function userable(): MorphTo
{
    return $this->morphTo();
}
```

**Face Model:**
```php
// Face → User (inverse polymorphic)
public function user(): MorphOne
{
    return $this->morphOne(User::class, 'userable');
}
```

**Producer Model:**
```php
// Producer → User (inverse polymorphic)
public function user(): MorphOne
{
    return $this->morphOne(User::class, 'userable');
}
```

### ProducerType Enum

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum ProducerType: string
{
    case Agency = 'agency';
    case Particulier = 'particulier';
}
```

### Previous Story Learnings

**From Story 1.1-1.3:**
- Backend is at `/backend` with Laravel 12
- User model already exists with HasApiTokens trait
- Database configured for MySQL in `.env`
- `php artisan serve` runs on port 8000

### Testing Verification

After completing all tasks, verify:
1. `php artisan migrate` runs without errors
2. All 4 tables exist: `users`, `faces`, `producers`, `admins`
3. User model can morph to Face: `$user->userable` returns Face instance
4. User model can morph to Producer: `$user->userable` returns Producer instance
5. Face/Producer can access user: `$face->user` returns User instance
6. Admin is standalone and functional

### Future Story Dependencies

This story completes **Epic 1: Project Initialization** and enables:
- **Epic 2**: Authentication (registration, login will use these models)
- **Epic 3**: Face Profile (will add columns to faces table)
- **Epic 4**: Producer Profile (will add columns to producers table)
- **Epic 13**: Admin features (uses Admin model)

### Anti-Patterns to Avoid

From [Source: _bmad-output/project-context.md]:
- ❌ Do NOT put Admin as a role on User - it's a separate model
- ❌ Do NOT allow User to be both Face AND Producer - exclusive polymorphic
- ❌ Do NOT use string for producer type - use PHP Enum
- ❌ Do NOT skip `declare(strict_types=1);` in any PHP file
- ❌ Do NOT forget indexes on foreign keys and frequently queried columns

## References

- [Source: docs/planning-artifacts/architecture.md#Authentication-&-Security] - Polymorphic user architecture
- [Source: docs/planning-artifacts/architecture.md#Naming-Patterns] - Database naming conventions
- [Source: _bmad-output/project-context.md#Critical-Implementation-Rules] - PHP rules
- [Source: _bmad-output/planning-artifacts/epics.md#Story-1.4] - Original story definition

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
- Modified users migration with polymorphic `userable_type` and `userable_id` columns
- Created faces table migration (basic structure for Epic 3 expansion)
- Created producers table migration with type enum (agency/particulier) and profile fields
- Created admins table migration (standalone authentication model)
- Updated User model with `userable()` morphTo relationship
- Created Face model with `user()` morphOne relationship
- Created Producer model with `user()` morphOne relationship and ProducerType enum cast
- Created Admin model as standalone Authenticatable (not polymorphic)
- Created ProducerType PHP enum with Agency and Particulier cases

**Database Tables Created:**
- `users` - with polymorphic columns and indexes ✅
- `faces` - basic structure ✅
- `producers` - with type enum, agency_name, first_name, last_name ✅
- `admins` - standalone with name, email, password ✅

**Verification Results:**
- All 4 tables created successfully ✅
- Polymorphic index on users table ✅
- Type index on producers table ✅
- Unique email indexes on users and admins ✅

### Files Created/Modified

- [x] `backend/database/migrations/0001_01_01_000000_create_users_table.php` - Added polymorphic columns
- [x] `backend/database/migrations/2026_01_07_171716_create_faces_table.php` - New migration
- [x] `backend/database/migrations/2026_01_07_171749_create_producers_table.php` - New migration with enum
- [x] `backend/database/migrations/2026_01_07_171912_create_admins_table.php` - New migration
- [x] `backend/app/Models/User.php` - Updated with userable relationship
- [x] `backend/app/Models/Face.php` - New model
- [x] `backend/app/Models/Producer.php` - New model with enum cast
- [x] `backend/app/Models/Admin.php` - New model
- [x] `backend/app/Enums/ProducerType.php` - New enum

