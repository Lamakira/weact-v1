import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises, RouterLinkStub, type VueWrapper } from '@vue/test-utils'
import { ref } from 'vue'
import type {
  AdminBookingDispute,
  AdminStalePaidBooking,
} from '@/features/admin/services/adminBookingDisputesApi'
import AdminBookingDisputesPage from '../AdminBookingDisputesPage.vue'

const mockFetchDisputes = vi.fn()
const mockResolveDispute = vi.fn()
const mockToastSuccess = vi.fn()
const mockToastError = vi.fn()

const disputesRef = ref<AdminBookingDispute[]>([])
const stalePaidRef = ref<AdminStalePaidBooking[]>([])
const isLoadingRef = ref(false)
const isResolvingRef = ref(false)
const errorRef = ref<string | null>(null)
const resolveErrorRef = ref<string | null>(null)
const resolveSuccessRef = ref<string | null>(null)
const wrappers: VueWrapper[] = []

vi.mock('@/features/admin/composables/useAdminBookingDisputes', () => ({
  useAdminBookingDisputes: () => ({
    disputes: disputesRef,
    stalePaid: stalePaidRef,
    isLoading: isLoadingRef,
    isResolving: isResolvingRef,
    error: errorRef,
    resolveError: resolveErrorRef,
    resolveSuccess: resolveSuccessRef,
    fetchDisputes: mockFetchDisputes,
    resolveDispute: mockResolveDispute,
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: mockToastSuccess, error: mockToastError }),
}))

function makeDispute(overrides: Partial<AdminBookingDispute> = {}): AdminBookingDispute {
  return {
    id: 'booking-uuid-1',
    status: 'no_show',
    face: { display_name: 'Amina K.' },
    producer: { display_name: 'Studio Lumière' },
    date_debut: '2026-10-08T00:00:00.000Z',
    date_fin: '2026-10-08T00:00:00.000Z',
    montant_total_producteur: 110000,
    montant_face_recoit: 90000,
    reported_at: '2026-10-09T08:00:00.000Z',
    settlement_due_at: '2026-10-12T08:00:00.000Z',
    disputed_at: '2026-10-10T10:00:00.000Z',
    dispute_message: 'J\'étais présente sur place.',
    cancellation_reason: null,
    ...overrides,
  }
}

function makeStale(overrides: Partial<AdminStalePaidBooking> = {}): AdminStalePaidBooking {
  return {
    id: 'stale-uuid-1',
    face: { display_name: 'Face Ancienne' },
    producer: { display_name: 'Producteur Ancien' },
    date_debut: '2026-04-01T00:00:00.000Z',
    date_fin: '2026-04-01T00:00:00.000Z',
    montant_total_producteur: 55000,
    montant_face_recoit: 45000,
    days_since_date_fin: 192,
    auto_complete_due_at: null,
    is_legacy: true,
    ...overrides,
  }
}

function mountPage(options: { attachTo?: HTMLElement } = {}) {
  return mount(AdminBookingDisputesPage, {
    ...options,
    global: { stubs: { RouterLink: RouterLinkStub } },
  })
}

async function fillNotesAndConfirm(notes: string): Promise<void> {
  const textarea = document.body.querySelector<HTMLTextAreaElement>('#booking-dispute-resolve-notes')!
  textarea.value = notes
  textarea.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
  document.body.querySelector<HTMLButtonElement>('[data-testid="booking-resolve-confirm"]')!.click()
  await flushPromises()
}

