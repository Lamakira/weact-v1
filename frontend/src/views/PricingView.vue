<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Check, ChevronDown, Crown, Minus } from 'lucide-vue-next'

// =============================================================
// Types
// =============================================================
interface TierFeature {
  text: string
  included: boolean
  highlight?: boolean
}

interface PricingTier {
  key: 'decouverte' | 'starter' | 'pro' | 'elite'
  name: string
  tagline: string
  priceLabel: string
  isFree: boolean
  description: string
  cta: string
  ctaTo: string
  badge: string | null
  features: TierFeature[]
}

interface FAQItem {
  question: string
  answer: string
}

type CellValue = boolean | string

interface ComparisonRow {
  name: string
  decouverte: CellValue
  starter: CellValue
  pro: CellValue
  elite: CellValue
}

interface ComparisonGroup {
  label: string
  rows: ComparisonRow[]
}

// =============================================================
// Data — 4 subscription tiers (annual pricing in FCFA)
// =============================================================
const tiers: PricingTier[] = [
  {
    key: 'decouverte',
    name: 'Découverte',
    tagline: 'Pour tester la plateforme',
    priceLabel: '0',
    isFree: true,
    description: "Crée ton profil et explore l'écosystème WeAct sans engagement.",
    cta: "S'inscrire",
    ctaTo: '/register/face',
    badge: null,
    features: [
      { text: 'Photo de profil', included: true },
      { text: '1 photo dans la galerie', included: true },
      { text: 'Profil visible (rang standard)', included: true },
    ],
  },
  {
    key: 'starter',
    name: 'Starter',
    tagline: 'Décroche tes premiers contrats UGC',
    priceLabel: '12 000',
    isFree: false,
    description: 'Pour les talents prêts à se lancer dans les missions UGC rémunérées.',
    cta: 'Choisir Starter',
    ctaTo: '/register/face?plan=starter',
    badge: null,
    features: [
      { text: 'Photo de profil', included: true },
      { text: '2 photos dans la galerie', included: true },
      { text: '1 vidéo de présentation', included: true },
      { text: 'Accès complet au module UGC', included: true },
      { text: 'Mise en avant Boostée', included: true },
    ],
  },
  {
    key: 'pro',
    name: 'Pro',
    tagline: 'Acting + UGC, le combo sérieux',
    priceLabel: '25 000',
    isFree: false,
    description: "Pour les talents qui font du cinéma, de l'acting et de l'UGC.",
    cta: 'Choisir Pro',
    ctaTo: '/register/face?plan=pro',
    badge: 'Populaire',
    features: [
      { text: 'Photo de profil', included: true },
      { text: '4 photos dans la galerie', included: true },
      { text: '1 vidéo de présentation', included: true },
      { text: '1 vidéo démo Acting', included: true },
      { text: 'Accès complet au module UGC', included: true },
      { text: 'Mise en avant Premium', included: true },
    ],
  },
  {
    key: 'elite',
    name: 'Élite',
    tagline: "L'offre VIP des tops profils",
    priceLabel: '40 000',
    isFree: false,
    description:
      'Pour les professionnels qui veulent une visibilité maximale et une commission réduite.',
    cta: 'Passer Élite',
    ctaTo: '/register/face?plan=elite',
    badge: 'VIP',
    features: [
      { text: 'Portfolio complet : 6 photos', included: true },
      { text: '1 vidéo de présentation', included: true },
      { text: '2 vidéos Acting', included: true },
      { text: '1 vidéo modèle UGC', included: true },
      { text: 'Commission réduite à 5% (au lieu de 10%)', included: true, highlight: true },
      { text: 'Badge "VIP / Elite" sur le profil', included: true, highlight: true },
      { text: 'Mise en avant Prioritaire Absolue', included: true, highlight: true },
      { text: 'Assistance prioritaire WeAct', included: true },
    ],
  },
]

