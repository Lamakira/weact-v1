# Story 3.8.1: Face Experience Dates Enhancement

Status: done

## Story

As a **Face**,
I want **to specify start and end dates for my professional experiences**,
So that **producers can see exactly when and how long I worked on each project**.

## Acceptance Criteria

1. **Given** I am adding an experience, **When** I fill the form, **Then** I can enter a start date (day-month-year)

2. **Given** I am adding an experience, **When** I fill the form, **Then** I can optionally enter an end date (day-month-year) or mark it as "ongoing"

3. **Given** an experience has both start and end dates, **When** I view my profile, **Then** I see the date range (e.g., "15/01/2023 - 20/03/2024")

4. **Given** an experience is ongoing, **When** I view my profile, **Then** I see "15/01/2023 - Présent"

5. **Given** I enter an end date, **When** I submit the form, **Then** the end date must be after or equal to the start date

6. **Given** I view my experiences list, **When** multiple experiences exist, **Then** they are sorted by start date descending (most recent first)

7. **Given** I submit invalid dates, **When** the form is validated, **Then** I see appropriate French error messages

## Tasks / Subtasks

### Task 1: Create Database Migration (AC: #1, #2)

- [x] 1.1 Create migration to modify `experiences` table:
  - Remove `annee` column
  - Add `date_debut` (date, required)
  - Add `date_fin` (date, nullable - null means ongoing)
- [x] 1.2 Update index: replace `annee` index with `date_debut` index
- [x] 1.3 Run migration and verify schema

### Task 2: Update Experience Model (AC: #3, #4, #6)

- [x] 2.1 Update `$fillable` array: replace `annee` with `date_debut`, `date_fin`
- [x] 2.2 Add `$casts` for date fields
- [x] 2.3 Update `scopeOrderedByYear()` to `scopeOrderedByDate()` - order by `date_debut` DESC
- [x] 2.4 Add accessor `is_ongoing` - returns true if `date_fin` is null
- [x] 2.5 Add accessor `formatted_period` - returns formatted date range string

### Task 3: Update Form Requests (AC: #1, #2, #5, #7)

- [x] 3.1 Update `StoreExperienceRequest.php`:
  ```php
  'date_debut' => ['required', 'date', 'before_or_equal:today'],
  'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut', 'before_or_equal:today'],
  ```
- [x] 3.2 Update `UpdateExperienceRequest.php` with same rules
- [x] 3.3 Add French error messages:
  - `date_debut.required` → "La date de début est requise"
  - `date_debut.date` → "La date de début n'est pas valide"
  - `date_debut.before_or_equal` → "La date de début ne peut pas être dans le futur"
  - `date_fin.date` → "La date de fin n'est pas valide"
  - `date_fin.after_or_equal` → "La date de fin doit être après la date de début"
  - `date_fin.before_or_equal` → "La date de fin ne peut pas être dans le futur"

### Task 4: Update Experience Service (AC: #1, #2)

- [x] 4.1 Update `createExperience()` to handle `date_debut` and `date_fin`
- [x] 4.2 Update `updateExperience()` to handle `date_debut` and `date_fin`

### Task 5: Update Experience Resource (AC: #3, #4)

- [x] 5.1 Update `ExperienceResource.php`:
  - Remove `annee`
  - Add `date_debut` (formatted as d/m/Y)
  - Add `date_fin` (formatted as d/m/Y or null)
  - Add `is_ongoing` (boolean)
  - Add `formatted_period` (string, e.g., "15/01/2023 - 20/03/2024" or "15/01/2023 - Présent")

### Task 6: Update Backend Tests (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 6.1 Update `ExperienceTest.php`:
  - Update all tests to use `date_debut` and `date_fin` instead of `annee`
  - Add test for ongoing experience (null `date_fin`)
  - Add test for date validation (end before start)
  - Add test for future date validation
  - Update ordering test to use `date_debut`
- [x] 6.2 Update `ExperienceFactory.php` to generate dates

### Task 7: Update Frontend Types (AC: #1, #2, #3, #4)

- [x] 7.1 Update `types.ts`:
  ```typescript
  interface Experience {
    id: number
    titre: string
    description: string | null
    date_debut: string  // ISO date string
    date_fin: string | null  // ISO date string or null for ongoing
    is_ongoing: boolean
    formatted_period: string
    created_at: string
    updated_at: string
  }

  interface ExperienceFormData {
    titre: string
    description?: string | null
    date_debut: string  // YYYY-MM-DD format
    date_fin?: string | null  // YYYY-MM-DD format or null
  }
  ```

