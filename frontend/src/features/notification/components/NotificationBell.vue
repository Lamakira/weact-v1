<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { storeToRefs } from 'pinia'
import { Bell } from 'lucide-vue-next'
import { useNotificationStore } from '@/stores/notification'
import NotificationsDropdown from './NotificationsDropdown.vue'

const notificationStore = useNotificationStore()
const { unreadCount } = storeToRefs(notificationStore)

const showDropdown = ref(false)
const bellContainer = ref<HTMLElement | null>(null)

const displayCount = computed(() => {
  if (unreadCount.value > 9) return '9+'
  return unreadCount.value.toString()
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

onMounted(() => {
  window.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
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
    />
  </div>
</template>
