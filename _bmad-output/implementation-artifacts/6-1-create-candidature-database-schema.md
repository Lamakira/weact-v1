# Story 6.1: Create Candidature Database Schema

Status: done

## Story

As a **developer**,
I want **the candidatures table with proper status workflow**,
so that **the application and confirmation process can be tracked**.

## Acceptance Criteria

1. **Given** the database migration runs **When** the candidatures table is created **Then** it includes all required fields: id, face_id, mission_id, message_motivation (nullable text), status, timestamps

2. **Given** a candidature is created **When** face_id is set **Then** the foreign key to faces table is properly indexed and constrained

3. **Given** a candidature is created **When** mission_id is set **Then** the foreign key to missions table is properly indexed and constrained

4. **Given** a candidature is created without explicit status **When** saved **Then** status defaults to 'pending'

5. **Given** the CandidatureStatus enum exists **When** status transitions **Then** it supports: pending, accepted, confirmed, in_progress, completed, rejected

6. **Given** the Candidature model exists **When** queried **Then** relationships to Face and Mission are properly defined

7. **Given** a Face applies to the same mission twice **When** saving **Then** the database enforces uniqueness constraint on (face_id, mission_id)

**(Implements FR34, FR35, FR36, FR37, FR38, FR39, FR40, FR41 foundation)**

## Tasks / Subtasks

