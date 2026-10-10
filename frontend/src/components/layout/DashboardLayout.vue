<script setup lang="ts">
/**
 * DashboardLayout Component
 * Main layout wrapper combining sidebar, header, and content area.
 * Design: Régie (dense, filets 1 px, couleur réservée aux états et à l'action)
 *
 * Features:
 * - Collapsible sidebar on desktop
 * - Hamburger menu with slide-in overlay on mobile (every destination)
 * - Optional bottom tab bar on mobile (`mobileTabs`: the 4 main destinations)
 * - Shared between Face and Producer dashboards
 */
import { ref, watch, nextTick, onMounted, onUnmounted, provide } from 'vue'
import { useRoute } from 'vue-router'
import { X, LogOut, Loader2 } from 'lucide-vue-next'
import DashboardSidebar, { type SidebarItem } from './DashboardSidebar.vue'
import DashboardHeader from './DashboardHeader.vue'
import DashboardMobileTabs from './DashboardMobileTabs.vue'
import { useSidebarState } from '@/composables/useSidebarState'
import logoPng from '@/assets/images/logonoir.png'
import {
  restoreDashboardScrollKey,
  type DashboardScrollDestination,
} from './dashboardScrollRestoration'

interface Props {
  sidebarItems: SidebarItem[]
  /** Destinations principales de la barre d'onglets mobile (absente = pas de barre) */
  mobileTabs?: SidebarItem[]
  title?: string
  logoText?: string
  userEmail?: string
  userName?: string
  avatarUrl?: string | null
  isLoggingOut?: boolean
  showNotifications?: boolean
  profileRoute?: string
}

withDefaults(defineProps<Props>(), {
  mobileTabs: () => [],
  title: 'Dashboard',
  logoText: 'WEACT',
  userEmail: '',
  userName: '',
  avatarUrl: null,
  isLoggingOut: false,
  showNotifications: true,
  profileRoute: '/face/profile',
})

const emit = defineEmits<{
  logout: []
}>()

const { isMobileOpen, closeMobile, collapse } = useSidebarState()

const route = useRoute()

// The <main> below is the dashboards' ONLY scroller (the wrapper is h-screen
// overflow-hidden, the window never scrolls) and this layout now persists
// across child navigations (App.vue keys it by the layout's own route), so
// nothing resets its scrollTop anymore. Handle it per navigation: a page
// cached by <keep-alive> gets its saved offset back on return; any other page
// starts at the top. router.scrollBehavior can't do this — it only drives the
// window scroller.
const contentEl = ref<HTMLElement | null>(null)
const savedScrollPositions = new Map<string, number>()

function restoreScroll(destination: DashboardScrollDestination): void {
  // Ignore a nextTick or transition hook belonging to a navigation that was
  // superseded before it completed.
  if (route.fullPath !== destination.fullPath) return

  const target = contentEl.value
  if (!target) return
  target.scrollTop = destination.keepAlive
    ? (savedScrollPositions.get(destination.fullPath) ?? 0)
    : 0
}

// KeepAliveRouterView owns the child-route transition. Its after-enter hook
// calls this once the destination content exists, so an early assignment that
// the browser clamped while mode="out-in" was between pages is not final.
provide(restoreDashboardScrollKey, restoreScroll)

watch(
  () => ({ fullPath: route.fullPath, path: route.path }),
  (to, from) => {
    const el = contentEl.value
    if (!el) return
    // Pre-flush: the DOM still shows the page being left — save its offset.
    if (from) savedScrollPositions.set(from.fullPath, el.scrollTop)

    // Query-only navigation on a route that opted in via
    // meta.preserveScrollOnQueryChange (profile tabs): the query carries view
    // state, not a new page — the component is not even remounted, since
    // KeepAliveRouterView keys its branches by route.name / route.path, so no
    // transition runs either. Keep the reading position. Without this, a tab
    // click throws the <main> back to the top — invisible on desktop where the
    // tabs sit high, but on the stacked mobile layout the user lands above the
    // fold and has to scroll all the way down to the tabs again. Paginated
    // lists (?page=) deliberately do NOT opt in: they want the top of the list.
    if (from && to.path === from.path && route.meta.preserveScrollOnQueryChange) {
      // Keep the offset addressable under the new fullPath so a later return to
      // this exact URL (keep-alive) restores where the user actually was.
      savedScrollPositions.set(to.fullPath, el.scrollTop)
      return
    }

    // Retain the immediate reset for DashboardLayout consumers without the
    // shared transition outlet; transitioned dashboards restore again after
    // enter through the provided callback above.
    const destination = { fullPath: to.fullPath, keepAlive: Boolean(route.meta.keepAlive) }
    void nextTick(() => restoreScroll(destination))
  },
)

/** Close mobile sidebar when clicking outside */
function handleBackdropClick() {
  closeMobile()
}

/** Close mobile sidebar on escape key */
function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && isMobileOpen.value) {
    closeMobile()
  }
}

/** Handle responsive behavior on resize */
function handleResize() {
  if (window.innerWidth < 1024) {
    collapse()
  }
  if (window.innerWidth >= 768) {
    closeMobile()
  }
}

