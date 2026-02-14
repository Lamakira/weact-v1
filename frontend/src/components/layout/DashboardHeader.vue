<script setup lang="ts">
/**
 * DashboardHeader Component
 * Header with user info and actions for dashboard layouts.
 * Design: Creative Flow (Soft & Organic)
 */
import { Menu, LogOut, Loader2 } from 'lucide-vue-next'
import NotificationBell from '@/features/notification/components/NotificationBell.vue'
import { useSidebarState } from '@/composables/useSidebarState'

interface Props {
  title?: string
  userEmail?: string
  userName?: string
  avatarUrl?: string | null
  isLoggingOut?: boolean
  showNotifications?: boolean
}

withDefaults(defineProps<Props>(), {
  title: 'Dashboard',
  userEmail: '',
  userName: '',
  avatarUrl: null,
  isLoggingOut: false,
  showNotifications: true,
})

const emit = defineEmits<{
  logout: []
}>()

const { openMobile } = useSidebarState()

function handleLogout() {
  emit('logout')
}
</script>

<template>
  <header
    class="h-20 flex items-center justify-between px-6 lg:px-10 bg-white/50 backdrop-blur-sm relative z-20"
    data-testid="dashboard-header"
  >
    <!-- Left: Mobile menu + Title badge -->
    <div class="flex items-center gap-4">
      <!-- Mobile hamburger menu -->
      <button
        @click="openMobile"
        class="lg:hidden w-10 h-10 rounded-2xl bg-teal-50 flex items-center justify-center text-primary hover:bg-teal-100 transition-colors"
        aria-label="Ouvrir le menu"
        data-testid="header-menu-button"
      >
        <Menu class="w-5 h-5" />
      </button>

      <!-- Title badge -->
      <div
        class="px-3 py-1 min-[376px]:px-4 min-[376px]:py-1.5 rounded-full bg-teal-50 text-primary text-[10px] min-[376px]:text-xs font-bold uppercase tracking-wider"
        data-testid="header-title"
      >
        {{ title }}
      </div>
    </div>

    <!-- Right: User info + Actions -->
    <div class="flex items-center gap-3">
      <!-- User email (hidden on mobile) -->
      <span
        v-if="userEmail"
        class="hidden md:block text-sm text-slate-500"
        data-testid="header-user-email"
      >
        {{ userEmail }}
      </span>

      <!-- Notification bell (hidden on admin dashboards — uses user API client) -->
      <NotificationBell v-if="showNotifications" data-testid="header-notifications" />

      <!-- Logout button (desktop only — mobile logout is in sidebar) -->
      <button
        @click="handleLogout"
        :disabled="isLoggingOut"
        class="hidden lg:flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        aria-label="Se déconnecter"
        data-testid="header-logout-button"
      >
        <LogOut v-if="!isLoggingOut" class="w-4 h-4" />
        <Loader2 v-else class="w-4 h-4 animate-spin" />
        <span>{{ isLoggingOut ? 'Déconnexion...' : 'Déconnexion' }}</span>
      </button>

      <!-- User avatar -->
      <div
        class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center"
        :class="avatarUrl ? '' : 'bg-gradient-to-tr from-primary to-teal-300'"
        data-testid="header-avatar"
      >
        <img
          v-if="avatarUrl"
          :src="avatarUrl"
          :alt="userName || userEmail || 'User'"
          class="w-full h-full object-cover"
        />
        <span v-else class="text-white font-bold text-sm">
          {{ userName ? userName.charAt(0).toUpperCase() : userEmail ? userEmail.charAt(0).toUpperCase() : 'U' }}
        </span>
      </div>
    </div>
  </header>
</template>

<style scoped>
.text-primary {
  color: var(--color-weact, #198496);
}
.bg-primary {
  background-color: var(--color-weact, #198496);
}
.from-primary {
  --tw-gradient-from: var(--color-weact, #198496);
}
</style>
