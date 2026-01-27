# Story 5.11: Producer Mission Count Exposure

Status: done

## Story

As a **Face or visitor viewing a Producer's profile**,
I want **to see the actual number of missions they have published**,
so that **I can evaluate their activity level and credibility on the platform**.

## Acceptance Criteria

1. **Given** I am viewing a Producer's public profile **When** they have published missions **Then** I see the actual count of published missions (not 0)

2. **Given** the Producer has missions in various statuses (draft, published, closed, completed) **When** I view their profile **Then** only published missions are counted (not draft, closed, or completed)

3. **Given** I view a Producer's profile via the public API **When** the response is returned **Then** `missions_count` reflects the real count from the database

4. **Given** a Producer has 0 published missions **When** I view their profile **Then** `missions_count` shows 0 (not a placeholder, but real data)

5. **Given** the existing frontend already displays `missions_count` **When** the backend returns real data **Then** the UI shows the correct value without any frontend changes

**(Implements Story 4.4 AC #5 - "I see how many missions they have posted" with real data instead of MVP placeholder)**

## Tasks / Subtasks

- [x] Task 1: Update PublicProducerResource to count real missions (AC: #1, #2, #3, #4)
  - [x] Replace hardcoded `'missions_count' => 0` with actual count
  - [x] Count only published missions using `$this->published_missions_count` accessor
  - [x] Document eager loading pattern for future use (N/A for single producer endpoint)
  - [x] Update the MVP comment to reflect this is now real data

- [x] Task 2: Add missions count relationship method to Producer model (AC: #2)
  - [x] Add `publishedMissionsCount()` accessor for reusability
  - [x] Ensure it filters by `MissionStatus::Published`

- [x] Task 3: Update backend tests (AC: #1, #2, #3, #4)
  - [x] Update `tests/Feature/Public/ProducerProfileTest.php`
  - [x] Test: Producer with 3 published missions shows `missions_count: 3`
  - [x] Test: Producer with published + draft missions only counts published
  - [x] Test: Producer with only closed/completed missions shows `missions_count: 0`
  - [x] Test: Producer with no missions shows `missions_count: 0`

- [x] Task 4: TypeScript and test verification
  - [x] All backend tests pass (no regressions) - 465 tests, 2079 assertions
  - [x] Manual verification: Frontend already displays count correctly (no changes needed)

## Dev Notes

### 🎯 This story fulfills Story 4.4 AC #5

Story 4-4 (Producer Profile Display) created a MVP placeholder:
```php
// MVP placeholders - will be updated when Epic 5 (Missions) is implemented
'missions_count' => 0,
```

Now that Epic 5 (Mission Management) is complete, this story replaces the placeholder with real data.

### Architecture Patterns

**Current PublicProducerResource (to modify):**
```php
// backend/app/Http/Resources/PublicProducerResource.php line 40
'missions_count' => 0, // MVP placeholder - TO BE REPLACED
```

**Target implementation:**
```php
'missions_count' => $this->missions()->where('status', MissionStatus::Published)->count(),
```

Or with a dedicated accessor on the Producer model:
```php
// In Producer model
public function getPublishedMissionsCountAttribute(): int
{
    return $this->missions()->where('status', MissionStatus::Published)->count();
}

// In PublicProducerResource
'missions_count' => $this->published_missions_count,
```

### Existing Relationship

The Producer model already has the `missions()` relationship:
```php
// backend/app/Models/Producer.php line 69-72
public function missions(): HasMany
{
    return $this->hasMany(Mission::class);
}
```

### Mission Status Enum

```php
// backend/app/Enums/MissionStatus.php
enum MissionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Completed = 'completed';
}
```

### Frontend: No Changes Required

The frontend already displays `missions_count` from the API response:
```vue
<!-- frontend/src/pages/public/ProducerProfilePage.vue line 285-289 -->
<div class="text-2xl font-bold text-gray-900" data-testid="missions-count">
  {{ producer.missions_count }}
</div>
<div class="text-xs text-gray-500">Missions publiées</div>
```

The label says "Missions publiées" which aligns perfectly with counting only published missions.

### Test Scenarios

| Scenario | Missions | Expected missions_count |
|----------|----------|-------------------------|
| No missions | 0 total | 0 |
| 3 published | 3 published | 3 |
| 2 published + 1 draft | 3 total | 2 |
| 1 published + 2 closed + 1 completed | 4 total | 1 |
| 2 draft + 1 closed | 3 total, 0 published | 0 |

### Existing Test File

Existing tests in `tests/Feature/Public/ProducerProfileTest.php`:
- Test: Response includes missions_count = 0 (MVP placeholder) → **UPDATE THIS**
- This test should be updated to verify real mission counts

### API Endpoint (unchanged)

```
GET /api/v1/public/producers/{id}

Response includes:
{
  "data": {
    ...
    "missions_count": 3,  // NOW REAL DATA
    ...
  }
}
```

### Performance Consideration

If the public API later supports listing multiple producers, ensure the count query uses `withCount()` for eager loading:
```php
Producer::withCount(['missions' => function ($query) {
    $query->where('status', MissionStatus::Published);
}])->find($id);
```

For the single producer show endpoint, inline count is acceptable.

### Git Intelligence (Recent Commits)

```
d441a13 docs: complete story 5-2 publish mission
8a134de feat(mission): integrate publish mission in producer dashboard
75d9e47 feat(mission): add mission creation feature
```

Story 5-2 (publish mission) is complete, so missions can be created and published.

### References

- [Source: _bmad-output/implementation-artifacts/4-4-producer-profile-display.md - AC #5, MVP placeholder]
- [Source: backend/app/Http/Resources/PublicProducerResource.php - Current implementation]
- [Source: backend/app/Models/Producer.php - missions() relationship]
- [Source: backend/app/Enums/MissionStatus.php - Status enum values]
- [Source: frontend/src/pages/public/ProducerProfilePage.vue - UI already displays count]
- [Source: tests/Feature/Public/ProducerProfileTest.php - Existing tests to update]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

N/A

### Completion Notes List

- Added `publishedMissionsCount()` accessor to Producer model using Laravel's Attribute pattern
- Updated PublicProducerResource to use `$this->published_missions_count` instead of hardcoded 0
- The accessor filters missions by `MissionStatus::Published` status only
- Added 4 comprehensive feature tests covering all mission count scenarios:
  - Producer with no missions (0)
  - Producer with 3 published missions (3)
  - Producer with mixed statuses (only counts published)
  - Producer with only non-published missions (0)
- Frontend requires no changes - already displays `missions_count` from API
- All 5 acceptance criteria satisfied

**Code Review Fixes (3 MEDIUM + 2 LOW):**
- M1: Added 7 unit tests for Producer model accessor (`tests/Unit/Models/ProducerTest.php`)
- M2: Clarified eager loading subtask (N/A for single producer endpoint)
- M3: Added story file to File List section
- L1: Improved PHPDoc for `publishedMissionsCount()` accessor
- L2: Simplified redundant comments in test file
- All 472 backend tests pass with 2087 assertions - no regressions

### Change Log

| Date | Author | Change |
|------|--------|--------|
| 2026-01-26 | SM Agent | Story created - ready-for-dev |
| 2026-01-26 | Dev Agent | All tasks implemented - status: review |
| 2026-01-26 | Code Review | Fixed 3 MEDIUM + 2 LOW issues - status: done |

### File List

**Created:**
- `_bmad-output/implementation-artifacts/5-11-producer-mission-count-exposure.md` - Story file
- `backend/tests/Unit/Models/ProducerTest.php` - Unit tests for Producer model accessor

**Modified:**
- `backend/app/Models/Producer.php` - Added publishedMissionsCount() accessor
- `backend/app/Http/Resources/PublicProducerResource.php` - Use real missions_count
- `backend/tests/Feature/Public/ProducerProfileTest.php` - Added 4 mission count tests
- `_bmad-output/implementation-artifacts/sprint-status.yaml` - Updated story status
