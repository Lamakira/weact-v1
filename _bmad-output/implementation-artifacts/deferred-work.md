# Deferred Work

## Deferred from: code review of fix-4-2-admin-featured-faces-ordering (2026-04-05)

- Migration down() does not restore original ville free-text values or quartier data — irreversible migration (FIX-4.1 scope)
- Migration silently nullifies unmatched city values with no audit trail — limited alias coverage in BeninCities::match() (FIX-4.1 scope)
- Frontend/backend Benin city lists are duplicated with no synchronization mechanism — risk of silent divergence (FIX-4.1 scope)
- Validation order: Rule::in + withValidator produce confusing double error messages for ville with non-Benin pays (FIX-4.1 scope)
- normalizedLookup() rebuilds full lookup array on every match() call — no memoization during migration (FIX-4.1 scope)
- loadPage early return on invalid city silently shows empty results with no user feedback (FIX-4.1 scope)
- Test test_formatted_location_with_only_ville relies on pre-existing pays model state (FIX-4.1 scope)
- BeninCities::match() N'Dali apostrophe edge case — "NDali" normalizes to "ndali" which won't match "n dali" (FIX-4.1 scope)
- quartier removed from Face model $fillable but Face factory not verified for stale quartier generation (FIX-4.1 scope)
- useBioLocation validateLocation uses stale bioLocationInfo as fallback for pays — client/server mismatch possible (FIX-4.1 scope)
- Existing city-standardization migration silently nulls unmatched `ville` values with no audit trail (FIX-4.1 scope)
- Admin Face detail page still edits `ville` via free-text input while backend expects the standardized Benin city list (FIX-4.1 scope)
- `quartier` contract removals bundled in adjacent FIX-4.1 changes remain out of scope for FIX-4.2 review patches (FIX-4.1 scope)

## Deferred from: code review of fix-7-2-mission-location-benin-cities (2026-04-06)

- Filter panel `lieu` field in MissionFiltersPanel.vue remains free-text while mission creation now uses enumerated city select — consider converting to select for consistency (FIX-7.2 scope)
- Test fixtures in useMissionsList.spec.ts and AvailableMissionCard.spec.ts use `lieu: 'Paris'` — non-Benin value that doesn't reflect current data model (FIX-7.2 scope)

## Deferred from: code review of fix-8-1-face-age-visibility-toggle (2026-04-06)

- FaceFactory has no `show_age` state helper — tests use inline create(['show_age' => false]), works fine but less explicit (FIX-8.1 scope)
- PublicFaceProfileView casts from list resource lacking age field — pre-existing fragile cast, works via nullish fallback (FIX-8.1 scope)
- Admin panel doesn't surface show_age flag — admins always see age, but can't see what the Face chose (FIX-8.1 scope)

## Deferred from: code review of fix-9-1-whatsapp-editable-profile-completion (2026-04-06)

- No phone number format validation — `whatsapp_number` accepts any string up to 30 chars. Both registration and update flows share this weak validation. Consider adding a regex like `/^\+?[0-9\s\-]+$/` (FIX-9.1 scope)
- Admin cannot edit `whatsapp_number` — `UpdateAdminFaceRequest` does not include the field (FIX-9.1 scope)
- `FaceResource` exposes `whatsapp_number` to all authenticated users — producers can see WhatsApp numbers of all Faces. Consider access control similar to `show_age` (FIX-9.1 scope)
- `FaceFactory::withPersonalInfo()` does not set `whatsapp_number` — future tests using this state may get unexpected completion percentages (FIX-9.1 scope)

## Deferred from: code review of fix-9-2-whatsapp-dashboard-hint-banner (2026-04-06)

- `handlePersonalInfoSave` type signature in `ProfileEditPage.vue` drops `show_age` and `whatsapp_number` — no runtime data loss but TypeScript type is inaccurate (FIX-9.2 scope)

## Deferred from: code review of fix-10-3-no-show-report-wallet-refund (2026-04-07)

