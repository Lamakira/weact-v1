<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Calendar, Wallet, MapPin, Users } from 'lucide-vue-next'
import RatingDisplay from '@/components/RatingDisplay.vue'
import type { Mission } from '../types'

const props = defineProps<{
  mission: Mission
}>()

const router = useRouter()

const emit = defineEmits<{
  click: [id: string]
}>()

// Truncate description at 120 chars
const truncatedDescription = computed(() => {
  if (!props.mission.description) return ''
  if (props.mission.description.length <= 120) return props.mission.description
  return props.mission.description.slice(0, 120) + '...'
})

// Get producer display name
const producerName = computed(() => {
  const producer = props.mission.producer
  if (!producer) return 'Producteur inconnu'
  if (producer.type === 'agency' && producer.agency_name) {
    return producer.agency_name
  }
  const firstName = producer.first_name || ''
  const lastName = producer.last_name || ''
  return `${firstName} ${lastName}`.trim() || 'Producteur'
})

// Get producer initials for avatar fallback
const producerInitials = computed(() => {
  return producerName.value.charAt(0).toUpperCase()
})

// Get producer avatar URL
const producerAvatarUrl = computed(() => {
  return props.mission.producer?.profile_photo_url || props.mission.producer?.thumbnail_url || null
})

// Format date in French
function formatDate(dateString: string): string {
  if (!dateString) return ''
  return new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(dateString))
}

// Format currency in XOF
function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
  }).format(amount)
}

function handleClick(): void {
  emit('click', props.mission.id)
}

// Producer rating data
const producerRating = computed(() => props.mission.producer?.average_rating ?? null)
const producerRatingsCount = computed(() => props.mission.producer?.ratings_count ?? 0)

// Navigate to producer profile
function handleProducerClick(event: MouseEvent | KeyboardEvent): void {
  event.stopPropagation()
  if (props.mission.producer?.slug) {
    router.push(`/producers/${props.mission.producer.slug}`)
  }
}
</script>

<template>
  <div
    class="group relative flex h-full cursor-pointer flex-col rounded-lg border border-border bg-card p-5 transition-all duration-200 hover:border-primary/30 hover:shadow-md"
    @click="handleClick"
  >
    <!-- Header: Badge & Title -->
    <div class="mb-4 flex flex-col gap-2">
      <div class="flex items-center justify-between">
        <span
          class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
        >
          {{ mission.type_mission === 'autre' && mission.type_mission_autre ? `Autre : ${mission.type_mission_autre}` : mission.type_mission_label }}
        </span>
      </div>
      <h3
        class="line-clamp-1 text-lg font-semibold text-foreground transition-colors group-hover:text-primary"
      >
        {{ mission.titre }}
      </h3>
    </div>

    <!-- Description -->
    <p class="mb-6 flex-grow text-sm leading-relaxed text-muted-foreground">
      {{ truncatedDescription }}
    </p>

    <!-- Mission Meta Grid -->
    <div class="mb-6 grid grid-cols-2 gap-x-4 gap-y-3">
      <div class="flex items-center gap-2 text-sm text-muted-foreground">
        <Calendar class="h-4 w-4 text-primary" />
        <span>{{ formatDate(mission.date_tournage) }}</span>
      </div>
      <div class="flex items-center gap-2 text-sm text-muted-foreground">
        <MapPin class="h-4 w-4 text-primary" />
        <span class="truncate">{{ mission.lieu }}</span>
      </div>
      <div class="flex items-center gap-2 text-sm font-medium text-foreground">
        <Wallet class="h-4 w-4 text-primary" />
        <span>{{ formatCurrency(mission.budget) }}</span>
      </div>
      <div class="flex items-center gap-2 text-sm text-muted-foreground">
        <Users class="h-4 w-4 text-primary" />
        <span>{{ mission.nombre_faces_voulu }} Face{{ mission.nombre_faces_voulu > 1 ? 's' : '' }}</span>
      </div>
    </div>

    <!-- Divider -->
    <div class="mb-4 h-px w-full bg-border"></div>

    <!-- Footer: Producer -->
    <div
      class="flex cursor-pointer items-center gap-3 rounded-md p-1 -m-1 transition-colors hover:bg-muted/50"
      role="button"
      tabindex="0"
      :aria-label="`Voir le profil de ${producerName}`"
      @click="handleProducerClick"
      @keydown.enter="handleProducerClick"
    >
      <div class="relative h-8 w-8 flex-shrink-0 overflow-hidden rounded-full border border-border bg-muted">
        <img
          v-if="producerAvatarUrl"
          :src="producerAvatarUrl"
          :alt="producerName"
          class="h-full w-full object-cover"
        />
        <div
          v-else
          class="flex h-full w-full items-center justify-center bg-primary/10 text-xs font-bold uppercase text-primary"
        >
          {{ producerInitials }}
        </div>
      </div>
      <div class="flex min-w-0 flex-col gap-0.5">
        <span class="truncate text-sm font-medium text-foreground">{{ producerName }}</span>
        <RatingDisplay
          :average-rating="producerRating"
          :review-count="producerRatingsCount"
          class="text-xs"
        />
      </div>
    </div>
  </div>
</template>
