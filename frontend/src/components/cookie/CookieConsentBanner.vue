<script setup lang="ts">
/**
 * Cookie Consent Banner
 * Conformité Art. 389-390 du Code du Numérique du Bénin (Loi 2017-20)
 * Le consentement doit être libre, spécifique, éclairé et univoque.
 */
import { useCookieConsent } from '@/composables/useCookieConsent'
import CookiePreferencesModal from './CookiePreferencesModal.vue'
import { Cookie } from 'lucide-vue-next'

const {
  showBanner,
  showPreferences,
  preferences,
  acceptAll,
  rejectAll,
  savePreferences,
  openPreferences,
  closePreferences,
} = useCookieConsent()
</script>

<template>
  <!-- Banner -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="translate-y-full opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-full opacity-0"
    >
      <div
        v-if="showBanner"
        class="fixed bottom-0 left-0 right-0 z-[9998] p-4 sm:p-6"
      >
        <div class="max-w-3xl mx-auto bg-white border border-gray-200 rounded-lg shadow-lg p-4 sm:p-5">
          <div class="flex flex-col sm:flex-row items-start gap-4">
            <!-- Icon + Text -->
            <div class="flex items-start gap-3 flex-1">
              <div class="p-1.5 bg-[#198496]/10 rounded-md text-[#198496] shrink-0 mt-0.5">
                <Cookie :size="16" />
              </div>
              <div>
                <p class="text-sm font-medium text-gray-900 mb-1">Nous respectons votre vie privée</p>
                <p class="text-xs text-gray-600 leading-relaxed">
                  WEACT utilise des cookies pour assurer le fonctionnement du site et, avec votre accord,
                  des cookies analytiques pour améliorer votre expérience. Vous pouvez accepter, refuser
                  ou personnaliser vos choix. En savoir plus dans notre
                  <router-link to="/politique-confidentialite" class="text-[#198496] hover:underline">
                    Politique de Confidentialité</router-link>.
                </p>
              </div>
            </div>

            <!-- Buttons -->
            <div class="flex flex-wrap items-center gap-2 shrink-0 w-full sm:w-auto">
              <button
                @click="rejectAll"
                class="flex-1 sm:flex-none px-3 py-2 text-xs font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors cursor-pointer"
              >
                Refuser
              </button>
              <button
                @click="openPreferences"
                class="flex-1 sm:flex-none px-3 py-2 text-xs font-medium text-[#198496] border border-[#198496]/20 rounded-md hover:bg-[#198496]/5 transition-colors cursor-pointer"
              >
                Personnaliser
              </button>
              <button
                @click="acceptAll"
                class="flex-1 sm:flex-none px-3 py-2 text-xs font-medium text-white bg-[#198496] rounded-md hover:bg-[#146c7a] transition-colors cursor-pointer"
              >
                Accepter tout
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Preferences Modal -->
  <CookiePreferencesModal
    v-if="showPreferences"
    :initial-analytics="preferences.analytics"
    @save="savePreferences"
    @close="closePreferences"
  />
</template>
