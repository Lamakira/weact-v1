import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Compteur « X à valider » Producteur = nb de livrables UGC in_review (inbox 4.4).
 * Source partagée + réactive pour la carte dashboard ; synchronisé par la page
 * validation et le dashboard à partir de listes déjà chargées (le badge de nav lit
 * `actionCounts`, calculé par COUNT côté serveur). Calque useNotificationStore.
 */
export const useUgcValidationCountStore = defineStore('ugcValidationCount', () => {
  const count = ref(0)

  // Sync depuis une liste déjà chargée (page validation) — 0 appel réseau (D-7.2.d).
  function setCount(n: number): void {
    count.value = Math.max(0, n)
  }

  function $reset(): void {
    count.value = 0
  }

  return { count, setCount, $reset }
})
