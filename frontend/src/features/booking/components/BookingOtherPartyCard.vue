<script setup lang="ts">
import { computed } from 'vue'
import { Star } from 'lucide-vue-next'
import WBadge from '@/components/ui/WBadge.vue'
import type { Booking, BookingFaceUserable, BookingProducerUserable } from '../types'
import { faceFullName, producerName, type BookingRole } from '../utils/bookingPresentation'

const props = defineProps<{
  booking: Booking
  /** Rôle de celui qui regarde : il voit l'AUTRE partie. */
  role: BookingRole
}>()

const PRODUCER_TYPE_LABELS: Record<string, string> = {
  agency: 'Agence',
  particulier: 'Particulier',
}

const sees = computed(() => (props.role === 'producer' ? 'face' : 'producer'))
const userable = computed(() => {
  const user = sees.value === 'face' ? props.booking.face : props.booking.producer
  return user?.userable as (BookingFaceUserable & BookingProducerUserable) | undefined
})

const name = computed(() => (sees.value === 'face' ? faceFullName(props.booking) : producerName(props.booking)))
const initial = computed(() => name.value.charAt(0).toUpperCase())
const avatarUrl = computed(() => userable.value?.thumbnail_url || userable.value?.profile_photo_url || null)
const isElite = computed(() => sees.value === 'face' && userable.value?.has_elite_badge === true)

const ratingCount = computed(() => userable.value?.ratings_count ?? 0)
const ratingValue = computed(() => {
  const average = userable.value?.average_rating
  if (average === null || average === undefined || ratingCount.value === 0) return null
  return average.toFixed(1).replace('.', ',')
})

const subtitle = computed(() => {
  const parts: string[] = []
  if (sees.value === 'face') {
    parts.push(isElite.value ? 'Face Élite' : 'Face')
    if (userable.value?.ville) parts.push(userable.value.ville)
  } else {
    parts.push('Producteur')
    const type = userable.value?.type
    if (type && PRODUCER_TYPE_LABELS[type]) parts.push(PRODUCER_TYPE_LABELS[type])
  }
  return parts.join(' · ')
})
</script>

<template>
  <section
    class="rounded-[10px] border border-gray-200 bg-white p-4"
    :aria-label="sees === 'face' ? 'La Face' : 'Le Producteur'"
    data-testid="booking-other-party"
  >
    <div class="grid grid-cols-[56px_1fr] items-center gap-3">
      <div
        v-if="avatarUrl"
        class="h-14 w-14 rounded-full border border-gray-200 bg-cover bg-center"
        :style="{ backgroundImage: `url(${avatarUrl})` }"
        role="img"
        :aria-label="name"
      />
      <div v-else class="grid h-14 w-14 place-items-center rounded-full bg-weact/10 text-lg font-bold text-weact">
        {{ initial }}
      </div>
      <div class="min-w-0">
        <p class="flex items-center gap-1.5 truncate text-base font-bold text-gray-900" data-testid="booking-other-party-name">
          {{ name }}
          <WBadge v-if="isElite" tier="elite" :size="16" />
        </p>
        <p class="text-sm text-gray-500" data-testid="booking-other-party-meta">
          {{ subtitle }}
          <template v-if="ratingValue">
            ·
            <span class="inline-flex items-center gap-0.5 font-bold text-amber-600">
              <Star class="h-3.5 w-3.5" fill="currentColor" aria-hidden="true" />
              {{ ratingValue }}
            </span>
            ({{ ratingCount }} avis)
          </template>
          <template v-else>· Pas encore noté</template>
        </p>
      </div>
    </div>
  </section>
</template>
