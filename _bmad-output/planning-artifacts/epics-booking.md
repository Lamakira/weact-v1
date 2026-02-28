---
stepsCompleted: [1, 2, 3, 4]
status: 'complete'
completedAt: '2026-02-28'
totalEpics: 6
totalStories: 16
frCoverage: '40/40'
inputDocuments:
  - path: "_bmad-output/planning-artifacts/prd.md"
    type: "prd"
    loaded: true
  - path: "_bmad-output/planning-artifacts/architecture-booking.md"
    type: "architecture"
    loaded: true
  - path: "_bmad-output/planning-artifacts/ux-design-specification.md"
    type: "ux-design"
    loaded: true
project_name: 'WEACT - Direct Booking & Payment'
user_name: 'Lamakira'
date: '2026-02-28'
---

# WEACT - Direct Booking & Payment - Epic Breakdown

## Overview

This document provides the complete epic and story breakdown for WEACT - Direct Booking & Payment, decomposing the requirements from the PRD, UX Design, and Architecture into implementable stories.

## Requirements Inventory

### Functional Requirements

FR1: Producer can browse and filter available Faces (category, location, availability)
FR2: Producer can view Face's complete public profile (photos, videos, bio, tariffs, ratings)
FR3: Producer can initiate a booking request from Face's profile (date, duration, content type)
FR4: System auto-calculates total booking amount (Face tariff based on hourly/daily rate + Producer commission 15%)
FR5: Face can receive and view incoming booking requests with full details (date, duration, type, amount, Producer profile)
FR6: Face can accept or refuse a booking request
FR7: Face must provide mandatory reason when refusing a booking request after payment
FR8: Producer can view the list of their bookings with statuses (pending, accepted, paid, completed, cancelled)
FR9: Face can view the list of their bookings with statuses (pending, accepted, paid, completed, cancelled)
FR10: System enforces minimum booking duration of 4 hours
FR11: System prevents booking a Face whose availability status is "Indisponible"
FR12: Producer can pay for an accepted booking via Mobile Money (MTN MoMo, Moov Money, Celtiis) through Fedapay
FR13: System blocks payment amount in escrow until booking completion is confirmed
FR14: System calculates and applies commissions automatically (15% deducted from Face + 15% charged to Producer)
FR15: Both parties can confirm booking completion (double confirmation)
FR16: System auto-completes a booking after 72 hours if Producer has not confirmed
FR17: System releases escrow funds to Face wallet within 24 hours after mutual confirmation
FR18: Each financial operation is idempotent (no duplicate transactions on retry)
FR19: System maintains complete, non-modifiable audit trail of all financial transactions
FR20: Face can view their wallet balance
FR21: Face can withdraw funds from wallet to Mobile Money account
FR22: Face can view wallet transaction history (payments received, withdrawals)
FR23: Chat between Producer and Face is unlocked only after successful payment
FR24: Producer and Face can exchange messages in real-time within booking chat
FR25: Each booking has its own dedicated chat conversation
FR26: Producer can rate a Face after booking completion (star rating)
FR27: Face can rate a Producer after booking completion (star rating)
FR28: System automatically deducts 1 star from Face's average rating when Face cancels a booking
FR29: Face's average rating and ratings count are displayed on their public profile
FR30: Face receives notification when new booking request arrives
FR31: Producer receives notification when Face accepts or refuses booking request
FR32: Producer receives notification when payment is confirmed and chat is unlocked
FR33: Both parties receive notification when other party confirms booking completion
FR34: Face receives notification when payment is credited to wallet
FR35: Face receives notification when booking is cancelled by Producer
FR36: Producer can cancel a booking before Face acceptance (full refund)
FR37: Producer can cancel a booking after Face acceptance (15% retained by WEACT, remainder refunded)
FR38: System processes refunds to Producer's Mobile Money account
FR39: System does not refund Producer in case of Producer no-show
FR40: Face is not penalized (no rating impact) when a Producer cancels

### NonFunctional Requirements

