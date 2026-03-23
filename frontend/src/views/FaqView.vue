<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  HelpCircle,
  User,
  Briefcase,
  ChevronDown,
  MessageSquare,
} from 'lucide-vue-next'

interface FAQItem {
  id: string
  question: string
  answer: string
}

interface Category {
  id: string
  title: string
  icon: unknown
  questions: FAQItem[]
}

const activeIndices = ref<Record<string, number | null>>({
  generale: null,
  faces: null,
  producteurs: null,
})

const toggleAccordion = (categoryId: string, index: number) => {
  if (activeIndices.value[categoryId] === index) {
    activeIndices.value[categoryId] = null
  } else {
    activeIndices.value[categoryId] = index
  }
}

const categories: Category[] = [
  {
    id: 'generale',
    title: 'Questions générales',
    icon: HelpCircle,
    questions: [
      {
        id: 'g1',
        question: "Qu'est-ce que WEACT ?",
        answer:
          "WEACT est la première marketplace de casting au Bénin. Nous connectons les talents (Faces) — acteurs, mannequins, influenceurs, figurants — avec les producteurs, marques et agences qui recherchent des profils pour leurs projets audiovisuels, publicitaires ou événementiels.",
      },
      {
        id: 'g2',
        question: 'Comment fonctionne la plateforme ?',
        answer:
          "Les Faces créent un profil avec leurs photos et informations. Les Producteurs publient des missions de casting. Les Faces postulent aux missions qui les intéressent, et les Producteurs sélectionnent les profils qui correspondent à leurs besoins. Tout le processus se fait via la plateforme.",
      },
      {
        id: 'g3',
        question: "L'inscription est-elle gratuite ?",
        answer:
          "Oui, l'inscription sur WEACT est entièrement gratuite pour les Faces. Les Producteurs bénéficient également d'un accès gratuit pour publier leurs premières missions. Des options premium sont disponibles pour une visibilité accrue.",
      },
      {
        id: 'g4',
        question: 'Comment commencer sur WEACT ?',
        answer:
          "Créez un compte en quelques minutes avec votre email, choisissez votre type de profil (Face ou Producteur), puis complétez vos informations. Pour les Faces, ajoutez vos meilleures photos. Pour les Producteurs, décrivez votre activité et publiez votre première mission.",
      },
      {
        id: 'g5',
        question: 'Dans quels pays WEACT est-il disponible ?',
        answer:
          "WEACT opère principalement au Bénin, avec une attention particulière sur Cotonou et les grandes villes. Nous prévoyons une expansion progressive vers d'autres pays d'Afrique de l'Ouest.",
      },
    ],
  },
  {
    id: 'faces',
    title: 'Pour les Faces',
    icon: User,
    questions: [
      {
        id: 'f1',
        question: 'Comment créer un profil attractif ?',
        answer:
          "Utilisez des photos de haute qualité montrant différentes expressions et tenues. Remplissez toutes les sections de votre profil : bio, mensurations, compétences, expériences passées. Un profil complet a 3 fois plus de chances d'être sélectionné.",
      },
      {
        id: 'f2',
        question: 'Quels types de missions existent ?',
        answer:
          "WEACT propose des missions variées : tournages publicitaires, shooting photo, casting pour films et séries, figuration, événements de marque, clips musicaux, et bien plus encore. Chaque mission détaille les critères recherchés et la rémunération.",
      },
      {
        id: 'f3',
        question: 'Comment postuler à une mission ?',
        answer:
          "Parcourez la liste des missions disponibles, consultez les détails de celles qui vous intéressent, et cliquez sur \"Postuler\". Le producteur examinera votre profil et vous contactera directement via la plateforme s'il souhaite vous retenir.",
      },
      {
        id: 'f4',
        question: 'Comment fonctionne le paiement ?',
        answer:
          "La rémunération est définie par le producteur dans la description de la mission. Le paiement est sécurisé et effectué après la réalisation de la prestation, selon les termes convenus entre vous et le producteur.",
      },
      {
        id: 'f5',
        question: 'Comment améliorer ma visibilité ?',
        answer:
          "Maintenez votre profil à jour avec de nouvelles photos régulièrement. Postulez activement aux missions. Les Faces avec un profil complet, des avis positifs et une activité régulière apparaissent plus haut dans les résultats de recherche des producteurs.",
      },
    ],
  },
  {
    id: 'producteurs',
    title: 'Pour les Producteurs',
    icon: Briefcase,
    questions: [
      {
        id: 'p1',
        question: 'Comment publier une mission ?',
        answer:
          "Depuis votre tableau de bord Producteur, cliquez sur \"Publier une mission\". Décrivez votre projet, les profils recherchés (âge, genre, style), la rémunération, les dates et le lieu. Plus votre brief est détaillé, plus vous recevrez de candidatures qualifiées.",
      },
      {
        id: 'p2',
        question: 'Quelle est la tarification pour les producteurs ?',
        answer:
          "WEACT propose plusieurs formules adaptées à vos besoins. Consultez notre page Tarifs pour découvrir les options disponibles, de l'accès gratuit aux forfaits premium avec des fonctionnalités avancées de recherche et de sélection.",
      },
      {
        id: 'p3',
        question: 'Comment sélectionner les meilleurs candidats ?',
        answer:
          "Utilisez les filtres de recherche (ville, catégorie, niche, mensurations) pour affiner vos résultats. Consultez les portfolios complets des Faces, lisez les avis des précédents producteurs, et contactez directement les profils qui vous intéressent.",
      },
      {
        id: 'p4',
        question: 'Quel est le délai moyen pour trouver un talent ?',
        answer:
          "En moyenne, vous recevez vos premières candidatures dans les 24 à 48 heures suivant la publication de votre mission. Le délai total dépend de la spécificité de vos critères et de la durée que vous définissez pour la mission.",
      },
      {
        id: 'p5',
        question: 'Quelle est la garantie de qualité ?',
        answer:
          "Tous les profils sur WEACT sont vérifiés. Les photos sont modérées pour garantir leur authenticité. Notre système d'avis permet de connaître la fiabilité des Faces. En cas de litige, notre équipe support intervient pour trouver une solution.",
      },
    ],
  },
]
</script>

