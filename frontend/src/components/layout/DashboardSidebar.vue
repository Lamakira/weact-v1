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
    ? 'bg-primary/10 text-primary font-medium'
    : 'text-slate-600 hover:bg-gray-50 hover:text-primary'
}
</script>

<template>
  <aside
    class="flex-none bg-white border-r border-gray-100 flex flex-col transition-all duration-300 ease-in-out sticky top-0 h-screen"
    :class="isExpanded ? 'w-64' : 'w-20'"
    data-testid="dashboard-sidebar"
  >
    <!-- Header: full logo + collapse button (expanded) / W mark that expands (collapsed) -->
    <div
      class="flex items-center py-6 border-b border-gray-100"
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
          class="flex h-9 w-9 flex-none items-center justify-center rounded-lg text-slate-500 hover:bg-gray-50 hover:text-slate-700 transition-colors"
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
        class="flex h-11 w-11 items-center justify-center rounded-lg hover:bg-gray-50 transition-colors"
        data-testid="sidebar-logo-mark"
        aria-label="Agrandir la barre latérale"
        title="Agrandir la barre latérale"
      >
        <img :src="logoMark" alt="" class="h-7 w-auto" />
      </button>
    </div>

    <!-- Navigation Items -->
    <nav class="flex-1 px-3 py-4 overflow-y-auto" data-testid="sidebar-nav">
      <ul class="space-y-1">
        <li v-for="item in items" :key="item.to">
          <RouterLink
            :to="item.to"
            class="flex items-center rounded-xl transition-all duration-200 cursor-pointer"
            :class="[
              getItemClasses(item),
              isExpanded ? 'px-4 py-2.5 gap-3' : 'w-11 h-11 justify-center mx-auto',
            ]"
            :data-testid="`sidebar-item-${item.label.toLowerCase().replace(/\s+/g, '-')}`"
          >
            <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
            <span
              v-if="isExpanded"
              class="text-sm truncate"
            >
              {{ item.label }}
            </span>
            <span
              v-if="item.badge && item.badge > 0 && isExpanded"
              class="ml-auto bg-primary text-white text-xs font-bold px-2 py-0.5 rounded-full"
              data-testid="sidebar-badge"
            >
              {{ item.badge }}
            </span>
          </RouterLink>
        </li>
      </ul>
    </nav>

    <!-- Bottom Actions -->
    <div class="p-4 border-t border-gray-100 space-y-2">
      <!-- Back to site -->
      <RouterLink
        to="/"
        class="flex items-center rounded-xl py-2.5 text-slate-500 hover:bg-gray-50 hover:text-primary transition-all"
        :class="isExpanded ? 'px-4 gap-3' : 'justify-center'"
        data-testid="sidebar-back-to-site"
      >
        <Home class="w-5 h-5 flex-shrink-0" />
        <span v-if="isExpanded" class="text-sm font-medium">Retour au site</span>
      </RouterLink>
    </div>
  </aside>
</template>

<style scoped>
.text-primary {
  color: var(--color-weact, #198496);
}
.bg-primary {
  background-color: var(--color-weact, #198496);
}
</style>
