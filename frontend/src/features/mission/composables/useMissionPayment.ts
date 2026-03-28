import { computed, ref } from 'vue'
import { missionApi } from '../services/missionApi'

export interface MissionPricingPreview {
  nombreFaces: number
  budgetParFace: number
  sousTotal: number
  commissionProducteur: number
  montantTotal: number
}

const COMMISSION_RATE = 0.10

function computePricing(budgetParFace: number, nombreFaces: number): MissionPricingPreview {
  const sousTotal = budgetParFace * nombreFaces
  const commissionProducteur = Math.round(sousTotal * COMMISSION_RATE)
  return {
    nombreFaces,
    budgetParFace,
    sousTotal,
    commissionProducteur,
    montantTotal: sousTotal + commissionProducteur,
  }
}

export function useMissionPayment(budgetParFace: number) {
  const selectedCandidatureIds = ref<number[]>([])
  const isSelectionMode = ref(true)
  const isConfirming = ref(false)
  const error = ref<string | null>(null)
  const pollIntervalId = ref<ReturnType<typeof setInterval> | null>(null)
  const paymentStatus = ref<string | null>(null)

  const pricing = computed<MissionPricingPreview | null>(() => {
    if (selectedCandidatureIds.value.length === 0) return null
    return computePricing(budgetParFace, selectedCandidatureIds.value.length)
  })

  function toggleSelection(id: number): void {
    const idx = selectedCandidatureIds.value.indexOf(id)
    if (idx === -1) {
      selectedCandidatureIds.value.push(id)
    } else {
      selectedCandidatureIds.value.splice(idx, 1)
    }
  }

  function isSelected(id: number): boolean {
    return selectedCandidatureIds.value.includes(id)
  }

  async function confirmAndPay(missionId: number): Promise<{ checkout_url: string } | null> {
    if (selectedCandidatureIds.value.length === 0) return null

    isConfirming.value = true
    error.value = null

    try {
      const response = await missionApi.confirmSelection(missionId, selectedCandidatureIds.value)
      return { checkout_url: response.data.checkout_url }
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      error.value = e?.response?.data?.message ?? 'Une erreur est survenue lors de la confirmation.'
      return null
    } finally {
      isConfirming.value = false
    }
  }

  function startPolling(
    missionId: number,
    onPaid: () => void,
    intervalMs = 3000
  ): void {
    stopPolling()

    pollIntervalId.value = setInterval(async () => {
      try {
        const response = await missionApi.getPaymentStatus(missionId)
        const data = response.data

        paymentStatus.value = data.status ?? null

        if (data.status === 'paid') {
          stopPolling()
          onPaid()
        }
      } catch {
        // Ignore polling errors
      }
    }, intervalMs)
  }

  function stopPolling(): void {
    if (pollIntervalId.value !== null) {
      clearInterval(pollIntervalId.value)
      pollIntervalId.value = null
    }
  }

  return {
    selectedCandidatureIds,
    isSelectionMode,
    isConfirming,
    error,
    pricing,
    paymentStatus,
    toggleSelection,
    isSelected,
    confirmAndPay,
    startPolling,
    stopPolling,
  }
}
