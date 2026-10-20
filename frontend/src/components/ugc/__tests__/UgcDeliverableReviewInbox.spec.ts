import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { setActivePinia, createPinia } from 'pinia'
import { flushPromises, mount } from '@vue/test-utils'
import UgcDeliverableReviewInbox from '../UgcDeliverableReviewInbox.vue'
import { useUgcValidationCountStore } from '@/stores/ugcValidationCount'
import type { Deliverable, DeliverableReviewItem } from '@/components/ugc'

const itemsRef = ref<DeliverableReviewItem[]>([])
const mockFetchPending = vi.fn()
const mockValidate = vi.fn()
const reviewFactory = vi.fn()

vi.mock('@/composables/useUgcDeliverableReview', () => ({
  useUgcDeliverableReview: (missionId?: string) => {
    reviewFactory(missionId)
    return {
      items: itemsRef,
      isLoading: ref(false),
      isSubmitting: ref(false),
      error: ref<string | null>(null),
      errorCode: ref<string | null>(null),
      fetchPending: mockFetchPending,
      validate: mockValidate,
      reject: vi.fn(),
      requestRetouche: vi.fn(),
    }
  },
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn() }),
}))

function makeItem(overrides: Partial<DeliverableReviewItem> = {}): DeliverableReviewItem {
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
    owner_type: 'candidature',
    owner_id: 'cand-uuid-1',
    face_name: 'Aïcha Bello',
    product_name: 'Tenue Shade Fit',
    video_url: 'http://localhost/video?signature=abc',
    thumbnail_url: 'http://localhost/thumb?signature=def',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  itemsRef.value = []
  mockFetchPending.mockImplementation(async () => {
    itemsRef.value = [makeItem()]
  })
})

describe('UgcDeliverableReviewInbox — filtrage par mission (onglet Livrables)', () => {
  it('passe l’uuid de la mission au composable', async () => {
    mount(UgcDeliverableReviewInbox, { props: { missionId: 'mission-uuid', compact: true } })
    await flushPromises()

    expect(reviewFactory).toHaveBeenCalledWith('mission-uuid')
  })

  it('ne re-synchronise pas le compteur global « à valider » avec une liste filtrée', async () => {
    const store = useUgcValidationCountStore()
    store.setCount(7)
    mount(UgcDeliverableReviewInbox, { props: { missionId: 'mission-uuid', compact: true } })
    await flushPromises()

    expect(itemsRef.value).toHaveLength(1)
    expect(store.count).toBe(7)
  })

  it('sans mission (page globale) : le compteur est bien re-synchronisé', async () => {
    const store = useUgcValidationCountStore()
    store.setCount(7)
    mount(UgcDeliverableReviewInbox)
    await flushPromises()

    expect(reviewFactory).toHaveBeenCalledWith(undefined)
    expect(store.count).toBe(1)
  })

  it('après une décision réussie : recharge la liste et prévient le parent (changed)', async () => {
    mockValidate.mockResolvedValue({ id: 'd1' } as Deliverable)
    const wrapper = mount(UgcDeliverableReviewInbox, { props: { missionId: 'mission-uuid', compact: true } })
    await flushPromises()

    await wrapper.get('[data-testid="ugc-review-validate"]').trigger('click')
    await flushPromises()

    expect(mockValidate).toHaveBeenCalledWith('d1')
    expect(mockFetchPending).toHaveBeenCalledTimes(2)
    expect(wrapper.emitted('changed')).toHaveLength(1)
  })

  it('mode compact : volets empilés (une colonne)', async () => {
    const wrapper = mount(UgcDeliverableReviewInbox, { props: { missionId: 'mission-uuid', compact: true } })
    await flushPromises()

    expect(wrapper.get('[data-testid="ugc-review-list"]').element.parentElement!.className).toContain('grid-cols-1')
  })
})
