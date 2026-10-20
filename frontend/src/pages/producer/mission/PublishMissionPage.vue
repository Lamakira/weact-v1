<script setup lang="ts">
/**
 * PublishMissionPage
 * Page for creating and publishing a new mission.
 * This component is rendered inside ProducerLayout via nested routing.
 *
 * Lot C9 : `?from={uuid}` republie une mission clôturée sans Face — le formulaire est pré-rempli
 * avec son brief (dates vides) et publie une NOUVELLE mission ; l'ancienne reste inchangée.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Loader2, RefreshCw } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { MissionForm } from '@/features/mission/components'
import { missionApi } from '@/features/mission/services/missionApi'
import { buildRepublishValues } from '@/features/mission/utils/republishPrefill'
import EmailVerificationRequired from '@/components/EmailVerificationRequired.vue'
import { MissionStatus, type Mission } from '@/features/mission/types'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { success, error: toastError } = useToast()

const fromId = computed(() => {
  const raw = route.query.from
  const value = Array.isArray(raw) ? raw[0] : raw
  return typeof value === 'string' && value !== '' ? value : null
})

const isLoadingSource = ref(fromId.value !== null)
const sourceMission = ref<Mission | null>(null)
const initialValues = computed(() => (sourceMission.value ? buildRepublishValues(sourceMission.value) : undefined))

onMounted(async () => {
  if (fromId.value === null) return
  try {
    sourceMission.value = (await missionApi.getMission(fromId.value)).data
  } catch {
    // Mission introuvable ou non possédée : on retombe sur un formulaire vierge.
    toastError('Impossible de charger la mission à republier. Vous pouvez en créer une nouvelle.')
  } finally {
    isLoadingSource.value = false
  }
})

function handleSuccess(mission: Mission): void {
  // Une mission UGC reste `pending_payment` (non publiée) tant que la commission n'est pas réglée (D-1.4.e).
  // On redirige vers la page détail avec ?pay={id} pour ouvrir automatiquement le tunnel de paiement.
  if (mission.status === MissionStatus.PENDING_PAYMENT) {
    success('Mission UGC créée. Réglez la commission pour la publier.')
    router.push({ name: 'producer-mission-detail', params: { id: mission.id }, query: { pay: mission.id } })
  } else {
    success('Mission publiée avec succès!')
    router.push({ name: 'producer-mission-detail', params: { id: mission.id } })
  }
}

function handleCancel(): void {
  router.push({ name: 'producer-missions' })
}
</script>

<template>
  <div>
    <!-- Email verification required -->
    <EmailVerificationRequired
      v-if="!authStore.isEmailVerified"
      title="Publication non disponible"
      message="Vous devez vérifier votre adresse email pour publier une mission."
    />

    <!-- Chargement de la mission à republier -->
    <div
      v-else-if="isLoadingSource"
      class="flex flex-col items-center justify-center py-20"
      data-testid="republish-loading"
    >
      <Loader2 class="mb-4 h-8 w-8 animate-spin text-primary" />
      <p class="text-muted-foreground">Chargement de la mission…</p>
    </div>

    <!-- Mission form -->
    <template v-else>
      <div
        v-if="sourceMission"
        class="mb-4 flex items-start gap-3 rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm"
        data-testid="republish-banner"
      >
        <RefreshCw class="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
        <p class="text-foreground">
          Vous republiez « {{ sourceMission.titre }} ». Les informations sont reprises : renseignez de
          nouvelles dates puis publiez. L'ancienne mission reste clôturée, avec son historique de paiement et de remboursement.
        </p>
      </div>
      <MissionForm
        :initial-values="initialValues"
        @success="handleSuccess"
        @cancel="handleCancel"
      />
    </template>
  </div>
</template>
