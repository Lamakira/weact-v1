import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import SubscriptionPanel from '../SubscriptionPanel.vue'
import type {
  FaceSubscriptionTier,
  SubscriptionCurrent,
  SubscriptionStatusValue,
  TierCapabilities,
} from '../../types'

const ctx = vi.hoisted(() => ({
  status: {} as Record<string, unknown>,
  payment: {} as Record<string, unknown>,
  authStore: { isEmailVerified: true },
  routerPush: vi.fn(),
}))

vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ctx.status,
}))
vi.mock('@/features/face/composables/useSubscriptionPayment', () => ({
  useSubscriptionPayment: () => ctx.payment,
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ctx.authStore,
}))
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: ctx.routerPush }),
}))

const CAPABILITIES: Record<FaceSubscriptionTier, TierCapabilities> = {
  free: {
    max_album_photos: 1,
    max_presentation_videos: 0,
    max_acting_videos: 0,
    max_ugc_videos: 0,
    ugc_access: false,
    commission_rate: 0.1,
    sort_priority: 4,
    has_elite_badge: false,
  },
  starter: {
    max_album_photos: 2,
    max_presentation_videos: 1,
    max_acting_videos: 0,
    max_ugc_videos: 0,
    ugc_access: true,
    commission_rate: 0.1,
    sort_priority: 3,
    has_elite_badge: false,
  },
  pro: {
    max_album_photos: 4,
    max_presentation_videos: 1,
    max_acting_videos: 1,
    max_ugc_videos: 0,
    ugc_access: true,
    commission_rate: 0.1,
    sort_priority: 2,
    has_elite_badge: false,
  },
  elite: {
    max_album_photos: 6,
    max_presentation_videos: 1,
    max_acting_videos: 2,
    max_ugc_videos: 1,
    ugc_access: true,
    commission_rate: 0.05,
    sort_priority: 1,
    has_elite_badge: true,
  },
}

function makeCurrent(
  tier: FaceSubscriptionTier,
  status: SubscriptionStatusValue,
  overrides: Partial<SubscriptionCurrent> = {},
): SubscriptionCurrent {
  return {
    tier,
    plan: tier === 'free' ? null : tier,
    status,
    starts_at: null,
    expires_at: null,
    cancelled_at: null,
    capabilities: CAPABILITIES[tier],
    ...overrides,
  }
}

function setupStatus(opts: {
  tier?: FaceSubscriptionTier
  status?: SubscriptionStatusValue
  expiresAt?: string | null
  cancelledAt?: string | null
  isLoading?: boolean
  error?: string | null
  cta?: { upgrade_available: boolean; downgrade_available: boolean; renew_available: boolean }
  currentOverride?: SubscriptionCurrent | null
}): void {
  const tier = opts.tier ?? 'free'
  const status = opts.status ?? 'free'
  const current =
    opts.currentOverride === undefined
      ? makeCurrent(tier, status, {
          expires_at: opts.expiresAt ?? null,
          cancelled_at: opts.cancelledAt ?? null,
        })
      : opts.currentOverride

  ctx.status.current = ref(current)
  ctx.status.cta = ref(
    opts.cta ?? { upgrade_available: true, downgrade_available: true, renew_available: true },
  )
  ctx.status.tier = ref(tier)
  ctx.status.statusValue = ref(status)
  ctx.status.currentPlan = ref(current?.plan ?? null)
  ctx.status.expiresAt = ref(opts.expiresAt ?? null)
  ctx.status.cancelledAt = ref(opts.cancelledAt ?? null)
  ctx.status.isLoading = ref(opts.isLoading ?? false)
  ctx.status.error = ref(opts.error ?? null)
  ctx.status.fetchStatus = vi.fn().mockResolvedValue(undefined)
}

