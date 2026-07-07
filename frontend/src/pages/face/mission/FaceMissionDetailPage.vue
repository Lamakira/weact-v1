<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  Calendar,
  MapPin,
  Wallet,
  Users,
  Clock,
  UserCheck,
  Target,
  AlertCircle,
  Loader2,
  ShieldCheck,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useMissionDetail } from '@/features/mission/composables'
import { isUgcMission } from '@/features/mission/types'
import { UgcMissionStats, UgcDeliverablesPreview } from '@/features/mission/components'
import { formatMissionDurationForDisplay } from '@/features/mission/constants/missionDuration'
import { ApplyToMissionModal } from '@/features/candidature/components'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import RatingDisplay from '@/components/RatingDisplay.vue'
import MissionApplyBlock from './MissionApplyBlock.vue'
import { ugcCandidatureTunnelStep, UGC_UNBOXING_DAYS, ProductPhotoGallery } from '@/components/ugc'
import { useCancelCandidature, useReconfirmCandidature } from '@/features/candidature/composables'
import { authApi } from '@/features/auth/services/authApi'
import type { Face } from '@/features/auth/types'
import { useToast } from '@/composables/useToast'
import { useUgcShipment } from '@/composables/useUgcShipment'
import { useUgcDeliverable } from '@/composables/useUgcDeliverable'

/**
 * LOGIC & STATE MANAGEMENT
 */
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const { mission, candidature, isLoading, error, notFound, ugcPaywall, ugcPaywallMessage, fetchMission, setCandidature } =
  useMissionDetail()

const isUgc = computed(() => mission.value !== null && isUgcMission(mission.value))

// FR5 : Face non éligible → paywall /pricing (D-2.3.c ; replace = pas de boucle back)
watch(
  ugcPaywall,
  (blocked) => {
    if (blocked) {
      // `||` (pas `??`) : un message backend vide ne doit pas produire un toast vide
      toast.info(
        ugcPaywallMessage.value || "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus).",
      )
      router.replace({ name: 'pricing' })
    }
  },
  { immediate: true },
)

// Modal state
const isApplyModalOpen = ref(false)

// Cancel candidature
const {
  isCancelling,
  error: cancelError,
  successMessage: cancelSuccessMessage,
  cancelCandidature,
  reset: resetCancel,
} = useCancelCandidature()
const showCancelModal = ref(false)

// Reconfirmation UGC (8-3, D-8.3.d) — la candidature `accepted` repasse `confirmed`
// via l'endpoint dédié /face/candidatures/{id}/reconfirm (séparé du confirm cash).
const {
  isReconfirming,
  error: reconfirmError,
  reconfirmCandidature,
} = useReconfirmCandidature()

// Candidature engagée : la Face a accepté le deal (bandeau sans CTA ni annulation).
const ugcEngaged = computed(() =>
  isUgc.value && ['confirmed', 'in_progress', 'completed'].includes(candidature.value?.status ?? ''),
)

// Carte de suivi Face (3.4) — remplace le bandeau engagé dès qu'un shipment existe (D-3.4.f).
const candidatureShipment = computed(() => candidature.value?.shipment ?? null)
const showFaceTrackingCard = computed(() => ugcEngaged.value && candidatureShipment.value !== null)
const ugcTrackingStep = computed(() =>
  ugcCandidatureTunnelStep(candidature.value?.status ?? '', candidatureShipment.value),
)

const showReceiptModal = ref(false)
const receiptModalMessage = `Le chrono Unboxing (${UGC_UNBOXING_DAYS} jours) démarre dès la confirmation — cette action est définitive.`

const {
  isSubmitting: isSubmittingReceipt,
  error: receiptError,
  errorCode: receiptErrorCode,
  confirmReceipt,
} = useUgcShipment()

// Upload du livrable Unboxing (4.2) — miroir d'AC5, noms distincts du receipt.
const {
  isUploading: isUploadingDeliverable,
  uploadProgress,
  error: deliverableError,
  errorCode: deliverableErrorCode,
  uploadDeliverable,
} = useUgcDeliverable()

