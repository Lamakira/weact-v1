import type { SidebarItem } from './DashboardSidebar.vue'

/**
 * Header title = label of the sidebar item owning the current path (longest matching
 * prefix, so /producer/missions/publish wins over /producer/missions).
 */
export function resolveSidebarTitle(
  items: ReadonlyArray<Pick<SidebarItem, 'label' | 'to'>>,
  path: string,
  fallback = 'Tableau de bord',
): string {
  const owner = items
    .filter((i) => path === i.to || path.startsWith(`${i.to}/`))
    .sort((a, b) => b.to.length - a.to.length)[0]
  return owner?.label ?? fallback
}
