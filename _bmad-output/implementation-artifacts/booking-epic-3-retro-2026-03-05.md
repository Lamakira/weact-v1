# Retrospective — Booking Epic 3: Booking Completion, Wallet & Payouts

Date: 2026-03-05
Facilitator: Bob (Scrum Master)
Epic: Booking Epic 3
Status: Complete (3/3 stories)

## Team Participants

- Bob (Scrum Master) — Facilitator
- Alice (Product Owner) — Business perspective
- Charlie (Senior Dev) — Technical architecture
- Dana (Frontend Dev) — UI/UX & frontend quality
- Elena (QA/Testing Specialist) — Quality & testing
- Amakira (Project Lead) — Strategic oversight

## Epic Summary & Metrics

**Delivery:**
- Stories completed: 3/3 (100%)
  - b3-1: Double Confirmation & Booking Completion — dual confirmation logic, escrow release, wallet credit, EscrowService, WalletService, auto-completion command
  - b3-2: Face Wallet Dashboard — wallet balance KPI card, wallet transactions list, booking KPI cards (pending/accepted/in_progress/completed), booking activity charts
  - b3-3: Face Withdraws Mobile Money — FedapayService::initiateWithdrawal(), Payout SDK integration, WithdrawWalletRequest, WalletController::withdraw(), FaceDashboard wallet card

**Tests added:**
- Backend: ~37 (b3-1 BookingCompletionTest + WalletServiceTest + EscrowServiceTest) + wallet + withdrawal tests (b3-2/b3-3)
- Frontend: dashboard composable unit tests
- Full suite: zero regressions

**Quality:**
- Code review issues found and fixed: 7 in b3-1 (2 HIGH, 3 MEDIUM, 2 LOW — all resolved)
- Emergency migration created: `make_booking_id_nullable_in_financial_events` (b3-3 bug)
- Post-implementation bugs fixed: 1 (face_id vs users.id in dashboard stats)

## Previous Retro Action Item Follow-Through (Booking Epic 2 → Epic 3)

| Action Item | Status | Evidence |
|-------------|--------|----------|
| Add "Sandbox verification" as explicit subtask in every external API story | ✅ Done | b3-3 included sandbox verification step |
| Document ngrok/tunnel requirement in Dev Notes for payment stories | ✅ Done | b3-3 Dev Notes referenced ngrok requirement |
| Correct Fedapay section in architecture-booking.md (modes + phone number) | ✅ Done | Mode names and phone fixed in architecture-booking.md |
| Update architecture-booking.md with FedaPay\Payout class (not Transaction) | ✅ Done | Added in Epic 3 retrospective (this session) |
| End-to-end webhook test (real Fedapay → ngrok → booking transitions to paid) | ✅ Done | Completed with ngrok — verified working in sandbox |

**All 5 action items from Epic 2 retro resolved. Zero carry-overs.**

## Successes

1. **100% delivery** — 3/3 stories, all code reviewed, all tests passing
2. **Story sequencing was right** — b3-1 built the escrow/wallet foundation, b3-2 surfaced it in the UI, b3-3 let the Face extract it. Each story built directly on the previous one's data model with zero rework
3. **Atomic financial operations — held perfectly** — Every money-touching operation (escrow lock, release, withdrawal debit) was wrapped in `DB::transaction()` with a `FinancialEvent` record. No half-written financial state across all 3 stories
4. **b3-1 code review quality** — 7 real issues caught (2 HIGH severity) before any of it ran in testing. The process worked exactly as designed
5. **`completeBooking()` private method** — Consolidating the dual confirmation logic into one private method was a clean DRY pattern. Could have been spaghetti
6. **`FedaPay\Payout` discovery** — Found and documented the correct SDK class for withdrawals. Future stories (Epic 5/6) can use this immediately without rework
7. **End-to-end webhook test completed** — The ❌ carry-over from Epic 2 was finally closed. Full sandbox validation: real Fedapay payment → ngrok → booking status transitions → FinancialEvent + EscrowTransaction verified

## Challenges

1. **`FedaPay\Payout` vs `FedaPay\Transaction`** (b3-3)
   - Two completely separate SDK classes for payments vs disbursements, with zero documentation distinguishing them
   - `Transaction` silently does nothing for payouts — no error, no feedback
   - Discovered only by reading SDK source code, not Fedapay docs

2. **`FinancialEvent.booking_id` NOT NULL constraint** (b3-3)
   - Withdrawal creates a `FinancialEvent` with no booking context, but the column had a NOT NULL FK constraint inherited from the payment flow
   - Required an emergency migration mid-story: `make_booking_id_nullable_in_financial_events`
   - Debugging path: `Log::debug()` + `DB::enableQueryLog()` to identify the constraint failure

3. **`face_id` vs `users.id` ambiguity** (b3-2 dashboard stats)
   - Booking stats returned 0 because `FaceDashboardController::getAuthenticatedFace()` returns the `Face` model with `faces.id`, but `bookings.face_id` = `users.id`
   - Required line-by-line controller reading to find — not immediately obvious from the model name
   - Fixed: use `$request->user()->id`, not `$face->id`, for all booking queries

