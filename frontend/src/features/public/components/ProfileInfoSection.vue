<script setup lang="ts">
import { computed } from 'vue'
import { MapPin, Star, Wallet } from 'lucide-vue-next'

interface Props {
  prenom: string
  ville: string | null
  categories: Array<{ value: string; label: string }>
  niches: Array<{ value: string; label: string }>
  averageRating: number | null
  ratingsCount: number
  tarifHoraire?: string | null
  tarifJournalier?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  tarifHoraire: null,
  tarifJournalier: null,
})

const hasPricing = computed(() => !!props.tarifHoraire || !!props.tarifJournalier)
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="profile-info-section">
    <!-- Header Section -->
    <div class="space-y-1">
      <h1
        class="text-3xl font-bold text-gray-900 tracking-tight"
        data-testid="face-prenom"
      >
        {{ prenom }}
      </h1>
      <div
        v-if="ville"
        class="flex items-center text-gray-500 text-sm"
        data-testid="face-ville"
      >
        <MapPin class="w-4 h-4 mr-1" aria-hidden="true" />
        <span>{{ ville }}</span>
      </div>
    </div>

    <!-- Badges Row -->
    <div class="flex flex-wrap gap-2" data-testid="face-badges">
      <span
        v-for="cat in categories"
        :key="cat.value"
        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-[#198496]/10 text-[#198496]"
        data-testid="categorie-badge"
      >
        {{ cat.label }}
      </span>
      <span
        v-for="niche in niches"
        :key="niche.value"
        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200/50"
        data-testid="niche-badge"
      >
        {{ niche.label }}
      </span>
    </div>

    <!-- Tarifs (producers only) -->
    <div
      v-if="hasPricing"
      class="flex flex-wrap gap-4"
      data-testid="tarifs-section"
    >
      <div v-if="tarifHoraire" class="flex items-center gap-2">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-green-100">
          <Wallet class="h-4 w-4 text-green-600" aria-hidden="true" />
        </div>
        <div>
          <p class="text-xs text-gray-500">Tarif demi-journée</p>
          <p class="font-semibold text-gray-900">{{ tarifHoraire }}</p>
        </div>
      </div>
      <div v-if="tarifJournalier" class="flex items-center gap-2">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100">
          <Wallet class="h-4 w-4 text-blue-600" aria-hidden="true" />
        </div>
        <div>
          <p class="text-xs text-gray-500">Tarif journalier</p>
          <p class="font-semibold text-gray-900">{{ tarifJournalier }}</p>
        </div>
      </div>
    </div>

    <!-- Rating Section -->
    <div
      class="flex items-center gap-2"
      data-testid="rating-section"
      :aria-label="`Note moyenne: ${averageRating?.toFixed(1) || '0'} étoiles sur 5, basée sur ${ratingsCount} avis`"
    >
      <div class="flex items-center" aria-hidden="true">
        <Star
          v-for="i in 5"
          :key="i"
          class="w-4 h-4"
          :class="
            i <= Math.round(averageRating || 0)
              ? 'fill-amber-400 text-amber-400'
              : 'text-gray-200'
          "
        />
      </div>
      <span class="text-sm font-medium text-gray-900">{{
        averageRating?.toFixed(1) || '0.0'
      }}</span>
      <span class="text-sm text-gray-500">({{ ratingsCount }} avis)</span>
    </div>


  </div>
</template>