NFR-P1: First Contentful Paint < 2s on mobile 4G Benin
NFR-P2: Time to Interactive < 3s on mobile 4G Benin
NFR-P3: API response time < 300ms (excluding Fedapay calls)
NFR-P4: Chat message delivery < 500ms via Reverb WebSocket
NFR-P5: Fedapay payment callback < 10s
NFR-P6: 100+ concurrent WebSocket connections supported
NFR-S1: All communications via HTTPS (TLS 1.2+)
NFR-S2: Laravel Sanctum token authentication with expiration
NFR-S3: CSRF protection on all mutating requests
NFR-S4: Rate limiting on sensitive endpoints (payment: 5/min, login: 5/min, booking: 10/min)
NFR-S5: No banking data or Mobile Money credentials stored on WEACT
NFR-S6: Financial amounts and escrow statuses only modifiable by system (never by user input)
NFR-S7: Server-side validation of all calculated amounts (never trust frontend)
NFR-S8: Financial event logging for all critical operations (payment, escrow, withdrawal, refund)
NFR-R1: Zero fund loss — escrow integrity guaranteed at every state transition
NFR-R2: Idempotent financial operations (payment, refund, withdrawal)
NFR-R3: Reliable 72h auto-completion via Laravel Scheduler (hourly)
NFR-R4: Fedapay failure recovery — conserve state, allow retry without duplication
NFR-R5: Wallet reconciliation mechanism (financial_events vs wallet_transactions)
NFR-R6: 99% uptime target (excluding planned maintenance)
NFR-I1: Fedapay integration for Mobile Money payment (MTN MoMo, Moov Money, Celtiis)
NFR-I2: Fedapay webhook handling (payment confirmation/failure callbacks)
NFR-I3: Fedapay integration for wallet withdrawals to Mobile Money
NFR-I4: Laravel Reverb for real-time booking chat (self-hosted WebSocket)
NFR-I5: Graceful fallback if Fedapay or Reverb unavailable (informative error, no data loss)
NFR-SC1: Architecture supports MVP load on single VPS (2 vCPU, 8GB RAM)
NFR-SC2: Lazy loading for all Vue Router booking pages
NFR-SC3: Pagination on all list endpoints (default 15 items)
NFR-SC4: Architecture supports future migration to dedicated server or cloud

### Additional Requirements

**From Architecture:**
- State Machine: ALL booking state transitions through BookingService — never set status directly on model
- Financial Atomicity: ALL money-touching operations wrapped in DB::transaction() with FinancialEvent record
- BookingPricing Value Object: ALL amount calculations through BookingPricing — no hardcoded commission math (15% Face + 15% Producer)
- Webhook Idempotency: Check fedapay_event_id before processing, return 200 fast, dispatch queued job
- Reverb Private Channels: private-booking.{id} for each booking chat, authorized for Producer + Face only
- Scheduled Commands: AutoCompleteBookingsCommand (hourly, 72h timeout), ExpireUnpaidBookingsCommand (hourly, 24h timeout)
- Reconciliation: ReconcileWalletCommand daily — compare financial_events totals with wallet_transactions
- Brownfield Coexistence: Zero regression on existing Mission workflow (82 existing FRs)
- No Starter Template: Extension of existing codebase only
- Implementation Sequence: Migrations → Enums → Models → BookingService → FedapayService → EscrowService → WalletService → Policy → Routes → Reverb → Frontend → Commands → Ratings
- Webhook Endpoint: POST /api/v1/webhooks/fedapay — excluded from Sanctum middleware, signature verification only
- Booking API Routes: RESTful under /api/v1/bookings with action endpoints (/accept, /refuse, /pay, /confirm, /cancel)

**From UX Design:**
- Mobile-first design: 360px width priority, Chrome Android, 4G Benin network conditions
- Touch targets: Minimum 44x44px for all interactive elements
- Bottom navigation: Fixed bottom bar for primary navigation (Accueil, Bookings, Chat, Wallet, Profil)
- USSD push payment model: User stays on WEACT page, confirmation arrives via phone USSD push, polling for status
- BookingTimeline hero component: Visual 7-step vertical timeline making escrow tangible and building trust
- BookingFormSheet: Bottom sheet overlay on Face profile for fast booking creation (3-4 fields)
- PaymentOverlay: Full-screen isolation during Mobile Money payment (provider selection → waiting → confirmation)
- Skeleton screens: All loading states use skeleton loaders (no empty screens on 4G)
- WCAG 2.1 AA compliance: Contrast ratios verified, ARIA attributes on all booking components
- Responsive breakpoints: sm(640px), md(768px), lg(1024px) — Tailwind 4.1
- Booking status colors: pending(amber), accepted(sky), paid(blue), active(teal), completed(emerald), cancelled(red)
- Money flow visualization: Amount → Escrow → Wallet with visual status at each node
- Chat design: Purpose-driven with booking context pinned at top, text-only MVP, presence indicators
- Emotional design: Micro-celebrations on payment confirmed, wallet credited, booking completed

### FR Coverage Map

