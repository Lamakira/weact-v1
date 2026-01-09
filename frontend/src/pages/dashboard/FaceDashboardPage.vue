<script setup lang="ts">
import { useAuth } from '@/features/auth/composables/useAuth'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const { logout } = useAuth()

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
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors"
            data-testid="logout-button"
          >
            <svg
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
            Déconnexion
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

