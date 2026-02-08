<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import LoginForm from '@/features/auth/components/LoginForm.vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import logoNoir from '@/assets/images/logonoir.png'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()

// Success message for password reset redirect
const successMessage = ref<string | null>(null)
// Warning message for session expired redirect
const warningMessage = ref<string | null>(null)

onMounted(() => {
  const message = route.query.message

  // Check for password reset success message from query
  if (message === 'password-reset-success') {
    successMessage.value = 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.'
    // Clean up the URL (preserve redirect if present)
    router.replace({ path: '/login', query: route.query.redirect ? { redirect: route.query.redirect } : {} })
  } else if (message === 'session-expired') {
    // Check for session expired message from query
    warningMessage.value = 'Session expirée, veuillez vous reconnecter.'
    toast.warning('Session expirée, veuillez vous reconnecter')
    // Clean up the URL (preserve redirect if present)
    router.replace({ path: '/login', query: route.query.redirect ? { redirect: route.query.redirect } : {} })
  }
})

function handleLoginSuccess(): void {
  // Check for redirect query param (e.g., from protected route redirect)
  const redirectPath = route.query.redirect as string
  if (redirectPath) {
    router.push(redirectPath)
    return
  }

  // Default: Redirect based on user role
  if (authStore.user?.userable_type === 'Face') {
    router.push('/face/dashboard')
  } else if (authStore.user?.userable_type === 'Producer') {
    router.push('/producer/dashboard')
  } else {
    // Fallback to home
    router.push('/')
  }
}
</script>

<template>
  <div class="min-h-screen flex">
    <!-- LEFT: Form Panel -->
    <div class="w-full lg:w-1/2 flex flex-col justify-center overflow-y-auto bg-white">
      <div class="max-w-md mx-auto w-full px-6 sm:px-8 py-12">
        <!-- Logo & Back link -->
        <div class="mb-8 flex items-center justify-between">
          <router-link to="/"><img :src="logoNoir" alt="WEACT" class="h-12 lg:h-8" /></router-link>
          <router-link to="/" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-500 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Retour au site
          </router-link>
        </div>

        <!-- Heading -->
        <h2 class="hidden lg:block text-3xl font-bold text-gray-900 mb-2">
          Connexion à WEACT
        </h2>
        <p class="text-gray-600 mb-8 text-center lg:text-left">
          <span class="lg:hidden">Connectez-vous à WEACT pour accéder à votre compte</span>
          <span class="hidden lg:inline">Connectez-vous pour accéder à votre compte</span>
        </p>

        <!-- Success Message (e.g., after password reset) -->
        <div
          v-if="successMessage"
          class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg"
          data-testid="success-message"
        >
          <div class="flex items-center">
            <svg class="w-5 h-5 text-green-500 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                clip-rule="evenodd"
              />
            </svg>
            <span class="text-sm font-medium text-green-800">{{ successMessage }}</span>
          </div>
        </div>

        <!-- Warning Message (e.g., after session expiration) -->
        <div
          v-if="warningMessage"
          class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg"
          data-testid="warning-message"
        >
          <div class="flex items-center">
            <svg class="w-5 h-5 text-amber-500 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path
                fill-rule="evenodd"
                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                clip-rule="evenodd"
              />
            </svg>
            <span class="text-sm font-medium text-amber-800">{{ warningMessage }}</span>
          </div>
        </div>

        <!-- Login Form -->
        <LoginForm @success="handleLoginSuccess" />

        <!-- Forgot password link -->
        <div class="mt-6 text-center">
          <router-link
            to="/forgot-password"
            class="text-sm font-medium text-primary-500 hover:text-primary-700 transition-colors"
          >
            Mot de passe oublié ?
          </router-link>
        </div>

        <!-- Divider -->
        <div class="mt-6">
          <div class="relative">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-200" />
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-2 bg-white text-gray-500">Pas encore de compte ?</span>
            </div>
          </div>
        </div>

        <!-- Registration links -->
        <div class="mt-6 grid grid-cols-2 gap-3">
          <router-link
            to="/register/face"
            class="w-full inline-flex justify-center py-3 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
          >
            Devenir Face
          </router-link>
          <router-link
            to="/register/producer"
            class="w-full inline-flex justify-center py-3 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
          >
            Devenir Producteur
          </router-link>
        </div>
      </div>
    </div>

    <!-- RIGHT: Branding Panel -->
    <div class="hidden lg:flex lg:w-1/2 relative flex-col justify-center overflow-hidden bg-gray-900">
      <!-- Background Image with Overlay -->
      <div class="absolute inset-0 z-0">
        <img
          src="https://images.unsplash.com/photo-1516321497487-e288fb19713f?auto=format&fit=crop&q=80&w=2000"
          alt="Creative Studio"
          class="h-full w-full object-cover opacity-60"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/40 to-transparent"></div>
      </div>

      <!-- Content Layer -->
      <div class="relative z-10 px-16 xl:px-24">
        <!-- Headline & Subtitle -->
        <div class="mb-12">
          <h2 class="text-4xl xl:text-5xl font-bold text-white tracking-tight leading-tight">
            Bienvenue sur <span class="text-primary-500">WEACT</span>
          </h2>
          <p class="mt-4 text-gray-300 text-lg leading-relaxed max-w-md">
            La première marketplace de casting au Bénin. Connectez votre talent aux opportunités les plus prestigieuses du marché créatif.
          </p>
        </div>

        <!-- Benefits List -->
        <div class="space-y-6">
          <div class="flex items-start">
            <div class="shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-primary-500/10 text-primary-500">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
            </div>
            <div class="ml-4">
              <h3 class="text-sm font-semibold text-white">Communauté vérifiée</h3>
              <p class="text-sm text-gray-400 mt-0.5">Profils talentueux et agences de confiance authentifiés.</p>
            </div>
          </div>

          <div class="flex items-start">
            <div class="shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-primary-500/10 text-primary-500">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
            </div>
            <div class="ml-4">
              <h3 class="text-sm font-semibold text-white">Casting simplifié</h3>
              <p class="text-sm text-gray-400 mt-0.5">Postulez et gérez vos auditions en quelques clics.</p>
            </div>
          </div>

          <div class="flex items-start">
            <div class="shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-primary-500/10 text-primary-500">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
            </div>
            <div class="ml-4">
              <h3 class="text-sm font-semibold text-white">Paiements sécurisés</h3>
              <p class="text-sm text-gray-400 mt-0.5">Transactions garanties et gestion de contrats intégrée.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Decorative Bottom Gradient -->
      <div class="absolute bottom-0 left-0 w-full h-32 bg-gradient-to-t from-primary-500/10 to-transparent pointer-events-none"></div>
    </div>
  </div>
</template>
