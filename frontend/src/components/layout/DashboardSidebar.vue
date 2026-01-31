<script setup lang="ts">
/**
 * DashboardSidebar Component
 * Collapsible sidebar navigation for dashboard layouts.
 * Design: Creative Flow (Soft & Organic)
 */
import { computed, type Component } from 'vue'
import { useRoute } from 'vue-router'
import { ChevronLeft, ChevronRight, Home } from 'lucide-vue-next'
import { useSidebarState } from '@/composables/useSidebarState'
import logoPng from '@/assets/images/logonoir.png'

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

const props = withDefaults(defineProps<Props>(), {
  logoText: 'WEACT',
})

const route = useRoute()
const { isExpanded, toggle } = useSidebarState()

/** Check if a route is active */
const isActive = (path: string) => {
  return route.path === path || route.path.startsWith(path + '/')
}

/** Get item classes based on active state */
const getItemClasses = (item: SidebarItem) => {
  const active = isActive(item.to)
  if (isExpanded.value) {
    return active
      ? 'bg-primary/10 text-primary font-medium'
      : 'text-slate-600 hover:bg-gray-50 hover:text-primary'
  }
  return active
    ? 'bg-primary/10 text-primary'
    : 'text-slate-400 hover:bg-gray-50 hover:text-primary'
}
</script>

<template>
  <aside
    class="flex-none bg-white border-r border-gray-100 flex flex-col transition-all duration-300 ease-in-out sticky top-0 h-screen"
    :class="isExpanded ? 'w-64' : 'w-20'"
    data-testid="dashboard-sidebar"
  >
    <!-- Logo -->
    <div
      class="flex items-center py-6 border-b border-gray-100"
      :class="isExpanded ? 'px-6' : 'px-4 justify-center'"
    >
      <RouterLink to="/" class="flex items-center" data-testid="sidebar-logo">
        <img
          :src="logoPng"
          alt="WEACT"
          :class="isExpanded ? 'h-8 w-auto' : 'h-7 w-auto'"
        />
      </RouterLink>
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

      <!-- Collapse Toggle -->
      <button
        @click="toggle"
        class="w-full flex items-center rounded-xl py-2.5 text-slate-400 hover:bg-gray-50 hover:text-slate-600 transition-all"
        :class="isExpanded ? 'px-4 gap-3' : 'justify-center'"
        data-testid="sidebar-toggle"
        :aria-label="isExpanded ? 'Réduire le menu' : 'Agrandir le menu'"
      >
        <ChevronLeft v-if="isExpanded" class="w-5 h-5 flex-shrink-0" />
        <ChevronRight v-else class="w-5 h-5 flex-shrink-0" />
        <span v-if="isExpanded" class="text-sm font-medium">Réduire</span>
      </button>
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
