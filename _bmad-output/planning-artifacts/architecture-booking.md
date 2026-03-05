---
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8]
status: 'complete'
completedAt: '2026-02-28'
inputDocuments:
  - path: "_bmad-output/planning-artifacts/prd.md"
    type: "prd"
    loaded: true
  - path: "docs/planning-artifacts/architecture.md"
    type: "architecture"
    loaded: true
    notes: "Existing WEACT architecture - brownfield reference"
  - path: "docs/planning-artifacts/prd.md"
    type: "prd"
    loaded: true
    notes: "Original WEACT MVP PRD - 82 FRs"
  - path: "_bmad-output/project-context.md"
    type: "project-context"
    loaded: true
    notes: "75 AI agent rules"
  - path: "_bmad-output/planning-artifacts/product-brief-WEACT-booking-2026-02-27.md"
    type: "product-brief"
    loaded: true
  - path: "_bmad-output/planning-artifacts/product-brief-WEACT-2026-01-07.md"
    type: "product-brief"
    loaded: true
    notes: "Skipped - pointer to docs/weact-brief.md"
workflowType: 'architecture'
project_name: 'WEACT - Direct Booking & Payment'
user_name: 'Lamakira'
date: '2026-02-27'
---

# Architecture Decision Document

_This document builds collaboratively through step-by-step discovery. Sections are appended as we work through each architectural decision together._

## Project Context Analysis

### Requirements Overview

**Functional Requirements:**
40 FRs across 7 capability areas: Booking Management (11), Payment & Escrow (8), Wallet & Withdrawals (3), Messaging (3), Rating & Reputation (4), Notifications (6), Cancellation & Refunds (5).

Core workflow: Producer initiates booking from Face profile → Face accepts/refuses → Producer pays via Mobile Money (Fedapay) → Escrow holds funds → Chat unlocks → Mission executes → Double confirmation → Escrow releases to Face wallet → Mutual rating.

This is a **parallel workflow** to the existing Mission workflow. Both must coexist independently.

**Non-Functional Requirements:**
- Performance: <2s FCP (4G mobile), <300ms API, <500ms WebSocket chat delivery
- Security: No banking data stored, server-side amount validation, financial event logging
- Reliability: Zero fund loss, idempotent financial ops, 72h auto-completion, reconciliation mechanism
- Integration: Fedapay (Mobile Money payments + withdrawals), Laravel Reverb (WebSocket chat)
- Scalability: VPS (2 vCPU, 8GB RAM), pagination, lazy loading

**Scale & Complexity:**
- Primary domain: Full-stack SPA extension (Vue 3 + Laravel 12 API)
- Complexity level: High (multi-party financial flows, escrow, external payment gateway)
- New architectural components: ~15 new Vue components, ~10 new API resources, ~6 new database tables, 1 state machine, 1 external integration (Fedapay), 1 WebSocket server (Reverb)

### Technical Constraints & Dependencies

| Constraint | Impact |
|------------|--------|
| Brownfield extension | Must inherit ALL existing conventions (naming, structure, API format, auth) |
| Fedapay dependency | Payment reliability depends on external service + Mobile Money operators |
| Single VPS (2 vCPU, 8GB) | Reverb must be lightweight, no separate infra |
| Coexistence with Mission workflow | Zero regression on existing 82 FRs |
| Mobile Money latency | Payment callbacks can take up to 10s — async handling required |
| Escrow integrity | Financial state transitions must be atomic and auditable |

### Cross-Cutting Concerns Identified

1. **Booking State Machine**: Central orchestrator — state transitions trigger payments, chat access, notifications, ratings, and wallet operations
2. **Financial Integrity**: Every money-touching operation (escrow lock, release, refund, withdrawal) must be atomic, idempotent, and auditable
3. **Fedapay Integration**: Webhook handling, retry logic, idempotency keys, error recovery across multiple endpoints (payment, refund, withdrawal)
4. **Real-Time Communication**: Laravel Reverb for booking chat replaces polling pattern from existing chat — architectural upgrade
5. **Notification Orchestration**: 6 distinct notification types triggered by booking lifecycle events
6. **Coexistence**: New booking routes, models, and features must not interfere with existing Mission/Candidature workflow

## Starter Template Evaluation

### Brownfield Assessment

This is an extension of an existing, operational project. The starter template decision was made during the initial WEACT architecture and is documented in `docs/planning-artifacts/architecture.md`.

**Selected Starter:** Custom Fresh Setup (Laravel 12 + Vue 3 + Tailwind CSS 4.1)
**Status:** Already initialized and operational — no new starter evaluation needed.

### Inherited Architectural Foundations

All foundational decisions carry forward unchanged:

| Foundation | Decision | Booking Impact |
|-----------|----------|---------------|
| Language & Runtime | PHP 8.2+ / TypeScript / Node 20+ | Inherited |
| Styling | Tailwind CSS 4.1 (CSS-first, @theme) | Inherited |
| Build Tooling | Vite 6.x | Inherited |
| Testing | PHPUnit + Pest / Vitest + Vue Test Utils | Inherited |
| Code Organization | Monorepo, hybrid feature/page-based | New `features/booking/` domain |
| API Format | REST JSON envelope `{ data, message, status }` | Inherited |
| Auth | Sanctum token + Fortify | Inherited — booking routes use existing middleware |
| State Management | Pinia stores | New `useBookingStore` follows existing pattern |