- [x] Task 1: Create CandidatureStatus enum (AC: #5)
  - [x] Create `app/Enums/CandidatureStatus.php` with values: pending, accepted, confirmed, in_progress, completed, rejected
  - [x] Add `label()` method for French display names
  - [x] Add `values()` static helper method for validation rules (pattern from MissionStatus)

- [x] Task 2: Create candidatures table migration (AC: #1, #2, #3, #4, #7)
  - [x] Create migration file `create_candidatures_table.php`
  - [x] Add all required columns with appropriate types
  - [x] Add foreign key constraint to `faces` table with cascade delete
  - [x] Add foreign key constraint to `missions` table with cascade delete
  - [x] Add unique constraint on (face_id, mission_id) to prevent duplicate applications
  - [x] Add index on status for filtering
  - [x] Set default value for status column to 'pending'

- [x] Task 3: Create Candidature model (AC: #1, #4, #6)
  - [x] Create `app/Models/Candidature.php`
  - [x] Define `$fillable` with: face_id, mission_id, message_motivation, status
  - [x] Define `$casts` for status enum
  - [x] Add `$attributes` default for status = 'pending'
  - [x] Add `face()` BelongsTo relationship
  - [x] Add `mission()` BelongsTo relationship
  - [x] Add status filtering scopes (pending, accepted, confirmed, inProgress, completed, rejected)

- [x] Task 4: Update Face model (AC: #6)
  - [x] Add `candidatures()` HasMany relationship to Face model

- [x] Task 5: Update Mission model (AC: #6)
  - [x] Add `candidatures()` HasMany relationship to Mission model

- [x] Task 6: Create Candidature factory (for testing)
  - [x] Create `database/factories/CandidatureFactory.php`
  - [x] Define realistic default values for all fields
  - [x] Add states for different candidature statuses
  - [x] Use existing FaceFactory and MissionFactory for relationships

- [x] Task 7: Create feature tests (AC: #1, #2, #3, #4, #5, #6, #7)
  - [x] Test migration creates table with all columns
  - [x] Test foreign key constraint to faces works correctly
  - [x] Test foreign key constraint to missions works correctly
  - [x] Test status defaults to 'pending'
  - [x] Test unique constraint on (face_id, mission_id) prevents duplicates
  - [x] Test Face->candidatures relationship
  - [x] Test Mission->candidatures relationship
  - [x] Test Candidature->face relationship
  - [x] Test Candidature->mission relationship
  - [x] Test enum casting works correctly
  - [x] Test all status scope filters

## Dev Notes

### CRITICAL BUSINESS RULES (from project-context.md)

These rules affect future stories but MUST be architected from the start:

1. **Chat Access Rule**: Chat is unlocked ONLY after candidature status = 'accepted' (Story 7.2)
2. **Rating Rule**: Ratings are possible ONLY after mission.status = 'completed' AND candidature.status = 'completed' (Story 8.x)
3. **2-Step Validation**: Face must confirm (accepted → confirmed) after Producer accepts (Story 6.8)

### Architecture Patterns (Following Story 5-1 Pattern)

- **Database Naming**: snake_case, plural tables, `{singular}_id` for FKs [Source: _bmad-output/project-context.md#Database]
- **Enums**: PHP 8.1+ backed enums in `app/Enums/` [Source: _bmad-output/project-context.md#PHP]
- **Models**: Eloquent models in `app/Models/` with strict_types
- **Testing**: Feature tests in `tests/Feature/` with Pest syntax

### Database Schema Details

```php
Schema::create('candidatures', function (Blueprint $table) {
    $table->id();
    $table->foreignId('face_id')->constrained('faces')->cascadeOnDelete();
    $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
    $table->text('message_motivation')->nullable(); // Optional motivation message
    $table->string('status', 20)->default('pending'); // enum: pending, accepted, confirmed, in_progress, completed, rejected
    $table->timestamps();

    // Unique constraint: A Face can only apply once per mission
    $table->unique(['face_id', 'mission_id']);

    // Index for common queries
    $table->index('status');
});
```

### CandidatureStatus Enum Values

**Status Workflow** (from epics.md and business rules):
- `pending` - Initial state after Face applies, waiting for Producer review
- `accepted` - Producer accepted the candidature, chat unlocked
- `confirmed` - Face confirmed participation (2-step validation complete)
- `in_progress` - Mission is actively being worked on
- `completed` - Mission finished for this Face, ratings enabled
- `rejected` - Producer rejected the candidature (terminal state)

**French Labels:**
- `pending` → "En attente"
- `accepted` → "Acceptée"
- `confirmed` → "Confirmée"
- `in_progress` → "En cours"
- `completed` → "Terminée"
- `rejected` → "Refusée"

### Project Structure (Files to Create)

```
backend/
├── app/
│   ├── Enums/
│   │   └── CandidatureStatus.php
│   └── Models/
│       └── Candidature.php
├── database/
│   ├── factories/
│   │   └── CandidatureFactory.php
│   └── migrations/
│       └── 2026_xx_xx_xxxxxx_create_candidatures_table.php
└── tests/
    └── Feature/
        └── Candidature/
            └── CandidatureSchemaTest.php
```

### Files to Modify

```
backend/app/Models/Face.php      # Add candidatures() relationship
backend/app/Models/Mission.php   # Add candidatures() relationship
```

### Existing Patterns to Follow (from Story 5-1)

**Enum Pattern** (from `backend/app/Enums/MissionStatus.php`):
```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum CandidatureStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Accepted => 'Acceptée',
            self::Confirmed => 'Confirmée',
            self::InProgress => 'En cours',
            self::Completed => 'Terminée',
            self::Rejected => 'Refusée',
        };
    }

    /**
     * Get all enum values as array for validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

**Model Pattern** (from `backend/app/Models/Mission.php`):
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CandidatureStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Candidature extends Model
{
    use HasFactory;

    protected $fillable = [
        'face_id',
        'mission_id',
        'message_motivation',
        'status',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => CandidatureStatus::class,
        ];
    }

    // Relationships
    public function face(): BelongsTo
    {
        return $this->belongsTo(Face::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', CandidatureStatus::Pending);
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', CandidatureStatus::Accepted);
    }

    // ... other scopes for each status
}
```

### Testing Standards

- Use `RefreshDatabase` trait
- Use Pest syntax: `it()`, `test()`, `expect()`
- Test all 7 acceptance criteria
- Verify enum casting works
- Test relationship loading with eager loading
- Verify unique constraint throws exception on duplicate
- All existing tests must pass (currently 465+ backend tests)

### Git Intelligence (Recent Commits from Epic 5)

```
13023cd docs: epic 5 retrospective done
b049331 docs: complete story 5-11 producer mission count exposure
b3e5752 test(producer): add mission count tests
fb498a7 feat(producer): expose real mission count in public profile API
```

Pattern: docs → test → feat commits for each feature

### Previous Story Learnings (from 5-1 and 5-11)

1. **Model accessors**: Use Laravel's `Attribute` pattern for computed properties
2. **Indexes**: Add composite indexes for frequently combined queries
3. **Enum helpers**: Always add `values()` static method for validation rules
4. **Factory states**: Create states for each status to facilitate testing
5. **PHPDoc**: Add proper documentation for all public methods

### Dependencies

- **Depends on**: Epic 5 (Mission model and migrations must exist) ✓ COMPLETED
- **Blocks**: Stories 6-2 through 6-9 (all candidature workflow stories)

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 6.1 - Candidature Database Schema]
- [Source: _bmad-output/planning-artifacts/epics.md#Epic 6 - Status workflow: pending → accepted → confirmed → in_progress → completed, rejected]
- [Source: _bmad-output/project-context.md#Critical Business Rules - Chat access, Ratings, 2-step validation]
- [Source: _bmad-output/project-context.md#Database Naming - snake_case conventions]
- [Source: backend/app/Enums/MissionStatus.php - Enum pattern to follow]
- [Source: backend/app/Models/Mission.php - Model pattern to follow]
- [Source: backend/database/migrations/2026_01_19_130801_create_missions_table.php - Migration pattern]
- [Source: _bmad-output/implementation-artifacts/5-1-create-mission-database-schema.md - Similar schema story pattern]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

No issues encountered during implementation.

### Completion Notes List

- Created CandidatureStatus enum with 6 status values (pending, accepted, confirmed, in_progress, completed, rejected)
- Enum includes French labels and values() helper method following MissionStatus pattern
- Created candidatures table migration with all required fields and constraints
- Migration includes foreign keys to faces and missions with cascade delete
- Migration includes unique constraint on (face_id, mission_id) to prevent duplicate applications
- Migration includes status index for filtering queries
- Created Candidature model with proper relationships, casts, default attributes, and 6 status scopes
- Updated Face model with candidatures() HasMany relationship
- Updated Mission model with candidatures() HasMany relationship (also added HasMany import)
- Created CandidatureFactory with states for all 6 statuses and motivation message variants
- Created comprehensive test suite with 27 tests covering all 7 acceptance criteria
- All 499 backend tests pass with 2146 assertions - no regressions

**Review Fixes Applied:**
- Added composite indexes migration for (mission_id, status) and (face_id, status) for query performance
- Added `allowsChatAccess()` helper method to CandidatureStatus enum for future Story 7.2
- Added `allowsRatings()` helper method to CandidatureStatus enum for future Story 8.x
- Added eager loading test for candidatures with face and mission
- Added tests for allowsChatAccess() and allowsRatings() helper methods
- Final test count: 30 tests (502 total backend tests pass)

### Change Log

| Date       | Author       | Change                                       |
|------------|--------------|----------------------------------------------|
| 2026-01-27 | SM Agent     | Story created - ready-for-dev                |
| 2026-01-27 | Dev Agent    | All tasks implemented - status: review       |
| 2026-01-27 | Review Agent | 4 MEDIUM issues fixed - status: done         |

### File List

**Created:**
- `backend/app/Enums/CandidatureStatus.php` - CandidatureStatus enum with 6 values + allowsChatAccess() + allowsRatings()
- `backend/app/Models/Candidature.php` - Candidature model with relationships and scopes
- `backend/database/migrations/2026_01_27_082003_create_candidatures_table.php` - Migration file
- `backend/database/migrations/2026_01_27_084949_add_candidatures_composite_indexes.php` - Composite indexes for performance
- `backend/database/factories/CandidatureFactory.php` - Factory with states for testing
- `backend/tests/Feature/Candidature/CandidatureSchemaTest.php` - 30 feature tests

**Modified:**
- `backend/app/Models/Face.php` - Added candidatures() HasMany relationship
- `backend/app/Models/Mission.php` - Added candidatures() HasMany relationship and HasMany import
