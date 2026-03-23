<script setup lang="ts">
/**
 * PublishMissionPage
 * Page for creating and publishing a new mission.
 * This component is rendered inside ProducerLayout via nested routing.
 */
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { MissionForm } from '@/features/mission/components'
import EmailVerificationRequired from '@/components/EmailVerificationRequired.vue'
import type { Mission } from '@/features/mission/types'

const router = useRouter()
const authStore = useAuthStore()
const { success } = useToast()

function handleSuccess(_mission: Mission): void {
  success('Mission publiée avec succès!')
  router.push({ name: 'producer-missions' })
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

    <!-- Mission form -->
    <MissionForm
      v-else
      @success="handleSuccess"
      @cancel="handleCancel"
    />
  </div>
</template>