### Task 8: Update useExperiences Composable (AC: #6)

- [x] 8.1 Update `sortByYearDesc()` to `sortByDateDesc()` - sort by `date_debut`
- [x] 8.2 Update any date-related logic

### Task 9: Update ExperienceForm Component (AC: #1, #2, #5, #7)

- [x] 9.1 Replace year input with two date inputs:
  - "Date de début *" (required date picker)
  - "Date de fin" (optional date picker with "En cours" checkbox)
- [x] 9.2 Add "En cours" checkbox that clears/disables `date_fin`
- [x] 9.3 Update form data handling for dates
- [x] 9.4 Update validation error display for date fields
- [x] 9.5 Set max date to today for both inputs

### Task 10: Update ExperienceCard Component (AC: #3, #4)

- [x] 10.1 Replace year badge with date period display
- [x] 10.2 Show `formatted_period` from API (e.g., "15/01/2023 - Présent")
- [x] 10.3 Style the date period appropriately

### Task 11: Update Frontend Tests (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 11.1 Update `useExperiences.spec.ts`:
  - Update all mock data to use dates instead of year
  - Update sorting tests for date-based ordering
- [x] 11.2 Update `ExperienceForm.spec.ts`:
  - Add tests for date inputs
  - Add tests for "En cours" checkbox
  - Add tests for date validation errors
- [x] 11.3 Update `ExperienceCard.spec.ts`:
  - Update to test formatted_period display
- [x] 11.4 Update `ExperiencesList.spec.ts`:
  - Update mock data to use dates

## Dev Notes

### Database Schema Change

```
experiences table (AFTER):
├── id (bigIncrements)
├── face_id (foreignId → faces, cascadeOnDelete)
├── titre (string, max 150)
├── description (text, nullable)
├── date_debut (date, required)
├── date_fin (date, nullable - null = ongoing)
├── created_at (timestamp)
└── updated_at (timestamp)

Indexes:
├── experiences_face_id_foreign (face_id)
└── experiences_date_debut_index (date_debut)
```

### API Response Example

```json
{
  "data": {
    "id": 1,
    "titre": "Publicité Coca-Cola",
    "description": "Rôle principal dans une publicité nationale",
    "date_debut": "2023-01-15",
    "date_fin": "2023-03-20",
    "is_ongoing": false,
    "formatted_period": "15/01/2023 - 20/03/2023",
    "created_at": "2026-01-14T10:00:00Z",
    "updated_at": "2026-01-14T10:00:00Z"
  }
}
```

### Ongoing Experience Example

```json
{
  "data": {
    "id": 2,
    "titre": "Série TV en cours",
    "description": "Rôle récurrent",
    "date_debut": "2024-06-01",
    "date_fin": null,
    "is_ongoing": true,
    "formatted_period": "01/06/2024 - Présent",
    "created_at": "2026-01-14T10:00:00Z",
    "updated_at": "2026-01-14T10:00:00Z"
  }
}
```

### French Error Messages

| Scenario | Message |
|----------|---------|
| Missing start date | "La date de début est requise" |
| Invalid start date | "La date de début n'est pas valide" |
| Start date in future | "La date de début ne peut pas être dans le futur" |
| Invalid end date | "La date de fin n'est pas valide" |
| End date before start | "La date de fin doit être après la date de début" |
| End date in future | "La date de fin ne peut pas être dans le futur" |

### UI Guidelines

- **Date Format Display**: dd/mm/yyyy (French format)
- **Date Input Format**: Native date picker (browser handles format)
- **Ongoing Checkbox**: "En cours" checkbox next to end date field
- **Period Display**: "15/01/2023 - 20/03/2024" or "15/01/2023 - Présent"

### Migration Strategy

This is a breaking change. The migration should:
1. Add new columns `date_debut` and `date_fin`
2. Migrate existing data: convert `annee` to `date_debut` = "01/01/{annee}", `date_fin` = "31/12/{annee}"
3. Drop `annee` column
4. Update index

### References

- [Source: Story 3.8 - Original implementation]
- [Parent: Epic 3 - Face Profile & Portfolio]

## Change Log

| Date       | Change                                          | Author          |
|------------|-------------------------------------------------|-----------------|
| 2026-01-14 | Story 3.8.1 created - Experience dates enhancement | Claude Opus 4.5 |
| 2026-01-14 | Code review completed - Fixed: client-side date validation, date_fin restoration on checkbox toggle, added 5 new tests | Claude Opus 4.5 |