### New Dependencies for Booking Domain

| Dependency | Purpose | Integration |
|-----------|---------|-------------|
| Laravel Reverb | Self-hosted WebSocket server for booking chat | Backend — `composer require laravel/reverb` |
| Fedapay PHP SDK | Mobile Money payment gateway (MTN, Moov, Celtiis) | Backend — `composer require fedapay/fedapay-php` |
| Laravel Echo + Pusher JS | Frontend WebSocket client for Reverb | Frontend — `npm install laravel-echo pusher-js` |

### Extension Strategy

The Booking & Payment feature integrates as a new **functional domain** within the existing architecture:
- **Backend**: New models, controllers, services, and migrations in the existing Laravel structure
- **Frontend**: New `features/booking/` directory following the hybrid organization pattern
- **No structural changes**: All existing conventions (naming, routing, auth, API format) are inherited

## Core Architectural Decisions

### Decision Priority Analysis

**Critical Decisions (Block Implementation):**
- Booking State Machine (Service + PHP Enum)
- Escrow Virtual Ledger model
- Fedapay integration pattern (Service + Webhook + Idempotency)
- Commission calculation (BookingPricing Value Object)

**Important Decisions (Shape Architecture):**
- Wallet running balance + transaction log
- Laravel Reverb private channels per booking
- Financial audit trail (dedicated table)
- Scheduled auto-completion & expiry

**Deferred Decisions (Post-MVP):**
- Dispute resolution admin interface (V2)
- Redis caching for wallet balance (V2)
- Push notifications / SMS (V2)
- Booking calendar / availability management (V2)

### Data Architecture (Booking Extension)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Booking State Machine | PHP Enum (`BookingStatus`) + `BookingService` transitions | Linear state machine (~6 states), consistent with existing enum pattern (`MissionType`, `MissionStatus`), zero additional dependencies |
| Escrow Model | Virtual Ledger — `escrow_transactions` table | Fedapay collects real money in one account; our DB tracks escrow state per booking (locked/released/refunded). Full local control and audit |
| Wallet Balance | Running `balance` column + `wallet_transactions` log | Atomic update in DB transaction; fast reads; reconciliation via transaction replay |
| Commission Calculation | `BookingPricing` Value Object | Single source of truth for all pricing logic (15% Face + 15% Producer). Used in preview, creation, and payout. Server-side recalculation |
| Financial Audit | Dedicated `financial_events` table | Typed columns (amount, booking_id, fedapay_ref, operation_type, status). Optimized for reconciliation queries |

### Authentication & Security (Booking Extension)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Booking Authorization | `BookingPolicy` | Only Producer can create/pay/cancel; only Face can accept/refuse; both can confirm and rate. Inherits Policies + Gates pattern |
| Webhook Security | Fedapay signature verification | Validate HMAC signature on every webhook callback before processing |
| Financial Idempotency | UUID `idempotency_key` per operation | Stored in `financial_events`; prevents duplicate charges/refunds on network retry or webhook replay |
| Amount Validation | Server-side recalculation | Frontend sends booking details, server recalculates amount via `BookingPricing`. Never trust client-submitted amounts |

### API & Communication Patterns (Booking Extension)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Real-Time Transport | Laravel Reverb (self-hosted WebSocket) | Decided in PRD — no external dependency, fits VPS constraints (2 vCPU, 8GB RAM) |
| Chat Channels | `private-booking.{id}` | One private channel per booking; authorized for Producer + Face only |
| Frontend WS Client | Laravel Echo + Pusher JS | Standard Reverb client stack; Pusher protocol compatibility |
| Webhook Endpoint | `POST /api/v1/webhooks/fedapay` | Single endpoint, event type dispatch, signature verification, idempotent processing |
| Booking API Routes | RESTful under `/api/v1/bookings` | CRUD + action endpoints (`/accept`, `/refuse`, `/pay`, `/confirm`, `/cancel`) |

### Frontend Architecture (Booking Extension)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Booking Store | `useBookingStore` (Pinia) | Follows existing store pattern; manages booking list, current booking, chat state |
| Feature Module | `features/booking/` | Components, composables, services, types — follows hybrid organization |
| Chat Component | Dedicated `BookingChat.vue` with Echo integration | Real-time message display, auto-scroll, typing indicator |
| Payment Flow | Multi-step with status polling | Initiate payment → redirect to Fedapay → poll status until callback confirms |

### Infrastructure & Deployment (Booking Extension)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Reverb Process | Supervisor-managed `php artisan reverb:start` | Long-running process on same VPS, supervised for auto-restart |
| Scheduled Commands | Laravel Scheduler via cron | `AutoCompleteBookings` (hourly), `ExpireUnpaidBookings` (hourly) |
| Fedapay Environment | Sandbox → Production toggle via `.env` | `FEDAPAY_API_KEY`, `FEDAPAY_SECRET`, `FEDAPAY_ENVIRONMENT` |
| WebSocket Port | Reverb on port 8080, proxied via Nginx | Nginx handles SSL termination + WebSocket upgrade (`/ws/`) |