// CTA « Reconfirmer ma participation » : candidature UGC `accepted` (8-3, D-8.3.d).
// Mutuellement exclusif avec « Annuler » (pending-only).
const canReconfirmUgc = computed(
  () => isUgc.value && candidature.value?.status === 'accepted',
)

// Email verification resend state
const isResendingVerification = ref(false)
const isRefreshingGenderContext = ref(false)

const currentFaceId = computed(() => authStore.user?.userable?.id ?? '')

function hasFaceSexeField(userable: unknown): userable is Pick<Face, 'sexe'> {
  return typeof userable === 'object' && userable !== null && 'sexe' in userable
}

// Get mission ID from route params
const missionId = computed(() => {
  const id = route.params.id
  return typeof id === 'string' && id.trim().length > 0 ? id : null
})

// Computed: Has the user already applied?
const hasApplied = computed(() => candidature.value !== null)

// Computed: Can the user cancel? (only pending candidatures)
const canCancelCandidature = computed(() => candidature.value?.status === 'pending')

// Computed: Can the user apply? (must have verified email)
const canApply = computed(() => authStore.isEmailVerified)

const currentFaceSexe = computed<Face['sexe'] | undefined>(() => {
  if (!authStore.isFace) return undefined

  const userable = authStore.user?.userable
  if (userable === undefined || !hasFaceSexeField(userable)) return undefined

  return userable.sexe
})

const isGenderContextUnknown = computed((): boolean => {
  if (!mission.value) return false
  if (mission.value.genre_voulu === 'tous') return false
  return authStore.isFace && authStore.user?.userable !== undefined && currentFaceSexe.value === undefined
})

// Computed: Gender mismatch check
const isGenderMismatch = computed((): boolean => {
  if (!mission.value) return false
  const genreVoulu = mission.value.genre_voulu
  if (genreVoulu === 'tous') return false
  const faceSexe = currentFaceSexe.value
  if (faceSexe === undefined) return false
  if (faceSexe === null) return true
  return faceSexe !== genreVoulu
})

const genderContextMessage = computed((): string => {
  if (isRefreshingGenderContext.value) {
    return 'Vérification de votre profil en cours...'
  }

  return 'Impossible de vérifier votre genre pour le moment. Rechargez la page pour continuer.'
})

const genderMismatchMessage = computed((): string => {
  if (!mission.value) return ''
  const faceSexe = currentFaceSexe.value
  if (faceSexe === undefined) return ''
  if (faceSexe === null) {
    return 'Complétez votre profil (genre) pour postuler à cette mission.'
  }
  return `Cette mission recherche un profil ${mission.value.genre_voulu_label}. Votre genre ne correspond pas.`
})

// Computed helpers
const producerName = computed(() => {
  const producer = mission.value?.producer
  if (!producer) return 'Producteur inconnu'
  if (producer.type === 'agency' && producer.agency_name) {
    return producer.agency_name
  }
  const firstName = producer.first_name || ''
  const lastName = producer.last_name || ''
  return `${firstName} ${lastName}`.trim() || 'Producteur'
})

const producerInitials = computed(() => {
  return producerName.value.charAt(0).toUpperCase()
})

const producerAvatarUrl = computed(() => {
  return mission.value?.producer?.profile_photo_url || mission.value?.producer?.thumbnail_url || null
})

const producerTypeLabel = computed(() => {
  if (!mission.value?.producer) return ''
  return mission.value.producer.type === 'agency' ? 'Agence' : 'Particulier'
})

// Producer rating data
const producerRating = computed(() => mission.value?.producer?.average_rating ?? null)
const producerRatingsCount = computed(() => mission.value?.producer?.ratings_count ?? 0)

/**
 * FORMAT HELPERS
 */
function formatDate(dateString: string): string {
  if (!dateString) return ''
  return new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(new Date(dateString))
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
  }).format(amount)
}

/**
 * ACTIONS
 */
function goBack(): void {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push({ name: 'face-missions' })
  }
}

function openApplyModal(): void {
  isApplyModalOpen.value = true
}

function closeApplyModal(): void {
  isApplyModalOpen.value = false
}

function handleApplySuccess(newCandidature: { id: string; status: string; status_label: string }): void {
  setCandidature({
    id: newCandidature.id,
    mission_id: missionId.value!,
    face_id: currentFaceId.value,
    status: newCandidature.status,
    status_label: newCandidature.status_label,
    message_motivation: null,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  })
}

