<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
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
  CheckCircle,
  ShieldAlert,
  Mail,
  XCircle,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useMissionDetail } from '@/features/mission/composables'
import { formatMissionDurationForDisplay } from '@/features/mission/constants/missionDuration'
import { ApplyToMissionModal } from '@/features/candidature/components'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import RatingDisplay from '@/components/RatingDisplay.vue'
import { useCancelCandidature } from '@/features/candidature/composables'
import { authApi } from '@/features/auth/services/authApi'
import { useToast } from '@/composables/useToast'

/**
 * LOGIC & STATE MANAGEMENT
 */
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const { mission, candidature, isLoading, error, notFound, fetchMission, setCandidature } = useMissionDetail()

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

// Email verification resend state
const isResendingVerification = ref(false)
const isRefreshingGenderContext = ref(false)

// Get mission ID from route params
const missionId = computed(() => {
  const id = route.params.id
  if (typeof id !== 'string') return null
  const parsed = parseInt(id, 10)
  return Number.isNaN(parsed) ? null : parsed
})

// Computed: Has the user already applied?
const hasApplied = computed(() => candidature.value !== null)

// Computed: Can the user cancel? (only pending candidatures)
const canCancelCandidature = computed(() => candidature.value?.status === 'pending')

// Computed: Can the user apply? (must have verified email)
const canApply = computed(() => authStore.isEmailVerified)

const isGenderContextUnknown = computed((): boolean => {
  if (!mission.value) return false
  if (mission.value.genre_voulu === 'tous') return false
  return authStore.isFace && authStore.user?.userable !== undefined && authStore.user?.userable?.sexe === undefined
})

