# Sprint Change Proposal — 2026-05-22

**Workflow:** correct-course
**Project:** WEACT - Tiered Face Premium Subscriptions Sprint 15
**Prepared by:** Amakira
**Change scope classification:** Moderate (backlog reorganization — one story added to an in-flight epic)

---

## Section 1 — Issue Summary

**Problem statement.** The FEATURE-FP-2 epic folded the Face-facing video portfolio UI (acting + UGC upload, per-type locked states) into story **FEATURE-FP-2.7**. That scoping became invalid after **FEATURE-FP-2.2.1** restructured the video model — and FP-2.7, when context-engineered, was deliberately scoped down to *subscription panel + photo locked states*. The Face video UI rebuild is now **covered by no story**.

**Discovery.** Surfaced 2026-05-22 during the `create-story` analysis for FEATURE-FP-2.7. Verifying the current frontend against the FP-2 backend showed `frontend/src/features/face/services/faceApi.ts:171-213` still calls `/api/v1/face/acting-video` — an endpoint **retired by FP-2.2.1**. `backend/routes/api/face.php` (lines 83-92) exposes only `/api/v1/face/presentation-video` and the typed `/api/v1/face/videos` — there is no `/acting-video` route. The acting-video upload UI therefore returns HTTP 404 against the current backend, and no UGC video UI exists at all.

**Root cause.** The epic was drafted 2026-05-20. FP-2.2.1 was created 2026-05-21 (via the prior correct-course, `sprint-change-proposal-2026-05-21.md`) — *after* the epic draft. FP-2.2.1 replaced the single `faces.acting_video` column with a typed, multi-row `face_videos` table and retired `ActingVideoController`. That turned FP-2.7's "video locked states" line item from a small masking tweak into a full UI rebuild on a new typed API. FP-2.7's epic AC was never re-sized for it. This is a downstream ripple of the FP-2.2 split — not a new requirement and not a defect in any delivered story.

**Evidence.**
- `frontend/src/features/face/services/faceApi.ts:171-213` — `getActingVideo` / `uploadActingVideo` / `deleteActingVideo` target `/face/acting-video`.
- `backend/routes/api/face.php:83-92` — `/presentation-video` (GET/POST/DELETE) + `/videos` (GET/POST/DELETE, `FaceVideoController`); **no** `/acting-video` route.
- `feature-fp-2-2-1-face-videos-table-and-three-video-types.md` — non-scope: "The Face/public video UI consuming the videos array is FP-2.7. No `frontend/` file changes."
- `feature-fp-2-7-face-ui-tier-cards-and-upgrade-flow.md` — non-scope défensif explicitly carves the video UI rebuild out to "a separate story".

---

## Section 2 — Impact Analysis

**Epic impact.** FEATURE-FP-2 (in-progress; 7 of 15 stories `done`) cannot be completed as planned: epic requirement **FEAT-FP2-FR7** ("the Face dashboard/profile area must show … locked media states by tier/type/position") and **FR3 / FR4** (per-tier video upload quotas + masking) are satisfied by no remaining story now that FP-2.7 excludes video. The correction is **additive** — one new story; the epic goal, scope, and the other 14 stories are unchanged.

**Story impact.**
- **FEATURE-FP-2.7** (`ready-for-dev`) — already correctly scoped to subscription panel + photo locked states; its non-scope section already names the video work as "a separate story". One cosmetic edit: cite the new story key by name.
- **FEATURE-FP-2.2.1** (`done`) — no change; it correctly delivered the backend and explicitly deferred the video UI.
- No other story is affected; no story requires rollback.

**Artifact conflicts.**
- `epics-face-premium-subscription-v2.md` — `totalStories` 15→16; the stories table, the recommended-delivery-order list, and the FP-2.7 section need the new story added/annotated.
- `sprint-status.yaml` — one new `backlog` story entry; the epic story-count comment 15→16.
- `prd.md` / `architecture-booking.md` / `ux-design-specification.md` — **no conflict.** These are booking-era artifacts; the FP-2 epic carries its own FRs. The video UI follows existing video-component patterns + the FP-2.7 design system — no hi-fi design handoff is required.

**Technical impact.** None to delivered code. The new story is frontend-only — the FP-2.2.1 backend (`/api/v1/face/videos` + `FaceResource` video masking with `is_locked` / `lock_reason`) is done and consumed as-is. Until the new story ships, Face acting/UGC video upload stays non-functional — an accepted mid-epic state (FP-2 is undeployed; FP-2.3 likewise left the frontend stale between stories).

---

## Section 3 — Recommended Approach

**Selected path: Option 1 — Direct Adjustment** (add one story within the existing epic structure).

- *Option 2 — Potential Rollback* — not applicable. Nothing is wrong with any delivered story; rolling back FP-2.2.1 would un-fix the backend.
- *Option 3 — PRD/MVP Review* — not needed. The epic scope and goal are unchanged; this makes implicit in-scope work explicit — it neither reduces nor expands the MVP.

