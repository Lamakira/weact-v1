<script setup lang="ts">
/**
 * DashboardSidebar Component
 * Collapsible sidebar navigation for dashboard layouts.
 * Design: Creative Flow (Soft & Organic)
 */
import { type Component } from 'vue'
import { useRoute } from 'vue-router'
import { Home, PanelLeftClose } from 'lucide-vue-next'
import { useSidebarState } from '@/composables/useSidebarState'
import logoPng from '@/assets/images/logonoir.png'
import logoMark from '@/assets/images/logo-mark.svg'

export interface SidebarItem {
  label: string
  icon: Component
  to: string
  badge?: number
}

interface Props {
  items: SidebarItem[]
  logoText?: string
}

withDefaults(defineProps<Props>(), {
  logoText: 'WEACT',
})

const route = useRoute()
const { isExpanded, toggle } = useSidebarState()

/** Check if a route is active (exact match only) */
const isActive = (path: string) => {
  return route.path === path
}

/** Item classes: identical colors whether the sidebar is expanded or collapsed */
const getItemClasses = (item: SidebarItem) => {
  return isActive(item.to)
    ? 'bg-white text-ink font-semibold ring-1 ring-line [&_svg]:text-weact-700'
    : 'text-ink-2 hover:bg-white/70 hover:text-ink'
}
</script>

<template>
  <aside
    class="flex-none bg-sidebar border-r border-line flex flex-col transition-all duration-300 ease-in-out sticky top-0 h-screen"
    :class="isExpanded ? 'w-64' : 'w-20'"
    data-testid="dashboard-sidebar"
  >
    <!-- Header: full logo + collapse button (expanded) / W mark that expands (collapsed) -->
    <div
      class="flex items-center py-5"
      :class="isExpanded ? 'px-6 justify-between gap-2' : 'px-4 justify-center'"
      data-testid="sidebar-header"
    >
      <template v-if="isExpanded">
        <RouterLink to="/" class="flex items-center" data-testid="sidebar-logo">
          <img :src="logoPng" alt="WEACT" class="h-8 w-auto" />
        </RouterLink>
        <button
          type="button"
          @click="toggle"
          class="flex h-9 w-9 flex-none items-center justify-center rounded-lg text-ink-3 hover:bg-white hover:text-ink transition-colors"
          data-testid="sidebar-toggle"
          aria-label="Réduire la barre latérale"
          title="Réduire la barre latérale"
        >
          <PanelLeftClose class="w-5 h-5" />
        </button>
      </template>
      <button
        v-else
        type="button"
        @click="toggle"
        class="flex h-11 w-11 items-center justify-center rounded-lg hover:bg-white transition-colors"
        data-testid="sidebar-logo-mark"
        aria-label="Agrandir la barre latérale"
        title="Agrandir la barre latérale"
      >
        <img :src="logoMark" alt="" class="h-7 w-auto" />
      </button>
    </div>

    <!-- Navigation Items -->
    <nav class="flex-1 px-3 py-4 overflow-y-auto" data-testid="sidebar-nav">
      <ul class="space-y-0.5">
        <li v-for="item in items" :key="item.to">
          <RouterLink
            :to="item.to"
            class="flex items-center rounded-lg text-dash transition-colors duration-200 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600"
            :class="[
              getItemClasses(item),
              isExpanded ? 'h-9 px-2.5 gap-2.5' : 'w-11 h-11 justify-center mx-auto',
            ]"
            :data-testid="`sidebar-item-${item.label.toLowerCase().replace(/\s+/g, '-')}`"
          >
            <component :is="item.icon" class="w-[18px] h-[18px] flex-shrink-0" />
            <span
              v-if="isExpanded"
              class="truncate"
            >
              {{ item.label }}
            </span>
            <span
              v-if="item.badge && item.badge > 0 && isExpanded"
              class="ml-auto inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-weact-600 px-1.5 text-[11px] font-semibold text-white"
              data-testid="sidebar-badge"
            >
              {{ item.badge }}
            </span>
          </RouterLink>
        </li>
      </ul>
    </nav>

    <!-- Bottom Actions -->
    <div class="p-3 border-t border-line space-y-2">
      <!-- Back to site -->
      <RouterLink
        to="/"
        class="flex items-center rounded-lg h-9 text-dash text-ink-3 hover:bg-white hover:text-ink transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600"
        :class="isExpanded ? 'px-2.5 gap-2.5' : 'justify-center'"
        data-testid="sidebar-back-to-site"
      >
        <Home class="w-[18px] h-[18px] flex-shrink-0" />
        <span v-if="isExpanded">Retour au site</span>
      </RouterLink>
    </div>
  </aside>
</template>
