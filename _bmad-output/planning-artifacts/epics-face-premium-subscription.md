---
stepsCompleted: [1, 2]
status: 'draft'
draftedAt: '2026-05-03'
totalEpics: 1
totalStories: 10
project_name: 'WEACT - Annual Face Premium Subscription Sprint 14'
user_name: 'Amakira'
date: '2026-05-03'
---

# WEACT - Annual Face Premium Subscription Sprint 14 - Epic Breakdown

## Overview

Sprint 14 introduces a paid annual subscription for Faces who want stronger public visibility and a larger public portfolio. The product decision is intentionally annual-only because the Benin economic/payment context does not support reliable monthly automatic debits. The operating model is therefore: the Face pays once, WEACT activates premium benefits until `expires_at`, and benefits are removed automatically when the annual period expires.

The first paid benefits are:

- Featured public placement for subscribed Faces.
- Public visibility of up to 4 album photos.
- Free/non-subscribed Faces keep only 2 album photos publicly visible.
- Existing photos 3-4 are never deleted when a subscription expires; they remain stored and visible to the Face in their private album management area, but they are hidden from public and producer-facing profile views until the subscription is active again.
- Public visibility of both the presentation video and the acting video for premium Faces.
- Free/non-subscribed Faces keep only the presentation video publicly visible; the acting video becomes a premium-only feature.
- Existing acting videos are never deleted on downgrade; they remain stored and visible to the Face in their private profile management area, but they are hidden from public and producer-facing profile views until the subscription is active again.

### Current Codebase Baseline

- `faces.is_featured` already exists and is used to prioritize public Face listing.
- Album upload is currently hard-limited to 4 photos via `PhotoAlbumService::MAX_PHOTOS`.
- Public Face profile detail returns album photos through `PublicFaceProfileResource`.
- Private Face album management returns all photos through the authenticated album endpoint.
- Presentation and acting videos are stored as two fixed columns on `faces` (`presentation_video`, `acting_video`), uploaded via `PresentationVideoService`/`PresentationVideoController` and `ActingVideoService`/`ActingVideoController`. Both videos are currently uploadable by every Face regardless of subscription state, and both are exposed publicly through `PublicFaceProfileResource`.
- Payment infrastructure already exists in adjacent domains (Fedapay, wallet, financial events, mission/booking payment flows), but there is no dedicated Face subscription table or annual plan model yet.

### Product Decisions

1. **Annual only**: no monthly recurring billing, no automatic monthly debit, no proration for MVP.
2. **No media deletion on downgrade**: photos beyond the free quota and the acting video beyond the free quota remain stored on disk and in DB.
3. **Public masking on downgrade**: photos 3-4 and the acting video are hidden publicly when the Face is not premium.
4. **Private transparency**: the Face can still see all stored photos and the acting video in their private management area, and understand which ones are publicly locked.
5. **Admin fallback is required**: because local payment operations may need manual intervention, admins must be able to activate, extend, cancel, or correct annual subscriptions.
6. **Featured placement is entitlement-driven**: paid featured visibility should be derived from an active subscription/feature window, not only a permanent admin boolean.
7. **Price is configuration-driven**: the annual plan amount must be stored in backend configuration/env, not hard-coded in frontend code.
8. **Subscription state is API-driven**: the Face UI consumes a backend status/entitlements endpoint instead of duplicating entitlement rules client-side.
9. **Acting video is a premium feature, presentation video is universal**: every Face can upload a presentation video so a free profile remains regardable; only premium Faces can upload and publicly expose the acting video. Existing acting videos on free Faces are masked, not deleted, to avoid destroying user content and to keep the upgrade path frictionless.

## Requirements Inventory

### Functional Requirements

**FEAT-FP-FR1**: The backend must persist annual Face subscriptions with plan, status, payment metadata, start date, expiry date, and cancellation fields.

**FEAT-FP-FR2**: The backend must expose a single entitlement source of truth for Face premium capabilities: public album photo limit, upload limit, featured status, and expiry.

**FEAT-FP-FR3**: Album upload and validation must become entitlement-aware: free Faces can upload up to 2 photos; active premium Faces can upload up to 4 photos. Acting video upload must also become entitlement-aware: free Faces are blocked from uploading an acting video; active premium Faces can upload one. Presentation video upload remains available to every Face.

**FEAT-FP-FR4**: Public and producer-facing profile responses must hide photos and the acting video beyond the active public quota. Private Face management responses must return all photos and the acting video, and mark locked/non-public media.

