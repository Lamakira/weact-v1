<script setup lang="ts">
import { computed } from 'vue'
import BookingStatusBadge from './BookingStatusBadge.vue'
import type { Booking, BookingFaceUserable, BookingProducerUserable } from '../types'

interface Props {
  booking: Booking
  currentUserId: number
}

const props = defineProps<Props>()

/**
 * Determine the other party (the one the current user is chatting with).
 * face_id = user id of the Face (not face profile id).
 */
const otherParty = computed(() => {
  const isFace = props.currentUserId === props.booking.face_id
  return isFace ? props.booking.producer : props.booking.face
})

const otherPartyName = computed((): string => {
  if (!otherParty.value?.userable) return 'Utilisateur'
  const userable = otherParty.value.userable
  // Face has nom + prenom, Producer has display_name
  if ('nom' in userable) {
    return `${(userable as BookingFaceUserable).prenom} ${(userable as BookingFaceUserable).nom}`
  }
  return (userable as BookingProducerUserable).display_name
})

const otherPartyPhoto = computed((): string | null => {
  return otherParty.value?.userable?.profile_photo_url ?? null
})

const otherPartyInitials = computed((): string => {
  const name = otherPartyName.value
  const parts = name.trim().split(' ')
  if (parts.length >= 2) {
    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
})

const bookingDateLabel = computed((): string => {
  const start = new Date(props.booking.date_debut)
  const dateStr = start.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })
  return `${dateStr} · ${props.booking.duree_heures}h`
})

const isFaceRole = computed((): boolean => props.currentUserId === props.booking.face_id)
const otherPartyRole = computed((): string => (isFaceRole.value ? 'Producteur' : 'Face'))
</script>

<template>
  <div
    class="sticky top-0 z-10 bg-white border-b border-gray-100 px-4 py-3 flex items-center gap-3"
    data-testid="chat-header"
  >
    <!-- Avatar -->
    <div class="shrink-0">
      <img
        v-if="otherPartyPhoto"
        :src="otherPartyPhoto"
        :alt="otherPartyName"
        class="w-10 h-10 rounded-full object-cover"
      />
      <div
        v-else
        class="w-10 h-10 rounded-full bg-[#198496]/10 text-[#198496] flex items-center justify-center text-sm font-semibold"
      >
        {{ otherPartyInitials }}
      </div>
    </div>

    <!-- Name + role + date -->
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2">
        <p class="text-sm font-semibold text-gray-900 truncate">{{ otherPartyName }}</p>
        <span class="text-xs text-gray-400 shrink-0">{{ otherPartyRole }}</span>
      </div>
      <p class="text-xs text-gray-500 mt-0.5">{{ bookingDateLabel }}</p>
    </div>

    <!-- Status badge -->
    <div class="shrink-0">
      <BookingStatusBadge :status="booking.status" />
    </div>
  </div>
</template>
