<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useAuthStore } from '@/stores/auth'
import EmailVerificationBanner from '@/components/EmailVerificationBanner.vue'

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
          <div class="w-10 h-10 bg-secondary rounded-full flex items-center justify-center">
            <span class="text-white font-bold">W</span>
          </div>
          <h1 class="text-xl font-semibold text-gray-900">Dashboard Producteur</h1>
        </div>

        <div class="flex items-center gap-4">
          <RouterLink
            :to="{ name: 'producer-profile' }"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary transition-colors"
            data-testid="profile-link"
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
                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
              />
            </svg>
            Mon profil
          </RouterLink>
          <span class="text-sm text-gray-600">
            {{ authStore.user?.email }}
          </span>
          <button
            @click="handleLogout"
            :disabled="isLoading"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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
      <!-- Email verification banner (shown if email not verified) -->
      <EmailVerificationBanner
        v-if="!authStore.isEmailVerified"
        data-testid="email-verification-banner"
      />

      <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Bienvenue sur votre Dashboard Producteur</h2>
        <p class="text-gray-600">
          Cette page sera développée dans les prochaines stories.
          Pour l'instant, vous pouvez gérer votre profil ou vous déconnecter.
        </p>
      </div>

      <!-- Quick access cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <RouterLink
          :to="{ name: 'producer-profile' }"
          class="bg-white rounded-lg shadow p-6 hover:shadow-md transition-shadow group"
          data-testid="profile-card"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-secondary/10 rounded-full flex items-center justify-center group-hover:bg-secondary/20 transition-colors">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-6 h-6 text-secondary"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-medium text-gray-900">Mon profil</h3>
              <p class="text-sm text-gray-500">Gérer ma photo et mes informations</p>
            </div>
          </div>
        </RouterLink>

        <RouterLink
          v-if="authStore.user?.userable?.id"
          :to="{ name: 'public-producer-profile', params: { id: authStore.user.userable.id } }"
          class="bg-white rounded-lg shadow p-6 hover:shadow-md transition-shadow group"
          data-testid="public-profile-card"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-secondary/10 rounded-full flex items-center justify-center group-hover:bg-secondary/20 transition-colors">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-6 h-6 text-secondary"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.64 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.64 0-8.573-3.007-9.963-7.178z"
                />
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-medium text-gray-900">Voir mon profil public</h3>
              <p class="text-sm text-gray-500">Comment les Faces voient mon profil</p>
            </div>
          </div>
        </RouterLink>

        <RouterLink
          :to="{ name: 'producer-missions' }"
          class="bg-white rounded-lg shadow p-6 hover:shadow-md transition-shadow group"
          data-testid="missions-list-card"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center group-hover:bg-primary/20 transition-colors">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-6 h-6 text-primary"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"
                />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-medium text-gray-900">Mes missions</h3>
              <p class="text-sm text-gray-500">Gérer mes castings en cours</p>
            </div>
          </div>
        </RouterLink>

        <RouterLink
          :to="{ name: 'publish-mission' }"
          class="bg-white rounded-lg shadow p-6 hover:shadow-md transition-shadow group"
          data-testid="publish-mission-card"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center group-hover:bg-primary/20 transition-colors">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-6 h-6 text-primary"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M12 4.5v15m7.5-7.5h-15"
                />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-medium text-gray-900">Publier une mission</h3>
              <p class="text-sm text-gray-500">Créer un nouveau casting</p>
            </div>
          </div>
        </RouterLink>

        <RouterLink
          :to="{ name: 'producer-messages' }"
          class="bg-white rounded-lg shadow p-6 hover:shadow-md transition-shadow group"
          data-testid="messages-card"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center group-hover:bg-blue-100 transition-colors">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-6 h-6 text-blue-500"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"
                />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-medium text-gray-900">Messages</h3>
              <p class="text-sm text-gray-500">Discussions avec les Faces</p>
            </div>
          </div>
        </RouterLink>
      </div>
    </main>
  </div>
</template>

<style scoped>
.bg-secondary {
  background-color: #E88B51;
}
.bg-secondary\/10 {
  background-color: rgb(232 139 81 / 0.1);
}
.bg-secondary\/20 {
  background-color: rgb(232 139 81 / 0.2);
}
.group:hover .group-hover\:bg-secondary\/20 {
  background-color: rgb(232 139 81 / 0.2);
}
.text-secondary {
  color: #E88B51;
}
.focus\:ring-secondary:focus {
  --tw-ring-color: #E88B51;
}
</style>