| FR | Epic | Description |
|----|------|-------------|
| FR1 | Epic 1 | Browse and filter Faces |
| FR2 | Epic 1 | View Face profile |
| FR3 | Epic 1 | Initiate booking from profile |
| FR4 | Epic 1 | Auto-calculate booking amount |
| FR5 | Epic 1 | Face views incoming requests |
| FR6 | Epic 1 | Face accepts/refuses |
| FR7 | Epic 1 | Mandatory reason on refusal after payment |
| FR8 | Epic 1 | Producer booking list |
| FR9 | Epic 1 | Face booking list |
| FR10 | Epic 1 | Minimum 4h duration |
| FR11 | Epic 1 | Prevent booking unavailable Face |
| FR12 | Epic 2 | Pay via Mobile Money (Fedapay) |
| FR13 | Epic 2 | Escrow locks funds |
| FR14 | Epic 2 | Automatic commission calculation |
| FR18 | Epic 2 | Idempotent financial operations |
| FR19 | Epic 2 | Non-modifiable audit trail |
| FR15 | Epic 3 | Double confirmation |
| FR16 | Epic 3 | Auto-complete after 72h |
| FR17 | Epic 3 | Escrow release to wallet |
| FR20 | Epic 3 | View wallet balance |
| FR21 | Epic 3 | Withdraw to Mobile Money |
| FR22 | Epic 3 | View wallet transaction history |
| FR23 | Epic 4 | Chat unlocked after payment |
| FR24 | Epic 4 | Real-time messaging |
| FR25 | Epic 4 | Dedicated chat per booking |
| FR36 | Epic 5 | Cancel before acceptance (full refund) |
| FR37 | Epic 5 | Cancel after acceptance (15% retained) |
| FR38 | Epic 5 | Refund to Mobile Money |
| FR39 | Epic 5 | No refund on no-show |
| FR40 | Epic 5 | Face not penalized on Producer cancel |
| FR26 | Epic 6 | Producer rates Face |
| FR27 | Epic 6 | Face rates Producer |
| FR28 | Epic 6 | -1 star penalty on Face cancellation |
| FR29 | Epic 6 | Rating on public profile |
| FR30 | Epic 6 | Notification: new booking request |
| FR31 | Epic 6 | Notification: accept/refuse |
| FR32 | Epic 6 | Notification: payment confirmed |
| FR33 | Epic 6 | Notification: completion confirmed |
| FR34 | Epic 6 | Notification: wallet credited |
| FR35 | Epic 6 | Notification: booking cancelled |

**Coverage: 40/40 FRs mapped**

## Epic List

### Epic 1: Booking Request & Management
Producer can create a booking from a Face's profile, Face can view and accept/refuse the request, both parties can view their booking lists and details with timeline visualization.
**FRs covered:** FR1, FR2, FR3, FR4, FR5, FR6, FR7, FR8, FR9, FR10, FR11
**NFRs addressed:** NFR-P1, NFR-P2, NFR-P3, NFR-S2, NFR-S3, NFR-S4, NFR-S7, NFR-SC2, NFR-SC3
**Dependency:** None (standalone foundation)

### Epic 2: Mobile Money Payment & Escrow
Producer can pay for an accepted booking via Mobile Money (MTN, Moov, Celtiis). System locks funds in escrow with full audit trail and idempotent operations.
**FRs covered:** FR12, FR13, FR14, FR18, FR19
**NFRs addressed:** NFR-S5, NFR-S6, NFR-S7, NFR-S8, NFR-R1, NFR-R2, NFR-R4, NFR-I1, NFR-I2
**Dependency:** Epic 1

### Epic 3: Booking Completion, Wallet & Payouts
Both parties confirm booking completion (or auto-complete at 72h). Escrow releases to Face's wallet. Face can view balance, transaction history, and withdraw to Mobile Money.
**FRs covered:** FR15, FR16, FR17, FR20, FR21, FR22
**NFRs addressed:** NFR-R1, NFR-R3, NFR-R5, NFR-I3
**Dependency:** Epic 2 | Parallelizable with Epic 4, Epic 5

### Epic 4: Booking Chat (Real-Time Messaging)
After payment confirmation, a dedicated chat opens between Producer and Face with booking context pinned at the top. Messages are delivered in real-time via Laravel Reverb.
**FRs covered:** FR23, FR24, FR25
**NFRs addressed:** NFR-P4, NFR-P6, NFR-I4, NFR-I5
**Dependency:** Epic 2 | Parallelizable with Epic 3, Epic 5

### Epic 5: Cancellation & Refunds
Producer can cancel before or after acceptance with transparent financial implications. System processes refunds and enforces no-show policy. Unpaid bookings auto-expire after 24h.
**FRs covered:** FR36, FR37, FR38, FR39, FR40
**NFRs addressed:** NFR-R1, NFR-R2, NFR-S8
**Dependency:** Epic 2 | Parallelizable with Epic 3, Epic 4

### Epic 6: Rating, Notifications & Lifecycle Polish
Both parties can rate each other after completion. System sends notifications at every lifecycle event. Face rating penalty on Face cancellation. Daily reconciliation for financial integrity.
**FRs covered:** FR26, FR27, FR28, FR29, FR30, FR31, FR32, FR33, FR34, FR35
**NFRs addressed:** NFR-R5, NFR-R6
**Dependency:** Epic 3

## Parallelization Strategy

```
Epic 1 → Epic 2 → ┬─ Epic 3 → Epic 6
                   ├─ Epic 4
                   └─ Epic 5
```

- **Sequential phase:** Epic 1 → Epic 2 (1 agent)
- **Parallel phase:** Epic 3 + Epic 4 + Epic 5 (3 agents concurrently)
- **Final phase:** Epic 6 (1 agent, after Epic 3 completes)

---

## Epic 1: Booking Request & Management

Producer can create a booking from a Face's profile, Face can view and accept/refuse the request, both parties can view their booking lists and details with timeline visualization.

### Story 1.1: Producer Creates a Booking Request

As a Producer,
I want to submit a booking request from a Face's profile with date, duration, and content type,
So that I can reserve a Face for my project with transparent pricing.