<template>
  <div class="py-4">
    <div>
      <!-- Header Section -->
      <header class="mb-6 text-center">
        <h1 class="text-3xl font-bold text-gray-900 tracking-tight mb-4">
          Comment pouvons-nous vous aider ?
        </h1>
        <p class="text-gray-500 text-sm max-w-md mx-auto">
          Trouvez les réponses aux questions les plus fréquentes sur l'écosystème WEACT.
        </p>
      </header>

      <!-- FAQ Content -->
      <div class="space-y-16">
        <section v-for="category in categories" :key="category.id" class="space-y-6">
          <div class="flex items-center gap-3 pb-2 border-b border-gray-100">
            <component :is="category.icon" class="w-5 h-5 text-[#198496]" />
            <h2 class="text-lg font-semibold text-gray-900">{{ category.title }}</h2>
          </div>

          <div class="space-y-3">
            <div
              v-for="(item, index) in category.questions"
              :key="item.id"
              class="group bg-white border border-gray-200 rounded-lg overflow-hidden transition-all duration-200"
              :class="{
                'border-[#198496] ring-1 ring-[#198496]/5':
                  activeIndices[category.id] === index,
              }"
            >
              <button
                @click="toggleAccordion(category.id, index)"
                class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 transition-colors"
              >
                <span
                  class="text-sm font-medium transition-colors"
                  :class="
                    activeIndices[category.id] === index ? 'text-[#198496]' : 'text-gray-700'
                  "
                >
                  {{ item.question }}
                </span>
                <ChevronDown
                  class="w-4 h-4 text-gray-400 transition-transform duration-300 shrink-0 ml-4"
                  :class="{
                    'rotate-180 text-[#198496]': activeIndices[category.id] === index,
                  }"
                />
              </button>

              <div
                class="grid transition-all duration-300 ease-in-out"
                :class="
                  activeIndices[category.id] === index
                    ? 'grid-rows-[1fr] opacity-100'
                    : 'grid-rows-[0fr] opacity-0'
                "
              >
                <div class="overflow-hidden">
                  <div
                    class="p-4 pt-0 text-sm text-gray-500 leading-relaxed border-t border-gray-50 mt-1"
                  >
                    {{ item.answer }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- Footer Help Section -->
      <footer class="mt-12 p-8 rounded-2xl bg-[#198496]/5 border border-[#198496]/10 text-center">
        <div class="inline-flex p-3 rounded-full bg-white shadow-sm mb-4">
          <MessageSquare class="w-6 h-6 text-[#198496]" />
        </div>
        <h3 class="text-lg font-semibold text-gray-900 mb-2">
          Vous ne trouvez pas votre réponse ?
        </h3>
        <p class="text-gray-500 text-sm mb-6 max-w-sm mx-auto">
          Notre équipe est disponible pour vous accompagner dans vos premiers pas sur WEACT.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <RouterLink
            to="/contact"
            class="text-sm font-medium bg-[#198496] text-white px-5 py-2 rounded-md hover:bg-[#146c7a] transition-colors inline-flex items-center"
          >
            Contacter le support
          </RouterLink>
          <RouterLink
            to="/guide"
            class="text-sm font-medium text-gray-700 hover:text-[#198496] px-5 py-2 transition-colors"
          >
            Consulter le guide complet
          </RouterLink>
        </div>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.grid-rows-\[0fr\] {
  grid-template-rows: 0fr;
}
.grid-rows-\[1fr\] {
  grid-template-rows: 1fr;
}
</style>