### Decision Impact Analysis

**Implementation Sequence:**
1. Database schema (bookings, escrow_transactions, wallet_transactions, financial_events, booking_messages)
2. BookingStatus enum + BookingPricing value object
3. BookingService with state machine transitions
4. FedapayService + webhook controller
5. BookingPolicy authorization
6. API routes + controllers
7. Laravel Reverb setup + chat channels
8. Frontend booking store + components
9. Scheduled commands (auto-complete, expiry)
10. Rating integration

**Cross-Component Dependencies:**
- State machine drives ALL other components (payment triggers, chat access, notifications)
- FedapayService is required before any payment flow works
- Reverb must be running before chat can function
- BookingPricing is used by both booking creation and escrow release
- Financial events table is written by multiple services (payment, escrow, wallet, refund)

## Implementation Patterns & Consistency Rules (Booking Extension)

### Pattern Categories Defined

**7 booking-specific conflict points** where AI agents could make different implementation choices. These extend the existing 25+ patterns documented in `docs/planning-artifacts/architecture.md`.

### State Machine Transition Pattern

ALL booking state transitions MUST go through `BookingService`. No controller or job should set `status` directly on the model.

```php
// CORRECT: Always through BookingService
$this->bookingService->accept($booking);
// Validates transition is legal, executes atomically, dispatches event

// ANTI-PATTERN: Direct status change
$booking->status = BookingStatus::Accepted; // ❌ Bypasses validation + side effects
$booking->save();
```

**Valid Transitions:**

```
pending → accepted (Face accepts)
pending → refused (Face refuses)
accepted → paid (Producer pays via Fedapay)
paid → in_progress (auto after payment confirmation)
in_progress → confirmed_by_face (Face confirms)
in_progress → confirmed_by_producer (Producer confirms)
confirmed_by_* → completed (both confirmations received, or 72h auto-complete)
pending|accepted → cancelled_by_producer
pending|accepted → cancelled_by_face
paid|in_progress → cancelled (with refund trigger)
```

### Financial Operation Pattern

Every money-touching operation MUST be wrapped in `DB::transaction()` and MUST create a `FinancialEvent` record.

```php
DB::transaction(function () use ($booking, $amount, $idempotencyKey) {
    // 1. Execute the financial change
    $booking->escrow->markAsReleased();

    // 2. Update wallet balance atomically
    $wallet->increment('balance', $netAmount);

    // 3. Log the wallet transaction
    WalletTransaction::create([/* ... */]);

    // 4. Log to financial audit trail
    FinancialEvent::create([
        'type' => FinancialEventType::EscrowRelease,
        'booking_id' => $booking->id,
        'amount' => $amount,
        'idempotency_key' => $idempotencyKey,
    ]);
});
```

### Webhook Processing Pattern

Fedapay webhooks MUST be idempotent, verify signature, and return 200 fast.

```php
// 1. Verify HMAC signature (reject 403 if invalid)
// 2. Check idempotency: firstOrCreate by fedapay_event_id
// 3. If already processed → return 200 'already_processed'
// 4. Dispatch queued job for actual processing
// 5. Return 200 'received' immediately
```

**Rule**: The webhook endpoint (`POST /api/v1/webhooks/fedapay`) is excluded from Sanctum middleware. Processing happens in a queued job, not in the controller.

### Booking Naming Conventions

Extends existing naming patterns with booking-specific prefixes:

| Element | Pattern | Example |
|---------|---------|---------|
| Tables | Domain-specific names | `bookings`, `booking_messages`, `escrow_transactions`, `wallet_transactions`, `financial_events` |
| Enums | `Booking` or domain prefix | `BookingStatus`, `BookingCancellationReason`, `FinancialEventType` |
| Events | `Booking{Action}` | `BookingCreated`, `BookingAccepted`, `BookingPaid`, `EscrowReleased` |
| Notifications | `Booking{Action}Notification` | `BookingAcceptedNotification`, `BookingPaymentReceivedNotification` |
| Services | `{Domain}Service` | `BookingService`, `FedapayService`, `EscrowService`, `WalletService` |
| API Resources | `{Model}Resource` | `BookingResource`, `BookingMessageResource`, `WalletTransactionResource` |
| Form Requests | `{Action}BookingRequest` | `CreateBookingRequest`, `AcceptBookingRequest`, `CancelBookingRequest` |
| Composables | `useBooking{Feature}` | `useBookingChat`, `useBookingPayment`, `useBookingList` |
| Frontend Services | `booking{Domain}Api` | `bookingApi`, `walletApi`, `bookingChatApi` |

### Real-Time Event Pattern (Reverb/Echo)

```php
// Backend: Channel format
new PrivateChannel("booking.{$this->booking->id}");

// Backend: Event naming
public function broadcastAs(): string {
    return 'message.sent'; // dot-separated lowercase
}

// Frontend: Listening pattern
Echo.private(`booking.${bookingId}`)
    .listen('.message.sent', (e) => { /* handle */ })
    .listen('.booking.status_changed', (e) => { /* handle */ });
```

