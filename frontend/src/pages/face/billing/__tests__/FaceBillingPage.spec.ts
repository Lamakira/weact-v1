import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import FaceBillingPage from '../FaceBillingPage.vue'
import type {
  FaceSubscriptionTier,
  SubscriptionCurrent,
  SubscriptionStatusValue,
  TierCapabilities,
} from '@/features/face/types'
import type { FaceBillingHistoryItem } from '@/features/face/services/faceBillingApi'

const ctx = vi.hoisted(() => ({
  status: {} as Record<string, unknown>,
  payment: {} as Record<string, unknown>,
  getHistory: vi.fn(),
  routerPush: vi.fn(),
  toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn() },
}))

vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ctx.status,
}))
vi.mock('@/features/face/composables/useSubscriptionPayment', () => ({
  useSubscriptionPayment: () => ctx.payment,
}))
vi.mock('@/features/face/services/faceBillingApi', () => ({
  faceBillingApi: { getHistory: ctx.getHistory },
}))
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: ctx.routerPush }),
}))
vi.mock('@/composables/useToast', () => ({
  useToast: () => ctx.toast,
}))
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: (e: unknown) => (e instanceof Error ? e.message : 'Erreur'),
}))

const CAPS: TierCapabilities = {
  max_album_photos: 4,
  max_presentation_videos: 1,
  max_acting_videos: 1,
  max_ugc_videos: 0,
  ugc_access: true,
  commission_rate: 0.1,
  sort_priority: 2,
  has_elite_badge: false,
}

const ALL_CTA = { upgrade_available: true, downgrade_available: true, renew_available: true }
const PENDING_CTA = { upgrade_available: false, downgrade_available: false, renew_available: false }

function daysFromNowISO(days: number): string {
  return new Date(Date.now() + days * 86400000).toISOString()
}

function makeCurrent(opts: {
  tier: FaceSubscriptionTier
  status: SubscriptionStatusValue
  plan?: 'starter' | 'pro' | 'elite' | null
  starts_at?: string | null
  expires_at?: string | null
  cancelled_at?: string | null
}): SubscriptionCurrent {
  return {
    tier: opts.tier,
    plan: opts.plan ?? (opts.tier === 'free' ? null : opts.tier),
    status: opts.status,
    starts_at: opts.starts_at ?? null,
    expires_at: opts.expires_at ?? null,
    cancelled_at: opts.cancelled_at ?? null,
    capabilities: CAPS,
  }
}

function setupStatus(opts: {
  current?: SubscriptionCurrent | null
  cta?: typeof ALL_CTA
  fetchStatus?: ReturnType<typeof vi.fn>
} = {}): void {
  ctx.status.current = ref(
    opts.current === undefined
      ? makeCurrent({ tier: 'pro', status: 'active', starts_at: daysFromNowISO(-60), expires_at: daysFromNowISO(300) })
      : opts.current,
  )
  ctx.status.cta = ref(opts.cta ?? ALL_CTA)
  ctx.status.fetchStatus = opts.fetchStatus ?? vi.fn().mockResolvedValue(undefined)
  ctx.status.refreshStatus = vi.fn().mockResolvedValue(undefined)
}

function setupPayment(opts: {
  paymentState?: 'idle' | 'waiting' | 'confirmed' | 'failed'
  isInitiating?: boolean
  isPolling?: boolean
  isVerifying?: boolean
  isCancelling?: boolean
  paymentError?: string | null
  cancelPendingResult?: boolean
} = {}): void {
  ctx.payment.isInitiating = ref(opts.isInitiating ?? false)
  ctx.payment.isPolling = ref(opts.isPolling ?? false)
  ctx.payment.isVerifying = ref(opts.isVerifying ?? false)
  ctx.payment.isCancelling = ref(opts.isCancelling ?? false)
  ctx.payment.paymentState = ref(opts.paymentState ?? 'idle')
  ctx.payment.error = ref(opts.paymentError ?? null)
  ctx.payment.verifyPayment = vi.fn().mockResolvedValue(undefined)
  ctx.payment.resumePayment = vi.fn().mockResolvedValue(true)
  ctx.payment.cancelPending = vi.fn().mockResolvedValue(opts.cancelPendingResult ?? true)
  ctx.payment.dismissPaymentError = vi.fn()
}