**Acceptance Criteria:**

**Given** a Producer is authenticated and viewing a Face's public profile
**When** they tap the "Booker" button
**Then** a bottom sheet (BookingFormSheet) opens with the Face's name, photo, and tariff displayed
**And** the form includes fields: date, duration (minimum 4h), content type, optional message

**Given** the Producer fills in date and duration
**When** the values change
**Then** the system auto-calculates and displays in real-time: Face tariff, Producer commission (15%), and total amount via BookingPricing

**Given** the Producer submits a valid booking form
**When** the API receives the request
**Then** a new booking is created with status `pending`, the server recalculates the amount via BookingPricing (never trusts frontend), and returns the BookingResource response
**And** the Producer sees a confirmation toast and is redirected to the booking detail page

**Given** the Face's availability status is "Indisponible"
**When** the Producer views the Face's profile
**Then** the "Booker" button is disabled with a tooltip "Cette Face n'est pas disponible"

**Given** the Producer selects a duration less than 4 hours
**When** they attempt to submit
**Then** validation prevents submission with message "La durée minimale est de 4 heures"

**Given** the Producer is not authenticated
**When** they tap "Booker"
**Then** they are redirected to login with automatic return to the Face's profile after authentication

**Technical scope:**
- Backend: `bookings` migration, `BookingStatus` enum, `BookingPricing` VO, `Booking` model, `BookingService::create()`, `BookingPolicy::create`, `CreateBookingRequest`, `BookingController::store`, `BookingResource`, route `POST /api/v1/bookings`
- Frontend: `BookingFormSheet`, "Booker" CTA on Face profile, `bookingApi.create()`, booking types, Zod validation schema
- FRs: FR1, FR2, FR3, FR4, FR10, FR11

### Story 1.2: Face Views and Responds to a Booking Request

As a Face,
I want to view incoming booking details and accept or refuse the request,
So that I can decide which opportunities to take based on the Producer's profile and booking details.

**Acceptance Criteria:**

**Given** a Face has a pending booking request
**When** they open the booking detail page
**Then** they see the BookingTimeline with the current step highlighted, plus: Producer name/photo/rating, booking date, duration, content type, guaranteed amount (tariff - 15% commission), and Producer's optional message

**Given** a Face views a pending booking
**When** they tap "Accepter"
**Then** the booking status transitions to `accepted` via BookingService, the timeline updates, and the Producer is notified

**Given** a Face views a pending booking
**When** they tap "Refuser" and the booking has NOT been paid
**Then** the booking status transitions to `refused` and the Producer is notified

**Given** a Face views a booking that HAS been paid
**When** they tap "Refuser"
**Then** a mandatory reason field appears, and refusal is only processed after the Face provides a reason

**Given** an unauthorized user attempts to accept/refuse a booking
**When** the API receives the request
**Then** a 403 Forbidden response is returned (BookingPolicy enforced)

**Technical scope:**
- Backend: `BookingController::show/accept/refuse`, `AcceptBookingRequest`, `RefuseBookingRequest`, `BookingService::accept()/refuse()`, state transitions
- Frontend: booking detail page (`pages/bookings/[id].vue`), `BookingTimeline` component, `BookingStatusBadge`, `useBookingActions` composable, `useBookingDetail` composable
- FRs: FR5, FR6, FR7

### Story 1.3: My Bookings List with Status Filtering

As a Producer or Face,
I want to see all my bookings with their current status and key details,
So that I can track my booking activity at a glance.

**Acceptance Criteria:**

**Given** a Producer is authenticated
**When** they navigate to "Mes Bookings"
**Then** they see a list of booking cards showing: Face photo/name, date, status badge, amount, sorted by most recent activity

**Given** a Face is authenticated
**When** they navigate to "Mes Bookings"
**Then** they see a list of booking cards showing: Producer photo/name, date, status badge, guaranteed amount, sorted by most recent activity

**Given** a user views their bookings list
**When** they filter by status (all, pending, active, completed, cancelled)
**Then** the list updates to show only bookings matching the selected status

**Given** a user has more than 15 bookings
**When** they scroll to the bottom
**Then** the next page loads automatically (pagination, 15 items per page)

**Given** a user has no bookings
**When** they view the bookings list
**Then** an empty state is displayed: "Pas encore de booking" with CTA to browse Faces (Producer) or complete profile (Face)

**Given** the bookings are loading
**When** the API is fetching data
**Then** skeleton loaders are displayed (no empty screen)

**Technical scope:**
- Backend: `BookingController::index` with role-based filtering, status query param, pagination (15/page)
- Frontend: `BookingCard` component, bookings list page (`pages/bookings/index.vue`), `useBookingList` composable, `bookingStore`, skeleton loaders, empty states
- FRs: FR8, FR9

---

## Epic 2: Mobile Money Payment & Escrow

Producer can pay for an accepted booking via Mobile Money (MTN, Moov, Celtiis). System locks funds in escrow with full audit trail and idempotent operations.

