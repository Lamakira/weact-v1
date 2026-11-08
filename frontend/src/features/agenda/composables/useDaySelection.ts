import { onUnmounted, ref } from 'vue'
import { eachDay } from '../utils/agendaDates'

interface Options {
  isTouchable: (iso: string) => boolean
  selectionMode: () => boolean
  selected: () => string[]
  /** Jours touchables à sélectionner ; `extend` = ajouter à la sélection existante. */
  onRange: (days: string[], extend: boolean) => void
  /** Simple clic (sans glisser). */
  onDay: (iso: string) => void
}

/**
 * Sélection à la souris d'une grille de jours : appui sur un jour touchable, glisser, relâcher
 * sélectionne les jours touchables entre les deux ; Maj + clic étend depuis le dernier jour choisi.
 * Les écouteurs `window` n'existent que le temps d'un glisser.
 */
export function useDaySelection(options: Options) {
  const dragStart = ref<string | null>(null)
  const dragEnd = ref<string | null>(null)

  function touchableBetween(a: string, b: string): string[] {
    const [lo, hi] = a <= b ? [a, b] : [b, a]
    return eachDay(lo, hi).filter(options.isTouchable)
  }

  function stopDrag(): void {
    window.removeEventListener('mouseup', onWindowMouseUp)
    dragStart.value = null
    dragEnd.value = null
  }

  function onWindowMouseUp(): void {
    const start = dragStart.value
    const end = dragEnd.value
    stopDrag()
    if (start && end && start !== end) options.onRange(touchableBetween(start, end), false)
  }

  function onMouseDown(iso: string, event: MouseEvent): void {
    if (event.button !== 0 || event.shiftKey || !options.isTouchable(iso)) return
    dragStart.value = iso
    dragEnd.value = iso
    window.addEventListener('mouseup', onWindowMouseUp)
  }

  function onMouseEnter(iso: string): void {
    if (dragStart.value) dragEnd.value = iso
  }

  function onClick(iso: string, event: MouseEvent): void {
    if (event.shiftKey && options.isTouchable(iso)) {
      const current = options.selected()
      const anchor = current[current.length - 1]
      options.onRange(anchor ? touchableBetween(anchor, iso) : [iso], true)
      return
    }
    if (options.selectionMode() && !options.isTouchable(iso)) return
    options.onDay(iso)
  }

  /** Jours touchables couverts par le glisser en cours (aperçu). */
  function dragDays(): string[] {
    return dragStart.value && dragEnd.value && dragStart.value !== dragEnd.value
      ? touchableBetween(dragStart.value, dragEnd.value)
      : []
  }

  onUnmounted(stopDrag)

  return { onMouseDown, onMouseEnter, onClick, dragDays }
}
