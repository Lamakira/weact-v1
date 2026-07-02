---
stepsCompleted: [1, 2]
status: 'draft'
draftedAt: '2026-05-20'
supersedes: 'epics-face-premium-subscription.md (FEATURE-FP-1, all 11 stories done, NEVER deployed to production)'
totalEpics: 1
totalStories: 16
project_name: 'WEACT - Tiered Face Premium Subscriptions Sprint 15'
user_name: 'Amakira'
date: '2026-05-20'
sourceSpec: 'docs/new-features-docs/abonnement weact.docx'
inputDocuments:
  - 'docs/new-features-docs/abonnement weact.docx (client spec)'
  - 'docs/design/Weact Subs Design/design_handoff_pricing/ (Claude Design hi-fi handoff — public /pricing page; visual source of truth for FP-2.13 + design-system reference for FP-2.7)'
  - '_bmad-output/planning-artifacts/epics-face-premium-subscription.md (FP-1 baseline being superseded)'
  - '_bmad-output/planning-artifacts/prd.md'
  - '_bmad-output/implementation-artifacts/epic-feature-fp-1-retro-2026-05-20.md (FP-1 retrospective lessons)'
---

# WEACT - Tiered Face Premium Subscriptions Sprint 15 - Epic Breakdown

## Status: Supersedes FEATURE-FP-1

**This epic supersedes the entire FEATURE-FP-1 series (all 11 stories `done` but never deployed).** The client returned with a new tier-based commercial model that requires structural refactor of the schema, entitlement service, payment flow, featured ordering, and Face/admin UIs. Because FP-1 was never deployed to production, **no data migration is required** — the FP-2 stories can redefine the schema freely.

FP-1 files remain in the repo as historical reference; FP-2 will overwrite them in place where structurally identical, and add net-new code where the model has grown (badge, tier change flow, capabilities matrix, etc.).

## Overview

Sprint 15 replaces the single annual "premium / non-premium" model from FP-1 with a **four-tier commercial model** that the WEACT team has retained as the production pricing strategy for Face subscriptions:

| Tier | Annual price | Photos | Videos | UGC access | Commission | Sort priority | Badge |
|---|---|--------|---|---|---|---|---|
| **Free** (Découverte) | 0 F CFA | 1      | — | ❌ | 10 % | 4ᵗʰ (Standard) | — |
| **Starter** | 12 000 F CFA | 2      | 1 presentation | ✅ | 10 % | 3ʳᵈ (Boostée) | — |
| **Pro** | 25 000 F CFA | 4      | 1 presentation + 1 acting | ✅ | 10 % | 2ⁿᵈ (Premium) | — |
| **Élite** | 40 000 F CFA | 6      | 1 presentation + 2 acting + 1 UGC | ✅ | **5 %** | 1ˢᵗ (Prioritaire) | "VIP / Élite" |

The operating model remains **annual-only** (no monthly recurring) and **prepaid** via Fedapay — same constraint as FP-1 driven by the Benin payment infrastructure.

### Current Codebase Baseline (post-FP-1, pre-FP-2)

- `face_subscriptions` table exists with single `plan = 'annual_premium'` enum value (FP-1.1).
- `FaceEntitlementService` exposes binary `isPremium(Face)` returning a single bool with derived fields `albumPublicLimit` (2 or 4) and `actingVideoAllowed`.
- `face_subscription_audits` table exists with 6 admin actions (FP-1.4).
- Album quota is hard-coded to 2 (free) / 4 (premium) via the service.
- Acting video is a binary premium gate; presentation video is universal; UGC video does not exist as a media type.
- Featured ordering is binary `is_featured_by_subscription` EXISTS-subquery elevation (FP-1.6).
- Face UI (FP-1.7) shows a single "Premium" status card + "Buy Premium" CTA.
- Admin UI (FP-1.11) operates on a single plan with no tier selector.
- Lifecycle notifications (FP-1.9) reference a single "Annual Premium" copy template.
- No badge rendering on public Face profile.
- No commission-rate-per-Face logic; missions use a flat platform commission (location: `backend/app/Services/Mission*` — to verify).
- UGC mission module does **not exist yet** — the "block Postuler UGC for Free" gate from the spec is naturally out of scope until the UGC module ships.

### Product Decisions (FP-2)