### Story 2.1: Producer Pays via Mobile Money (Fedapay)

As a Producer,
I want to pay for an accepted booking via Mobile Money (MTN MoMo, Moov Money, or Celtiis),
So that the booking is confirmed and funds are secured.

**Acceptance Criteria:**

**Given** a booking is in `accepted` status
**When** the Producer taps "Payer maintenant"
**Then** a full-screen PaymentOverlay opens showing: total amount, commission breakdown, and Mobile Money provider selection (MTN/Moov/Celtiis as visual radio buttons with logos)

**Given** the Producer selects a provider and taps "Payer"
**When** the payment is initiated
**Then** the system calls FedapayService to create a payment transaction with an idempotency_key, and the overlay transitions to a waiting state: "Confirmez sur votre téléphone..." with a spinner and timer

**Given** the Fedapay USSD push arrives on the Producer's phone
**When** the Producer confirms via PIN
**Then** Fedapay sends a webhook callback to `POST /api/v1/webhooks/fedapay`

**Given** the payment succeeds
**When** the webhook is processed
**Then** the booking transitions to `paid` status via BookingService, escrow is locked via EscrowService, a FinancialEvent record is created, and the Producer sees a checkmark animation + "Paiement confirmé !"

**Given** the payment fails
**When** the webhook returns a failure
**Then** the Producer sees an error message with a "Réessayer" button (same overlay, no navigation away)

**Given** a duplicate webhook arrives (same fedapay_event_id)
**When** the system processes it
**Then** it returns 200 without re-processing (idempotency check)

**Technical scope:**
- Backend: `escrow_transactions` migration, `financial_events` migration, `fedapay_webhook_events` migration, `EscrowTransaction` model, `FinancialEvent` model, `FedapayWebhookEvent` model, `FinancialEventType` enum, `FedapayService::initiatePayment()`, `EscrowService::lock()`, `BookingService::markAsPaid()`, `PayBookingRequest`, `BookingController::pay`, `FedapayWebhookController`, `HandleFedapayWebhook` listener (queued job), route `POST /api/v1/bookings/{id}/pay`, route `POST /api/v1/webhooks/fedapay`
- Frontend: `PaymentOverlay` (3-step: provider → waiting → confirmation), `useBookingPayment` composable, payment status polling
- FRs: FR12, FR13, FR14

### Story 2.2: Financial Audit Trail & Idempotency

As a system administrator,
I want every financial operation to be logged in a non-modifiable audit trail with idempotency protection,
So that all money movements are traceable and no duplicate transactions can occur.

**Acceptance Criteria:**

**Given** any financial operation occurs (payment, escrow lock, escrow release, refund, withdrawal)
**When** the operation executes
**Then** a `FinancialEvent` record is created within the same DB transaction, containing: type, booking_id, amount, fedapay_ref, idempotency_key, status, and metadata

**Given** a financial operation is attempted with an existing idempotency_key
**When** the system checks the key
**Then** the operation is skipped and the existing result is returned (no duplicate processing)

**Given** a DB transaction fails mid-operation
**When** the rollback occurs
**Then** both the financial change AND the FinancialEvent are rolled back atomically (zero partial state)

**Given** a Fedapay webhook arrives
**When** the controller processes it
**Then** it checks `fedapay_event_id` in `fedapay_webhook_events` table, returns 200 immediately, and dispatches processing to a queued job

**Given** the audit trail exists
**When** queried
**Then** all financial events are immutable (no update/delete operations allowed on the model)

**Technical scope:**
- Backend: Enforce `DB::transaction()` wrapper pattern in all services (EscrowService, WalletService, FedapayService), idempotency_key generation (UUID), `FinancialEvent` model with no update/delete, webhook event deduplication
- Tests: Transaction atomicity test, idempotency test, webhook replay test
- FRs: FR18, FR19

### Story 2.3: Commission Calculation & Pricing Transparency

As a Producer,
I want to see an exact breakdown of all fees before and during payment,
So that I understand exactly what I'm paying and where the money goes.

**Acceptance Criteria:**

**Given** a booking is created or viewed
**When** the pricing is displayed
**Then** it shows: Face tariff (base amount), Producer commission (15%), total Producer pays, Face receives (tariff - 15%), and platform revenue (both commissions)

**Given** any component needs to calculate pricing
**When** it computes amounts
**Then** it uses `BookingPricing` Value Object exclusively — no hardcoded commission math anywhere

**Given** the frontend displays a pricing preview
**When** the booking is submitted or paid
**Then** the server recalculates all amounts via `BookingPricing` and rejects requests where frontend amount doesn't match server calculation

**Given** a Face has a tariff of 50,000 XOF/day and the booking is 1 day
**When** pricing is calculated
**Then** Producer pays 57,500 XOF (50,000 + 7,500), Face receives 42,500 XOF (50,000 - 7,500), platform earns 15,000 XOF

**Technical scope:**
- Backend: `BookingPricing` VO unit tests, server-side recalculation enforcement in `CreateBookingRequest` and `PayBookingRequest`
- Frontend: `BookingPricingPreview` component displaying commission breakdown
- FRs: FR14