4. **FinancialEvent ordering** (b3-3)
   - `FinancialEvent::create()` was initially placed before the Fedapay payout call
   - Since `FinancialEvent` is immutable, creating it before the external call means it would carry the wrong status/ref if the payout call failed or returned different data
   - Fixed: moved `FinancialEvent::create()` to after `FedapayService::initiateWithdrawal()` succeeds

## Key Insights

1. **External SDK classes require explicit verification, not assumption**
   - `FedaPay\Transaction` vs `FedaPay\Payout` shows that "SDK research" must include reading source code, not just docs
   - Add "verify class name and method signature for this operation" as an explicit subtask for any new Fedapay operation

2. **Database constraints inherited from one flow can break another**
   - `NOT NULL` on `booking_id` made sense for payment events but broke withdrawal events
   - Pre-story: review existing migration constraints against the new use case before writing the story spec

3. **Immutable audit records must be created last**
   - `FinancialEvent` is immutable by design — creating it before an external call is always wrong
   - Rule: create `FinancialEvent` only after the external operation completes and you have the final reference/status

4. **`face_id` = `users.id` rule must be explicitly restated in every story touching bookings**
   - This pattern burned us twice (b3-2 dashboard, earlier in Epic 2). It's non-obvious from the column name
   - Architecture-booking.md now documents this explicitly (updated this session)

## Technical Debt

| Item | Priority | Notes |
|------|----------|-------|
| `FinancialEvent` has no `user_id` column — withdrawal events carry no user reference without booking context | Medium | For future reconciliation: consider adding nullable `user_id` FK to `financial_events` |
| Wallet routes are in `bookings.php` not a dedicated `wallet.php` file | Low | Functional but inconsistent with architecture plan. Refactor when Epic 5/6 adds more wallet routes |
| b3-3 story file still shows `status: review` (not `done`) | Low | Update story file status before Epic 4 kickoff |

## Action Items

### Architecture Documentation

1. **`architecture-booking.md` updated (this session):**
   - `FedaPay\Payout` class reference with code example
   - `face_id` vs `users.id` disambiguation with code examples
   - `FinancialEvent.booking_id` nullable constraint documented
   - End-to-end webhook test setup (ngrok steps + sandbox phone numbers)
   Owner: Amakira — ✅ Done

### Process Improvements

2. **Add "verify DB constraints for nullable FK" as story pre-check for any new FinancialEvent operation**
   Owner: SM Agent (story creation)
   When: b5-1, b6-3

3. **Add "FinancialEvent must be created AFTER external call" to Enforcement Guidelines in architecture-booking.md**
   Owner: SM Agent (or Amakira)
   When: Before Epic 4 kickoff

4. **Unit test for withdrawal FinancialEvent DB constraint path** (Elena)
   Owner: Elena
   When: Before Epic 4 kickoff

### Epic 4 Preparation

5. **Spike: Laravel Reverb vs long-polling for booking chat**
   Owner: Charlie
   When: Epic 4, before Story 1 creation
   Success criteria: Recommendation documented with pros/cons

## Next Epic Preview — Booking Epic 4: Booking Chat

**Stories:** b4-1 (Backend + Real-Time Infrastructure), b4-2 (Frontend Real-Time UI)

**Key architectural question for spike:** Laravel Reverb (WebSocket) vs long-polling
- Reverb: already in dependencies, real-time, requires supervisor process + Nginx config
- Long-polling: simpler infra, higher latency, easier to test

**Dependencies ready from Epic 3:**
- `Booking` model with status transitions ✅
- `BookingService` state machine ✅
- Auth middleware pattern ✅

**New in Epic 4:**
- `booking_messages` table migration
- `BookingMessage` model
- `BookingMessageController`
- Real-time broadcast event (if Reverb chosen)
- `BookingChat.vue` component
- `useBookingChat` composable

**Safe to run in parallel:**
- Epic 14 continuation (email/profile features) — zero booking dependency
- Booking Epic 5 (Cancellation & Refunds) — independent of chat

## Readiness Assessment

| Area | Status |
|------|--------|
| Testing & Quality | ✅ Unit + integration tests pass, end-to-end webhook verified in sandbox |
| Technical Debt | ✅ Low — 3 minor items, none blocking |
| Architecture Documentation | ✅ Updated this session (Payout class, face_id, FinancialEvent, webhook setup) |
| Action Items | ✅ Clearly owned, no unresolved blockers |
| Epic 4 Readiness | ⚠️ Needs Reverb vs polling spike before story creation |

**Verdict:** Booking Epic 3 complete. One prerequisite before Epic 4 story creation: the Reverb vs polling spike (Charlie). Epic 4 can be kicked off immediately after.

## Next Steps

1. Charlie runs Reverb vs long-polling spike
2. SM creates b4-1 story (after spike decision)
3. Optional: Elena adds withdrawal FinancialEvent constraint test
4. Begin Epic 4 implementation
