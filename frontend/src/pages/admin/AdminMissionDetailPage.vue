<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  AlertCircle,
  Loader2,
  Briefcase,
  MapPin,
  Calendar,
  Users,
  DollarSign,
  Clock,
  CheckCircle,
  XCircle,
} from 'lucide-vue-next'
import { useAdminMissions } from '@/features/admin/composables/useAdminMissions'
import { formatMissionDurationForDisplay } from '@/features/mission/constants/missionDuration'

const route = useRoute()
const router = useRouter()
const { mission, isLoading, error, fetchMission } = useAdminMissions()

const missionId = computed(() => route.params.id as string)

onMounted(() => {
  if (!missionId.value) {
    router.replace({ name: 'admin-missions-list' })
    return
  }
  fetchMission(missionId.value)
})

function getStatusClass(status: string): string {
  switch (status) {
    case 'draft': return 'bg-gray-100 text-gray-800'
    case 'published': return 'bg-green-100 text-green-800'
    case 'closed': return 'bg-orange-100 text-orange-800'
    case 'completed': return 'bg-blue-100 text-blue-800'
    default: return 'bg-gray-100 text-gray-800'
  }
}

function formatDate(dateString: string | null): string {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

function formatBudget(budget: number | null): string {
  if (budget === null || budget === undefined) return '—'
  return new Intl.NumberFormat('fr-FR').format(budget) + ' XOF'
}

function goToProducer(producerId: string): void {
  router.push({ name: 'admin-producer-detail', params: { id: producerId } })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Back Link -->
    <router-link
      :to="{ name: 'admin-missions-list' }"
      class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-900 transition-colors"
    >
      <ArrowLeft class="h-4 w-4" />
      Retour à la liste
    </router-link>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-12">
      <Loader2 class="h-8 w-8 text-primary-500 animate-spin" />
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="rounded-lg bg-red-50 border border-red-200 p-4 flex items-start gap-3"
      role="alert"
    >
      <AlertCircle class="h-5 w-5 text-red-500 mt-0.5 shrink-0" />
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Mission Detail -->
    <template v-else-if="mission">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">{{ mission.titre }}</h1>
          <p class="mt-1 text-sm text-gray-500">
            Mission #{{ mission.id }} — Créée le {{ formatDate(mission.created_at) }}
          </p>
        </div>
        <span
          class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium shrink-0"
          :class="getStatusClass(mission.status)"
        >
          {{ mission.status_label }}
        </span>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Main Info -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Description -->
          <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Description</h2>
            <p v-if="mission.description" class="text-sm text-gray-700 whitespace-pre-line">{{ mission.description }}</p>
            <p v-else class="text-sm text-gray-400 italic">Aucune description</p>
          </div>

          <!-- Profil recherché -->
          <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Profil recherché</h2>
            <p v-if="mission.profil_recherche" class="text-sm text-gray-700 whitespace-pre-line">{{ mission.profil_recherche }}</p>
            <p v-else class="text-sm text-gray-400 italic">Non spécifié</p>
          </div>

          <!-- Mission Details Grid -->
          <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Détails de la mission</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
              <div>
                <dt class="flex items-center gap-2 text-sm font-medium text-gray-500">
                  <Briefcase class="h-4 w-4" />
                  Type
                </dt>
                <dd class="mt-1 text-sm text-gray-900">{{ mission.type_mission === 'autre' && mission.type_mission_autre ? `Autre : ${mission.type_mission_autre}` : (mission.type_mission_label ?? '—') }}</dd>
              </div>
              <div>
                <dt class="flex items-center gap-2 text-sm font-medium text-gray-500">
                  <Users class="h-4 w-4" />
                  Genre recherché
                </dt>
                <dd class="mt-1 text-sm text-gray-900">{{ mission.genre_voulu_label ?? '—' }}</dd>
              </div>
              <div>
                <dt class="flex items-center gap-2 text-sm font-medium text-gray-500">
                  <MapPin class="h-4 w-4" />
                  Lieu
                </dt>
                <dd class="mt-1 text-sm text-gray-900">{{ mission.lieu ?? '—' }}</dd>
              </div>
              <div>
                <dt class="flex items-center gap-2 text-sm font-medium text-gray-500">
                  <Clock class="h-4 w-4" />
                  Durée
                </dt>
                <dd class="mt-1 text-sm text-gray-900">{{ formatMissionDurationForDisplay(mission.duree) || '—' }}</dd>
              </div>
              <div>
                <dt class="flex items-center gap-2 text-sm font-medium text-gray-500">
                  <DollarSign class="h-4 w-4" />
                  Budget
                </dt>
                <dd class="mt-1 text-sm text-gray-900">{{ formatBudget(mission.budget) }}</dd>
              </div>
              <div>
                <dt class="flex items-center gap-2 text-sm font-medium text-gray-500">
                  <Users class="h-4 w-4" />
                  Faces souhaitées
                </dt>
                <dd class="mt-1 text-sm text-gray-900">{{ mission.nombre_faces_voulu ?? '—' }}</dd>
              </div>
              <div>
                <dt class="flex items-center gap-2 text-sm font-medium text-gray-500">
                  <Calendar class="h-4 w-4" />
                  Date de tournage
                </dt>
                <dd class="mt-1 text-sm text-gray-900">{{ formatDate(mission.date_tournage) }}</dd>
              </div>
              <div>
                <dt class="flex items-center gap-2 text-sm font-medium text-gray-500">
                  <Calendar class="h-4 w-4" />
                  Date limite candidature
                </dt>
                <dd class="mt-1 text-sm text-gray-900">{{ formatDate(mission.date_limite_candidature) }}</dd>
              </div>
            </dl>
          </div>
        </div>

        <!-- Right Column: Sidebar -->
        <div class="space-y-6">
          <!-- Producer Card -->
          <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Producteur</h3>
            <div v-if="mission.producer" class="flex items-center gap-3">
              <img
                v-if="mission.producer.thumbnail_url || mission.producer.profile_photo_url"
                :src="(mission.producer.thumbnail_url ?? mission.producer.profile_photo_url) ?? undefined"
                :alt="mission.producer.display_name"
                class="h-10 w-10 rounded-full object-cover"
              />
              <div v-else class="h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center text-sm font-medium text-primary-700">
                {{ (mission.producer.first_name?.[0] ?? '').toUpperCase() }}{{ (mission.producer.last_name?.[0] ?? '').toUpperCase() }}
              </div>
              <div class="min-w-0">
                <button
                  @click="goToProducer(mission.producer.id)"
                  class="text-sm font-medium text-primary-600 hover:text-primary-800 truncate block transition-colors"
                >
                  {{ mission.producer.display_name }}
                </button>
                <p class="text-xs text-gray-500 capitalize">{{ mission.producer.type }}</p>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400 italic">Producteur inconnu</p>
          </div>

          <!-- Stats Card -->
          <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Statistiques</h3>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500">Candidatures</span>
                <span class="text-sm font-semibold text-gray-900">{{ mission.candidatures_count }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500">Accepte les candidatures</span>
                <component
                  :is="mission.is_accepting_candidatures ? CheckCircle : XCircle"
                  class="h-5 w-5"
                  :class="mission.is_accepting_candidatures ? 'text-green-500' : 'text-red-400'"
                />
              </div>
            </div>
          </div>

          <!-- Metadata Card -->
          <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Métadonnées</h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-500">Créée le</span>
                <span class="text-gray-900">{{ formatDate(mission.created_at) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Mise à jour</span>
                <span class="text-gray-900">{{ formatDate(mission.updated_at) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
