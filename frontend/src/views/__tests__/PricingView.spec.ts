import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, RouterLinkStub, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { Check, Crown, Minus } from 'lucide-vue-next'
import PricingView from '../PricingView.vue'
import type {
  FaceSubscriptionTier,
  SubscriptionCurrent,
  SubscriptionOffer,
  SubscriptionStatusValue,
  TierCapabilities,
} from '@/features/face/types'

// =============================================================
// Shared mock context (FP-2.13.1 auth-aware behavior)
// Default state = unauth, so the existing 14+ FP-2.13 tests
// keep passing unchanged (isFace === false → RouterLink fallback).
// =============================================================
const ctx = vi.hoisted(() => ({
  status: {
    current: { value: null },
    cta: {
      value: { upgrade_available: false, downgrade_available: false, renew_available: false },
    },
    tier: { value: 'free' },
    statusValue: { value: 'free' },
    currentPlan: { value: null },
    expiresAt: { value: null },
    offers: { value: [] },
    isLoading: { value: false },
    fetchStatus: vi.fn().mockResolvedValue(undefined),
  } as Record<string, unknown>,
  payment: {
    isInitiating: { value: false },
    isPolling: { value: false },
    isVerifying: { value: false },
    isCancelling: { value: false },
    paymentState: { value: 'idle' },
    error: { value: null },
    initiatePayment: vi.fn().mockResolvedValue(true),
    resumePayment: vi.fn().mockResolvedValue(true),
    verifyPayment: vi.fn().mockResolvedValue(undefined),
    cancelPending: vi.fn().mockResolvedValue(true),
    dismissPaymentError: vi.fn(),
  } as Record<string, unknown>,
  authStore: {
    isAuthenticated: false,
    user: null as { id: number; userable_type: 'Face' | 'Producer'; email_verified: boolean } | null,
    isEmailVerified: false,
  },
  route: { query: {} as Record<string, string> },
  toast: {
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
    clear: vi.fn(),
    toast: vi.fn(),
  },
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
vi.mock('@/composables/useToast', () => ({
  useToast: () => ctx.toast,
}))
vi.mock('vue-router', async (importOriginal) => {
  const actual = await importOriginal<typeof import('vue-router')>()
  return {
    ...actual,
    useRoute: () => ctx.route,
  }
})

// Replace the `vi.hoisted` placeholders with real refs now that `ref` is
// imported. PricingView calls `watch(paymentState, ...)` which requires a
// ref source — without this swap Vue logs an "Invalid watch source" warning
// on every mount in the existing FP-2.13 tests.
ctx.status.current = ref(null)
ctx.status.cta = ref({
  upgrade_available: false,
  downgrade_available: false,
  renew_available: false,
})
ctx.status.tier = ref('free')
ctx.status.statusValue = ref('free')
ctx.status.currentPlan = ref(null)
ctx.status.expiresAt = ref(null)
ctx.status.offers = ref([])
ctx.status.isLoading = ref(false)
ctx.payment.isInitiating = ref(false)
ctx.payment.isPolling = ref(false)
ctx.payment.isVerifying = ref(false)
ctx.payment.isCancelling = ref(false)
ctx.payment.paymentState = ref('idle')
ctx.payment.error = ref(null)

const mountPricing = () =>
  mount(PricingView, {
    global: {
      stubs: {
        RouterLink: RouterLinkStub,
      },
    },
  })

describe('PricingView (public /pricing — FP-2.13)', () => {
  // ---------------------------------------------------------------
  // Hero
  // ---------------------------------------------------------------
  describe('hero section', () => {
    it('renders the H1 copy', () => {
      const wrapper = mountPricing()
      const h1 = wrapper.find('h1')
      expect(h1.exists()).toBe(true)
      expect(h1.text()).toBe('Plus tu montes, plus tu décroches.')
    })
  })

  // ---------------------------------------------------------------
  // Pricing Cards
  // ---------------------------------------------------------------
  describe('pricing cards', () => {
    it('renders the four tier cards in DOM order Découverte → Starter → Pro → Élite', () => {
      const wrapper = mountPricing()
      const cards = wrapper.findAll('[data-testid^="tier-card-"]')
      expect(cards.length).toBe(4)
      const tierNames = cards.map((c) => c.find('h3').text())
      expect(tierNames).toEqual(['Découverte', 'Starter', 'Pro', 'Élite'])
    })

    it('shows the "Populaire" badge inside the Pro card only (not on the three other cards)', () => {
      const wrapper = mountPricing()
      expect(wrapper.get('[data-testid="tier-card-pro"]').text()).toContain('Populaire')
      expect(wrapper.get('[data-testid="tier-card-decouverte"]').text()).not.toContain('Populaire')
      expect(wrapper.get('[data-testid="tier-card-starter"]').text()).not.toContain('Populaire')
      // "Populaire" must not appear inside the Élite card wrapper either.
      expect(wrapper.get('[data-testid="tier-card-elite"]').text()).not.toContain('Populaire')
    })

    it('shows a Crown icon on the Élite card only (no Crown on the three other cards)', () => {
      const wrapper = mountPricing()
      expect(wrapper.get('[data-testid="tier-card-decouverte"]').findAllComponents(Crown).length).toBe(0)
      expect(wrapper.get('[data-testid="tier-card-starter"]').findAllComponents(Crown).length).toBe(0)
      expect(wrapper.get('[data-testid="tier-card-pro"]').findAllComponents(Crown).length).toBe(0)
      expect(wrapper.get('[data-testid="tier-card-elite"]').findAllComponents(Crown).length).toBe(1)
    })

    it('applies the dark variant (bg-[#0F1419]) on the Élite card wrapper, transparent on the others', () => {
      const wrapper = mountPricing()
      const elite = wrapper.get('[data-testid="tier-card-elite"]')
      expect(elite.classes()).toContain('bg-[#0F1419]')
      expect(elite.classes()).toContain('text-white')
      // Sibling cards rely on the page background — no bg-* utility on the wrapper.
      for (const key of ['decouverte', 'starter', 'pro'] as const) {
        const card = wrapper.get(`[data-testid="tier-card-${key}"]`)
        expect(card.classes().some((c) => c.startsWith('bg-'))).toBe(false)
      }
    })

    it('lists "4 photos dans la galerie" inside the Pro card features (AC #6 — content correction)', () => {
      const wrapper = mountPricing()
      const proCard = wrapper.get('[data-testid="tier-card-pro"]')
      expect(proCard.find('h3').text()).toBe('Pro')
      expect(proCard.text()).toContain('4 photos dans la galerie')
      // Make sure the legacy "2 photos" wording is not present in the Pro card
      expect(proCard.text()).not.toContain('2 photos dans la galerie')
    })
  })

  // ---------------------------------------------------------------
  // Comparison Table
  // ---------------------------------------------------------------
  describe('comparison table', () => {
    it('renders the "Photos dans la galerie" row with the Pro cell showing "4" (AC #6 + #7)', () => {
      const wrapper = mountPricing()
      const rows = wrapper.findAll('table tbody tr')
      const photosRow = rows.find((tr) => tr.find('td')?.text() === 'Photos dans la galerie')
      expect(photosRow).toBeTruthy()
      const cells = photosRow!.findAll('td')
      // Cell layout: [name, decouverte, starter, pro, elite]
      expect(cells[1].text()).toBe('1')
      expect(cells[2].text()).toBe('2')
      expect(cells[3].text()).toBe('4')
      expect(cells[4].text()).toBe('6')
    })

    it('renders Check icons for true booleans and Minus icons for false (AC #7)', () => {
      const wrapper = mountPricing()
      const rows = wrapper.findAll('table tbody tr')

      // "Photo de profil" row: all four tiers true → 4 Check icons, 0 Minus icons in that row
      const photoRow = rows.find((tr) => tr.find('td')?.text() === 'Photo de profil')
      expect(photoRow).toBeTruthy()
      expect(photoRow!.findAllComponents(Check).length).toBe(4)
      expect(photoRow!.findAllComponents(Minus).length).toBe(0)

      // "Vidéo modèle UGC" row: only Élite true → 1 Check, 3 Minus
      const ugcRow = rows.find((tr) => tr.find('td')?.text() === 'Vidéo modèle UGC')
      expect(ugcRow).toBeTruthy()
      expect(ugcRow!.findAllComponents(Check).length).toBe(1)
      expect(ugcRow!.findAllComponents(Minus).length).toBe(3)
    })
  })

  // ---------------------------------------------------------------
  // FAQ accordion
  // ---------------------------------------------------------------
  describe('FAQ accordion', () => {
    it('opens the first FAQ item by default and keeps the four others closed (aria-expanded)', () => {
      const wrapper = mountPricing()
      const toggles = wrapper.findAll('[data-testid^="faq-toggle-"]')
      expect(toggles.length).toBe(5)
      expect(toggles[0].attributes('aria-expanded')).toBe('true')
      expect(toggles[1].attributes('aria-expanded')).toBe('false')
      expect(toggles[2].attributes('aria-expanded')).toBe('false')
      expect(toggles[3].attributes('aria-expanded')).toBe('false')
      expect(toggles[4].attributes('aria-expanded')).toBe('false')
    })

    it('exposes aria-controls on every FAQ toggle and an id on every FAQ panel (A11y)', () => {
      const wrapper = mountPricing()
      const toggles = wrapper.findAll('[data-testid^="faq-toggle-"]')
      const panels = wrapper.findAll('[data-testid^="faq-panel-"], [id^="faq-panel-"]')
      expect(toggles.length).toBe(5)
      // Each toggle's aria-controls must point to an existing panel id.
      for (const t of toggles) {
        const controlled = t.attributes('aria-controls')
        expect(controlled).toMatch(/^faq-panel-\d+$/)
        expect(panels.some((p) => p.attributes('id') === controlled)).toBe(true)
      }
    })

    it('marks closed FAQ panels as aria-hidden + inert (and the open one as not hidden)', () => {
      const wrapper = mountPricing()
      const panels = wrapper.findAll('[id^="faq-panel-"]')
      expect(panels.length).toBe(5)
      // FAQ #0 starts open
      expect(panels[0].attributes('aria-hidden')).toBe('false')
      expect(panels[0].attributes('inert')).toBeUndefined()
      // Others are closed → aria-hidden="true" and inert is present
      for (let i = 1; i < panels.length; i++) {
        expect(panels[i].attributes('aria-hidden')).toBe('true')
        expect(panels[i].attributes('inert')).toBeDefined()
      }
    })

    it('toggles a closed FAQ open on click, then closes it on a second click (aria-expanded flips)', async () => {
      const wrapper = mountPricing()
      const toggle = wrapper.get('[data-testid="faq-toggle-1"]')
      // Second FAQ starts closed
      expect(toggle.attributes('aria-expanded')).toBe('false')

      await toggle.trigger('click')
      expect(toggle.attributes('aria-expanded')).toBe('true')

      await toggle.trigger('click')
      expect(toggle.attributes('aria-expanded')).toBe('false')
    })

    it('closes any previously open FAQ when a different one is opened (exclusive accordion)', async () => {
      const wrapper = mountPricing()
      const t0 = wrapper.get('[data-testid="faq-toggle-0"]')
      const t1 = wrapper.get('[data-testid="faq-toggle-1"]')
      const t2 = wrapper.get('[data-testid="faq-toggle-2"]')
      // FAQ #0 starts open by default
      expect(t0.attributes('aria-expanded')).toBe('true')

      // Click FAQ #1 — FAQ #0 must auto-close
      await t1.trigger('click')
      expect(t0.attributes('aria-expanded')).toBe('false')
      expect(t1.attributes('aria-expanded')).toBe('true')

      // Click FAQ #2 — FAQ #1 must auto-close
      await t2.trigger('click')
      expect(t1.attributes('aria-expanded')).toBe('false')
      expect(t2.attributes('aria-expanded')).toBe('true')
    })

    it('FAQ #1 answer uses the no-pro-rata wording (AC #10 — Product Decision #3)', () => {
      const wrapper = mountPricing()
      const text = wrapper.text()
      expect(text).toContain('facturé au prix annuel plein')
      expect(text).toContain('jours restants')
      // Defensive — make sure the deprecated "calculé au prorata" copy is gone
      expect(text).not.toContain('au prorata')
      expect(text).not.toContain('calculé au pro')
    })

    it('FAQ #4 answer mentions the 90-day retention window (AC #11 — Product Decision #11)', () => {
      const wrapper = mountPricing()
      expect(wrapper.text()).toContain('90 jours')
    })
  })

  // ---------------------------------------------------------------
  // CTA routes
  // ---------------------------------------------------------------
  describe('CTA routes', () => {
    const tierExpected: Record<string, string> = {
      decouverte: '/register/face',
      starter: '/login?redirect=/pricing?plan=starter',
      pro: '/login?redirect=/pricing?plan=pro',
      elite: '/login?redirect=/pricing?plan=elite',
    }

    it('routes each tier card primary CTA to /register/face with the right ?plan= query (AC #12)', () => {
      const wrapper = mountPricing()
      for (const [key, expected] of Object.entries(tierExpected)) {
        const link = wrapper.findComponent(`[data-testid="cta-tier-${key}"]` as never)
        expect(link.exists()).toBe(true)
        expect(link.props('to')).toBe(expected)
      }
    })

    it('routes the comparison-table per-tier "Choisir" buttons to the same targets per tier (AC #12)', () => {
      const wrapper = mountPricing()
      for (const [key, expected] of Object.entries(tierExpected)) {
        const link = wrapper.findComponent(`[data-testid="cta-comparison-${key}"]` as never)
        expect(link.exists()).toBe(true)
        expect(link.props('to')).toBe(expected)
      }
    })

    it('routes the two footer CTAs to /register/face and /contact (AC #12)', () => {
      const wrapper = mountPricing()
      const register = wrapper.findComponent('[data-testid="cta-footer-register"]' as never)
      const contact = wrapper.findComponent('[data-testid="cta-footer-contact"]' as never)
      expect(register.exists()).toBe(true)
      expect(contact.exists()).toBe(true)
      expect(register.props('to')).toBe('/register/face')
      expect(contact.props('to')).toBe('/contact')
    })
  })

  // ---------------------------------------------------------------
  // Responsive layout
  // ---------------------------------------------------------------
  describe('responsive layout', () => {
    it('keeps the lg:grid-cols-4 class on the pricing grid container (AC #13)', () => {
      const wrapper = mountPricing()
      const grid = wrapper.get('[data-testid="pricing-grid"]')
      expect(grid.classes()).toContain('lg:grid-cols-4')
      expect(grid.classes()).toContain('grid-cols-1')
      expect(grid.classes()).toContain('sm:grid-cols-2')
    })
  })

  // ---------------------------------------------------------------
  // Tier prices — NBSP (no narrow-viewport line wrap, FR typography)
  // ---------------------------------------------------------------
  describe('tier prices', () => {
    it('renders amount prices with non-breaking space between thousands', () => {
      const wrapper = mountPricing()
      // U+00A0 NBSP between the thousand and hundred segments
      expect(wrapper.get('[data-testid="tier-card-starter"]').text()).toContain('12 000')
      expect(wrapper.get('[data-testid="tier-card-pro"]').text()).toContain('25 000')
      expect(wrapper.get('[data-testid="tier-card-elite"]').text()).toContain('40 000')
    })
  })
})

// =============================================================
// FP-2.13.1 — auth-aware behavior
// =============================================================

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

function makeOffers(): SubscriptionOffer[] {
  return (['starter', 'pro', 'elite'] as const).map((t) => ({
    tier: t,
    price: t === 'starter' ? 12000 : t === 'pro' ? 25000 : 40000,
    currency: 'FCFA',
    capabilities: CAPABILITIES[t],
  }))
}

interface StatusSetup {
  tier?: FaceSubscriptionTier
  status?: SubscriptionStatusValue
  expiresAt?: string | null
  cta?: { upgrade_available: boolean; downgrade_available: boolean; renew_available: boolean }
  isLoading?: boolean
  currentOverride?: SubscriptionCurrent | null
  offers?: SubscriptionOffer[]
}

function setupStatus(opts: StatusSetup = {}): void {
  const tier = opts.tier ?? 'free'
  const status = opts.status ?? 'free'
  const current =
    opts.currentOverride === undefined
      ? makeCurrent(tier, status, { expires_at: opts.expiresAt ?? null })
      : opts.currentOverride
  ctx.status.current = ref(current)
  ctx.status.cta = ref(
    opts.cta ??
      (status === 'active'
        ? { upgrade_available: true, downgrade_available: true, renew_available: true }
        : status === 'free'
          ? { upgrade_available: true, downgrade_available: false, renew_available: false }
          : { upgrade_available: false, downgrade_available: false, renew_available: false }),
  )
  ctx.status.tier = ref(tier)
  ctx.status.statusValue = ref(status)
  ctx.status.currentPlan = ref(current?.plan ?? null)
  ctx.status.expiresAt = ref(opts.expiresAt ?? null)
  ctx.status.offers = ref(opts.offers ?? makeOffers())
  ctx.status.isLoading = ref(opts.isLoading ?? false)
  ctx.status.fetchStatus = vi.fn().mockResolvedValue(undefined)
}

interface PaymentSetup {
  paymentState?: 'idle' | 'waiting' | 'confirmed' | 'failed'
  isInitiating?: boolean
  isPolling?: boolean
  isVerifying?: boolean
  isCancelling?: boolean
  paymentError?: string | null
  cancelPendingResult?: boolean
}

function setupPayment(opts: PaymentSetup = {}): void {
  ctx.payment.isInitiating = ref(opts.isInitiating ?? false)
  ctx.payment.isPolling = ref(opts.isPolling ?? false)
  ctx.payment.isVerifying = ref(opts.isVerifying ?? false)
  ctx.payment.isCancelling = ref(opts.isCancelling ?? false)
  ctx.payment.paymentState = ref(opts.paymentState ?? 'idle')
  ctx.payment.error = ref(opts.paymentError ?? null)
  ctx.payment.initiatePayment = vi.fn().mockResolvedValue(true)
  ctx.payment.resumePayment = vi.fn().mockResolvedValue(true)
  ctx.payment.verifyPayment = vi.fn().mockResolvedValue(undefined)
  ctx.payment.cancelPending = vi.fn().mockResolvedValue(opts.cancelPendingResult ?? true)
  ctx.payment.dismissPaymentError = vi.fn()
}

const TierChangeModalStub = {
  name: 'TierChangeModal',
  props: ['open', 'mode', 'targetOffer', 'currentTierLabel', 'forfeitedDays', 'isSubmitting'],
  emits: ['confirm', 'cancel'],
  template: `
    <div data-testid="tier-change-modal"
         :data-mode="mode"
         :data-target-tier="targetOffer?.tier"
         :data-open="open">
      <button data-testid="modal-confirm" @click="$emit('confirm')">Confirm</button>
      <button data-testid="modal-cancel" @click="$emit('cancel')">Cancel</button>
    </div>
  `,
}

function mountAuth() {
  return mount(PricingView, {
    global: {
      stubs: {
        RouterLink: RouterLinkStub,
        TierChangeModal: TierChangeModalStub,
      },
    },
  })
}

describe('auth-aware behavior (FP-2.13.1)', () => {
  beforeEach(() => {
    ctx.authStore.isAuthenticated = true
    ctx.authStore.user = { id: 42, userable_type: 'Face', email_verified: true }
    ctx.authStore.isEmailVerified = true
    ctx.route.query = {}
    setupStatus({ tier: 'pro', status: 'active', expiresAt: '2027-05-23T00:00:00Z' })
    setupPayment()
  })

  afterEach(() => {
    ctx.authStore.isAuthenticated = false
    ctx.authStore.user = null
    ctx.authStore.isEmailVerified = false
    ctx.route.query = {}
    ctx.status.current = { value: null }
    ctx.status.cta = {
      value: { upgrade_available: false, downgrade_available: false, renew_available: false },
    }
    ctx.status.tier = { value: 'free' }
    ctx.status.statusValue = { value: 'free' }
    ctx.status.currentPlan = { value: null }
    ctx.status.expiresAt = { value: null }
    ctx.status.offers = { value: [] }
    ctx.status.isLoading = { value: false }
    ctx.payment.paymentState = { value: 'idle' }
    ctx.payment.error = { value: null }
    ctx.payment.isInitiating = { value: false }
    ctx.payment.isPolling = { value: false }
    ctx.payment.isVerifying = { value: false }
    vi.clearAllMocks()
  })

  it('preserves the unauth render — no banners, RouterLink CTAs only', async () => {
    ctx.authStore.isAuthenticated = false
    ctx.authStore.user = null
    ctx.authStore.isEmailVerified = false
    const wrapper = mountAuth()
    await flushPromises()

    expect(wrapper.find('[data-testid="pricing-banners"]').exists()).toBe(false)
    for (const k of ['decouverte', 'starter', 'pro', 'elite'] as const) {
      const cta = wrapper.findComponent(`[data-testid="cta-tier-${k}"]` as never)
      expect(cta.exists()).toBe(true)
      expect(cta.props('to')).toBeDefined()
    }
  })

  it('treats Producer logged-in as unauth (no banners, no fetchStatus)', async () => {
    ctx.authStore.user = { id: 99, userable_type: 'Producer', email_verified: true }
    const wrapper = mountAuth()
    await flushPromises()

    expect(wrapper.find('[data-testid="pricing-banners"]').exists()).toBe(false)
    expect(ctx.status.fetchStatus).not.toHaveBeenCalled()
  })

  it('triggers fetchStatus on mount when a Face is logged in', async () => {
    mountAuth()
    await flushPromises()
    expect(ctx.status.fetchStatus).toHaveBeenCalled()
  })

  it('highlights the current-tier card with the teal ring (Face on Pro)', async () => {
    const wrapper = mountAuth()
    await flushPromises()

    const proCard = wrapper.get('[data-testid="tier-card-pro"]')
    expect(proCard.classes()).toContain('ring-2')
    expect(proCard.classes()).toContain('ring-[#198496]')

    for (const k of ['decouverte', 'starter', 'elite'] as const) {
      const card = wrapper.get(`[data-testid="tier-card-${k}"]`)
      expect(card.classes()).not.toContain('ring-2')
    }
  })

  it('renders "Passer à Élite" button on the Élite card (Face on Pro)', async () => {
    const wrapper = mountAuth()
    await flushPromises()
    const eliteBtn = wrapper.get('[data-testid="cta-tier-elite"]')
    expect(eliteBtn.element.tagName).toBe('BUTTON')
    expect(eliteBtn.text()).toBe('Passer à Élite')
  })

  it('renders "Revenir à Starter" button on the Starter card (Face on Pro)', async () => {
    const wrapper = mountAuth()
    await flushPromises()
    const starterBtn = wrapper.get('[data-testid="cta-tier-starter"]')
    expect(starterBtn.element.tagName).toBe('BUTTON')
    expect(starterBtn.text()).toBe('Revenir à Starter')
  })

  it('renders "Renouveler Pro" button when renew_available is true', async () => {
    setupStatus({
      tier: 'pro',
      status: 'active',
      expiresAt: '2027-05-23T00:00:00Z',
      cta: { upgrade_available: true, downgrade_available: true, renew_available: true },
    })
    const wrapper = mountAuth()
    await flushPromises()
    const proBtn = wrapper.get('[data-testid="cta-tier-pro"]')
    expect(proBtn.element.tagName).toBe('BUTTON')
    expect(proBtn.text()).toBe('Renouveler Pro')
  })

  it('renders "Palier actuel" marker when renew_available is false', async () => {
    setupStatus({
      tier: 'pro',
      status: 'active',
      expiresAt: '2027-05-23T00:00:00Z',
      cta: { upgrade_available: true, downgrade_available: true, renew_available: false },
    })
    const wrapper = mountAuth()
    await flushPromises()
    expect(wrapper.find('[data-testid="cta-tier-pro"]').exists()).toBe(false)
    const marker = wrapper.get('[data-testid="cta-tier-pro-marker"]')
    expect(marker.text()).toBe('Palier actuel')
    expect(marker.element.tagName).toBe('P')
  })

  it('renders "Non disponible" marker on the Découverte card when user is on a paid tier', async () => {
    const wrapper = mountAuth()
    await flushPromises()
    expect(wrapper.find('[data-testid="cta-tier-decouverte"]').exists()).toBe(false)
    const marker = wrapper.get('[data-testid="cta-tier-decouverte-marker"]')
    expect(marker.text()).toBe('Non disponible')
  })

  it('renders Free-user CTAs as "Passer à {Name}" with "Palier actuel" on Découverte', async () => {
    setupStatus({
      tier: 'free',
      status: 'free',
      cta: { upgrade_available: true, downgrade_available: false, renew_available: false },
    })
    const wrapper = mountAuth()
    await flushPromises()

    const decouverteMarker = wrapper.get('[data-testid="cta-tier-decouverte-marker"]')
    expect(decouverteMarker.text()).toBe('Palier actuel')

    expect(wrapper.get('[data-testid="cta-tier-starter"]').text()).toBe('Passer à Starter')
    expect(wrapper.get('[data-testid="cta-tier-pro"]').text()).toBe('Passer à Pro')
    expect(wrapper.get('[data-testid="cta-tier-elite"]').text()).toBe('Passer à Élite')
  })

  it('opens TierChangeModal in upgrade mode when clicking the Élite CTA (Face on Pro)', async () => {
    const wrapper = mountAuth()
    await flushPromises()
    await wrapper.get('[data-testid="cta-tier-elite"]').trigger('click')
    await flushPromises()

    const modal = wrapper.get('[data-testid="tier-change-modal"]')
    expect(modal.attributes('data-mode')).toBe('upgrade')
    expect(modal.attributes('data-target-tier')).toBe('elite')
  })

  it('calls initiatePayment with the targeted plan when the modal confirms', async () => {
    const wrapper = mountAuth()
    await flushPromises()
    await wrapper.get('[data-testid="cta-tier-elite"]').trigger('click')
    await flushPromises()
    await wrapper.get('[data-testid="modal-confirm"]').trigger('click')
    await flushPromises()

    expect(ctx.payment.initiatePayment).toHaveBeenCalledWith('elite')
    expect(ctx.payment.initiatePayment).toHaveBeenCalledTimes(1)
  })

  it('disables all tier CTAs and shows the email-not-verified note when email is unverified', async () => {
    ctx.authStore.isEmailVerified = false
    if (ctx.authStore.user) ctx.authStore.user.email_verified = false
    const wrapper = mountAuth()
    await flushPromises()

    expect(wrapper.find('[data-testid="pricing-email-note"]').exists()).toBe(true)
    for (const k of ['starter', 'elite'] as const) {
      const btn = wrapper.get(`[data-testid="cta-tier-${k}"]`)
      expect(btn.attributes('disabled')).toBeDefined()
    }
  })

  it('renders BOTH "Continuer le paiement" and "Vérifier maintenant" when the pending banner is visible', async () => {
    setupStatus({
      tier: 'pro',
      status: 'pending_payment',
      cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
    })
    setupPayment()
    const wrapper = mountAuth()
    await flushPromises()

    expect(wrapper.find('[data-testid="pricing-banner-pending"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="pricing-banner-resume"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="pricing-banner-verify"]').exists()).toBe(true)
  })

  it('shows the waiting banner with precedence over the pending banner', async () => {
    setupStatus({
      tier: 'pro',
      status: 'pending_payment',
      cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
    })
    setupPayment({ paymentState: 'waiting' })
    const wrapper = mountAuth()
    await flushPromises()

    expect(wrapper.find('[data-testid="pricing-banner-waiting"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="pricing-banner-pending"]').exists()).toBe(false)
  })

  it('auto-opens the modal for ?plan=pro when a Face on Starter lands on /pricing', async () => {
    setupStatus({ tier: 'starter', status: 'active', expiresAt: '2027-05-23T00:00:00Z' })
    ctx.route.query = { plan: 'pro' }
    const wrapper = mountAuth()
    await flushPromises()

    const modal = wrapper.get('[data-testid="tier-change-modal"]')
    expect(modal.attributes('data-target-tier')).toBe('pro')
    expect(modal.attributes('data-mode')).toBe('upgrade')
  })

  it("skips deep-link auto-open when ?plan= matches the user's current tier", async () => {
    ctx.route.query = { plan: 'pro' } // Face on Pro from beforeEach
    const wrapper = mountAuth()
    await flushPromises()
    expect(wrapper.find('[data-testid="tier-change-modal"]').exists()).toBe(false)
  })

  it('silently ignores an invalid ?plan= value (no modal opens)', async () => {
    ctx.route.query = { plan: 'invalid' }
    const wrapper = mountAuth()
    await flushPromises()
    expect(wrapper.find('[data-testid="tier-change-modal"]').exists()).toBe(false)
  })

  it('emits a success toast and closes the modal when paymentState becomes "confirmed"', async () => {
    const wrapper = mountAuth()
    await flushPromises()

    await wrapper.get('[data-testid="cta-tier-elite"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-testid="tier-change-modal"]').exists()).toBe(true)

    ;(ctx.payment.paymentState as { value: string }).value = 'confirmed'
    await flushPromises()

    expect(ctx.toast.success).toHaveBeenCalledWith(
      'Paiement confirmé — votre abonnement est actif.',
    )
    expect(wrapper.find('[data-testid="tier-change-modal"]').exists()).toBe(false)
  })

  it('closes the modal and clears modalTarget when the user cancels (AC #9)', async () => {
    const wrapper = mountAuth()
    await flushPromises()

    await wrapper.get('[data-testid="cta-tier-elite"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-testid="tier-change-modal"]').exists()).toBe(true)

    await wrapper.get('[data-testid="modal-cancel"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="tier-change-modal"]').exists()).toBe(false)
    expect(ctx.payment.initiatePayment).not.toHaveBeenCalled()
  })
})

