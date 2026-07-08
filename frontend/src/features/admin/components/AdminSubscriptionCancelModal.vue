<script setup lang="ts">
import { ref, watch } from 'vue'
import { Loader2, X } from 'lucide-vue-next'
import type { AdminSubscriptionActionResult } from '../composables/useAdminFaceSubscriptions'
import type { CancelPayload } from '../services/adminFaceSubscriptionsApi'
import { useModalFocusTrap } from '@/composables/useModalFocusTrap'

/** Vue minimale de l'abonnement ciblé — la modale n'a besoin que de l'id. */
export interface CancelModalTarget {
  id: string
}

const props = withDefaults(
  defineProps<{
    /** Abonnement ciblé ; la modale est ouverte tant qu'il est non-null. */
    target: CancelModalTarget | null
    /** Mutation à exécuter (cancel du composable useAdminFaceSubscriptions du parent). */
    submitAction: (
      subscriptionId: string,
      payload: CancelPayload,
    ) => Promise<AdminSubscriptionActionResult>
    /** Préfixe des data-testid propres au consommateur (racine, bannière, erreurs de champ). */
    testidPrefix?: string
    /** Préfixe des id HTML (titre, champs) — évite les collisions entre consommateurs. */
    idPrefix?: string
  }>(),
  {
    testidPrefix: 'subscriptions',
    idPrefix: 'subscriptions-cancel',
  },
)

const emit = defineEmits<{
  /** Fermeture demandée par l'utilisateur (Échap, X, backdrop, bouton Retour). */
  close: []
  /** Mutation réussie — le parent ferme (target = null), toaste et rafraîchit. */
  success: [message: string | null]
  /** Échec non-validation (409…) — l'état a bougé ailleurs, le parent rafraîchit. */
  conflict: []
}>()

const modalRef = ref<HTMLDivElement | null>(null)
const { prepareModalFocus, restoreFocus, trapModalFocus } = useModalFocusTrap()

const notes = ref('')
const submitting = ref(false)
const conflictMessage = ref<string | null>(null)
const errors = ref<Record<string, string[]>>({})

watch(
  () => props.target,
  async (target, previous) => {
    if (!target || target === previous) return
    notes.value = ''
    submitting.value = false
    conflictMessage.value = null
    errors.value = {}
    await prepareModalFocus(modalRef)
  },
)

function requestClose(): void {
  if (submitting.value) return
  emit('close')
  restoreFocus()
}

async function submit(): Promise<void> {
  if (submitting.value) return
  if (!props.target) return
  submitting.value = true
  conflictMessage.value = null
  errors.value = {}

  const payload: CancelPayload = { notes: notes.value.trim() }

  let result: AdminSubscriptionActionResult
  try {
    result = await props.submitAction(props.target.id, payload)
  } catch {
    submitting.value = false
    return
  }

  if (result.success) {
    submitting.value = false
    // Restaure le focus sur le bouton d'origine — parité avec la fermeture
    // manuelle ; le parent ferme (target = null) dans son handler @success.
    restoreFocus()
    emit('success', result.message ?? null)
    return
  }

  submitting.value = false

  if (result.code === 'VALIDATION_ERROR') {
    errors.value = result.errors ?? {}
    conflictMessage.value = result.message ?? null
  } else {
    errors.value = {}
    conflictMessage.value = result.message ?? 'Une erreur est survenue'
    // Un 409 signifie que l'état a bougé ailleurs — le parent rafraîchit sa
    // liste (et ses stats le cas échéant) pendant que la modale reste ouverte.
    emit('conflict')
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="target"
        ref="modalRef"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        :data-testid="`${testidPrefix}-cancel-modal`"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`${idPrefix}-title`"
        tabindex="-1"
        @click.self="requestClose"
        @keydown.esc="requestClose"
        @keydown.tab="trapModalFocus($event, modalRef)"
      >
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl max-h-[90vh] overflow-y-auto">
          <div class="border-b border-gray-100 px-6 py-4 flex items-start justify-between">
            <div>
              <h3 :id="`${idPrefix}-title`" class="text-lg font-semibold text-gray-900">
                Annuler l'abonnement
              </h3>
              <p class="mt-1 text-sm text-gray-500">
                Cette action est immédiate. L'utilisateur ne pourra plus accéder aux fonctionnalités
                Premium, mais ses photos 3-4 et sa vidéo de casting ne seront PAS supprimées (elles
                deviendront simplement masquées publiquement).
              </p>
            </div>
            <button
              type="button"
              class="text-gray-400 hover:text-gray-600"
              aria-label="Fermer"
              @click="requestClose"
            >
              <X class="h-5 w-5" />
            </button>
          </div>

          <div class="space-y-4 px-6 py-5">
            <div
              v-if="conflictMessage"
              class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
              :data-testid="`${testidPrefix}-cancel-error`"
            >
              {{ conflictMessage }}
            </div>

            <div class="space-y-2">
              <label class="text-sm font-medium text-gray-700" :for="`${idPrefix}-notes`">
                Notes <span class="text-red-500">*</span>
              </label>
              <textarea
                :id="`${idPrefix}-notes`"
                v-model="notes"
                rows="3"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="Raison de l'annulation (5 à 1000 caractères)..."
                data-testid="cancel-notes"
              />
              <p
                v-if="errors.notes?.length"
                class="text-xs text-red-600"
                :data-testid="`${testidPrefix}-cancel-field-error-notes`"
              >
                {{ errors.notes[0] }}
              </p>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
            <button
              type="button"
              class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
              @click="requestClose"
            >
              Retour
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-50"
              :disabled="submitting"
              data-testid="cancel-submit"
              @click="submit"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              Confirmer l'annulation
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