**FEAT-FP-FR5**: Annual payment initiation and confirmation must activate the subscription for 12 months. Payment confirmation must be idempotent.

**FEAT-FP-FR6**: Admin users must be able to view, activate, extend, cancel, and correct Face subscriptions for operational support.

**FEAT-FP-FR7**: The Face dashboard/profile area must show subscription state, annual expiry, available benefits, album quota, and locked photo states.

**FEAT-FP-FR8**: Expired subscriptions must automatically stop premium benefits and featured placement without deleting stored media.

**FEAT-FP-FR9**: The backend must expose a Face subscription status endpoint returning current plan, status, expiry, quota, entitlements, and payment CTA metadata.

**FEAT-FP-FR10**: Admin subscription mutations must be auditable with admin id, action, notes, previous state, and new state.

## Epic & Story Breakdown

---

### Epic FEATURE-FP-1: Annual Face Premium Subscription & Featured Portfolio

**Goal:** Launch a production-ready annual premium subscription for Faces that unlocks paid featured placement and up to 4 publicly visible album photos, while keeping the free tier at 2 public photos and preserving photos 3-4 privately after downgrade or expiration.

**Priority:** High — revenue feature, public discovery impact, and direct dependency on clear payment/admin operations.

#### Stories

| ID | Story | FRs | Priority |
|----|-------|-----|----------|
| FEATURE-FP-1.1 | Subscription schema and entitlement service | FEAT-FP-FR1, FEAT-FP-FR2 | High |
| FEATURE-FP-1.2 | Dynamic album quota, public photo masking, and acting video premium gating | FEAT-FP-FR3, FEAT-FP-FR4 | High |
| FEATURE-FP-1.3 | Face subscription status and entitlement API | FEAT-FP-FR2, FEAT-FP-FR7, FEAT-FP-FR9 | High |
| FEATURE-FP-1.4 | Admin subscription operations and audit trail | FEAT-FP-FR6, FEAT-FP-FR10 | High |
| FEATURE-FP-1.5 | Annual payment initiation and idempotent activation | FEAT-FP-FR5 | High |
| FEATURE-FP-1.6 | Featured placement driven by active subscription | FEAT-FP-FR2, FEAT-FP-FR8 | High |
| FEATURE-FP-1.7 | Face subscription UI and locked album states | FEAT-FP-FR7, FEAT-FP-FR9 | Medium |
| FEATURE-FP-1.8 | Expiration command and entitlement removal | FEAT-FP-FR8 | High |
| FEATURE-FP-1.9 | Renewal reminders and subscription notifications | FEAT-FP-FR8 | Medium |
| FEATURE-FP-1.10 | Regression coverage and rollout/backfill safeguards | FEAT-FP-FR1-FR10 | High |

**Recommended delivery order:**

1. **FEATURE-FP-1.1** — schema and entitlement source of truth.
2. **FEATURE-FP-1.2** — enforce quotas and public/private visibility contract.
3. **FEATURE-FP-1.3** — expose the subscription status/entitlement contract needed by frontend and QA.
4. **FEATURE-FP-1.4** — admin activation fallback, useful before payment automation is fully live.
5. **FEATURE-FP-1.5** — annual payment flow and webhook/confirmation activation.
6. **FEATURE-FP-1.6** — wire featured placement to active premium entitlement.
7. **FEATURE-FP-1.7** — Face-facing subscription and album UX.
8. **FEATURE-FP-1.8** — expiry automation for entitlement removal.
9. **FEATURE-FP-1.9** — reminders and lifecycle notifications.
10. **FEATURE-FP-1.10** — final regression sweep, rollout checklist, and data safeguards.

---

#### FEATURE-FP-1.1: Subscription schema and entitlement service

**Description:** Add the persistence and domain layer for annual Face subscriptions. Create a subscription table/model, status enum, plan enum/value object if needed, and a service that answers entitlement questions for a Face without scattering premium checks through controllers/resources.

**Acceptance Criteria (draft):**
- Migration creates a subscription table linked to `faces` with fields for `plan`, `status`, `starts_at`, `expires_at`, `cancelled_at`, `paid_amount`, `currency`, `provider`, `provider_reference`, and timestamps.
- Subscription statuses cover at least `pending_payment`, `active`, `expired`, `cancelled`, and `failed`.
- The model exposes an active scope based on `status = active` and `expires_at > now()`.
- `FaceEntitlementService` or equivalent exposes methods for:
  - `albumUploadLimit(Face $face): int`
  - `publicAlbumPhotoLimit(Face $face): int`
  - `isPremium(Face $face): bool`
  - `isFeaturedBySubscription(Face $face): bool`