// =============================================================
// Comparison table data
// =============================================================
const comparisonGroups: ComparisonGroup[] = [
  {
    label: 'Portfolio',
    rows: [
      { name: 'Photo de profil', decouverte: true, starter: true, pro: true, elite: true },
      { name: 'Photos dans la galerie', decouverte: '1', starter: '2', pro: '4', elite: '6' },
      { name: 'Vidéo de présentation', decouverte: false, starter: true, pro: true, elite: true },
      { name: 'Vidéos Acting / démo', decouverte: false, starter: false, pro: '1', elite: '2' },
      { name: 'Vidéo modèle UGC', decouverte: false, starter: false, pro: false, elite: true },
    ],
  },
  {
    label: 'Missions & revenus',
    rows: [
      {
        name: 'Accès aux missions UGC',
        decouverte: false,
        starter: true,
        pro: true,
        elite: true,
      },
      { name: 'Dotation produit', decouverte: false, starter: true, pro: true, elite: true },
      { name: 'Missions rémunérées', decouverte: false, starter: true, pro: true, elite: true },
      {
        name: 'Commission plateforme',
        decouverte: '—',
        starter: '10 %',
        pro: '10 %',
        elite: '5 %',
      },
    ],
  },
  {
    label: 'Visibilité',
    rows: [
      {
        name: 'Mise en avant',
        decouverte: 'Standard',
        starter: 'Boostée',
        pro: 'Premium',
        elite: 'Prioritaire',
      },
      {
        name: 'Rang dans la recherche',
        decouverte: '4ᵉ',
        starter: '3ᵉ',
        pro: '2ᵉ',
        elite: '1ᵉʳ',
      },
      { name: 'Badge VIP / Élite', decouverte: false, starter: false, pro: false, elite: true },
    ],
  },
  {
    label: 'Support',
    rows: [
      { name: "Centre d'aide", decouverte: true, starter: true, pro: true, elite: true },
      {
        name: 'Assistance prioritaire WeAct',
        decouverte: false,
        starter: false,
        pro: false,
        elite: true,
      },
    ],
  },
]

// =============================================================
// FAQ
// =============================================================
const faqs: FAQItem[] = [
  {
    question: 'Puis-je changer de palier à tout moment ?',
    answer:
      "Oui. Tu peux passer à un autre palier quand tu veux. Le nouveau palier est facturé au prix annuel plein et démarre une nouvelle période de 12 mois - les jours restants de ton abonnement en cours ne sont pas reportés. Le nombre de jours perdus t'est indiqué avant la confirmation.",
  },
  {
    question: 'Comment fonctionne la commission réduite Élite ?',
    answer:
      'Sur toutes les missions rémunérées en argent, la plateforme prélève 10 % de frais. Avec l\'abonnement Élite, ce taux passe à 5 % - soit la moitié. Sur 100 000 FCFA de cachet, tu encaisses 95 000 FCFA au lieu de 90 000 FCFA.',
  },
  {
    question: 'Quels moyens de paiement acceptez-vous ?',
    answer:
      'Mobile Money (MTN, Moov), carte bancaire internationale (Visa, Mastercard) et virement bancaire pour les paliers Pro et Élite.',
  },
  {
    question: 'Que se passe-t-il si mon abonnement expire ?',
    answer:
      'Ton profil bascule automatiquement sur l\'offre Découverte gratuite. Tes photos et vidéos restent stockées 90 jours, le temps de te réabonner si tu le souhaites.',
  },
  {
    question: 'Comment fonctionne la mise en avant ?',
    answer:
      "Dans la recherche des producteurs, les profils sont triés par palier : Élite en premier, puis Pro, puis Starter, puis Découverte. À palier égal, c'est l'algorithme classique de pertinence qui s'applique.",
  },
]

const openFaqIndex = ref<number | null>(0)

const toggleFaq = (index: number) => {
  openFaqIndex.value = openFaqIndex.value === index ? null : index
}

const tierKeys: Array<'decouverte' | 'starter' | 'pro' | 'elite'> = [
  'decouverte',
  'starter',
  'pro',
  'elite',
]
const tierLabels: Record<string, string> = {
  decouverte: 'Découverte',
  starter: 'Starter',
  pro: 'Pro',
  elite: 'Élite',
}
const tierPrices: Record<string, string> = {
  decouverte: '0',
  starter: '12 000',
  pro: '25 000',
  elite: '40 000',
}
</script>

