<script setup lang="ts">
/**
 * FaceMissionHub (lot C5) — la page mission comme point central de la Face une fois la candidature posée.
 *
 * En-tête (statut + UNE action principale), barre d'étapes, carte principale (brief, détails, chat,
 * blocs UGC) et bloc « Et ensuite ? ». Sur mobile (< 640 px) l'action principale vit dans une barre
 * collée en bas. Présentationnel : la page parente porte les appels réseau et reçoit les actions via emits.
 *
 * Mouvement (principes Emil Kowalski) : uniquement transform / opacity / filter, ease-out fort,
 * supprimé ou réduit sous prefers-reduced-motion. Le CSS de mouvement est scopé ici.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Calendar, MapPin, Wallet, MessageCircle, Lock, Clock, Check, ShieldCheck, Star } from 'lucide-vue-next'
import { UgcFaceTrackingCard, ProductPhotoGallery } from '@/components/ugc'
import type { Shipment, UgcUploadProgress } from '@/components/ugc'
import { UgcMissionStats, UgcDeliverablesPreview } from '@/features/mission/components'
import MissionAbsenceContestBlock from '@/features/candidature/components/MissionAbsenceContestBlock.vue'
import MissionRatingDialog from '@/features/candidature/components/MissionRatingDialog.vue'
import type { MissionRating } from '@/features/candidature/types'
import RatingDisplay from '@/components/RatingDisplay.vue'
import { formatMissionDurationForDisplay } from '@/features/mission/constants/missionDuration'
import { deriveFaceHubState, formatRemaining, formatShortDate } from '@/features/candidature/utils/faceMissionHub'
import type { HubActionKey, HubTone } from '@/features/candidature/utils/faceMissionHub'
import type { Mission, MissionCandidature } from '@/features/mission/types'

const props = defineProps<{
  mission: Mission
  candidature: MissionCandidature
  isUgc: boolean
  producerName: string
  /** Une action réseau de la page est en cours : l'action principale est désactivée. */
  busy: boolean
  showFaceTrackingCard: boolean
  candidatureShipment: Shipment | null
  ugcTrackingStep: number
  isSubmittingReceipt: boolean
  isUploadingDeliverable: boolean
  uploadProgress: UgcUploadProgress | null
}>()

const emit = defineEmits<{
  action: [key: HubActionKey]
  'confirm-receipt': []
  upload: [file: File]
  contested: []
  /** La Face vient de noter le producteur : la page peut relire la mission en silence. */
  rated: [rating: MissionRating]
}>()

// Notation donnée dans cette session : prime sur le payload tant que la page n'a pas relu la mission.
const localRating = ref<MissionRating | null>(null)
const myRating = computed(() => localRating.value ?? props.candidature.my_rating ?? null)
const ratingOpen = ref(false)

const hub = computed(() =>
  deriveFaceHubState(
    props.mission,
    localRating.value ? { ...props.candidature, can_rate: false } : props.candidature,
    { isUgc: props.isUgc, producerName: props.producerName },
  ),
)

function onAction(key: HubActionKey): void {
  if (key === 'rate') {
    ratingOpen.value = true
    return
  }
  emit('action', key)
}

function onRated(rating: MissionRating): void {
  localRating.value = rating
  ratingOpen.value = false
  emit('rated', rating)
}

// Compte à rebours : recalculé chaque minute, sans animation.
const now = ref(new Date())
let timer: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  timer = setInterval(() => {
    now.value = new Date()
  }, 60_000)
})
onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})

const remaining = computed(() =>
  hub.value.next.deadline ? formatRemaining(hub.value.next.deadline, now.value) : '',
)

// Le fondu de changement d'action ne joue pas au premier rendu, seulement quand la clé change.
const swapEnabled = ref(false)
onMounted(() => {
  swapEnabled.value = true
})

// Remplissage de la barre : 0 → 1 sur l'ensemble des étapes ; la transition CSS anime le changement.
const fillRatio = computed(() => {
  const last = hub.value.steps.length - 1
  return Math.min(hub.value.currentStep, last) / last
})

const stepStates = computed(() =>
  hub.value.steps.map((name, i) => ({
    name,
    done: i < hub.value.currentStep,
    now: i === hub.value.currentStep,
  })),
)

