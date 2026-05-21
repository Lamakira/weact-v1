# Sprint Change Proposal — FEATURE-FP-2.2 Story Split

**Date:** 2026-05-21
**Role:** Scrum Master (Correct Course workflow)
**Project:** WEACT — Tiered Face Premium Subscriptions (Sprint 15)
**Status:** Approved by Amakira — applied 2026-05-21
**Change scope:** Moderate (backlog reorganization)

---

## Section 1 — Issue Summary

**Triggering story:** FEATURE-FP-2.2 — "Dynamic photo quota + three video types (presentation, acting, UGC)".

**How discovered:** During the `/bmad-create-story` run for FEATURE-FP-2.2 on 2026-05-21. An exhaustive codebase analysis (55 backend files verified — entitlement service, photo/album subsystem, video subsystem, API resources, controllers, test suites) revealed the story's true implementation footprint.

**Problem statement:** Issue type — *requirements scope under-estimated during epic breakdown*. FEATURE-FP-2.2 as written bundles two structurally independent subsystems into one story with a combined footprint of ~35-40 files:

1. **Photo quota** — migrate the album-photo upload guards (`PhotoAlbumService`, `AddAlbumPhotoRequest`) and the photo masking (`FaceResource`, `PublicFaceProfileResource`) from the FP-1 binary shims to the FP-2.1 capabilities matrix. No schema change — `face_photos` already supports the 1-6 position range and `config/face_subscription_tiers.php` already carries the photo quotas. ~10 files, low risk.

2. **Three video types** — the larger and structurally heavier half. Today `presentation_video` and `acting_video` are single string columns on the `faces` table (exactly one of each per Face). The FP-2 tier matrix (`config/face_subscription_tiers.php`) requires the **Élite** tier to store **2 acting videos + 1 UGC video**, and the UGC video type does not exist anywhere in the codebase. A single column cannot represent a multi-row, ordered, quota-limited collection. This forces a schema redesign: a new `face_videos` table, a `FaceVideo` model + `FaceVideoType` enum + factory, a non-destructive production-data migration of `faces.acting_video`, a unified video controller/service/form-request, and the retirement of `ActingVideoController` / `ActingVideoService` / `UploadActingVideoRequest`. ~25 files.

A ~35-40 file story is two-to-three stories' worth of work. The FEATURE-FP-1 retrospective (`epic-feature-fp-1-retro-2026-05-20.md`, section 3) noted that even well-specified large stories (FP-1.5, FP-1.11) required multiple BLOCKER-scale review passes; bundling raises that risk and reduces dev-agent focus.

**Evidence:** 55-file verified analysis; the FP-2.1 capabilities config defining `max_acting_videos` = 0/0/1/2 and `max_ugc_videos` = 0/0/0/1 across Free/Starter/Pro/Élite; the current single-column video storage (`faces.acting_video`, migration `2026_01_12_005738_add_acting_video_to_faces.php`).

## Section 2 — Impact Analysis

**Epic impact:** Epic FEATURE-FP-2 remains valid and completable as planned. One story (FP-2.2) requires splitting into two. No other epic is affected — the change is contained entirely within FEATURE-FP-2.

**Story impact:** FP-2.2 is split into FP-2.2 (photo) + a new FP-2.2.1 (video). FEATURE-FP-2.3 … FEATURE-FP-2.14 are unaffected and **not renumbered** (sub-story numbering chosen — see Section 3). No story file existed for FP-2.2 yet (it was `backlog`), so there is no in-flight work to revert or rewrite.

**Artifact conflicts:**
- *PRD* — none. The FEATURE-FP-2 epic file is the authoritative requirements source; `prd.md` predates the FP work entirely.
- *Architecture (`architecture-booking.md`)* — none. The `face_videos` table is a story-level data-model decision documented in FP-2.2.1; it alters no booking-domain architecture.
- *UX specification* — none.
- *`sprint-status.yaml`* — requires the FP-2.2 key rename + a new FP-2.2.1 key (applied).

**Technical impact:** FP-2.2.1 will introduce a new table and migrate production data (`faces.acting_video` → `face_videos`, copy-then-drop, non-destructive — WEACT is in production and existing Faces hold acting videos). No FP-2.2 / FP-2.2.1 work touches the booking/mission modules (epic NFR4 preserved).

## Section 3 — Recommended Approach

**Selected path:** Option 1 — Direct Adjustment (split the story within the existing epic structure).

