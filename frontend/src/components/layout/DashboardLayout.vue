<script setup lang="ts">
/**
 * DashboardLayout Component
 * Main layout wrapper combining sidebar, header, and content area.
 * Design: Creative Flow (Soft & Organic)
 *
 * Features:
 * - Collapsible sidebar on desktop
 * - Hamburger menu with slide-in overlay on mobile
 * - Shared between Face and Producer dashboards
 */
import { watch, onMounted, onUnmounted } from 'vue'
import { X } from 'lucide-vue-next'
import DashboardSidebar, { type SidebarItem } from './DashboardSidebar.vue'
import DashboardHeader from './DashboardHeader.vue'
import { useSidebarState } from '@/composables/useSidebarState'
import logoPng from '@/assets/images/logonoir.png'

interface Props {
  sidebarItems: SidebarItem[]
  title?: string
  logoText?: string
  userEmail?: string
  userName?: string
  avatarUrl?: string | null
  isLoggingOut?: boolean
  showNotifications?: boolean
}

withDefaults(defineProps<Props>(), {
  title: 'Dashboard',
  logoText: 'WEACT',
  userEmail: '',
  userName: '',
  avatarUrl: null,
  isLoggingOut: false,
  showNotifications: true,
})

const emit = defineEmits<{
  logout: []
}>()

const { isMobileOpen, closeMobile, collapse } = useSidebarState()

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
  <div class="min-h-screen bg-gray-50" data-testid="dashboard-layout">
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
          class="fixed left-0 top-0 bottom-0 w-72 bg-white shadow-2xl z-50 lg:hidden flex flex-col"
          data-testid="mobile-sidebar"
        >
          <!-- Mobile sidebar header -->
          <div class="flex items-center justify-between p-4 border-b border-gray-100">
            <RouterLink to="/" class="flex items-center">
              <img :src="logoPng" alt="WEACT" class="h-8 w-auto" />
            </RouterLink>
            <button
              @click="closeMobile"
              class="w-10 h-10 rounded-2xl hover:bg-gray-100 flex items-center justify-center text-slate-400 transition-colors"
              aria-label="Fermer le menu"
              data-testid="mobile-sidebar-close"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Mobile sidebar navigation -->
          <nav class="flex-1 p-4 overflow-y-auto">
            <ul class="space-y-2">
              <li v-for="item in sidebarItems" :key="item.to">
                <RouterLink
                  :to="item.to"
                  class="flex items-center gap-3 px-4 py-3 rounded-2xl text-slate-600 hover:bg-teal-50 hover:text-primary transition-all"
                  active-class="bg-teal-50 text-primary font-medium"
                  @click="closeMobile"
                  :data-testid="`mobile-sidebar-item-${item.label.toLowerCase().replace(/\s+/g, '-')}`"
                >
                  <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                  <span class="text-sm">{{ item.label }}</span>
                  <span
                    v-if="item.badge && item.badge > 0"
                    class="ml-auto bg-primary text-white text-xs font-bold px-2 py-0.5 rounded-full"
                  >
                    {{ item.badge }}
                  </span>
                </RouterLink>
              </li>
            </ul>
          </nav>
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
      <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
        <!-- Header (sticky) -->
        <DashboardHeader
          :title="title"
          :user-email="userEmail"
          :user-name="userName"
          :avatar-url="avatarUrl"
          :is-logging-out="isLoggingOut"
          :show-notifications="showNotifications"
          class="flex-shrink-0"
          @logout="handleLogout"
        />

        <!-- Content (scrollable) -->
        <main
          class="flex-1 p-6 lg:p-12 overflow-y-auto overflow-x-hidden relative"
          data-testid="dashboard-content"
        >
          <!-- Background Decoration -->
          <div
            class="absolute top-0 right-0 w-96 h-96 bg-teal-50 rounded-full blur-3xl -mr-48 -mt-48 opacity-50 pointer-events-none"
            aria-hidden="true"
          />

          <!-- Content Slot -->
          <div class="relative z-10">
            <slot />
          </div>
        </main>
      </div>
    </div>
  </div>
</template>

<style scoped>
.text-primary {
  color: var(--color-weact, #198496);
}
.bg-primary {
  background-color: var(--color-weact, #198496);
}

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
