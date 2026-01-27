# Story 5.1: Create Mission Database Schema

Status: done

## Story

As a **developer**,
I want **the missions table with all required fields**,
so that **Producers can publish detailed casting calls**.

## Acceptance Criteria

1. **Given** the database migration runs **When** the missions table is created **Then** it includes all required fields: id, producer_id, titre, description, date_tournage, profil_recherche, budget, date_limite_candidature, nombre_faces_voulu, type_mission, genre_voulu, lieu, duree, status, timestamps
2. **Given** a mission is created **When** producer_id is set **Then** the foreign key to producers table is properly indexed
3. **Given** a mission is created without explicit status **When** saved **Then** status defaults to 'draft'
4. **Given** the Mission model exists **When** queried **Then** relationships to Producer are properly defined

## Tasks / Subtasks

- [x] Task 1: Create MissionStatus enum (AC: #1, #3)
  - [x] Create `app/Enums/MissionStatus.php` with values: draft, published, closed, completed
  - [x] Add `label()` method for French display names

- [x] Task 2: Create MissionType enum (AC: #1)
  - [x] Create `app/Enums/MissionType.php` with values: publicite, film, court_metrage, clip_musical, autre
  - [x] Add `label()` method for French display names

- [x] Task 3: Create MissionGender enum (AC: #1)
  - [x] Create `app/Enums/MissionGender.php` with values: homme, femme, tous
  - [x] Add `label()` method for French display names

- [x] Task 4: Create missions table migration (AC: #1, #2, #3)
  - [x] Create migration file `create_missions_table.php`
  - [x] Add all required columns with appropriate types
  - [x] Add foreign key constraint to `producers` table with cascade delete
  - [x] Add indexes for commonly queried columns (producer_id, status, date_tournage, date_limite_candidature)
  - [x] Set default value for status column to 'draft'

- [x] Task 5: Create Mission model (AC: #1, #2, #4)
  - [x] Create `app/Models/Mission.php`
  - [x] Define `$fillable` with all editable fields
  - [x] Define `$casts` for enums and dates
  - [x] Add `producer()` BelongsTo relationship
  - [x] Add appropriate scopes for status filtering

- [x] Task 6: Update Producer model (AC: #4)
  - [x] Add `missions()` HasMany relationship to Producer model

- [x] Task 7: Create Mission factory (for testing)
  - [x] Create `database/factories/MissionFactory.php`
  - [x] Define realistic default values for all fields
  - [x] Add states for different mission statuses

- [x] Task 8: Create feature tests (AC: #1, #2, #3, #4)
  - [x] Test migration creates table with all columns
  - [x] Test foreign key constraint works correctly
  - [x] Test status defaults to 'draft'
  - [x] Test Producer->missions relationship
  - [x] Test Mission->producer relationship
  - [x] Test enum casting works correctly

## Dev Notes

### Architecture Patterns

- **Database Naming**: snake_case, plural tables, `{singular}_id` for FKs [Source: docs/planning-artifacts/architecture.md#Naming Patterns]
- **Enums**: PHP 8.1+ backed enums in `app/Enums/` [Source: docs/planning-artifacts/architecture.md#Backend Structure]
- **Models**: Eloquent models in `app/Models/` [Source: docs/planning-artifacts/architecture.md#Backend Structure]
- **Testing**: Feature tests in `tests/Feature/` [Source: docs/planning-artifacts/architecture.md#Testing Framework]

### Database Schema Details

```php
Schema::create('missions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('producer_id')->constrained('producers')->cascadeOnDelete();
    $table->string('titre', 150);
    $table->text('description');
    $table->date('date_tournage');
    $table->text('profil_recherche');
    $table->unsignedInteger('budget'); // XOF in integers
    $table->date('date_limite_candidature');
    $table->unsignedSmallInteger('nombre_faces_voulu')->default(1);
    $table->string('type_mission', 50); // enum: publicite, film, court_metrage, clip_musical, autre
    $table->string('genre_voulu', 20); // enum: homme, femme, tous
    $table->string('lieu', 150);
    $table->string('duree', 100); // e.g., "2 jours", "4 heures"
    $table->string('status', 20)->default('draft'); // enum: draft, published, closed, completed
    $table->timestamps();

    // Indexes for common queries
    $table->index('status');
    $table->index('date_tournage');
    $table->index('date_limite_candidature');
});
```

### Enum Values Reference

**MissionStatus** (workflow from docs/weact-brief.md):
- `draft` - Initial state, not visible publicly
- `published` - Visible to Faces, accepting candidatures
- `closed` - No longer accepting candidatures
- `completed` - Mission finished, ratings enabled

**MissionType** (from docs/weact-brief.md line 99):
- `publicite` - Publicité
- `film` - Film
- `court_metrage` - Court-métrage
- `clip_musical` - Clip musical
- `autre` - Autre

**MissionGender** (from docs/weact-brief.md line 100):
- `homme` - Homme
- `femme` - Femme
- `tous` - Homme et Femme

### Project Structure Notes

Files to create:
```
backend/
├── app/
│   ├── Enums/
│   │   ├── MissionStatus.php
│   │   ├── MissionType.php
│   │   └── MissionGender.php
│   └── Models/
│       └── Mission.php
├── database/
│   ├── factories/
│   │   └── MissionFactory.php
│   └── migrations/
│       └── 2026_xx_xx_xxxxxx_create_missions_table.php
└── tests/
    └── Feature/
        └── Mission/
            └── MissionSchemaTest.php
```

Files to modify:
```
backend/app/Models/Producer.php  # Add missions() relationship
```

### Testing Standards

- Use RefreshDatabase trait
- Test all acceptance criteria
- Verify enum casting works
- Test relationship loading with eager loading
- Verify index creation via Schema::hasIndex() or raw queries

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 5.1]
- [Source: docs/planning-artifacts/architecture.md#Data Architecture]
- [Source: docs/planning-artifacts/architecture.md#Naming Patterns]
- [Source: docs/weact-brief.md#Fonctionnalités Producteur - Publier une mission]
- [Source: docs/weact-brief.md#WORKFLOW MÉTIER MVP]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

- No debug issues encountered

### Completion Notes List

- Created 3 PHP enums (MissionStatus, MissionType, MissionGender) with French labels
- Created missions table migration with all 14 required fields plus timestamps
- Migration includes foreign key to producers with cascade delete
- Migration includes indexes on status, date_tournage, date_limite_candidature
- Created Mission model with proper casts for enums and dates
- Added $attributes default for status = 'draft' in model
- Added status filtering scopes (draft, published, closed, completed, acceptingCandidatures)
- Updated Producer model with missions() HasMany relationship
- Created MissionFactory with realistic defaults and states for all statuses
- Created comprehensive test suite with 19 tests covering all acceptance criteria
- All 322 backend tests pass
- All 757 frontend tests pass

**Code Review Fixes (2026-01-19):**
- Added composite index on (status, date_limite_candidature) for acceptingCandidatures scope performance
- Added index on type_mission for future filtering (story 5-9)
- Updated MissionFactory to use realistic Benin cities instead of random worldwide cities
- Added values() static helper method to all 3 mission enums for validation rules
- Added test for enum values() helper
- All 323 backend tests pass after fixes

### File List

**Created:**
- backend/app/Enums/MissionStatus.php
- backend/app/Enums/MissionType.php
- backend/app/Enums/MissionGender.php
- backend/app/Models/Mission.php
- backend/database/migrations/2026_01_19_130801_create_missions_table.php
- backend/database/migrations/2026_01_19_171522_add_missions_indexes.php
- backend/database/factories/MissionFactory.php
- backend/tests/Feature/Mission/MissionSchemaTest.php

**Modified:**
- backend/app/Models/Producer.php (added missions() relationship)

## Change Log

- 2026-01-19: Initial implementation of mission database schema (Story 5.1)
- 2026-01-19: Code review fixes - added performance indexes, values() helper, realistic Benin cities in factory