**Rule**: Channel = `booking.{id}`. Event names = dot-separated lowercase. Frontend listens with `.` prefix (Echo convention).

### BookingPricing Usage Pattern

ALL pricing calculations go through `BookingPricing` Value Object. No hardcoded math.

```php
$pricing = new BookingPricing($faceAmount);
$pricing->totalProducerPays;   // face amount + 15% commission
$pricing->faceReceives;         // face amount - 15% commission
$pricing->platformRevenue;      // both commissions combined
```

**Rule**: Used in booking creation (preview), payment initiation, escrow release, and wallet credit. Frontend can display a preview, but the server ALWAYS recalculates via `BookingPricing`.

### Booking API Route Pattern

```
POST   /api/v1/bookings                    # Create booking
GET    /api/v1/bookings                    # List my bookings (filtered by role)
GET    /api/v1/bookings/{id}               # Get booking details
POST   /api/v1/bookings/{id}/accept        # Face accepts
POST   /api/v1/bookings/{id}/refuse        # Face refuses
POST   /api/v1/bookings/{id}/pay           # Producer initiates payment
POST   /api/v1/bookings/{id}/confirm       # Either party confirms completion
POST   /api/v1/bookings/{id}/cancel        # Either party cancels (with reason)
GET    /api/v1/bookings/{id}/messages      # Get chat messages
POST   /api/v1/bookings/{id}/messages      # Send chat message
POST   /api/v1/bookings/{id}/rate          # Submit rating

GET    /api/v1/wallet                      # Get wallet balance + recent transactions
POST   /api/v1/wallet/withdraw             # Initiate withdrawal to Mobile Money

POST   /api/v1/webhooks/fedapay            # Fedapay callback (no auth middleware)
```

**Rule**: Action endpoints use `POST /bookings/{id}/{action}`. Webhook endpoint excluded from Sanctum middleware. All other routes require `auth:sanctum`.

### Enforcement Guidelines (Booking Extension)

**All AI Agents MUST additionally:**
1. Route ALL booking state changes through `BookingService` — never set status directly
2. Wrap ALL financial operations in `DB::transaction()` with a `FinancialEvent` record
3. Use `BookingPricing` for ALL amount calculations — no hardcoded math
4. Process webhooks idempotently — check `fedapay_event_id` before processing
5. Use `private-booking.{id}` channel naming for all real-time events
6. Follow booking naming conventions for all new domain classes

**Anti-Patterns to Avoid:**
- Direct `$booking->status = ...` without service validation
- Financial operation outside `DB::transaction()`
- Webhook processing without idempotency check
- Amount calculation without `BookingPricing`
- Broadcasting on public channels for booking data
- Mixing booking logic into existing Mission controllers/services

---

### Implementation Notes (Discovered during Epics 2 & 3)

> These notes document gotchas and corrections discovered during implementation. They override or clarify the original architecture where discrepancies exist.

#### 1. Fedapay SDK: Payout vs Transaction

For **withdrawals** (Face → Mobile Money), use `FedaPay\Payout`, **not** `FedaPay\Transaction`.

These are two completely separate SDK classes. `FedaPay\Transaction` handles payments (Producer → Fedapay escrow). `FedaPay\Payout` handles disbursements (Fedapay → Mobile Money recipient). Using `Transaction` for withdrawals silently does nothing.

```php
// CORRECT: Withdrawals use FedaPay\Payout
use FedaPay\Payout;

$payout = Payout::create([
    'amount'      => $amount,
    'currency'    => ['iso' => 'XOF'],
    'description' => $description,
    'customer'    => [
        'firstname'    => $user->name,
        'email'        => $user->email,
        'phone_number' => [
            'number'  => $phoneData['number'],
            'country' => $phoneData['country'],
        ],
    ],
]);
$payout->sendNow(); // NOT sendNowWithToken()

// WRONG: Transaction is for payments only
use FedaPay\Transaction; // ❌ Does nothing for withdrawals
```

**Payment modes (up to date):**

| Mode | Environment | Description |
|------|------------|-------------|
| `momo_test` | Sandbox only | Recommended for sandbox testing |
| `mtn` | Production | MTN Mobile Money |
| `moov` | Production | Moov Money |

**Note**: `mtn_open` is not a valid mode — use `mtn`.

#### 2. face_id vs users.id Disambiguation

`bookings.face_id` references **`users.id`**, NOT `faces.id`.

This is a critical gotcha. The `Face` model has its own `faces.id` primary key, but the booking relation stores the `users.id` of the Face user.

```php
// CORRECT: Use the authenticated user's id for booking queries
$userId = $request->user()->id;                    // ✅ users.id
Booking::where('face_id', $userId)->get();

// WRONG: Using the Face model's own id
$face = Face::findOrFail($user->userable_id);
Booking::where('face_id', $face->id)->get();       // ❌ faces.id ≠ users.id
```