- Free tier returns 2 photos; active annual premium returns 4 photos.
- Unit/feature tests cover active, expired, cancelled, and missing subscription cases.

**Technical Notes:**
- Keep entitlement checks server-side. Frontend may display state, but backend remains source of truth.
- Prefer integer XOF amounts.
- Use explicit date fields rather than deriving expiry from payment timestamps in every caller.
- Avoid overloading `faces.is_featured` as the only paid-state source; keep admin manual featuring and paid featuring distinguishable.

---

#### FEATURE-FP-1.2: Dynamic album quota, public photo masking, and acting video premium gating

**Description:** Replace the fixed album limit with entitlement-aware limits. Public and producer-facing APIs return only photos allowed by the current subscription. Private Face album management returns all stored photos, with metadata explaining which photos are publicly visible or locked. The same gating is applied to videos: the presentation video remains free for every Face, but the acting video becomes a premium-only feature for upload and public exposure. Existing acting videos on free Faces are masked in public responses, never deleted.

**Acceptance Criteria (draft):**

Album quota and masking:
- Free Face upload is blocked after 2 album photos.
- Active premium Face upload is allowed up to 4 album photos.
- A Face with 4 stored photos and no active premium subscription receives all 4 photos from the private album endpoint, with photos 3-4 marked as not publicly visible.
- Public Face profile response returns only photos 1-2 for non-premium Faces.
- Public Face profile response returns photos 1-4 for active premium Faces.
- Producer candidate/profile views apply the same public visibility rule unless the endpoint is explicitly owner/admin-only.
- `has_album_photos` and `album_photos_count` reflect visible public photos in public responses.
- Tests cover downgrade: premium Face with 4 photos becomes expired; no files are deleted; public API returns 2; private API returns 4.

Video quota and masking:
- Presentation video upload remains available to every Face (free and premium), unchanged from current behavior.
- Acting video upload (`POST` on `ActingVideoController`) is blocked for free Faces with an HTTP 403 and a French error message; only Faces with an active premium subscription can upload an acting video.
- Public Face profile response returns `acting_video = null` (and the derived `acting_video_url` / `acting_video_thumbnail_url` accessors as null) for non-premium Faces, even when the DB column is populated.
- Public Face profile response returns the acting video URLs for active premium Faces.
- Producer candidate/profile views apply the same public visibility rule unless the endpoint is explicitly owner/admin-only.
- Private Face management responses (`FaceResource` on owner/admin endpoints) always return the acting video URLs with a flag indicating whether it is publicly visible, so the Face can see their own content and understand it is locked.
- Tests cover downgrade: a Face that uploaded an acting video while premium becomes expired; the file is preserved on disk and in DB; public API hides the acting video; private API returns it with the locked flag.
- Tests cover upgrade: a free Face that already has an acting video stored in DB (e.g. uploaded before the gating shipped) automatically sees it become public again when they subscribe.

**Technical Notes:**
- Extend `FaceEntitlementService` with `canUploadActingVideo(Face $face): bool` and a public video visibility helper (e.g. `publicVideoVisibility(Face $face): array{presentation: bool, acting: bool}` or two boolean accessors). Do not introduce a separate `VideoEntitlementService`; entitlement rules stay in one place.
- Reuse the same masking pattern as the album: `PublicFaceProfileResource` consults `FaceEntitlementService` and nulls out the relevant fields rather than removing them from the response shape, so the contract remains stable for frontend.
- Use `position` as the public masking boundary for album photos. There is no equivalent ordering for videos because the schema keeps two fixed columns (`presentation_video`, `acting_video`) — keep that schema, do not introduce a `face_videos` table in this story.
- Acting video upload guard must live in the Form Request layer (e.g. `UploadActingVideoRequest`) so the controller stays thin, mirroring the album upload guard.
- The Form Request error message must be returned in French (project convention).
- Avoid deleting files or DB rows during expiration. Storage reclaim is intentionally out of scope for MVP; reconsider in a later hardening story if storage cost becomes a concern.
- Do not trust frontend counts or frontend role flags for upload enforcement; all gating decisions are server-side via `FaceEntitlementService`.

---

#### FEATURE-FP-1.3: Face subscription status and entitlement API

**Description:** Expose a backend contract that the Face dashboard/profile UI can consume to render subscription state, premium benefits, album quota, payment CTA state, and expiry without duplicating entitlement logic in the frontend.

