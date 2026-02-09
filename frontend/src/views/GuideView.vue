<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  UserPlus,
  Camera,
  Search,
  CheckCircle,
  Briefcase,
  Users,
  ChevronRight,
} from 'lucide-vue-next'

type Track = 'face' | 'producer'

const activeTrack = ref<Track>('face')

const faceSteps = [
  {
    number: 1,
    icon: UserPlus,
    title: 'Créez votre compte',
    description:
      "Inscrivez-vous rapidement avec votre adresse email pour rejoindre la communauté WEACT.",
  },
  {
    number: 2,
    icon: Camera,
    title: 'Complétez votre profil',
    description:
      "Mettez en avant votre talent en ajoutant vos meilleures photos, votre bio et vos mensurations.",
  },
  {
    number: 3,
    icon: Search,
    title: 'Parcourez les missions',
    description:
      "Explorez les castings disponibles au Bénin et postulez aux projets qui vous correspondent.",
  },
  {
    number: 4,
    icon: CheckCircle,
    title: 'Décrochez votre première mission',
    description:
      "Soyez sélectionné par les marques, réalisez votre prestation et recevez votre paiement en toute sécurité.",
  },
]

const producerSteps = [
  {
    number: 1,
    icon: UserPlus,
    title: 'Inscrivez-vous en tant que Producteur',
    description:
      "Accédez à un espace dédié pour gérer vos besoins en casting et trouver les meilleurs profils.",
  },
  {
    number: 2,
    icon: Briefcase,
    title: 'Publiez votre première mission',
    description:
      "Décrivez précisément vos besoins, le style recherché et les détails logistiques de votre projet.",
  },
  {
    number: 3,
    icon: Users,
    title: 'Parcourez les profils',
    description:
      "Utilisez nos filtres avancés pour découvrir les visages qui donneront vie à votre vision.",
  },
  {
    number: 4,
    icon: CheckCircle,
    title: 'Sélectionnez et collaborez',
    description:
      "Choisissez vos talents favoris, communiquez directement via la plateforme et collaborez sereinement.",
  },
]
</script>

<template>
  <div class="py-4">
    <div>
      <!-- Header Section -->
      <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-3 tracking-tight">Guide de démarrage</h1>
        <p class="text-sm text-gray-500 max-w-md mx-auto">
          Découvrez comment utiliser WEACT pour propulser votre carrière ou trouver les talents
          idéaux pour vos projets au Bénin.
        </p>
      </div>

      <!-- Pill Toggle Selection -->
      <div class="flex justify-center mb-8">
        <div
          class="inline-flex p-1 bg-gray-200/50 rounded-lg backdrop-blur-sm border border-gray-200"
        >
          <button
            @click="activeTrack = 'face'"
            :class="[
              'px-6 py-2 text-xs font-medium rounded-md transition-all duration-200',
              activeTrack === 'face'
                ? 'bg-white text-[#198496] shadow-sm'
                : 'text-gray-500 hover:text-gray-700',
            ]"
          >
            Je suis une Face
          </button>
          <button
            @click="activeTrack = 'producer'"
            :class="[
              'px-6 py-2 text-xs font-medium rounded-md transition-all duration-200',
              activeTrack === 'producer'
                ? 'bg-white text-[#198496] shadow-sm'
                : 'text-gray-500 hover:text-gray-700',
            ]"
          >
            Je suis un Producteur
          </button>
        </div>
      </div>

      <!-- Timeline Layout -->
      <div class="max-w-2xl mx-auto relative">
        <!-- Vertical Line -->
        <div class="absolute left-6 top-2 bottom-2 w-px bg-gray-200 hidden sm:block"></div>

        <!-- Steps Content -->
        <div class="space-y-12 relative">
          <div
            v-for="step in activeTrack === 'face' ? faceSteps : producerSteps"
            :key="step.number"
            class="flex flex-col sm:flex-row items-start gap-4 sm:gap-8 group"
          >
            <!-- Badge & Icon -->
            <div class="relative z-10 flex-shrink-0">
              <div
                class="w-12 h-12 rounded-full bg-white border border-gray-200 flex items-center justify-center text-[#198496] shadow-sm group-hover:border-[#198496]/30 transition-colors duration-300"
              >
                <component :is="step.icon" :size="20" stroke-width="2" />
              </div>
              <div
                class="absolute -top-1 -right-1 w-5 h-5 bg-[#198496] text-white text-[10px] font-bold rounded-full flex items-center justify-center ring-2 ring-white"
              >
                {{ step.number }}
              </div>
            </div>

            <!-- Content -->
            <div class="pt-1">
              <h3 class="text-base font-semibold text-gray-900 mb-2">
                {{ step.title }}
              </h3>
              <p class="text-sm text-gray-600 leading-relaxed">
                {{ step.description }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- CTA Section -->
      <div class="mt-10 text-center border-t border-gray-200 pt-10">
        <div class="bg-white p-8 rounded-xl border border-gray-200 shadow-sm max-w-xl mx-auto">
          <h2 class="text-lg font-bold text-gray-900 mb-2">Prêt à commencer l'aventure ?</h2>
          <p class="text-sm text-gray-500 mb-6">
            Rejoignez la première marketplace de casting au Bénin dès aujourd'hui.
          </p>
          <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <RouterLink
              to="/register/face"
              class="text-sm font-medium bg-[#198496] text-white px-8 py-2.5 rounded-md hover:bg-[#146c7a] transition-colors inline-flex items-center justify-center gap-2"
            >
              Créer mon compte
              <ChevronRight :size="16" />
            </RouterLink>
            <RouterLink
              to="/about"
              class="text-sm font-medium text-[#198496] border border-[#198496] px-8 py-2.5 rounded-md hover:bg-[#198496]/5 transition-colors inline-flex items-center justify-center"
            >
              En savoir plus
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