<template>
  <div>
    <!-- ============================================================ -->
    <!-- Hero                                                          -->
    <!-- ============================================================ -->
    <section class="max-w-3xl mx-auto px-6 pb-6 text-center">
      <h1
        class="text-3xl sm:text-4xl lg:text-[44px] font-bold text-[#0F1419] leading-[1.1] tracking-tight mb-4"
      >
        Plus tu montes, plus tu décroches.
      </h1>
      <p class="text-base text-gray-500 leading-relaxed max-w-3xl mx-auto">
        Quatre paliers pensés pour les talents béninois et ouest-africains. Du profil découverte
        gratuit au statut VIP, chaque palier débloque plus de portfolio, de visibilité et de
        missions UGC.
      </p>
    </section>

    <!-- ============================================================ -->
    <!-- Pricing Cards — Ladder + Élite sombre                        -->
    <!-- ============================================================ -->
    <section class="max-w-7xl mx-auto pb-8">
      <div
        data-testid="pricing-grid"
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-0 lg:rounded-2xl lg:overflow-hidden lg:border lg:border-gray-200 lg:shadow-[0_4px_32px_-16px_rgba(15,20,25,0.10)]"
      >
        <div
          v-for="(tier, idx) in tiers"
          :key="tier.key"
          :data-testid="`tier-card-${tier.key}`"
          :class="[
            'flex flex-col p-7 lg:p-7 relative',
            'rounded-2xl lg:rounded-none',
            'border lg:border-0 border-gray-200',
            tier.key === 'elite' ? 'bg-[#0F1419] text-white' : '',
            idx < tiers.length - 1 && tier.key !== 'elite' ? 'lg:border-r lg:border-gray-200' : '',
          ]"
        >
          <!-- Ladder indicator -->
          <div class="flex gap-1 mb-5">
            <div
              v-for="i in 4"
              :key="i"
              :class="[
                'w-5 h-[3px] rounded-full',
                i - 1 <= idx
                  ? tier.key === 'elite'
                    ? 'bg-white'
                    : 'bg-[#198496]'
                  : tier.key === 'elite'
                    ? 'bg-gray-700'
                    : 'bg-gray-200',
              ]"
            ></div>
          </div>

          <!-- Tier name + badge -->
          <div class="flex items-center gap-2 mb-1.5">
            <h3
              :class="[
                'text-xl font-bold tracking-tight',
                tier.key === 'elite' ? 'text-white' : 'text-gray-900',
              ]"
            >
              {{ tier.name }}
            </h3>
            <Crown v-if="tier.key === 'elite'" class="h-3.5 w-3.5 text-white" />
            <span
              v-if="tier.badge && tier.key !== 'elite'"
              class="text-[9px] font-bold tracking-[0.12em] uppercase text-white bg-[#198496] px-1.5 py-0.5 rounded"
            >
              {{ tier.badge }}
            </span>
          </div>

          <!-- Tagline -->
          <p
            :class="[
              'text-sm leading-snug mb-6 min-h-[38px]',
              tier.key === 'elite' ? 'text-gray-400' : 'text-gray-500',
            ]"
          >
            {{ tier.tagline }}
          </p>

          <!-- Price -->
          <div class="mb-6">
            <div class="flex items-baseline gap-1">
              <span
                :class="[
                  'text-[38px] font-bold tracking-tight leading-none',
                  tier.key === 'elite' ? 'text-white' : 'text-gray-900',
                ]"
              >
                {{ tier.priceLabel }}
              </span>
              <span
                v-if="!tier.isFree"
                :class="[
                  'text-sm font-medium',
                  tier.key === 'elite' ? 'text-gray-400' : 'text-gray-500',
                ]"
              >
                FCFA
              </span>
            </div>
            <div
              :class="[
                'text-xs mt-1.5',
                tier.key === 'elite' ? 'text-gray-500' : 'text-gray-400',
              ]"
            >
              {{ tier.isFree ? 'Offre découverte' : 'par an · sans engagement' }}
            </div>
          </div>

          <!-- CTA -->
          <RouterLink
            :to="tier.ctaTo"
            :data-testid="`cta-tier-${tier.key}`"
            :class="[
              'w-full text-center text-sm font-semibold py-2.5 px-4 rounded-md transition-colors mb-7',
              tier.key === 'elite'
                ? 'bg-white text-[#0F1419] hover:bg-gray-100'
                : tier.key === 'pro'
                  ? 'bg-[#198496] text-white hover:bg-[#146c7a]'
                  : 'text-[#198496] border border-[#198496] hover:bg-[#198496]/5',
            ]"
          >
            {{ tier.cta }}
          </RouterLink>

          <!-- Features -->
          <div
            :class="[
              'text-[11px] font-bold uppercase tracking-[0.10em] mb-3.5',
              tier.key === 'elite' ? 'text-gray-500' : 'text-gray-400',
            ]"
          >
            Inclus
          </div>
          <ul class="flex flex-col gap-3 flex-grow">
            <li
              v-for="(f, i) in tier.features"
              :key="i"
              class="flex items-start gap-2.5"
            >
              <Check
                :class="[
                  'h-4 w-4 mt-0.5 shrink-0',
                  tier.key === 'elite' ? 'text-white' : 'text-[#198496]',
                ]"
              />
              <span
                :class="[
                  'text-sm leading-snug',
                  tier.key === 'elite'
                    ? f.highlight
                      ? 'text-white font-semibold'
                      : 'text-gray-300'
                    : f.highlight
                      ? 'text-gray-900 font-semibold'
                      : 'text-gray-700',
                ]"
              >
                {{ f.text }}
              </span>
            </li>
          </ul>

          <div
            v-if="tier.key === 'decouverte'"
            class="mt-4 pt-4 border-t border-gray-100 text-[11px] text-gray-400 leading-snug"
          >
            Pas d'accès aux missions UGC
          </div>
        </div>
      </div>

      <p class="text-center text-xs text-gray-400 mt-6">
        Tous les abonnements sont annuels et payables une seule fois. Mobile Money, carte bancaire
        et virement acceptés.
      </p>
    </section>

    <!-- ============================================================ -->
    <!-- Comparison Table — Classic grouped                            -->
    <!-- ============================================================ -->
    <section class="bg-[#FAFAFA] py-8">
      <div class="max-w-7xl mx-auto">
        <div class="text-center mb-6">
          <div class="text-xs font-semibold text-[#198496] uppercase tracking-[0.14em] mb-3">
            Comparatif détaillé
          </div>
          <h2 class="text-2xl sm:text-3xl font-bold text-[#0F1419] tracking-tight mb-3">
            Toutes les fonctionnalités, palier par palier
          </h2>
          <p class="text-sm text-gray-500">
            Ce que tu débloques exactement à chaque niveau d'abonnement.
          </p>
        </div>

        <div
          class="border border-gray-200 rounded-2xl overflow-hidden overflow-x-auto"
        >
          <table class="w-full border-collapse table-fixed min-w-[760px]">
            <colgroup>
              <col class="w-[32%]" />
              <col class="w-[17%]" />
              <col class="w-[17%]" />
              <col class="w-[17%]" />
              <col class="w-[17%]" />
            </colgroup>
            <thead>
              <tr>
                <th
                  class="px-6 pt-6 pb-5 text-left align-top bg-[#FAFAFA] border-b border-gray-200"
                >
                  <div
                    class="text-[11px] font-semibold text-gray-400 uppercase tracking-[0.12em]"
                  >
                    Comparatif
                  </div>
                  <div class="text-sm text-gray-700 mt-2 font-medium">
                    Tarifs annuels en FCFA
                  </div>
                </th>
                <th
                  v-for="k in tierKeys"
                  :key="k"
                  :class="[
                    'px-4 pt-6 pb-5 text-left align-top relative border-b',
                    k === 'elite'
                      ? 'bg-[#0F1419] text-white border-[#0F1419]'
                      : 'bg-[#FAFAFA] text-gray-900 border-gray-200',
                  ]"
                >
                  <div
                    v-if="k === 'pro'"
                    class="absolute top-3.5 right-3.5 text-[9px] font-bold tracking-[0.12em] uppercase text-white bg-[#198496] px-1.5 py-0.5 rounded"
                  >
                    Populaire
                  </div>
                  <div
                    v-if="k === 'elite'"
                    class="absolute top-3.5 right-3.5 text-[9px] font-bold tracking-[0.12em] uppercase text-[#0F1419] bg-white px-1.5 py-0.5 rounded"
                  >
                    VIP
                  </div>
                  <div class="text-base font-bold">{{ tierLabels[k] }}</div>
                  <div class="flex items-baseline gap-1 mt-1.5">
                    <span class="text-[22px] font-bold tracking-tight">{{
                      tierPrices[k]
                    }}</span>
                    <span
                      v-if="k !== 'decouverte'"
                      :class="['text-[11px]', k === 'elite' ? 'text-gray-400' : 'text-gray-500']"
                      >FCFA / an</span
                    >
                    <span v-else class="text-[11px] text-gray-500">· gratuit</span>
                  </div>
                  <RouterLink
                    :to="
                      k === 'decouverte'
                        ? '/register/face'
                        : `/register/face?plan=${k}`
                    "
                    :data-testid="`cta-comparison-${k}`"
                    :class="[
                      'inline-block mt-3.5 px-3.5 py-1.5 text-xs font-semibold rounded-md transition-colors',
                      k === 'elite'
                        ? 'bg-white text-[#0F1419] hover:bg-gray-100'
                        : k === 'pro'
                          ? 'bg-[#198496] text-white hover:bg-[#146c7a]'
                          : 'border border-[#198496] text-[#198496] hover:bg-[#198496]/5',
                    ]"
                    >Choisir</RouterLink
                  >
                </th>
              </tr>
            </thead>
            <tbody>
              <template v-for="group in comparisonGroups" :key="group.label">
                <tr>
                  <td
                    colspan="4"
                    class="px-6 py-3.5 text-[11px] font-bold uppercase tracking-[0.14em] text-[#198496] bg-gray-50 border-t border-b border-gray-200"
                  >
                    {{ group.label }}
                  </td>
                  <td class="bg-[#0F1419] border-t border-b border-gray-200"></td>
                </tr>
                <tr v-for="(row, i) in group.rows" :key="i">
                  <td
                    class="px-6 py-3.5 text-sm font-medium text-gray-700 border-b border-gray-100"
                  >
                    {{ row.name }}
                  </td>
                  <td
                    v-for="k in tierKeys"
                    :key="k"
                    :class="[
                      'px-4 py-3.5 text-sm',
                      k === 'elite'
                        ? 'bg-[#0F1419] text-white border-b border-gray-800'
                        : 'text-gray-900 border-b border-gray-100',
                    ]"
                  >
                    <template v-if="typeof row[k] === 'boolean'">
                      <Check
                        v-if="row[k]"
                        :class="['h-4 w-4', k === 'elite' ? 'text-white' : 'text-[#198496]']"
                      />
                      <Minus v-else class="h-4 w-4 text-gray-300" />
                    </template>
                    <span v-else class="font-medium">{{ row[k] }}</span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ============================================================ -->
    <!-- FAQ                                                           -->
    <!-- ============================================================ -->
    <section class="max-w-3xl mx-auto px-6 py-8">
      <div class="text-center mb-5">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight mb-2">
          Questions fréquentes
        </h2>
        <p class="text-sm text-gray-500">Tout ce que tu dois savoir avant de souscrire.</p>
      </div>
      <div class="flex flex-col gap-2.5">
        <div
          v-for="(faq, index) in faqs"
          :key="index"
          class="border border-gray-200 rounded-lg overflow-hidden transition-all"
        >
          <button
            @click="toggleFaq(index)"
            class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition-colors"
            :aria-expanded="openFaqIndex === index"
            :aria-controls="`faq-panel-${index}`"
            :data-testid="`faq-toggle-${index}`"
          >
            <span class="text-sm font-semibold text-gray-900 pr-4">{{ faq.question }}</span>
            <ChevronDown
              :class="[
                'h-4 w-4 text-gray-400 transition-transform duration-200 shrink-0',
                openFaqIndex === index ? 'rotate-180' : '',
              ]"
            />
          </button>
          <div
            :id="`faq-panel-${index}`"
            role="region"
            class="grid transition-[grid-template-rows] duration-300 ease-out"
            :class="openFaqIndex === index ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
            :aria-hidden="openFaqIndex !== index"
            :inert="openFaqIndex !== index || undefined"
          >
            <div class="overflow-hidden">
              <div
                class="px-5 pb-4 text-sm text-gray-600 leading-relaxed border-t border-gray-100 pt-3"
              >
                {{ faq.answer }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ============================================================ -->
    <!-- Footer CTA                                                    -->
    <!-- ============================================================ -->
    <section class="bg-[#FAFAFA] pb-8">
      <div class="max-w-3xl mx-auto px-6">
        <div
          class="border border-gray-200 rounded-2xl p-10 text-center shadow-sm"
        >
          <h3 class="text-xl sm:text-2xl font-bold text-gray-900 tracking-tight mb-3">
            Tu hésites encore ?
          </h3>
          <p class="text-sm text-gray-500 leading-relaxed mb-6 max-w-md mx-auto">
            Commence avec l'offre Découverte, crée ton profil, et passe à un palier supérieur dès
            que tu veux candidater à des missions UGC.
          </p>
          <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <RouterLink
              to="/register/face"
              data-testid="cta-footer-register"
              class="text-sm font-semibold bg-[#198496] text-white px-5 py-2.5 rounded-md hover:bg-[#146c7a] transition-colors"
            >
              Créer mon profil gratuit
            </RouterLink>
            <RouterLink
              to="/contact"
              data-testid="cta-footer-contact"
              class="text-sm font-semibold text-[#198496] border border-[#198496] px-5 py-2.5 rounded-md hover:bg-[#198496]/5 transition-colors"
            >
              Nous contacter
            </RouterLink>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
