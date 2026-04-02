<script setup lang="ts">
import { ref } from 'vue'
import { ChevronDown, FileText, ShieldCheck, Mail } from 'lucide-vue-next'

const activeSection = ref<string | null>('cgu')

const toggleSection = (id: string) => {
  activeSection.value = activeSection.value === id ? null : id
}

const lastUpdate = '1er février 2026'
</script>

<template>
  <div class="py-1">
    <!-- Header Section -->
    <header class="mb-6">
      <h1 class="text-2xl text-center lg:text-left font-semibold text-gray-900 mb-2">Mentions Légales & Conditions</h1>
      <p class="text-sm text-gray-500">
        Dernière mise à jour : <span class="font-medium text-gray-700">{{ lastUpdate }}</span>
      </p>
    </header>

    <div class="space-y-4">
      <!-- 1. CGU Section -->
      <section
        class="bg-white border border-gray-200 rounded-md overflow-hidden transition-all duration-200"
        :class="{ 'shadow-sm border-[#198496]/20': activeSection === 'cgu' }"
      >
        <button
          @click="toggleSection('cgu')"
          class="w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 transition-colors group"
        >
          <div class="flex items-center gap-4">
            <div
              class="p-2 rounded-md transition-colors"
              :class="activeSection === 'cgu' ? 'bg-[#198496]/10 text-[#198496]' : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200'"
            >
              <FileText :size="18" />
            </div>
            <div>
              <h2 class="text-sm font-medium text-gray-900">Conditions Générales d'Utilisation (CGU)</h2>
              <p class="text-xs text-gray-500 mt-0.5">Règles d'utilisation de la plateforme WEACT</p>
            </div>
          </div>
          <ChevronDown
            :size="18"
            class="text-gray-400 transition-transform duration-300"
            :class="{ 'rotate-180 text-[#198496]': activeSection === 'cgu' }"
          />
        </button>

        <div
          class="grid transition-all duration-300 ease-in-out"
          :class="activeSection === 'cgu' ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
        >
          <div class="overflow-hidden">
            <div class="p-6 pt-0 border-t border-gray-100 text-sm text-gray-700 leading-relaxed space-y-6">
              <div>
                <h3 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">1. Objet du site</h3>
                <p>WEACT est une marketplace de casting opérant au Bénin, mettant en relation des talents ("Faces") avec des recruteurs professionnels ("Producteurs"). Les présentes conditions régissent l'accès et l'utilisation de tous les services fournis par WEACT.</p>
              </div>

              <div>
                <h3 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">2. Inscription et Compte</h3>
                <p>L'accès à la plateforme nécessite la création d'un compte. L'utilisateur s'engage à fournir des informations exactes et à les maintenir à jour.</p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-4 bg-gray-50 rounded border border-gray-100">
                  <h4 class="font-medium text-gray-900 mb-2 text-xs">Obligations des Faces</h4>
                  <ul class="list-disc pl-4 space-y-1 text-xs">
                    <li>Authenticité des photos et du portfolio</li>
                    <li>Ponctualité lors des castings acceptés</li>
                    <li>Respect de l'image de marque de WEACT</li>
                  </ul>
                </div>
                <div class="p-4 bg-gray-50 rounded border border-gray-100">
                  <h4 class="font-medium text-gray-900 mb-2 text-xs">Obligations des Producteurs</h4>
                  <ul class="list-disc pl-4 space-y-1 text-xs">
                    <li>Description honnête des missions</li>
                    <li>Respect du droit du travail béninois</li>
                    <li>Interdiction de sollicitation directe hors plateforme</li>
                  </ul>
                </div>
              </div>

              <div>
                <h3 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">3. Propriété Intellectuelle</h3>
                <p>Tous les éléments du site (logos, interfaces, algorithmes) sont la propriété exclusive de WEACT. Les Faces concèdent à WEACT un droit d'utilisation de leur image dans le cadre strict de la mise en relation sur la plateforme.</p>
              </div>

              <div>
                <h3 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">4. Responsabilité et Résiliation</h3>
                <p>WEACT agit en tant qu'intermédiaire technique. En cas de manquement grave aux obligations, WEACT se réserve le droit de suspendre ou supprimer tout compte sans préavis.</p>
              </div>

              <div class="pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-500 font-medium italic">
                  Loi applicable : Les présentes CGU sont régies par le droit en vigueur en République du Bénin. En cas de litige, les tribunaux de Cotonou seront seuls compétents.
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- 2. Privacy Policy Section -->
      <section
        class="bg-white border border-gray-200 rounded-md overflow-hidden transition-all duration-200"
        :class="{ 'shadow-sm border-[#198496]/20': activeSection === 'privacy' }"
      >
        <button
          @click="toggleSection('privacy')"
          class="w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 transition-colors group"
        >
          <div class="flex items-center gap-4">
            <div
              class="p-2 rounded-md transition-colors"
              :class="activeSection === 'privacy' ? 'bg-[#198496]/10 text-[#198496]' : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200'"
            >
              <ShieldCheck :size="18" />
            </div>
            <div>
              <h2 class="text-sm font-medium text-gray-900">Politique de Confidentialité</h2>
              <p class="text-xs text-gray-500 mt-0.5">Gestion de vos données personnelles et cookies</p>
            </div>
          </div>
          <ChevronDown
            :size="18"
            class="text-gray-400 transition-transform duration-300"
            :class="{ 'rotate-180 text-[#198496]': activeSection === 'privacy' }"
          />
        </button>

        <div
          class="grid transition-all duration-300 ease-in-out"
          :class="activeSection === 'privacy' ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
        >
          <div class="overflow-hidden">
            <div class="p-6 pt-0 border-t border-gray-100 text-sm text-gray-700 leading-relaxed space-y-6">
              <div>
                <h3 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">1. Collecte des données</h3>
                <p>Nous collectons les données nécessaires au bon fonctionnement du service : identité, coordonnées, portfolio (photos/vidéos), et informations bancaires pour les paiements.</p>
              </div>

              <div class="flex flex-wrap gap-2">
                <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-[10px] font-medium border border-gray-200">Identité</span>
                <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-[10px] font-medium border border-gray-200">Images</span>
                <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-[10px] font-medium border border-gray-200">Localisation</span>
                <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-[10px] font-medium border border-gray-200">Données de paiement</span>
              </div>

              <div>
                <h3 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">2. Utilisation et Partage</h3>
                <p>Vos données sont utilisées pour le matching de casting et le traitement des paiements. Elles ne sont jamais vendues à des tiers. Nous partageons uniquement les profils des Faces avec les Producteurs dans le cadre d'un casting spécifique.</p>
              </div>

              <div>
                <h3 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">3. Cookies</h3>
                <p>WEACT utilise des cookies techniques pour maintenir votre session active et des cookies analytiques pour améliorer l'expérience utilisateur. Vous pouvez gérer vos préférences depuis les réglages de votre navigateur.</p>
              </div>

              <div>
                <h3 class="font-semibold text-gray-900 mb-2 uppercase tracking-wider text-[11px]">4. Vos Droits</h3>
                <p>Conformément à la législation sur la protection des données personnelles, vous disposez d'un droit d'accès, de rectification, de suppression et d'opposition au traitement de vos données.</p>
              </div>

              <!-- Contact CTA -->
              <div class="mt-8 p-6 bg-[#198496]/5 rounded-lg border border-[#198496]/10 flex flex-col md:flex-row items-center gap-4 justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center shadow-sm text-[#198496]">
                    <Mail :size="20" />
                  </div>
                  <div>
                    <p class="text-xs font-semibold text-gray-900">Contact DPO</p>
                    <p class="text-[11px] text-gray-500">Pour toute question relative à vos données</p>
                  </div>
                </div>
                <a
                  href="mailto:contact@weact.bj"
                  class="text-xs font-medium bg-[#198496] text-white px-4 py-2 rounded hover:bg-[#146c7a] transition-colors"
                >
                  Contacter le délégué (DPO)
                </a>
              </div>
            </div>
          </div>
        </div>
      </section>
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
