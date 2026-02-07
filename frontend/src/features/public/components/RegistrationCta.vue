<script setup lang="ts">
import { UserPlus, Briefcase } from 'lucide-vue-next'

defineProps<{
  variant: 'faces' | 'missions'
}>()

const primaryCtaClass = 'inline-flex items-center gap-2 text-sm font-medium bg-[#198496] text-white px-6 py-2.5 rounded-md hover:bg-[#146c7a] transition-all active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-[#198496]/20'
const secondaryCtaClass = 'inline-flex items-center gap-2 text-sm font-medium text-[#198496] border border-[#198496]/30 px-6 py-2.5 rounded-md hover:bg-[#198496]/5 transition-all active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-[#198496]/20'
</script>

<template>
  <section
    class="rounded-2xl border border-gray-100 bg-gradient-to-br from-[#198496]/5 via-white to-[#198496]/5 p-6 md:p-8 text-center shadow-sm"
    data-testid="registration-cta"
  >
    <!-- Faces variant -->
    <template v-if="variant === 'faces'">
      <h2 class="text-lg font-semibold text-gray-900 mb-2">
        Rejoignez notre communauté
      </h2>
      <p class="text-sm text-gray-600 mb-6 max-w-md mx-auto">
        Créez votre profil pour être visible par les producteurs ou publiez vos missions pour trouver les meilleurs talents.
      </p>
      <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <RouterLink
          to="/register/face"
          :class="primaryCtaClass"
          data-testid="register-face-cta"
        >
          <UserPlus :size="16" aria-hidden="true" />
          Créer mon profil
        </RouterLink>
        <RouterLink
          to="/register/producer"
          :class="secondaryCtaClass"
          data-testid="register-producer-cta"
        >
          <Briefcase :size="16" aria-hidden="true" />
          Poster une mission
        </RouterLink>
      </div>
    </template>

    <!-- Missions variant -->
    <template v-else>
      <h2 class="text-lg font-semibold text-gray-900 mb-2">
        Prêt(e) à passer à l'action ?
      </h2>
      <p class="text-sm text-gray-600 mb-6 max-w-md mx-auto">
        Postulez aux missions en créant votre profil ou publiez vos propres missions pour trouver les talents idéaux.
      </p>
      <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <RouterLink
          to="/register/face"
          :class="primaryCtaClass"
          data-testid="register-face-cta"
        >
          <UserPlus :size="16" aria-hidden="true" />
          Postuler aux missions
        </RouterLink>
        <RouterLink
          to="/register/producer"
          :class="secondaryCtaClass"
          data-testid="register-producer-cta"
        >
          <Briefcase :size="16" aria-hidden="true" />
          Publier une mission
        </RouterLink>
      </div>
    </template>
  </section>
</template>
