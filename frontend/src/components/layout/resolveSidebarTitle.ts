import type { SidebarItem } from './DashboardSidebar.vue'

/**
 * Header title = label of the sidebar item owning the current path (longest matching
 * prefix, so /producer/missions/publish wins over /producer/missions).
 */
export function resolveSidebarTitle(
  items: ReadonlyArray<Pick<SidebarItem, 'label' | 'to' | 'match'>>,
  path: string,
  fallback = 'Tableau de bord',
): string {
  let best: { label: string; length: number } | null = null
  for (const item of items) {
    for (const prefix of [item.to, ...(item.match ?? [])]) {
      if ((path === prefix || path.startsWith(`${prefix}/`)) && (!best || prefix.length > best.length)) {
        best = { label: item.label, length: prefix.length }
      }
    }
  }
  return best?.label ?? fallback
}
