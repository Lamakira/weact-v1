<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Check, ChevronDown, HelpCircle, X } from 'lucide-vue-next'

interface PricingTier {
  name: string
  price: string
  period: string
  description: string
  features: string[]
  cta: string
  popular?: boolean
}

interface FAQItem {
  question: string
  answer: string
  isOpen: boolean
}

const pricingTiers: PricingTier[] = [
  {
    name: 'Gratuit',
    price: '0',
    period: 'FCFA/mois',
    description: 'Pour découvrir la plateforme et lancer vos premières recherches.',
    features: [
      '2 missions par mois',
      'Accès aux profils de base',
      'Support par email',
      'Messagerie standard',
    ],
    cta: 'Commencer',
  },
  {
    name: 'Pro',
    price: '15 000',
    period: 'FCFA/mois',
    description: 'Pour les agences actives qui ont besoin de réactivité et de choix.',
    features: [
      'Missions illimitées',
      'Filtres de recherche avancés',
      'Support prioritaire',
      'Statistiques et analytics',
      'Badges de vérification',
    ],
    cta: 'Commencer',
    popular: true,
  },
  {
    name: 'Entreprise',
    price: '45 000',
    period: 'FCFA/mois',
    description: 'Solutions sur mesure pour les grandes marques et besoins complexes.',
    features: [
      'Tout ce qui est dans Pro',
      'Account Manager dédié',
      'Accès API',
      'Branding personnalisé',
      'Contrats sur mesure',
    ],
    cta: 'Commencer',
  },
]

const faqs = ref<FAQItem[]>([
  {
    question: 'Puis-je changer de forfait à tout moment ?',
    answer:
      'Oui, vous pouvez passer à un forfait supérieur ou inférieur à tout moment depuis vos paramètres de compte. Le changement sera effectif immédiatement.',
    isOpen: false,
  },
  {
    question: 'Comment fonctionne le paiement en FCFA ?',
    answer:
      "Nous acceptons les cartes bancaires internationales ainsi que les solutions de paiement localement disponibles en Afrique de l'Ouest. Les factures sont émises mensuellement.",
    isOpen: false,
  },
  {
    question: 'Y a-t-il des frais de commission sur les missions ?',
    answer:
      "Selon votre abonnement, des frais de service réduits peuvent s'appliquer sur les transactions sécurisées via la plateforme pour garantir la protection de vos fonds.",
    isOpen: false,
  },
])

const toggleFaq = (index: number) => {
  const faq = faqs.value[index]
  if (faq) faq.isOpen = !faq.isOpen
}

const comparisonFeatures = [
  { name: 'Nombre de missions', gratuit: '2 / mois', pro: 'Illimité', entreprise: 'Illimité' },
  { name: 'Accès aux Faces', gratuit: 'Basique', pro: 'Complet', entreprise: 'Premium' },
  { name: 'Support technique', gratuit: 'Email', pro: 'Prioritaire', entreprise: '24/7 Dédié' },
  { name: 'Filtres avancés', gratuit: false, pro: true, entreprise: true },
  { name: 'Analyses de données', gratuit: false, pro: true, entreprise: true },
  { name: 'Accès API', gratuit: false, pro: false, entreprise: true },
]
</script>

