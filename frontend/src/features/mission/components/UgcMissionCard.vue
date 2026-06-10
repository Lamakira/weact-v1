<script setup lang="ts">
import { computed } from 'vue'
import { Lock, MapPin, Video } from 'lucide-vue-next'
import StatusPill from '@/components/ugc/StatusPill.vue'
import type { Mission, UgcMissionTeaser } from '../types'

const props = defineProps<{
  item: Mission | UgcMissionTeaser
  locked: boolean
}>()

const emit = defineEmits<{
  click: [id: string]
}>()

// Narrowing : seul le MissionResource complet (branche éligible) a `description`.
// Ne JAMAIS discriminer par type_mission==='ugc' (hors type TS) ni commission_ugc
// (masqué pour les Faces depuis la review 2.1) — D-2.2.g.
const fullMission = computed<Mission | null>(() =>
  'description' in props.item ? props.item : null,
)

const producerName = computed(() => fullMission.value?.producer?.display_name ?? null)

const isOpen = computed(() => {
  if (fullMission.value) return fullMission.value.is_accepting_candidatures
  // Teaser : heuristique d'affichage (display-only, D-2.2.e).
  // Jour de deadline inclus — sémantique backend isAcceptingCandidatures
  // (comparaison par date, pas par instant) ; date inparsable → ouvert.
  const deadline = props.item.date_limite_candidature
  if (!deadline) return true
  const parsed = new Date(deadline)
  if (Number.isNaN(parsed.getTime())) return true
  const endOfDeadlineDay = new Date(
    parsed.getFullYear(),
    parsed.getMonth(),
    parsed.getDate(),
    23,
    59,
    59,
    999,
  )
  return endOfDeadlineDay.getTime() >= Date.now()
})

function formatFcfa(amount: number): string {
  return `${(amount || 0).toLocaleString('fr-FR')} FCFA`
}

function handleClick(): void {
  emit('click', props.item.id)
}
</script>

<template>
  <div
    class="group relative flex h-full cursor-pointer flex-col overflow-hidden rounded-lg border border-border bg-card transition-all duration-200 hover:border-primary/30 hover:shadow-md"
    role="button"
    tabindex="0"
    :aria-label="locked ? 'Mission verrouillée — abonnement requis' : undefined"
    data-testid="ugc-mission-card"
    @click="handleClick"
    @keydown.enter="handleClick"
    @keydown.space.prevent="handleClick"
  >
    <!-- Header band (teal léger) : badge UGC + tag compensation + kicker + titre -->
    <div class="relative bg-primary/5 p-4">
      <div :class="{ 'blur-[2px]': locked }">
        <div class="flex items-center gap-2">
          <span
            class="inline-flex items-center rounded-full bg-primary px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide text-white"
          >
            UGC
          </span>
          <span
            v-if="item.type_compensation_label"
            class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-medium text-primary"
          >
            {{ item.type_compensation_label }}
          </span>
        </div>
        <p
          v-if="item.nom_produit"
          class="mt-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground"
        >
          {{ item.nom_produit }}
        </p>
        <h3
          class="mt-1 line-clamp-2 text-base font-semibold text-foreground transition-colors group-hover:text-primary"
        >
          {{ item.titre }}
        </h3>
      </div>

      <!-- Overlay cadenas (carte verrouillée) -->
      <div
        v-if="locked"
        class="absolute inset-0 flex items-center justify-center bg-card/40"
        data-testid="ugc-card-lock-overlay"
      >
        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary shadow-md">
          <Lock class="h-4 w-4 text-white" />
        </div>
      </div>
    </div>

    <!-- Body : valeur produit / cash + méta + pill -->
    <div class="flex flex-1 flex-col gap-3 p-4">
      <div class="flex items-center justify-between gap-2">
        <p v-if="item.valeur_produit !== null" class="text-sm text-muted-foreground">
          <span class="font-semibold text-primary">{{ formatFcfa(item.valeur_produit) }}</span>
          de produit
        </p>
        <p
          v-if="fullMission?.montant_remuneration"
          class="text-sm font-semibold text-foreground"
        >
          + {{ formatFcfa(fullMission.montant_remuneration) }}
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
        <span v-if="item.nombre_videos !== null" class="flex items-center gap-1.5">
          <Video class="h-4 w-4 text-primary" />
          {{ item.nombre_videos }} vidéo{{ item.nombre_videos > 1 ? 's' : '' }}
        </span>
        <span class="flex items-center gap-1.5">
          <MapPin class="h-4 w-4 text-primary" />
          <span class="truncate">{{ item.lieu }}</span>
        </span>
      </div>

      <div>
        <StatusPill :kind="isOpen ? 'accepted' : 'overdue'">
          {{ isOpen ? 'Candidatures ouvertes' : 'Délai dépassé' }}
        </StatusPill>
      </div>

      <!-- Footer producteur (branche éligible uniquement) -->
      <div
        v-if="producerName"
        class="mt-auto border-t border-border pt-3"
        data-testid="ugc-card-producer"
      >
        <div class="flex items-center gap-2">
          <div
            class="flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-xs font-bold uppercase text-primary"
          >
            {{ producerName.charAt(0) }}
          </div>
          <span class="truncate text-sm font-medium text-foreground">{{ producerName }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
