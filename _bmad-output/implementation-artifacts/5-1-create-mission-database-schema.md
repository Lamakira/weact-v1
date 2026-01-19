# Story 5.1: Create Mission Database Schema

Status: ready-for-dev

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

- [ ] Task 1: Create MissionStatus enum (AC: #1, #3)
  - [ ] Create `app/Enums/MissionStatus.php` with values: draft, published, closed, completed
  - [ ] Add `label()` method for French display names

- [ ] Task 2: Create MissionType enum (AC: #1)
  - [ ] Create `app/Enums/MissionType.php` with values: publicite, film, court_metrage, clip_musical, autre
  - [ ] Add `label()` method for French display names

- [ ] Task 3: Create MissionGender enum (AC: #1)
  - [ ] Create `app/Enums/MissionGender.php` with values: homme, femme, tous
  - [ ] Add `label()` method for French display names

- [ ] Task 4: Create missions table migration (AC: #1, #2, #3)
  - [ ] Create migration file `create_missions_table.php`
  - [ ] Add all required columns with appropriate types
  - [ ] Add foreign key constraint to `producers` table with cascade delete
  - [ ] Add indexes for commonly queried columns (producer_id, status, date_tournage, date_limite_candidature)
  - [ ] Set default value for status column to 'draft'

- [ ] Task 5: Create Mission model (AC: #1, #2, #4)
  - [ ] Create `app/Models/Mission.php`
  - [ ] Define `$fillable` with all editable fields
  - [ ] Define `$casts` for enums and dates
  - [ ] Add `producer()` BelongsTo relationship
  - [ ] Add appropriate scopes for status filtering

- [ ] Task 6: Update Producer model (AC: #4)
  - [ ] Add `missions()` HasMany relationship to Producer model

- [ ] Task 7: Create Mission factory (for testing)
  - [ ] Create `database/factories/MissionFactory.php`
  - [ ] Define realistic default values for all fields
  - [ ] Add states for different mission statuses

- [ ] Task 8: Create feature tests (AC: #1, #2, #3, #4)
  - [ ] Test migration creates table with all columns
  - [ ] Test foreign key constraint works correctly
  - [ ] Test status defaults to 'draft'
  - [ ] Test Producer->missions relationship
  - [ ] Test Mission->producer relationship
  - [ ] Test enum casting works correctly

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

{{agent_model_name_version}}

### Debug Log References

### Completion Notes List

### File List