<template>
  <div class="py-4">
    <div>
      <!-- Header Section -->
      <div class="text-center mb-16">
        <h1 class="text-sm font-semibold text-[#198496] uppercase tracking-wider mb-3">Tarifs</h1>
        <h2 class="text-3xl font-bold text-gray-900 mb-4">
          Une tarification transparente pour vos productions
        </h2>
        <p class="text-base text-gray-500 max-w-2xl mx-auto">
          Choisissez le forfait qui correspond à votre volume de production. Pas de frais cachés,
          résiliable à tout moment.
        </p>
      </div>

      <!-- Pricing Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-24">
        <div
          v-for="tier in pricingTiers"
          :key="tier.name"
          :class="[
            'relative bg-white rounded-xl border p-8 transition-all duration-200 flex flex-col',
            tier.popular
              ? 'border-[#198496] shadow-lg ring-1 ring-[#198496]/50 scale-105 z-10'
              : 'border-gray-200 shadow-sm hover:border-[#198496]/30',
          ]"
        >
          <div
            v-if="tier.popular"
            class="absolute -top-4 left-1/2 -translate-x-1/2 bg-[#198496] text-white text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-widest"
          >
            Populaire
          </div>

          <div class="mb-8">
            <h3 class="text-lg font-bold text-gray-900 mb-2">{{ tier.name }}</h3>
            <p class="text-sm text-gray-500 leading-relaxed mb-6">{{ tier.description }}</p>
            <div class="flex items-baseline">
              <span class="text-3xl font-bold text-gray-900">{{ tier.price }}</span>
              <span class="ml-1 text-sm font-medium text-gray-500">{{ tier.period }}</span>
            </div>
          </div>

          <div class="space-y-4 mb-8 flex-grow">
            <div v-for="feature in tier.features" :key="feature" class="flex items-start">
              <Check class="h-4 w-4 text-[#198496] mt-0.5 shrink-0" />
              <span class="ml-3 text-sm text-gray-700">{{ feature }}</span>
            </div>
          </div>

          <RouterLink
            to="/register/producer"
            :class="[
              tier.popular
                ? 'text-center text-sm font-medium bg-[#198496] text-white px-5 py-2.5 rounded-md hover:bg-[#146c7a] transition-colors shadow-sm'
                : 'text-center text-sm font-medium text-[#198496] border border-[#198496] px-5 py-2.5 rounded-md hover:bg-[#198496]/5 transition-colors',
            ]"
          >
            {{ tier.cta }}
          </RouterLink>
        </div>
      </div>

      <!-- Comparison Section -->
      <div class="mb-24">
        <div class="text-center mb-12">
          <h3 class="text-2xl font-bold text-gray-900 mb-2">Comparer les fonctionnalités</h3>
          <p class="text-sm text-gray-500">Détails complets de chaque offre</p>
        </div>

        <div class="overflow-x-auto border border-gray-200 rounded-lg bg-white shadow-sm">
          <table class="w-full text-left border-collapse min-w-[600px]">
            <thead>
              <tr class="bg-gray-50/50 border-b border-gray-200">
                <th class="py-4 px-6 text-xs font-semibold text-gray-500 uppercase">
                  Fonctionnalité
                </th>
                <th class="py-4 px-6 text-xs font-semibold text-gray-500 uppercase">Gratuit</th>
                <th class="py-4 px-6 text-xs font-semibold text-[#198496] uppercase">Pro</th>
                <th class="py-4 px-6 text-xs font-semibold text-gray-500 uppercase">Entreprise</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr
                v-for="f in comparisonFeatures"
                :key="f.name"
                class="hover:bg-gray-50/30 transition-colors"
              >
                <td class="py-4 px-6 text-sm font-medium text-gray-900">{{ f.name }}</td>
                <td class="py-4 px-6 text-sm text-gray-600">
                  <template v-if="typeof f.gratuit === 'boolean'">
                    <Check v-if="f.gratuit" class="h-4 w-4 text-[#198496]" />
                    <X v-else class="h-4 w-4 text-gray-300" />
                  </template>
                  <span v-else>{{ f.gratuit }}</span>
                </td>
                <td class="py-4 px-6 text-sm text-gray-900 font-medium">
                  <template v-if="typeof f.pro === 'boolean'">
                    <Check v-if="f.pro" class="h-4 w-4 text-[#198496]" />
                    <X v-else class="h-4 w-4 text-gray-300" />
                  </template>
                  <span v-else>{{ f.pro }}</span>
                </td>
                <td class="py-4 px-6 text-sm text-gray-600">
                  <template v-if="typeof f.entreprise === 'boolean'">
                    <Check v-if="f.entreprise" class="h-4 w-4 text-[#198496]" />
                    <X v-else class="h-4 w-4 text-gray-300" />
                  </template>
                  <span v-else>{{ f.entreprise }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- FAQ Section -->
      <div class="max-w-3xl mx-auto">
        <div class="text-center mb-10">
          <h3 class="text-2xl font-bold text-gray-900 mb-2">Questions fréquentes</h3>
          <p class="text-sm text-gray-500">Tout ce que vous devez savoir sur nos abonnements</p>
        </div>

        <div class="space-y-3">
          <div
            v-for="(faq, index) in faqs"
            :key="index"
            class="border border-gray-200 rounded-lg bg-white overflow-hidden transition-all duration-200"
          >
            <button
              @click="toggleFaq(index)"
              class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition-colors"
            >
              <div class="flex items-center space-x-3">
                <HelpCircle class="h-4 w-4 text-gray-400 shrink-0" />
                <span class="text-sm font-medium text-gray-900">{{ faq.question }}</span>
              </div>
              <ChevronDown
                :class="[
                  'h-4 w-4 text-gray-400 transition-transform duration-200 shrink-0 ml-4',
                  faq.isOpen ? 'rotate-180' : '',
                ]"
              />
            </button>
            <div
              v-show="faq.isOpen"
              class="px-6 pb-4 text-sm text-gray-500 leading-relaxed border-t border-gray-100 pt-3"
            >
              {{ faq.answer }}
            </div>
          </div>
        </div>
      </div>

      <!-- Footer CTA -->
      <div class="mt-24 text-center bg-white border border-gray-200 rounded-2xl p-10 shadow-sm">
        <h3 class="text-xl font-bold text-gray-900 mb-2">
          Besoin d'une solution personnalisée ?
        </h3>
        <p class="text-sm text-gray-500 mb-8 max-w-lg mx-auto">
          Pour les agences gérant plus de 50 missions par mois, contactez notre équipe commerciale
          pour un devis adapté.
        </p>
        <RouterLink
          to="/contact"
          class="text-sm font-medium text-[#198496] border border-[#198496] px-8 py-2.5 rounded-md hover:bg-[#198496]/5 transition-colors"
        >
          Contacter le support
        </RouterLink>
      </div>
    </div>
  </div>
</template>