---

## Epic 3: Booking Completion, Wallet & Payouts

Both parties confirm booking completion (or auto-complete at 72h). Escrow releases to Face's wallet. Face can view balance, transaction history, and withdraw to Mobile Money.

### Story 3.1: Double Confirmation & Booking Completion

As a Producer or Face,
I want to confirm that the booking was completed successfully,
So that the escrow can be released and the Face gets paid.

**Acceptance Criteria:**

**Given** a booking is in `in_progress` status (paid + mission day passed)
**When** the Producer taps "Confirmer la réalisation"
**Then** the booking transitions to `confirmed_by_producer` and the Face is notified: "Le producteur a confirmé. À votre tour !"

**Given** a booking is `confirmed_by_producer`
**When** the Face taps "Confirmer aussi"
**Then** the booking transitions to `completed`, escrow is released via EscrowService, and the Face's wallet is credited via WalletService — all within a single DB transaction with FinancialEvent

**Given** a booking is in `in_progress` status
**When** 72 hours pass without Producer confirmation
**Then** the `AutoCompleteBookingsCommand` (hourly cron) automatically transitions the booking to `completed`, releases escrow, and credits the Face's wallet

**Given** both confirmations are received
**When** the booking is marked completed
**Then** the BookingTimeline updates to show all steps completed with a celebration micro-animation

**Technical scope:**
- Backend: `BookingController::confirm`, `ConfirmBookingRequest`, `BookingService::confirm()` with double-confirmation logic, `EscrowService::release()`, `WalletService::credit()`, `AutoCompleteBookingsCommand` (scheduled hourly), `wallet_transactions` migration, `add_balance_to_users` migration
- Frontend: Confirm button on booking detail, `useBookingActions::confirm()`, timeline completion animation
- FRs: FR15, FR16, FR17

### Story 3.2: Face Wallet Dashboard

As a Face,
I want to see my wallet balance and transaction history,
So that I can track my earnings from bookings.

**Acceptance Criteria:**

**Given** a Face is authenticated
**When** they navigate to the Wallet page
**Then** they see a WalletDashboard with: current balance displayed prominently (large, bold), a list of recent transactions (credits from bookings, withdrawals), and pending amounts (escrow not yet released)

**Given** the Face has completed bookings
**When** they view the transaction list
**Then** each transaction shows: type icon (credit/debit), description ("Booking avec Kofi — Publicité"), amount (green for credit, red for debit), date, and reference

**Given** the Face has more than 15 transactions
**When** they scroll
**Then** pagination loads the next page

**Given** the Face has no transactions
**When** they view the wallet
**Then** balance shows 0 XOF with empty state: "Vos revenus apparaîtront ici après votre premier booking"

**Given** a Producer is authenticated
**When** they try to access /wallet
**Then** they are redirected (wallet is Face-only)

**Technical scope:**
- Backend: `WalletController::index` (balance + recent transactions), `WalletResource`, `WalletTransactionResource`, `WalletTransaction` model, Face-only middleware
- Frontend: wallet page (`pages/wallet/index.vue`), `WalletBalance` component, `WalletTransactionList` component, `useWallet` composable, `walletStore`, `walletApi`
- FRs: FR20, FR22

### Story 3.3: Face Withdraws to Mobile Money

As a Face,
I want to withdraw my wallet balance to my Mobile Money account,
So that I can access my earnings as real money.

**Acceptance Criteria:**

**Given** a Face has a positive wallet balance
**When** they tap "Retirer"
**Then** a withdrawal form appears with: amount input (max = current balance), Mobile Money provider selection (MTN/Moov/Celtiis), and phone number field

**Given** the Face submits a valid withdrawal request
**When** the API processes it
**Then** WalletService debits the balance atomically, FedapayService initiates the transfer, a FinancialEvent is recorded, and a WalletTransaction (debit) is created — all in one DB transaction

**Given** the withdrawal is initiated
**When** Fedapay processes the transfer
**Then** the Face sees the withdrawal status in their transaction list: "En cours" → "Terminé"

**Given** the Face enters an amount greater than their balance
**When** they submit
**Then** validation prevents submission: "Solde insuffisant"

**Given** the Face enters 0 or a negative amount
**When** they submit
**Then** validation prevents submission

**Given** the withdrawal fails (Fedapay error)
**When** the error is returned
**Then** the wallet balance is restored (transaction rolled back) and the Face sees: "Retrait échoué. Veuillez réessayer."

**Technical scope:**
- Backend: `WalletController::withdraw`, `WithdrawWalletRequest`, `WalletService::debit()`, `FedapayService::initiateWithdrawal()`, idempotency_key for withdrawal
- Frontend: `WalletWithdrawForm` component, provider selection, amount validation
- FRs: FR21

---

## Epic 4: Booking Chat (Real-Time Messaging)

After payment confirmation, a dedicated chat opens between Producer and Face with booking context pinned at the top. Messages are delivered in real-time via Laravel Reverb.

