import { onActivated } from 'vue'

/**
 * Runs `refresh` each time a `<keep-alive>`-cached component is RE-activated
 * (i.e. the user navigates back to it), but NOT on its first activation — the
 * first one coincides with the initial mount, whose data load the component
 * already does in `onMounted`.
 *
 * For a component that is NOT wrapped in `<keep-alive>`, `onActivated` never
 * fires, so this is a no-op there.
 *
 * Use on cached listings whose rows can be mutated on a detail/child page
 * (accept a candidate, suspend a Face, …) so the restored list reflects the
 * change instead of showing stale data.
 */
export function useRefreshOnReturn(refresh: () => void | Promise<void>): void {
  let firstActivation = true

  onActivated(() => {
    if (firstActivation) {
      firstActivation = false
      return
    }
    void refresh()
  })
}
