<script setup lang="ts">
import { computed } from 'vue'
import { Lock, MapPin, Video } from 'lucide-vue-next'
import StatusPill from '@/components/ugc/StatusPill.vue'
import type { ProductPhoto } from '@/components/ugc'
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

// Vitrine photo produit : les DEUX branches (éligible et teaser) reçoivent
// product_photos depuis la découverte UGC. Photo nette même verrouillée
// (décision PO) — c'est l'argument d'upsell de la carte.
const photos = computed<ProductPhoto[]>(() => props.item.product_photos ?? [])

// Première photo = position la plus basse (relation ordonnée par `position`
// côté serveur). Vignette `grid` ~400px, repli sur l'original tant que le job
// de variantes n'a pas tourné (parité ProductPhotoGallery).
const coverSrc = computed<string>(() => {
  const cover = photos.value[0]
  return cover?.grid_url || cover?.photo_url || ''
})

// Une row sans aucune URL exploitable ne doit pas produire un <img> cassé :
// la carte retombe alors sur son en-tête historique.
const hasCover = computed(() => coverSrc.value !== '')

const coverAlt = computed(() =>
  props.item.nom_produit ? `Photo du produit ${props.item.nom_produit}` : 'Photo du produit',
)

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
    <!-- Bandeau photo produit (16:9) : badges en surimpression sur un dégradé
         pour rester lisibles quelle que soit la photo. Absent sans photo. -->
    <div
      v-if="hasCover"
      class="relative aspect-[4/3] overflow-hidden bg-muted"
      data-testid="ugc-card-cover"
    >
      <img
        :src="coverSrc"
        :alt="coverAlt"
        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
        loading="lazy"
        decoding="async"
      />

      <div
        class="absolute inset-x-0 bottom-0 flex items-center gap-2 bg-gradient-to-t from-black/70 via-black/25 to-transparent px-4 pb-3 pt-10"
      >
        <span
          class="inline-flex items-center rounded-full bg-primary px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide text-white"
        >
          UGC
        </span>
        <span
          v-if="item.type_compensation_label"
          class="inline-flex items-center rounded-full bg-white/90 px-2.5 py-0.5 text-[11px] font-medium text-primary"
        >
          {{ item.type_compensation_label }}
        </span>
      </div>

      <!-- Compteur des photos restantes (la galerie complète est sur le détail) -->
      <span
        v-if="photos.length > 1"
        class="absolute right-3 top-3 rounded-full bg-black/55 px-2 py-0.5 text-[11px] font-medium text-white"
        data-testid="ugc-card-photo-count"
      >
        +{{ photos.length - 1 }}
      </span>

      <!-- Cadenas : voile léger, la photo reste NETTE (décision PO) -->
      <div
        v-if="locked"
        class="absolute inset-0 flex items-center justify-center bg-black/25"
        data-testid="ugc-card-lock-overlay"
      >
        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary shadow-md">
          <Lock class="h-4 w-4 text-white" />
        </div>
      </div>
    </div>

    <!-- Header band (teal léger) : badge UGC + tag compensation + kicker + titre.
         Les badges et le cadenas ne s'y affichent que sans photo de couverture. -->
    <div class="relative bg-primary/5 p-4">
      <div :class="{ 'blur-[2px]': locked }">
        <div v-if="!hasCover" class="flex items-center gap-2">
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
          class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground"
          :class="{ 'mt-3': !hasCover }"
        >
          {{ item.nom_produit }}
        </p>
        <h3
          class="mt-1 line-clamp-2 text-base font-semibold text-foreground transition-colors group-hover:text-primary"
        >
          {{ item.titre }}
        </h3>
      </div>

      <!-- Overlay cadenas (carte verrouillée, sans photo de couverture) -->
      <div
        v-if="locked && !hasCover"
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
        <span v-if="item.lieu" class="flex items-center gap-1.5">
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
