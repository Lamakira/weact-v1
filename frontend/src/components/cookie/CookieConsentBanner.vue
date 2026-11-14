<script setup lang="ts">
/**
 * Cookie Consent Banner
 * Conformité Art. 389-390 du Code du Numérique du Bénin (Loi 2017-20)
 * Le consentement doit être libre, spécifique, éclairé et univoque :
 * deux boutons de même poids visuel et de même taille (Refuser / Accepter).
 * La modale de préférences reste montée ici (ouverte aussi depuis le pied de
 * page et la politique de confidentialité).
 */
import { useCookieConsent } from '@/composables/useCookieConsent'
import CookiePreferencesModal from './CookiePreferencesModal.vue'

const {
  showBanner,
  showPreferences,
  preferences,
  acceptAll,
  rejectAll,
  savePreferences,
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
        data-testid="cookie-banner"
        role="dialog"
        aria-label="Mesure d'audience"
        class="fixed inset-x-0 bottom-0 z-[9998] rounded-t-2xl border-t border-border bg-background px-4 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))] shadow-lg sm:inset-x-auto sm:bottom-4 sm:left-4 sm:w-[380px] sm:rounded-xl sm:border sm:p-5"
      >
        <p class="mb-1 text-sm font-semibold text-foreground">Nous aider à améliorer WeAct</p>
        <p class="text-xs leading-relaxed text-muted-foreground">
          Avec votre accord, nous mesurons les pages consultées et les clics, et enregistrons certaines sessions de façon anonymisée. Vos saisies et vos messages ne sont jamais enregistrés.
          <router-link
            to="/politique-confidentialite"
            class="text-primary underline-offset-2 hover:underline"
            data-testid="cookie-banner-learn-more"
          >En savoir plus</router-link>
        </p>

        <div class="mt-4 grid grid-cols-2 gap-2">
          <button
            type="button"
            data-testid="cookie-banner-reject"
            class="cursor-pointer rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
            @click="rejectAll"
          >
            Refuser
          </button>
          <button
            type="button"
            data-testid="cookie-banner-accept"
            class="cursor-pointer rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
            @click="acceptAll"
          >
            Accepter
          </button>
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
