<script setup lang="ts">
/**
 * DashboardMobileTabs
 * Barre d'onglets du bas (mobile) pour les dashboards Face et Producteur :
 * les 4 destinations principales. Le tiroir (hamburger) reste la porte vers
 * tous les autres items. Masquée dès lg, où la barre latérale prend le relais.
 */
import { useRoute } from 'vue-router'
import type { SidebarItem } from './DashboardSidebar.vue'

interface Props {
  tabs: SidebarItem[]
}

defineProps<Props>()

const route = useRoute()

/** Actif sur la route exacte ou une sous-route (ex. détail d'une mission) */
function isActive(tab: SidebarItem): boolean {
  return [tab.to, ...(tab.match ?? [])].some((p) => route.path === p || route.path.startsWith(`${p}/`))
}

function slug(label: string): string {
  return label.toLowerCase().replace(/\s+/g, '-')
}

function accessibleName(tab: SidebarItem): string | undefined {
  return tab.badge && tab.badge > 0 ? `${tab.label}, ${tab.badge} non lus` : undefined
}
</script>

<template>
  <nav
    class="flex-shrink-0 grid border-t border-line bg-white pt-1 pb-[max(0.25rem,env(safe-area-inset-bottom))] lg:hidden"
    :style="{ gridTemplateColumns: `repeat(${tabs.length}, minmax(0, 1fr))` }"
    aria-label="Navigation principale"
    data-testid="mobile-tabbar"
  >
    <RouterLink
      v-for="tab in tabs"
      :key="tab.to"
      :to="tab.to"
      class="relative flex min-h-11 flex-col items-center justify-center gap-0.5 text-[10.5px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-weact-600"
      :class="isActive(tab) ? 'font-semibold text-weact-700' : 'text-ink-3'"
      :aria-current="isActive(tab) ? 'page' : undefined"
      :aria-label="accessibleName(tab)"
      :data-testid="`mobile-tab-${slug(tab.label)}`"
    >
      <component :is="tab.icon" class="size-[18px]" aria-hidden="true" />
      <span>{{ tab.label }}</span>
      <span
        v-if="tab.badge && tab.badge > 0"
        class="absolute left-[54%] top-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-weact-600 px-1 text-[9.5px] font-semibold text-white"
        aria-hidden="true"
        data-testid="mobile-tab-badge"
      >
        {{ tab.badge }}
      </span>
    </RouterLink>
  </nav>
</template>
