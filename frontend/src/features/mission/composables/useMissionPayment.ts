import { computed, ref, watch } from 'vue'
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

interface SelectedFace {
  candidatureId: string
  label: string
}

export function useMissionPayment(budgetParFace: number, missionId: string, maxSelection?: number) {
  const storageKey = `weact_selection_${missionId}`
  const labelsKey = `weact_selection_labels_${missionId}`

  // Restore from sessionStorage (survives pagination + navigation)
  let restoredIds: string[] = []
  let restoredLabels: Record<string, string> = {}
  try {
    const storedIds = sessionStorage.getItem(storageKey)
    if (storedIds) {
      const parsed = JSON.parse(storedIds)
      if (Array.isArray(parsed)) restoredIds = parsed
    }
    const storedLabels = sessionStorage.getItem(labelsKey)
    if (storedLabels) {
      const parsed = JSON.parse(storedLabels)
      if (parsed && typeof parsed === 'object') restoredLabels = parsed
    }
  } catch {
    sessionStorage.removeItem(storageKey)
    sessionStorage.removeItem(labelsKey)
  }
  const selectedCandidatureIds = ref<string[]>(restoredIds)
  const selectedLabels = ref<Record<string, string>>(restoredLabels)

  const isSelectionMode = ref(true)
  const isConfirming = ref(false)
  const error = ref<string | null>(null)
  const pollIntervalId = ref<ReturnType<typeof setInterval> | null>(null)
  const paymentStatus = ref<string | null>(null)
  const selectionLimitReached = ref(false)

  // Sync to sessionStorage on every change
  watch(selectedCandidatureIds, (ids) => {
    sessionStorage.setItem(storageKey, JSON.stringify(ids))
    selectionLimitReached.value = false
  }, { deep: true })

  watch(selectedLabels, (labels) => {
    sessionStorage.setItem(labelsKey, JSON.stringify(labels))
  }, { deep: true })

  const selectedCount = computed(() => selectedCandidatureIds.value.length)

  const selectedFaces = computed<SelectedFace[]>(() =>
    selectedCandidatureIds.value.map((id) => ({
      candidatureId: id,
      label: selectedLabels.value[id] ?? `#${id}`,
    }))
  )

  const pricing = computed<MissionPricingPreview | null>(() => {
    if (selectedCandidatureIds.value.length === 0) return null
    return computePricing(budgetParFace, selectedCandidatureIds.value.length)
  })

  function toggleSelection(id: string, label?: string): void {
    const idx = selectedCandidatureIds.value.indexOf(id)
    if (idx === -1) {
      if (maxSelection && selectedCandidatureIds.value.length >= maxSelection) {
        selectionLimitReached.value = true
        return
      }
      selectedCandidatureIds.value.push(id)
      if (label) selectedLabels.value[id] = label
    } else {
      selectedCandidatureIds.value.splice(idx, 1)
      delete selectedLabels.value[id]
    }
  }

  function removeSelection(id: string): void {
    const idx = selectedCandidatureIds.value.indexOf(id)
    if (idx !== -1) {
      selectedCandidatureIds.value.splice(idx, 1)
      delete selectedLabels.value[id]
    }
  }

  function isSelected(id: string): boolean {
    return selectedCandidatureIds.value.includes(id)
  }

  function clearSelection(): void {
    selectedCandidatureIds.value = []
    selectedLabels.value = {}
    sessionStorage.removeItem(storageKey)
    sessionStorage.removeItem(labelsKey)
  }

  async function confirmAndPay(missionId: string): Promise<{ checkout_url: string } | null> {
    if (selectedCandidatureIds.value.length === 0) return null

    isConfirming.value = true
    error.value = null

    try {
      const response = await missionApi.confirmSelection(missionId, selectedCandidatureIds.value)
      clearSelection()
      return { checkout_url: response.data.checkout_url }
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      // NOTE: the selection is intentionally NOT cleared here so the producer
      // can retry without re-picking faces after a failed initiation.
      // (FIX-19.3 AC #3 — Preserve selection for retry.)
      error.value =
        e?.response?.data?.message
          ?? 'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.'
      return null
    } finally {
      isConfirming.value = false
    }
  }

  function startPolling(
    missionId: string,
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
    selectedCount,
    selectedFaces,
    isSelectionMode,
    isConfirming,
    error,
    pricing,
    paymentStatus,
    selectionLimitReached,
    toggleSelection,
    removeSelection,
    isSelected,
    clearSelection,
    confirmAndPay,
    startPolling,
    stopPolling,
  }
}
