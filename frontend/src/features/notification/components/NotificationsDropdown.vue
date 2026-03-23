<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle, XCircle, Bell, Loader2 } from 'lucide-vue-next'
import type { Notification } from '../types'
import { NotificationType } from '../types'
import { notificationApi } from '../services/notificationApi'
import { useToast } from '@/composables/useToast'

/**
 * NotificationsDropdown component
 * Shows list of notifications with mark as read functionality
 * Navigates to deep link (data.url) when available, otherwise candidatures
 */

const emit = defineEmits<{
  close: []
  'notification-read': []
  'all-read': []
}>()

const router = useRouter()
const toast = useToast()

const notifications = ref<Notification[]>([])
const isLoading = ref(true)

const hasUnread = computed(() => notifications.value.some((n) => !n.read_at))

/**
 * Format date to relative string (French)
 */
function formatRelativeTime(dateString: string): string {
  const now = new Date()
  const past = new Date(dateString)
  const diffInSeconds = Math.floor((now.getTime() - past.getTime()) / 1000)

  if (diffInSeconds < 60) return "À l'instant"
  const diffInMinutes = Math.floor(diffInSeconds / 60)
  if (diffInMinutes < 60) return `Il y a ${diffInMinutes}min`
  const diffInHours = Math.floor(diffInMinutes / 60)
  if (diffInHours < 24) return `Il y a ${diffInHours}h`
  const diffInDays = Math.floor(diffInHours / 24)
  if (diffInDays < 7) return `Il y a ${diffInDays}j`
  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' }).format(past)
}

/**
 * Fetch notifications on mount
 */
async function fetchNotifications(): Promise<void> {
  isLoading.value = true
  try {
    const response = await notificationApi.getNotifications()
    notifications.value = response.data
  } catch (error) {
    console.error('[NotificationsDropdown] Failed to fetch notifications:', error)
    toast.error('Impossible de charger les notifications')
  } finally {
    isLoading.value = false
  }
}

/**
 * Handle notification click - mark as read and navigate to candidatures
 */
async function handleNotificationClick(notification: Notification): Promise<void> {
  // Mark as read if not already
  if (!notification.read_at) {
    try {
      await notificationApi.markAsRead(notification.id)
      notification.read_at = new Date().toISOString()
      emit('notification-read')
    } catch (error) {
      console.error('[NotificationsDropdown] Failed to mark as read:', error)
      toast.error('Impossible de marquer comme lu')
    }
  }

  // Navigate to the deep link if available, otherwise fall back to candidatures
  emit('close')
  if (notification.data.url) {
    await router.push(notification.data.url)
  } else {
    await router.push({ name: 'face-candidatures' })
  }
}

/**
 * Mark all as read
 */
async function handleMarkAllAsRead(): Promise<void> {
  try {
    await notificationApi.markAllAsRead()
    const now = new Date().toISOString()
    notifications.value.forEach((n) => {
      if (!n.read_at) {
        n.read_at = now
      }
    })
    emit('all-read')
  } catch (error) {
    console.error('[NotificationsDropdown] Failed to mark all as read:', error)
    toast.error('Impossible de marquer tout comme lu')
  }
}

/**
 * Returns the icon variant for a notification based on its type.
 * Positive events → 'check', negative events → 'x', informational → 'bell'
 */
function getNotificationIcon(type: string): 'check' | 'x' | 'bell' {
  const positiveTypes: string[] = [
    NotificationType.CANDIDATURE_ACCEPTED,
    NotificationType.BOOKING_ACCEPTED,
    NotificationType.BOOKING_PAID,
    NotificationType.BOOKING_WALLET_CREDITED,
    NotificationType.BOOKING_COMPLETED,
  ]
  const negativeTypes: string[] = [
    NotificationType.BOOKING_REFUSED,
    NotificationType.BOOKING_CANCELLED,
  ]
  if (positiveTypes.includes(type)) return 'check'
  if (negativeTypes.includes(type)) return 'x'
  return 'bell'
}

onMounted(() => {
  fetchNotifications()
})
</script>