function setupPayment(opts: {
  paymentState?: 'idle' | 'waiting' | 'confirmed' | 'failed'
  isInitiating?: boolean
  isPolling?: boolean
  isVerifying?: boolean
  isCancelling?: boolean
  paymentError?: string | null
} = {}): void {
  ctx.payment.isInitiating = ref(opts.isInitiating ?? false)
  ctx.payment.isPolling = ref(opts.isPolling ?? false)
  ctx.payment.isVerifying = ref(opts.isVerifying ?? false)
  ctx.payment.isCancelling = ref(opts.isCancelling ?? false)
  ctx.payment.paymentState = ref(opts.paymentState ?? 'idle')
  ctx.payment.error = ref(opts.paymentError ?? null)
  ctx.payment.verifyPayment = vi.fn().mockResolvedValue(undefined)
  ctx.payment.resumePayment = vi.fn().mockResolvedValue(true)
  ctx.payment.cancelPending = vi.fn().mockResolvedValue(true)
  ctx.payment.reset = vi.fn()
  ctx.payment.dismissPaymentError = vi.fn()
}

describe('SubscriptionPanel (FP-2.7 v2 — minimalist + resume-pending + redirect)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    ctx.authStore.isEmailVerified = true
    setupStatus({})
    setupPayment()
  })

  it('renders the loading skeleton when isLoading && !current', async () => {
    setupStatus({ isLoading: true, currentOverride: null })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()
    expect(wrapper.get('[data-testid="subscription-panel-loading"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="subscription-panel-change-plan"]').exists()).toBe(false)
  })

  it('renders the error block + retry button which calls fetchStatus', async () => {
    setupStatus({ error: 'Network error', currentOverride: null })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.get('[data-testid="subscription-panel-error"]').text()).toContain(
      'Network error',
    )
    await wrapper.find('button').trigger('click')
    expect(ctx.status.fetchStatus).toHaveBeenCalled()
  })

  it('renders the tier name + status line for an active Pro subscription', async () => {
    setupStatus({ tier: 'pro', status: 'active', expiresAt: '2027-05-23T00:00:00Z' })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.get('[data-testid="subscription-panel-tier-name"]').text()).toBe('Pro')
    expect(wrapper.get('[data-testid="subscription-panel-status-line"]').text()).toContain(
      "Actif jusqu'au",
    )
    expect(wrapper.get('[data-testid="subscription-panel-status-line"]').text()).toContain('2027')
  })

  it('renders the Crown icon adjacent to the tier name only for Élite', async () => {
    setupStatus({ tier: 'elite', status: 'active', expiresAt: '2027-05-23T00:00:00Z' })
    const elite = mount(SubscriptionPanel)
    await flushPromises()
    expect(elite.find('[data-testid="subscription-panel-tier-crown"]').exists()).toBe(true)

    setupStatus({ tier: 'pro', status: 'active', expiresAt: '2027-05-23T00:00:00Z' })
    const pro = mount(SubscriptionPanel)
    await flushPromises()
    expect(pro.find('[data-testid="subscription-panel-tier-crown"]').exists()).toBe(false)
  })

  it('renders "Choisir un abonnement" CTA for a never-paid Free Face', async () => {
    setupStatus({ tier: 'free', status: 'free' })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.get('[data-testid="subscription-panel-status-line"]').text()).toBe(
      'Offre Découverte (gratuite)',
    )
    expect(wrapper.get('[data-testid="subscription-panel-change-plan"]').text()).toBe(
      'Choisir un abonnement',
    )
  })

  it('renders "Expiré le {date}" for an expired Pro Face', async () => {
    setupStatus({
      tier: 'free',
      status: 'expired',
      expiresAt: '2026-04-12T00:00:00Z',
      currentOverride: makeCurrent('free', 'expired', {
        plan: 'pro',
        expires_at: '2026-04-12T00:00:00Z',
      }),
    })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()
    expect(wrapper.get('[data-testid="subscription-panel-status-line"]').text()).toContain(
      'Expiré le',
    )
  })

  it('renders "Annulé le {date}" for a cancelled subscription using cancelled_at', async () => {
    setupStatus({
      tier: 'free',
      status: 'cancelled',
      cancelledAt: '2026-03-01T00:00:00Z',
      currentOverride: makeCurrent('free', 'cancelled', {
        plan: 'pro',
        cancelled_at: '2026-03-01T00:00:00Z',
      }),
    })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()
    expect(wrapper.get('[data-testid="subscription-panel-status-line"]').text()).toContain(
      'Annulé le',
    )
  })

  it('renders "Dernier paiement non abouti" for a failed payment state', async () => {
    setupStatus({ tier: 'free', status: 'failed' })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()
    expect(wrapper.get('[data-testid="subscription-panel-status-line"]').text()).toBe(
      'Dernier paiement non abouti',
    )
  })

  it('clicking the primary CTA calls router.push({ name: "pricing" })', async () => {
    setupStatus({ tier: 'pro', status: 'active', expiresAt: '2027-05-23T00:00:00Z' })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    await wrapper.find('[data-testid="subscription-panel-change-plan"]').trigger('click')
    expect(ctx.routerPush).toHaveBeenCalledWith({ name: 'pricing' })
    expect(ctx.routerPush).toHaveBeenCalledTimes(1)
  })

  it('disables the primary CTA when hasPendingPayment is true (cta all-false)', async () => {
    setupStatus({
      tier: 'pro',
      status: 'active',
      expiresAt: '2027-05-23T00:00:00Z',
      cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
    })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    const cta = wrapper.find('[data-testid="subscription-panel-change-plan"]')
    expect(cta.attributes('disabled')).toBeDefined()
    await cta.trigger('click')
    expect(ctx.routerPush).not.toHaveBeenCalled()
  })

  it('renders BOTH "Continuer le paiement" and "Vérifier maintenant" when the pending banner is visible', async () => {
    setupStatus({
      tier: 'pro',
      status: 'pending_payment',
      cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
    })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.find('[data-testid="subscription-panel-pending"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="subscription-panel-resume"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="subscription-panel-verify"]').exists()).toBe(true)

    await wrapper.find('[data-testid="subscription-panel-resume"]').trigger('click')
    expect(ctx.payment.resumePayment).toHaveBeenCalledTimes(1)
  })

  it('shows the email-not-verified note and disables the CTA when email is unverified', async () => {
    ctx.authStore.isEmailVerified = false
    setupStatus({ tier: 'free', status: 'free' })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.find('[data-testid="subscription-panel-email-note"]').exists()).toBe(true)
    const cta = wrapper.find('[data-testid="subscription-panel-change-plan"]')
    expect(cta.attributes('disabled')).toBeDefined()
  })

  it('shows the waiting banner with precedence over the pending banner (P7 preserved)', async () => {
    setupStatus({
      tier: 'pro',
      status: 'pending_payment',
      cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
    })
    setupPayment({ paymentState: 'waiting' })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.find('[data-testid="subscription-panel-waiting"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="subscription-panel-pending"]').exists()).toBe(false)
  })

  it('shows the payment-failed banner with a dismissible error', async () => {
    setupStatus({ tier: 'pro', status: 'active', expiresAt: '2027-05-23T00:00:00Z' })
    setupPayment({ paymentState: 'failed', paymentError: 'Une erreur est survenue' })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.find('[data-testid="subscription-panel-payment-error"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="subscription-panel-payment-error"]').text()).toContain(
      'Une erreur est survenue',
    )

    await wrapper.find('[data-testid="subscription-panel-payment-error"] button').trigger('click')
    // Round 2 P6 — dismiss must NOT call reset() (which would clear the stash);
    // it calls the surgical dismissPaymentError() that preserves the stash.
    expect(ctx.payment.dismissPaymentError).toHaveBeenCalled()
    expect(ctx.payment.reset).not.toHaveBeenCalled()
  })

  it('does NOT render any TierCard or TierChangeModal in the DOM (v2 invariant)', async () => {
    setupStatus({ tier: 'pro', status: 'active', expiresAt: '2027-05-23T00:00:00Z' })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    // The minimalist v2 panel has zero tier cards and zero modal.
    expect(wrapper.find('[data-testid^="tier-card-"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="tier-change-modal"]').exists()).toBe(false)
  })

  it('shows the historical plan name (not "Découverte") for an expired paid Face (Findings #3 AC #5)', async () => {
    setupStatus({
      tier: 'free',
      status: 'expired',
      expiresAt: '2026-04-12T00:00:00Z',
      currentOverride: makeCurrent('free', 'expired', {
        plan: 'pro',
        expires_at: '2026-04-12T00:00:00Z',
      }),
    })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.get('[data-testid="subscription-panel-tier-name"]').text()).toBe('Pro')
    expect(wrapper.get('[data-testid="subscription-panel-status-line"]').text()).toContain(
      'Expiré le',
    )
  })

  it('shows the Crown on an expired Élite Face (historical plan derives isElite, Findings #3)', async () => {
    setupStatus({
      tier: 'free',
      status: 'expired',
      expiresAt: '2026-04-12T00:00:00Z',
      currentOverride: makeCurrent('free', 'expired', {
        plan: 'elite',
        expires_at: '2026-04-12T00:00:00Z',
      }),
    })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.get('[data-testid="subscription-panel-tier-name"]').text()).toBe('Élite')
    expect(wrapper.find('[data-testid="subscription-panel-tier-crown"]').exists()).toBe(true)
  })

  it('shows the historical plan name for a cancelled paid Face (Findings #3 AC #5 symmetric for cancelled)', async () => {
    setupStatus({
      tier: 'free',
      status: 'cancelled',
      cancelledAt: '2026-03-01T00:00:00Z',
      currentOverride: makeCurrent('free', 'cancelled', {
        plan: 'starter',
        cancelled_at: '2026-03-01T00:00:00Z',
      }),
    })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.get('[data-testid="subscription-panel-tier-name"]').text()).toBe('Starter')
  })

  it('renders an inline error on manual verify failure when paymentState is not "failed" (Findings #2 visibility)', async () => {
    setupStatus({
      tier: 'pro',
      status: 'pending_payment',
      cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
    })
    // Manual-verify error set without state flip (the composable contract).
    setupPayment({ paymentState: 'idle', paymentError: 'Erreur de vérification' })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    // Round 2 P10 — testid renamed to "pending-error" because resumePayment errors
    // also land here, making "verify-error" semantically misleading.
    expect(wrapper.find('[data-testid="subscription-panel-pending-error"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="subscription-panel-pending-error"]').text()).toBe(
      'Erreur de vérification',
    )
    // The dedicated failed banner is NOT rendered (state isn't 'failed').
    expect(wrapper.find('[data-testid="subscription-panel-payment-error"]').exists()).toBe(false)
  })

  it('disables BOTH resume and verify buttons while isVerifying (Findings #4 race guard)', async () => {
    setupStatus({
      tier: 'pro',
      status: 'pending_payment',
      cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
    })
    setupPayment({ isVerifying: true })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.get('[data-testid="subscription-panel-resume"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('[data-testid="subscription-panel-verify"]').attributes('disabled')).toBeDefined()
  })

  it('disables BOTH resume and verify buttons while isInitiating (Findings #4 symmetric)', async () => {
    setupStatus({
      tier: 'pro',
      status: 'pending_payment',
      cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
    })
    setupPayment({ isInitiating: true })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.get('[data-testid="subscription-panel-resume"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('[data-testid="subscription-panel-verify"]').attributes('disabled')).toBeDefined()
  })

  it('shows ONLY the failed banner (not the pending banner) when paymentState=failed (Findings #5 cascade)', async () => {
    setupStatus({
      tier: 'pro',
      status: 'pending_payment',
      cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
    })
    // Simulate the polling timeout: paymentState='failed' while backend status
    // is still 'pending_payment' (the stale row hasn't been auto-failed yet).
    setupPayment({ paymentState: 'failed', paymentError: 'Le délai a expiré.' })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.find('[data-testid="subscription-panel-payment-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="subscription-panel-pending"]').exists()).toBe(false)
  })

  // Round 2 D2 — displayTier extends to 'failed' so the user keeps the historical
  // paid plan context on a failed payment instead of seeing "Découverte".
  it('shows the historical plan name (not "Découverte") for a failed paid Face (Round 2 D2)', async () => {
    setupStatus({
      tier: 'free',
      status: 'failed',
      currentOverride: makeCurrent('free', 'failed', { plan: 'pro' }),
    })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.get('[data-testid="subscription-panel-tier-name"]').text()).toBe('Pro')
    expect(wrapper.get('[data-testid="subscription-panel-status-line"]').text()).toBe(
      'Dernier paiement non abouti',
    )
  })

  it('shows the Crown on a failed Élite Face (Round 2 D2 symmetric for Élite)', async () => {
    setupStatus({
      tier: 'free',
      status: 'failed',
      currentOverride: makeCurrent('free', 'failed', { plan: 'elite' }),
    })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.get('[data-testid="subscription-panel-tier-name"]').text()).toBe('Élite')
    expect(wrapper.find('[data-testid="subscription-panel-tier-crown"]').exists()).toBe(true)
  })

  // Round 2 P7 — formatDate guards against null and invalid ISO strings, falling
  // back to a date-less status line instead of rendering "Actif jusqu'au " or
  // "Invalid Date" raw.
  it('falls back to a date-less status line when expires_at is null on active', async () => {
    setupStatus({
      tier: 'pro',
      status: 'active',
      currentOverride: makeCurrent('pro', 'active', { expires_at: null }),
    })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.get('[data-testid="subscription-panel-status-line"]').text()).toBe('Actif')
  })

  it('falls back to a date-less status line when expires_at is a malformed string', async () => {
    setupStatus({
      tier: 'pro',
      status: 'expired',
      expiresAt: 'not-a-date',
      currentOverride: makeCurrent('free', 'expired', { plan: 'pro', expires_at: 'not-a-date' }),
    })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    const line = wrapper.get('[data-testid="subscription-panel-status-line"]').text()
    expect(line).toBe('Expiré')
    expect(line).not.toContain('Invalid Date')
  })
})