**Rationale.** The video UI was always epic-scope work (FR3 / FR4 / FR7); the FP-2.2.1 restructure simply made it large enough to need its own story. This is the **identical pattern** used 2026-05-21 to split FEATURE-FP-2.2 into FP-2.2 + FP-2.2.1. Effort: change-management = **Low** (one story added); the new story's own implementation = **Medium** (a contained frontend video rebuild). Risk: **Low** — additive, no rollback, no cross-epic dependency.

**New story.**

| Field | Value |
|---|---|
| ID | FEATURE-FP-2.7.1 |
| Title | Face Video Portfolio UI — Acting + UGC Upload and Per-Type Locked States |
| sprint-status key | `feature-fp-2-7-1-face-video-ui-and-locked-states` |
| Priority | High |
| FRs | FEAT-FP2-FR3, FEAT-FP2-FR4, FEAT-FP2-FR7 |
| Depends on | FEATURE-FP-2.2.1 (backend `face_videos` API + masking), FEATURE-FP-2.7 (the `useSubscriptionStatus` rewrite + `capabilities` matrix) |
| Delivery position | immediately after FEATURE-FP-2.7 |
| Initial status | `backlog` |

---

## Section 4 — Detailed Change Proposals

### Edit A — `epics-face-premium-subscription-v2.md` · frontmatter

```
OLD:  totalStories: 15
NEW:  totalStories: 16
```

### Edit B — `epics-face-premium-subscription-v2.md` · Stories table

```
OLD:
| FEATURE-FP-2.7 | Face UI: 4 tier cards + upgrade/downgrade flow + per-type locked states | FEAT-FP2-FR7, FEAT-FP2-FR9 | High |

NEW:
| FEATURE-FP-2.7 | Face UI: 4 tier cards + upgrade/downgrade flow + photo locked states | FEAT-FP2-FR7, FEAT-FP2-FR9 | High |
| FEATURE-FP-2.7.1 | Face video portfolio UI: acting + UGC upload on the typed videos API + per-type video locked states | FEAT-FP2-FR3, FEAT-FP2-FR4, FEAT-FP2-FR7 | High |
```

*Rationale:* FP-2.7 reworded ("per-type" → "photo") to match its delivered scope; FP-2.7.1 added.

### Edit C — `epics-face-premium-subscription-v2.md` · Recommended delivery order

Insert FEATURE-FP-2.7.1 as item 9 (right after FP-2.7); reword item 8; renumber the rest. Items 1-7 unchanged; the new list 8-16:

```
8.  **FEATURE-FP-2.7** — Face-facing tier-cards UI + upgrade/downgrade flow + photo locked states (consumes the FP-2.13 design system).
9.  **FEATURE-FP-2.7.1** — Face video portfolio UI: acting + UGC upload on the typed `face_videos` API + per-type locked states.
10. **FEATURE-FP-2.8** — expiration automation (drop any tier to Free).
11. **FEATURE-FP-2.14** — 90-day retention window + purge command (depends on FP-2.8 expiration + FP-2.2 / FP-2.2.1 quota model).
12. **FEATURE-FP-2.9** — reminders and lifecycle notifications with per-tier copy.
13. **FEATURE-FP-2.10** — admin UI tier selector.
14. **FEATURE-FP-2.12** — public Élite badge (small frontend-only story, late delivery is safe).
15. **FEATURE-FP-2.13** — public `/pricing` page refonte (frontend-only, near drop-in; can be delivered any time after FP-2 pricing is final).
16. **FEATURE-FP-2.11** — final regression sweep, runbook update, and 4-tier × 6-state matrix.
```

### Edit D — `epics-face-premium-subscription-v2.md` · FP-2.7 section

Append a split note to the FEATURE-FP-2.7 section (after its Acceptance Criteria block):

```
NEW (appended):
**Split note (2026-05-22 correct-course):** the per-type **video** locked states + the video upload UI were split out to **FEATURE-FP-2.7.1** — the FP-2.2.1 `face_videos` restructure made the video portion a full UI rebuild. FP-2.7 delivers the subscription panel, the 4 tier cards, the upgrade/downgrade flow, and the **photo** locked states only.
```

### Edit E — `epics-face-premium-subscription-v2.md` · new FP-2.7.1 section

Insert after the FEATURE-FP-2.7 section, before FEATURE-FP-2.8:

