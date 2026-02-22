<script setup lang="ts">
import { computed } from 'vue'
import { Calendar, Wallet, MapPin, Users } from 'lucide-vue-next'
import type { PublicMission } from '../services/publicMissionsApi'

const props = defineProps<{
  mission: PublicMission
}>()

// Truncate description at 120 chars
const truncatedDescription = computed((): string => {
  if (!props.mission.description) return ''
  if (props.mission.description.length <= 120) return props.mission.description
  return props.mission.description.slice(0, 120) + '...'
})

// Producer display name
const producerName = computed((): string => {
  return props.mission.producer?.display_name || 'Producteur'
})

// Producer initials for avatar fallback
const producerInitials = computed((): string => {
  return producerName.value.charAt(0).toUpperCase()
})

// Producer avatar URL
const producerAvatarUrl = computed((): string | null => {
  return props.mission.producer?.profile_photo_thumbnail_url || null
})

// Format date in French
function formatDate(dateString: string | null): string {
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
</script>

<template>
  <router-link
    :to="`/missions/${mission.slug}`"
    class="group relative flex h-full cursor-pointer flex-col rounded-lg border border-border bg-card p-5 transition-all duration-200 hover:border-primary/30 hover:shadow-md"
    :aria-label="`Mission : ${mission.titre}`"
    :data-testid="`mission-card-${mission.id}`"
  >
    <!-- Header: Badge & Title -->
    <div class="mb-4 flex flex-col gap-2">
      <div class="flex items-center justify-between">
        <span
          class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
          data-testid="mission-type-badge"
        >
          {{ mission.type_mission === 'autre' && mission.type_mission_autre ? `Autre : ${mission.type_mission_autre}` : mission.type_mission_label }}
        </span>
      </div>
      <h3
        class="line-clamp-1 text-lg font-semibold text-foreground transition-colors group-hover:text-primary"
        data-testid="mission-title"
      >
        {{ mission.titre }}
      </h3>
    </div>

    <!-- Description -->
    <p
      class="mb-6 flex-grow text-sm leading-relaxed text-muted-foreground"
      data-testid="mission-description"
    >
      {{ truncatedDescription }}
    </p>

    <!-- Mission Meta Grid -->
    <div class="mb-6 grid grid-cols-2 gap-x-4 gap-y-3">
      <div class="flex items-center gap-2 text-sm text-muted-foreground">
        <Calendar class="h-4 w-4 text-primary" aria-hidden="true" />
        <span data-testid="mission-date">{{ formatDate(mission.date_tournage) }}</span>
      </div>
      <div class="flex items-center gap-2 text-sm text-muted-foreground">
        <MapPin class="h-4 w-4 text-primary" aria-hidden="true" />
        <span class="truncate" data-testid="mission-lieu">{{ mission.lieu }}</span>
      </div>
      <div class="flex items-center gap-2 text-sm font-medium text-foreground">
        <Wallet class="h-4 w-4 text-primary" aria-hidden="true" />
        <span data-testid="mission-budget">{{ formatCurrency(mission.budget) }}</span>
      </div>
      <div class="flex items-center gap-2 text-sm text-muted-foreground">
        <Users class="h-4 w-4 text-primary" aria-hidden="true" />
        <span data-testid="mission-faces">{{ mission.nombre_faces_voulu }} Face{{ mission.nombre_faces_voulu > 1 ? 's' : '' }}</span>
      </div>
    </div>

    <!-- Divider -->
    <div class="mb-4 h-px w-full bg-border"></div>

    <!-- Footer: Producer -->
    <div class="flex items-center gap-3">
      <div class="relative h-8 w-8 flex-shrink-0 overflow-hidden rounded-full border border-border bg-muted">
        <img
          v-if="producerAvatarUrl"
          :src="producerAvatarUrl"
          :alt="`Photo de ${producerName}`"
          loading="lazy"
          class="h-full w-full object-cover"
        />
        <div
          v-else
          class="flex h-full w-full items-center justify-center bg-primary/10 text-xs font-bold uppercase text-primary"
        >
          {{ producerInitials }}
        </div>
      </div>
      <span class="truncate text-sm font-medium text-foreground" data-testid="mission-producer-name">
        {{ producerName }}
      </span>
    </div>
  </router-link>
</template>
