<script setup lang="ts">
import { useRouter } from 'vue-router'
import ProducerRegistrationForm from '@/features/auth/components/ProducerRegistrationForm.vue'

const router = useRouter()

function handleSuccess() {
  // Redirect to Producer dashboard after successful registration
  router.push('/producer/dashboard')
}
</script>

<template>
  <div class="min-h-screen flex">
    <!-- LEFT: Form Panel (scrollable) -->
    <div class="w-full lg:w-1/2 overflow-y-auto bg-white">
      <div class="max-w-md mx-auto px-8 py-12">
        <!-- Logo -->
        <div class="mb-8">
          <span class="text-2xl font-bold">
            <span class="text-gray-900">W</span><span class="text-primary">E</span
            ><span class="text-gray-900">ACT</span>
          </span>
        </div>

        <!-- Title -->
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Inscription Producteur</h1>
        <p class="text-gray-600 mb-8">
          Créez votre compte et trouvez les talents parfaits pour vos projets
        </p>

        <!-- Registration Form -->
        <ProducerRegistrationForm @success="handleSuccess" />

        <!-- Terms Notice -->
        <p class="mt-6 text-xs text-center text-gray-500">
          En vous inscrivant, vous acceptez nos
          <router-link to="/terms" class="text-primary hover:underline"
            >conditions générales</router-link
          >
          et notre
          <router-link to="/privacy" class="text-primary hover:underline"
            >politique de confidentialité</router-link
          >
        </p>

        <!-- Bottom Link -->
        <p class="text-center mt-6 text-gray-600">
          Vous êtes un talent ?
          <router-link to="/register/face" class="font-medium text-primary hover:text-primary-dark">
            Inscrivez-vous comme face
          </router-link>
        </p>
      </div>
    </div>

    <!-- RIGHT: Branding Panel (hidden on mobile, fixed on desktop) -->
    <div
      class="hidden lg:flex lg:w-1/2 bg-slate-800 relative overflow-hidden"
      style="
        background-image: url('https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1200&q=80');
        background-size: cover;
        background-position: center;
      "
    >
      <!-- Dark overlay -->
      <div class="absolute inset-0 bg-slate-900/70"></div>

      <!-- Content -->
      <div class="relative z-10 flex flex-col justify-center px-12 text-white">
        <h2 class="text-4xl font-bold mb-4 leading-tight">
          Trouvez les talents parfaits pour vos
          <span class="text-primary">productions</span>
        </h2>
        <p class="text-lg text-gray-300 mb-10">
          Accédez à une communauté de faces talentueuses au Bénin
        </p>

        <!-- Stats Cards -->
        <div class="flex gap-4 mb-10">
          <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 flex-1">
            <div class="text-3xl font-bold text-white">500+</div>
            <div class="text-gray-400 text-sm">Faces disponibles</div>
          </div>
          <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 flex-1">
            <div class="text-3xl font-bold text-white">200+</div>
            <div class="text-gray-400 text-sm">Missions complétées</div>
          </div>
        </div>

        <!-- Features -->
        <div class="space-y-4">
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-primary/20 flex items-center justify-center">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-5 h-5 text-primary"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
                />
              </svg>
            </div>
            <div>
              <div class="font-medium text-white">Base de talents diversifiée</div>
              <div class="text-sm text-gray-400">
                Accédez à des profils vérifiés avec portfolios complets
              </div>
            </div>
          </div>

          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-primary/20 flex items-center justify-center">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-5 h-5 text-primary"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"
                />
              </svg>
            </div>
            <div>
              <div class="font-medium text-white">Gestion simplifiée</div>
              <div class="text-sm text-gray-400">
                Publiez vos castings et recevez des candidatures qualifiées
              </div>
            </div>
          </div>

          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-primary/20 flex items-center justify-center">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-5 h-5 text-primary"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"
                />
              </svg>
            </div>
            <div>
              <div class="font-medium text-white">Paiements sécurisés</div>
              <div class="text-sm text-gray-400">
                Intégration Mobile Money pour des transactions faciles
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.text-primary {
  color: #198496;
}
.bg-primary\/20 {
  background-color: rgb(25 132 150 / 0.2);
}
.hover\:text-primary-dark:hover {
  color: #156b7a;
}
</style>