// Computed: Gender mismatch check
const isGenderMismatch = computed((): boolean => {
  if (!mission.value) return false
  const genreVoulu = mission.value.genre_voulu
  if (genreVoulu === 'tous') return false
  const faceSexe = authStore.user?.userable?.sexe
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
  const faceSexe = authStore.user?.userable?.sexe
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

function handleApplySuccess(newCandidature: { id: number; status: string; status_label: string }): void {
  setCandidature({
    id: newCandidature.id,
    mission_id: missionId.value!,
    face_id: 0,
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
    <div v-else-if="mission" class="max-w-4xl">
      <!-- Mission Type Badge -->
      <div class="mb-4">
        <span
          class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-xs min-[376px]:text-sm font-medium text-primary"
        >
          {{ mission.type_mission === 'autre' && mission.type_mission_autre ? `Autre : ${mission.type_mission_autre}` : mission.type_mission_label }}
        </span>
      </div>

      <!-- Mission Title -->
      <h1 class="text-xl min-[376px]:text-2xl sm:text-3xl font-bold text-foreground mb-6">
        {{ mission.titre }}
      </h1>

      <!-- Mission Description -->
      <div class="mb-8 rounded-lg border border-border bg-card p-4 min-[376px]:p-6">
        <h2 class="text-base min-[376px]:text-lg font-semibold text-foreground mb-3">Description</h2>
        <p class="text-xs min-[376px]:text-sm text-muted-foreground whitespace-pre-wrap leading-relaxed">
          {{ mission.description }}
        </p>
      </div>

      <!-- Mission Details Grid -->
      <div class="mb-8 rounded-lg border border-border bg-card p-4 min-[376px]:p-6">
        <h2 class="text-base min-[376px]:text-lg font-semibold text-foreground mb-4">Détails de la mission</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 min-[376px]:gap-4">
          <!-- Date de tournage -->
          <div class="flex items-start gap-3 p-2 min-[376px]:p-3 rounded-lg bg-muted/50">
            <div class="flex-shrink-0 p-1.5 min-[376px]:p-2 rounded-lg bg-primary/10">
              <Calendar class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-primary" />
            </div>
            <div>
              <p class="text-[10px] min-[376px]:text-xs text-muted-foreground uppercase tracking-wider">Date de tournage</p>
              <p class="text-xs min-[376px]:text-sm font-medium text-foreground">{{ formatDate(mission.date_tournage) }}</p>
            </div>
          </div>

          <!-- Lieu -->
          <div class="flex items-start gap-3 p-2 min-[376px]:p-3 rounded-lg bg-muted/50">
            <div class="flex-shrink-0 p-1.5 min-[376px]:p-2 rounded-lg bg-primary/10">
              <MapPin class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-primary" />
            </div>
            <div>
              <p class="text-[10px] min-[376px]:text-xs text-muted-foreground uppercase tracking-wider">Lieu</p>
              <p class="text-xs min-[376px]:text-sm font-medium text-foreground">{{ mission.lieu }}</p>
            </div>
          </div>

          <!-- Budget -->
          <div class="flex items-start gap-3 p-2 min-[376px]:p-3 rounded-lg bg-muted/50">
            <div class="flex-shrink-0 p-1.5 min-[376px]:p-2 rounded-lg bg-primary/10">
              <Wallet class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-primary" />
            </div>
            <div>
              <p class="text-[10px] min-[376px]:text-xs text-muted-foreground uppercase tracking-wider">Rémunération proposée</p>
              <p class="text-xs min-[376px]:text-sm font-medium text-foreground">{{ formatCurrency(mission.budget) }}</p>
            </div>
          </div>

          <!-- Nombre de Faces -->
          <div class="flex items-start gap-3 p-2 min-[376px]:p-3 rounded-lg bg-muted/50">
            <div class="flex-shrink-0 p-1.5 min-[376px]:p-2 rounded-lg bg-primary/10">
              <Users class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-primary" />
            </div>
            <div>
              <p class="text-[10px] min-[376px]:text-xs text-muted-foreground uppercase tracking-wider">Faces recherchées</p>
              <p class="text-xs min-[376px]:text-sm font-medium text-foreground">{{ mission.nombre_faces_voulu }} Face{{ mission.nombre_faces_voulu > 1 ? 's' : '' }}</p>
            </div>
          </div>

          <!-- Durée -->
          <div class="flex items-start gap-3 p-2 min-[376px]:p-3 rounded-lg bg-muted/50">
            <div class="flex-shrink-0 p-1.5 min-[376px]:p-2 rounded-lg bg-primary/10">
              <Clock class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-primary" />
            </div>
            <div>
              <p class="text-[10px] min-[376px]:text-xs text-muted-foreground uppercase tracking-wider">Durée</p>
              <p class="text-xs min-[376px]:text-sm font-medium text-foreground">{{ formatMissionDurationForDisplay(mission.duree) }}</p>
            </div>
          </div>

          <!-- Genre voulu -->
          <div class="flex items-start gap-3 p-2 min-[376px]:p-3 rounded-lg bg-muted/50">
            <div class="flex-shrink-0 p-1.5 min-[376px]:p-2 rounded-lg bg-primary/10">
              <UserCheck class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-primary" />
            </div>
            <div>
              <p class="text-[10px] min-[376px]:text-xs text-muted-foreground uppercase tracking-wider">Genre recherché</p>
              <p class="text-xs min-[376px]:text-sm font-medium text-foreground">{{ mission.genre_voulu_label }}</p>
            </div>
          </div>

          <!-- Date limite candidature -->
          <div class="flex items-start gap-3 p-2 min-[376px]:p-3 rounded-lg bg-muted/50 sm:col-span-2">
            <div class="flex-shrink-0 p-1.5 min-[376px]:p-2 rounded-lg bg-primary/10">
              <Target class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-primary" />
            </div>
            <div>
              <p class="text-[10px] min-[376px]:text-xs text-muted-foreground uppercase tracking-wider">Date limite de candidature</p>
              <p class="text-xs min-[376px]:text-sm font-medium text-foreground">{{ formatDate(mission.date_limite_candidature) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Profil recherché -->
      <div class="mb-8 rounded-lg border border-border bg-card p-4 min-[376px]:p-6">
        <h2 class="text-base min-[376px]:text-lg font-semibold text-foreground mb-3">Profil recherché</h2>
        <p class="text-xs min-[376px]:text-sm text-muted-foreground whitespace-pre-wrap leading-relaxed">
          {{ mission.profil_recherche }}
        </p>
      </div>

      <!-- Producer Card -->
      <div class="mb-8 rounded-lg border border-border bg-card p-4 min-[376px]:p-6">
        <h2 class="text-base min-[376px]:text-lg font-semibold text-foreground mb-4">Producteur</h2>
        <router-link
          v-if="mission.producer?.id"
          :to="`/producers/${mission.producer.id}`"
          class="flex items-center gap-3 min-[376px]:gap-4 rounded-lg p-2 -m-2 transition-colors hover:bg-muted/50"
          :aria-label="`Voir le profil de ${producerName}`"
        >
          <div class="relative h-12 w-12 min-[376px]:h-16 min-[376px]:w-16 flex-shrink-0 overflow-hidden rounded-full border-2 border-border bg-muted">
            <img
              v-if="producerAvatarUrl"
              :src="producerAvatarUrl"
              :alt="producerName"
              class="h-full w-full object-cover"
            />
            <div
              v-else
              class="flex h-full w-full items-center justify-center bg-primary/10 text-base min-[376px]:text-xl font-bold uppercase text-primary"
            >
              {{ producerInitials }}
            </div>
          </div>
          <div class="flex flex-col gap-0.5 min-[376px]:gap-1">
            <p class="text-base min-[376px]:text-lg font-semibold text-foreground">{{ producerName }}</p>
            <p class="text-xs min-[376px]:text-sm text-muted-foreground">{{ producerTypeLabel }}</p>
            <RatingDisplay
              :average-rating="producerRating"
              :review-count="producerRatingsCount"
            />
          </div>
        </router-link>
        <div v-else class="flex items-center gap-3 min-[376px]:gap-4">
          <div class="relative h-12 w-12 min-[376px]:h-16 min-[376px]:w-16 flex-shrink-0 overflow-hidden rounded-full border-2 border-border bg-muted">
            <div class="flex h-full w-full items-center justify-center bg-primary/10 text-base min-[376px]:text-xl font-bold uppercase text-primary">
              {{ producerInitials }}
            </div>
          </div>
          <div class="flex flex-col gap-0.5 min-[376px]:gap-1">
            <p class="text-base min-[376px]:text-lg font-semibold text-foreground">{{ producerName }}</p>
            <p class="text-xs min-[376px]:text-sm text-muted-foreground">{{ producerTypeLabel }}</p>
            <RatingDisplay
              :average-rating="producerRating"
              :review-count="producerRatingsCount"
            />
          </div>
        </div>
      </div>

      <!-- Apply Section -->
      <div class="mb-8">
        <!-- State 1: Already Applied -->
        <div v-if="hasApplied" class="space-y-3">
          <div
            class="flex items-center justify-center gap-2 rounded-lg border px-6 min-[376px]:px-8 py-3"
            :class="canCancelCandidature
              ? 'border-green-200 bg-green-50 text-green-700'
              : candidature?.status === 'cancelled'
                ? 'border-gray-200 bg-gray-50 text-gray-600'
                : 'border-green-200 bg-green-50 text-green-700'"
          >
            <CheckCircle v-if="candidature?.status !== 'cancelled'" class="h-5 w-5" />
            <XCircle v-else class="h-5 w-5" />
            <span class="text-xs min-[376px]:text-sm font-medium">
              {{ candidature?.status === 'cancelled' ? 'Candidature annulée' : 'Candidature envoyée' }}
            </span>
            <span class="text-xs opacity-75">({{ candidature?.status_label }})</span>
          </div>
          <button
            v-if="canCancelCandidature"
            type="button"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-6 min-[376px]:px-8 py-2.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="isCancelling"
            @click="openCancelModal"
          >
            <Loader2 v-if="isCancelling" class="h-4 w-4 animate-spin" />
            <XCircle v-else class="h-4 w-4" />
            {{ isCancelling ? 'Annulation...' : 'Annuler ma candidature' }}
          </button>
        </div>

        <!-- State 2: Email not verified -->
        <div
          v-else-if="!canApply && mission.is_accepting_candidatures"
          class="rounded-lg border border-amber-200 bg-amber-50 p-3 min-[376px]:p-4"
          data-testid="email-verification-apply-block"
        >
          <div class="flex flex-col sm:flex-row items-center gap-3 min-[376px]:gap-4">
            <div class="flex items-center gap-3">
              <div class="flex-shrink-0 w-9 h-9 min-[376px]:w-10 min-[376px]:h-10 rounded-full bg-amber-100 flex items-center justify-center">
                <ShieldAlert class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-amber-600" />
              </div>
              <div class="text-center sm:text-left">
                <p class="text-xs min-[376px]:text-sm font-medium text-amber-800">Vérification email requise</p>
                <p class="text-[10px] min-[376px]:text-xs text-amber-700">Vous devez vérifier votre email pour postuler.</p>
              </div>
            </div>
            <button
              type="button"
              :disabled="isResendingVerification"
              class="flex-shrink-0 inline-flex items-center gap-2 px-3 min-[376px]:px-4 py-2 text-xs min-[376px]:text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              @click="handleResendVerification"
            >
              <Mail v-if="!isResendingVerification" class="h-4 w-4" />
              <Loader2 v-else class="h-4 w-4 animate-spin" />
              {{ isResendingVerification ? 'Envoi...' : "Renvoyer l'email" }}
            </button>
          </div>
        </div>

        <!-- State 3: Gender context unavailable -->
        <div
          v-else-if="mission.is_accepting_candidatures && isGenderContextUnknown"
          class="rounded-lg border border-amber-200 bg-amber-50 p-3 min-[376px]:p-4"
          data-testid="gender-context-block"
        >
          <div class="flex flex-col gap-3">
            <div class="flex items-center gap-3">
              <div class="flex-shrink-0 w-9 h-9 min-[376px]:w-10 min-[376px]:h-10 rounded-full bg-amber-100 flex items-center justify-center">
                <Loader2
                  v-if="isRefreshingGenderContext"
                  class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-amber-600 animate-spin"
                />
                <ShieldAlert
                  v-else
                  class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-amber-600"
                />
              </div>
              <div>
                <p class="text-xs min-[376px]:text-sm font-medium text-amber-800">Validation du profil requise</p>
                <p
                  id="gender-context-message"
                  class="text-[10px] min-[376px]:text-xs text-amber-700"
                >
                  {{ genderContextMessage }}
                </p>
              </div>
            </div>
            <div>
              <button
                type="button"
                disabled
                aria-describedby="gender-context-message"
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 min-[376px]:px-8 py-3 text-sm min-[376px]:text-base font-semibold text-white opacity-50 cursor-not-allowed"
                data-testid="apply-button-disabled"
              >
                Postuler à cette mission
              </button>
            </div>
          </div>
        </div>

        <!-- State 4: Gender mismatch -->
        <div
          v-else-if="mission.is_accepting_candidatures && isGenderMismatch"
          class="rounded-lg border border-amber-200 bg-amber-50 p-3 min-[376px]:p-4"
          data-testid="gender-mismatch-block"
        >
          <div class="flex flex-col gap-3">
            <div class="flex items-center gap-3">
              <div class="flex-shrink-0 w-9 h-9 min-[376px]:w-10 min-[376px]:h-10 rounded-full bg-amber-100 flex items-center justify-center">
                <ShieldAlert class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-amber-600" />
              </div>
              <div>
                <p class="text-xs min-[376px]:text-sm font-medium text-amber-800">Candidature non autorisée</p>
                <p
                  id="gender-mismatch-message"
                  class="text-[10px] min-[376px]:text-xs text-amber-700"
                >
                  {{ genderMismatchMessage }}
                </p>
              </div>
            </div>
            <div>
              <button
                type="button"
                disabled
                aria-describedby="gender-mismatch-message"
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 min-[376px]:px-8 py-3 text-sm min-[376px]:text-base font-semibold text-white opacity-50 cursor-not-allowed"
                data-testid="apply-button-disabled"
              >
                Postuler à cette mission
              </button>
            </div>
          </div>
        </div>

        <!-- State 5: Can Apply -->
        <button
          v-else-if="mission.is_accepting_candidatures"
          type="button"
          class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 min-[376px]:px-8 py-3 text-sm min-[376px]:text-base font-semibold text-white transition-colors hover:bg-primary/90"
          @click="openApplyModal"
        >
          Postuler à cette mission
        </button>

        <!-- State 6: Mission Closed -->
        <div
          v-else
          class="flex items-center justify-center gap-2 rounded-lg border border-muted bg-muted/50 px-6 min-[376px]:px-8 py-3 text-xs min-[376px]:text-sm text-muted-foreground"
        >
          <AlertCircle class="h-5 w-5" />
          <span>Les candidatures sont fermées pour cette mission</span>
        </div>
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
  </div>
</template>
