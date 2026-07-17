import { onDeactivated } from 'vue'

/**
 * Closes an overlay rendered through `<Teleport to="body">` when the component
 * hosting it is DEACTIVATED by an ancestor `<keep-alive>`.
 *
 * Why: Vue moves a deactivated subtree into the keep-alive storage container,
 * but the teleported children are NOT moved out of `document.body` (verified in
 * the 3.5 runtime: `moveTeleport` in REORDER mode never walks the teleported
 * children). An overlay left open would therefore stay visible ON TOP of the
 * next page, with its listeners still active. Closing on deactivation restores
 * the pre-keep-alive semantics, where navigating away unmounted the overlay.
 *
 * No-op for components never rendered under a `<keep-alive>` (the hook simply
 * never fires), and unmount is unaffected (Teleport cleans up there already).
 */
export function useDismissOnDeactivate(isOpen: () => boolean, dismiss: () => void): void {
  onDeactivated(() => {
    if (isOpen()) dismiss()
  })
}
