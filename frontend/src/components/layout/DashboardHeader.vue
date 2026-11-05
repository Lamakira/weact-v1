<script setup lang="ts">
/**
 * DashboardHeader Component
 * Header with user info and actions for dashboard layouts.
 * Design: Régie — titre de page à gauche, actions secondaires (slot `actions`),
 * cloche, avatar.
 */
import { Menu } from 'lucide-vue-next'
import NotificationBell from '@/features/notification/components/NotificationBell.vue'
import UserAvatarMenu from './UserAvatarMenu.vue'
import { useSidebarState } from '@/composables/useSidebarState'

interface Props {
  title?: string
  userEmail?: string
  userName?: string
  avatarUrl?: string | null
  isLoggingOut?: boolean
  showNotifications?: boolean
  profileRoute?: string
}

withDefaults(defineProps<Props>(), {
  title: 'Dashboard',
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

const { openMobile } = useSidebarState()
</script>

<template>
  <header
    class="h-16 flex items-center justify-between px-4 lg:px-7 border-b border-line bg-white relative z-20"
    data-testid="dashboard-header"
  >
    <!-- Left: Mobile menu + Title badge -->
    <div class="flex min-w-0 items-center gap-4">
      <!-- Mobile hamburger menu -->
      <button
        @click="openMobile"
        class="lg:hidden w-11 h-11 -ml-2 rounded-lg flex items-center justify-center text-ink-2 hover:bg-sidebar hover:text-ink transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600"
        aria-label="Ouvrir le menu"
        data-testid="header-menu-button"
      >
        <Menu class="w-5 h-5" />
      </button>

      <!-- Title badge -->
      <div
        class="truncate text-[17px] font-semibold tracking-[-0.02em] text-ink"
        data-testid="header-title"
      >
        {{ title }}
      </div>
    </div>

    <!-- Right: User info + Actions -->
    <div class="flex flex-shrink-0 items-center gap-2 sm:gap-3">
      <!-- Secondary page actions (slot, optional) -->
      <slot name="actions" />

      <!-- User email (hidden on mobile) -->
      <span
        v-if="userEmail"
        class="hidden md:block text-dash text-ink-3"
        data-testid="header-user-email"
      >
        {{ userEmail }}
      </span>

      <!-- Notification bell (hidden on admin dashboards — uses user API client) -->
      <NotificationBell v-if="showNotifications" data-testid="header-notifications" />

      <!-- Avatar with dropdown menu -->
      <UserAvatarMenu
        :user-name="userName"
        :user-email="userEmail"
        :avatar-url="avatarUrl"
        :profile-route="profileRoute"
        :is-logging-out="isLoggingOut"
        @logout="emit('logout')"
      />
    </div>
  </header>
</template>
