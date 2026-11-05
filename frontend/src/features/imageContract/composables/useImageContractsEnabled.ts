import { computed, type ComputedRef } from 'vue'
import { useAuthStore } from '@/stores/auth'

/** Drapeau serveur : faux ou absent, aucun écran du contrat d'image ne s'affiche. */
export function useImageContractsEnabled(): ComputedRef<boolean> {
  const authStore = useAuthStore()
  return computed(() => authStore.user?.image_contracts_enabled === true)
}