async function handleResendVerification(): Promise<void> {
  isResendingVerification.value = true
  try {
    const result = await authApi.resendVerificationEmail()
    if (result.sent) {
      toast.success('Un email de vérification a été envoyé.')
    } else if (result.verified) {
      toast.success('Votre email est déjà vérifié.')
      await authStore.refreshUser()
    }
  } catch (err) {
    if ((err as { response?: { status?: number } })?.response?.status === 429) {
      toast.warning('Veuillez patienter avant de renvoyer un email.')
    } else {
      toast.error("Impossible d'envoyer l'email. Veuillez réessayer.")
    }
  } finally {
    isResendingVerification.value = false
  }
}

/**
 * CANCEL CANDIDATURE
 */
function openCancelModal(): void {
  showCancelModal.value = true
}

function closeCancelModal(): void {
  showCancelModal.value = false
}

async function handleCancelConfirm(): Promise<void> {
  if (!candidature.value) return

  showCancelModal.value = false
  const success = await cancelCandidature(candidature.value.id)

  if (success) {
    toast.success(cancelSuccessMessage.value || 'Candidature annulée avec succès.')
    // Update the local candidature status so the UI reflects the change
    setCandidature({
      ...candidature.value,
      status: 'cancelled',
      status_label: 'Annulée',
    })
  } else {
    toast.error(cancelError.value || 'Erreur lors de l\'annulation')
  }

  resetCancel()
}

/**
 * UGC RECEIPT CONFIRMATION (3.4)
 */
async function handleConfirmReceipt(): Promise<void> {
  showReceiptModal.value = false
  const shipment = candidatureShipment.value
  if (!shipment || !candidature.value) return

  const updated = await confirmReceipt(shipment.id)
  if (updated) {
    // Assignation locale via le setter existant (D-3.4.d) — le statut candidature ne bouge pas.
    // Snapshot relu après l'await : la ref peut avoir été remplacée/vidée pendant la requête.
    const current = candidature.value
    if (current) setCandidature({ ...current, shipment: updated })
    toast.success('Réception confirmée — le chrono Unboxing démarre')
    return
  }

  if (receiptErrorCode.value === 'ALREADY_RECEIVED') {
    // Multi-onglets : récupérer l'état réel (parité D-3.2.e).
    toast.info(receiptError.value || 'La réception de ce produit a déjà été confirmée.')
    if (missionId.value) await fetchMission(missionId.value)
    return
  }

  toast.error(receiptError.value || 'Erreur lors de la confirmation de la réception')
}

/**
 * UGC DELIVERABLE UPLOAD (4.2) — miroir d'AC5 (booking), refetch via fetchMission.
 */
async function handleUploadDeliverable(file: File): Promise<void> {
  const shipment = candidatureShipment.value
  if (!shipment) return

  const deliverable = await uploadDeliverable(shipment.id, file)
  if (deliverable) {
    toast.success('Vidéo Unboxing déposée — en attente de validation')
    if (missionId.value) await fetchMission(missionId.value) // D-4.2.d
    return
  }
  if (deliverableErrorCode.value === 'ALREADY_UPLOADED') {
    toast.info(deliverableError.value || 'Votre vidéo Unboxing a déjà été déposée.')
    if (missionId.value) await fetchMission(missionId.value)
    return
  }
  if (deliverableErrorCode.value === 'INVALID_STATUS') {
    toast.error(deliverableError.value || "La vidéo ne peut pas être déposée dans l'état actuel de ce deal.")
    if (missionId.value) await fetchMission(missionId.value)
    return
  }
  toast.error(deliverableError.value || "Erreur lors de l'envoi de la vidéo Unboxing")
}

/**
 * UGC RECONFIRMATION (8-3, D-8.3.d/f) — accepted → confirmed.
 */
async function handleReconfirm(): Promise<void> {
  if (!candidature.value || !missionId.value) return

  const result = await reconfirmCandidature(candidature.value.id)
  if (result) {
    toast.success('Participation reconfirmée — le producteur va expédier votre produit')
    await fetchMission(missionId.value) // D-8.3.f : refetch (pas setCandidature)
    return
  }

  toast.error(reconfirmError.value || 'Impossible de reconfirmer votre participation.')
}