// Close mobile menu on route change
watch(
  () => isMobileOpen.value,
  (isOpen) => {
    if (isOpen) {
      document.body.style.overflow = 'hidden'
    } else {
      document.body.style.overflow = ''
    }
  }
)

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
  window.addEventListener('resize', handleResize)
  handleResize() // Initial check
  closeMobile() // Ensure mobile sidebar is closed on mount/navigation
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('resize', handleResize)
  document.body.style.overflow = ''
})

function handleLogout() {
  emit('logout')
}
</script>

<template>
  <div class="min-h-screen bg-canvas" data-testid="dashboard-layout">
    <!-- Mobile Sidebar Overlay -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="isMobileOpen"
          class="fixed inset-0 bg-black/30 backdrop-blur-sm z-40 lg:hidden"
          @click="handleBackdropClick"
          data-testid="sidebar-backdrop"
        />
      </Transition>

      <Transition name="slide">
        <aside
          v-if="isMobileOpen"
          class="fixed left-0 top-0 bottom-0 w-72 bg-sidebar shadow-2xl z-50 lg:hidden flex flex-col"
          data-testid="mobile-sidebar"
        >
          <!-- Mobile sidebar header -->
          <div class="flex items-center justify-between p-4 border-b border-line">
            <RouterLink to="/" class="flex items-center">
              <img :src="logoPng" alt="WEACT" class="h-8 w-auto" />
            </RouterLink>
            <button
              @click="closeMobile"
              class="w-11 h-11 rounded-lg hover:bg-white flex items-center justify-center text-ink-3 transition-colors"
              aria-label="Fermer le menu"
              data-testid="mobile-sidebar-close"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Mobile sidebar navigation -->
          <nav class="flex-1 p-4 overflow-y-auto">
            <ul class="space-y-0.5">
              <li v-for="item in sidebarItems" :key="item.to">
                <RouterLink
                  :to="item.to"
                  class="flex min-h-11 items-center gap-2.5 px-3 rounded-lg text-dash text-ink-2 hover:bg-white/70 hover:text-ink transition-colors"
                  active-class="!bg-white !text-ink font-semibold ring-1 ring-line [&_svg]:text-weact-700"
                  @click="closeMobile"
                  :data-testid="`mobile-sidebar-item-${item.label.toLowerCase().replace(/\s+/g, '-')}`"
                >
                  <component :is="item.icon" class="w-[18px] h-[18px] flex-shrink-0" />
                  <span>{{ item.label }}</span>
                  <span
                    v-if="item.badge && item.badge > 0"
                    class="ml-auto inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-weact-600 px-1.5 text-[11px] font-semibold text-white"
                  >
                    {{ item.badge }}
                  </span>
                </RouterLink>
              </li>
            </ul>
          </nav>

          <!-- Mobile sidebar logout -->
          <div class="p-4 border-t border-line">
            <button
              @click="handleLogout"
              :disabled="isLoggingOut"
              class="flex min-h-11 items-center gap-2.5 w-full px-3 rounded-lg text-dash text-ink-2 hover:bg-red-50 hover:text-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              data-testid="mobile-sidebar-logout"
            >
              <LogOut v-if="!isLoggingOut" class="w-[18px] h-[18px] flex-shrink-0" />
              <Loader2 v-else class="w-[18px] h-[18px] flex-shrink-0 animate-spin" />
              <span>{{ isLoggingOut ? 'Déconnexion...' : 'Déconnexion' }}</span>
            </button>
          </div>
        </aside>
      </Transition>
    </Teleport>

    <!-- Main Layout Container -->
    <div
      class="flex min-h-screen bg-white"
      data-testid="layout-container"
    >
      <!-- Desktop Sidebar -->
      <DashboardSidebar
        :items="sidebarItems"
        :logo-text="logoText"
        class="hidden lg:flex"
      />

      <!-- Main Content Area -->
      <div class="flex-1 flex flex-col min-w-0 h-dvh overflow-hidden">
        <!-- Header (sticky) -->
        <DashboardHeader
          :title="title"
          :user-email="userEmail"
          :user-name="userName"
          :avatar-url="avatarUrl"
          :is-logging-out="isLoggingOut"
          :show-notifications="showNotifications"
          :profile-route="profileRoute"
          class="flex-shrink-0"
          @logout="handleLogout"
        >
          <template v-if="$slots['header-actions']" #actions>
            <slot name="header-actions" />
          </template>
        </DashboardHeader>

        <!-- Content (scrollable) -->
        <main
          ref="contentEl"
          class="flex-1 p-4 sm:p-6 lg:p-7 overflow-y-auto overflow-x-hidden relative text-dash"
          data-testid="dashboard-content"
        >
          <!-- Content Slot -->
          <div class="relative z-10 max-w-7xl mx-auto">
            <slot />
          </div>
        </main>

        <!-- Bottom tab bar (mobile only): the main destinations; the drawer keeps the rest -->
        <DashboardMobileTabs v-if="mobileTabs.length > 0" :tabs="mobileTabs" />
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s ease;
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(-100%);
}
</style>
