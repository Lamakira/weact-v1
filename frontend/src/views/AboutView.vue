<script setup lang="ts">
import { RouterLink } from 'vue-router'
import {
  Users,
  ShieldCheck,
  Sparkles,
  Zap,
  Heart,
  Star,
  Globe,
} from 'lucide-vue-next'

const missions = [
  {
    title: 'Accessibilité',
    description:
      "Nous rendons le casting accessible à tous les talents du Bénin, peu importe leur parcours, en digitalisant les opportunités.",
    icon: Users,
  },
  {
    title: 'Transparence',
    description:
      "Un processus de sélection clair et équitable pour garantir une relation de confiance entre Faces et Producteurs.",
    icon: ShieldCheck,
  },
  {
    title: 'Qualité',
    description:
      "Nous mettons en relation des talents premium avec des projets d'envergure pour élever les standards de la production locale.",
    icon: Sparkles,
  },
]

const values = [
  {
    title: 'Innovation',
    description:
      "Utiliser la technologie pour transformer radicalement l'industrie créative ouest-africaine.",
    icon: Zap,
  },
  {
    title: 'Inclusivité',
    description:
      'Célébrer la diversité des profils et donner une voix à chaque talent béninois.',
    icon: Heart,
  },
  {
    title: 'Excellence',
    description:
      'Viser la perfection dans chaque connexion établie sur notre marketplace.',
    icon: Star,
  },
  {
    title: 'Impact local',
    description:
      'Contribuer activement au rayonnement culturel et économique du Bénin.',
    icon: Globe,
  },
]
</script>

<template>
  <div>
    <!-- Hero Section -->
    <section class="py-6 md:py-10 border-b border-gray-100 bg-white rounded-lg">
      <div class="max-w-4xl mx-auto px-6 text-center">
        <span class="text-[#198496] font-medium text-xs uppercase tracking-widest mb-4 block"
          >À propos de WEACT</span
        >
        <h1 class="text-2xl md:text-4xl font-bold text-gray-900 leading-tight mb-6">
          Connecter les talents béninois avec les opportunités qui les méritent
        </h1>
        <p class="text-gray-700 text-base leading-relaxed max-w-2xl mx-auto">
          WEACT est la première marketplace de casting au Bénin. Notre mission est de démocratiser
          l'accès aux opportunités artistiques et professionnelles en Afrique de l'Ouest, en créant
          un pont numérique entre les Faces (talents) et les Producteurs (marques et agences).
        </p>
      </div>
    </section>

    <!-- Notre Mission Section -->
    <section class="py-6 md:py-8">
      <div>
        <div class="text-center mb-6">
          <h2 class="text-2xl font-bold text-gray-900 mb-3">Notre Mission</h2>
          <div class="w-12 h-1 bg-[#198496] mx-auto rounded-full"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div
            v-for="item in missions"
            :key="item.title"
            class="bg-white p-8 rounded-md border border-gray-200 hover:shadow-sm transition-all duration-200 group"
          >
            <div
              class="w-10 h-10 bg-[#198496]/5 rounded-md flex items-center justify-center mb-5 group-hover:bg-[#198496] transition-colors duration-200"
            >
              <component
                :is="item.icon"
                class="w-5 h-5 text-[#198496] group-hover:text-white transition-colors duration-200"
              />
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-3">{{ item.title }}</h3>
            <p class="text-gray-500 text-sm leading-relaxed">
              {{ item.description }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Nos Valeurs Section -->
    <section class="py-6 md:py-8">
      <div>
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-4">
          <div class="max-w-xl">
            <h2 class="text-2xl font-bold text-gray-900 mb-3">Nos Valeurs</h2>
            <p class="text-gray-500 text-sm">
              Ce qui guide chaque décision au sein de la première plateforme de casting du Bénin.
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
          <div v-for="value in values" :key="value.title" class="flex gap-4">
            <div class="shrink-0">
              <component :is="value.icon" class="w-5 h-5 text-[#198496]" />
            </div>
            <div>
              <h4 class="text-sm font-bold text-gray-900 mb-1">{{ value.title }}</h4>
              <p class="text-gray-500 text-xs leading-relaxed">{{ value.description }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="py-6 md:py-8 border-t border-gray-100">
      <div
        class="max-w-3xl mx-auto text-center bg-white rounded-xl py-12 px-6 border border-gray-200"
      >
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Rejoignez l'aventure</h2>
        <p class="text-gray-700 text-sm mb-8 max-w-md mx-auto">
          Que vous soyez un talent cherchant à briller ou une entreprise en quête d'authenticité,
          WEACT est votre partenaire de confiance.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
          <RouterLink
            to="/register/face"
            class="w-full sm:w-auto text-sm font-medium bg-[#198496] text-white px-8 py-2.5 rounded-md hover:bg-[#146c7a] transition-colors text-center"
          >
            Devenir une Face
          </RouterLink>
          <RouterLink
            to="/register/producer"
            class="w-full sm:w-auto text-sm font-medium text-[#198496] border border-[#198496] px-8 py-2.5 rounded-md hover:bg-[#198496]/5 transition-colors text-center"
          >
            Poster une mission
          </RouterLink>
        </div>
      </div>
    </section>

    <!-- Footer Decoration -->
    <div class="pb-12 text-center">
      <p class="text-[10px] text-gray-400 uppercase tracking-widest">Fait avec fierté au Bénin</p>
    </div>
  </div>
</template>