function histItem(overrides: Partial<FaceBillingHistoryItem>): FaceBillingHistoryItem {
  return {
    id: overrides.id ?? 'h1',
    plan: overrides.plan ?? 'starter',
    plan_label: 'Starter',
    status: overrides.status ?? 'expired',
    status_label: 'Expiré',
    starts_at: overrides.starts_at ?? '2024-01-12T00:00:00Z',
    expires_at: overrides.expires_at ?? '2025-01-12T00:00:00Z',
    cancelled_at: overrides.cancelled_at ?? null,
    paid_amount: overrides.paid_amount ?? 12000,
    currency: overrides.currency ?? 'XOF',
    provider: overrides.provider ?? 'fedapay',
    provider_reference: overrides.provider_reference ?? 'TX-1',
    created_at: overrides.created_at ?? '2024-01-12T00:00:00Z',
  }
}

describe('FaceBillingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setupStatus()
    setupPayment()
    ctx.getHistory.mockResolvedValue({ data: [] })
  })

  it('shows the loading skeleton until status resolves', async () => {
    setupStatus({ current: null, fetchStatus: vi.fn(() => new Promise(() => {})) }) // never resolves
    const wrapper = mount(FaceBillingPage)
    await flushPromises()
    expect(wrapper.find('[data-testid="billing-loading"]').exists()).toBe(true)
  })

  it('renders an active Pro subscription: tier name, "Actif" pill, "Changer de plan" CTA', async () => {
    setupStatus({
      current: makeCurrent({ tier: 'pro', status: 'active', starts_at: daysFromNowISO(-60), expires_at: daysFromNowISO(300) }),
    })
    const wrapper = mount(FaceBillingPage)
    await flushPromises()

    expect(wrapper.get('[data-testid="current-tier-name"]').text()).toBe('Pro')
    expect(wrapper.get('[data-testid="current-status-pill"]').text()).toContain('Actif')
    expect(wrapper.get('[data-testid="change-plan-button"]').text()).toBe('Changer de plan')
    expect(wrapper.get('[data-testid="current-info-note"]').text()).toContain('Abonnement annuel')
  })

  it('shows the WeAct badge only for Élite — never for Pro (badge is Élite-reserved)', async () => {
    setupStatus({
      current: makeCurrent({ tier: 'pro', status: 'active', starts_at: daysFromNowISO(-60), expires_at: daysFromNowISO(300) }),
    })
    const pro = mount(FaceBillingPage)
    await flushPromises()
    expect(pro.get('[data-testid="current-tier-name"]').text()).toBe('Pro')
    expect(pro.find('svg.weact-badge').exists()).toBe(false)

    setupStatus({
      current: makeCurrent({ tier: 'elite', status: 'active', starts_at: daysFromNowISO(-60), expires_at: daysFromNowISO(300) }),
    })
    const elite = mount(FaceBillingPage)
    await flushPromises()
    expect(elite.get('[data-testid="current-tier-name"]').text()).toBe('Élite')
    expect(elite.find('svg.weact-badge').exists()).toBe(true)
  })

  it('renders a cancelled subscription as an IMMEDIATE downgrade — no "access until" promise', async () => {
    setupStatus({
      current: makeCurrent({
        tier: 'free',
        status: 'cancelled',
        plan: 'pro',
        starts_at: daysFromNowISO(-60),
        expires_at: daysFromNowISO(120),
        cancelled_at: daysFromNowISO(-1),
      }),
    })
    const wrapper = mount(FaceBillingPage)
    await flushPromises()

    expect(wrapper.get('[data-testid="current-tier-name"]').text()).toBe('Pro')
    expect(wrapper.get('[data-testid="current-status-pill"]').text()).toContain('Annulé')

    const note = wrapper.get('[data-testid="current-info-note"]').text()
    expect(note).toContain('Découverte')
    expect(note).not.toContain("conserves l'accès")

    const card = wrapper.get('[data-testid="current-subscription"]').text()
    expect(card).toContain('Annulé le')
    expect(card).not.toContain("Accès jusqu'au")
    expect(card).not.toContain('Temps restant sur la période')
    // The lapsed plan's price must not read as an ongoing charge — Montant tile hidden.
    expect(card).not.toContain('Montant')
    expect(card).not.toContain('FCFA')
  })

  it('renders a cancelled-pending / failed payment as free Découverte — no attempted-plan name or price (reported bug)', async () => {
    // A Découverte user who cancelled a pending Starter purchase: cancelOwnPending
    // sets status=failed, plan=starter, tier=free. The card must NOT claim "Starter".
    setupStatus({ current: makeCurrent({ tier: 'free', status: 'failed', plan: 'starter' }), cta: ALL_CTA })
    const wrapper = mount(FaceBillingPage)
    await flushPromises()

    expect(wrapper.get('[data-testid="current-tier-name"]').text()).toBe('Découverte')
    expect(wrapper.get('[data-testid="current-status-pill"]').text()).toContain('Découverte')

    const card = wrapper.get('[data-testid="current-subscription"]').text()
    expect(card).not.toContain('Starter')
    expect(card).not.toContain('FCFA')
    expect(card).toContain('Gratuit')
  })

  it('hides the Montant tile for an expired plan (keeps the lapsed plan name, drops the misleading price)', async () => {
    setupStatus({
      current: makeCurrent({
        tier: 'free',
        status: 'expired',
        plan: 'pro',
        starts_at: daysFromNowISO(-400),
        expires_at: daysFromNowISO(-30),
      }),
    })
    const wrapper = mount(FaceBillingPage)
    await flushPromises()

    expect(wrapper.get('[data-testid="current-tier-name"]').text()).toBe('Pro')
    expect(wrapper.get('[data-testid="current-status-pill"]').text()).toContain('Expiré')
    const card = wrapper.get('[data-testid="current-subscription"]').text()
    expect(card).not.toContain('Montant')
    expect(card).not.toContain('FCFA')
    expect(card).toContain('Expiré le')
  })

  it('renders the free state with "Découverte" pill and "Choisir un abonnement" CTA', async () => {
    setupStatus({ current: makeCurrent({ tier: 'free', status: 'free' }), cta: ALL_CTA })
    const wrapper = mount(FaceBillingPage)
    await flushPromises()

    expect(wrapper.get('[data-testid="current-tier-name"]').text()).toBe('Découverte')
    expect(wrapper.get('[data-testid="current-status-pill"]').text()).toContain('Découverte')
    expect(wrapper.get('[data-testid="change-plan-button"]').text()).toBe('Choisir un abonnement')
  })

  it('"Changer de plan" navigates to the pricing route', async () => {
    setupStatus({ current: makeCurrent({ tier: 'free', status: 'free' }) })
    const wrapper = mount(FaceBillingPage)
    await flushPromises()

    await wrapper.get('[data-testid="change-plan-button"]').trigger('click')
    expect(ctx.routerPush).toHaveBeenCalledWith({ name: 'pricing' })
  })

  it('lists expired/cancelled history rows and excludes active/pending/failed', async () => {
    setupStatus({
      current: makeCurrent({ tier: 'pro', status: 'active', starts_at: daysFromNowISO(-60), expires_at: daysFromNowISO(300) }),
    })
    ctx.getHistory.mockResolvedValue({
      data: [
        histItem({ id: 'h-active', status: 'active', plan: 'pro' }),
        histItem({ id: 'h-pending', status: 'pending_payment', plan: 'pro' }),
        histItem({ id: 'h-failed', status: 'failed', plan: 'elite' }),
        histItem({ id: 'h-expired', status: 'expired', plan: 'starter' }),
        histItem({ id: 'h-cancelled', status: 'cancelled', plan: 'pro' }),
      ],
    })
    const wrapper = mount(FaceBillingPage)
    await flushPromises()

    expect(wrapper.find('[data-testid="history-row-h-expired"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="history-row-h-cancelled"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="history-row-h-active"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="history-row-h-pending"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="history-row-h-failed"]').exists()).toBe(false)
  })

  it('expands a history row to reveal billing detail on click', async () => {
    setupStatus({ current: makeCurrent({ tier: 'free', status: 'free' }) })
    ctx.getHistory.mockResolvedValue({
      data: [histItem({ id: 'h-x', status: 'expired', plan: 'starter', provider_reference: 'TX-ABC' })],
    })
    const wrapper = mount(FaceBillingPage)
    await flushPromises()

    const row = wrapper.get('[data-testid="history-row-h-x"]')
    expect(row.text()).not.toContain('TX-ABC')
    await row.find('button').trigger('click')
    expect(row.text()).toContain('TX-ABC')
    expect(row.find('button').attributes('aria-expanded')).toBe('true')
  })

  it('dedups the current representative row out of the history list (cancelled current)', async () => {
    const expiresAt = daysFromNowISO(120)
    setupStatus({
      current: makeCurrent({ tier: 'free', status: 'cancelled', plan: 'pro', starts_at: daysFromNowISO(-60), expires_at: expiresAt }),
    })
    ctx.getHistory.mockResolvedValue({
      data: [
        histItem({ id: 'h-current-twin', status: 'cancelled', plan: 'pro', expires_at: expiresAt }),
        histItem({ id: 'h-old', status: 'expired', plan: 'starter' }),
      ],
    })
    const wrapper = mount(FaceBillingPage)
    await flushPromises()

    expect(wrapper.find('[data-testid="history-row-h-current-twin"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="history-row-h-old"]').exists()).toBe(true)
  })

  it('renders the error state with a retry button that reloads', async () => {
    setupStatus({ current: null, fetchStatus: vi.fn().mockRejectedValueOnce(new Error('Réseau indisponible')) })
    const wrapper = mount(FaceBillingPage)
    await flushPromises()

    const errorBlock = wrapper.get('[data-testid="billing-error"]')
    expect(errorBlock.text()).toContain('Réseau indisponible')

    // Retry path uses refreshStatus (force); make current resolve to free.
    ctx.status.current = ref(makeCurrent({ tier: 'free', status: 'free' }))
    await errorBlock.find('button').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="billing-error"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="current-tier-name"]').text()).toBe('Découverte')
  })

  // -----------------------------------------------------------------------
  // Pending-payment management (resume / verify / cancel) — the billing tab
  // is the home for resuming an unconfirmed payment.
  // -----------------------------------------------------------------------

  it('renders the pending banner with Continuer / Vérifier / Annuler when a payment is pending', async () => {
    setupStatus({
      current: makeCurrent({ tier: 'free', status: 'pending_payment', plan: 'pro' }),
      cta: PENDING_CTA,
    })
    const wrapper = mount(FaceBillingPage)
    await flushPromises()

    expect(wrapper.find('[data-testid="billing-banner-pending"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="billing-banner-resume"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="billing-banner-verify"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="billing-banner-cancel"]').exists()).toBe(true)
  })

  it('"Continuer le paiement" calls resumePayment and "Vérifier maintenant" calls verifyPayment', async () => {
    setupStatus({
      current: makeCurrent({ tier: 'free', status: 'pending_payment', plan: 'pro' }),
      cta: PENDING_CTA,
    })
    const wrapper = mount(FaceBillingPage)
    await flushPromises()

    await wrapper.get('[data-testid="billing-banner-resume"]').trigger('click')
    expect(ctx.payment.resumePayment).toHaveBeenCalledTimes(1)

    await wrapper.get('[data-testid="billing-banner-verify"]').trigger('click')
    expect(ctx.payment.verifyPayment).toHaveBeenCalledWith({ manual: true })
  })

  it('"Annuler le paiement" opens the ConfirmModal; confirming calls cancelPending and toasts', async () => {
    setupStatus({
      current: makeCurrent({ tier: 'free', status: 'pending_payment', plan: 'pro' }),
      cta: PENDING_CTA,
    })
    const wrapper = mount(FaceBillingPage)
    await flushPromises()

    const { default: ConfirmModal } = await import('@/components/ui/ConfirmModal.vue')
    expect(wrapper.findComponent(ConfirmModal).props('isOpen')).toBe(false)

    await wrapper.get('[data-testid="billing-banner-cancel"]').trigger('click')
    expect(wrapper.findComponent(ConfirmModal).props('isOpen')).toBe(true)

    wrapper.findComponent(ConfirmModal).vm.$emit('confirm')
    await flushPromises()

    expect(ctx.payment.cancelPending).toHaveBeenCalledTimes(1)
    expect(ctx.toast.success).toHaveBeenCalledWith('Paiement annulé.')
    expect(wrapper.findComponent(ConfirmModal).props('isOpen')).toBe(false)
  })

  it('shows the waiting banner with precedence over the pending banner', async () => {
    setupStatus({
      current: makeCurrent({ tier: 'free', status: 'pending_payment', plan: 'pro' }),
      cta: PENDING_CTA,
    })
    setupPayment({ paymentState: 'waiting' })
    const wrapper = mount(FaceBillingPage)
    await flushPromises()

    expect(wrapper.find('[data-testid="billing-banner-waiting"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="billing-banner-pending"]').exists()).toBe(false)
  })
})
