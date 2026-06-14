import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import ProducerUgcValidationPage from '../ProducerUgcValidationPage.vue'
import type { Deliverable, DeliverableReviewItem } from '@/components/ugc'

// Spies + refs hissés hors des factories pour être capturables (calque
// AdminAttendanceDisputesPage.spec / FaceBookingDetailPage.spec).
const itemsRef = ref<DeliverableReviewItem[]>([])
const isLoadingRef = ref(false)
const isSubmittingRef = ref(false)
const errorRef = ref<string | null>(null)
const errorCodeRef = ref<string | null>(null)
const mockFetchPending = vi.fn()
const mockValidate = vi.fn()
const mockReject = vi.fn()
const mockRequestRetouche = vi.fn()
const mockToastSuccess = vi.fn()
const mockToastError = vi.fn()

vi.mock('@/composables/useUgcDeliverableReview', () => ({
  useUgcDeliverableReview: () => ({
    items: itemsRef,
    isLoading: isLoadingRef,
    isSubmitting: isSubmittingRef,
    error: errorRef,
    errorCode: errorCodeRef,
    fetchPending: mockFetchPending,
    validate: mockValidate,
    reject: mockReject,
    requestRetouche: mockRequestRetouche,
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: mockToastSuccess, error: mockToastError }),
}))

function makeReviewItem(overrides: Partial<DeliverableReviewItem> = {}): DeliverableReviewItem {
  return {
    id: 'd1',
    kind: 'unboxing',
    kind_label: 'Unboxing',
    validation_status: 'in_review',
    validation_status_label: 'En cours de validation',
    review_note: null,
    submitted_at: '2026-06-14T10:00:00+00:00',
    chrono_started_at: '2026-06-13T10:00:00+00:00',
    deadline_at: '2026-06-20T10:00:00+00:00',
    review_due_at: '2026-06-16T10:00:00+00:00',
    duree_seconds: 42,
    owner_type: 'booking',
    owner_id: 'booking-uuid-1',
    face_name: 'Aïcha Bello',
    product_name: 'Tenue Shade Fit',
    video_url: 'http://localhost/video?signature=abc',
    thumbnail_url: 'http://localhost/thumb?signature=def',
    ...overrides,
  }
}

function makeDeliverable(overrides: Partial<Deliverable> = {}): Deliverable {
  return {
    id: 'd1',
    kind: 'unboxing',
    kind_label: 'Unboxing',
    validation_status: 'validated',
    validation_status_label: 'Validé',
    chrono_started_at: '2026-06-13T10:00:00+00:00',
    deadline_at: '2026-06-20T10:00:00+00:00',
    duree_seconds: 42,
    created_at: '2026-06-13T11:00:00+00:00',
    ...overrides,
  }
}

const item2 = makeReviewItem({ id: 'd2', face_name: 'Bénédicte Koffi', product_name: 'Sérum Lumi-C', kind_label: 'Avis' })

function mountPage() {
  return mount(ProducerUgcValidationPage, {
    global: { stubs: { ChronoBadge: true } },
  })
}

