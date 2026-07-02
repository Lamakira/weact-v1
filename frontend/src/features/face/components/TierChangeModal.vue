<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { Loader2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import type { SubscriptionOffer } from '@/features/face/types'
import { TIER_PRESENTATION, buildTierFeatureLines } from '@/features/face/tierPresentation'

type ChangeMode = 'activate' | 'renew' | 'upgrade' | 'downgrade'

interface Props {
  open: boolean
  mode: ChangeMode
  targetOffer: SubscriptionOffer
  currentTierLabel: string
  forfeitedDays: number
  isSubmitting: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{ confirm: []; cancel: [] }>()

const targetName = computed(() => TIER_PRESENTATION[props.targetOffer.tier].name)
const features = computed(() =>
  buildTierFeatureLines(props.targetOffer.tier, props.targetOffer.capabilities),
)
const priceLabel = computed(() =>
  new Intl.NumberFormat('fr-FR').format(props.targetOffer.price),
)

const title = computed(() => {
  switch (props.mode) {
    case 'renew':
      return `Renouveler ${targetName.value}`
    case 'upgrade':
      return `Passer à ${targetName.value}`
    case 'downgrade':
      return `Revenir à ${targetName.value}`
    default:
      return `Souscrire à ${targetName.value}`
  }
})

const showForfeitWarning = computed(() => props.forfeitedDays > 0)
const forfeitText = computed(() => {
  const n = props.forfeitedDays
  const jour = n > 1 ? 'jours' : 'jour'
  const restant = n > 1 ? 'restants' : 'restant'
  return `En confirmant, vous perdez ${n} ${jour} ${restant} de votre abonnement ${props.currentTierLabel} — aucun remboursement au prorata. Une nouvelle période de 12 mois démarre.`
})

function onCancel(): void {
  if (props.isSubmitting) return
  emit('cancel')
}

function onKeydown(e: KeyboardEvent): void {
  // P9 — the component stays mounted between `modalOpen=false` and the watch
  // that clears `modalTarget` (paymentState='confirmed' path). Ignore Escape
  // while the modal is visually hidden so the listener does not silently
  // clear modalTarget mid-polling.
  if (!props.open) return
  if (e.key === 'Escape') onCancel()
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
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
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        data-testid="tier-change-backdrop"
        @click.self="onCancel"
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
            v-if="open"
            class="relative w-full max-w-md overflow-hidden bg-white shadow-2xl rounded-2xl"
            role="dialog"
            aria-modal="true"
            data-testid="tier-change-modal"
          >
            <div class="p-6 sm:p-8">
              <h3 class="text-xl font-bold tracking-tight text-gray-900 mb-2">
                {{ title }}
              </h3>

              <p
                class="text-sm font-medium text-gray-700 mb-5"
                data-testid="tier-change-price"
              >
                {{ priceLabel }} F CFA · 12 mois
              </p>

              <p class="text-[11px] font-bold uppercase tracking-[0.10em] text-gray-400 mb-3">
                Inclus
              </p>
              <ul class="space-y-2 mb-5">
                <li
                  v-for="(feature, index) in features"
                  :key="index"
                  class="flex items-start gap-2 text-sm leading-snug"
                  :class="feature.highlight ? 'font-semibold text-gray-900' : 'text-gray-700'"
                >
                  <span
                    class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-[#198496]"
                    aria-hidden="true"
                  />
                  <span>{{ feature.text }}</span>
                </li>
              </ul>

              <!-- Loss-of-days warning (decision #3) -->
              <div
                v-if="showForfeitWarning"
                class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
                data-testid="tier-change-forfeit-warning"
              >
                {{ forfeitText }}
              </div>

              <!-- Footer -->
              <div class="flex flex-col sm:flex-row items-center justify-end gap-3">
                <Button
                  type="button"
                  variant="outline"
                  class="w-full sm:w-auto"
                  data-testid="tier-change-cancel"
                  @click="onCancel"
                >
                  Annuler
                </Button>
                <Button
                  type="button"
                  variant="default"
                  class="w-full sm:w-auto"
                  :disabled="isSubmitting"
                  data-testid="tier-change-confirm"
                  @click="emit('confirm')"
                >
                  <Loader2 v-if="isSubmitting" class="w-4 h-4 animate-spin" />
                  Confirmer et payer
                </Button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