/**
 * APPLY BLOCK PROPS
 *
 * Regroupe l'état consommé par MissionApplyBlock pour le `v-bind` aux deux
 * emplacements (sidebar sticky desktop + barre fixe / inline mobile) sans
 * répéter ~20 props à la main. Purement présentationnel — aucune logique
 * nouvelle, juste un miroir des computeds/refs existants. `mission.value!` :
 * le composant n'est rendu que dans la branche `v-else-if="mission"`.
 */
const applyBlockProps = computed(() => ({
  mission: mission.value!,
  candidature: candidature.value,
  hasApplied: hasApplied.value,
  showFaceTrackingCard: showFaceTrackingCard.value,
  candidatureShipment: candidatureShipment.value,
  ugcTrackingStep: ugcTrackingStep.value,
  isSubmittingReceipt: isSubmittingReceipt.value,
  isUploadingDeliverable: isUploadingDeliverable.value,
  uploadProgress: uploadProgress.value,
  ugcEngaged: ugcEngaged.value,
  canCancelCandidature: canCancelCandidature.value,
  canReconfirmUgc: canReconfirmUgc.value,
  isReconfirming: isReconfirming.value,
  isCancelling: isCancelling.value,
  canApply: canApply.value,
  isResendingVerification: isResendingVerification.value,
  isGenderContextUnknown: isGenderContextUnknown.value,
  isRefreshingGenderContext: isRefreshingGenderContext.value,
  genderContextMessage: genderContextMessage.value,
  isGenderMismatch: isGenderMismatch.value,
  genderMismatchMessage: genderMismatchMessage.value,
}))

/**
 * LIFECYCLE
 */
onMounted(() => {
  if (isGenderContextUnknown.value) {
    isRefreshingGenderContext.value = true

    authStore.refreshUser()
      .finally(() => {
        isRefreshingGenderContext.value = false
      })
  }

  if (missionId.value) {
    fetchMission(missionId.value)
  }
})
</script>

