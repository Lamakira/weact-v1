import { readonly, ref } from 'vue'

/**
 * Barre de progression de navigation (façon YouTube / NProgress).
 *
 * Comme on ne connaît pas la durée réelle d'un changement de route (chunk lazy,
 * guards, résolution du composant), la progression est *simulée* : elle avance
 * vite au début puis « trickle » (ralentit) en approchant d'un plafond, puis
 * saute à 100 % et disparaît une fois la navigation confirmée.
 *
 * L'état est au niveau module (singleton) pour être partagé entre les hooks du
 * router (`start` / `done`) et le composant d'affichage `NavigationProgress.vue`.
 */

const percent = ref(0)
const visible = ref(false)

let trickleTimer: ReturnType<typeof setInterval> | null = null
let fadeTimer: ReturnType<typeof setTimeout> | null = null

function clearTrickle() {
  if (trickleTimer !== null) {
    clearInterval(trickleTimer)
    trickleTimer = null
  }
}

function clearFade() {
  if (fadeTimer !== null) {
    clearTimeout(fadeTimer)
    fadeTimer = null
  }
}

/** Incrément décroissant à mesure qu'on approche du plafond — imite le trickle NProgress. */
function nextIncrement(current: number): number {
  if (current < 20) return 8
  if (current < 50) return 4
  if (current < 80) return 2
  if (current < 90) return 0.5
  return 0.1
}

/** Démarre (ou relance) la barre. Idempotent : conserve la progression d'une navigation déjà en cours. */
function start() {
  clearFade()
  clearTrickle()

  // Navigation déjà en vol (ex : redirection via un guard) → on garde la progression.
  // Sinon on repart d'une petite valeur visible.
  if (!visible.value || percent.value >= 100) {
    percent.value = 8
  }
  visible.value = true

  trickleTimer = setInterval(() => {
    percent.value = Math.min(percent.value + nextIncrement(percent.value), 95)
    if (percent.value >= 95) {
      clearTrickle()
    }
  }, 300)
}

/** Termine la barre : saut à 100 %, fondu, puis remise à zéro invisible. */
function done() {
  clearTrickle()
  if (!visible.value) {
    return
  }

  percent.value = 100
  clearFade()
  // Laisse la largeur atteindre 100 %, puis lance le fondu de sortie.
  fadeTimer = setTimeout(() => {
    visible.value = false
    // Remet la largeur à zéro seulement une fois totalement transparent,
    // pour ne jamais voir la barre « rembobiner ».
    fadeTimer = setTimeout(() => {
      percent.value = 0
    }, 300)
  }, 250)
}

export function useNavigationProgress() {
  return {
    percent: readonly(percent),
    visible: readonly(visible),
    start,
    done,
  }
}
