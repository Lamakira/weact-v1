<script setup lang="ts">
/**
 * DashboardHeader Component
 * Header with user info and actions for dashboard layouts.
 * Design: Creative Flow (Soft & Organic)
 */
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { Menu, LogOut, Loader2, User } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'
import NotificationBell from '@/features/notification/components/NotificationBell.vue'
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

// Avatar dropdown state
const isDropdownOpen = ref(false)
const dropdownRef = ref<HTMLElement | null>(null)
const avatarButtonRef = ref<HTMLElement | null>(null)

function toggleDropdown() {
  isDropdownOpen.value = !isDropdownOpen.value
}

function closeDropdown() {
  isDropdownOpen.value = false
}

function handleLogout() {
  closeDropdown()
  emit('logout')
}

// Focus first item when dropdown opens
watch(isDropdownOpen, async (isOpen) => {
  if (isOpen) {
    await nextTick()
    const firstItem = dropdownRef.value?.querySelector('a, button') as HTMLElement | null
    firstItem?.focus()
  }
})

// Close on Escape key
function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && isDropdownOpen.value) {
    closeDropdown()
    avatarButtonRef.value?.focus()
  }
}

// Close on click outside
function handleClickOutside(event: MouseEvent) {
  if (!isDropdownOpen.value) return
  const target = event.target as Node
  if (!dropdownRef.value?.contains(target) && !avatarButtonRef.value?.contains(target)) {
    closeDropdown()
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.removeEventListener('click', handleClickOutside)
})
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
        class="lg:hidden w-10 h-10 rounded-2xl flex items-center justify-center text-primary hover:text-primary/70 transition-colors"
        aria-label="Ouvrir le menu"
        data-testid="header-menu-button"
      >
        <Menu class="w-5 h-5" />
      </button>

      <!-- Title badge -->
      <div
        class="text-primary text-sm font-semibold"
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

      <!-- Avatar with dropdown menu -->
      <div class="relative inline-block">
        <!-- Avatar Trigger -->
        <button
          ref="avatarButtonRef"
          @click="toggleDropdown"
          type="button"
          class="relative flex h-10 w-10 items-center justify-center rounded-full overflow-hidden transition-all hover:ring-2 hover:ring-[#198496]/30 focus:outline-none focus:ring-2 focus:ring-[#198496] focus:ring-offset-2"
          :class="avatarUrl ? '' : 'bg-gradient-to-tr from-primary to-teal-300'"
          aria-haspopup="true"
          :aria-expanded="isDropdownOpen"
          aria-label="Menu utilisateur"
          data-testid="header-avatar"
        >
          <img
            v-if="avatarUrl"
            :src="avatarUrl"
            :alt="userName || userEmail || 'User'"
            class="h-full w-full object-cover"
          />
          <span v-else class="text-white font-bold text-sm">
            {{ userName ? userName.charAt(0).toUpperCase() : userEmail ? userEmail.charAt(0).toUpperCase() : 'U' }}
          </span>
        </button>

        <!-- Dropdown Menu -->
        <Transition
          enter-active-class="transition duration-300 ease-out"
          enter-from-class="transform scale-95 opacity-0"
          enter-to-class="transform scale-100 opacity-100"
          leave-active-class="transition duration-200 ease-in"
          leave-from-class="transform scale-100 opacity-100"
          leave-to-class="transform scale-95 opacity-0"
        >
          <div
            v-if="isDropdownOpen"
            ref="dropdownRef"
            class="absolute right-0 mt-3 w-48 origin-top-right rounded-md border border-gray-200 bg-white shadow-lg z-50"
            data-testid="avatar-dropdown"
          >
            <!-- Pointer arrow -->
            <div class="absolute -top-1.5 right-4 h-3 w-3 rotate-45 border-l border-t border-gray-200 bg-white"></div>

            <div class="relative py-1">
              <!-- Profile Link -->
              <RouterLink
                :to="profileRoute"
                class="flex w-full items-center px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-100"
                data-testid="dropdown-profile-link"
                @click="closeDropdown"
              >
                <User :size="16" class="mr-3 text-gray-500" />
                <span>Mon Profil</span>
              </RouterLink>

              <div class="my-1 border-t border-gray-100"></div>

              <!-- Logout Button -->
              <button
                type="button"
                @click="handleLogout"
                :disabled="isLoggingOut"
                class="flex w-full items-center px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                data-testid="dropdown-logout-button"
              >
                <Loader2 v-if="isLoggingOut" :size="16" class="mr-3 text-gray-500 animate-spin" />
                <LogOut v-else :size="16" class="mr-3 text-gray-500" />
                <span>{{ isLoggingOut ? 'Déconnexion...' : 'Déconnexion' }}</span>
              </button>
            </div>
          </div>
        </Transition>
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
