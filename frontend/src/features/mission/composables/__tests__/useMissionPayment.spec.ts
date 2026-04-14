import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useMissionPayment } from '../useMissionPayment'
import { missionApi } from '../../services/missionApi'

vi.mock('../../services/missionApi', () => ({
  missionApi: {
    confirmSelection: vi.fn(),
    getPaymentStatus: vi.fn(),
  },
}))

const MISSION_ID = 'mission-uuid-under-test'

function storageKeyFor(missionId: string): string {
  return `weact_selection_${missionId}`
}

function labelsKeyFor(missionId: string): string {
  return `weact_selection_labels_${missionId}`
}

describe('useMissionPayment', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    sessionStorage.clear()
  })

  describe('confirmAndPay', () => {
    it('preserves the local selection when the API call fails (FIX-19.3 AC #3)', async () => {
      // Arrange: seed a selection in sessionStorage so the composable hydrates
      // with two faces already picked (mimics the producer coming back from a
      // failed initiation attempt).
      sessionStorage.setItem(
        storageKeyFor(MISSION_ID),
        JSON.stringify(['candidature-1', 'candidature-2']),
      )
      sessionStorage.setItem(
        labelsKeyFor(MISSION_ID),
        JSON.stringify({ 'candidature-1': 'Alice', 'candidature-2': 'Bob' }),
      )

      vi.mocked(missionApi.confirmSelection).mockRejectedValueOnce({
        response: {
          data: {
            message: 'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.',
          },
        },
      })

      const { selectedCandidatureIds, error, confirmAndPay } = useMissionPayment(1000, MISSION_ID)

      // Act
      const result = await confirmAndPay(MISSION_ID)

      // Assert
      expect(result).toBeNull()
      expect(error.value).toBe(
        'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.',
      )
      expect(selectedCandidatureIds.value).toEqual(['candidature-1', 'candidature-2'])
      // The sessionStorage-backed selection must survive so the producer can retry
      // without re-picking faces.
      expect(sessionStorage.getItem(storageKeyFor(MISSION_ID))).toBe(
        JSON.stringify(['candidature-1', 'candidature-2']),
      )
    })

    it('clears the local selection only after a successful initiation', async () => {
      sessionStorage.setItem(
        storageKeyFor(MISSION_ID),
        JSON.stringify(['candidature-1']),
      )
      sessionStorage.setItem(
        labelsKeyFor(MISSION_ID),
        JSON.stringify({ 'candidature-1': 'Alice' }),
      )

      vi.mocked(missionApi.confirmSelection).mockResolvedValueOnce({
        data: {
          payment_id: 42,
          montant_total: 110000,
          nombre_faces: 1,
          checkout_url: 'https://checkout.fedapay.com/mission-token',
          status: 'pending',
        },
        message: 'Sélection confirmée. Redirection vers le paiement...',
      })

      const { selectedCandidatureIds, confirmAndPay } = useMissionPayment(100000, MISSION_ID)
      const result = await confirmAndPay(MISSION_ID)

      expect(result).toEqual({ checkout_url: 'https://checkout.fedapay.com/mission-token' })
      expect(selectedCandidatureIds.value).toEqual([])
      // clearSelection() removes the key from storage; Vue watchers may repopulate
      // it with the empty array representation — either outcome is acceptable.
      const stored = sessionStorage.getItem(storageKeyFor(MISSION_ID))
      expect(stored === null || stored === '[]').toBe(true)
    })

    it('surfaces a mission-payment-specific fallback message when the backend returns no body (FIX-19.3 AC #4)', async () => {
      sessionStorage.setItem(
        storageKeyFor(MISSION_ID),
        JSON.stringify(['candidature-1']),
      )

      vi.mocked(missionApi.confirmSelection).mockRejectedValueOnce(new Error('network down'))

      const { error, confirmAndPay } = useMissionPayment(50000, MISSION_ID)
      const result = await confirmAndPay(MISSION_ID)

      expect(result).toBeNull()
      // Regression guard: the fallback must name the payment-initialization
      // failure and invite a retry, not read as a generic confirmation error.
      expect(error.value).toMatch(/paiement/i)
      expect(error.value).toMatch(/réessayer/i)
    })
  })
})
