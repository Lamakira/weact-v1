import { ref, watch } from 'vue'

export type AgendaMobileView = 'month' | 'list'

const MOBILE_VIEW_KEY = 'weact:agenda:view-mobile'

function readMobileView(): AgendaMobileView {
  try {
    const stored = localStorage.getItem(MOBILE_VIEW_KEY)
    if (stored === 'month' || stored === 'list') return stored
  } catch {
    // Stockage indisponible : vue Mois.
  }
  return 'month'
}

/** Vue (Mois / Liste) de l'agenda téléphone, mémorisée à part de celle de l'ordinateur. */
export function useAgendaMobileView() {
  const mobileView = ref<AgendaMobileView>(readMobileView())

  watch(mobileView, (value) => {
    try {
      localStorage.setItem(MOBILE_VIEW_KEY, value)
    } catch {
      // Quota ou navigation privée : le choix reste valable pour la session.
    }
  })

  return { mobileView }
}