describe('AdminBookingDisputesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    document.body.innerHTML = ''
    disputesRef.value = []
    stalePaidRef.value = []
    isLoadingRef.value = false
    isResolvingRef.value = false
    errorRef.value = null
    resolveErrorRef.value = null
    resolveSuccessRef.value = null
  })

  afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
    document.body.innerHTML = ''
  })

  it('fetches the disputes on mount', async () => {
    wrappers.push(mountPage())
    await flushPromises()

    expect(mockFetchDisputes).toHaveBeenCalledTimes(1)
  })

  it('shows both empty states', async () => {
    const wrapper = mountPage()
    wrappers.push(wrapper)
    await flushPromises()

    expect(wrapper.text()).toContain('Aucun litige en attente.')
    expect(wrapper.text()).toContain('Aucun booking payé sans suite.')
  })

  it('renders the disputes list and the read-only stale paid section', async () => {
    disputesRef.value = [makeDispute()]
    stalePaidRef.value = [makeStale()]
    const wrapper = mountPage()
    wrappers.push(wrapper)
    await flushPromises()

    const disputeRows = wrapper.findAll('[data-testid="booking-disputes-rows"] tr')
    expect(disputeRows).toHaveLength(1)
    expect(disputeRows[0].text()).toContain('Absence signalée')
    expect(disputeRows[0].text()).toContain('Amina K.')
    expect(disputeRows[0].text()).toContain('J\'étais présente sur place.')

    expect(wrapper.text()).toContain('Bookings payés sans suite')
    const staleRows = wrapper.findAll('[data-testid="stale-paid-rows"] tr')
    expect(staleRows).toHaveLength(1)
    expect(staleRows[0].text()).toContain('192 jours')
    // Lecture seule : aucune action sur la section « sans suite ».
    expect(staleRows[0].find('button').exists()).toBe(false)
  })

  it('posts a resolution with trimmed notes and the favor_producer outcome', async () => {
    disputesRef.value = [makeDispute({ id: 'booking-uuid-7' })]
    mockResolveDispute.mockResolvedValue(true)
    resolveSuccessRef.value = 'Litige résolu avec succès'

    const wrapper = mountPage({ attachTo: document.body })
    wrappers.push(wrapper)
    await flushPromises()

    await wrapper.find('[data-testid="resolve-favor-producer"]').trigger('click')
    const textarea = document.body.querySelector<HTMLTextAreaElement>('#booking-dispute-resolve-notes')
    expect(textarea).not.toBeNull()

    textarea!.value = '  Absence avérée  '
    textarea!.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()
    document.body.querySelector<HTMLButtonElement>('[data-testid="booking-resolve-confirm"]')!.click()
    await flushPromises()

    expect(mockResolveDispute).toHaveBeenCalledWith('booking-uuid-7', 'favor_producer', 'Absence avérée')
    expect(mockToastSuccess).toHaveBeenCalledWith('Litige résolu avec succès')
  })

  it('posts a favor_face resolution and blocks notes shorter than 5 characters', async () => {
    disputesRef.value = [makeDispute({ id: 'booking-uuid-8', status: 'cancelled_by_producer' })]
    mockResolveDispute.mockResolvedValue(true)

    const wrapper = mountPage({ attachTo: document.body })
    wrappers.push(wrapper)
    await flushPromises()

    await wrapper.find('[data-testid="resolve-favor-face"]').trigger('click')
    const textarea = document.body.querySelector<HTMLTextAreaElement>('#booking-dispute-resolve-notes')!
    const confirm = () => document.body.querySelector<HTMLButtonElement>('[data-testid="booking-resolve-confirm"]')!

    textarea.value = 'ok'
    textarea.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()
    expect(confirm().disabled).toBe(true)

    textarea.value = 'Annulation trop tardive'
    textarea.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()
    confirm().click()
    await flushPromises()

    expect(mockResolveDispute).toHaveBeenCalledWith('booking-uuid-8', 'favor_face', 'Annulation trop tardive')
  })

  it('does not claim there is nothing to review when the load failed', async () => {
    errorRef.value = 'Impossible de charger les litiges.'
    const wrapper = mountPage()
    wrappers.push(wrapper)
    await flushPromises()

    expect(wrapper.text()).toContain('Impossible de charger les litiges.')
    expect(wrapper.text()).not.toContain('Aucun litige en attente.')
    expect(wrapper.text()).not.toContain('Aucun booking payé sans suite.')
  })

  it('shows a loading state in the stale paid section', async () => {
    isLoadingRef.value = true
    const wrapper = mountPage()
    wrappers.push(wrapper)
    await flushPromises()

    expect(wrapper.find('[data-testid="stale-paid-loading"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Aucun booking payé sans suite.')
  })

  it('tells which stale rows will be paid automatically and links to the booking', async () => {
    stalePaidRef.value = [
      makeStale({ id: 'legacy-uuid', is_legacy: true, auto_complete_due_at: null }),
      makeStale({ id: 'auto-uuid', is_legacy: false, auto_complete_due_at: '2026-10-16T08:00:00.000Z' }),
    ]
    disputesRef.value = [makeDispute({ id: 'dispute-uuid' })]
    const wrapper = mountPage()
    wrappers.push(wrapper)
    await flushPromises()

    const cells = wrapper.findAll('[data-testid="stale-auto-payment"]')
    expect(cells[0].text()).toBe('Ancien booking : jamais payé automatiquement')
    expect(cells[1].text()).toContain('Paiement automatique prévu le')

    const links = wrapper.findAllComponents(RouterLinkStub)
    const targets = links.map((link) => link.props('to'))
    expect(targets).toContainEqual({ name: 'admin-booking-detail', params: { id: 'legacy-uuid' } })
    expect(targets).toContainEqual({ name: 'admin-booking-detail', params: { id: 'dispute-uuid' } })
  })

  it('keeps the modal open with the notes when the resolution fails, and closes it on success', async () => {
    disputesRef.value = [makeDispute({ id: 'booking-uuid-9' })]
    mockResolveDispute.mockResolvedValueOnce(false).mockResolvedValueOnce(true)
    resolveErrorRef.value = 'Le litige a déjà été tranché.'

    const wrapper = mountPage({ attachTo: document.body })
    wrappers.push(wrapper)
    await flushPromises()

    await wrapper.find('[data-testid="resolve-favor-face"]').trigger('click')
    await fillNotesAndConfirm('Notes de décision')

    expect(document.body.querySelector('[data-testid="booking-resolve-modal"]')).not.toBeNull()
    expect(document.body.querySelector<HTMLTextAreaElement>('#booking-dispute-resolve-notes')!.value).toBe('Notes de décision')
    expect(document.body.textContent).toContain('Le litige a déjà été tranché.')

    resolveErrorRef.value = null
    document.body.querySelector<HTMLButtonElement>('[data-testid="booking-resolve-confirm"]')!.click()
    await flushPromises()

    expect(document.body.querySelector('[data-testid="booking-resolve-modal"]')).toBeNull()
  })

  it('shows an error toast when the resolution fails', async () => {
    disputesRef.value = [makeDispute()]
    mockResolveDispute.mockResolvedValue(false)
    resolveErrorRef.value = 'Impossible de résoudre le litige.'

    const wrapper = mountPage({ attachTo: document.body })
    wrappers.push(wrapper)
    await flushPromises()

    await wrapper.find('[data-testid="resolve-favor-face"]').trigger('click')
    const textarea = document.body.querySelector<HTMLTextAreaElement>('#booking-dispute-resolve-notes')!
    textarea.value = 'Note suffisante'
    textarea.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()
    document.body.querySelector<HTMLButtonElement>('[data-testid="booking-resolve-confirm"]')!.click()
    await flushPromises()

    expect(mockToastError).toHaveBeenCalledWith('Impossible de résoudre le litige.')
  })
})