const toneClasses: Record<HubTone, string> = {
  info: 'bg-primary-50 text-primary-600',
  warn: 'bg-amber-50 text-amber-800',
  ok: 'bg-emerald-50 text-emerald-700',
  mute: 'border border-border bg-muted text-muted-foreground',
}

const eyebrow = computed(() => `${props.producerName} · Mission ${props.isUgc ? 'UGC' : 'standard'}`)
const shootDate = computed(() => formatShortDate(props.mission.date_tournage))

function formatFcfa(amount: number): string {
  return `${new Intl.NumberFormat('fr-FR').format(amount).replace(/[\u202f\u00a0]/g, ' ')} FCFA`
}

// « Vous recevrez » seulement quand le net est connu (place payée) ; sinon on reste sur le brut proposé.
const moneyLabel = computed(() => {
  const net = props.candidature.montant_face_recoit
  if (!props.isUgc) {
    return net != null ? `Vous recevrez ${formatFcfa(net)}` : `Rémunération proposée ${formatFcfa(props.mission.budget)}`
  }
  const cash = props.mission.montant_remuneration
  const product = props.mission.nom_produit ? `Produit offert : ${props.mission.nom_produit}` : 'Dotation produit'
  return cash ? `${product} + ${formatFcfa(cash)}` : product
})

const producer = computed(() => props.mission.producer)
const producerAvatarUrl = computed(() => producer.value?.profile_photo_url || producer.value?.thumbnail_url || null)
const producerInitials = computed(() => props.producerName.charAt(0).toUpperCase())

const chatTarget = computed(() =>
  hub.value.chatOpen
    ? { name: 'face-conversation', params: { conversationId: props.candidature.conversation_id as string } }
    : null,
)
</script>