describe('Cancel-pending action (FP-2.15.1)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    ctx.authStore.isEmailVerified = true
    setupStatus({})
    setupPayment()
  })

  function pendingBannerSetup(): void {
    // Active Pro with CTA all-false simulates a pending tier-change → pending banner
    // visible. Also covers free → paid pending (statusValue='pending_payment') for the
    // purpose of asserting cancel-button visibility — the banner predicate is the same.
    setupStatus({
      tier: 'pro',
      status: 'active',
      cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
    })
    setupPayment({ paymentState: 'idle' })
  }

  it('T1 — renders "Annuler le paiement" button alongside Continuer / Vérifier when hasPendingPayment is true', async () => {
    pendingBannerSetup()
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.find('[data-testid="subscription-panel-pending"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="subscription-panel-resume"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="subscription-panel-verify"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="subscription-panel-cancel"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="subscription-panel-cancel"]').text()).toBe(
      'Annuler le paiement',
    )
  })

  it('T7 — resume button is visible inside the pending banner without any local stash predicate (FP-2.15.2)', async () => {
    // Pending banner visible → resume button visible (no stash predicate gates it).
    pendingBannerSetup()
    const wrapperPending = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapperPending.find('[data-testid="subscription-panel-pending"]').exists()).toBe(true)
    expect(wrapperPending.find('[data-testid="subscription-panel-resume"]').exists()).toBe(true)

    // Pending banner NOT visible (active + cta enabled) → resume button NOT rendered.
    setupStatus({
      tier: 'pro',
      status: 'active',
      expiresAt: '2027-05-23T00:00:00Z',
      cta: { upgrade_available: true, downgrade_available: false, renew_available: true },
    })
    setupPayment({ paymentState: 'idle' })
    const wrapperActive = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapperActive.find('[data-testid="subscription-panel-pending"]').exists()).toBe(false)
    expect(wrapperActive.find('[data-testid="subscription-panel-resume"]').exists()).toBe(false)
  })

  it('T2 — does NOT render "Annuler le paiement" button when there is no pending banner (e.g., active state)', async () => {
    setupStatus({
      tier: 'pro',
      status: 'active',
      cta: { upgrade_available: true, downgrade_available: false, renew_available: true },
    })
    setupPayment({ paymentState: 'idle' })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.find('[data-testid="subscription-panel-pending"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="subscription-panel-cancel"]').exists()).toBe(false)
  })

  it('T3 — clicking "Annuler le paiement" opens the ConfirmModal (isOpen=true)', async () => {
    pendingBannerSetup()
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    const { default: ConfirmModal } = await import('@/components/ui/ConfirmModal.vue')
    const modalBefore = wrapper.findComponent(ConfirmModal)
    expect(modalBefore.props('isOpen')).toBe(false)

    await wrapper.find('[data-testid="subscription-panel-cancel"]').trigger('click')

    const modalAfter = wrapper.findComponent(ConfirmModal)
    expect(modalAfter.props('isOpen')).toBe(true)
    expect(modalAfter.props('title')).toBe('Annuler le paiement en cours ?')
    expect(modalAfter.props('variant')).toBe('warning')
  })

  it('T4 — confirming the modal calls cancelPending, emits "payment-cancelled" on success, closes the modal', async () => {
    pendingBannerSetup()
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    await wrapper.find('[data-testid="subscription-panel-cancel"]').trigger('click')

    const { default: ConfirmModal } = await import('@/components/ui/ConfirmModal.vue')
    const modal = wrapper.findComponent(ConfirmModal)
    modal.vm.$emit('confirm')
    await flushPromises()

    expect(ctx.payment.cancelPending).toHaveBeenCalledOnce()
    expect(wrapper.emitted('payment-cancelled')).toHaveLength(1)
    expect(wrapper.findComponent(ConfirmModal).props('isOpen')).toBe(false)
  })

  it('T5 — clicking the modal cancel does NOT call cancelPending', async () => {
    pendingBannerSetup()
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    await wrapper.find('[data-testid="subscription-panel-cancel"]').trigger('click')

    const { default: ConfirmModal } = await import('@/components/ui/ConfirmModal.vue')
    const modal = wrapper.findComponent(ConfirmModal)
    modal.vm.$emit('cancel')
    await flushPromises()

    expect(ctx.payment.cancelPending).not.toHaveBeenCalled()
    expect(wrapper.emitted('payment-cancelled')).toBeUndefined()
    expect(wrapper.findComponent(ConfirmModal).props('isOpen')).toBe(false)
  })

  it('T6 — waiting banner renders "Annuler le paiement" button and clicking it opens the ConfirmModal (FP-2.15.1 L2)', async () => {
    setupStatus({
      tier: 'free',
      status: 'pending_payment',
      cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
    })
    setupPayment({ paymentState: 'waiting' })
    const wrapper = mount(SubscriptionPanel)
    await flushPromises()

    expect(wrapper.find('[data-testid="subscription-panel-waiting"]').exists()).toBe(true)
    // Pending banner is suppressed by the cascade while waiting takes precedence.
    expect(wrapper.find('[data-testid="subscription-panel-pending"]').exists()).toBe(false)

    const cancelBtn = wrapper.find('[data-testid="subscription-panel-waiting-cancel"]')
    expect(cancelBtn.exists()).toBe(true)
    expect(cancelBtn.text()).toBe('Annuler le paiement')

    const { default: ConfirmModal } = await import('@/components/ui/ConfirmModal.vue')
    expect(wrapper.findComponent(ConfirmModal).props('isOpen')).toBe(false)

    await cancelBtn.trigger('click')

    expect(wrapper.findComponent(ConfirmModal).props('isOpen')).toBe(true)
  })
})