describe('ProducerUgcValidationPage', () => {
  beforeEach(() => {
    // resetAllMocks (≠ clearAllMocks) : purge AUSSI les implémentations posées
    // par un test précédent (ex. le mockFetchPending qui filtre itemsRef).
    vi.resetAllMocks()
    itemsRef.value = []
    isLoadingRef.value = false
    isSubmittingRef.value = false
    errorRef.value = null
    errorCodeRef.value = null
  })

  it('fetches pending deliverables on mount and renders the list', async () => {
    itemsRef.value = [makeReviewItem(), item2]
    const wrapper = mountPage()
    await flushPromises()

    expect(mockFetchPending).toHaveBeenCalledOnce()
    expect(wrapper.findAll('[data-testid="ugc-review-item"]')).toHaveLength(2)
    expect(wrapper.get('[data-testid="ugc-review-list"]').text()).toContain('2 livrables en attente')
  })

  it('renders the empty state when there are no items', async () => {
    itemsRef.value = []
    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-review-empty"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="ugc-review-list"]').exists()).toBe(false)
  })

  it('auto-selects the first item and shows its detail pane', async () => {
    itemsRef.value = [makeReviewItem(), item2]
    const wrapper = mountPage()
    await flushPromises()

    const detail = wrapper.get('[data-testid="ugc-review-detail"]')
    expect(detail.text()).toContain('Tenue Shade Fit')
    expect(detail.text()).toContain('Aïcha Bello')
  })

  it('shows the clicked item in the preview pane', async () => {
    itemsRef.value = [makeReviewItem(), item2]
    const wrapper = mountPage()
    await flushPromises()

    await wrapper.findAll('[data-testid="ugc-review-item"]')[1]!.trigger('click')

    const detail = wrapper.get('[data-testid="ugc-review-detail"]')
    expect(detail.text()).toContain('Sérum Lumi-C')
    expect(detail.text()).toContain('Bénédicte Koffi')
  })

  it('renders the static conformity checklist for the selected item', async () => {
    itemsRef.value = [makeReviewItem()]
    const wrapper = mountPage()
    await flushPromises()

    const detail = wrapper.get('[data-testid="ugc-review-detail"]').text()
    expect(detail).toContain('Format vertical 9:16')
    expect(detail).toContain('Mention WeAct visible')
  })

  it('validates the selected deliverable then refetches (item leaves the list)', async () => {
    itemsRef.value = [makeReviewItem(), item2]
    const wrapper = mountPage()
    await flushPromises()
    expect(wrapper.findAll('[data-testid="ugc-review-item"]')).toHaveLength(2)

    mockValidate.mockResolvedValue(makeDeliverable({ id: 'd1' }))
    // Le refetch simule la sortie du livrable validé (plus in_review).
    mockFetchPending.mockImplementation(() => {
      itemsRef.value = itemsRef.value.filter((d) => d.id !== 'd1')
      return Promise.resolve()
    })

    await wrapper.get('[data-testid="ugc-review-validate"]').trigger('click')
    await flushPromises()

    expect(mockValidate).toHaveBeenCalledWith('d1')
    expect(mockFetchPending).toHaveBeenCalledTimes(2) // onMounted + après action
    expect(mockToastSuccess).toHaveBeenCalledWith('Livrable validé')
    expect(wrapper.findAll('[data-testid="ugc-review-item"]')).toHaveLength(1)
  })

  it('blocks rejection without a note and shows an inline message', async () => {
    itemsRef.value = [makeReviewItem()]
    const wrapper = mountPage()
    await flushPromises()

    await wrapper.get('[data-testid="ugc-review-reject"]').trigger('click')

    expect(mockReject).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="ugc-review-note-error"]').exists()).toBe(true)
  })

  it('rejects with a valid note (≥ 5 chars)', async () => {
    itemsRef.value = [makeReviewItem()]
    mockReject.mockResolvedValue(makeDeliverable({ validation_status: 'rejected' }))
    const wrapper = mountPage()
    await flushPromises()

    await wrapper.get('[data-testid="ugc-review-note"]').setValue('Cadrage hors sujet, recommence.')
    await wrapper.get('[data-testid="ugc-review-reject"]').trigger('click')
    await flushPromises()

    expect(mockReject).toHaveBeenCalledWith('d1', 'Cadrage hors sujet, recommence.')
    expect(mockToastSuccess).toHaveBeenCalledWith('Livrable refusé')
  })

  it('requests a retouche with a valid note', async () => {
    itemsRef.value = [makeReviewItem()]
    mockRequestRetouche.mockResolvedValue(makeDeliverable({ validation_status: 'retouche_requested' }))
    const wrapper = mountPage()
    await flushPromises()

    await wrapper.get('[data-testid="ugc-review-note"]').setValue('Ajoute le plan packaging.')
    await wrapper.get('[data-testid="ugc-review-retouche"]').trigger('click')
    await flushPromises()

    expect(mockRequestRetouche).toHaveBeenCalledWith('d1', 'Ajoute le plan packaging.')
    expect(mockToastSuccess).toHaveBeenCalledWith('Retouche demandée')
  })
})