```markdown
#### FEATURE-FP-2.7.1: Face Video Portfolio UI — Acting + UGC Upload and Per-Type Locked States

**Description:** Frontend-only story split out of FEATURE-FP-2.7 (2026-05-22 correct-course). Rebuilds the Face video portfolio UI onto the typed `face_videos` API introduced by FP-2.2.1. The current `ActingVideoUpload.vue` + `useActingVideo` consume the retired `/api/v1/face/acting-video` endpoint (HTTP 404 against the FP-2.2.1 backend), and no UGC video UI exists. This story migrates the Face video upload/list/delete UI to `GET|POST|DELETE /api/v1/face/videos` (typed `acting` | `ugc`, positioned, multi-row), adds a UGC video surface, enforces the per-tier per-type video quotas in the UI, and renders the per-type locked states (presentation / acting / UGC) from the `FaceResource` `is_locked` / `lock_reason` fields (`tier_below_required` | `quota_exceeded`). The retired-endpoint acting-video files are retired.

**Context — why this is a separate story:** the epic (drafted 2026-05-20) folded the Face video locked-states UI into FP-2.7. FP-2.2.1 — created 2026-05-21 via correct-course, after the epic draft — restructured video from the single `faces.acting_video` column into a typed `face_videos` table and retired `ActingVideoController`. That made FP-2.7's video portion a full UI rebuild, too large to carry alongside the tier-cards + payment surface. FP-2.7 was scoped to subscription panel + photo locked states; this story is its video sibling — the same split pattern as FP-2.2 → FP-2.2 / FP-2.2.1.

**Acceptance Criteria (draft):**
- Frontend-only story (zero backend changes — the FP-2.2.1 `/api/v1/face/videos` API + the `FaceResource` video masking are consumed as-is).
- The Face video upload/list/delete UI consumes `GET|POST|DELETE /api/v1/face/videos`; `ActingVideoUpload.vue` / `useActingVideo` / the `faceApi` `acting-video` methods (on the retired endpoint) are retired.
- A UGC video upload surface is added; acting supports multi-row (Élite = 2 acting videos); the presentation video stays on its existing scalar endpoint.
- Per-tier per-type video quotas are enforced in the UI (presentation 0/1/1/1, acting 0/0/1/2, ugc 0/0/0/1 for Free/Starter/Pro/Élite); over-quota or tier-locked videos render a locked state with an explanatory tooltip, differentiated by type.
- The `ProfileEditPage` video sections are rewired; the FP-2.7 minimal build-green touch on the acting-video section is superseded by the real wiring.
- All copy in correct French with proper accents.
- ≥ 25 frontend tests (Vitest + Vue Test Utils).

**Depends on:** FEATURE-FP-2.2.1 (backend `face_videos` API + masking) and FEATURE-FP-2.7 (the `useSubscriptionStatus` rewrite + the `capabilities` matrix the video quotas read).
```

### Edit F — `sprint-status.yaml`

```
OLD (epic header comment, line ~560):
  # Epic FEATURE-FP-2: Tiered Face Premium Subscriptions & Featured Portfolio (15 stories)
NEW:
  # Epic FEATURE-FP-2: Tiered Face Premium Subscriptions & Featured Portfolio (16 stories)
```

```
OLD (development_status, after feature-fp-2-7):
  feature-fp-2-7-face-ui-tier-cards-and-upgrade-flow: ready-for-dev
  feature-fp-2-8-expiration-command-drop-to-free: backlog
NEW:
  feature-fp-2-7-face-ui-tier-cards-and-upgrade-flow: ready-for-dev
  feature-fp-2-7-1-face-video-ui-and-locked-states: backlog
  feature-fp-2-8-expiration-command-drop-to-free: backlog
```

Plus a correct-course log comment at the top of the comment block (after `# last_updated`).

### Edit G — `feature-fp-2-7-…-upgrade-flow.md` (FP-2.7 story file)

Cosmetic precision edit — name the sibling story in the non-scope défensif video bullet:

```
OLD:  … is a separate story. FP-2.7's only video edit is the two-line decision-#9 touch …
NEW:  … is a separate story (FEATURE-FP-2.7.1, added 2026-05-22 via correct-course). FP-2.7's only video edit is the two-line decision-#9 touch …
```

---

## Section 5 — Implementation Handoff

**Scope classification:** Moderate — backlog reorganization (one story added to an in-flight epic).

**Routing:**
- **Scrum Master (BMAD `create-story`)** — context-engineer FEATURE-FP-2.7.1 when it is reached in the delivery order (immediately after FP-2.7 is `done`). The draft AC above is the input.
- **Development team (BMAD `dev-story`)** — implement FEATURE-FP-2.7.1; then `code-review` closes it.

**Sequencing:** FP-2.7.1 depends on FP-2.7 (the `useSubscriptionStatus` rewrite) — it must be created and developed **after** FP-2.7 is done, not in parallel.

**Success criteria:**
- Epic FEATURE-FP-2's FR3 / FR4 / FR7 video requirements are satisfied.
- The Face can upload, list, and delete acting + UGC videos against `/api/v1/face/videos`.
- Per-type video locked states render correctly across the four tiers.
- FEATURE-FP-2 has no remaining scope gap before its regression sweep (FP-2.11).

**No escalation required** — additive change, no PRD/architecture impact, no rollback.