1. **Four-tier model** (free + 3 paid annual). Free is the default state for any Face without an active paid subscription; no row in `face_subscriptions` is required to represent Free.
2. **Annual only, prepaid** — same as FP-1. No monthly billing, no proration on upgrade/downgrade.
3. **Upgrade/downgrade allowed mid-subscription, without pro-rata.** The Face pays full annual price for the new tier; the old subscription is cancelled at the moment the new one becomes active (12-month period restarts). The Face is informed of the loss of remaining days before confirming.
4. **No data migration.** FP-1 schema is rewritten in place. No production users to preserve.
5. **Badge "VIP / Élite"** is exposed on the public Face profile and producer-facing views for Élite tier only.
6. **Capabilities matrix** is the single source of truth: photo quota, video quotas per type, UGC access bool, commission rate, sort priority, badge bool. `FaceEntitlementService` returns this matrix; controllers/resources consume capabilities, never read tier strings directly.
7. **Public masking is tier-aware**, not binary. A Free Face exposes 1 photo + 0 videos. A Starter exposes 2 photos + 1 presentation. A Pro exposes 4 photos + 1 presentation + 1 acting. An Élite exposes 6 photos + 1 presentation + 2 acting + 1 UGC. Excess media uploaded under a higher tier remain stored and visible to the Face privately after downgrade — but only for a **90-day retention window** (see Product Decision #11), after which a scheduled purge permanently deletes the over-quota media.
8. **Featured ordering is tier-priority-based.** Producers' search results sort by `(tier_priority ASC, …)` where Élite=1, Pro=2, Starter=3, Free=4.
9. **Sort priority is config-driven** in the same way prices are. The 4-tier price list and capabilities matrix live in `config/face_subscription_tiers.php` (or equivalent), not hard-coded in service code.
10. **Out of FP-2 scope, scheduled separately**: (a) Élite commission rate 5 % on monetary missions — touches the booking/mission payout module ; (b) UGC mission gating for Free Faces — depends on the UGC module which does not exist yet.
11. **90-day media retention then purge.** This is a deliberate change from the FP-1 "never delete" invariant. After a downgrade or an expiration, over-quota media (photos beyond the new tier's quota, acting/UGC videos no longer covered) stay stored and privately visible for 90 days from the `expires_at` / downgrade date. A scheduled command purges them permanently (disk + DB) once the window elapses. Rationale: the client's `/pricing` FAQ commits to this policy publicly ("Tes photos et vidéos restent stockées 90 jours, le temps de te réabonner") — the backend must honor it.
12. **The public `/pricing` page is a hi-fi design handoff.** Claude Design produced a production-ready `PricingView.vue` (Vue 3 + TS + Tailwind, static, pre-auth). FP-2.13 integrates it pixel-accurately. The handoff's design tokens + card anatomy are ALSO the visual reference for the in-app FP-2.7 tier UI, so the two surfaces stay visually consistent.

## Requirements Inventory

### Functional Requirements

**FEAT-FP2-FR1**: The backend must persist tiered Face subscriptions with `plan` enum (`starter` | `pro` | `elite`), status, payment metadata, start date, expiry date, cancellation fields. A Face with no active paid subscription is implicitly Free.

**FEAT-FP2-FR2**: The backend must expose a single capabilities matrix per Face via `FaceEntitlementService`, returning: `tier` enum, `max_album_photos`, `max_presentation_videos`, `max_acting_videos`, `max_ugc_videos`, `ugc_access` bool, `commission_rate`, `sort_priority`, `has_elite_badge` bool. All entitlement consumers MUST read from this service.

**FEAT-FP2-FR3**: Album upload and validation must enforce dynamic per-tier photo quotas: 1 photo (Free) / 2 photos (Starter) / 4 photos (Pro) / 6 photos (Élite). Video upload must enforce per-tier quotas across three video types (`presentation`, `acting`, `ugc`).

**FEAT-FP2-FR4**: Public and producer-facing profile responses must hide media beyond the active tier's quota. Private Face management responses must return all stored media and mark locked items per type and per position. Media beyond quota is preserved (not deleted) while the subscription is expired **for a 90-day retention window** — see FEAT-FP2-FR14 for the post-window purge policy.

**FEAT-FP2-FR5**: Annual payment initiation must select a tier (`starter` | `pro` | `elite`) and price (12 000 / 25 000 / 40 000 F CFA) before redirecting to Fedapay. Payment confirmation must be idempotent and tier-aware. Upgrade/downgrade from one paid tier to another is supported: a new pending row is created for the target tier ; on confirmation, the previous active subscription is cancelled with `cancelled_at = new.starts_at` and the new one becomes active for 12 months starting at confirmation.

**FEAT-FP2-FR6**: Admin users must be able to view, activate (with tier param), extend, cancel, and correct Face subscriptions for operational support. Audit shape must capture the tier and any tier change.

**FEAT-FP2-FR7**: The Face dashboard/profile area must show current tier, expiry, all 4 tier cards with prices and benefits, upgrade/downgrade affordances with explicit warning about loss of remaining days, and locked media states by tier/type/position.

**FEAT-FP2-FR8**: Expired subscriptions must automatically transition the Face back to Free (entitlement-removal) without deleting stored media. The expiration command handles every paid tier identically (drop to Free, log).

**FEAT-FP2-FR9**: The backend must expose a Face subscription status endpoint returning current tier, status, expiry, full capabilities matrix, all 4 tier offers (price + capabilities), and payment CTA metadata per tier.

**FEAT-FP2-FR10**: Admin subscription mutations must be auditable with admin id, action, notes, previous tier+state, new tier+state. Tier change is a first-class audit action.

**FEAT-FP2-FR11**: Producer-facing search results must order Faces by `(sort_priority ASC, …existing tiebreakers…)` where Élite = 1, Pro = 2, Starter = 3, Free = 4. The previous binary `is_featured_by_subscription` boolean is replaced by the tier-priority ordering.

**FEAT-FP2-FR12**: Public Face profile and producer-facing Face profile must render a "VIP / Élite" badge visible to all viewers when the Face's active subscription tier is Élite. The badge disappears immediately on tier change or expiration.

**FEAT-FP2-FR13**: The public marketing page `/pricing` must be rewritten to present the 4-tier model: hero, 4 pricing cards (ladder indicator, Élite dark card, "Populaire" badge on Pro), a category-grouped comparison table, FAQ accordion, and a footer CTA. The page is static (no API, no Pinia), pre-auth, and pixel-accurate to the Claude Design hi-fi handoff. CTAs route to `/register/face` with a `?plan=` query param per tier.

**FEAT-FP2-FR14**: Media stored beyond the active tier's quota (after a downgrade or an expiration) is retained for a **90-day window** measured from the subscription `expires_at` / downgrade date. A scheduled purge command permanently deletes the masked over-quota media (files on disk + DB rows) once the 90-day window has elapsed without the Face re-subscribing to a tier that re-covers that media. Media that the current tier still covers is never purged. This supersedes the FP-1 "never delete" invariant with a bounded retention policy.

### Non-Functional Requirements

**FEAT-FP2-NFR1**: All four tier prices and the full capabilities matrix must be configuration-driven (env or `config/` PHP file), not hard-coded in service or controller code. Adding a fifth tier or modifying a price must require zero schema or controller code change.

**FEAT-FP2-NFR2**: Webhook idempotency invariants from FP-1.5 must be preserved across tier-change flows. A replayed webhook on a tier upgrade must never produce two active subscriptions on the same Face.

**FEAT-FP2-NFR3**: Existing FP-1 regression matrix patterns (test every Face state × every viewer lens × every surface) must be expanded to 4 tiers × 6 subscription states × N surfaces. No surface may regress versus the FP-1 visibility contract.

**FEAT-FP2-NFR4**: No production source code from the booking/mission module may be modified by FP-2 stories (the Élite commission 5 % story is explicitly scheduled separately to keep the booking blast radius contained).

**FEAT-FP2-NFR5**: The `FaceEntitlementService::capabilities()` call must remain cheap (single query or eager-load aware). The N+1 entitlement pattern documented in FP-1.2 and partially addressed in FP-1.6 must be fully resolved as part of FP-2.1 / FP-2.2 / FP-2.2.1.

### Additional Requirements (from FP-1 retrospective lessons)

- **Self-review pass by dev agent before `ready-for-review`** — applied per story. Documented in CLAUDE.md `Story Files: Implementation-Ready Discipline`.
- **Chained reviewer passes**, not parallel — reviewer #2 receives reviewer #1's findings before starting, to dedup and focus on the delta.
- **Story files must remain self-contained and verifiable** — every line/file/method citation must be opened or grepped before flagging the story `ready-for-dev`. Pattern proven on FP-1.

## Epic & Story Breakdown

---

### Epic FEATURE-FP-2: Tiered Face Premium Subscriptions & Featured Portfolio

**Goal:** Replace the single-plan annual premium model from FP-1 with a four-tier model (Free + Starter + Pro + Élite) that drives album quota, video quotas across three media types, featured search ordering, public Élite badge, and operational admin tooling. The Face UI must support full upgrade/downgrade flows without pro-rata.

**Priority:** High — revenue feature, public discovery impact, supersedes a completed-but-undeployed epic.

#### Stories

| ID | Story | FRs | Priority |
|---|---|---|---|
| FEATURE-FP-2.1 | Tier-aware subscription schema + capabilities matrix | FEAT-FP2-FR1, FEAT-FP2-FR2, FEAT-FP2-NFR1, FEAT-FP2-NFR5 | High |
| FEATURE-FP-2.2 | Dynamic photo quota + tier-aware photo masking | FEAT-FP2-FR3, FEAT-FP2-FR4 | High |
| FEATURE-FP-2.2.1 | face_videos table + three video types (presentation, acting, UGC) + video masking | FEAT-FP2-FR3, FEAT-FP2-FR4 | High |
| FEATURE-FP-2.3 | Face subscription status API exposing capabilities matrix + tier offers | FEAT-FP2-FR2, FEAT-FP2-FR7, FEAT-FP2-FR9 | High |
| FEATURE-FP-2.4 | Admin subscription operations with tier param + extended audit | FEAT-FP2-FR6, FEAT-FP2-FR10 | High |
| FEATURE-FP-2.5 | Annual payment flow with tier selection + upgrade/downgrade without pro-rata | FEAT-FP2-FR5, FEAT-FP2-NFR2 | High |
| FEATURE-FP-2.6 | Producer-facing search ordering by tier priority (4 buckets) | FEAT-FP2-FR11 | High |
| FEATURE-FP-2.7 | Face UI: 4 tier cards + upgrade/downgrade flow + photo locked states | FEAT-FP2-FR7, FEAT-FP2-FR9 | High |
| FEATURE-FP-2.7.1 | Face video portfolio UI: acting + UGC upload on the typed videos API + per-type video locked states | FEAT-FP2-FR3, FEAT-FP2-FR4, FEAT-FP2-FR7 | High |
| FEATURE-FP-2.8 | Expiration command: drop any tier back to Free | FEAT-FP2-FR8 | High |
| FEATURE-FP-2.9 | Renewal reminders + lifecycle notifications with per-tier copy | FEAT-FP2-FR8 | Medium |
| FEATURE-FP-2.10 | Admin UI: tier selector in subscription section + extended audit display | FEAT-FP2-FR6, FEAT-FP2-FR10 | Medium |
| FEATURE-FP-2.11 | Regression matrix 4 tiers × 6 states + rollout runbook update | FEAT-FP2-FR1-FR14, FEAT-FP2-NFR3 | High |
| FEATURE-FP-2.12 | Public "VIP / Élite" badge on Face profile + producer-facing views | FEAT-FP2-FR12 | Medium |
| FEATURE-FP-2.13 | Public `/pricing` page refonte (4-tier hi-fi design handoff) | FEAT-FP2-FR13 | Medium |
| FEATURE-FP-2.14 | 90-day post-expiration media retention window + purge command | FEAT-FP2-FR14 | High |

**Recommended delivery order:**

1. **FEATURE-FP-2.1** — schema rewrite and capabilities matrix; locks the entitlement contract for every downstream story.
2. **FEATURE-FP-2.2** — enforce dynamic per-tier photo quotas; tier-aware public/producer/owner photo masking.
3. **FEATURE-FP-2.2.1** — introduce the `face_videos` table and the three video types (presentation, acting, UGC); per-tier per-type video quotas and masking.
4. **FEATURE-FP-2.3** — expose the capabilities matrix + the 4 tier offers via status endpoint; unblocks frontend and QA.
5. **FEATURE-FP-2.4** — admin fallback with tier param; useful before payment flow is wired.
6. **FEATURE-FP-2.5** — annual payment with tier selection and upgrade/downgrade; chained-renewal preserved.
7. **FEATURE-FP-2.6** — wire featured search ordering to 4-bucket tier priority.
8. **FEATURE-FP-2.7** — Face-facing tier-cards UI + upgrade/downgrade flow + photo locked states (consumes the FP-2.13 design system).
9. **FEATURE-FP-2.7.1** — Face video portfolio UI: acting + UGC upload on the typed `face_videos` API + per-type locked states.
10. **FEATURE-FP-2.8** — expiration automation (drop any tier to Free).
11. **FEATURE-FP-2.14** — 90-day retention window + purge command (depends on FP-2.8 expiration + FP-2.2 / FP-2.2.1 quota model).
12. **FEATURE-FP-2.9** — reminders and lifecycle notifications with per-tier copy.
13. **FEATURE-FP-2.10** — admin UI tier selector.
14. **FEATURE-FP-2.12** — public Élite badge (small frontend-only story, late delivery is safe).
15. **FEATURE-FP-2.13** — public `/pricing` page refonte (frontend-only, near drop-in; can be delivered any time after FP-2 pricing is final).
16. **FEATURE-FP-2.11** — final regression sweep, runbook update, and 4-tier × 6-state matrix.

---

#### FEATURE-FP-2.1: Tier-aware subscription schema + capabilities matrix

**Description:** Rewrite the FP-1.1 schema to support three paid tiers + an implicit Free state. Replace `FaceEntitlementService::isPremium(Face): bool` with `FaceEntitlementService::capabilities(Face): TierCapabilities` that returns the full matrix (quotas, ugc_access, commission_rate, sort_priority, has_elite_badge). Move price + capability data into a config file. Resolve the N+1 entitlement issue identified in FP-1.2/1.6 by memoizing per request.

**Acceptance Criteria (draft):**
- Migration drops or evolves `face_subscriptions.plan` enum to `('starter', 'pro', 'elite')` and adds any missing columns. No data migration (no production users).
- `config/face_subscription_tiers.php` exposes price + capabilities per tier (Free, Starter, Pro, Élite).
- `FaceEntitlementService::capabilities(Face): TierCapabilities` returns the full matrix.
- All FP-1 callers of `isPremium()` are migrated to consume the new capabilities API or removed.
- Capabilities call is memoized per request to eliminate the FP-1.2 N+1 pattern; new perf test pins the query count for serialized Face collections.
- ≥ 20 unit + feature tests covering each tier's capabilities matrix, plus the Free default for Faces without subscription.

---

#### FEATURE-FP-2.2: Dynamic photo quota + tier-aware photo masking

**Description:** Refactor the FP-1.2 photo-album surface to enforce dynamic per-tier photo quotas (1 / 2 / 4 / 6 for Free / Starter / Pro / Élite). Album upload guards and public/producer/owner masking are driven entirely by the capabilities matrix returned from FP-2.1. The three-video-types work is split out to FEATURE-FP-2.2.1.

**Acceptance Criteria (draft):**
- `PhotoAlbumService` upload guard and `AddAlbumPhotoRequest` validation read `capabilities.max_album_photos`; reject upload beyond tier.
- Public + producer Face profile resources mask photos beyond `max_album_photos`.
- Owner / admin private profile responses return all stored photos with per-item `is_locked` + `lock_reason` (`quota_exceeded` for locked photos, `null` otherwise).
- Over-quota photos are preserved on downgrade (not deleted in this story); the bounded 90-day retention + purge is owned by FP-2.14.
- The legacy binary album shims (`albumUploadLimit()` / `publicAlbumPhotoLimit()`) are migrated to `capabilities()` for the album surface.
- No schema change — `face_photos` already supports positions 1-6 and `config/face_subscription_tiers.php` already carries the photo quotas.
- ≥ 15 feature tests covering every (tier × photo-count × viewer lens) combination.

---

#### FEATURE-FP-2.2.1: face_videos table + three video types (presentation, acting, UGC) + video masking

**Description:** Introduce a `face_videos` table to store the Face's portfolio videos — acting and UGC — as positioned, typed, multi-row media. The current single `faces.acting_video` column cannot hold the Élite tier's 2 acting videos, and no storage exists for the new UGC video type; the table applies the same pattern the project already uses for album photos (`face_photos`). The `presentation_video` stays a scalar column on `faces` (capped at 1 for every tier). Existing production `faces.acting_video` data is migrated into the new table. Video upload enforces per-tier per-type quotas, and public/producer resources mask videos beyond quota while the owner sees all with per-item `is_locked` + `lock_reason`.

**Acceptance Criteria (draft):**
- New `face_videos` table (`type` enum `acting` | `ugc`, `position`) + `FaceVideo` model + `FaceVideoType` enum + factory.
- Migration copies every non-null `faces.acting_video` row into `face_videos` (type `acting`, position 1), then drops the `faces.acting_video` / `acting_video_thumbnail` columns — no production data lost.
- Unified `FaceVideoController` / `FaceVideoService` / video upload Form Request for acting + UGC; `ActingVideoController` / `ActingVideoService` / `UploadActingVideoRequest` are retired.
- Video upload enforces `max_presentation_videos`, `max_acting_videos`, `max_ugc_videos` per tier; presentation-video upload is gated for Free (quota 0).
- Public + producer resources mask videos beyond per-type quotas; owner / admin responses return all stored videos with per-item `is_locked` + `lock_reason`.
- Over-quota video is preserved on downgrade (not deleted in this story); the bounded 90-day retention + purge is owned by FP-2.14.
- ≥ 30 feature tests covering every (tier × video type × viewer lens) combination.

**Depends on:** FEATURE-FP-2.1 (capabilities matrix) and FEATURE-FP-2.2 (shared masking shape `is_locked` / `lock_reason`).

---

#### FEATURE-FP-2.3: Face subscription status API exposing capabilities matrix + tier offers

**Description:** Refactor FP-1.3 status endpoint to return the capabilities matrix for the current tier plus the four tier offers (price + capabilities) for upgrade UI. Breaking change versus FP-1.3 (which exposed only `isPremium` + flat quota); since FP-1 is undeployed, this is acceptable.

**Acceptance Criteria (draft):**
- `GET /api/v1/face/subscription-status` returns `{ current: { tier, status, starts_at, expires_at, capabilities }, offers: [{ tier, price, currency, capabilities }, …4 tiers], cta: { upgrade_available, downgrade_available, renew_available } }`.
- All four tier offers are returned with full capabilities so the frontend can render comparison cards without extra calls.
- ≥ 15 feature tests covering Free state, each paid tier state, expired state, pending payment state, status during a pending tier-change.

---

#### FEATURE-FP-2.4: Admin subscription operations with tier param + extended audit

**Description:** Refactor FP-1.4 admin endpoints. `activate` accepts a `plan` (`starter` | `pro` | `elite`) parameter. New admin action `change_tier` to move a Face from one paid tier to another with audit row.

**Acceptance Criteria (draft):**
- `POST /api/v1/admin/faces/{face}/subscriptions` accepts `plan` in the body; validation rejects unknown plans.
- New `POST /api/v1/admin/face-subscriptions/{id}/change-tier` endpoint with `new_plan` parameter.
- Audit shape extended: `previous_state` + `new_state` include `tier` field.
- All existing FP-1.4 admin actions (extend, cancel, correct) preserved with same conflict codes (`ALREADY_ACTIVE`, `PENDING_PAYMENT_EXISTS`, etc.).
- Same role gating as FP-1.4 (`auth:sanctum`, `api.token`, `admin`, `admin.role:superadmin,admin`).
- ≥ 25 feature tests covering each action × each tier transition matrix.

---

#### FEATURE-FP-2.5: Annual payment flow with tier selection + upgrade/downgrade without pro-rata

**Description:** Refactor FP-1.5 payment initiation to accept a tier choice. Build the upgrade/downgrade flow: the Face confirms tier change knowing remaining days are forfeited, a new pending row is created for the target tier, on Fedapay confirmation the old active subscription is cancelled with `cancelled_at = new.starts_at` and the new one becomes active for 12 months.

**Acceptance Criteria (draft):**
- `POST /api/v1/face/subscription/initiate-payment` accepts `plan` parameter; validation rejects unknown plans; rejects `plan === 'free'` (Free is implicit, no payment).
- Tier-change confirmation flow: new pending row created via same idempotency contract as FP-1.5 (Face row lock + Cache::lock + provider_reference unique constraint).
- Webhook `HandleFedapayWebhook` reads tier from pending row metadata; on activation, transitions old active → cancelled and new pending → active atomically in `DB::transaction`.
- Loss-of-days warning is part of the API response when initiating an upgrade/downgrade ("vous perdez X jours de votre abonnement actuel").
- Replay safety preserved (FP-1.5 invariant): replayed webhook on a tier change never produces two active subscriptions.
- ≥ 35 feature tests covering each tier's first-time activation, tier upgrade, tier downgrade, webhook replay on each, conflict cases.

---

#### FEATURE-FP-2.6: Producer-facing search ordering by tier priority (4 buckets)

**Description:** Refactor FP-1.6 binary EXISTS-subquery elevation into a tier-priority ORDER BY. Élite Faces appear first, then Pro, then Starter, then Free. Within a bucket, existing tiebreakers (e.g. created_at, rating) apply.

**Acceptance Criteria (draft):**
- Producer Face listing query orders by `(tier_priority ASC, …existing tiebreakers…)` where `tier_priority` is derived from the active subscription (Élite=1, Pro=2, Starter=3, Free=4).
- The previous `is_featured_by_subscription` admin-only flag from FP-1.6 is removed or rewritten as `subscription_tier` exposing the tier string.
- Eager-load avoids N+1 across listing queries.
- ≥ 15 feature tests: producer list ordering with mixed-tier Faces, ordering after tier change, ordering after expiration, ordering with all-Free Faces.

---

#### FEATURE-FP-2.7: Face Profile UI — Minimalist Subscription Card + Resume-Pending + Redirect to /pricing

**Description:** Frontend-only refactor of the Face profile page (`/face/profile`) subscription surface. Replace the FP-1.7 SubscriptionCard with a minimalist current-subscription card (Gemini MCP for visual design, reusing FP-2.13 hi-fi tokens for visual consistency) showing the active tier + expiry + status indicator. Adds a primary CTA "Changer de plan" that redirects to `/pricing` (FP-2.13 / FP-2.13.1) for tier comparison + payment flow. Adds a "Continuer le paiement" button to the pending-payment banner that reopens the Fedapay checkout URL stashed in `sessionStorage` at initiate time — closes the UX gap where an interrupted payment (tab closed, refresh, navigation, popup blocked) leaves the Face stuck. Composables (`useSubscriptionStatus`, `useSubscriptionPayment`), types, `tierPresentation`, `TierCard`, `TierChangeModal`, and the photo album tier-aware work are preserved in `frontend/src/features/face/...` for shared consumption by both this minimalist profile panel AND FP-2.13.1's `/pricing` page.

**Design contract:** the minimalist profile card uses Gemini MCP for visual design, reusing the FP-2.13 hi-fi tokens (teal `#198496`, dark `#0F1419`) so the in-app surface and the public `/pricing` page stay visually consistent.

**Acceptance Criteria (draft):**
- Frontend-only story (zero backend changes).
- `SubscriptionPanel.vue` rewritten as a minimalist current-subscription card (no tier cards, no change modal in profile).
- CTA "Changer de plan" → `router.push('/pricing')` (target page delivered by FP-2.13 + FP-2.13.1).
- Pending-payment banner gains a "Continuer le paiement" button reading sessionStorage (key `weact:pending-checkout:{subscription_id}`), reopens Fedapay tab + starts polling.
- Photo album tier-aware preserved (locked states, `max_album_photos` from capabilities).
- All copy in correct French with proper accents.
- ≥ 10 new/updated frontend tests covering the minimalist card render, the CTA route navigation, the resume-pending flow.

**Split note (2026-05-22 correct-course):** the per-type **video** locked states and the video upload UI were split out to **FEATURE-FP-2.7.1** — the FP-2.2.1 `face_videos` restructure made the video portion a full UI rebuild.

**Split note (2026-05-23 correct-course):** the 4 tier cards + upgrade/downgrade flow + Fedapay flow originally placed in `/face/profile` (FP-2.7 v1, implemented + reviewed on 2026-05-22 with 11 code-review patches) are split out to **FEATURE-FP-2.13.1** — the public `/pricing` page is the natural authoritative home for plan selection + payment, not the profile editor. FP-2.7 v2 keeps only the in-profile minimalist surface + the redirect CTA + the resume-pending mechanic. The previous FP-2.7 implementation code (composables, types, TierCard, TierChangeModal, tierPresentation, photo album work) is preserved on disk and consumed by FP-2.13.1. See `planning-artifacts/sprint-change-proposal-2026-05-23.md`.

---

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

---

#### FEATURE-FP-2.8: Expiration command: drop any tier back to Free

**Description:** Refactor FP-1.8 hourly expiration command. The command transitions any paid tier (Starter, Pro, Élite) whose `expires_at <= now()` to status `expired`, dropping the Face back to implicit Free. Same "no media deletion" invariant.

**Acceptance Criteria (draft):**
- Command `subscriptions:expire-faces` runs hourly via `routes/console.php`.
- Each paid tier handled identically: row status `active → expired`, no audit row written, no email (FP-2.9 owns notifications).
- Logged per-row transition with tier info.
- ≥ 12 feature tests covering each tier's expiration, boundary second, idempotence on repeat invocation.

---

#### FEATURE-FP-2.8.1: Stale Pending Cleanup + User-Initiated Cancel

**Description:** Backend hardening to resolve stuck `pending_payment` rows that block legitimate user re-attempts. Without this story, a Face whose Fedapay payment got interrupted (tab closed, network drop, popup blocked, session expired beyond 24h) is left with an indefinite `pending_payment` row — any new `initiatePayment` attempt returns `409 PENDING_PAYMENT_EXISTS` until an admin manually intervenes (cf. `deferred-work.md` D3 partial defer surfaced by FP-2.7 v2 code review). FP-2.7 v2's frontend-only sessionStorage "Continuer le paiement" mechanic covers the happy case (same browser session, within ~24h) but cannot help cross-session / cross-device / post-Fedapay-session-expiry. This story adds the backend safety net.

**Two complementary mechanisms:**

1. **Hourly cron `subscriptions:fail-stale-pending`** — automatic safety net:
   - Query : `FaceSubscription::where('status', PendingPayment)->where('created_at', '<', now()->subHours($maxHours))`.
   - For each match : `update(['status' => Failed, 'metadata' => array_merge([...existing], ['stale_pending_at' => now()->toIso8601String(), 'stale_pending_reason' => 'auto_failed_by_cron'])])`.
   - TTL configurable : `config('face_subscription_tiers.stale_pending_max_hours', 48)` — 48h is conservative (Fedapay sessions expire at ~24h, 48h gives a 2× buffer for webhook delivery edge cases).
   - Idempotent : re-running the cron on already-Failed rows is a no-op (filter excludes them).
   - Logged per-row with `face_subscription_id`, `face_id`, `created_at`, `tier`.
   - Registered in `routes/console.php` as `app(Schedule::class)->command(FailStalePendingFaceSubscriptionsCommand::class)->hourly()`.

2. **User-initiated cancel endpoint `POST /api/v1/face/subscription/cancel-pending`** — immediate control:
   - Auth : `auth:sanctum` + `api.token` + `face_role` (same middleware stack as other `/face/subscription/*` routes).
   - Body : empty (the FP-2.5 invariant guarantees a Face has at most one `pending_payment` row at a time).
   - Service : `FaceSubscriptionPaymentService::cancelOwnPending(Face $face): FaceSubscription`.
     - `lockForUpdate` on the row.
     - If no `pending_payment` row → throw `FaceSubscriptionConflictException::noPendingPayment()` → `404 NO_PENDING_PAYMENT`.
     - If pending found → `update(['status' => Failed, 'metadata' => array_merge([...existing], ['cancelled_by_user_at' => now()->toIso8601String(), 'cancellation_source' => 'user_self_cancel'])])`. No refund (the row was pending — never paid).
   - Response 200 : `{ "data": { "subscription_id": "<uuid>", "status": "failed" }, "message": "Paiement annulé." }`.
   - Response 404 : `{ "error": { "code": "NO_PENDING_PAYMENT", "message": "Aucun paiement en attente à annuler." } }`.

**Resolved design decision — race between user cancel and Fedapay webhook:** the `lockForUpdate` inside `cancelOwnPending` serializes against `HandleFedapayWebhook::markAsPaid` / failure handler. If the webhook wins the race → row becomes `Active`, the user cancel sees `status != PendingPayment` and returns 404. If the user wins → row becomes `Failed`, the webhook arrival sees non-pending and routes through the existing "ignoring failure for non-pending face subscription" branch (`FaceSubscriptionPaymentService.php:376-385`). No double-update. Edge case rare (~ms window) and the user accepts the consequence by clicking.

**Acceptance Criteria (draft):**
- Backend-only story (zero frontend changes — frontend wiring deferred to FP-2.13.1 OR a follow-up FP-2.7 v2.1 patch).
- Cron `subscriptions:fail-stale-pending` registered hourly ; respects `config('face_subscription_tiers.stale_pending_max_hours', 48)` ; auto-fails pending rows with full metadata audit ; logs per-row.
- Endpoint `POST /api/v1/face/subscription/cancel-pending` ships with route + controller + form-request + service method ; respects Face role + email_verified gating ; returns 200/404 envelopes per the spec.
- New `FaceSubscriptionConflictException::noPendingPayment()` factory (`409` → adapted to `404` at the controller layer since it's a "not-found" semantic, not a conflict — to be confirmed during dev).
- `FaceSubscriptionStatus::Failed` enum already exists (no new status).
- Metadata fields added on Failed transitions : `stale_pending_at` (cron path), `cancelled_by_user_at` + `cancellation_source` (user path).
- ≥ 8 backend tests for the cron (no-rows, 1-row-fresh-skipped, 1-row-old-failed, multiple-rows-mixed-ages, idempotence, config TTL boundary, ≠pending statuses untouched, metadata correctness).
- ≥ 6 backend tests for the endpoint (success, no-pending 404, race-condition simulation, auth-required, face-only role, webhook-already-failed no-double-update).
- Zero regressions on the FP-2.5/2.7 backend tests (full backend suite passes).
- Pint + PHPStan level 5 clean on all touched files.

**Non-scope:**
- No frontend wiring (the "Annuler ce paiement" button on the pending banner lives in FP-2.13.1 once `/pricing` auth flow ships, or a follow-up surgical edit to FP-2.7 v2).
- No `regenerate-checkout-url` endpoint (refresh a Fedapay session for a stuck pending — deferred further, requires Fedapay API integration + idempotency work, only useful if the cron TTL is felt too long).
- No notification/email on auto-fail (the user discovers the failed state when they re-visit `/face/profile`).
- No retroactive backfill of metadata on pre-existing stuck rows (the cron only catches new pendings created post-deployment).

**Depends on:** FEATURE-FP-2.5 (payment service is the integration point — both `initiate` for `created_at`/`metadata.initiated_at` and the webhook for the race-condition contract).

**Closes (partially) :** `deferred-work.md` D3 entry (popup-blocked dangling row). The cron resolves the "stuck forever" symptom ; the user-cancel endpoint adds immediate control. Full robustness (cross-device persistence, URL regeneration) still requires FP-2.8.2 or equivalent if/when product asks for it.

---

#### FEATURE-FP-2.9: Renewal reminders + lifecycle notifications with per-tier copy

**Description:** Refactor FP-1.9 reminder cron + lifecycle event listeners. Three lifecycle events preserved (Activated, Expired, Cancelled). Mailables and in-app notifications receive the tier as part of the payload; templates render tier-specific copy ("Votre abonnement Pro expire dans 30 jours").

**Acceptance Criteria (draft):**
- 3 events × 4 tiers = 12 distinct email/notification variations covered by tests.
- Reminder cron (30d / 7d) preserves the per-tier idempotency from FP-1.9 (`reminder_sent_at_30d`, `reminder_sent_at_7d`).
- ≥ 20 feature tests covering reminder dispatch per tier, lifecycle event dispatch per tier, mailable rendering per tier.

---

#### FEATURE-FP-2.10: Admin UI: tier selector in subscription section + extended audit display

**Description:** Refactor FP-1.11 frontend admin section. Activate modal gains a tier `<select>`. New "Change tier" modal for tier-change action. Audit display shows tier transitions explicitly.

**Acceptance Criteria (draft):**
- Frontend-only story (zero backend changes; consumes FP-2.4 endpoints).
- Activate modal: tier `<select>` with Starter / Pro / Élite options, default Starter.
- New "Change tier" modal: same audit-notes pattern as other admin modals.
- Audit row display shows `previous_state.tier → new_state.tier` prominently.
- All admin UI patterns from FP-1.11 preserved (Teleport+Transition modals, conflict code surfacing, etc.).
- ≥ 25 frontend tests covering tier selector validation, change-tier flow, audit display.

---

#### FEATURE-FP-2.11: Regression matrix 4 tiers × 6 states + rollout runbook update

**Description:** Refactor FP-1.10 `FaceSubscriptionRegressionMatrixTest` to cover 4 tiers (Free, Starter, Pro, Élite) × 6 subscription states (pending_payment, active, expired, cancelled, failed, no-subscription-row-for-Free) × N viewer lenses (public_unauth, producer_auth, owner, admin). Refactor the audit command and the rollout runbook to reflect the new tier model.

**Acceptance Criteria (draft):**
- Matrix test fixture-generates 24 base Face states (4 tiers × 6 states) and asserts each viewer lens's visibility contract per state.
- Matrix covers the FP-2.14 retention window: media within the 90-day window stays visible privately ; media past the window is purged.
- `faces:audit-premium-readiness` command extended with per-tier counts in Section A; new Section E for tier distribution; new section for media pending purge.
- `docs/runbook-face-premium-rollout.md` rewritten with FP-2 tier model, per-tier rollback paths, env var matrix, and the 90-day purge command operational notes.
- ≥ 100 assertions across the matrix test ; full backend suite green.

---

#### FEATURE-FP-2.12: Public "VIP / Élite" badge on Face profile + producer-facing views

**Description:** Add a new field `has_elite_badge: bool` to public + producer Face resources, sourced from the FP-2.1 capabilities matrix. Frontend renders the badge inline next to the Face name on the public profile and in producer search results.

**Acceptance Criteria (draft):**
- `PublicFaceProfileResource` + `ProducerFaceResource` expose `has_elite_badge` boolean.
- Badge disappears immediately on tier change or expiration (no caching).
- Frontend renders an "Élite VIP" badge on public profile detail + producer Face cards.
- Badge accessible: `aria-label="Profil Élite VIP"`.
- ≥ 12 feature tests (backend resource shape per tier) + ≥ 8 frontend tests (badge rendering per tier).

---

#### FEATURE-FP-2.13: Public `/pricing` page refonte (4-tier hi-fi design handoff)

**Description:** Replace the existing `frontend/src/views/PricingView.vue` (legacy producer-oriented monthly pricing) with the 4-tier Face pricing page delivered by the Claude Design hi-fi handoff at `docs/design/Weact Subs Design/design_handoff_pricing/`. The handoff `PricingView.vue` is production-grade Vue 3 + TS + Tailwind, static (no API, no Pinia), pre-auth. This story integrates it pixel-accurately, fixes the CTA routes, applies the content corrections listed below, and adds component tests.

**Design source of truth:** `docs/design/Weact Subs Design/design_handoff_pricing/PricingView.vue` (target component) + `references/` (visual prototypes) + `README.md` (design tokens, anatomy, responsive spec). The story file must embed the design tokens verbatim so the dev agent does not guess.

**Content corrections to apply during integration** (the handoff was authored before two FP-2 product decisions were finalized — these are NOT visual changes):
- **Pro photo count**: handoff `PricingView.vue` card features + comparison table say "2 photos" for Pro. Correct to **4 photos** (Pro card feature line + `comparisonGroups` "Photos dans la galerie" row → `pro: '4'`).
- **FAQ #1 (upgrade)**: handoff answer says the upgrade complement is "calculé au prorata". FP-2 Product Decision #3 is **upgrade/downgrade WITHOUT pro-rata**. Rewrite the answer: upgrading charges the full annual price of the new tier, the current subscription is cancelled, the 12-month period restarts, and remaining days are forfeited.
- FAQ #4 (90-day retention) is **correct as written** — it matches FP-2 Product Decision #11. No change.

**Acceptance Criteria (draft):**
- `frontend/src/views/PricingView.vue` replaced with the handoff component, integrated to WeAct conventions.
- The `/pricing` route already exists in `frontend/src/router/index.ts:90` — no router change required for the page itself.
- CTA routes verified/created: `/register/face`, `/register/face?plan=starter|pro|elite`, `/contact`. `/register/face` exists at `router/index.ts:97`; the `?plan=` query handling and `/contact` must be verified.
- The two content corrections above are applied.
- Pixel-accurate to the handoff: design tokens (teal `#198496`, dark `#0F1419`, surface `#FAFAFA`), card anatomy (ladder indicator, Élite dark card, "Populaire" badge on Pro), comparison table, FAQ accordion, footer CTA.
- Responsive: 1 col < 640px, 2 col 640-1024px, 4 joined col ≥ 1024px (per handoff README).
- FAQ accordion `aria-expanded` wired; first item open by default.
- ≥ 12 frontend tests (Vitest + Vue Test Utils): tier card rendering, FAQ toggle, comparison table cell rendering (boolean / string), CTA route targets, responsive class assertions.

**Non-scope:** This page is static marketing copy — it does NOT consume the FP-2.3 status API. The "block Postuler UGC for Free" note in the handoff README is a cross-module concern (out of FP-2, see below).

**Split note (2026-05-23 correct-course):** the authenticated payment flow on `/pricing` (tier selection for logged-in Faces, payment initiation, modal interaction) is split out to **FEATURE-FP-2.13.1**. FP-2.13 stays as the static visual base — pre-auth marketing copy with register CTAs. FP-2.13.1 adds the auth-aware layer on top.

---

#### FEATURE-FP-2.13.1: /pricing Page Authenticated Payment Flow

**Description:** Frontend-only story split out of FEATURE-FP-2.13 (2026-05-23 correct-course). Extends the static `/pricing` page (delivered by FP-2.13) with auth-aware behavior: for unauthenticated visitors the page renders register CTAs (FP-2.13 default), for authenticated Faces the page renders tier-selection CTAs that open `TierChangeModal` and drive `useSubscriptionPayment.initiatePayment(plan)`. Reuses `TierCard.vue` / `TierChangeModal.vue` / `tierPresentation.ts` / `useSubscriptionStatus` / `useSubscriptionPayment` (all delivered by FP-2.7 v1 and preserved through the 2026-05-23 split). Handles the `?plan=` query param for deep-link preselection.

**Context — why this is a separate story:** the original FP-2.7 placed the tier cards + change modal + payment flow in `/face/profile`. Sprint change 2026-05-23 moved this surface to the public `/pricing` page so the profile page can be minimalist + uncluttered. FP-2.13 stays static (the visual base); FP-2.13.1 is the auth-aware layer. Same split pattern as FP-2.2 → FP-2.2 + FP-2.2.1 and FP-2.7 → FP-2.7 + FP-2.7.1.

**Acceptance Criteria (draft):**
- Frontend-only story (zero backend changes — consumes FP-2.3 status + FP-2.5 payment + the static `/pricing` from FP-2.13).
- Unauthenticated visitors: register CTAs (FP-2.13 default behavior).
- Authenticated Face: replaces register CTAs with tier-selection openers; consumes `useSubscriptionStatus`; current tier highlighted via `ring-2 ring-[#198496]`; relations (current / upgrade / downgrade / unavailable / activate / renew) computed per the FP-2.7 v1 logic.
- Click → mounts existing `TierChangeModal.vue` with appropriate mode + forfeitedDays.
- Confirm → `useSubscriptionPayment.initiatePayment(plan)` opens Fedapay + polls; the panel banners (waiting / payment-failed) mirror the FP-2.7 v1 flow.
- `?plan=starter|pro|elite` query param preselects the card (opens modal if logged in, scrolls to card if not).
- All previous code-review patches P1-P11 from FP-2.7 v1 review apply here (P6 Free guard + P9 Escape guard migrate naturally since the modal lives here now).
- ≥ 15 new frontend tests (Vitest + Vue Test Utils).

**Depends on:** FEATURE-FP-2.13 (static page) + FEATURE-FP-2.7 v2 (composables + TierCard/TierChangeModal preserved on disk).

---

#### FEATURE-FP-2.14: 90-day post-expiration media retention window + purge command

**Description:** Implement the bounded media-retention policy (Product Decision #11, FEAT-FP2-FR14). After a downgrade or expiration, over-quota media stays stored and privately visible for 90 days; a scheduled command then permanently deletes it. This deliberately supersedes the FP-1 "never delete" invariant. The public `/pricing` FAQ commits to this policy, so the backend must honor it.

**Acceptance Criteria (draft):**
- A `retention_until` (or equivalent) timestamp is set on over-quota media — or computed from the subscription `expires_at` / downgrade date — marking the 90-day window.
- New scheduled command (e.g. `faces:purge-expired-media`) runs daily; for each Face whose retention window has elapsed AND whose current tier still does not cover the media, it deletes the file on disk + the DB row.
- Media that the Face's current tier covers (e.g. after re-subscribing to a tier that re-covers it) is never purged — re-subscription within the window fully restores access.
- Purge is logged per-item; no purge of media still within the 90-day window; idempotent on repeat invocation.
- Purge respects all three media types (photos beyond quota, acting video, UGC video).
- Depends on FP-2.8 (expiration transitions) and FP-2.2 / FP-2.2.1 (quota model + over-quota identification).
- ≥ 20 feature tests: window not elapsed → no purge; window elapsed → purge; re-subscription within window → no purge; re-subscription that re-covers media → no purge; per-media-type coverage; idempotence.

---

## Out of FP-2 Scope (scheduled separately)

These items are surfaced in the spec but explicitly excluded from FP-2:

### Élite commission 5 % on monetary missions

The spec mandates that Élite-tier Faces are charged 5 % platform commission on monetary missions instead of the default 10 %. This change touches the **booking/mission payout module** — not the Face subscription module. Implementing it inside FP-2 would widen the epic's blast radius onto code FP-2 stories should not touch (per FEAT-FP2-NFR4). It will be a dedicated story (`mission-commission-tier-aware`) once FP-2 is `done` and the capabilities matrix is stable.

### UGC mission gating for Free Faces

The spec requires blocking the "Postuler" button and masking UGC mission details for Free Faces. The UGC mission module **does not yet exist** in the codebase. This requirement will be picked up as part of the future UGC module epic, where Free-tier gating is a natural constraint to bake in from the start.

## Implementation Approach Notes

- **No data migration**: FP-1 schema changes are applied freely. The `face_subscriptions` table from FP-1 is migrated by schema-replacement, not data-migration.
- **FP-1 files are overwritten, not duplicated**: FP-2 stories edit the same paths FP-1 created (`FaceEntitlementService.php`, `face_subscriptions` migration, etc.). The story files in `_bmad-output/implementation-artifacts/feature-fp-1-*.md` remain as historical reference.
- **Story files must follow the FP-1 discipline pattern**: read-this-line citations, fixture squelette concret for multi-entity tests, explicit non-scope défensif. FP-1 retrospective lessons (see `epic-feature-fp-1-retro-2026-05-20.md` action items #1 and #2) are baked into the dev/review process for FP-2.
- **Chained reviewer passes**: per FP-1 retro action item #2, on stories that need multiple review passes, reviewer #2 receives reviewer #1's findings as input to dedup and focus on the delta.
- **Capabilities matrix is the contract**: every entitlement decision in the codebase MUST consume `FaceEntitlementService::capabilities()`. No controller, resource, or service should branch on `plan` strings directly. Audit pass at FP-2.11 verifies this.