**Acceptance Criteria (draft):**
- Authenticated Face can fetch their current subscription status.
- Response includes `status`, `plan`, `starts_at`, `expires_at`, `is_premium`, `is_featured_by_subscription`, and `can_renew`.
- Response includes entitlement limits:
  - `album_upload_limit`
  - `public_album_photo_limit`
  - `current_album_photo_count`
  - `public_album_photo_count`
  - `locked_album_photo_count`
  - `can_upload_acting_video` (boolean)
  - `has_acting_video` (boolean — true if a non-null `acting_video` exists in DB, regardless of premium state)
  - `is_acting_video_publicly_visible` (boolean — true only when premium is active AND an acting video exists)
- Response includes configured annual plan metadata: amount in XOF, currency, provider, and CTA availability.
- Free Faces receive a stable response with `status = free`, no active subscription id, and free-tier limits.
- Pending payment Faces receive enough metadata for the UI to show a pending state without exposing sensitive provider data.
- Non-Face users cannot access the Face subscription status endpoint.
- Tests cover free, pending payment, active, expired, cancelled, and non-Face access.

**Technical Notes:**
- The endpoint should use `FaceEntitlementService`; it must not recalculate entitlement rules independently.
- Keep the response public-safe for the authenticated Face; do not leak raw provider payloads.
- This story is a contract story for frontend and QA. Avoid adding UI here.

---

#### FEATURE-FP-1.4: Admin subscription operations and audit trail

**Description:** Add back-office controls so admins can support annual subscriptions even if payment confirmation requires manual intervention. Admins can inspect, activate, extend, cancel, or correct a Face subscription, and every mutation is auditable.

**Acceptance Criteria (draft):**
- Admin can view a Face subscription state on the Face detail page.
- Admin can manually activate annual premium for a Face with required reason/notes.
- Admin can extend an active subscription.
- Admin can cancel a subscription.
- Admin can correct start/expiry dates when operational support requires it.
- Admin operations write audit metadata including admin id, action, notes, previous state, and new state.
- Audit entries are persisted in a dedicated table or equivalent append-only history model.
- Non-admin users cannot access these operations.
- Tests cover each admin operation, audit creation, and authorization failure.

**Technical Notes:**
- This story is intentionally before or parallel to payment automation to support a manual launch path.
- Avoid silent changes; admin mutation endpoints should require notes.
- Prefer append-only audit rows over overwriting opaque JSON blobs.

---

#### FEATURE-FP-1.5: Annual payment initiation and idempotent activation

**Description:** Add an annual subscription payment flow. A Face can initiate payment for one annual premium plan. Successful confirmation activates or extends the subscription for 12 months. Webhook/confirmation handling must be idempotent to avoid double extensions for the same payment event.

**Acceptance Criteria (draft):**
- Authenticated Face can initiate annual premium payment.
- Backend stores a pending subscription or payment intent with provider metadata.
- Annual plan amount and currency are read from backend configuration/env.
- Successful Fedapay confirmation activates the subscription for one year.
- Replaying the same provider event/reference does not create duplicate active periods or double financial events.
- A Face cannot create conflicting concurrent pending annual subscriptions for the same plan.
- Failed/cancelled payment leaves the Face on free tier.
- API responses follow the standard `{ data, meta, message }` and `{ error: { code, message, details } }` contracts.
- Tests cover successful activation, failed payment, duplicate webhook/event, and expired previous subscription renewal.

**Technical Notes:**
- Reuse existing Fedapay patterns where possible.
- Because there is no monthly recurring debit, no retry/proration machinery is required for MVP.
- Define renewal behavior explicitly: if a Face renews while active, extend from current `expires_at`; if expired, start from `now()`.
- Record financial events for initiation/confirmation/failure using existing idempotency conventions where applicable.

---

#### FEATURE-FP-1.6: Featured placement driven by active subscription

**Description:** Connect public listing priority to active paid featured entitlement while preserving admin-controlled featuring. The public query should treat a Face as featured when either admin featuring is active or paid subscription featuring is active.

**Acceptance Criteria (draft):**
- Public Face listing prioritizes active subscription-featured Faces.
- Admin manual `is_featured` behavior remains supported.
- Expired/cancelled subscriptions stop contributing to featured ordering automatically.
- Public response still avoids exposing internal subscription/payment details.
- Admin detail view can distinguish manual featured from subscription featured.
- Ordering rules define what happens when manual featured Faces and paid featured Faces coexist.
- Ordering remains deterministic inside each featured group.
- Tests cover ordering: paid active featured first, expired paid no longer first, manual admin featured still first.