This affects every controller method that queries bookings for the authenticated Face, including dashboard stats, booking lists, and chart data. Always derive `face_id` from `$request->user()->id`.

#### 3. FinancialEvent: booking_id is nullable

`financial_events.booking_id` is a **nullable** foreign key. Withdrawal `FinancialEvent` records have no associated booking — they are user-level operations.

```php
// Withdrawal FinancialEvent: booking_id is null
FinancialEvent::create([
    'type'             => FinancialEventType::Withdrawal,
    'booking_id'       => null,   // ✅ explicitly null — no booking context
    'user_id'          => $userId,
    'amount'           => $amount,
    'fedapay_ref'      => $payoutRef,
    'idempotency_key'  => $idempotencyKey,
    'status'           => 'completed',
]);
```

Always create `FinancialEvent` **after** the Fedapay call succeeds — not before. `FinancialEvent` is immutable (no updates), so it must be created with the final confirmed state.

#### 4. End-to-End Webhook Test Setup (ngrok)

Validated in Epic 3. Use this setup to test the full Fedapay sandbox → webhook → booking state transition flow locally.

**Prerequisites:**
- ngrok account + authtoken configured (`ngrok config add-authtoken <token>`)
- Fedapay sandbox webhook configured to point to your ngrok URL
- Laravel app running locally (`php artisan serve`)

**Steps:**

```bash
# 1. Start ngrok tunnel (expose local port 8000)
ngrok http 8000

# 2. Copy the https forwarding URL, e.g.:
#    https://abc123.ngrok-free.app

# 3. In Fedapay sandbox dashboard → Webhooks → set URL to:
#    https://abc123.ngrok-free.app/api/v1/webhooks/fedapay

# 4. Run the payment flow (initiate a booking payment via the app)
# 5. Watch ngrok inspector at http://localhost:4040 to see the webhook hit
# 6. Check booking status transitions in DB:
php artisan tinker
>>> Booking::latest()->first()->status
```

**What to verify:**
- Fedapay sends `transaction.approved` event → webhook receives it → booking transitions `accepted → paid → in_progress`
- `FinancialEvent` record created with correct `fedapay_ref`
- `EscrowTransaction` status changes to `locked`
- `FedapayWebhookEvent` marked as `processed` (idempotency)

**Test phone numbers (Fedapay sandbox):**

| Network | Number | Expected result |
|---------|--------|----------------|
| MTN test | `64000001` | Success |
| Moov test | `96000001` | Success |

---

## Project Structure & Boundaries (Booking Extension)

### New Backend Files

```
backend/app/
├── Enums/
│   ├── BookingStatus.php              # pending, accepted, paid, in_progress, confirmed_*, completed, cancelled_*, refused
│   ├── BookingCancellationReason.php   # schedule_conflict, price_disagreement, other
│   └── FinancialEventType.php         # payment, escrow_lock, escrow_release, refund, withdrawal, commission
│
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── BookingController.php      # CRUD + accept, refuse, pay, confirm, cancel
│   │   ├── BookingMessageController.php # GET/POST messages
│   │   ├── BookingRatingController.php # POST rating
│   │   ├── WalletController.php       # GET balance, POST withdraw
│   │   └── FedapayWebhookController.php # POST webhook (no auth)
│   │
│   └── Requests/
│       ├── CreateBookingRequest.php
│       ├── AcceptBookingRequest.php
│       ├── RefuseBookingRequest.php
│       ├── PayBookingRequest.php
│       ├── ConfirmBookingRequest.php
│       ├── CancelBookingRequest.php
│       ├── SendBookingMessageRequest.php
│       ├── RateBookingRequest.php
│       └── WithdrawWalletRequest.php
│
├── Http/Resources/
│   ├── BookingResource.php
│   ├── BookingMessageResource.php
│   ├── BookingRatingResource.php
│   ├── WalletResource.php
│   └── WalletTransactionResource.php
│
├── Models/
│   ├── Booking.php                    # face_id, producer_id, status, tarif, description, dates
│   ├── BookingMessage.php             # booking_id, sender_id, content, read_at
│   ├── EscrowTransaction.php         # booking_id, amount, status (locked/released/refunded), fedapay_ref
│   ├── WalletTransaction.php         # user_id, type (credit/debit), amount, reference, description
│   ├── FinancialEvent.php            # type, booking_id, amount, fedapay_ref, idempotency_key, status, metadata
│   ├── BookingRating.php             # booking_id, rater_id, rated_id, score, comment
│   └── FedapayWebhookEvent.php       # fedapay_event_id, payload, status (processing/processed/failed)
│
├── Policies/
│   └── BookingPolicy.php             # create, view, accept, refuse, pay, confirm, cancel, rate
│
├── Services/
│   ├── BookingService.php            # State machine transitions + orchestration
│   ├── FedapayService.php            # Payment initiation, refund, withdrawal via SDK
│   ├── EscrowService.php             # Lock, release, refund escrow operations
│   └── WalletService.php             # Credit, debit, balance operations
│
├── ValueObjects/
│   └── BookingPricing.php            # Commission calculations (15% Face + 15% Producer)
│
├── Events/
│   ├── BookingCreated.php
│   ├── BookingAccepted.php
│   ├── BookingRefused.php
│   ├── BookingPaid.php
│   ├── BookingConfirmed.php
│   ├── BookingCompleted.php
│   ├── BookingCancelled.php
│   ├── EscrowLocked.php
│   ├── EscrowReleased.php
│   ├── BookingMessageSent.php        # implements ShouldBroadcast
│   └── FedapayWebhookReceived.php
│
├── Listeners/
│   ├── HandleBookingAccepted.php     # Notify Producer
│   ├── HandleBookingPaid.php         # Lock escrow, unlock chat, notify Face
│   ├── HandleBookingCompleted.php    # Release escrow, credit wallet, notify both
│   ├── HandleBookingCancelled.php    # Trigger refund if paid, notify parties
│   └── HandleFedapayWebhook.php      # Route webhook to appropriate service
│
├── Notifications/
│   ├── BookingReceivedNotification.php
│   ├── BookingAcceptedNotification.php
│   ├── BookingPaymentReceivedNotification.php
│   ├── BookingCompletedNotification.php
│   ├── BookingCancelledNotification.php
│   └── BookingRatingReceivedNotification.php
│
└── Console/Commands/
    ├── AutoCompleteBookingsCommand.php   # 72h auto-complete
    └── ExpireUnpaidBookingsCommand.php   # 24h payment timeout

backend/database/migrations/
├── xxxx_create_bookings_table.php
├── xxxx_create_booking_messages_table.php
├── xxxx_create_escrow_transactions_table.php
├── xxxx_create_wallet_transactions_table.php
├── xxxx_create_financial_events_table.php
├── xxxx_create_booking_ratings_table.php
├── xxxx_create_fedapay_webhook_events_table.php
└── xxxx_add_balance_to_users_table.php   # wallet balance column

backend/routes/
├── api/bookings.php                    # Booking routes (auth:sanctum)
├── api/wallet.php                      # Wallet routes (auth:sanctum)
└── api/webhooks.php                    # Webhook routes (no auth)

backend/routes/channels.php             # Updated: booking.{id} authorization

backend/tests/Feature/
├── Booking/
│   ├── CreateBookingTest.php
│   ├── BookingStateTransitionTest.php
│   ├── BookingPaymentTest.php
│   ├── BookingChatTest.php
│   ├── BookingRatingTest.php
│   └── BookingCancellationTest.php
├── Wallet/
│   ├── WalletBalanceTest.php
│   └── WalletWithdrawalTest.php
└── Webhook/
    └── FedapayWebhookTest.php
```