describe('Cancel-pending action (FP-2.15.1)', () => {
  beforeEach(() => {
    ctx.authStore.isAuthenticated = true
    ctx.authStore.user = { id: 42, userable_type: 'Face', email_verified: true }
    ctx.authStore.isEmailVerified = true
    ctx.route.query = {}
    setupStatus({
      tier: 'pro',
      status: 'active',
      expiresAt: '2027-05-23T00:00:00Z',
      cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
    })
    setupPayment()
  })

  afterEach(() => {
    ctx.authStore.isAuthenticated = false
    ctx.authStore.user = null
    ctx.authStore.isEmailVerified = false
    vi.clearAllMocks()
  })

  it('T1 — renders "Annuler le paiement" button (testid pricing-banner-cancel) when hasPendingPayment is true', async () => {
    const wrapper = mountAuth()
    await flushPromises()

    expect(wrapper.find('[data-testid="pricing-banner-pending"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="pricing-banner-resume"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="pricing-banner-verify"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="pricing-banner-cancel"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="pricing-banner-cancel"]').text()).toBe('Annuler le paiement')
  })

  it('T2 — clicking "Annuler le paiement" opens the ConfirmModal', async () => {
    const wrapper = mountAuth()
    await flushPromises()

    const { default: ConfirmModal } = await import('@/components/ui/ConfirmModal.vue')
    expect(wrapper.findComponent(ConfirmModal).props('isOpen')).toBe(false)

    await wrapper.find('[data-testid="pricing-banner-cancel"]').trigger('click')
    await flushPromises()

    const modal = wrapper.findComponent(ConfirmModal)
    expect(modal.props('isOpen')).toBe(true)
    expect(modal.props('title')).toBe('Annuler le paiement en cours ?')
    expect(modal.props('variant')).toBe('warning')
  })

  it('T3 — confirming the modal calls cancelPending and toasts "Paiement annulé." on success', async () => {
    const wrapper = mountAuth()
    await flushPromises()

    await wrapper.find('[data-testid="pricing-banner-cancel"]').trigger('click')
    await flushPromises()

    const { default: ConfirmModal } = await import('@/components/ui/ConfirmModal.vue')
    const modal = wrapper.findComponent(ConfirmModal)
    modal.vm.$emit('confirm')
    await flushPromises()

    expect(ctx.payment.cancelPending).toHaveBeenCalledOnce()
    expect(ctx.toast.success).toHaveBeenCalledWith('Paiement annulé.')
    expect(wrapper.findComponent(ConfirmModal).props('isOpen')).toBe(false)
  })

  it('T4 — confirming when cancelPending returns false does NOT toast success', async () => {
    setupPayment({
      paymentError: 'Aucun paiement en cours à annuler.',
      cancelPendingResult: false,
    })
    const wrapper = mountAuth()
    await flushPromises()

    await wrapper.find('[data-testid="pricing-banner-cancel"]').trigger('click')
    await flushPromises()

    const { default: ConfirmModal } = await import('@/components/ui/ConfirmModal.vue')
    const modal = wrapper.findComponent(ConfirmModal)
    modal.vm.$emit('confirm')
    await flushPromises()

    expect(ctx.payment.cancelPending).toHaveBeenCalledOnce()
    expect(ctx.toast.success).not.toHaveBeenCalledWith('Paiement annulé.')
    expect(
      wrapper.find('[data-testid="pricing-banner-pending-error"]').exists(),
    ).toBe(true)
    expect(
      wrapper.get('[data-testid="pricing-banner-pending-error"]').text(),
    ).toContain('Aucun paiement en cours')
  })

  it('T5 — waiting banner renders "Annuler le paiement" button and clicking it opens the ConfirmModal (FP-2.15.1 L2)', async () => {
    setupPayment({ paymentState: 'waiting' })
    const wrapper = mountAuth()
    await flushPromises()

    expect(wrapper.find('[data-testid="pricing-banner-waiting"]').exists()).toBe(true)
    // Pending banner is suppressed by the cascade while waiting takes precedence.
    expect(wrapper.find('[data-testid="pricing-banner-pending"]').exists()).toBe(false)

    const cancelBtn = wrapper.find('[data-testid="pricing-banner-waiting-cancel"]')
    expect(cancelBtn.exists()).toBe(true)
    expect(cancelBtn.text()).toBe('Annuler le paiement')

    const { default: ConfirmModal } = await import('@/components/ui/ConfirmModal.vue')
    expect(wrapper.findComponent(ConfirmModal).props('isOpen')).toBe(false)

    await cancelBtn.trigger('click')
    await flushPromises()

    expect(wrapper.findComponent(ConfirmModal).props('isOpen')).toBe(true)
  })

  it('T6 — resume button is visible inside the pending banner without any local stash predicate (FP-2.15.2)', async () => {
    // Pending banner visible → resume button visible (no stash predicate gates it).
    const wrapperPending = mountAuth()
    await flushPromises()

    expect(wrapperPending.find('[data-testid="pricing-banner-pending"]').exists()).toBe(true)
    expect(wrapperPending.find('[data-testid="pricing-banner-resume"]').exists()).toBe(true)

    // Pending banner NOT visible (active + cta enabled) → resume button NOT rendered.
    setupStatus({
      tier: 'pro',
      status: 'active',
      expiresAt: '2027-05-23T00:00:00Z',
      cta: { upgrade_available: true, downgrade_available: false, renew_available: true },
    })
    setupPayment()
    const wrapperActive = mountAuth()
    await flushPromises()

    expect(wrapperActive.find('[data-testid="pricing-banner-pending"]').exists()).toBe(false)
    expect(wrapperActive.find('[data-testid="pricing-banner-resume"]').exists()).toBe(false)
  })
})