<template>
  <div>
    <!-- Back Button -->
    <div class="mb-6">
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-xs min-[376px]:text-sm font-medium transition-colors hover:bg-muted"
        @click="goBack"
      >
        <ArrowLeft class="h-4 w-4" />
        Retour aux missions
      </button>
    </div>

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="flex flex-col items-center justify-center py-24"
    >
      <Loader2 class="h-12 w-12 animate-spin text-primary" />
      <p class="mt-4 text-xs min-[376px]:text-sm text-muted-foreground">Chargement de la mission...</p>
    </div>

    <!-- Not Found State -->
    <div
      v-else-if="notFound"
      class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-muted py-24 text-center"
    >
      <div class="mb-4 rounded-full bg-muted p-4">
        <AlertCircle class="h-10 w-10 text-muted-foreground" />
      </div>
      <h3 class="text-lg min-[376px]:text-xl font-bold text-foreground">Mission introuvable</h3>
      <p class="mt-2 max-w-xs text-xs min-[376px]:text-sm text-muted-foreground">
        Cette mission n'existe pas ou n'est plus disponible.
      </p>
      <button
        type="button"
        class="mt-6 inline-flex items-center gap-2 rounded-lg border border-border bg-card px-6 py-2 text-xs min-[376px]:text-sm font-medium transition-colors hover:bg-muted"
        @click="goBack"
      >
        <ArrowLeft class="h-4 w-4" />
        Retour aux missions
      </button>
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-destructive/20 bg-destructive/5 py-24 text-center"
    >
      <div class="mb-4 rounded-full bg-destructive/10 p-4 text-destructive">
        <AlertCircle class="h-10 w-10" />
      </div>
      <h3 class="text-lg min-[376px]:text-xl font-bold text-foreground">Oups ! Une erreur est survenue</h3>
      <p class="mt-2 max-w-xs text-xs min-[376px]:text-sm text-muted-foreground">
        Impossible de charger les détails de la mission.
      </p>
      <p class="mt-2 text-xs min-[376px]:text-sm text-destructive">{{ error }}</p>
      <button
        type="button"
        class="mt-6 inline-flex items-center gap-2 rounded-lg border border-border bg-card px-6 py-2 text-xs min-[376px]:text-sm font-medium transition-colors hover:bg-muted"
        @click="goBack"
      >
        <ArrowLeft class="h-4 w-4" />
        Retour aux missions
      </button>
    </div>

    <!-- Mission Detail Content -->
    <div v-else-if="mission">
      <!-- En-tête pleine largeur (au-dessus de la grille) -->
      <div class="mb-6">
        <div class="mb-3 flex flex-wrap items-center gap-2">
          <span
            class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-xs min-[376px]:text-sm font-medium text-primary"
          >
            {{ mission.type_mission === 'autre' && mission.type_mission_autre ? `Autre : ${mission.type_mission_autre}` : mission.type_mission_label }}
          </span>
          <span
            v-if="isUgc && mission.type_compensation_label"
            class="inline-flex items-center rounded-full bg-muted px-3 py-1 text-xs min-[376px]:text-sm font-medium text-muted-foreground"
            data-testid="ugc-compensation-tag"
          >
            {{ mission.type_compensation_label }}
          </span>
          <span
            v-if="isUgc"
            class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700"
          >
            <ShieldCheck class="h-3.5 w-3.5" aria-hidden="true" />
            Dotation réglée via WeAct
          </span>
        </div>
        <h1 class="text-xl min-[376px]:text-2xl sm:text-3xl font-bold text-foreground">
          {{ mission.titre }}
        </h1>
        <p class="mt-1.5 text-xs min-[376px]:text-sm text-gray-400">
          Publiée le {{ formatDate(mission.created_at) }} · {{ producerName }}
        </p>
      </div>

      <!-- Grille 2 colonnes : contenu (gauche) + carte d'action sticky (droite).
           `items-start` est indispensable au fonctionnement du sticky. `pb-28`
           réserve l'espace de la barre d'action fixe mobile (retirée dès qu'on a
           déjà postulé — l'état passe alors inline dans la colonne de gauche). -->
      <div
        class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start"
        :class="{ 'pb-28 lg:pb-0': !hasApplied }"
      >
        <!-- COLONNE GAUCHE : contenu défilable -->
        <div class="lg:col-span-7 xl:col-span-8 space-y-5">
          <!-- Mobile : stats + producteur compact remontés en tête (signal de confiance ;
               sur desktop ils vivent dans la carte d'action / la sidebar) -->
          <div class="space-y-4 lg:hidden">
            <UgcMissionStats
              v-if="isUgc"
              :valeur-produit="mission.valeur_produit"
              :montant-remuneration="mission.montant_remuneration"
              :nombre-videos="mission.nombre_videos"
            />
            <router-link
              v-if="mission.producer?.slug"
              :to="`/producers/${mission.producer.slug}`"
              class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-3 shadow-sm transition-colors hover:bg-muted/30"
              :aria-label="`Voir le profil de ${producerName}`"
            >
              <div class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-full bg-muted">
                <img v-if="producerAvatarUrl" :src="producerAvatarUrl" :alt="producerName" class="h-full w-full object-cover" />
                <div v-else class="flex h-full w-full items-center justify-center bg-primary/10 text-sm font-bold uppercase text-primary">{{ producerInitials }}</div>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900 truncate">{{ producerName }}</p>
                <RatingDisplay :average-rating="producerRating" :review-count="producerRatingsCount" />
              </div>
              <span v-if="isUgc" class="flex flex-shrink-0 items-center gap-1 text-[10px] font-medium text-primary">
                <ShieldCheck class="h-3 w-3" aria-hidden="true" />
                Vérifié
              </span>
            </router-link>
            <div v-else class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-3 shadow-sm">
              <div class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-full bg-muted">
                <img v-if="producerAvatarUrl" :src="producerAvatarUrl" :alt="producerName" class="h-full w-full object-cover" />
                <div v-else class="flex h-full w-full items-center justify-center bg-primary/10 text-sm font-bold uppercase text-primary">{{ producerInitials }}</div>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900 truncate">{{ producerName }}</p>
                <RatingDisplay :average-rating="producerRating" :review-count="producerRatingsCount" />
              </div>
              <span v-if="isUgc" class="flex flex-shrink-0 items-center gap-1 text-[10px] font-medium text-primary">
                <ShieldCheck class="h-3 w-3" aria-hidden="true" />
                Vérifié
              </span>
            </div>
          </div>

          <!-- Brief / Description -->
          <section class="rounded-2xl border border-gray-100 bg-white p-4 min-[376px]:p-6 shadow-sm">
            <h2 class="mb-2.5 text-sm font-semibold text-gray-900">{{ isUgc ? 'Brief' : 'Description' }}</h2>
            <p class="whitespace-pre-line text-sm leading-relaxed text-gray-600">{{ mission.description }}</p>
          </section>

          <!-- Photos produit (spec photos produit) — URLs publiques directes ;
               aucune section sans photo (missions pré-deploy) -->
          <section
            v-if="isUgc && mission.product_photos?.length"
            class="rounded-2xl border border-gray-100 bg-white p-4 min-[376px]:p-6 shadow-sm"
          >
            <h2 class="mb-2.5 text-sm font-semibold text-gray-900">Photos du produit</h2>
            <ProductPhotoGallery :photos="mission.product_photos" />
          </section>

          <!-- Livrables UGC -->
          <UgcDeliverablesPreview v-if="isUgc" :nombre-videos="mission.nombre_videos" />

          <!-- Détails de la mission (champs de tournage masqués pour l'UGC : valeurs null) -->
          <section class="rounded-2xl border border-gray-100 bg-white p-4 min-[376px]:p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-gray-900">Détails de la mission</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
              <!-- Date de tournage (masquée pour l'UGC) -->
              <div v-if="!isUgc" class="flex items-start gap-3 rounded-xl bg-gray-50 p-3.5">
                <span class="flex-shrink-0 rounded-lg bg-primary/10 p-2 text-primary">
                  <Calendar class="h-[18px] w-[18px]" />
                </span>
                <div>
                  <p class="text-[10px] uppercase tracking-wider text-gray-500">Date de tournage</p>
                  <p class="text-sm font-medium text-gray-900">{{ formatDate(mission.date_tournage) }}</p>
                </div>
              </div>

              <!-- Lieu (masqué pour l'UGC) -->
              <div v-if="!isUgc" class="flex items-start gap-3 rounded-xl bg-gray-50 p-3.5">
                <span class="flex-shrink-0 rounded-lg bg-primary/10 p-2 text-primary">
                  <MapPin class="h-[18px] w-[18px]" />
                </span>
                <div>
                  <p class="text-[10px] uppercase tracking-wider text-gray-500">Lieu</p>
                  <p class="text-sm font-medium text-gray-900">{{ mission.lieu }}</p>
                </div>
              </div>

              <!-- Rémunération (masquée pour l'UGC : dérivée serveur, la grille stats est canonique) -->
              <div v-if="!isUgc" class="flex items-start gap-3 rounded-xl bg-gray-50 p-3.5">
                <span class="flex-shrink-0 rounded-lg bg-primary/10 p-2 text-primary">
                  <Wallet class="h-[18px] w-[18px]" />
                </span>
                <div>
                  <p class="text-[10px] uppercase tracking-wider text-gray-500">Rémunération proposée</p>
                  <p class="text-sm font-medium text-gray-900">{{ formatCurrency(mission.budget) }}</p>
                </div>
              </div>

              <!-- Nombre de Faces -->
              <div class="flex items-start gap-3 rounded-xl bg-gray-50 p-3.5">
                <span class="flex-shrink-0 rounded-lg bg-primary/10 p-2 text-primary">
                  <Users class="h-[18px] w-[18px]" />
                </span>
                <div>
                  <p class="text-[10px] uppercase tracking-wider text-gray-500">Faces recherchées</p>
                  <p class="text-sm font-medium text-gray-900">{{ mission.nombre_faces_voulu }} Face{{ mission.nombre_faces_voulu > 1 ? 's' : '' }}</p>
                </div>
              </div>

              <!-- Durée (masquée pour l'UGC) -->
              <div v-if="!isUgc" class="flex items-start gap-3 rounded-xl bg-gray-50 p-3.5">
                <span class="flex-shrink-0 rounded-lg bg-primary/10 p-2 text-primary">
                  <Clock class="h-[18px] w-[18px]" />
                </span>
                <div>
                  <p class="text-[10px] uppercase tracking-wider text-gray-500">Durée</p>
                  <p class="text-sm font-medium text-gray-900">{{ formatMissionDurationForDisplay(mission.duree) }}</p>
                </div>
              </div>

              <!-- Genre voulu -->
              <div class="flex items-start gap-3 rounded-xl bg-gray-50 p-3.5">
                <span class="flex-shrink-0 rounded-lg bg-primary/10 p-2 text-primary">
                  <UserCheck class="h-[18px] w-[18px]" />
                </span>
                <div>
                  <p class="text-[10px] uppercase tracking-wider text-gray-500">Genre recherché</p>
                  <p class="text-sm font-medium text-gray-900">{{ mission.genre_voulu_label }}</p>
                </div>
              </div>

              <!-- Date limite candidature -->
              <div class="flex items-start gap-3 rounded-xl bg-gray-50 p-3.5">
                <span class="flex-shrink-0 rounded-lg bg-primary/10 p-2 text-primary">
                  <Target class="h-[18px] w-[18px]" />
                </span>
                <div>
                  <p class="text-[10px] uppercase tracking-wider text-gray-500">Date limite</p>
                  <p class="text-sm font-medium text-gray-900">{{ formatDate(mission.date_limite_candidature) }}</p>
                </div>
              </div>
            </div>
          </section>

          <!-- Profil recherché -->
          <section class="rounded-2xl border border-gray-100 bg-white p-4 min-[376px]:p-6 shadow-sm">
            <h2 class="mb-2.5 text-sm font-semibold text-gray-900">Profil recherché</h2>
            <p class="whitespace-pre-line text-sm leading-relaxed text-gray-600">{{ mission.profil_recherche }}</p>
          </section>

          <!-- Mobile : zone d'action inline une fois la candidature posée (le suivi de
               livraison / les bandeaux n'ont pas leur place dans une barre fixe étroite). -->
          <div v-if="hasApplied" class="lg:hidden">
            <MissionApplyBlock
              v-bind="applyBlockProps"
              @apply="openApplyModal"
              @cancel="openCancelModal"
              @reconfirm="handleReconfirm"
              @resend-verification="handleResendVerification"
              @confirm-receipt="showReceiptModal = true"
              @upload="handleUploadDeliverable"
            />
          </div>
        </div>

        <!-- COLONNE DROITE : carte d'action sticky (desktop uniquement) -->
        <aside class="hidden lg:col-span-5 lg:block xl:col-span-4">
          <div class="sticky top-6 space-y-4">
            <!-- Carte d'action : stats/budget + infos clés + zone Postuler -->
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
              <UgcMissionStats
                v-if="isUgc"
                class="mb-4"
                :valeur-produit="mission.valeur_produit"
                :montant-remuneration="mission.montant_remuneration"
                :nombre-videos="mission.nombre_videos"
              />

              <dl class="mb-4 space-y-2 text-sm">
                <div v-if="!isUgc" class="flex items-center justify-between">
                  <dt class="text-gray-500">Rémunération</dt>
                  <dd class="font-medium text-gray-900">{{ formatCurrency(mission.budget) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                  <dt class="text-gray-500">Faces recherchées</dt>
                  <dd class="font-medium text-gray-900">{{ mission.nombre_faces_voulu }} Face{{ mission.nombre_faces_voulu > 1 ? 's' : '' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                  <dt class="text-gray-500">Genre</dt>
                  <dd class="font-medium text-gray-900">{{ mission.genre_voulu_label }}</dd>
                </div>
                <div class="flex items-center justify-between">
                  <dt class="text-gray-500">Date limite</dt>
                  <dd class="font-medium text-gray-900">{{ formatDate(mission.date_limite_candidature) }}</dd>
                </div>
              </dl>

              <div class="mb-4 h-px w-full bg-gray-100"></div>

              <MissionApplyBlock
                v-bind="applyBlockProps"
                @apply="openApplyModal"
                @cancel="openCancelModal"
                @reconfirm="handleReconfirm"
                @resend-verification="handleResendVerification"
                @confirm-receipt="showReceiptModal = true"
                @upload="handleUploadDeliverable"
              />
            </div>

            <!-- Carte producteur -->
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
              <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Producteur</h2>
              <router-link
                v-if="mission.producer?.slug"
                :to="`/producers/${mission.producer.slug}`"
                class="-m-1 flex items-center gap-3 rounded-lg p-1 transition-colors hover:bg-muted/50"
                :aria-label="`Voir le profil de ${producerName}`"
              >
                <div class="h-11 w-11 flex-shrink-0 overflow-hidden rounded-full border border-border bg-muted">
                  <img v-if="producerAvatarUrl" :src="producerAvatarUrl" :alt="producerName" class="h-full w-full object-cover" />
                  <div v-else class="flex h-full w-full items-center justify-center bg-primary/10 text-base font-bold uppercase text-primary">{{ producerInitials }}</div>
                </div>
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-gray-900">{{ producerName }}</p>
                  <p class="text-xs text-gray-400">{{ producerTypeLabel }}</p>
                  <RatingDisplay :average-rating="producerRating" :review-count="producerRatingsCount" />
                </div>
              </router-link>
              <div v-else class="flex items-center gap-3">
                <div class="h-11 w-11 flex-shrink-0 overflow-hidden rounded-full border border-border bg-muted">
                  <img v-if="producerAvatarUrl" :src="producerAvatarUrl" :alt="producerName" class="h-full w-full object-cover" />
                  <div v-else class="flex h-full w-full items-center justify-center bg-primary/10 text-base font-bold uppercase text-primary">{{ producerInitials }}</div>
                </div>
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-gray-900">{{ producerName }}</p>
                  <p class="text-xs text-gray-400">{{ producerTypeLabel }}</p>
                  <RatingDisplay :average-rating="producerRating" :review-count="producerRatingsCount" />
                </div>
              </div>
              <p v-if="isUgc" class="mt-3 flex items-center gap-1.5 text-xs font-medium text-primary" data-testid="ugc-verified-producer">
                <ShieldCheck class="h-3.5 w-3.5" aria-hidden="true" />
                Producteur vérifié — dotation réglée via WeAct
              </p>
            </div>
          </div>
        </aside>
      </div>

      <!-- Barre d'action fixe en bas (mobile) — CTA toujours visible tant qu'on n'a
           pas postulé ; une fois la candidature posée, l'état passe inline (colonne
           gauche). z-30 < menu mobile (z-40/z-50) → bien recouvert à l'ouverture. -->
      <div
        v-if="!hasApplied"
        class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-100 bg-white/95 px-4 pt-3 pb-7 backdrop-blur lg:hidden"
      >
        <MissionApplyBlock
          v-bind="applyBlockProps"
          @apply="openApplyModal"
          @cancel="openCancelModal"
          @reconfirm="handleReconfirm"
          @resend-verification="handleResendVerification"
          @confirm-receipt="showReceiptModal = true"
          @upload="handleUploadDeliverable"
        />
      </div>
    </div>

    <!-- Apply Modal -->
    <ApplyToMissionModal
      v-if="mission"
      :is-open="isApplyModalOpen"
      :mission-id="mission.id"
      :mission-title="mission.titre"
      @close="closeApplyModal"
      @success="handleApplySuccess"
    />

    <!-- Cancel Confirmation Modal -->
    <ConfirmModal
      :is-open="showCancelModal"
      title="Annuler cette candidature ?"
      message="Cette action est définitive. Vous ne pourrez pas postuler à nouveau pour cette mission."
      confirm-text="Oui, annuler"
      cancel-text="Non, garder"
      variant="warning"
      @confirm="handleCancelConfirm"
      @cancel="closeCancelModal"
    />

    <!-- Confirmation « Produit reçu » (3.4, D-3.4.c) — state dédié, chrono 7j irréversible -->
    <ConfirmModal
      :is-open="showReceiptModal"
      title="Confirmer la réception ?"
      :message="receiptModalMessage"
      confirm-text="Oui, j'ai reçu le produit"
      cancel-text="Pas encore"
      variant="warning"
      @confirm="handleConfirmReceipt"
      @cancel="showReceiptModal = false"
    />
  </div>
</template>
