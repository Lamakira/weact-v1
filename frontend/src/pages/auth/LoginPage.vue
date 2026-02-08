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
        <!-- Logo -->
        <div class="mb-8 flex justify-center lg:justify-start">
          <router-link to="/"><img :src="logoNoir" alt="WEACT" class="h-12 lg:h-8" /></router-link>
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
    <div class="hidden lg:flex lg:w-1/2 relative flex-col overflow-hidden bg-gray-900">
      <!-- Background Image with Overlay -->
      <div class="absolute inset-0 z-0">
        <img
          src="https://images.unsplash.com/photo-1598448663025-726488737576?auto=format&fit=crop&q=80&w=2000"
          alt="Creative Studio"
          class="h-full w-full object-cover opacity-60"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-950/40 to-transparent"></div>
      </div>

      <!-- Content Layer -->
      <div class="relative z-10 flex h-full flex-col justify-between p-16">
        <!-- Header Section -->
        <div>
          <h1 class="text-4xl font-light tracking-tight text-white mb-4">
            Bienvenue sur <span class="text-primary-500 font-semibold">WEACT</span>
          </h1>
          <p class="max-w-md text-sm leading-relaxed text-gray-300">
            La première marketplace de casting au Bénin. Connectez votre talent aux opportunités
            les plus prestigieuses du marché créatif.
          </p>
        </div>

        <!-- Features & Stats Section -->
        <div class="space-y-12">
          <!-- Trust Features List -->
          <div class="space-y-6">
            <div class="flex items-start gap-4">
              <div class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-500/10 border border-primary-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="text-primary-500"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </div>
              <div>
                <h3 class="text-sm font-medium text-white">Communauté vérifiée</h3>
                <p class="text-xs text-gray-400 mt-1">Profils talentueux et agences de confiance authentifiés.</p>
              </div>
            </div>

            <div class="flex items-start gap-4">
              <div class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-500/10 border border-primary-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="text-primary-500"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="m9 8 6 4-6 4Z"/></svg>
              </div>
              <div>
                <h3 class="text-sm font-medium text-white">Casting simplifié</h3>
                <p class="text-xs text-gray-400 mt-1">Postulez et gérez vos auditions en quelques clics.</p>
              </div>
            </div>

            <div class="flex items-start gap-4">
              <div class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-500/10 border border-primary-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="text-primary-500"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
              </div>
              <div>
                <h3 class="text-sm font-medium text-white">Paiements sécurisés</h3>
                <p class="text-xs text-gray-400 mt-1">Transactions garanties et gestion de contrats intégrée.</p>
              </div>
            </div>
          </div>

          <!-- Glassmorphism Stats Cards -->
          <div class="grid grid-cols-2 gap-4 max-w-sm">
            <div class="backdrop-blur-sm bg-white/10 border border-white/10 p-4 rounded-md">
              <div class="text-lg font-semibold text-white">500+</div>
              <div class="text-[10px] uppercase tracking-wider text-primary-500 font-bold">Faces disponibles</div>
            </div>
            <div class="backdrop-blur-sm bg-white/10 border border-white/10 p-4 rounded-md">
              <div class="text-lg font-semibold text-white">200+</div>
              <div class="text-[10px] uppercase tracking-wider text-primary-500 font-bold">Missions complétées</div>
            </div>
          </div>
        </div>

        <!-- Footer Accent -->
        <div class="flex items-center gap-2">
          <div class="h-px w-8 bg-primary-500"></div>
          <span class="text-[10px] uppercase tracking-[0.2em] text-gray-500 font-medium">L'excellence créative au Bénin</span>
        </div>
      </div>
    </div>
  </div>
</template>