- `goBack()` fallback in `FaceBookingDetailPage.vue` sends Producers to `face-dashboard` instead of `producer-dashboard` — pre-existing issue in the shared booking detail page, affects all Producer actions (FIX-10.3 scope)
- `BookingTimeline.vue` references `baseSteps` by array index instead of by key — brittle to future reordering but low risk currently (FIX-10.3 scope)
- `ShouldBroadcastNow` on all booking events adds synchronous latency before HTTP response — cross-cutting concern, all events (BookingCancelled, BookingExpired, etc.) use the same pattern. Consider domain-wide migration to `ShouldBroadcast` (queued) (FIX-10.3 scope)

## Deferred from: code review of rt-1-2-store-frontend-echo-subscription (2026-04-11)

- `unsubscribe()` reads `userId` from auth store at call time — fragile coupling with `clearAuth()` ordering. Storing userId at subscribe-time would be more robust (RT-1.2 scope)
- 401 interceptor in `apiClient.ts` calls `authStore.clearAuth()` but never calls `notificationStore.unsubscribe()` — on session expiry the Echo channel stays subscribed with stale credentials, and `isSubscribed` remains true, preventing re-subscription on next login. Belongs in RT-1.4 fallback/reconnect scope

## Deferred from: code review of rt-1-3-refactor-ui-bell-dropdown (2026-04-11)

- Add a reconciliation path for `unreadCount` after removing polling — defer to RT-1.4 fallback scope, which already covers window focus refresh, reconnect handling, and slow polling recovery
- First dropdown fetch can lose a realtime notification that arrives in-flight — pre-existing RT-1.2 store race around fetch/knownIds replacement; track as a separate store fix rather than patching RT-1.3

## Deferred from: code review of rt-1-4-fallback-non-regression (2026-04-11)

- 401 interceptor removes localStorage tokens synchronously before async `unsubscribe()` runs — if auth store ever derived `user` from localStorage instead of Pinia state, `echo.leave()` would be skipped. Pre-existing interceptor pattern, not introduced by RT-1.4
- No debounce on window focus handler — rapid tab-switching triggers concurrent `fetchUnreadCount()` and `fetchNotifications()` calls, with potential out-of-order response overwrites. Low impact, pre-existing design choice
- In-flight fallback fetches can repopulate the notification store after logout/reset — real but low impact race because only already in-flight requests can write stale badge state, the window is brief, and the next login reconciliation overwrites it
- 401 cleanup can skip `clearAuth()` if the dynamic import chain rejects — defensive weakness in a pre-existing best-effort cleanup pattern; storage is cleared and redirect happens synchronously even if the import chain fails

## Deferred from: code review of fix-19-1-compensate-mission-payment-initiation-failure (2026-04-12)

- Orphan FedaPay transaction after remote `Transaction::create()` succeeds but `generateToken()` or network throws — local deletes payment row, remote transaction persists with stale `custom_metadata.mission_payment_id`; requires reconciliation strategy (fix-19-2/fix-19-4 scope)
- Retry after partial compensation failure blocked by `mission_id` UNIQUE on `mission_payments` — if compensate crashes mid-way, row stays and next `confirmAndPay` hits unique violation → 500; producer trapped (fix-19-2 scope)
- Concurrent double-submit mid-flight creates orphan remote transactions — prepare tx commits before external call, releasing locks; slow FedaPay response lets a second attempt be rejected while the first still spawns a remote transaction (fix-19-2/fix-19-5 scope)
- `Str::uuid()` idempotency key regenerated per call and only stored in FedaPay `custom_metadata` — useless for dedup since FedaPay SDK does not honor it; should derive from `$payment->id` or persist locally (fix-19-2 scope)
- Deadlock risk: prepare locks mission→candidatures→payment; compensate locks payment→mission→candidatures (reverse order) — no retry-on-deadlock wired, fragile under concurrent compensate+retry (fix-19-2 scope)
- Post-commit notification failures silently lose candidature notifications on success path — `notifySafely` swallows failures after `finalizePreparedPayment` commits; producer pays but Faces never learn (pre-existing notifySafely pattern, not introduced by this change)
- Legacy `MissionPaymentService::initiatePayment()` method is currently unused but deferred because fix-19-2 is the planned resume-checkout story and may reuse it as the single resume path
