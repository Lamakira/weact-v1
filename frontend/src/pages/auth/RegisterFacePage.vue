<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import FaceRegistrationForm from '@/features/auth/components/FaceRegistrationForm.vue'
import { useToast } from '@/composables/useToast'
import { authApi } from '@/features/auth/services/authApi'
import logoNoir from '@/assets/images/logonoir.png'
import registerFaceIllustration from '@/assets/images/register-face-illustration.webp'

const router = useRouter()
const route = useRoute()
const toast = useToast()
const registrationEnabled = ref<boolean | null>(null)

onMounted(async () => {
  try {
    const response = await authApi.getRegistrationStatus()
    registrationEnabled.value = response.data.enabled
  } catch {
    // FIX-23.1 — Fallback build-time permissif : si l'API /auth/registration-status
    // échoue (réseau, 5xx, CDN), on lit VITE_REGISTRATION_ENABLED. Valeur par défaut
    // permissive ('true') pour ne jamais masquer le formulaire par erreur réseau
    // quand le flag backend est à true. Seule une réponse API explicite enabled=false
    // masque le formulaire. Normalisation (trim + lowercase) pour tolérer les
    // variantes opérateur (`False`, `FALSE`, ` false `).
    registrationEnabled.value =
      String(import.meta.env.VITE_REGISTRATION_ENABLED ?? '').trim().toLowerCase() !== 'false'
  }
})

function handleSuccess() {
  // Show email verification reminder
  toast.info(
    'Un email de vérification a été envoyé. Veuillez vérifier votre boîte de réception.',
    { timeout: 8000 }
  )
  // Redirect post-registration: honor ?redirect= bounce-back from /login (FP-2.15),
  // else default to Face dashboard.
  // Defensive guard (FP-2.15 review P3): startsWith('/') && !startsWith('//')
  // rejects protocol-relative (//evil.com) and absolute URLs that would otherwise
  // crash pushState with a SecurityError.
  const redirectQuery = typeof route.query.redirect === 'string' ? route.query.redirect : null
  const redirectPath =
    redirectQuery && redirectQuery.startsWith('/') && !redirectQuery.startsWith('//')
      ? redirectQuery
      : null
  router.push(redirectPath ?? '/face/dashboard')
}
</script>

<template>
  <div class="min-h-screen flex">
    <!-- LEFT: Form Panel -->
    <div class="w-full lg:w-1/2 flex flex-col justify-center overflow-y-auto bg-white">
      <div class="max-w-md mx-auto w-full px-6 sm:px-8 py-12">
        <!-- Back link -->
        <div class="mb-4">
          <router-link to="/" class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-primary-500 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Retour au site
          </router-link>
        </div>

        <!-- Centered Logo -->
        <div class="mb-3.5 flex justify-center">
          <router-link to="/"><img :src="logoNoir" alt="WEACT" class="h-12 lg:h-8" /></router-link>
        </div>

        <!-- Heading -->
        <h1 class="hidden lg:block text-3xl font-bold text-gray-900 mb-2">Devenir Face</h1>
        <p class="text-gray-600 mb-8 text-center lg:text-left">
          <span class="lg:hidden">Devenez une Face et rejoignez la communauté WEACT</span>
          <span class="hidden lg:inline">Créez votre compte et rejoignez la communauté WEACT</span>
        </p>

        <!-- Loading state -->
        <div v-if="registrationEnabled === null" class="flex justify-center py-12">
          <svg class="animate-spin h-6 w-6 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <!-- Registration suspended message -->
        <div
          v-else-if="!registrationEnabled"
          class="rounded-lg bg-amber-50 border border-amber-200 px-5 py-6 text-center"
        >
          <p class="text-sm font-medium text-amber-800">Les inscriptions sont temporairement suspendues.</p>
          <p class="text-xs text-amber-600 mt-1">Veuillez réessayer ultérieurement.</p>
        </div>

        <!-- Registration Form -->
        <FaceRegistrationForm v-else @success="handleSuccess" />

        <!-- Terms Notice -->
        <p class="mt-6 text-xs text-center text-gray-500">
          En vous inscrivant, vous acceptez nos
          <router-link to="/cgu" class="text-primary-500 hover:underline">conditions générales</router-link>
          et notre
          <router-link to="/politique-confidentialite" class="text-primary-500 hover:underline">politique de confidentialité</router-link>
        </p>

        <!-- Links -->
        <div class="text-center mt-6 space-y-2">
          <p class="text-sm text-gray-600">
            Déjà un compte ?
            <router-link to="/login" class="font-medium text-primary-500 hover:text-primary-700 transition-colors">
              Se connecter
            </router-link>
          </p>
          <p class="text-sm text-gray-600">
            Vous êtes Producteur ?
            <router-link
              to="/register/producer"
              class="font-medium text-primary-500 hover:text-primary-700 transition-colors"
            >
              S'inscrire en tant que Producteur
            </router-link>
          </p>
        </div>
      </div>
    </div>

    <!-- RIGHT: Branding Panel -->
    <div class="hidden lg:flex lg:w-1/2 relative flex-col justify-center overflow-hidden bg-gray-900">
      <!-- Background Image with Overlay -->
      <div class="absolute inset-0 z-0">
        <img
          :src="registerFaceIllustration"
          alt="Talent Creative"
          class="h-full w-full object-cover opacity-60"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/40 to-transparent"></div>
      </div>

      <!-- Content Layer -->
      <div class="relative z-10 px-16 xl:px-24">
        <!-- Headline & Subtitle -->
        <div class="mb-12">
          <h2 class="text-4xl xl:text-5xl font-bold text-white tracking-tight leading-tight">
            Lancez votre <span class="text-primary-500">carrière</span>
          </h2>
          <p class="mt-4 text-gray-300 text-lg leading-relaxed max-w-md">
            Rejoignez la plus grande communauté de casting au Bénin et donnez vie à vos ambitions artistiques.
          </p>
        </div>

        <!-- Benefits List -->
        <div class="space-y-6">
          <div class="flex items-start">
            <div class="shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-primary-500/10 text-primary-500">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
              </svg>
            </div>
            <div class="ml-4">
              <h3 class="text-sm font-semibold text-white">Portfolio professionnel</h3>
              <p class="text-sm text-gray-400 mt-0.5">Mettez en valeur votre talent avec un profil optimisé pour les directeurs de casting.</p>
            </div>
          </div>

          <div class="flex items-start">
            <div class="shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-primary-500/10 text-primary-500">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.563.563 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.563.563 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
              </svg>
            </div>
            <div class="ml-4">
              <h3 class="text-sm font-semibold text-white">Opportunités de casting</h3>
              <p class="text-sm text-gray-400 mt-0.5">Accédez à des projets exclusifs : cinéma, publicité et événements de mode.</p>
            </div>
          </div>

          <div class="flex items-start">
            <div class="shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-primary-500/10 text-primary-500">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
              </svg>
            </div>
            <div class="ml-4">
              <h3 class="text-sm font-semibold text-white">Visibilité maximale</h3>
              <p class="text-sm text-gray-400 mt-0.5">Soyez découvert par les agences et marques les plus influentes du pays.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Decorative Bottom Gradient -->
      <div class="absolute bottom-0 left-0 w-full h-32 bg-gradient-to-t from-primary-500/10 to-transparent pointer-events-none"></div>
    </div>
  </div>
</template>