### New Frontend Files

```
frontend/src/
├── features/booking/
│   ├── components/
│   │   ├── BookingCard.vue              # Booking summary card in lists
│   │   ├── BookingDetail.vue            # Full booking detail view
│   │   ├── BookingForm.vue              # Create booking form with pricing preview
│   │   ├── BookingStatusBadge.vue       # Status badge with color coding
│   │   ├── BookingActionButtons.vue     # Context-aware action buttons
│   │   ├── BookingChat.vue              # Real-time chat with Echo integration
│   │   ├── BookingChatMessage.vue       # Individual chat message bubble
│   │   ├── BookingPricingPreview.vue    # Commission breakdown display
│   │   ├── BookingRatingForm.vue        # Star rating + comment form
│   │   ├── BookingTimeline.vue          # Visual timeline of booking lifecycle
│   │   └── BookingPaymentStatus.vue     # Payment progress indicator
│   │
│   ├── composables/
│   │   ├── useBookingList.ts            # Fetch + filter bookings
│   │   ├── useBookingDetail.ts          # Single booking with real-time updates
│   │   ├── useBookingChat.ts            # Echo connection, send/receive messages
│   │   ├── useBookingPayment.ts         # Payment flow + status polling
│   │   └── useBookingActions.ts         # Accept, refuse, confirm, cancel actions
│   │
│   ├── services/
│   │   ├── bookingApi.ts                # Booking CRUD + actions API calls
│   │   ├── bookingChatApi.ts            # Chat messages API calls
│   │   └── bookingRatingApi.ts          # Rating API calls
│   │
│   └── types.ts                         # Booking, BookingMessage, BookingStatus types
│
├── features/wallet/
│   ├── components/
│   │   ├── WalletBalance.vue            # Balance display card
│   │   ├── WalletTransactionList.vue    # Transaction history
│   │   └── WalletWithdrawForm.vue       # Withdrawal form (Mobile Money)
│   │
│   ├── composables/
│   │   └── useWallet.ts                 # Wallet balance + transactions
│   │
│   ├── services/
│   │   └── walletApi.ts                 # Wallet API calls
│   │
│   └── types.ts                         # Wallet, WalletTransaction types
│
├── pages/
│   ├── bookings/
│   │   ├── index.vue                    # My bookings list (Producer + Face views)
│   │   └── [id].vue                     # Booking detail + chat page
│   └── wallet/
│       └── index.vue                    # Wallet dashboard (Face only)
│
├── stores/
│   ├── bookingStore.ts                  # useBookingStore — list, current, filters
│   └── walletStore.ts                   # useWalletStore — balance, transactions
│
└── types/
    └── booking.ts                       # Shared booking types (re-exported)
```

