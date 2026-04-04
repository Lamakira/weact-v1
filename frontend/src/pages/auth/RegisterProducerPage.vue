<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import ProducerRegistrationForm from '@/features/auth/components/ProducerRegistrationForm.vue'
import { useToast } from '@/composables/useToast'
import { authApi } from '@/features/auth/services/authApi'
import logoNoir from '@/assets/images/logonoir.png'
import registerProducerIllustration from '@/assets/images/register-producer-illustration.webp'

const router = useRouter()
const toast = useToast()
const registrationEnabled = ref<boolean | null>(null)

onMounted(async () => {
  try {
    const response = await authApi.getRegistrationStatus()
    registrationEnabled.value = response.data.enabled
  } catch {
    registrationEnabled.value = false
  }
})

function handleSuccess() {
  // Show email verification reminder
  toast.info(
    'Un email de vérification a été envoyé. Veuillez vérifier votre boîte de réception.',
    { timeout: 8000 }
  )
  // Redirect to Producer dashboard after successful registration
  router.push('/producer/dashboard')
}
</script>

<template>
  <div class="min-h-screen flex">
    <!-- LEFT: Form Panel (scrollable) -->
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

        <!-- Title -->
        <h1 class="hidden lg:block text-3xl font-bold text-gray-900 mb-2">Inscription Producteur</h1>
        <p class="text-gray-600 mb-4 text-center lg:text-left">
          <span class="lg:hidden">Devenez Producteur et trouvez les talents parfaits pour vos projets</span>
          <span class="hidden lg:inline">Créez votre compte et trouvez les talents parfaits pour vos projets</span>
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
        <ProducerRegistrationForm v-else @success="handleSuccess" />

        <!-- Terms Notice -->
        <p class="mt-6 text-xs text-center text-gray-500">
          En vous inscrivant, vous acceptez nos
          <router-link to="/cgu" class="text-primary-500 hover:underline">conditions générales</router-link>
          et notre
          <router-link to="/politique-confidentialite" class="text-primary-500 hover:underline">politique de confidentialité</router-link>
        </p>

        <!-- Bottom Link -->
        <p class="text-center mt-6 text-gray-600">
          Vous êtes un talent ?
          <router-link to="/register/face" class="font-medium text-primary-500 hover:text-primary-700 transition-colors">
            Inscrivez-vous comme face
          </router-link>
        </p>
      </div>
    </div>

    <!-- RIGHT: Branding Panel -->
    <div class="hidden lg:flex lg:w-1/2 relative flex-col justify-center overflow-hidden bg-gray-900">
      <!-- Background Image with Dark Overlay -->
      <div class="absolute inset-0 z-0">
        <img
          :src="registerProducerIllustration"
          alt="Creative team collaborating"
          class="h-full w-full object-cover"
        />
        <div class="absolute inset-0 bg-gray-900/70"></div>
      </div>

      <!-- Content Container -->
      <div class="relative z-10 px-16 xl:px-24">
        <!-- Headline & Subtitle -->
        <div class="max-w-xl mb-12">
          <h2 class="text-3xl xl:text-4xl font-semibold text-white leading-tight mb-6">
            Trouvez les talents parfaits pour vos
            <span class="text-primary-500">productions</span>
          </h2>
          <p class="text-sm text-gray-300 leading-relaxed max-w-md">
            Accédez à la communauté de talents la plus diversifiée du Bénin.
            Simplifiez votre workflow de casting, de la découverte à la contractualisation.
          </p>
        </div>

        <!-- Benefit Items -->
        <div class="space-y-6 max-w-sm">
          <div class="flex items-start gap-4">
            <div class="shrink-0 p-2 bg-primary-500/10 rounded-lg">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-primary-500">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
              </svg>
            </div>
            <div>
              <h3 class="text-sm font-semibold text-white mb-1">Base de talents diversifiée</h3>
              <p class="text-xs text-gray-400 leading-normal">
                Mannequins, acteurs et figurants aux profils variés pour tous vos besoins créatifs.
              </p>
            </div>
          </div>

          <div class="flex items-start gap-4">
            <div class="shrink-0 p-2 bg-primary-500/10 rounded-lg">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-primary-500">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
              </svg>
            </div>
            <div>
              <h3 class="text-sm font-semibold text-white mb-1">Gestion simplifiée</h3>
              <p class="text-xs text-gray-400 leading-normal">
                Gérez vos castings, sélections et plannings sur une interface unique et intuitive.
              </p>
            </div>
          </div>

          <div class="flex items-start gap-4">
            <div class="shrink-0 p-2 bg-primary-500/10 rounded-lg">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-primary-500">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
              </svg>
            </div>
            <div>
              <h3 class="text-sm font-semibold text-white mb-1">Paiements sécurisés</h3>
              <p class="text-xs text-gray-400 leading-normal">
                Transactions garanties et facturation automatisée pour une tranquillité d'esprit totale.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
