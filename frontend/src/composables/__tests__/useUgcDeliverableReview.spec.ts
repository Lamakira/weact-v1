import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useUgcDeliverableReview } from '../useUgcDeliverableReview'
import { producerApi } from '@/features/producer/services/producerApi'
import type { Deliverable, DeliverableReviewItem } from '@/components/ugc'

vi.mock('@/features/producer/services/producerApi', () => ({
  producerApi: {
    listDeliverablesToReview: vi.fn(),
    validateDeliverable: vi.fn(),
    rejectDeliverable: vi.fn(),
    requestDeliverableRetouche: vi.fn(),
  },
}))

function makeReviewItem(overrides: Partial<DeliverableReviewItem> = {}): DeliverableReviewItem {
  return {
    id: 'deliverable-uuid-1',
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
    video_url: 'http://localhost/api/v1/producer/deliverables/deliverable-uuid-1/video?signature=abc',
    thumbnail_url: 'http://localhost/api/v1/producer/deliverables/deliverable-uuid-1/thumbnail?signature=def',
    ...overrides,
  }
}

function makeDeliverable(overrides: Partial<Deliverable> = {}): Deliverable {
  return {
    id: 'deliverable-uuid-1',
    kind: 'unboxing',
    kind_label: 'Unboxing',
    validation_status: 'validated',
    validation_status_label: 'Validé',
    review_note: null,
    validated_at: '2026-06-13T11:00:00+00:00',
    chrono_started_at: '2026-06-13T10:00:00+00:00',
    deadline_at: '2026-06-20T10:00:00+00:00',
    duree_seconds: 42,
    created_at: '2026-06-13T11:00:00+00:00',
    ...overrides,
  }
}

/** Erreur axios-like portant l'envelope FIX-22.2 { error: { code, message } }. */
function makeEnvelopeError(code: string, message: string): unknown {
  return {
    isAxiosError: true,
    response: { status: 422, data: { error: { code, message } } },
  }
}

describe('useUgcDeliverableReview', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetchPending populates items and toggles isLoading', async () => {
    vi.mocked(producerApi.listDeliverablesToReview).mockResolvedValue({ data: [makeReviewItem()] })

    const { items, isLoading, error, fetchPending } = useUgcDeliverableReview()
    expect(isLoading.value).toBe(false)

    await fetchPending()

    expect(producerApi.listDeliverablesToReview).toHaveBeenCalledOnce()
    expect(items.value).toHaveLength(1)
    expect(items.value[0]?.face_name).toBe('Aïcha Bello')
    expect(isLoading.value).toBe(false)
    expect(error.value).toBeNull()
  })

  it('captures the error and code when fetchPending fails', async () => {
    vi.mocked(producerApi.listDeliverablesToReview).mockRejectedValue(
      makeEnvelopeError('INTERNAL_ERROR', 'Erreur interne du serveur.'),
    )

    const { items, error, errorCode, fetchPending } = useUgcDeliverableReview()
    await fetchPending()

    expect(items.value).toEqual([])
    expect(errorCode.value).toBe('INTERNAL_ERROR')
    expect(error.value).toBe('Erreur interne du serveur.')
  })

  it('validate returns the deliverable and toggles isSubmitting', async () => {
    let resolveCall!: (value: { data: Deliverable }) => void
    vi.mocked(producerApi.validateDeliverable).mockReturnValue(
      new Promise((resolve) => {
        resolveCall = resolve
      }),
    )

    const { validate, isSubmitting } = useUgcDeliverableReview()
    expect(isSubmitting.value).toBe(false)

    const pending = validate('deliverable-uuid-1')
    expect(isSubmitting.value).toBe(true)

    resolveCall({ data: makeDeliverable() })
    const result = await pending

    expect(producerApi.validateDeliverable).toHaveBeenCalledWith('deliverable-uuid-1')
    expect(result).toMatchObject({ id: 'deliverable-uuid-1', validation_status: 'validated' })
    expect(isSubmitting.value).toBe(false)
  })

  it('reject sends the note and surfaces INVALID_STATUS on failure', async () => {
    vi.mocked(producerApi.rejectDeliverable).mockRejectedValue(
      makeEnvelopeError('INVALID_STATUS', 'Ce livrable n’est plus en attente de validation.'),
    )

    const { reject, error, errorCode } = useUgcDeliverableReview()
    const result = await reject('deliverable-uuid-1', 'Cadrage hors sujet')

    expect(producerApi.rejectDeliverable).toHaveBeenCalledWith('deliverable-uuid-1', 'Cadrage hors sujet')
    expect(result).toBeNull()
    expect(errorCode.value).toBe('INVALID_STATUS')
    expect(error.value).toBe('Ce livrable n’est plus en attente de validation.')
  })

  it('requestRetouche delegates to the producer api', async () => {
    vi.mocked(producerApi.requestDeliverableRetouche).mockResolvedValue({
      data: makeDeliverable({ validation_status: 'retouche_requested' }),
    })

    const { requestRetouche } = useUgcDeliverableReview()
    const result = await requestRetouche('deliverable-uuid-1', 'Ajoute le plan packaging')

    expect(producerApi.requestDeliverableRetouche).toHaveBeenCalledWith(
      'deliverable-uuid-1',
      'Ajoute le plan packaging',
    )
    expect(result).toMatchObject({ validation_status: 'retouche_requested' })
  })
})
