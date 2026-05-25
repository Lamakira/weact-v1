import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { nextTick, ref } from 'vue'
import type {
  AdminFaceSubscription,
  AdminSubscriptionStatus,
} from '@/features/admin/services/adminFaceSubscriptionsApi'
import AdminFaceSubscriptionSection from '../AdminFaceSubscriptionSection.vue'

const mockFetchSubscriptions = vi.fn()
const mockAbortRequests = vi.fn()
const mockActivate = vi.fn()
const mockExtend = vi.fn()
const mockCancel = vi.fn()
const mockCorrect = vi.fn()
const mockChangeTier = vi.fn()
const mockToastSuccess = vi.fn()
const mockToastError = vi.fn()

let subscriptionsRef = ref<AdminFaceSubscription[]>([])
let faceDisplayRef = ref<{ id: string; display_name: string } | null>(null)
let isLoadingRef = ref(false)
let errorRef = ref<string | null>(null)

vi.mock('@/features/admin/composables/useAdminFaceSubscriptions', () => ({
  useAdminFaceSubscriptions: () => ({
    subscriptions: subscriptionsRef,
    faceDisplay: faceDisplayRef,
    isLoading: isLoadingRef,
    error: errorRef,
    fetchSubscriptions: mockFetchSubscriptions,
    abortRequests: mockAbortRequests,
    activate: mockActivate,
    extend: mockExtend,
    cancel: mockCancel,
    correct: mockCorrect,
    changeTier: mockChangeTier,
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: mockToastSuccess,
    error: mockToastError,
    warning: vi.fn(),
    info: vi.fn(),
  }),
}))

const FACE_ID = 'face-uuid-1'

function makeSubscription(
  overrides: Partial<AdminFaceSubscription> = {},
): AdminFaceSubscription {
  return {
    id: 'sub-uuid-1',
    plan: 'pro',
    plan_label: 'Pro',
    status: 'active',
    status_label: 'Active',
    starts_at: '2026-01-01T00:00:00+00:00',
    expires_at: '2027-01-01T00:00:00+00:00',
    cancelled_at: null,
    paid_amount: 50000,
    currency: 'XOF',
    created_at: '2026-01-01T00:00:00+00:00',
    updated_at: '2026-01-01T00:00:00+00:00',
    audits: [],
    ...overrides,
  }
}

async function mountSection(
  initialSubscriptions: AdminFaceSubscription[] = [],
): Promise<VueWrapper> {
  subscriptionsRef.value = initialSubscriptions
  faceDisplayRef.value = { id: FACE_ID, display_name: 'Jane Doe' }
  isLoadingRef.value = false
  errorRef.value = null

  mockFetchSubscriptions.mockResolvedValue(undefined)

  const wrapper = mount(AdminFaceSubscriptionSection, {
    props: { faceId: FACE_ID },
    attachTo: document.body,
  })

  await flushPromises()
  return wrapper
}

