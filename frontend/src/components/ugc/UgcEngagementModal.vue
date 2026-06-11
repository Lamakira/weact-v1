<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { X, AlertTriangle, PackageCheck, Truck } from 'lucide-vue-next'
import ChronoRing from './ChronoRing.vue'

// Modal d'engagement UGC (2.4) — partagée booking + mission. L'engagement sur
// les délais est explicite : expédition après acceptation, « Produit reçu » =
// déclencheur des chronos, Unboxing 7 j / Avis 14 j (copy statique, D-2.3.f).
// Primitive autonome : n'importe que ChronoRing du même dossier (D-2.4.h).
const props = withDefaults(
  defineProps<{
    isOpen: boolean
    nombreVideos: number | null
    nomProduit?: string | null
    isSubmitting?: boolean
  }>(),
  { nomProduit: null, isSubmitting: false },
)

const emit = defineEmits<{
  (e: 'confirm'): void
  (e: 'cancel'): void
}>()

const extraVideos = computed(() => Math.max(0, (props.nombreVideos ?? 0) - 2))

// Pas d'annulation pendant la soumission : la requête part quand même,
// fermer la modal en plein vol ferait croire à un engagement annulé.
const handleCancel = (): void => {
  if (props.isSubmitting) {
    return
  }
  emit('cancel')
}

const handleEscape = (e: KeyboardEvent): void => {
  if (e.key === 'Escape' && props.isOpen) {
    handleCancel()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleEscape)
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        @click.self="handleCancel"
      >
        <Transition
          appear
          enter-active-class="transition duration-300 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-2"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-200 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-95 translate-y-2"
        >
          <div
            v-if="isOpen"
            class="relative w-full max-w-md overflow-hidden bg-white shadow-2xl rounded-2xl"
            role="dialog"
            aria-modal="true"
            aria-labelledby="ugc-engagement-title"
          >
            <button
              @click="handleCancel"
              :disabled="isSubmitting"
              class="absolute top-4 right-4 p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-all duration-200 disabled:cursor-not-allowed disabled:opacity-60"
              aria-label="Fermer"
            >
              <X class="w-4 h-4" />
            </button>

            <div class="p-6 sm:p-8">
              <h3 id="ugc-engagement-title" class="text-xl font-bold text-gray-900">
                Accepter ce deal UGC
              </h3>
              <p v-if="nomProduit" class="mt-1 text-sm text-gray-500">
                Produit : {{ nomProduit }}
              </p>

              <!-- Engagement : expédition + déclencheur des chronos -->
              <ul class="mt-5 space-y-3 text-sm text-gray-600">
                <li class="flex items-start gap-3">
                  <Truck class="mt-0.5 h-5 w-5 shrink-0 text-weact-500" aria-hidden="true" />
                  <span>Le producteur expédie le produit après votre acceptation.</span>
                </li>
                <li class="flex items-start gap-3">
                  <PackageCheck class="mt-0.5 h-5 w-5 shrink-0 text-weact-500" aria-hidden="true" />
                  <span>À la réception, appuyez sur « Produit reçu » — les chronos démarrent.</span>
                </li>
              </ul>

              <!-- Livrables sous chrono -->
              <div class="mt-5 space-y-3 rounded-xl bg-gray-50 p-4">
                <div class="flex items-center gap-3">
                  <ChronoRing
                    :progress="0"
                    :size="36"
                    :stroke="4"
                    label="7"
                    aria-label="Chrono Unboxing : 7 jours, non démarré"
                  />
                  <span class="text-sm font-medium text-gray-700">Vidéo Unboxing — à livrer sous 7 jours</span>
                </div>
                <div class="flex items-center gap-3">
                  <ChronoRing
                    :progress="0"
                    :size="36"
                    :stroke="4"
                    label="14"
                    aria-label="Chrono Avis : 14 jours, non démarré"
                  />
                  <span class="text-sm font-medium text-gray-700">Vidéo Avis — à livrer sous 14 jours</span>
                </div>
                <p v-if="extraVideos > 0" class="text-xs text-gray-500" data-testid="ugc-engagement-extras">
                  + {{ extraVideos }} vidéo(s) supplémentaire(s) — détails dans le brief
                </p>
              </div>

              <!-- Avertissement suspension -->
              <div
                class="mt-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4"
                data-testid="ugc-engagement-warning"
              >
                <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" aria-hidden="true" />
                <p class="text-sm text-amber-800">
                  Dépasser un délai sans livrer entraîne la suspension automatique de votre compte.
                </p>
              </div>

              <!-- Actions -->
              <div class="mt-6 flex flex-col sm:flex-row items-center justify-center gap-3">
                <button
                  type="button"
                  @click="handleCancel"
                  :disabled="isSubmitting"
                  class="w-full sm:flex-1 px-5 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 hover:border-gray-300 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-gray-100 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  Annuler
                </button>
                <button
                  type="button"
                  :disabled="isSubmitting"
                  data-testid="ugc-engagement-confirm"
                  @click="emit('confirm')"
                  class="w-full sm:flex-1 px-5 py-2.5 text-sm font-semibold text-white rounded-lg bg-weact-500 hover:bg-weact-600 transition-all duration-200 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-weact-500 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  J'accepte et je m'engage
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
