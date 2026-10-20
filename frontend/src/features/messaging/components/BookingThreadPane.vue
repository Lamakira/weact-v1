<script setup lang="ts">
/**
 * Fil de booking ouvert dans la page Messages : charge le booking puis réutilise le chat
 * du booking (BookingChat). Le chargement des messages marque lu côté serveur ; le lien
 * « Voir le booking » mène au détail du booking.
 */
import { computed, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowLeft, Loader2 } from 'lucide-vue-next'
import BookingChat from '@/features/booking/components/BookingChat.vue'
import { useBookingDetail } from '@/features/booking/composables/useBookingDetail'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{
  bookingId: string
  title: string
  role: 'face' | 'producer'
  compact?: boolean
}>()

const emit = defineEmits<{ (e: 'back'): void }>()

const authStore = useAuthStore()
const { booking, isLoading, error, fetchBooking } = useBookingDetail()

const currentUserId = computed(() => authStore.user?.id ?? null)
const bookingRoute = computed(() => ({
  name: `${props.role}-booking-detail`,
  params: { id: props.bookingId },
}))

watch(
  () => props.bookingId,
  (id) => void fetchBooking(id),
  { immediate: true },
)
</script>

<template>
  <div class="flex min-h-0 min-w-0 flex-1 flex-col bg-white" data-testid="booking-thread-pane">
    <header class="flex h-14 shrink-0 items-center gap-2 border-b border-line px-2 lg:gap-3 lg:px-5">
      <button
        v-if="compact"
        type="button"
        class="flex size-10 items-center justify-center rounded-full text-ink-2 hover:bg-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600"
        aria-label="Retour aux conversations"
        data-testid="thread-back"
        @click="emit('back')"
      >
        <ArrowLeft class="size-5" />
      </button>
      <span
        class="shrink-0 rounded bg-weact-50 px-1.5 text-[10.5px] font-semibold uppercase tracking-wide text-weact-700"
      >Booking</span>
      <h2 class="min-w-0 flex-1 truncate text-[14px] font-semibold text-ink">
        {{ title.replace(/^Booking · /, '') }}
      </h2>
      <RouterLink
        :to="bookingRoute"
        class="shrink-0 text-[12.5px] font-medium text-weact-700 hover:underline"
        data-testid="booking-thread-link"
      >Voir le booking</RouterLink>
    </header>

    <div class="min-h-0 flex-1">
      <div
        v-if="isLoading && !booking"
        class="flex h-full items-center justify-center text-ink-3"
        data-testid="booking-thread-loading"
      >
        <Loader2 class="size-5 animate-spin" aria-hidden="true" />
      </div>
      <p
        v-else-if="error"
        class="p-6 text-center text-sm text-ink-2"
        role="alert"
        data-testid="booking-thread-error"
      >{{ error }}</p>
      <BookingChat
        v-else-if="booking && currentUserId !== null"
        :key="booking.id"
        :booking="booking"
        :current-user-id="currentUserId"
      />
    </div>
  </div>
</template>
