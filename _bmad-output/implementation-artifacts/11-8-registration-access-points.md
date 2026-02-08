# Story 11.8: Registration Access Points

Status: done

## Story

As a **visitor**,
I want **clear paths to register as Face or Producer on every public page**,
So that **I can easily join the platform from any entry point**.

## Acceptance Criteria

1. **Given** I am on the public Faces list page (`/faces`) **When** the page loads **Then** I see a visible CTA section encouraging registration (e.g., "Rejoignez notre communauté" with both Face and Producer registration links)

2. **Given** I am on the public Missions list page (`/missions`) **When** the page loads **Then** I see a visible CTA section encouraging registration (e.g., "Prêt(e) à postuler ?" for Face registration and "Publiez votre mission" for Producer registration)

3. **Given** I am on any public page (faces list, missions list, face profile, mission detail, landing page) **When** I look at the header **Then** I see registration CTAs ("Devenir une face" and "Poster une mission") — already implemented in AppHeader.vue

4. **Given** I click a "Devenir une face" CTA **When** the navigation completes **Then** I am on `/register/face`

5. **Given** I click a "Poster une mission" / "Publier une mission" CTA **When** the navigation completes **Then** I am on `/register/producer`

6. **Given** I am on a mobile device **When** I view any public page **Then** the registration CTAs are accessible and properly displayed (responsive)

7. **Given** I am logged in **When** I visit any public page **Then** registration CTAs are not shown (replaced by dashboard links) — already handled by AppHeader.vue conditional rendering

## Tasks / Subtasks

- [x] Task 1: Add registration CTA section to PublicFacesView (AC: 1, 4, 5, 6)
  - [x] 1.1 Create a `RegistrationCta.vue` reusable component in `features/public/components/`
  - [x] 1.2 Component accepts props: `variant` ("faces" | "missions"), `class` for styling
  - [x] 1.3 "faces" variant: dual CTA — "Créer mon profil" → `/register/face` (primary) + "Poster une mission" → `/register/producer` (secondary)
  - [x] 1.4 "missions" variant: dual CTA — "Postuler aux missions" → `/register/face` (primary) + "Publier une mission" → `/register/producer` (secondary)
  - [x] 1.5 Insert `<RegistrationCta variant="faces" />` at the bottom of `PublicFacesView.vue` (after the grid, before pagination)
  - [x] 1.6 Write unit test for `RegistrationCta.vue` — both variants render correct links, correct text, responsive classes

- [x] Task 2: Add registration CTA section to PublicMissionsView (AC: 2, 4, 5, 6)
  - [x] 2.1 Insert `<RegistrationCta variant="missions" />` at the bottom of `PublicMissionsView.vue` (after the grid, before pagination)
  - [x] 2.2 Write integration test for PublicMissionsView — CTA section appears with correct links

- [x] Task 3: Write tests for PublicFacesView CTA integration (AC: 1)
  - [x] 3.1 Update `PublicFacesView.spec.ts` — test that CTA section is rendered after data loads
  - [x] 3.2 Test that CTA links point to `/register/face` and `/register/producer`

- [x] Task 4: Verify existing CTAs are complete (AC: 3, 7)
  - [x] 4.1 Verify AppHeader.vue already has registration CTAs for both desktop and mobile — pre-existing (lines 157, 165 desktop; 270, 277 mobile)
  - [x] 4.2 Verify PublicFaceProfileView.vue already has "S'inscrire pour voir" CTAs via LockedContentTeaser — pre-existing (cta-link="/register/producer")
  - [x] 4.3 Verify PublicMissionDetailView.vue already has "Se connecter pour postuler" + "Créez votre compte" CTAs — pre-existing (lines 449-465)
  - [x] 4.4 Verify landing page HeroFace/HeroProducer already have registration CTAs — pre-existing (HeroFace line 85, HeroProducer line 143)

## Dev Notes

### What Already Exists (DO NOT recreate)

Registration CTAs already exist in these locations — **verify, do not duplicate:**

| Component | CTA | Target Route |
|-----------|-----|-------------|
| `AppHeader.vue` (desktop, lines 150-174) | "Poster une mission" / "Devenir une face" / "Se connecter" | `/register/producer`, `/register/face`, `/login` |
| `AppHeader.vue` (mobile, lines 266-290) | Same CTAs in mobile menu | Same routes |
| `HeroFace.vue` (line 84-91) | "Créer mon profil" | `/register/face` |
| `HeroProducer.vue` (line 142-150) | "Publier une mission" | `/register/producer` |
| `ProfileInfoSection.vue` (line 121-128) | "Créer un compte producteur..." | `/register/producer` |
| `LockedContentTeaser.vue` (used on face profile) | "S'inscrire pour voir" | `/register/producer` |
| `PublicMissionDetailView.vue` (lines 429-467) | "Se connecter pour postuler" + "Créez votre compte" | `/login`, `/register/face` |

### What's Missing (THIS story implements)

- **PublicFacesView.vue** (`/faces`) — NO registration CTA at all
- **PublicMissionsView.vue** (`/missions`) — NO registration CTA at all

### Implementation Pattern

