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
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useMissionDetail } from '@/features/mission/composables'
import { ApplyToMissionModal } from '@/features/candidature/components'
import RatingDisplay from '@/components/RatingDisplay.vue'
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

// Email verification resend state
const isResendingVerification = ref(false)

// Get mission ID from route params
const missionId = computed(() => {
  const id = route.params.id
  if (typeof id !== 'string') return null
  const parsed = parseInt(id, 10)
  return Number.isNaN(parsed) ? null : parsed
})

// Computed: Has the user already applied?
const hasApplied = computed(() => candidature.value !== null)

// Computed: Can the user apply? (must have verified email)
const canApply = computed(() => authStore.isEmailVerified)

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
  // Use router.back() to preserve filters from missions list (AC #7)
  // Fallback to missions list if no history
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
  // Update local candidature state
  setCandidature({
    id: newCandidature.id,
    mission_id: missionId.value!,
    face_id: 0, // We don't need the exact face_id on frontend
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
      // Refresh user data to update the UI
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
 * LIFECYCLE
 */
onMounted(() => {
  if (missionId.value) {
    fetchMission(missionId.value)
  }
})
</script>

<template>
  <div class="min-h-screen bg-background pb-20">
    <!-- Header Section -->
    <header class="sticky top-0 z-20 border-b border-border bg-background/80 backdrop-blur-md">
      <div class="container mx-auto flex h-16 items-center gap-4 px-4 sm:px-6">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm font-medium transition-colors hover:bg-muted"
          @click="goBack"
        >
          <ArrowLeft class="h-4 w-4" />
          <span class="hidden sm:inline">Retour aux missions</span>
        </button>
      </div>
    </header>

    <main class="container mx-auto mt-8 px-4 sm:px-6">
      <!-- Loading State -->
      <div
        v-if="isLoading"
        class="flex flex-col items-center justify-center py-24"
      >
        <Loader2 class="h-12 w-12 animate-spin text-primary" />
        <p class="mt-4 text-muted-foreground">Chargement de la mission...</p>
      </div>

      <!-- Not Found State -->
      <div
        v-else-if="notFound"
        class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-muted py-24 text-center"
      >
        <div class="mb-4 rounded-full bg-muted p-4">
          <AlertCircle class="h-10 w-10 text-muted-foreground" />
        </div>
        <h3 class="text-xl font-bold text-foreground">Mission introuvable</h3>
        <p class="mt-2 max-w-xs text-muted-foreground">
          Cette mission n'existe pas ou n'est plus disponible.
        </p>
        <button
          type="button"
          class="mt-6 inline-flex items-center gap-2 rounded-lg border border-border bg-card px-6 py-2 text-sm font-medium transition-colors hover:bg-muted"
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
        <h3 class="text-xl font-bold text-foreground">Oups ! Une erreur est survenue</h3>
        <p class="mt-2 max-w-xs text-muted-foreground">
          Impossible de charger les détails de la mission.
        </p>
        <p class="mt-2 text-sm text-destructive">{{ error }}</p>
        <button
          type="button"
          class="mt-6 inline-flex items-center gap-2 rounded-lg border border-border bg-card px-6 py-2 text-sm font-medium transition-colors hover:bg-muted"
          @click="goBack"
        >
          <ArrowLeft class="h-4 w-4" />
          Retour aux missions
        </button>
      </div>

      <!-- Mission Detail Content -->
      <div v-else-if="mission" class="max-w-4xl mx-auto">
        <!-- Mission Type Badge -->
        <div class="mb-4">
          <span
            class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary"
          >
            {{ mission.type_mission_label }}
          </span>
        </div>

        <!-- Mission Title -->
        <h1 class="text-2xl sm:text-3xl font-bold text-foreground mb-6">
          {{ mission.titre }}
        </h1>

        <!-- Mission Description -->
        <div class="mb-8 rounded-lg border border-border bg-card p-6">
          <h2 class="text-lg font-semibold text-foreground mb-3">Description</h2>
          <p class="text-muted-foreground whitespace-pre-wrap leading-relaxed">
            {{ mission.description }}
          </p>
        </div>

        <!-- Mission Details Grid -->
        <div class="mb-8 rounded-lg border border-border bg-card p-6">
          <h2 class="text-lg font-semibold text-foreground mb-4">Détails de la mission</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Date de tournage -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-muted/50">
              <div class="flex-shrink-0 p-2 rounded-lg bg-primary/10">
                <Calendar class="h-5 w-5 text-primary" />
              </div>
              <div>
                <p class="text-xs text-muted-foreground uppercase tracking-wider">Date de tournage</p>
                <p class="text-sm font-medium text-foreground">{{ formatDate(mission.date_tournage) }}</p>
              </div>
            </div>

            <!-- Lieu -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-muted/50">
              <div class="flex-shrink-0 p-2 rounded-lg bg-primary/10">
                <MapPin class="h-5 w-5 text-primary" />
              </div>
              <div>
                <p class="text-xs text-muted-foreground uppercase tracking-wider">Lieu</p>
                <p class="text-sm font-medium text-foreground">{{ mission.lieu }}</p>
              </div>
            </div>

            <!-- Budget -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-muted/50">
              <div class="flex-shrink-0 p-2 rounded-lg bg-primary/10">
                <Wallet class="h-5 w-5 text-primary" />
              </div>
              <div>
                <p class="text-xs text-muted-foreground uppercase tracking-wider">Budget</p>
                <p class="text-sm font-medium text-foreground">{{ formatCurrency(mission.budget) }}</p>
              </div>
            </div>

            <!-- Nombre de Faces -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-muted/50">
              <div class="flex-shrink-0 p-2 rounded-lg bg-primary/10">
                <Users class="h-5 w-5 text-primary" />
              </div>
              <div>
                <p class="text-xs text-muted-foreground uppercase tracking-wider">Faces recherchées</p>
                <p class="text-sm font-medium text-foreground">{{ mission.nombre_faces_voulu }} Face{{ mission.nombre_faces_voulu > 1 ? 's' : '' }}</p>
              </div>
            </div>

            <!-- Durée -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-muted/50">
              <div class="flex-shrink-0 p-2 rounded-lg bg-primary/10">
                <Clock class="h-5 w-5 text-primary" />
              </div>
              <div>
                <p class="text-xs text-muted-foreground uppercase tracking-wider">Durée</p>
                <p class="text-sm font-medium text-foreground">{{ mission.duree }}</p>
              </div>
            </div>

            <!-- Genre voulu -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-muted/50">
              <div class="flex-shrink-0 p-2 rounded-lg bg-primary/10">
                <UserCheck class="h-5 w-5 text-primary" />
              </div>
              <div>
                <p class="text-xs text-muted-foreground uppercase tracking-wider">Genre recherché</p>
                <p class="text-sm font-medium text-foreground">{{ mission.genre_voulu_label }}</p>
              </div>
            </div>

            <!-- Date limite candidature -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-muted/50 sm:col-span-2">
              <div class="flex-shrink-0 p-2 rounded-lg bg-primary/10">
                <Target class="h-5 w-5 text-primary" />
              </div>
              <div>
                <p class="text-xs text-muted-foreground uppercase tracking-wider">Date limite de candidature</p>
                <p class="text-sm font-medium text-foreground">{{ formatDate(mission.date_limite_candidature) }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Profil recherché -->
        <div class="mb-8 rounded-lg border border-border bg-card p-6">
          <h2 class="text-lg font-semibold text-foreground mb-3">Profil recherché</h2>
          <p class="text-muted-foreground whitespace-pre-wrap leading-relaxed">
            {{ mission.profil_recherche }}
          </p>
        </div>

        <!-- Producer Card -->
        <div class="mb-8 rounded-lg border border-border bg-card p-6">
          <h2 class="text-lg font-semibold text-foreground mb-4">Producteur</h2>
          <router-link
            v-if="mission.producer?.id"
            :to="`/producers/${mission.producer.id}`"
            class="flex items-center gap-4 rounded-lg p-2 -m-2 transition-colors hover:bg-muted/50"
            :aria-label="`Voir le profil de ${producerName}`"
          >
            <div class="relative h-16 w-16 flex-shrink-0 overflow-hidden rounded-full border-2 border-border bg-muted">
              <img
                v-if="producerAvatarUrl"
                :src="producerAvatarUrl"
                :alt="producerName"
                class="h-full w-full object-cover"
              />
              <div
                v-else
                class="flex h-full w-full items-center justify-center bg-primary/10 text-xl font-bold uppercase text-primary"
              >
                {{ producerInitials }}
              </div>
            </div>
            <div class="flex flex-col gap-1">
              <p class="text-lg font-semibold text-foreground">{{ producerName }}</p>
              <p class="text-sm text-muted-foreground">{{ producerTypeLabel }}</p>
              <RatingDisplay
                :average-rating="producerRating"
                :review-count="producerRatingsCount"
              />
            </div>
          </router-link>
          <div v-else class="flex items-center gap-4">
            <div class="relative h-16 w-16 flex-shrink-0 overflow-hidden rounded-full border-2 border-border bg-muted">
              <div class="flex h-full w-full items-center justify-center bg-primary/10 text-xl font-bold uppercase text-primary">
                {{ producerInitials }}
              </div>
            </div>
            <div class="flex flex-col gap-1">
              <p class="text-lg font-semibold text-foreground">{{ producerName }}</p>
              <p class="text-sm text-muted-foreground">{{ producerTypeLabel }}</p>
              <RatingDisplay
                :average-rating="producerRating"
                :review-count="producerRatingsCount"
              />
            </div>
          </div>
        </div>

        <!-- Apply Button (Sticky on mobile) -->
        <div class="fixed bottom-0 left-0 right-0 border-t border-border bg-background/80 backdrop-blur-md p-4 sm:static sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none">
          <div class="container mx-auto max-w-4xl">
            <!-- State 1: Already Applied -->
            <div
              v-if="hasApplied"
              class="flex items-center justify-center gap-2 rounded-lg border border-green-200 bg-green-50 px-8 py-3 text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400"
            >
              <CheckCircle class="h-5 w-5" />
              <span class="font-medium">Candidature envoyée</span>
              <span class="text-sm opacity-75">({{ candidature?.status_label }})</span>
            </div>

            <!-- State 2: Email not verified - Show blocked state -->
            <div
              v-else-if="!canApply && mission.is_accepting_candidatures"
              class="rounded-lg border border-amber-200 bg-amber-50 p-4"
              data-testid="email-verification-apply-block"
            >
              <div class="flex flex-col sm:flex-row items-center gap-4">
                <div class="flex items-center gap-3">
                  <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                    <ShieldAlert class="h-5 w-5 text-amber-600" />
                  </div>
                  <div class="text-center sm:text-left">
                    <p class="text-sm font-medium text-amber-800">Vérification email requise</p>
                    <p class="text-xs text-amber-700">Vous devez vérifier votre email pour postuler.</p>
                  </div>
                </div>
                <button
                  type="button"
                  :disabled="isResendingVerification"
                  class="flex-shrink-0 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  @click="handleResendVerification"
                >
                  <Mail v-if="!isResendingVerification" class="h-4 w-4" />
                  <Loader2 v-else class="h-4 w-4 animate-spin" />
                  {{ isResendingVerification ? 'Envoi...' : "Renvoyer l'email" }}
                </button>
              </div>
            </div>

            <!-- State 3: Can Apply (email verified) -->
            <button
              v-else-if="mission.is_accepting_candidatures"
              type="button"
              class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-8 py-3 text-base font-semibold text-white transition-colors hover:bg-primary/90"
              @click="openApplyModal"
            >
              Postuler à cette mission
            </button>

            <!-- State 4: Mission Closed -->
            <div
              v-else
              class="flex items-center justify-center gap-2 rounded-lg border border-muted bg-muted/50 px-8 py-3 text-muted-foreground"
            >
              <AlertCircle class="h-5 w-5" />
              <span>Les candidatures sont fermées pour cette mission</span>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Apply Modal -->
    <ApplyToMissionModal
      v-if="mission"
      :is-open="isApplyModalOpen"
      :mission-id="mission.id"
      :mission-title="mission.titre"
      @close="closeApplyModal"
      @success="handleApplySuccess"
    />
  </div>
</template>

<!-- Styles use Tailwind CSS variables from main.css -->