### Architectural Boundaries

**API Boundaries:**

| Boundary | Routes | Auth | Purpose |
|----------|--------|------|---------|
| Booking Domain | `/api/v1/bookings/*` | `auth:sanctum` | All booking CRUD + actions |
| Wallet Domain | `/api/v1/wallet/*` | `auth:sanctum` + Face role | Balance + withdrawals |
| Webhooks | `/api/v1/webhooks/fedapay` | Signature verification only | Fedapay callbacks |
| WebSocket | `ws://*/ws/` (via Nginx) | Echo auth endpoint | Real-time chat |

**Service Boundaries:**

```
BookingController → BookingService → [EscrowService, FedapayService, WalletService]
                                   → Events → Listeners → Notifications

FedapayWebhookController → FedapayWebhookEvent → HandleFedapayWebhook → [BookingService, EscrowService]
```

**Data Boundaries:**
- `BookingService` owns `bookings` table — sole writer for status changes
- `EscrowService` owns `escrow_transactions` — sole writer for escrow state
- `WalletService` owns `wallet_transactions` + `users.balance` — sole writer for balance
- `FinancialEvent` is written by ALL financial services (shared audit trail)

### Requirements to Structure Mapping

| FR Range | Capability | Backend | Frontend |
|----------|-----------|---------|----------|
| FR1-FR11 | Booking Management | `BookingController`, `BookingService`, `BookingPolicy` | `features/booking/`, `pages/bookings/` |
| FR12-FR19 | Payment & Escrow | `FedapayService`, `EscrowService`, `FedapayWebhookController` | `useBookingPayment`, `BookingPaymentStatus` |
| FR20-FR22 | Wallet & Withdrawals | `WalletController`, `WalletService` | `features/wallet/`, `pages/wallet/` |
| FR23-FR25 | Messaging | `BookingMessageController`, `BookingMessageSent` event | `BookingChat`, `useBookingChat` |
| FR26-FR29 | Rating & Reputation | `BookingRatingController` | `BookingRatingForm` |
| FR30-FR35 | Notifications | `Notifications/Booking*`, `Listeners/Handle*` | Toast notifications via existing system |
| FR36-FR40 | Cancellation & Refunds | `BookingService::cancel()`, `EscrowService::refund()` | `BookingActionButtons` (cancel action) |

### Integration Points

**External:**
- **Fedapay API** → `FedapayService` (outbound: payment, refund, withdrawal)
- **Fedapay Webhooks** → `FedapayWebhookController` (inbound: payment status callbacks)

**Internal (existing → booking):**
- **Face Profile** → Booking creation link on Face profile page
- **Auth System** → Sanctum middleware on all booking routes
- **Notification System** → Existing `vue-toastification` for frontend toasts

**Internal (booking → existing):**
- **User Model** → `balance` column added for wallet
- **Rating System** → Extends existing rating display on Face profiles

## Architecture Validation Results

### Coherence Validation

**Decision Compatibility:** All 8 new architectural decisions are compatible with each other and with the 15 inherited decisions from the existing architecture. No contradictions found.

**Pattern Consistency:** Booking-specific patterns (state machine, financial operations, webhook processing, naming, real-time events, pricing, API routes) cleanly extend the existing 25+ patterns. Naming conventions follow established prefixes and formats.

**Structure Alignment:** Project tree maps 1:1 with architectural decisions. Each service has clear data ownership boundaries.

### Requirements Coverage Validation

**Functional Requirements: 40/40 covered**

| FR Range | Coverage | Key Components |
|----------|----------|---------------|
| FR1-FR11 (Booking Management) | 11/11 | `BookingController`, `BookingService`, `BookingPolicy`, `CreateBookingRequest` |
| FR12-FR19 (Payment & Escrow) | 8/8 | `FedapayService`, `EscrowService`, `BookingPricing`, `financial_events`, `idempotency_key` |
| FR20-FR22 (Wallet & Withdrawals) | 3/3 | `WalletController`, `WalletService`, `wallet_transactions` |
| FR23-FR25 (Messaging) | 3/3 | `BookingMessageSent` broadcast, `private-booking.{id}` channels, Reverb |
| FR26-FR29 (Rating & Reputation) | 4/4 | `BookingRatingController`, `BookingRating`, rating penalty in `HandleBookingCancelled` |
| FR30-FR35 (Notifications) | 6/6 | 6 `Booking*Notification` classes triggered by event listeners |
| FR36-FR40 (Cancellation & Refunds) | 5/5 | `BookingService::cancel()`, `EscrowService::refund()`, `FedapayService::refund()` |

**Non-Functional Requirements: All covered**

| NFR Category | Coverage | Key Mechanisms |
|-------------|----------|---------------|
| Security (NFR-S1 to S8) | 8/8 | Inherited (Sanctum, HTTPS, rate limiting) + BookingPricing server validation + financial_events audit |
| Reliability (NFR-R1 to R6) | 6/6 | DB::transaction(), idempotency_key, AutoCompleteBookingsCommand, FedapayWebhookEvent |
| Integration (NFR-I1 to I5) | 5/5 | FedapayService (payment/refund/withdrawal), Reverb, graceful error handling |

