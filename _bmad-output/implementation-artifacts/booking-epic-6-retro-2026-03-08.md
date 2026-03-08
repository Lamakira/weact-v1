# Retrospective — Booking Epic 6: Rating, Notifications & Lifecycle Polish

Date: 2026-03-08
Facilitator: Bob (Scrum Master)
Epic: Booking Epic 6
Status: Story-complete (3/3), booking domain fully closed

## Team Participants

- Bob (Scrum Master) — Facilitator
- Alice (Product Owner) — Product perspective
- Charlie (Senior Dev) — Technical perspective
- Dana (QA Engineer) — Quality perspective
- Winston (Architect) — Architecture continuity
- Amakira (Project Lead) — Delivery oversight

## Epic Summary & Metrics

**Delivery**
- Stories completed: 3/3 (100%)
  - `b6-1`: Mutual rating after booking completion (backend + frontend)
  - `b6-2`: Booking lifecycle notifications (6 listeners + shared endpoint + frontend)
  - `b6-3`: Financial reconciliation command (daily artisan command + 5 tests)
- All sprint-status stories marked `done`
- Booking domain: all 6 epics complete (16 stories total)

**Quality**
- Backend test suite: 1704 tests, 0 failures after epic close
- Notification tests: 15 tests, 60 assertions
- Reconciliation tests: 5 tests
- Code review findings: ~10 real issues across 3 stories — all fixed
- Technical debt incurred: 0 critical items
- Production incidents: 1 UI incident (stale Vite cache — not a code regression)

## Successes & Strengths

1. Event-driven architecture backbone validated — 6 listeners plugged in cleanly without touching core `BookingService` flow.
2. Non-fatal pattern (`try/catch` + `Log::warning`) applied consistently across all listeners — notification failures never affect booking lifecycle.
3. `#[AsEventListener]` attribute-based discovery (Laravel 12) worked exactly as designed — no `EventServiceProvider` hacks.
4. Set-based reconciliation query (GROUP BY + WHERE IN, 2 total queries regardless of user count) — production-ready at scale, improved by code review.
5. Code review loop consistently found real issues (not nitpicks) in every story — the process is working.
6. The execution flow across the 3 stories built naturally without retrofitting: b6-1 established the completion domain, b6-2 layered notifications on existing lifecycle events, b6-3 closed the financial loop.

## Challenges & Growth Areas

1. **Notification-layer spec gaps**: Story spec for b6-2 only mentioned notifying the Producer on payment. The Face notification requirement was discovered during implementation. Notification recipients should be enumerated explicitly per event in AC.
2. **Architecture doc inaccuracy**: Doc described reconciliation using `financial_events` vs `wallet_transactions`, but `financial_events` has no `user_id` column. Implementation had to correct course. Financial domain docs must be schema-accurate, not conceptually descriptive.
3. **Stale Vite HMR cache**: After code review fixed icon logic, browser served old JS. Looked like a regression. Hard refresh resolved it but cost debugging time.
4. **Misleading listener name**: `NotifyProducerOnBookingPaid` was extended to notify both Producer and Face, but name still implied single-party. **Fixed during retrospective** → renamed to `NotifyPartiesOnBookingPaid` (tests: 15 passed, 60 assertions).
5. **`Log::fake()` unavailable**: Test approach had to be adjusted for b6-3 — `Monolog\Logger::fake()` doesn't exist in this Laravel version. Tests verify behavior via exit codes, not log assertions.

## Key Insights & Learnings

1. Notification-layer changes (types, icons, endpoint, navigation) touch many places — review as a unified surface per story, not piecemeal.
2. When extending a listener to notify additional parties, rename the class to reflect the new scope immediately.
3. Architecture docs for data-layer stories must be verified against actual schema column existence before implementation begins.
4. Scheduler-driven commands (`bookings:reconcile-wallet`) need explicit post-deploy verification runbook items — same lesson from Epic 5 carried forward.

## Previous Retro Follow-Through (Booking Epic 5 → Booking Epic 6)

From `booking-epic-5-retro-2026-03-06.md`:

| Action Item | Status | Evidence |
|-------------|--------|----------|
| Mandatory post-deploy checklist for scheduler features | ⏳ Partial | b6-3 is scheduler-driven but no explicit post-deploy evidence in story record |
| Explicit financial-event verification notes for payment stories | ✅ Applied | b6-3 correctly identified `wallet_transactions` as source (not `financial_events`) — caught architecture doc ambiguity |
| Execute staging smoke validation for Epic 5 flows | ⏳ No evidence | Not reflected in b6 story records |
| Add operational monitoring for expiry command | ⏳ No evidence | Not reflected in b6 story records |
| Document b5-2 verification approach | ⏳ No evidence | Not reflected in story records |

The technical learnings were applied. The operational/process commitments (post-deploy smoke, monitoring) remain outstanding.

## Next Epic Preview

**No next booking epic** — the booking domain is fully closed (6 epics, 16 stories complete).

Next active work: **Epic 14** (post-roadmap features, `in-progress`). No blocking dependencies from Booking Epic 6 identified.

## Action Items

### Process Improvements

1. **Notification spec completeness** — when a story involves payment or status events, explicitly list each notification recipient in acceptance criteria.
   - Owner: Alice (Product Owner) / Bob (Scrum Master)
   - Success criteria: Story ACs enumerate all notification recipients explicitly per event.

2. **Architecture doc accuracy for financial domain** — update architecture-booking.md to clarify that `wallet_transactions` (not `financial_events`) is the correct reconciliation source per user.
   - Owner: Winston (Architect)
   - Success criteria: Doc updated before any future financial-domain story implementation.

### Technical Debt Fixed

1. ✅ **Listener rename** — `NotifyProducerOnBookingPaid` → `NotifyPartiesOnBookingPaid` (fixed during retrospective session, tests passing).

### Team Agreements

- When extending a listener to notify additional parties, rename the class immediately to reflect the new scope.
- Notification-layer changes (types, icons, navigation, endpoint) should be reviewed as a unified surface, not story-by-story.

## Critical Path

None — booking domain is complete. No critical path items for a next booking epic.

## Significant Discovery Detection

- No architectural assumptions proven wrong.
- No mandatory epic definition changes required.
- Listener naming inconsistency detected and **corrected during this session**.
- **Epic update required:** No.

## Readiness Assessment

- Testing & Quality: Strong automated evidence (1704 tests, 0 failures). b6-1 had test-DB environment constraint during implementation — tests syntactically validated.
- Deployment: Pending (feature branch, project-level deployment decision).
- Stakeholder Acceptance: Pending formal sign-off after deployment.
- Technical Health: Clean. Listener pattern well-established, no workarounds. Reconciliation command follows existing command pattern exactly.
- Unresolved Blockers: None.

**Assessment:** Booking Epic 6 is complete from a story and implementation perspective. Deployment and formal stakeholder acceptance remain as post-deploy operational items.

## Commitments & Next Steps

1. Deploy Booking Epic 6 changes (combined with prior pending epics).
2. Execute post-deploy smoke verification for notification delivery and reconciliation command scheduling.
3. Obtain formal stakeholder acceptance for the completed booking domain.
4. Apply notification-spec completeness discipline to Epic 14 stories.
5. Update architecture-booking.md financial domain section (Winston).

---

Bob (Scrum Master): "Retrospective closed. The booking domain is done — 6 epics, 16 stories, a full direct-booking and payment system. Well delivered."