Create ONE reusable `RegistrationCta.vue` component used on both list pages. Follow the design language established in:
- `LockedContentTeaser.vue` — for CTA styling patterns
- `PublicMissionDetailView.vue` lines 429-467 — for dual Face/Producer CTA layout

### Component Design

```
RegistrationCta.vue
Props:
  - variant: "faces" | "missions" (determines copy and primary CTA)
  - class?: string (optional additional classes)

Structure:
  <section data-testid="registration-cta">
    <heading>  — e.g., "Rejoignez WEACT" or context-aware text
    <description> — value proposition text
    <div.cta-buttons>
      <RouterLink to="/register/face" primary>  — "Créer mon profil" or "Postuler aux missions"
      <RouterLink to="/register/producer" secondary> — "Publier une mission"
    </div>
  </section>
```

### Project Structure Notes

- New component: `frontend/src/features/public/components/RegistrationCta.vue`
- New test: `frontend/src/features/public/components/__tests__/RegistrationCta.spec.ts`
- Modified: `frontend/src/views/PublicFacesView.vue` (add CTA import + usage)
- Modified: `frontend/src/views/PublicMissionsView.vue` (add CTA import + usage)
- Modified: `frontend/src/views/__tests__/PublicFacesView.spec.ts` (add CTA assertion)
- No backend changes needed

### Technical Requirements

- **No backend work** — purely frontend component + integration
- Use `<RouterLink>` (not `<a>`) for SPA navigation
- Use Tailwind CSS classes matching existing design-system.md
- CTA section should be visually distinct (background color, padding) but not intrusive
- Responsive: stack buttons vertically on mobile, side-by-side on desktop
- Add `data-testid="registration-cta"` for testing
- Use `lucide-vue-next` icons if needed (UserPlus, Briefcase)

### Testing Requirements

- `RegistrationCta.spec.ts` — unit test: renders both variants with correct links and text
- `PublicFacesView.spec.ts` — update: CTA section appears after data loads
- No E2E tests needed for this story (simple navigation links)

### Previous Story Intelligence

**From Story 11-10 (most recent):**
- Code review found missing `encodeURIComponent()` in API URLs — not relevant here (no API calls)
- Code review found broken `handleRetry()` references — ensure no similar copy-paste errors
- Used `data-testid` attributes consistently — follow same pattern

**From Story 11-7 (mission detail):**
- CTA section pattern: primary button (filled) + secondary link (text) — reuse same visual pattern
- Date/currency formatting established — not relevant here
- `LockedContentTeaser.vue` pattern — useful reference for styling

### References

- [Source: _bmad-output/planning-artifacts/epics.md — Epic 11, Story 11.8, FR82]
- [Source: frontend/src/components/layout/AppHeader.vue — existing header CTAs]
- [Source: frontend/src/views/PublicMissionDetailView.vue:429-467 — CTA section pattern]
- [Source: frontend/src/features/public/components/LockedContentTeaser.vue — reusable CTA pattern]
- [Source: _bmad-output/project-context.md — naming conventions, testing standards]

## Dev Agent Record

### Agent Model Used
Claude Opus 4.6

### Debug Log References
- Fixed string escaping in RegistrationCta.spec.ts (l'action apostrophe in single-quoted JS string)

### Completion Notes List
- Created reusable RegistrationCta.vue component with "faces" and "missions" variants
- Integrated CTA into PublicFacesView.vue (after grid, before pagination)
- Integrated CTA into PublicMissionsView.vue (after grid, before pagination)
- Unit tests: 11 tests for RegistrationCta component (both variants + accessibility)
- Integration tests: 5 new tests in PublicFacesView.spec.ts (CTA rendering, links, conditional visibility)
- Verified all 5 pre-existing CTA locations (AppHeader, LockedContentTeaser, PublicMissionDetailView, HeroFace, HeroProducer)
- All 1623 frontend tests pass, no backend changes needed

### Code Review Fixes (2026-02-07)
- HIGH-1: Added 6 missing CTA integration tests to PublicMissionsView.spec.ts (was marked [x] but not done)
- MEDIUM-1: Extracted duplicated Tailwind class strings into `primaryCtaClass` / `secondaryCtaClass` constants in RegistrationCta.vue
- MEDIUM-2: Added RegistrationCta to empty state in both PublicFacesView.vue and PublicMissionsView.vue (visitors see CTA even with zero results)
- Added empty-state CTA test to both PublicFacesView.spec.ts and PublicMissionsView.spec.ts

### File List
- `frontend/src/features/public/components/RegistrationCta.vue` (created, review: extracted class constants)
- `frontend/src/features/public/components/__tests__/RegistrationCta.spec.ts` (created)
- `frontend/src/views/PublicFacesView.vue` (modified — added RegistrationCta import + usage in grid + empty state)
- `frontend/src/views/PublicMissionsView.vue` (modified — added RegistrationCta import + usage in grid + empty state)
- `frontend/src/views/__tests__/PublicFacesView.spec.ts` (modified — added 6 CTA tests + register routes)
- `frontend/src/views/__tests__/PublicMissionsView.spec.ts` (modified — added 6 CTA tests + register routes)
