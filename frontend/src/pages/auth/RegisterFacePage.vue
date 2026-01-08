<script setup lang="ts">
import { useRouter } from 'vue-router'
import FaceRegistrationForm from '@/features/auth/components/FaceRegistrationForm.vue'

const router = useRouter()

function handleSuccess() {
  // Redirect to Face dashboard after successful registration
  router.push('/face/dashboard')
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <!-- Header -->
      <div class="text-center">
        <h1 class="text-3xl font-bold text-gray-900">Devenir Face</h1>
        <p class="mt-2 text-sm text-gray-600">
          Créez votre compte et rejoignez la communauté WEACT
        </p>
      </div>

      <!-- Registration Form Card -->
      <div class="bg-white py-8 px-6 shadow-md rounded-lg">
        <FaceRegistrationForm @success="handleSuccess" />
      </div>

      <!-- Links -->
      <div class="text-center space-y-2">
        <p class="text-sm text-gray-600">
          Déjà un compte ?
          <router-link to="/login" class="font-medium text-primary hover:text-primary-dark">
            Se connecter
          </router-link>
        </p>
        <p class="text-sm text-gray-600">
          Vous êtes Producteur ?
          <router-link
            to="/register/producer"
            class="font-medium text-primary hover:text-primary-dark"
          >
            S'inscrire en tant que Producteur
          </router-link>
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.text-primary {
  color: #198496;
}
.hover\:text-primary-dark:hover {
  color: #156b7a;
}
</style>
