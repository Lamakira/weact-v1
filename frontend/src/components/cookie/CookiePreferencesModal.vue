<script setup lang="ts">
import { ref } from 'vue'
import { X, Lock, BarChart3 } from 'lucide-vue-next'

const props = defineProps<{
  initialAnalytics: boolean
}>()

const emit = defineEmits<{
  save: [prefs: { analytics: boolean }]
  close: []
}>()

const analytics = ref(props.initialAnalytics)
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
      <!-- Backdrop -->
      <div
        class="absolute inset-0 bg-black/50"
        @click="emit('close')"
      />

      <!-- Modal -->
      <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-100">
          <h2 class="text-sm font-semibold text-gray-900">Préférences de cookies</h2>
          <button
            @click="emit('close')"
            class="p-1 text-gray-400 hover:text-gray-600 transition-colors rounded cursor-pointer"
            aria-label="Fermer"
          >
            <X :size="18" />
          </button>
        </div>

        <!-- Body -->
        <div class="p-4 space-y-4">
          <p class="text-xs text-gray-600 leading-relaxed">
            Nous utilisons des cookies pour assurer le fonctionnement du site et améliorer votre expérience.
            Vous pouvez personnaliser vos choix ci-dessous. Pour en savoir plus, consultez notre
            <router-link to="/politique-confidentialite" class="text-[#198496] hover:underline" @click="emit('close')">
              Politique de Confidentialité</router-link>.
          </p>

          <!-- Necessary cookies -->
          <div class="p-3 bg-gray-50 rounded-md border border-gray-100">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Lock :size="14" class="text-gray-500" />
                <span class="text-xs font-medium text-gray-900">Cookies strictement nécessaires</span>
              </div>
              <span class="text-[10px] font-medium text-gray-500 bg-gray-200 px-2 py-0.5 rounded">Toujours actifs</span>
            </div>
            <p class="text-[11px] text-gray-500 mt-1.5 ml-5">
              Indispensables au fonctionnement du site : session, authentification, sécurité.
              Ces cookies ne nécessitent pas votre consentement.
            </p>
          </div>

          <!-- Analytics cookies -->
          <div class="p-3 bg-gray-50 rounded-md border border-gray-100">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <BarChart3 :size="14" class="text-gray-500" />
                <span class="text-xs font-medium text-gray-900">Cookies analytiques</span>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input
                  v-model="analytics"
                  type="checkbox"
                  class="sr-only peer"
                />
                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#198496]/30 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#198496]" />
              </label>
            </div>
            <p class="text-[11px] text-gray-500 mt-1.5 ml-5">
              Nous aident à comprendre comment le site est utilisé pour améliorer l'expérience.
              Ces cookies sont soumis à votre consentement.
            </p>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-2 p-4 border-t border-gray-100">
          <button
            @click="emit('close')"
            class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200 transition-colors cursor-pointer"
          >
            Annuler
          </button>
          <button
            @click="emit('save', { analytics })"
            class="px-3 py-1.5 text-xs font-medium text-white bg-[#198496] rounded hover:bg-[#146c7a] transition-colors cursor-pointer"
          >
            Enregistrer mes préférences
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
