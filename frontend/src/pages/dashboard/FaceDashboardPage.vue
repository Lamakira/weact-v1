<script setup lang="ts">
import { useAuth } from '@/features/auth/composables/useAuth'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const { logout, isLoading } = useAuth()

async function handleLogout(): Promise<void> {
  await logout()
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header with logout -->
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
            <span class="text-white font-bold">W</span>
          </div>
          <h1 class="text-xl font-semibold text-gray-900">Dashboard Face</h1>
        </div>

        <div class="flex items-center gap-4">
          <span class="text-sm text-gray-600">
            {{ authStore.user?.email }}
          </span>
          <button
            @click="handleLogout"
            :disabled="isLoading"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            data-testid="logout-button"
          >
            <svg
              v-if="!isLoading"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
              class="w-5 h-5"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"
              />
            </svg>
            <svg
              v-else
              class="w-5 h-5 animate-spin"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ isLoading ? 'Déconnexion...' : 'Déconnexion' }}
          </button>
        </div>
      </div>
    </header>

    <!-- Main content placeholder -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Bienvenue sur votre Dashboard Face</h2>
        <p class="text-gray-600">
          Cette page sera développée dans les prochaines stories.
          Pour l'instant, vous pouvez vous déconnecter en utilisant le bouton ci-dessus.
        </p>
      </div>
    </main>
  </div>
</template>

<style scoped>
.bg-primary {
  background-color: #198496;
}
.focus\:ring-primary:focus {
  --tw-ring-color: #198496;
}
</style>

