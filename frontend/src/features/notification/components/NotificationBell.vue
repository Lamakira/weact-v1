<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Bell } from 'lucide-vue-next'
import { notificationApi } from '../services/notificationApi'
import NotificationsDropdown from './NotificationsDropdown.vue'

/**
 * NotificationBell component for Face navbar
 * Shows bell icon with unread count badge
 * Toggles notifications dropdown on click
 * Pauses polling while dropdown is open
 */

const unreadCount = ref(0)
const showDropdown = ref(false)
const bellContainer = ref<HTMLElement | null>(null)
let pollInterval: ReturnType<typeof setInterval> | null = null

const displayCount = computed(() => {
  if (unreadCount.value > 9) return '9+'
  return unreadCount.value.toString()
})

async function fetchUnreadCount(): Promise<void> {
  try {
    const response = await notificationApi.getUnreadCount()
    unreadCount.value = response.count
  } catch (error) {
    // Log error for debugging, but don't disrupt UX
    console.error('[NotificationBell] Failed to fetch unread count:', error)
  }
}

function startPolling(): void {
  if (pollInterval) return
  pollInterval = setInterval(fetchUnreadCount, 30000)
}

function stopPolling(): void {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

// Pause polling when dropdown is open, resume when closed
watch(showDropdown, (isOpen) => {
  if (isOpen) {
    stopPolling()
  } else {
    startPolling()
  }
})

function toggleDropdown(): void {
  showDropdown.value = !showDropdown.value
}

function closeDropdown(): void {
  showDropdown.value = false
}

function handleClickOutside(event: MouseEvent): void {
  if (bellContainer.value && !bellContainer.value.contains(event.target as Node)) {
    if (showDropdown.value) {
      showDropdown.value = false
    }
  }
}

function handleNotificationRead(): void {
  // Decrement count when a notification is marked as read
  unreadCount.value = Math.max(0, unreadCount.value - 1)
}

function handleAllRead(): void {
  unreadCount.value = 0
}

onMounted(() => {
  fetchUnreadCount()
  startPolling()
  window.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  stopPolling()
  window.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div ref="bellContainer" class="relative inline-block">
    <!-- Notification Bell Button -->
    <button
      type="button"
      class="group relative flex h-10 w-10 items-center justify-center rounded-full border border-border bg-background transition-all duration-200 hover:bg-muted hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
      :class="{ 'bg-muted ring-2 ring-primary/10': showDropdown }"
      aria-label="Notifications"
      @click.stop="toggleDropdown"
    >
      <Bell
        :size="20"
        class="text-muted-foreground transition-colors duration-200 group-hover:text-primary"
        :class="{ 'text-primary': showDropdown || unreadCount > 0 }"
      />

      <!-- Unread Badge -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="scale-0 opacity-0"
        enter-to-class="scale-100 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="scale-100 opacity-100"
        leave-to-class="scale-0 opacity-0"
      >
        <span
          v-if="unreadCount > 0"
          class="absolute -top-1 -right-1 flex min-w-[1.25rem] h-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white shadow-sm"
        >
          {{ displayCount }}
        </span>
      </Transition>
    </button>

    <!-- Notifications Dropdown -->
    <NotificationsDropdown
      v-if="showDropdown"
      @close="closeDropdown"
      @notification-read="handleNotificationRead"
      @all-read="handleAllRead"
    />
  </div>
</template>