### Story 4.1: Booking Chat Backend & Real-Time Infrastructure

As a Producer or Face,
I want to exchange real-time messages within my booking,
So that we can coordinate logistics for the mission.

**Acceptance Criteria:**

**Given** a booking is in `paid` or `in_progress` status
**When** the Producer or Face accesses the chat
**Then** they can see all previous messages and send new ones in real-time

**Given** a user sends a message
**When** the message is saved
**Then** it is broadcast via Laravel Reverb on `private-booking.{bookingId}` channel and appears on the other party's screen within 500ms

**Given** a booking is NOT in `paid` or `in_progress` status
**When** a user attempts to access the chat
**Then** the chat is locked with a message explaining why (e.g., "Le chat sera débloqué après le paiement")

**Given** a user who is NOT the Producer or Face of the booking
**When** they attempt to access the chat
**Then** authorization is denied (BookingPolicy)

**Given** the chat has many messages
**When** the user opens it
**Then** messages are paginated (older messages load on scroll-up) and auto-scroll to the latest message

**Technical scope:**
- Backend: `booking_messages` migration, `BookingMessage` model, `BookingMessageController::index/store`, `SendBookingMessageRequest`, `BookingMessageResource`, `BookingMessageSent` event (implements `ShouldBroadcast`), `private-booking.{id}` channel authorization in `channels.php`, Laravel Reverb configuration
- Routes: `GET /api/v1/bookings/{id}/messages`, `POST /api/v1/bookings/{id}/messages`
- FRs: FR23, FR24, FR25

### Story 4.2: Booking Chat Frontend with Real-Time UI

As a Producer or Face,
I want a chat interface with booking context pinned at the top,
So that I can coordinate without losing sight of the booking details.

**Acceptance Criteria:**

**Given** a user opens the booking chat
**When** the page loads
**Then** a ChatHeader is pinned at the top showing: other party's photo/name, booking date, duration, and status badge

**Given** the chat is active
**When** messages are exchanged
**Then** each message displays as a ChatBubble with: sender alignment (right = mine, left = theirs), message text, timestamp, and sent/read status indicator

**Given** a new message arrives via WebSocket
**When** the chat is open
**Then** the message appears instantly with auto-scroll to bottom

**Given** Reverb is unavailable
**When** the user opens the chat
**Then** a banner displays "Chat temporairement indisponible" with a manual refresh button (graceful fallback)

**Given** the user is on mobile (< 640px)
**When** they open the chat
**Then** it displays full-screen with the input fixed at the bottom in the thumb zone

**Technical scope:**
- Frontend: `BookingChat` component, `ChatBubble` component, `ChatHeader` component, `useBookingChat` composable (Echo connection, send/receive), `bookingChatApi`, chat page integration in booking detail, responsive layout
- FRs: FR23, FR24, FR25 (frontend implementation)

---

## Epic 5: Cancellation & Refunds

Producer can cancel before or after acceptance with transparent financial implications. System processes refunds and enforces no-show policy. Unpaid bookings auto-expire after 24h.

### Story 5.1: Producer Cancels a Booking

As a Producer,
I want to cancel a booking with a clear view of financial consequences,
So that I can exit a booking I no longer need while understanding the costs.

**Acceptance Criteria:**

**Given** a booking is in `pending` status (before Face acceptance)
**When** the Producer taps "Annuler"
**Then** the booking is cancelled immediately with full refund (if payment was made), and the Face is notified

**Given** a booking is in `accepted` or `paid` status (after Face acceptance)
**When** the Producer taps "Annuler"
**Then** a confirmation dialog shows the financial breakdown: amount paid, 15% WEACT fee retained, refund amount
**And** upon confirmation, the system processes the partial refund via FedapayService, creates FinancialEvent records, and transitions to `cancelled_by_producer`

**Given** a booking is cancelled after payment
**When** the refund is processed
**Then** EscrowService releases the refund amount, FedapayService initiates the Mobile Money refund, and a FinancialEvent (type: refund) is recorded atomically

**Given** a Producer no-shows (booking date passes, no cancellation, no confirmation)
**When** the auto-complete runs after 72h
**Then** the booking is completed normally — no refund for Producer

**Given** a booking is cancelled by the Producer
**When** the Face receives the notification
**Then** the Face is NOT penalized — no rating impact, message: "Booking annulé par le producteur. Vous n'êtes pas pénalisé."

**Technical scope:**
- Backend: `BookingController::cancel`, `CancelBookingRequest`, `BookingService::cancel()` with pre/post-acceptance logic, `EscrowService::refund()`, `FedapayService::initiateRefund()`, cancellation FinancialEvents
- Frontend: cancellation dialog with financial breakdown, `useBookingActions::cancel()`
- FRs: FR36, FR37, FR38, FR39, FR40

### Story 5.2: Automatic Expiry of Unpaid Bookings

As the system,
I want to automatically expire bookings that haven't been paid within 24 hours of acceptance,
So that Faces are not blocked waiting indefinitely for payment.

**Acceptance Criteria:**

