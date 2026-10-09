<script setup lang="ts">
/**
 * ActiveMissionsPanel — module « Missions actives » du dashboard Producteur.
 * Missions publiées ou en cours : candidatures reçues (dont nouvelles des dernières
 * 24 h), Faces confirmées vs voulues avec barre de progression. « Voir » ouvre les
 * candidatures de la mission.
 */
import { RouterLink } from 'vue-router'
import { Briefcase } from 'lucide-vue-next'
import RPanel from '@/components/regie/RPanel.vue'
import RStatusDot from '@/components/regie/RStatusDot.vue'
import type { StatusTone } from '@/components/regie/statusTone'
import { Skeleton } from '@/components/ui/skeleton'
import type { ProducerActiveMission } from '../../types'

interface Props {
  items: ProducerActiveMission[]
  /** Nombre total de missions actives (peut dépasser `items`) */
  total: number
  isLoading?: boolean
  error?: string | null
}

withDefaults(defineProps<Props>(), {
  isLoading: false,
  error: null,
})

defineEmits<{ retry: [] }>()

// « Publiée » = vert ; tout le reste de la liste est du travail en cours = bleu
function tone(mission: ProducerActiveMission): StatusTone {
  return mission.status === 'published' ? 'success' : 'progress'
}

function label(mission: ProducerActiveMission): string {
  return mission.status === 'published' ? 'Publiée' : 'En cours'
}

function hasFacesTarget(mission: ProducerActiveMission): boolean {
  return (mission.faces_wanted ?? 0) > 0
}

function progress(mission: ProducerActiveMission): number {
  if (!hasFacesTarget(mission)) return 0
  return Math.min(100, Math.round((mission.confirmed_count / (mission.faces_wanted ?? 1)) * 100))
}

function candidaturesText(mission: ProducerActiveMission): string {
  const n = mission.candidatures_count
  return `${n} candidature${n > 1 ? 's' : ''}`
}
</script>

<template>
  <RPanel title="Missions actives" data-testid="module-active-missions">
    <template #actions>
      <span
        v-if="!isLoading && !error && total > 0"
        class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-sidebar px-1.5 text-[11.5px] font-semibold text-ink-2"
        data-testid="active-missions-count"
      >{{ total }}</span>
      <RouterLink
        :to="{ name: 'producer-missions' }"
        class="text-[12.5px] font-medium text-ink-2 hover:text-ink hover:underline"
        data-testid="active-missions-see-all"
      >Tout voir</RouterLink>
    </template>

    <div v-if="isLoading" class="space-y-3 p-4" data-testid="active-missions-loading">
      <Skeleton class="h-12 w-full" />
      <Skeleton class="h-12 w-full" />
    </div>

    <p v-else-if="error" class="p-4 text-dash text-ink-2" role="alert" data-testid="active-missions-error">
      {{ error }}
      <button type="button" class="font-semibold text-weact-700 hover:underline" @click="$emit('retry')">
        Réessayer
      </button>
    </p>

    <div
      v-else-if="items.length === 0"
      class="flex flex-col items-center gap-2 px-4 py-8 text-center"
      data-testid="active-missions-empty"
    >
      <Briefcase class="size-5 text-ink-3" aria-hidden="true" />
      <p class="text-dash text-ink-2">Aucune mission active pour le moment.</p>
      <RouterLink
        :to="{ name: 'publish-mission' }"
        class="text-[12.5px] font-semibold text-weact-700 hover:underline"
      >Publier une mission</RouterLink>
    </div>

    <ul v-else class="divide-y divide-line" data-testid="active-missions-list">
      <li v-for="mission in items" :key="mission.id" class="px-4 py-3 sm:px-5" data-testid="active-mission-row">
        <div class="flex items-center justify-between gap-3">
          <p class="min-w-0 truncate text-dash font-semibold text-ink">{{ mission.titre }}</p>
          <RStatusDot :tone="tone(mission)" :label="label(mission)" class="shrink-0 text-[12px] text-ink-3" />
        </div>

        <div v-if="hasFacesTarget(mission)" class="mt-2 flex items-center gap-3">
          <div
            class="h-1.5 flex-1 overflow-hidden rounded-full bg-sidebar"
            role="progressbar"
            :aria-valuenow="progress(mission)"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="`${mission.confirmed_count} sur ${mission.faces_wanted} Faces confirmées`"
          >
            <div
              class="h-full rounded-full"
              :class="tone(mission) === 'success' ? 'bg-weact-600' : 'bg-state-progress'"
              :style="{ width: `${progress(mission)}%` }"
              data-testid="active-mission-progress"
            />
          </div>
          <span class="shrink-0 text-[12px] text-ink-3" data-testid="active-mission-faces">
            {{ mission.confirmed_count }} / {{ mission.faces_wanted }} Faces confirmées
          </span>
        </div>

        <div class="mt-1.5 flex items-center justify-between gap-3 text-[12px] text-ink-3">
          <p>
            <span data-testid="active-mission-candidatures">{{ candidaturesText(mission) }}</span>
            <template v-if="mission.new_candidatures_count > 0">
              ·
              <b class="font-semibold text-weact-700" data-testid="active-mission-new">
                {{ mission.new_candidatures_count }} nouvelle{{ mission.new_candidatures_count > 1 ? 's' : '' }}
              </b>
            </template>
          </p>
          <RouterLink
            :to="{ name: 'producer-mission-candidatures', params: { id: mission.id } }"
            class="font-semibold text-weact-700 hover:underline"
            :aria-label="`Voir la mission ${mission.titre}`"
          >Voir</RouterLink>
        </div>
      </li>
    </ul>
  </RPanel>
</template>
