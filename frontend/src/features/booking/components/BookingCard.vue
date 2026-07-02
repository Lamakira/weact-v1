<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Calendar, Clock, MapPin, Wallet } from 'lucide-vue-next'
import type { Booking, BookingFaceUserable, BookingProducerUserable } from '../types'
import BookingStatusBadge from './BookingStatusBadge.vue'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{
  booking: Booking
}>()

const authStore = useAuthStore()

/**
 * Determine if current user is the Face in this booking
 */
const isFace = computed(() => authStore.user?.userable_type === 'Face')

/**
 * Counterpart info: Face sees Producer, Producer sees Face
 */
const counterpartName = computed(() => {
  if (isFace.value) {
    const userable = props.booking.producer?.userable as BookingProducerUserable | undefined
    return userable?.display_name || 'Producteur'
  }
  const userable = props.booking.face?.userable as BookingFaceUserable | undefined
  if (userable?.prenom && userable?.nom) {
    return `${userable.prenom} ${userable.nom}`
  }
  return 'Face'
})

const counterpartAvatar = computed(() => {
  const user = isFace.value ? props.booking.producer : props.booking.face
  if (!user?.userable) return null
  const userable = user.userable as BookingFaceUserable | BookingProducerUserable
  return userable.thumbnail_url || userable.profile_photo_url || null
})

const counterpartInitials = computed(() => counterpartName.value.charAt(0).toUpperCase())

/**
 * Financial amount: Face sees montant_face_recoit, Producer sees montant_total_producteur
 */
const displayAmount = computed(() => {
  return isFace.value ? props.booking.montant_face_recoit : props.booking.montant_total_producteur
})

/**
 * Route name for detail page
 */
const detailRouteName = computed(() => {
  return isFace.value ? 'face-booking-detail' : 'producer-booking-detail'
})

/**
 * Format helpers
 */
function formatDate(dateString: string | null): string {
  if (!dateString) return ''
  return new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(dateString))
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
  }).format(amount)
}

const formattedDateRange = computed(() => {
  // UGC dotations have no shoot dates → empty (the row is hidden via v-if).
  if (!props.booking.date_debut || !props.booking.date_fin) return ''
  const start = formatDate(props.booking.date_debut)
  const end = formatDate(props.booking.date_fin)
  return `${start} — ${end}`
})
</script>

<template>
  <RouterLink
    :to="{ name: detailRouteName, params: { id: booking.id } }"
    class="block rounded-xl border border-border bg-card p-4 transition-all hover:border-primary/50 hover:shadow-md sm:p-5"
  >
    <!-- Status Badge + Type -->
    <div class="mb-3 flex items-center justify-between">
      <BookingStatusBadge :status="booking.status" />
      <span class="text-xs text-muted-foreground">
        {{ booking.type_contenu }}
      </span>
    </div>

    <!-- Counterpart Info -->
    <div class="mb-3 flex items-center gap-3">
      <div class="relative h-10 w-10 shrink-0 overflow-hidden rounded-full border border-border bg-muted">
        <img
          v-if="counterpartAvatar"
          :src="counterpartAvatar"
          :alt="counterpartName"
          class="h-full w-full object-cover"
        />
        <div
          v-else
          class="flex h-full w-full items-center justify-center bg-primary/10 text-sm font-bold uppercase text-primary"
        >
          {{ counterpartInitials }}
        </div>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-foreground truncate">
          {{ counterpartName }}
        </p>
        <p class="text-xs text-muted-foreground">
          Demande de booking
        </p>
      </div>
    </div>

    <!-- Booking Details -->
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-foreground border-t border-border pt-3">
      <div v-if="formattedDateRange" class="flex items-center gap-1.5">
        <Calendar class="h-4 w-4" />
        <span>{{ formattedDateRange }}</span>
      </div>
      <div v-if="booking.duree_heures" class="flex items-center gap-1.5">
        <Clock class="h-4 w-4" />
        <span>max {{ booking.duree_heures }}h</span>
      </div>
      <div v-if="booking.lieu" class="flex items-center gap-1.5">
        <MapPin class="h-4 w-4" />
        <span>{{ booking.lieu }}</span>
      </div>
      <div class="flex items-center gap-1.5">
        <Wallet class="h-4 w-4" />
        <span class="font-medium text-foreground">{{ formatCurrency(displayAmount) }}</span>
      </div>
    </div>
  </RouterLink>
</template>
