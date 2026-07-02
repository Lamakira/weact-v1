import { defineStore } from 'pinia'
import { ref } from 'vue'
import { producerApi } from '@/features/producer/services/producerApi'

/**
 * Compteur « X à valider » Producteur = nb de livrables UGC in_review (inbox 4.4).
 * Source partagée + réactive pour le badge de nav (ProducerLayout) et la carte
 * dashboard ; re-synchronisé par la page validation. Calque useNotificationStore.
 */
export const useUgcValidationCountStore = defineStore('ugcValidationCount', () => {
  const count = ref(0)

  // Recharge depuis l'inbox in_review (count = data.length). Non-fatal (D-7.2.e).
  async function fetchCount(): Promise<void> {
    try {
      const response = await producerApi.listDeliverablesToReview()
      count.value = response.data.length
    } catch (error) {
      console.error('[UgcValidationCountStore] Failed to fetch validation count:', error)
    }
  }

  // Sync depuis une liste déjà chargée (page validation) — 0 appel réseau (D-7.2.d).
  function setCount(n: number): void {
    count.value = Math.max(0, n)
  }

  function $reset(): void {
    count.value = 0
  }

  return { count, fetchCount, setCount, $reset }
})