<template>
  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="translate-y-2 opacity-0 scale-95"
    enter-to-class="translate-y-0 opacity-100 scale-100"
    leave-active-class="transition duration-150 ease-in"
    leave-from-class="translate-y-0 opacity-100 scale-100"
    leave-to-class="translate-y-2 opacity-0 scale-95"
  >
    <div
      v-if="true"
      class="fixed sm:absolute inset-x-4 sm:inset-x-auto sm:right-0 top-16 sm:top-auto sm:mt-2 w-auto sm:w-96 bg-popover border border-border rounded-xl shadow-lg z-50 overflow-hidden flex flex-col"
      role="menu"
      aria-label="Notifications"
    >
      <!-- Header -->
      <div class="px-4 py-3 border-b border-border flex items-center justify-between bg-card/50">
        <h3 class="text-sm font-semibold text-foreground">Notifications</h3>
        <button
          v-if="hasUnread && !isLoading"
          type="button"
          class="text-xs font-medium text-primary hover:text-primary/80 transition-colors"
          @click="handleMarkAllAsRead"
        >
          Tout marquer comme lu
        </button>
      </div>

      <!-- Content Area -->
      <div class="max-h-[360px] overflow-y-auto custom-scrollbar bg-popover">
        <!-- Loading State -->
        <div
          v-if="isLoading"
          class="flex flex-col items-center justify-center py-12 px-4 space-y-3"
        >
          <Loader2 class="w-5 h-5 text-primary animate-spin" />
          <p class="text-xs text-muted-foreground">Chargement...</p>
        </div>

        <!-- Empty State -->
        <div
          v-else-if="notifications.length === 0"
          class="flex flex-col items-center justify-center py-12 px-6 text-center"
        >
          <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center mb-3">
            <Bell class="w-6 h-6 text-muted-foreground/60" />
          </div>
          <p class="text-sm font-medium text-foreground">Aucune notification</p>
          <p class="text-xs text-muted-foreground mt-1">Vous êtes à jour !</p>
        </div>

        <!-- Notifications List -->
        <ul v-else class="divide-y divide-border/50">
          <li
            v-for="notification in notifications"
            :key="notification.id"
            class="relative group p-4 hover:bg-muted/50 cursor-pointer transition-colors"
            :class="{ 'bg-primary/5': !notification.read_at }"
            @click="handleNotificationClick(notification)"
          >
            <!-- Unread Indicator -->
            <div
              v-if="!notification.read_at"
              class="absolute left-0 top-0 bottom-0 w-[3px] bg-primary"
            />

            <div class="flex gap-3">
              <!-- Icon -->
              <div class="flex-shrink-0 mt-0.5">
                <CheckCircle
                  v-if="getNotificationIcon(notification.type) === 'check'"
                  class="w-5 h-5 text-green-600"
                />
                <XCircle
                  v-else-if="getNotificationIcon(notification.type) === 'x'"
                  class="w-5 h-5 text-red-500"
                />
                <Bell v-else class="w-5 h-5 text-primary" />
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2 mb-1">
                  <!-- Candidature notifications have a mission_title as bold header -->
                  <p
                    v-if="notification.data.mission_title"
                    class="text-sm font-medium text-foreground line-clamp-1"
                  >
                    {{ notification.data.mission_title }}
                  </p>
                  <span class="text-[10px] text-muted-foreground whitespace-nowrap flex-shrink-0">
                    {{ formatRelativeTime(notification.created_at) }}
                  </span>
                </div>
                <!-- Message: primary text for booking types, secondary for candidature types -->
                <p
                  class="line-clamp-2"
                  :class="
                    notification.data.mission_title
                      ? 'text-xs text-muted-foreground'
                      : 'text-sm font-medium text-foreground'
                  "
                >
                  {{ notification.data.message }}
                </p>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <!-- Footer -->
      <div
        class="px-4 py-2.5 bg-muted/30 border-t border-border text-center cursor-pointer hover:bg-muted/50 transition-colors"
        @click="emit('close')"
      >
        <span class="text-xs text-muted-foreground">Fermer</span>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: var(--border);
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: var(--muted-foreground);
}
</style>