### Gap Analysis

**Critical Gaps:** None.

**Important Gaps (non-blocking, addressed):**

1. **Reverb health check** — Added `GET /api/v1/health/reverb` endpoint. Frontend shows "chat temporarily unavailable" if Reverb is down.
2. **Reconciliation automation** — Added `ReconcileWalletCommand` as daily scheduled task comparing `financial_events` totals with `wallet_transactions`.

**Deferred by design (V2):**
- Dispute resolution admin interface
- KYC / advanced fraud detection
- Push / SMS notifications
- Calendar / availability management

### Architecture Completeness Checklist

**Requirements Analysis**
- [x] Project context thoroughly analyzed (brownfield, constraints, cross-cutting)
- [x] Scale and complexity assessed (high — multi-party financial flows)
- [x] Technical constraints identified (VPS, Fedapay dependency, coexistence)
- [x] Cross-cutting concerns mapped (6 concerns)

**Architectural Decisions**
- [x] Critical decisions documented with rationale (8 new decisions)
- [x] Technology stack fully specified (inherited + 3 new dependencies)
- [x] Integration patterns defined (Fedapay, Reverb, existing system)
- [x] Performance considerations addressed (mobile-first, lazy loading, pagination)

**Implementation Patterns**
- [x] Naming conventions established (booking-specific extensions)
- [x] Structure patterns defined (service ownership, data boundaries)
- [x] Communication patterns specified (Reverb channels, events, webhooks)
- [x] Process patterns documented (state machine, financial ops, idempotency)

**Project Structure**
- [x] Complete directory structure defined (50+ new files)
- [x] Component boundaries established (BookingService, EscrowService, WalletService, FedapayService)
- [x] Integration points mapped (internal + external)
- [x] Requirements to structure mapping complete (40 FRs → specific files)

### Architecture Readiness Assessment

**Overall Status:** READY FOR IMPLEMENTATION

**Confidence Level:** High

**Key Strengths:**
- Clean separation from existing Mission workflow (zero regression risk)
- Financial integrity patterns enforced at every level (atomic, idempotent, auditable)
- Clear service ownership boundaries (single writer per data domain)
- Complete FR-to-structure mapping (no ambiguity for implementing agents)
- Brownfield-aware — inherits all existing conventions without modification

**Areas for Future Enhancement:**
- Reverb health check + chat fallback mechanism
- Reconciliation automation (daily command)
- Admin dashboard for dispute resolution (V2)
- Redis caching for wallet balance reads (V2)

### Implementation Handoff

**AI Agent Guidelines:**
- Follow ALL architectural decisions exactly as documented in this file
- Inherit ALL patterns from `docs/planning-artifacts/architecture.md` — this document extends, never overrides
- Use booking-specific implementation patterns (state machine, financial ops, webhook, pricing)
- Respect service ownership boundaries — never bypass BookingService for state changes
- Refer to the Requirements-to-Structure mapping for file placement

**First Implementation Priority:**
1. Database migrations (6 new tables + 1 column addition)
2. Enums + BookingPricing Value Object
3. Models with relationships
4. BookingService with state machine
5. FedapayService + webhook handling

## Architecture Completion Summary

### Workflow Completion

**Architecture Decision Workflow:** COMPLETED
**Total Steps Completed:** 8
**Date Completed:** 2026-02-28
**Document Location:** `_bmad-output/planning-artifacts/architecture-booking.md`

### Final Architecture Deliverables

**Complete Architecture Document:**
- 8 new architectural decisions documented with rationale
- 7 booking-specific implementation patterns defined
- 50+ new files mapped across backend and frontend
- 40/40 functional requirements architecturally supported
- All non-functional requirements addressed

**Architecture Scope:**
- Extends existing WEACT architecture (brownfield — inherits 15 decisions, 25+ patterns)
- Adds Booking & Payment domain as parallel workflow to Mission
- Integrates 3 new dependencies: Laravel Reverb, Fedapay SDK, Laravel Echo

**Development Sequence:**
1. Database migrations (bookings, escrow, wallet, financial_events, messages, ratings, webhook_events)
2. BookingStatus enum + BookingPricing Value Object
3. Eloquent models with relationships
4. BookingService with state machine transitions
5. FedapayService + webhook controller + idempotency
6. EscrowService + WalletService
7. BookingPolicy authorization
8. API routes + controllers + Form Requests + Resources
9. Laravel Reverb setup + chat channels + broadcasting
10. Frontend: stores, composables, components, pages
11. Scheduled commands (auto-complete 72h, expire unpaid 24h)
12. Rating integration + notifications

---

**Architecture Status:** READY FOR IMPLEMENTATION

**Next Phase:** Create Epics & Stories (`/bmad:bmm:workflows:create-epics-and-stories`) or UX Design (`/bmad:bmm:workflows:create-ux-design`)

**Document Maintenance:** Update this architecture when major technical decisions are made during implementation.
