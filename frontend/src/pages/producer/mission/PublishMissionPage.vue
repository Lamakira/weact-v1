<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { MissionForm } from '@/features/mission/components'
import EmailVerificationRequired from '@/components/EmailVerificationRequired.vue'
import type { Mission } from '@/features/mission/types'

const router = useRouter()
const authStore = useAuthStore()
const { success } = useToast()

function handleSuccess(mission: Mission): void {
  success('Mission publiée avec succès!')
  router.push({ name: 'producer-dashboard' })
}

function handleCancel(): void {
  router.push({ name: 'producer-dashboard' })
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Email verification required -->
    <EmailVerificationRequired
      v-if="!authStore.isEmailVerified"
      title="Publication non disponible"
      message="Vous devez vérifier votre adresse email pour publier une mission."
    />

    <template v-else>
    <!-- Header -->
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 bg-weact rounded-full flex items-center justify-center">
            <span class="text-white font-bold">W</span>
          </div>
          <h1 class="text-xl font-semibold text-gray-900">Publier une mission</h1>
        </div>
      </div>
    </header>

    <!-- Main content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <MissionForm @success="handleSuccess" @cancel="handleCancel" />
    </main>
    </template>
  </div>
</template>

<style scoped>
.bg-weact {
  background-color: #198496;
}
</style>
