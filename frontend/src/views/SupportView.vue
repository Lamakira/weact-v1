<script setup lang="ts">
import { RouterLink } from 'vue-router'
import {
  UserCog,
  Briefcase,
  CreditCard,
  Settings,
  ChevronRight,
  Mail,
  ExternalLink,
  BookOpen,
  MessageCircle,
} from 'lucide-vue-next'

interface Category {
  title: string
  icon: unknown
  description: string
}

const categories: Category[] = [
  {
    title: 'Mon compte',
    icon: UserCog,
    description: 'Gérez vos informations personnelles et préférences',
  },
  {
    title: 'Missions',
    icon: Briefcase,
    description: 'Questions sur les castings et les contrats',
  },
  {
    title: 'Paiements',
    icon: CreditCard,
    description: 'Facturation, virements et délais de paiement',
  },
  {
    title: 'Technique',
    icon: Settings,
    description: "Assistance sur l'utilisation de la plateforme",
  },
]

const quickLinks = [
  { name: 'FAQ', to: '/faq', icon: MessageCircle },
  { name: 'Guide de démarrage', to: '/guide', icon: BookOpen },
  { name: 'Contact', to: '/contact', icon: Mail },
]
</script>

<template>
  <div class="py-4">
    <!-- Header -->
    <header class="text-center mb-8">
      <h1 class="text-3xl font-bold text-gray-900 mb-3 tracking-tight">Centre d'aide</h1>
      <p class="text-gray-500 text-sm max-w-lg mx-auto leading-relaxed">
        Trouvez les réponses à vos questions et découvrez comment tirer le meilleur parti de WEACT.
      </p>
    </header>

    <!-- Categories Grid -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
      <div
        v-for="category in categories"
        :key="category.title"
        class="group bg-white border border-gray-200 rounded-md p-6 hover:border-[#198496] hover:shadow-sm transition-all duration-200"
      >
        <div
          class="w-10 h-10 rounded-md bg-gray-50 flex items-center justify-center mb-4 group-hover:bg-[#198496]/10 transition-colors"
        >
          <component :is="category.icon" class="w-5 h-5 text-[#198496]" />
        </div>
        <h3 class="text-sm font-semibold text-gray-900 mb-2">
          {{ category.title }}
        </h3>
        <p class="text-xs text-gray-500 leading-relaxed">
          {{ category.description }}
        </p>
      </div>
    </section>

    <!-- Quick Links & Resources -->
    <section class="mb-10">
      <h2 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-6 text-center">
        Liens rapides
      </h2>
      <div class="bg-white border border-gray-200 rounded-md divide-y divide-gray-100">
        <RouterLink
          v-for="link in quickLinks"
          :key="link.name"
          :to="link.to"
          class="flex items-center justify-between p-4 hover:bg-gray-50 transition-colors group"
        >
          <div class="flex items-center gap-3">
            <component
              :is="link.icon"
              class="w-4 h-4 text-gray-400 group-hover:text-[#198496] transition-colors"
            />
            <span class="text-sm font-medium text-gray-700">{{ link.name }}</span>
          </div>
          <ChevronRight
            class="w-4 h-4 text-gray-300 group-hover:text-[#198496] transform group-hover:translate-x-1 transition-all"
          />
        </RouterLink>
      </div>
    </section>

    <!-- Support CTA -->
    <section>
      <div class="bg-gray-900 rounded-lg p-8 text-center relative overflow-hidden">
        <div class="absolute inset-0 opacity-10 pointer-events-none">
          <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="0.5" />
              </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#grid)" />
          </svg>
        </div>

        <div class="relative z-10">
          <h2 class="text-xl font-bold text-white mb-2">
            Vous ne trouvez pas ce que vous cherchez ?
          </h2>
          <p class="text-gray-400 text-xs mb-8">
            Notre équipe est là pour vous accompagner dans toutes vos démarches.
          </p>

          <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a
              href="mailto:contact@weact.bj"
              class="flex items-center gap-2 text-sm text-gray-300 hover:text-white transition-colors"
            >
              <Mail class="w-4 h-4" />
              <span>contact@weact.bj</span>
            </a>

            <div class="hidden sm:block w-px h-4 bg-gray-700"></div>

            <RouterLink
              to="/contact"
              class="text-sm font-medium bg-[#198496] text-white px-5 py-2 rounded-md hover:bg-[#146c7a] transition-colors inline-flex items-center gap-2"
            >
              Contactez-nous
              <ExternalLink class="w-3 h-3" />
            </RouterLink>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