describe('AdminFaceSubscriptionSection', () => {
  beforeEach(() => {
    subscriptionsRef = ref<AdminFaceSubscription[]>([])
    faceDisplayRef = ref<{ id: string; display_name: string } | null>(null)
    isLoadingRef = ref(false)
    errorRef = ref<string | null>(null)
    vi.clearAllMocks()
    document.body.innerHTML = ''
  })

  it('renders the empty state when subscriptions array is empty', async () => {
    const wrapper = await mountSection([])
    expect(wrapper.find('[data-testid="admin-face-subscription-empty"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="admin-face-subscription-list"]').exists()).toBe(false)
    expect(mockFetchSubscriptions).toHaveBeenCalledWith(FACE_ID)
  })

  it('renders one card per subscription, ordered as returned by the API', async () => {
    const subs: AdminFaceSubscription[] = [
      makeSubscription({ id: 'sub-1', status: 'active', status_label: 'Active' }),
      makeSubscription({ id: 'sub-2', status: 'expired', status_label: 'Expirée' }),
      makeSubscription({ id: 'sub-3', status: 'cancelled', status_label: 'Annulée' }),
    ]
    const wrapper = await mountSection(subs)

    expect(wrapper.find('[data-testid="subscription-card-sub-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="subscription-card-sub-2"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="subscription-card-sub-3"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid^="subscription-card-"]')).toHaveLength(3)
  })

  it('renders a zero audit count for subscriptions without audits', async () => {
    const wrapper = await mountSection([makeSubscription({ id: 'sub-no-audit', audits: [] })])

    expect(wrapper.find('[data-testid="audit-count-sub-no-audit"]').text()).toBe('0 opération')
  })

  it('does not crash when a subscription has an invalid currency code', async () => {
    const wrapper = await mountSection([
      makeSubscription({ id: 'sub-bad-currency', paid_amount: 50000, currency: 'FCFA' }),
    ])

    expect(wrapper.find('[data-testid="subscription-card-sub-bad-currency"]').text()).toContain(
      'FCFA',
    )
    expect(wrapper.find('[data-testid="subscription-card-sub-bad-currency"]').text()).toContain(
      '50',
    )
  })

  it('shows the Extend button only on active, unexpired cards', async () => {
    const futureExpires = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString()
    const subs: AdminFaceSubscription[] = [
      makeSubscription({ id: 'sub-active', status: 'active', expires_at: futureExpires }),
      makeSubscription({ id: 'sub-expired', status: 'expired', status_label: 'Expirée' }),
      makeSubscription({ id: 'sub-cancelled', status: 'cancelled', status_label: 'Annulée' }),
    ]
    const wrapper = await mountSection(subs)

    expect(wrapper.findAll('[data-testid="extend-button"]')).toHaveLength(1)
    expect(
      wrapper.find('[data-testid="extend-button"][data-subscription-id="sub-active"]').exists(),
    ).toBe(true)
  })

  it('shows the Cancel button only on active or pending_payment cards', async () => {
    const futureExpires = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString()
    const states: Array<{ id: string; status: AdminSubscriptionStatus }> = [
      { id: 'sub-active', status: 'active' },
      { id: 'sub-pending', status: 'pending_payment' },
      { id: 'sub-expired', status: 'expired' },
      { id: 'sub-cancelled', status: 'cancelled' },
      { id: 'sub-failed', status: 'failed' },
    ]
    const subs = states.map((s) =>
      makeSubscription({
        id: s.id,
        status: s.status,
        expires_at: s.status === 'active' ? futureExpires : null,
      }),
    )
    const wrapper = await mountSection(subs)

    expect(wrapper.findAll('[data-testid="cancel-button"]')).toHaveLength(2)
    expect(
      wrapper.find('[data-testid="cancel-button"][data-subscription-id="sub-active"]').exists(),
    ).toBe(true)
    expect(
      wrapper.find('[data-testid="cancel-button"][data-subscription-id="sub-pending"]').exists(),
    ).toBe(true)
  })

  it('activate modal sends the correct payload on submit', async () => {
    mockActivate.mockResolvedValue({ success: true, message: 'Abonnement activé manuellement' })

    const wrapper = await mountSection([])
    await wrapper.find('[data-testid="open-activate-button"]').trigger('click')
    await flushPromises()

    const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="activate-notes"]')!
    const startsAt = document.querySelector<HTMLInputElement>('[data-testid="activate-starts-at"]')!
    const durationDays = document.querySelector<HTMLInputElement>(
      '[data-testid="activate-duration-days"]',
    )!
    notes.value = 'Activation manuelle de test'
    notes.dispatchEvent(new Event('input'))
    startsAt.value = '2026-05-01'
    startsAt.dispatchEvent(new Event('input'))
    durationDays.value = '180'
    durationDays.dispatchEvent(new Event('input'))

    await flushPromises()

    document
      .querySelector<HTMLButtonElement>('[data-testid="activate-submit"]')!
      .click()
    await flushPromises()

    expect(mockActivate).toHaveBeenCalledWith(FACE_ID, {
      plan: 'starter',
      notes: 'Activation manuelle de test',
      starts_at: '2026-05-01',
      duration_days: 180,
    })
    expect(mockFetchSubscriptions).toHaveBeenCalledTimes(2)
    expect(mockToastSuccess).toHaveBeenCalledWith('Abonnement activé manuellement')
  })

  it('prevents duplicate activate submits while the request is in flight', async () => {
    let resolveActivate: (value: unknown) => void
    mockActivate.mockReturnValue(
      new Promise((resolve) => {
        resolveActivate = resolve
      }),
    )

    const wrapper = await mountSection([])
    await wrapper.find('[data-testid="open-activate-button"]').trigger('click')
    await flushPromises()

    const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="activate-notes"]')!
    notes.value = 'Activation manuelle de test'
    notes.dispatchEvent(new Event('input'))
    await flushPromises()

    const submitButton = document.querySelector<HTMLButtonElement>(
      '[data-testid="activate-submit"]',
    )!
    submitButton.click()
    await nextTick()

    expect(submitButton.disabled).toBe(true)

    submitButton.click()

    expect(mockActivate).toHaveBeenCalledTimes(1)

    resolveActivate!({ success: true, message: 'Abonnement activé manuellement' })
    await flushPromises()
  })

  it('activate modal surfaces ALREADY_ACTIVE conflict and keeps modal open', async () => {
    mockActivate.mockResolvedValue({
      success: false,
      code: 'ALREADY_ACTIVE',
      message:
        'Cette Face a déjà un abonnement actif. Utilisez « Étendre » pour prolonger la période en cours.',
      errors: {},
    })

    const wrapper = await mountSection([])
    await wrapper.find('[data-testid="open-activate-button"]').trigger('click')
    await flushPromises()

    const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="activate-notes"]')!
    notes.value = 'Tentative de réactivation'
    notes.dispatchEvent(new Event('input'))
    await flushPromises()

    document.querySelector<HTMLButtonElement>('[data-testid="activate-submit"]')!.click()
    await flushPromises()

    const errorEl = document.querySelector('[data-testid="admin-face-subscription-activate-error"]')
    expect(errorEl).not.toBeNull()
    expect(errorEl!.textContent).toContain('déjà un abonnement actif')

    // Modal stays open
    expect(document.querySelector('[data-testid="admin-face-subscription-activate-modal"]')).not.toBeNull()

    // Background refresh
    expect(mockFetchSubscriptions).toHaveBeenCalledTimes(2)
    expect(mockToastSuccess).not.toHaveBeenCalled()
  })

  it('activate modal surfaces per-field validation errors', async () => {
    mockActivate.mockResolvedValue({
      success: false,
      code: 'VALIDATION_ERROR',
      message: 'Les données fournies ne sont pas valides',
      errors: {
        notes: ['Le champ notes doit contenir au moins 5 caractères.'],
      },
    })

    const wrapper = await mountSection([])
    await wrapper.find('[data-testid="open-activate-button"]').trigger('click')
    await flushPromises()

    const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="activate-notes"]')!
    notes.value = 'abc'
    notes.dispatchEvent(new Event('input'))
    await flushPromises()

    document.querySelector<HTMLButtonElement>('[data-testid="activate-submit"]')!.click()
    await flushPromises()

    const fieldErr = document.querySelector(
      '[data-testid="admin-face-subscription-activate-field-error-notes"]',
    )
    expect(fieldErr).not.toBeNull()
    expect(fieldErr!.textContent).toContain('au moins 5 caractères')
  })

  it('extend modal sends the correct payload on submit', async () => {
    mockExtend.mockResolvedValue({ success: true, message: 'Abonnement étendu' })

    const futureExpires = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString()
    const subs = [
      makeSubscription({ id: 'sub-active', status: 'active', expires_at: futureExpires }),
    ]
    const wrapper = await mountSection(subs)

    await wrapper.find('[data-testid="extend-button"][data-subscription-id="sub-active"]').trigger('click')
    await flushPromises()

    const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="extend-notes"]')!
    const days = document.querySelector<HTMLInputElement>(
      '[data-testid="extend-additional-days"]',
    )!
    notes.value = 'Extension demandée par support'
    notes.dispatchEvent(new Event('input'))
    days.value = '60'
    days.dispatchEvent(new Event('input'))
    await flushPromises()

    document.querySelector<HTMLButtonElement>('[data-testid="extend-submit"]')!.click()
    await flushPromises()

    expect(mockExtend).toHaveBeenCalledWith('sub-active', {
      notes: 'Extension demandée par support',
      additional_days: 60,
    })
    expect(mockFetchSubscriptions).toHaveBeenCalledTimes(2)
    expect(mockToastSuccess).toHaveBeenCalledWith('Abonnement étendu')
  })

  it('blocks invalid extend day values before calling the API', async () => {
    const futureExpires = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString()
    const wrapper = await mountSection([
      makeSubscription({ id: 'sub-active', status: 'active', expires_at: futureExpires }),
    ])

    await wrapper.find('[data-testid="extend-button"][data-subscription-id="sub-active"]').trigger('click')
    await flushPromises()

    const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="extend-notes"]')!
    const days = document.querySelector<HTMLInputElement>(
      '[data-testid="extend-additional-days"]',
    )!
    notes.value = 'Extension demandée par support'
    notes.dispatchEvent(new Event('input'))
    days.value = '0'
    days.dispatchEvent(new Event('input'))
    await flushPromises()

    document.querySelector<HTMLButtonElement>('[data-testid="extend-submit"]')!.click()
    await flushPromises()

    expect(mockExtend).not.toHaveBeenCalled()
    expect(
      document.querySelector('[data-testid="admin-face-subscription-extend-field-error-additional_days"]')
        ?.textContent,
    ).toContain('nombre entier entre 1 et 3650')
  })

  it('cancel modal sends the correct payload on submit', async () => {
    mockCancel.mockResolvedValue({ success: true, message: 'Abonnement annulé' })

    const futureExpires = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString()
    const subs = [
      makeSubscription({ id: 'sub-active', status: 'active', expires_at: futureExpires }),
    ]
    const wrapper = await mountSection(subs)

    await wrapper.find('[data-testid="cancel-button"][data-subscription-id="sub-active"]').trigger('click')
    await flushPromises()

    const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="cancel-notes"]')!
    notes.value = 'Annulation à la demande du client.'
    notes.dispatchEvent(new Event('input'))
    await flushPromises()

    document.querySelector<HTMLButtonElement>('[data-testid="cancel-submit"]')!.click()
    await flushPromises()

    expect(mockCancel).toHaveBeenCalledWith('sub-active', {
      notes: 'Annulation à la demande du client.',
    })
    expect(mockFetchSubscriptions).toHaveBeenCalledTimes(2)
    expect(mockToastSuccess).toHaveBeenCalledWith('Abonnement annulé')
  })

  async function openCorrectModal(): Promise<void> {
    const subs = [
      makeSubscription({
        id: 'sub-active',
        status: 'active',
        starts_at: '2026-01-01T00:00:00+00:00',
        expires_at: '2027-01-01T00:00:00+00:00',
      }),
    ]
    const wrapper = await mountSection(subs)

    await wrapper.find('[data-testid="correct-button-sub-active"]').trigger('click')
    await flushPromises()
  }

  it('correct modal blocks submission when both dates are unchanged', async () => {
    await openCorrectModal()

    expect(document.body.textContent).toContain('Doit être entre 2020-01-01 et +10 ans.')
    expect(document.body.textContent).toContain(
      'Doit être entre 2020-01-01 et +10 ans, et postérieure à starts_at.',
    )

    // First attempt: both dates unchanged → client-side error, no API call.
    const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="correct-notes"]')!
    notes.value = 'Notes valides pour la correction.'
    notes.dispatchEvent(new Event('input'))
    await flushPromises()

    document.querySelector<HTMLButtonElement>('[data-testid="correct-submit"]')!.click()
    await flushPromises()

    expect(mockCorrect).not.toHaveBeenCalled()
    const clientErr = document.querySelector(
      '[data-testid="admin-face-subscription-correct-client-error"]',
    )
    expect(clientErr).not.toBeNull()
    expect(clientErr!.textContent).toContain('Modifiez au moins une des deux dates')
  })

  it('correct modal includes only changed dates in the payload', async () => {
    mockCorrect.mockResolvedValue({ success: true, message: 'Dates corrigées' })
    await openCorrectModal()

    const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="correct-notes"]')!
    notes.value = 'Notes valides pour la correction.'
    notes.dispatchEvent(new Event('input'))
    await flushPromises()

    const expiresAt = document.querySelector<HTMLInputElement>(
      '[data-testid="correct-expires-at"]',
    )!
    expiresAt.value = '2027-06-01'
    expiresAt.dispatchEvent(new Event('input'))
    await flushPromises()

    document.querySelector<HTMLButtonElement>('[data-testid="correct-submit"]')!.click()
    await flushPromises()

    expect(mockCorrect).toHaveBeenCalledTimes(1)
    expect(mockCorrect).toHaveBeenCalledWith('sub-active', {
      notes: 'Notes valides pour la correction.',
      expires_at: '2027-06-01',
    })
    expect(mockFetchSubscriptions).toHaveBeenCalledTimes(2)
    expect(mockToastSuccess).toHaveBeenCalledWith('Dates corrigées')
  })
})

