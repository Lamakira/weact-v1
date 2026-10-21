<script setup lang="ts">
import { formatMoney } from '@/utils/money'
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowRight, Calendar, CalendarClock, Clock, MapPin, Wallet } from 'lucide-vue-next'
import type { Booking, BookingFaceUserable, BookingProducerUserable } from '../types'
import BookingStatusBadge from './BookingStatusBadge.vue'
import { useAuthStore } from '@/stores/auth'
import { bookingListAction, bookingListSubtitle } from '../utils/bookingPresentation'

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

/** Sous-titre court selon le statut et le rôle (plus de « Demande de booking » figé). */
const subtitle = computed(() => bookingListSubtitle(props.booking, isFace.value ? 'face' : 'producer'))

/** Action principale + échéance (lot D7) : mène à la page du booking, sans rien muter depuis la liste. */
const listAction = computed(() => bookingListAction(props.booking, isFace.value ? 'face' : 'producer'))

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

const formattedDateRange = computed(() => {
  // UGC dotations have no shoot dates → empty (the row is hidden via v-if).
  if (!props.booking.date_debut || !props.booking.date_fin) return ''
  const start = formatDate(props.booking.date_debut)
  const end = formatDate(props.booking.date_fin)
  return `${start} — ${end}`
})
</script>

<template>
  <!--
    Carte entière cliquable SANS lien imbriqué : le nom de la contrepartie porte le lien étiré
    (after:absolute inset-0) ; le bouton d'action, au-dessus (z-10), est un second lien frère.
  -->
  <article
    class="relative block rounded-xl border border-border bg-card p-4 transition-all hover:border-primary/50 hover:shadow-md sm:p-5"
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
          width="40"
          height="40"
          decoding="async"
          loading="lazy"
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
          <RouterLink
            :to="{ name: detailRouteName, params: { id: booking.id } }"
            class="after:absolute after:inset-0 after:rounded-xl focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-primary"
            data-testid="booking-card-link"
          >
            {{ counterpartName }}
          </RouterLink>
        </p>
        <p class="text-xs text-muted-foreground" data-testid="booking-card-subtitle">
          {{ subtitle }}
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
        <span class="font-medium text-foreground">{{ formatMoney(displayAmount) }}</span>
      </div>
    </div>

    <!-- Échéance + action principale (lot D7), quand l'utilisateur doit agir -->
    <div
      v-if="listAction"
      class="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-border pt-3"
    >
      <p
        v-if="listAction.deadline"
        class="flex items-center gap-1.5 text-sm font-medium text-foreground"
        data-testid="booking-card-deadline"
      >
        <CalendarClock class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
        {{ listAction.deadline }}
      </p>
      <RouterLink
        :to="{ name: detailRouteName, params: { id: booking.id } }"
        class="relative z-10 ml-auto inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
        :aria-label="`${listAction.label} : booking avec ${counterpartName}`"
        data-testid="booking-card-action"
      >
        {{ listAction.label }}
        <ArrowRight class="h-3.5 w-3.5" aria-hidden="true" />
      </RouterLink>
    </div>
  </article>
</template>
