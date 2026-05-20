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
    plan: 'annual_premium',
    plan_label: 'Premium annuel',
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