**Given** a booking is in `accepted` status
**When** 24 hours pass without payment
**Then** the `ExpireUnpaidBookingsCommand` transitions the booking to `cancelled` (expired) and notifies both parties

**Given** the command runs hourly
**When** it finds expired bookings
**Then** each is processed individually — one failure doesn't block others

**Given** a booking detail page shows a pending payment
**When** the timer approaches 24h
**Then** a countdown indicator is visible: "Paiement requis dans Xh"

**Technical scope:**
- Backend: `ExpireUnpaidBookingsCommand` (scheduled hourly), booking expiry state transition in `BookingService`
- Frontend: expiry countdown display on booking detail
- Architecture requirement: Scheduled command via Laravel Scheduler

---

## Epic 6: Rating, Notifications & Lifecycle Polish

Both parties can rate each other after completion. System sends notifications at every lifecycle event. Face rating penalty on Face cancellation. Daily reconciliation for financial integrity.

### Story 6.1: Mutual Rating After Booking Completion

As a Producer or Face,
I want to rate the other party after a completed booking,
So that the community can make informed decisions based on reputation.

**Acceptance Criteria:**

**Given** a booking is in `completed` status
**When** the user views the booking detail
**Then** a rating prompt appears: "Comment s'est passé le booking ?" with 1-5 star selector and optional comment field

**Given** a user submits a rating
**When** the API processes it
**Then** a `BookingRating` is created, the rated party's average rating is recalculated, and the rating appears on their public profile

**Given** a Face cancels a booking (cancelled_by_face)
**When** the cancellation is processed
**Then** the Face's average rating is automatically penalized by -1 star

**Given** a Producer cancels a booking
**When** the cancellation is processed
**Then** no rating penalty is applied to the Face

**Given** a user has already rated for a specific booking
**When** they view the booking
**Then** their rating is displayed (read-only, no modification)

**Given** a booking is NOT completed
**When** a user attempts to submit a rating
**Then** the API returns 403 — ratings only after completion

**Technical scope:**
- Backend: `booking_ratings` migration, `BookingRating` model, `BookingRatingController::store`, `RateBookingRequest`, `BookingRatingResource`, rating average recalculation, -1 star penalty in `BookingService::cancel()` for Face cancellation
- Frontend: `BookingRatingForm` component, star selector, rating display on profile
- FRs: FR26, FR27, FR28, FR29

### Story 6.2: Booking Lifecycle Notifications

As a Producer or Face,
I want to receive notifications at every important step of the booking lifecycle,
So that I can take action promptly and never miss an update.

**Acceptance Criteria:**

**Given** a new booking request is created
**When** the Face has a pending booking
**Then** the Face receives a notification: "[Producer] souhaite vous booker pour [type] le [date]"

**Given** a Face accepts or refuses a booking
**When** the status changes
**Then** the Producer receives a notification: "[Face] a accepté votre booking" or "[Face] a refusé votre booking"

**Given** a payment is confirmed
**When** escrow is locked
**Then** the Producer receives: "Paiement confirmé ! Chat débloqué"

**Given** one party confirms completion
**When** the status changes
**Then** the other party receives: "Le producteur a confirmé. À votre tour !" or "La Face a confirmé. À votre tour !"

**Given** escrow is released to the Face's wallet
**When** the wallet is credited
**Then** the Face receives: "[amount] XOF ajoutés à votre wallet !"

**Given** a Producer cancels a booking
**When** the cancellation is processed
**Then** the Face receives: "Booking annulé par [Producer]. Vous n'êtes pas pénalisé."

**Given** any notification is triggered
**When** the user is on the platform
**Then** a toast notification appears with the message and a link to the relevant booking

**Technical scope:**
- Backend: `BookingReceivedNotification`, `BookingAcceptedNotification`, `BookingPaymentReceivedNotification`, `BookingCompletedNotification`, `BookingCancelledNotification`, `BookingRatingReceivedNotification`, event listeners (`HandleBookingAccepted`, `HandleBookingPaid`, `HandleBookingCompleted`, `HandleBookingCancelled`)
- Frontend: toast notification display via existing notification system, notification content with deep links
- FRs: FR30, FR31, FR32, FR33, FR34, FR35

### Story 6.3: Financial Reconciliation Command

As a system administrator,
I want a daily reconciliation check between wallet balances and financial events,
So that any discrepancy is detected before it becomes a problem.

**Acceptance Criteria:**

**Given** the ReconcileWalletCommand runs daily
**When** it compares total FinancialEvents (credits - debits) per user with their wallet balance
**Then** any discrepancy is logged with full details (user_id, expected_balance, actual_balance, difference)

**Given** a discrepancy is found
**When** the command completes
**Then** it returns a non-zero exit code and logs a warning (future: alert admin)

**Given** all balances match
**When** the command completes
**Then** it logs "Reconciliation OK" with timestamp and user count checked

**Technical scope:**
- Backend: `ReconcileWalletCommand`, scheduled daily via Laravel Scheduler
- Architecture requirement: NFR-R5
