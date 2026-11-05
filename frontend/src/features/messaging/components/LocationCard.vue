<script setup lang="ts">
import { computed } from 'vue'
import { MapPin } from 'lucide-vue-next'
import type { MessageLocation } from '../types'

const props = defineProps<{
  location: MessageLocation
  isOwn: boolean
  /** Nom d'affichage de l'expéditeur (titre « Position de {prénom} »). */
  senderName?: string | null
  /** Le parent borne déjà la largeur (chat de booking) : largeur fixe plutôt qu'un pourcentage. */
  fixedWidth?: boolean
}>()

// Le serveur renvoie « Utilisateur » quand le nom est inconnu : pas de prénom exploitable
const firstName = computed(() => {
  const name = props.senderName?.trim() ?? ''
  if (name === '' || name === 'Utilisateur') return ''
  return name.split(/\s+/)[0] ?? ''
})

const title = computed(() => {
  if (props.isOwn) return 'Votre position'
  if (!firstName.value) return 'Position partagée'
  // Élision devant voyelle ou h muet : « Position d'Aïcha ».
  const startsWithVowel = /^[aeiouyh]/i.test(firstName.value.normalize('NFD'))
  return startsWithVowel ? `Position d'${firstName.value}` : `Position de ${firstName.value}`
})
</script>

<template>
  <div
    class="rounded-[12px] bg-white px-3.5 py-3 text-ink ring-1 ring-line"
    :class="fixedWidth ? 'w-[260px] max-w-full' : 'w-full max-w-[82%] lg:max-w-[72%]'"
    data-testid="location-card"
  >
    <div class="flex items-start gap-3">
      <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-weact-50 text-weact-600">
        <MapPin class="size-[18px]" aria-hidden="true" />
      </span>
      <div class="min-w-0">
        <p class="text-[14px] font-semibold leading-tight text-ink" data-testid="location-title">{{ title }}</p>
        <p v-if="location.accuracy_m != null" class="mt-0.5 text-[12px] text-ink-3" data-testid="location-accuracy">
          Précision : {{ location.accuracy_m }} m
        </p>
      </div>
    </div>
    <a
      :href="location.maps_url"
      target="_blank"
      rel="noopener noreferrer"
      class="mt-3 flex min-h-11 items-center justify-center rounded-[10px] bg-weact-600 px-4 text-[13px] font-semibold text-white hover:bg-weact-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600 focus-visible:ring-offset-2"
      data-testid="location-open"
    >Ouvrir dans Google Maps</a>
  </div>
</template>