Option 2 (rollback) and Option 3 (PRD MVP review) are not applicable: no work has started on FP-2.2, and the MVP scope is unchanged — the same requirements (FEAT-FP2-FR3, FEAT-FP2-FR4) are delivered, now across two stories instead of one.

**Numbering decision:** the video story is inserted as a **sub-story FEATURE-FP-2.2.1**, not by renumbering FP-2.3 … FP-2.14. Renumbering 12 stories would churn the epic document, every cross-reference, and 12 sprint-status keys for zero functional gain. The sub-story pattern has direct precedent in this project (`3-8-1-face-experience-dates-enhancement`). Effort: low. Risk: low.

**Delivery order:** FP-2.2 (photo) → FP-2.2.1 (video) → FP-2.3 (status API). FP-2.2.1 depends on FP-2.1 (capabilities matrix) and FP-2.2 (shared `is_locked` / `lock_reason` masking shape).

## Section 4 — Detailed Change Proposals

### 4.1 — `_bmad-output/planning-artifacts/epics-face-premium-subscription-v2.md` (6 edits)

1. **Frontmatter** — `totalStories: 14` → `totalStories: 15`.
2. **Story table** — FP-2.2 row title `Dynamic photo quota + three video types (presentation, acting, UGC)` → `Dynamic photo quota + tier-aware photo masking`; new row inserted: `FEATURE-FP-2.2.1 | face_videos table + three video types (presentation, acting, UGC) + video masking | FEAT-FP2-FR3, FEAT-FP2-FR4 | High`.
3. **Recommended delivery order** — list expanded 14 → 15 items; FP-2.2.1 inserted at position 3; ordinals reflowed.
4. **Detail sections** — `#### FEATURE-FP-2.2` rewritten to a photo-only scope; new `#### FEATURE-FP-2.2.1` section added with description, draft ACs, and a `Depends on` line.
5. **NFR5 (cross-reference)** — `resolved as part of FP-2.1 / FP-2.2` → `FP-2.1 / FP-2.2 / FP-2.2.1`.
6. **FP-2.14 dependency (cross-reference)** — `depends on FP-2.8 ... and FP-2.2 (quota model ...)` → `FP-2.2 / FP-2.2.1 (quota model ...)`.

### 4.2 — `_bmad-output/implementation-artifacts/sprint-status.yaml` (3 edits)

1. Header comment — `(1 epic, 14 stories)` → `(1 epic, 15 stories)`.
2. Key rename — `feature-fp-2-2-dynamic-quota-and-three-video-types: backlog` → `feature-fp-2-2-dynamic-photo-quota-and-masking: backlog`.
3. New key inserted directly after — `feature-fp-2-2-1-face-videos-table-and-three-video-types: backlog`.
4. A changelog comment recording the restructure was prepended to the file header.

Both stories remain `backlog`. No story status was advanced by this change.

## Section 5 — Implementation Handoff

**Change scope classification:** Moderate (backlog reorganization — Product Owner / Scrum Master territory).

**Handoff:** Scrum Master / story-creation workflow. Next step — re-run `/bmad-create-story`; it picks `feature-fp-2-2-dynamic-photo-quota-and-masking` as the next `backlog` story in epic FEATURE-FP-2 (the smaller, well-scoped photo story). FP-2.2.1 follows. The full 55-file analysis from the 2026-05-21 create-story run remains available to accelerate both stories.

**Success criteria:** FP-2.2 and FP-2.2.1 each reach `ready-for-dev` as self-contained, implementation-ready stories per the CLAUDE.md story-file discipline; together they satisfy FEAT-FP2-FR3 + FEAT-FP2-FR4 with no un-owned gap.

---

## Change Navigation Checklist — summary

| Section | Status | Note |
|---|---|---|
| 1. Understand the trigger and context | Done | Trigger FP-2.2; cause = scope under-estimate; evidence = 55-file analysis |
| 2. Epic impact assessment | Done | Single epic (FP-2); one story split; no resequencing of FP-2.3+ |
| 3. Artifact conflict analysis | Done | PRD / architecture / UX — N/A; sprint-status — updated |
| 4. Path forward evaluation | Done | Option 1 (Direct Adjustment) selected; Options 2 & 3 N/A |
| 5. Sprint Change Proposal components | Done | This document |
| 6. Final review and handoff | Done | Approved by Amakira; sprint-status.yaml updated; handoff to create-story |