**Technical Notes:**
- Consider a computed query expression or materialized `featured_until` depending on query complexity.
- Avoid long-term reliance on a permanent boolean for paid featured state.
- Be explicit about ranking priority before implementation. Recommended MVP: paid active and manual featured are both elevated, then existing secondary sort rules apply.

---

#### FEATURE-FP-1.7: Face subscription UI and locked album states

**Description:** Add Face-facing UI for annual premium status and album visibility. The Face should understand their current plan, expiry date, quota, and why photos 3-4 may be hidden publicly.

**Acceptance Criteria (draft):**
- Face dashboard/profile area displays subscription state: free, pending payment, active, expired, cancelled.
- UI displays annual expiry date for active subscriptions.
- Album management displays quota as 2/2 for free and 4/4 for premium.
- Photos beyond the free quota show a locked/non-public state when premium is inactive.
- Video management displays the presentation video as universally available and the acting video as premium-only.
- A stored acting video on a free Face shows a locked/non-public state with a clear explanation that subscribing republishes it without re-upload.
- The acting video upload entry point is disabled for free Faces with an explanatory tooltip; the frontend never bypasses the backend guard, the disabled state is a UX hint only.
- CTA allows starting annual premium payment.
- UI handles payment pending/success/failure states.
- UI consumes the subscription status API from `FEATURE-FP-1.3` instead of reconstructing subscription rules.
- Tests cover locked photo rendering, locked acting video rendering, and quota messaging.

**Technical Notes:**
- Keep public profile UI simple; backend filtering should handle visibility.
- Use existing Vue 3 Composition API and Pinia/service patterns.
- For any new visually rich UI, use Gemini Design MCP per project instruction before implementation.

---

#### FEATURE-FP-1.8: Expiration command and entitlement removal

**Description:** Add lifecycle automation that expires annual subscriptions and removes premium entitlements automatically without deleting stored media.

**Acceptance Criteria (draft):**
- Scheduled command marks active subscriptions as expired when `expires_at <= now()`.
- Expiration does not delete photos.
- Expiration immediately causes public photo masking and removes paid featured ordering.
- Command is idempotent.
- Command outputs summary counts for expired, skipped, and failed subscriptions.
- Tests cover expiration, no media deletion, public masking after expiration, featured removal after expiration, and idempotency.

**Technical Notes:**
- Commands should be safe to run repeatedly.
- Keep reminders out of this story so entitlement removal can ship independently.

---

#### FEATURE-FP-1.9: Renewal reminders and subscription notifications

**Description:** Notify Faces about subscription lifecycle events and renewal windows without blocking the core entitlement expiration path.

**Acceptance Criteria (draft):**
- Renewal reminders are sent before expiry, at minimum 30 days and 7 days before `expires_at`.
- Expired notification tells the Face that photos 3-4 and the acting video are now hidden publicly until renewal.
- Activation notification confirms premium benefits and annual expiry date.
- Cancellation notification explains when benefits stop.
- Reminder selection is idempotent and does not send duplicate reminders for the same window.
- Tests cover reminder windows, duplicate prevention, activation notification, expiration notification, and cancellation notification.

**Technical Notes:**
- If email templates are too large for Sprint 14, keep in-app notifications and document deferred email copy.
- Prefer explicit reminder timestamp fields or notification audit rows over inferring from sent mail state.

---

#### FEATURE-FP-1.10: Regression coverage and rollout/backfill safeguards

**Description:** Add final regression tests and rollout documentation to prevent billing/visibility mistakes. Include a deployment checklist and data checks for Faces with more than 2 existing photos.

**Acceptance Criteria (draft):**
- Regression tests cover public/private album visibility across free, active premium, expired, cancelled, and admin-featured cases.
- Regression tests cover public/private acting video visibility across the same matrix.
- Tests cover that no storage delete is triggered by subscription expiry, for both album photos and the acting video.
- Tests cover payment idempotency and admin manual activation audit.
- Rollout checklist documents feature flags/env vars, annual price config, Fedapay config, scheduler requirements, and rollback plan.
- Data check identifies Faces with more than 2 photos AND Faces with an existing acting video before launch so support can anticipate locked-media UX.
- `sprint-status.yaml` tracking entries are updated.

**Technical Notes:**
- Treat this as a release hardening story, not a dumping ground for unfinished implementation.
- Keep any one-shot data query read-only unless an explicit migration/backfill is approved.