describe('AdminFaceSubscriptionSection — FP-2.10 tier selector + change-tier flow', () => {
  beforeEach(() => {
    subscriptionsRef = ref<AdminFaceSubscription[]>([])
    faceDisplayRef = ref<{ id: string; display_name: string } | null>(null)
    isLoadingRef = ref(false)
    errorRef = ref<string | null>(null)
    vi.clearAllMocks()
    document.body.innerHTML = ''
  })

  // ---------- Activate modal tier select (6 tests) ----------

  describe('activate modal tier select', () => {
    it('pre-selects Starter by default when the modal opens', async () => {
      const wrapper = await mountSection([])
      await wrapper.find('[data-testid="open-activate-button"]').trigger('click')
      await flushPromises()

      const select = document.querySelector<HTMLSelectElement>(
        '[data-testid="activate-plan"]',
      )!
      expect(select).not.toBeNull()
      expect(select.value).toBe('starter')
    })

    it('renders Starter, Pro, and Élite options in that order', async () => {
      const wrapper = await mountSection([])
      await wrapper.find('[data-testid="open-activate-button"]').trigger('click')
      await flushPromises()

      const options = Array.from(
        document.querySelectorAll<HTMLOptionElement>(
          '[data-testid="activate-plan"] option',
        ),
      )
      expect(options.map((o) => o.value)).toEqual(['starter', 'pro', 'elite'])
      expect(options.map((o) => o.textContent?.trim())).toEqual(['Starter', 'Pro', 'Élite'])
    })

    it('sends the selected plan (Pro) in the activate payload', async () => {
      mockActivate.mockResolvedValue({ success: true, message: 'Abonnement activé manuellement' })

      const wrapper = await mountSection([])
      await wrapper.find('[data-testid="open-activate-button"]').trigger('click')
      await flushPromises()

      const select = document.querySelector<HTMLSelectElement>('[data-testid="activate-plan"]')!
      select.value = 'pro'
      select.dispatchEvent(new Event('change'))
      const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="activate-notes"]')!
      notes.value = 'Activation Pro pour test'
      notes.dispatchEvent(new Event('input'))
      await flushPromises()

      document.querySelector<HTMLButtonElement>('[data-testid="activate-submit"]')!.click()
      await flushPromises()

      expect(mockActivate).toHaveBeenCalledWith(
        FACE_ID,
        expect.objectContaining({ plan: 'pro', notes: 'Activation Pro pour test' }),
      )
    })

    it('sends the selected plan (Élite) in the activate payload', async () => {
      mockActivate.mockResolvedValue({ success: true, message: 'Abonnement activé manuellement' })

      const wrapper = await mountSection([])
      await wrapper.find('[data-testid="open-activate-button"]').trigger('click')
      await flushPromises()

      const select = document.querySelector<HTMLSelectElement>('[data-testid="activate-plan"]')!
      select.value = 'elite'
      select.dispatchEvent(new Event('change'))
      const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="activate-notes"]')!
      notes.value = 'Activation Élite pour test'
      notes.dispatchEvent(new Event('input'))
      await flushPromises()

      document.querySelector<HTMLButtonElement>('[data-testid="activate-submit"]')!.click()
      await flushPromises()

      expect(mockActivate).toHaveBeenCalledWith(
        FACE_ID,
        expect.objectContaining({ plan: 'elite', notes: 'Activation Élite pour test' }),
      )
    })

    it('surfaces a 422 field error on plan', async () => {
      mockActivate.mockResolvedValue({
        success: false,
        code: 'VALIDATION_ERROR',
        message: 'Les données fournies ne sont pas valides',
        errors: { plan: ['Le champ palier sélectionné est invalide.'] },
      })

      const wrapper = await mountSection([])
      await wrapper.find('[data-testid="open-activate-button"]').trigger('click')
      await flushPromises()

      const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="activate-notes"]')!
      notes.value = 'Tentative invalide'
      notes.dispatchEvent(new Event('input'))
      await flushPromises()

      document.querySelector<HTMLButtonElement>('[data-testid="activate-submit"]')!.click()
      await flushPromises()

      const fieldErr = document.querySelector(
        '[data-testid="admin-face-subscription-activate-field-error-plan"]',
      )
      expect(fieldErr).not.toBeNull()
      expect(fieldErr!.textContent).toContain('palier')
    })

    it('resets activatePlan to starter when the modal is re-opened', async () => {
      const wrapper = await mountSection([])
      await wrapper.find('[data-testid="open-activate-button"]').trigger('click')
      await flushPromises()

      let select = document.querySelector<HTMLSelectElement>('[data-testid="activate-plan"]')!
      select.value = 'elite'
      select.dispatchEvent(new Event('change'))
      await flushPromises()
      expect(select.value).toBe('elite')

      // Close via Annuler footer button (the first one in the modal footer)
      const cancelBtn = Array.from(
        document.querySelectorAll<HTMLButtonElement>(
          '[data-testid="admin-face-subscription-activate-modal"] button',
        ),
      ).find((b) => b.textContent?.trim() === 'Annuler')!
      cancelBtn.click()
      await flushPromises()

      await wrapper.find('[data-testid="open-activate-button"]').trigger('click')
      await flushPromises()

      select = document.querySelector<HTMLSelectElement>('[data-testid="activate-plan"]')!
      expect(select.value).toBe('starter')
    })
  })

  // ---------- Change-tier button visibility (3 tests) ----------

  describe('change-tier button visibility', () => {
    it('renders a Changer de palier button on each active+unexpired card', async () => {
      const futureExpires = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString()
      const subs: AdminFaceSubscription[] = [
        makeSubscription({ id: 'sub-active', status: 'active', expires_at: futureExpires }),
        makeSubscription({ id: 'sub-expired', status: 'expired', status_label: 'Expirée' }),
        makeSubscription({ id: 'sub-cancelled', status: 'cancelled', status_label: 'Annulée' }),
      ]
      const wrapper = await mountSection(subs)

      expect(wrapper.findAll('[data-testid="change-tier-button"]')).toHaveLength(1)
      expect(
        wrapper
          .find('[data-testid="change-tier-button"][data-subscription-id="sub-active"]')
          .exists(),
      ).toBe(true)
    })

    it('does not render the button on expired / cancelled / failed / pending_payment cards', async () => {
      const states: Array<{ id: string; status: AdminSubscriptionStatus }> = [
        { id: 'sub-pending', status: 'pending_payment' },
        { id: 'sub-expired', status: 'expired' },
        { id: 'sub-cancelled', status: 'cancelled' },
        { id: 'sub-failed', status: 'failed' },
      ]
      const subs = states.map((s) =>
        makeSubscription({ id: s.id, status: s.status, expires_at: null }),
      )
      const wrapper = await mountSection(subs)

      expect(wrapper.findAll('[data-testid="change-tier-button"]')).toHaveLength(0)
    })

    it('does not render the button on zombie active subscriptions (expires_at in the past)', async () => {
      const pastExpires = new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString()
      const wrapper = await mountSection([
        makeSubscription({ id: 'sub-zombie', status: 'active', expires_at: pastExpires }),
      ])

      expect(wrapper.findAll('[data-testid="change-tier-button"]')).toHaveLength(0)
    })
  })

  // ---------- Change-tier modal (14 tests) ----------

  describe('change-tier modal', () => {
    const futureExpires = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString()

    async function openChangeTierModal(
      overrides: Partial<AdminFaceSubscription> = {},
    ): Promise<ReturnType<typeof mountSection> extends Promise<infer W> ? W : never> {
      const sub = makeSubscription({
        id: 'sub-active',
        status: 'active',
        expires_at: futureExpires,
        plan: 'pro',
        plan_label: 'Pro',
        ...overrides,
      })
      const wrapper = await mountSection([sub])
      await wrapper
        .find('[data-testid="change-tier-button"][data-subscription-id="sub-active"]')
        .trigger('click')
      await flushPromises()
      return wrapper
    }

    it('opens with the current tier shown as a readonly label', async () => {
      await openChangeTierModal({ plan: 'pro', plan_label: 'Pro' })
      const current = document.querySelector('[data-testid="change-tier-current"]')
      expect(current).not.toBeNull()
      expect(current!.textContent).toContain('Pro')
    })

    it('tier select excludes the current tier', async () => {
      await openChangeTierModal({ plan: 'pro', plan_label: 'Pro' })
      const options = Array.from(
        document.querySelectorAll<HTMLOptionElement>(
          '[data-testid="change-tier-new-plan"] option',
        ),
      )
      expect(options.map((o) => o.value)).toEqual(['starter', 'elite'])
      expect(options.map((o) => o.textContent?.trim())).toEqual(['Starter', 'Élite'])
    })

    it('tier select initialises to the first remaining plan', async () => {
      await openChangeTierModal({ plan: 'pro', plan_label: 'Pro' })
      const select = document.querySelector<HTMLSelectElement>(
        '[data-testid="change-tier-new-plan"]',
      )!
      expect(select.value).toBe('starter')
    })

    it('sends the correct payload on submit', async () => {
      mockChangeTier.mockResolvedValue({ success: true, message: 'Palier modifié' })
      await openChangeTierModal({ plan: 'pro', plan_label: 'Pro' })

      const select = document.querySelector<HTMLSelectElement>(
        '[data-testid="change-tier-new-plan"]',
      )!
      select.value = 'elite'
      select.dispatchEvent(new Event('change'))
      const notes = document.querySelector<HTMLTextAreaElement>(
        '[data-testid="change-tier-notes"]',
      )!
      notes.value = 'Correction de palier'
      notes.dispatchEvent(new Event('input'))
      await flushPromises()

      document.querySelector<HTMLButtonElement>('[data-testid="change-tier-submit"]')!.click()
      await flushPromises()

      expect(mockChangeTier).toHaveBeenCalledWith('sub-active', {
        notes: 'Correction de palier',
        new_plan: 'elite',
      })
      expect(mockFetchSubscriptions).toHaveBeenCalledTimes(2)
      expect(mockToastSuccess).toHaveBeenCalledWith('Palier modifié')
    })

    it('does not render the change-tier button when the current plan is missing', async () => {
      const wrapper = await mountSection([
        makeSubscription({
          id: 'sub-active',
          status: 'active',
          expires_at: futureExpires,
          plan: null,
          plan_label: null,
        }),
      ])

      expect(wrapper.find('[data-testid="change-tier-button"]').exists()).toBe(false)
      expect(
        document.querySelector('[data-testid="admin-face-subscription-change-tier-modal"]'),
      ).toBeNull()
      expect(mockChangeTier).not.toHaveBeenCalled()
      expect(mockToastError).not.toHaveBeenCalled()
    })

    it('surfaces NOT_TIER_CHANGEABLE conflict and keeps modal open', async () => {
      mockChangeTier.mockResolvedValue({
        success: false,
        code: 'NOT_TIER_CHANGEABLE',
        message:
          'Seul un abonnement actif et non expiré peut changer de palier. Utilisez « Activer » pour redémarrer un abonnement terminé.',
        errors: {},
      })
      await openChangeTierModal({ plan: 'pro', plan_label: 'Pro' })

      const notes = document.querySelector<HTMLTextAreaElement>(
        '[data-testid="change-tier-notes"]',
      )!
      notes.value = 'Tentative changement'
      notes.dispatchEvent(new Event('input'))
      await flushPromises()

      document.querySelector<HTMLButtonElement>('[data-testid="change-tier-submit"]')!.click()
      await flushPromises()

      const errorEl = document.querySelector(
        '[data-testid="admin-face-subscription-change-tier-error"]',
      )
      expect(errorEl).not.toBeNull()
      expect(errorEl!.textContent).toContain('peut changer de palier')

      expect(
        document.querySelector('[data-testid="admin-face-subscription-change-tier-modal"]'),
      ).not.toBeNull()
      expect(mockFetchSubscriptions).toHaveBeenCalledTimes(2)
      expect(mockToastSuccess).not.toHaveBeenCalled()
    })

    it('surfaces SAME_TIER conflict and keeps modal open', async () => {
      mockChangeTier.mockResolvedValue({
        success: false,
        code: 'SAME_TIER',
        message: 'Cet abonnement est déjà sur ce palier. Choisissez un palier différent.',
        errors: {},
      })
      await openChangeTierModal({ plan: 'pro', plan_label: 'Pro' })

      const notes = document.querySelector<HTMLTextAreaElement>(
        '[data-testid="change-tier-notes"]',
      )!
      notes.value = 'Same tier attempt'
      notes.dispatchEvent(new Event('input'))
      await flushPromises()

      document.querySelector<HTMLButtonElement>('[data-testid="change-tier-submit"]')!.click()
      await flushPromises()

      const errorEl = document.querySelector(
        '[data-testid="admin-face-subscription-change-tier-error"]',
      )
      expect(errorEl).not.toBeNull()
      expect(errorEl!.textContent).toContain('déjà sur ce palier')
      expect(
        document.querySelector('[data-testid="admin-face-subscription-change-tier-modal"]'),
      ).not.toBeNull()
    })

    it('rebinds the modal target from refreshed subscription data after a conflict', async () => {
      mockChangeTier.mockResolvedValue({
        success: false,
        code: 'SAME_TIER',
        message: 'Cet abonnement est déjà sur ce palier. Choisissez un palier différent.',
        errors: {},
      })
      await openChangeTierModal({ plan: 'pro', plan_label: 'Pro' })

      mockFetchSubscriptions.mockImplementation(async () => {
        subscriptionsRef.value = [
          makeSubscription({
            id: 'sub-active',
            status: 'active',
            expires_at: futureExpires,
            plan: 'elite',
            plan_label: 'Élite',
          }),
        ]
      })

      document.querySelector<HTMLButtonElement>('[data-testid="change-tier-submit"]')!.click()
      await flushPromises()

      expect(document.querySelector('[data-testid="change-tier-current"]')?.textContent).toContain(
        'Élite',
      )
      const options = Array.from(
        document.querySelectorAll<HTMLOptionElement>(
          '[data-testid="change-tier-new-plan"] option',
        ),
      )
      expect(options.map((o) => o.value)).toEqual(['starter', 'pro'])
    })

    it('closes the modal when conflict refresh no longer contains the target subscription', async () => {
      mockChangeTier.mockResolvedValue({
        success: false,
        code: 'NOT_TIER_CHANGEABLE',
        message:
          'Seul un abonnement actif et non expiré peut changer de palier. Utilisez « Activer » pour redémarrer un abonnement terminé.',
        errors: {},
      })
      await openChangeTierModal({ plan: 'pro', plan_label: 'Pro' })

      mockFetchSubscriptions.mockImplementation(async () => {
        subscriptionsRef.value = []
      })

      document.querySelector<HTMLButtonElement>('[data-testid="change-tier-submit"]')!.click()
      await flushPromises()

      expect(
        document.querySelector('[data-testid="admin-face-subscription-change-tier-modal"]'),
      ).toBeNull()
      expect(mockToastError).toHaveBeenCalledWith("Cet abonnement n'est plus modifiable.")
    })

    it('closes the modal when conflict refresh returns a target without a paid plan', async () => {
      mockChangeTier.mockResolvedValue({
        success: false,
        code: 'SAME_TIER',
        message: 'Cet abonnement est déjà sur ce palier. Choisissez un palier différent.',
        errors: {},
      })
      await openChangeTierModal({ plan: 'pro', plan_label: 'Pro' })

      mockFetchSubscriptions.mockImplementation(async () => {
        subscriptionsRef.value = [
          makeSubscription({
            id: 'sub-active',
            status: 'active',
            expires_at: futureExpires,
            plan: null,
            plan_label: null,
          }),
        ]
      })

      document.querySelector<HTMLButtonElement>('[data-testid="change-tier-submit"]')!.click()
      await flushPromises()

      expect(
        document.querySelector('[data-testid="admin-face-subscription-change-tier-modal"]'),
      ).toBeNull()
      expect(mockToastError).toHaveBeenCalledWith("Cet abonnement n'est plus modifiable.")
    })

    it('surfaces per-field validation errors', async () => {
      mockChangeTier.mockResolvedValue({
        success: false,
        code: 'VALIDATION_ERROR',
        message: 'Les données fournies ne sont pas valides',
        errors: {
          new_plan: ['Le champ palier sélectionné est invalide.'],
        },
      })
      await openChangeTierModal({ plan: 'pro', plan_label: 'Pro' })

      const notes = document.querySelector<HTMLTextAreaElement>(
        '[data-testid="change-tier-notes"]',
      )!
      notes.value = 'Notes valides'
      notes.dispatchEvent(new Event('input'))
      await flushPromises()

      document.querySelector<HTMLButtonElement>('[data-testid="change-tier-submit"]')!.click()
      await flushPromises()

      const fieldErr = document.querySelector(
        '[data-testid="admin-face-subscription-change-tier-field-error-new_plan"]',
      )
      expect(fieldErr).not.toBeNull()
      expect(fieldErr!.textContent).toContain('palier')

      const banner = document.querySelector(
        '[data-testid="admin-face-subscription-change-tier-error"]',
      )
      expect(banner!.textContent).toContain('Les données fournies ne sont pas valides')
    })

    it('prevents duplicate change-tier submits while the request is in flight', async () => {
      let resolveChangeTier: (value: unknown) => void
      mockChangeTier.mockReturnValue(
        new Promise((resolve) => {
          resolveChangeTier = resolve
        }),
      )
      await openChangeTierModal({ plan: 'pro', plan_label: 'Pro' })

      const notes = document.querySelector<HTMLTextAreaElement>(
        '[data-testid="change-tier-notes"]',
      )!
      notes.value = 'Notes duplicate guard'
      notes.dispatchEvent(new Event('input'))
      await flushPromises()

      const submit = document.querySelector<HTMLButtonElement>(
        '[data-testid="change-tier-submit"]',
      )!
      submit.click()
      await nextTick()
      expect(submit.disabled).toBe(true)
      submit.click()
      expect(mockChangeTier).toHaveBeenCalledTimes(1)

      resolveChangeTier!({ success: true, message: 'Palier modifié' })
      await flushPromises()
    })

    it('disables the submit button while the request is in flight', async () => {
      let resolveChangeTier: (value: unknown) => void
      mockChangeTier.mockReturnValue(
        new Promise((resolve) => {
          resolveChangeTier = resolve
        }),
      )
      await openChangeTierModal({ plan: 'pro', plan_label: 'Pro' })

      const notes = document.querySelector<HTMLTextAreaElement>(
        '[data-testid="change-tier-notes"]',
      )!
      notes.value = 'Notes disabled guard'
      notes.dispatchEvent(new Event('input'))
      await flushPromises()

      const submit = document.querySelector<HTMLButtonElement>(
        '[data-testid="change-tier-submit"]',
      )!
      submit.click()
      await nextTick()
      expect(submit.disabled).toBe(true)

      resolveChangeTier!({ success: true, message: 'Palier modifié' })
      await flushPromises()
    })

    it('closes the modal when the operator presses Escape', async () => {
      await openChangeTierModal({ plan: 'pro', plan_label: 'Pro' })

      const modal = document.querySelector<HTMLDivElement>(
        '[data-testid="admin-face-subscription-change-tier-modal"]',
      )!
      expect(modal).not.toBeNull()

      modal.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
      await flushPromises()

      expect(
        document.querySelector('[data-testid="admin-face-subscription-change-tier-modal"]'),
      ).toBeNull()
    })
  })

  // ---------- Audit tier transition display (3 tests) ----------

  describe('audit tier transition display', () => {
    it('renders a tier transition badge on change_tier audit rows', async () => {
      const sub = makeSubscription({
        id: 'sub-changed',
        plan: 'elite',
        plan_label: 'Élite',
        audits: [
          {
            id: 'a1',
            action: 'change_tier',
            action_label: 'Changement de palier',
            notes: 'Pro vers Élite',
            previous_state: { tier: 'starter' },
            new_state: { tier: 'elite' },
            admin: { id: 'admin-1', name: 'Admin Op', role: 'admin' },
            created_at: '2026-05-20T10:00:00+00:00',
          },
        ],
      })
      const wrapper = await mountSection([sub])

      await wrapper.find('[data-testid="audits-toggle-sub-changed"]').trigger('click')
      await flushPromises()

      const badge = wrapper.find('[data-testid="audit-tier-transition-a1"]')
      expect(badge.exists()).toBe(true)
      expect(badge.text()).toContain('Starter')
      expect(badge.text()).toContain('Élite')
      expect(badge.text()).toContain('→')
    })

    it('renders a single tier badge on manual_activate audit rows', async () => {
      const sub = makeSubscription({
        id: 'sub-activated',
        plan: 'pro',
        plan_label: 'Pro',
        audits: [
          {
            id: 'a2',
            action: 'manual_activate',
            action_label: 'Activation manuelle',
            notes: 'Activation Pro',
            previous_state: null,
            new_state: { tier: 'pro' },
            admin: { id: 'admin-1', name: 'Admin Op', role: 'admin' },
            created_at: '2026-05-20T10:00:00+00:00',
          },
        ],
      })
      const wrapper = await mountSection([sub])

      await wrapper.find('[data-testid="audits-toggle-sub-activated"]').trigger('click')
      await flushPromises()

      const badge = wrapper.find('[data-testid="audit-tier-new-a2"]')
      expect(badge.exists()).toBe(true)
      expect(badge.text()).toContain('Pro')
      expect(wrapper.findAll('[data-testid^="audit-tier-transition-"]')).toHaveLength(0)
    })

    it('does not render a tier badge on extend, cancel, or correct_dates audit rows', async () => {
      const sub = makeSubscription({
        id: 'sub-noisy',
        audits: [
          {
            id: 'a3',
            action: 'extend',
            action_label: 'Prolongation',
            notes: 'extended',
            previous_state: { tier: 'pro' },
            new_state: { tier: 'pro' },
            admin: { id: 'admin-1', name: 'Admin Op', role: 'admin' },
            created_at: '2026-05-20T10:00:00+00:00',
          },
          {
            id: 'a4',
            action: 'cancel',
            action_label: 'Annulation',
            notes: 'cancelled',
            previous_state: { tier: 'pro' },
            new_state: { tier: 'pro' },
            admin: { id: 'admin-1', name: 'Admin Op', role: 'admin' },
            created_at: '2026-05-20T10:00:00+00:00',
          },
          {
            id: 'a5',
            action: 'correct_dates',
            action_label: 'Correction des dates',
            notes: 'corrected',
            previous_state: { tier: 'pro' },
            new_state: { tier: 'pro' },
            admin: { id: 'admin-1', name: 'Admin Op', role: 'admin' },
            created_at: '2026-05-20T10:00:00+00:00',
          },
        ],
      })
      const wrapper = await mountSection([sub])

      await wrapper.find('[data-testid="audits-toggle-sub-noisy"]').trigger('click')
      await flushPromises()

      expect(wrapper.findAll('[data-testid^="audit-tier-"]')).toHaveLength(0)
    })
  })
})