<template>
  <div class="hub pb-28 sm:pb-0" data-testid="face-mission-hub">
    <!-- En-tête : eyebrow, titre, statut + méta ; l'action principale à droite (desktop) -->
    <header class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto] sm:gap-x-5">
      <div class="min-w-0">
        <p class="text-xs text-muted-foreground" data-testid="hub-eyebrow">{{ eyebrow }}</p>
        <h1 class="text-lg font-bold leading-tight text-foreground [text-wrap:balance] sm:text-[22px]">
          {{ mission.titre }}
        </h1>
        <div class="mt-1.5 flex flex-wrap items-center gap-x-3.5 gap-y-1.5 text-[13px] text-muted-foreground">
          <span
            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap"
            :class="toneClasses[hub.pill.tone]"
            data-testid="hub-pill"
            :data-tone="hub.pill.tone"
          >
            <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
            {{ hub.pill.label }}
          </span>
          <span v-if="!isUgc && shootDate" class="inline-flex items-center gap-1.5" data-testid="hub-date">
            <Calendar class="h-3.5 w-3.5" aria-hidden="true" />Tournage {{ shootDate }}
          </span>
          <span v-if="!isUgc && mission.lieu" class="inline-flex items-center gap-1.5">
            <MapPin class="h-3.5 w-3.5" aria-hidden="true" />{{ mission.lieu }}
          </span>
          <span class="inline-flex items-center gap-1.5 tabular-nums" data-testid="hub-money">
            <Wallet class="h-3.5 w-3.5" aria-hidden="true" />{{ moneyLabel }}
          </span>
        </div>
      </div>

      <div class="hidden items-start sm:flex">
        <button
          :key="hub.action.key"
          type="button"
          class="hub-btn inline-flex flex-col items-center justify-center rounded-[9px] border px-4 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60"
          :class="[
            hub.action.kind === 'primary'
              ? 'hub-btn--primary border-primary bg-primary text-primary-foreground'
              : 'hub-btn--ghost border-border bg-card text-muted-foreground',
            swapEnabled && 'hub-swap',
          ]"
          :disabled="busy"
          data-testid="hub-action-desktop"
          @click="onAction(hub.action.key)"
        >
          <span>{{ hub.action.label }}</span>
          <span v-if="hub.action.sub" class="text-xs font-medium opacity-85">{{ hub.action.sub }}</span>
        </button>
      </div>
    </header>

    <!-- Barre d'étapes -->
    <nav class="mb-4 rounded-[10px] border border-border bg-card px-4 py-3.5 max-sm:pb-9" aria-label="Étapes" data-testid="hub-steps">
      <ol class="relative grid" :style="{ gridTemplateColumns: `repeat(${hub.steps.length}, 1fr)` }">
        <span
          class="absolute top-[11px] h-0.5 rounded-sm bg-border"
          :style="{ left: `calc(50% / ${hub.steps.length})`, right: `calc(50% / ${hub.steps.length})` }"
          aria-hidden="true"
        />
        <span
          class="hub-fill absolute top-[11px] h-0.5 rounded-sm"
          :class="hub.stopped ? 'bg-muted-foreground/50' : 'bg-primary'"
          :style="{
            left: `calc(50% / ${hub.steps.length})`,
            right: `calc(50% / ${hub.steps.length})`,
            transform: `scaleX(${fillRatio})`,
          }"
          data-testid="hub-fill"
          aria-hidden="true"
        />
        <li
          v-for="(step, i) in stepStates"
          :key="step.name"
          class="relative z-[1] grid justify-items-center gap-1.5 px-1 text-center"
          :class="{ 'is-done': step.done, 'is-now': step.now }"
          :aria-current="step.now && !hub.stopped ? 'step' : undefined"
          :data-step-state="step.done ? 'done' : step.now ? 'now' : 'todo'"
        >
          <span
            class="hub-dot grid h-6 w-6 place-items-center rounded-full border-2 text-xs font-bold"
            :class="
              step.done
                ? 'border-primary bg-primary text-primary-foreground'
                : step.now
                  ? hub.stopped
                    ? 'border-muted-foreground/50 bg-card text-muted-foreground'
                    : 'border-primary bg-card text-primary shadow-[0_0_0_4px_var(--color-primary-50)]'
                  : 'border-border bg-card text-muted-foreground'
            "
          >
            <Check v-if="step.done" class="h-3.5 w-3.5" aria-hidden="true" />
            <template v-else>{{ i + 1 }}</template>
          </span>
          <span
            class="text-xs leading-tight"
            :class="[
              step.now ? 'font-semibold text-foreground' : 'text-muted-foreground',
              step.now ? 'max-sm:absolute max-sm:top-[30px] max-sm:whitespace-nowrap' : 'max-sm:hidden',
            ]"
          >
            {{ step.name }}
          </span>
        </li>
      </ol>
    </nav>

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
      <!-- Colonne principale -->
      <div class="grid min-w-0 gap-4">
        <section class="grid gap-3 rounded-[10px] border border-border bg-card p-4">
          <h2 class="text-[15px] font-bold text-foreground">{{ isUgc ? 'Brief' : 'Le brief' }}</h2>
          <p class="m-0 whitespace-pre-line text-sm leading-relaxed text-gray-600">{{ mission.description }}</p>

          <UgcMissionStats
            v-if="isUgc"
            :valeur-produit="mission.valeur_produit"
            :montant-remuneration="mission.montant_remuneration"
            :nombre-videos="mission.nombre_videos"
          />
          <ProductPhotoGallery v-if="isUgc && mission.product_photos?.length" :photos="mission.product_photos" />
          <UgcDeliverablesPreview v-if="isUgc" :nombre-videos="mission.nombre_videos" />

          <dl class="mt-1 grid gap-2 text-[13px]" data-testid="hub-details">
            <div v-if="mission.profil_recherche" class="flex justify-between gap-3">
              <dt class="text-muted-foreground">Profil recherché</dt>
              <dd class="text-right font-semibold text-foreground">{{ mission.profil_recherche }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-muted-foreground">Genre recherché</dt>
              <dd class="text-right font-semibold text-foreground">{{ mission.genre_voulu_label }}</dd>
            </div>
            <div v-if="!isUgc && mission.duree" class="flex justify-between gap-3">
              <dt class="text-muted-foreground">Durée</dt>
              <dd class="text-right font-semibold text-foreground">{{ formatMissionDurationForDisplay(mission.duree) }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-muted-foreground">
                {{ candidature.montant_face_recoit != null || isUgc ? 'Votre part' : 'Rémunération proposée' }}
              </dt>
              <dd class="text-right font-semibold tabular-nums text-foreground">
                {{
                  isUgc
                    ? moneyLabel
                    : formatFcfa(candidature.montant_face_recoit != null ? candidature.montant_face_recoit : mission.budget)
                }}
              </dd>
            </div>
          </dl>

          <router-link
            v-if="chatTarget"
            :to="chatTarget"
            class="hub-btn hub-btn--ghost inline-flex items-center justify-center gap-2 rounded-[9px] border border-border bg-card px-3.5 py-2 text-sm font-semibold text-foreground"
            data-testid="hub-chat-link"
          >
            <MessageCircle class="h-4 w-4" aria-hidden="true" />
            Discuter avec le producteur
          </router-link>
          <div
            v-else
            class="flex items-start gap-2.5 rounded-[9px] border border-dashed border-border bg-muted p-3 text-[13px] text-muted-foreground"
            data-testid="hub-chat-locked"
          >
            <Lock class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
            <span>Le chat avec le producteur s’ouvre quand votre place est payée et confirmée.</span>
          </div>
        </section>

        <!-- Blocs UGC existants : suivi/réception/livrables, contestation d'absence -->
        <UgcFaceTrackingCard
          v-if="showFaceTrackingCard && candidatureShipment"
          :shipment="candidatureShipment"
          :current="ugcTrackingStep"
          :is-submitting="isSubmittingReceipt"
          :is-uploading="isUploadingDeliverable"
          :upload-progress="uploadProgress"
          :deliverables="candidature.deliverables ?? []"
          @confirm-receipt="emit('confirm-receipt')"
          @upload="(file: File) => emit('upload', file)"
        />
        <MissionAbsenceContestBlock
          v-if="candidature.attendance_dispute"
          :dispute="candidature.attendance_dispute"
          :mission-id="mission.id"
          @contested="emit('contested')"
        />

        <!-- Notation donnée au producteur (lot C8) : lecture seule -->
        <section
          v-if="myRating"
          class="hub-rating grid gap-1.5 rounded-[10px] border border-border bg-card p-4"
          data-testid="hub-my-rating"
        >
          <h2 class="text-[15px] font-bold text-foreground">Votre évaluation</h2>
          <div class="flex items-center gap-1.5">
            <span class="flex" role="img" :aria-label="`${myRating.score} sur 5`">
              <Star
                v-for="star in 5"
                :key="star"
                class="h-5 w-5"
                :class="myRating.score >= star ? 'text-amber-400' : 'text-gray-300'"
                :fill="myRating.score >= star ? 'currentColor' : 'none'"
                aria-hidden="true"
              />
            </span>
            <span class="text-sm font-semibold tabular-nums text-foreground" data-testid="hub-my-rating-score">
              {{ myRating.score }}/5
            </span>
          </div>
          <p v-if="myRating.comment" class="m-0 whitespace-pre-line text-sm text-gray-600" data-testid="hub-my-rating-comment">
            {{ myRating.comment }}
          </p>
        </section>
      </div>

      <!-- Aside : « Et ensuite ? » + producteur -->
      <aside class="grid gap-3 lg:sticky lg:top-6">
        <section
          class="rounded-[10px] border border-primary bg-primary-50 p-4"
          aria-label="Et ensuite ?"
          data-testid="hub-next"
        >
          <span class="inline-flex rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-semibold text-primary-600 ring-1 ring-primary/30">
            Et ensuite ?
          </span>
          <p class="mt-2 text-base font-bold text-foreground" data-testid="hub-next-headline">{{ hub.next.headline }}</p>
          <p class="mt-1.5 text-sm text-foreground">{{ hub.next.text }}</p>
          <div
            v-if="remaining"
            class="mt-2.5 inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-[13px] font-bold tabular-nums text-amber-800"
            data-testid="hub-countdown"
          >
            <Clock class="h-3.5 w-3.5" aria-hidden="true" />{{ remaining }}
          </div>
          <p class="mt-2 text-xs text-muted-foreground" data-testid="hub-who">{{ hub.next.who }}</p>
        </section>

        <section class="rounded-[10px] border border-border bg-card p-4">
          <h2 class="mb-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Producteur</h2>
          <component
            :is="producer?.slug ? 'router-link' : 'div'"
            v-bind="producer?.slug ? { to: `/producers/${producer.slug}`, 'aria-label': `Voir le profil de ${producerName}` } : {}"
            class="flex items-center gap-3"
          >
            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full border border-border bg-muted">
              <img v-if="producerAvatarUrl" :src="producerAvatarUrl" :alt="producerName" class="h-full w-full object-cover" />
              <div v-else class="flex h-full w-full items-center justify-center bg-primary/10 text-sm font-bold uppercase text-primary">
                {{ producerInitials }}
              </div>
            </div>
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-gray-900">{{ producerName }}</p>
              <RatingDisplay :average-rating="producer?.average_rating ?? null" :review-count="producer?.ratings_count ?? 0" />
            </div>
          </component>
          <p v-if="isUgc" class="mt-3 flex items-center gap-1.5 text-xs font-medium text-primary">
            <ShieldCheck class="h-3.5 w-3.5" aria-hidden="true" />
            Producteur vérifié — dotation réglée via WeAct
          </p>
        </section>
      </aside>
    </div>

    <!-- Barre d'action collée en bas (mobile) : intitulé + échéance + action principale -->
    <div
      class="fixed inset-x-0 bottom-0 z-30 flex items-center gap-2 border-t border-border bg-card px-3 pt-2.5 pb-[calc(0.625rem+env(safe-area-inset-bottom,0px))] sm:hidden"
      data-testid="hub-actionbar"
    >
      <div class="min-w-0 text-xs leading-snug text-muted-foreground">
        <b class="block text-[13px] text-foreground">{{ hub.next.headline }}</b>
        {{ remaining || hub.next.who }}
      </div>
      <button
        :key="hub.action.key"
        type="button"
        class="hub-btn ml-auto shrink-0 rounded-[9px] border px-3.5 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60"
        :class="[
          hub.action.kind === 'primary'
            ? 'hub-btn--primary border-primary bg-primary text-primary-foreground'
            : 'hub-btn--ghost border-border bg-card text-muted-foreground',
          swapEnabled && 'hub-swap',
        ]"
        :disabled="busy"
        data-testid="hub-action-mobile"
        @click="onAction(hub.action.key)"
      >
        {{ hub.action.label }}
      </button>
    </div>

    <MissionRatingDialog
      :open="ratingOpen"
      :candidature-id="candidature.id"
      :producer-name="producerName"
      @close="ratingOpen = false"
      @rated="onRated"
    />
  </div>
</template>

<style scoped>
.hub {
  --hub-ease: cubic-bezier(0.23, 1, 0.32, 1);
}

/* Remplissage de la barre d'étapes : transform seulement, démarre à gauche. */
.hub-fill {
  transform-origin: left center;
  transition: transform 520ms var(--hub-ease);
}

.hub-btn {
  transition:
    transform 160ms var(--hub-ease),
    background-color 160ms ease,
    border-color 160ms ease,
    filter 160ms ease;
}
.hub-btn:active:not(:disabled) {
  transform: scale(0.97);
}
/* Survol réservé aux pointeurs précis : pas de « hover collé » au tactile. */
@media (hover: hover) and (pointer: fine) {
  .hub-btn--primary:hover:not(:disabled) {
    filter: brightness(1.05);
  }
  .hub-btn--ghost:hover:not(:disabled) {
    border-color: var(--color-muted-foreground);
  }
}

/* Changement d'action principale : fondu court avec léger flou (masque le croisement des deux états). */
.hub-swap {
  animation: hub-swap-in 240ms var(--hub-ease);
}
@keyframes hub-swap-in {
  from {
    opacity: 0;
    filter: blur(3px);
    transform: translateY(2px);
  }
  to {
    opacity: 1;
    filter: blur(0);
    transform: none;
  }
}

/* Apparition de l'évaluation donnée : fondu + léger glissement. */
.hub-rating {
  animation: hub-swap-in 240ms var(--hub-ease);
}

@media (prefers-reduced-motion: reduce) {
  .hub-rating {
    animation: none;
  }
  .hub-fill {
    transition: none;
  }
  .hub-btn {
    transition-property: background-color, border-color;
  }
  .hub-btn:active:not(:disabled) {
    transform: none;
  }
  .hub-swap {
    animation: hub-swap-fade 1ms;
  }
  @keyframes hub-swap-fade {
    from {
      opacity: 0.01;
    }
    to {
      opacity: 1;
    }
  }
}
</style>
