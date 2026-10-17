<script setup lang="ts">
/**
 * MissionAbsenceContestBlock
 *
 * État « Le Producteur vous a déclarée absente » + action « Contester » (fenêtre 72 h,
 * échéance calculée par le backend : `attendance_dispute.deadline`). Monté sur la
 * carte candidature (liste) et sur la page détail mission.
 * Passé l'échéance ou une fois tranché, le backend n'envoie plus `attendance_dispute`.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { AlertTriangle, CheckCircle, Loader2 } from 'lucide-vue-next'
import { Textarea } from '@/components/ui/textarea'
import { useToast } from '@/composables/useToast'
import { useContestMissionAbsence } from '../composables/useContestMissionAbsence'
import type { AttendanceDispute } from '../types'

const props = defineProps<{
  dispute: AttendanceDispute
  missionId: string
}>()

const emit = defineEmits<{
  contested: []
}>()

const toast = useToast()
const { isContesting, error, contestAbsence, reset } = useContestMissionAbsence()

const MESSAGE_MIN = 10
const MESSAGE_MAX = 1000

const showModal = ref(false)
const message = ref('')
// Succès local : l'état reste cohérent même avant le rafraîchissement parent.
const justContested = ref(false)

const isContested = computed(() => justContested.value || props.dispute.state === 'contested')

const formattedDeadline = computed(() => {
  if (!props.dispute.deadline) return ''
  const date = new Date(props.dispute.deadline)
  if (isNaN(date.getTime())) return ''
  return new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
})

const trimmedLength = computed(() => message.value.trim().length)
const canSubmit = computed(
  () => trimmedLength.value >= MESSAGE_MIN && message.value.length <= MESSAGE_MAX && !isContesting.value,
)

function openDialog(): void {
  message.value = ''
  reset()
  showModal.value = true
}

function closeDialog(): void {
  if (isContesting.value) return
  showModal.value = false
}

async function handleSubmit(): Promise<void> {
  if (!canSubmit.value) return

  const ok = await contestAbsence(props.missionId, message.value.trim())

  if (ok) {
    showModal.value = false
    justContested.value = true
    toast.success('Contestation envoyée.')
    emit('contested')
    reset()
  }
  // Échec : le dialogue reste ouvert et affiche l'erreur serveur (message saisi conservé).
}

function handleKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape' && showModal.value) closeDialog()
}

onMounted(() => document.addEventListener('keydown', handleKeydown))
onUnmounted(() => document.removeEventListener('keydown', handleKeydown))
</script>

<template>
  <div
    v-if="isContested"
    class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700"
    data-testid="absence-contested"
  >
    <CheckCircle class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
    <p class="text-sm font-medium">
      Contestation envoyée — un administrateur va trancher.
    </p>
  </div>

  <div
    v-else
    class="space-y-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800"
    data-testid="absence-contestable"
  >
    <div class="flex items-start gap-3">
      <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
      <p class="text-sm font-medium">
        Le Producteur vous a déclarée absente — vous avez jusqu'au {{ formattedDeadline }} pour contester.
      </p>
    </div>
    <button
      v-if="dispute.can_contest"
      type="button"
      data-testid="absence-contest-btn"
      class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
      :disabled="isContesting"
      @click.stop.prevent="openDialog"
    >
      <Loader2 v-if="isContesting" class="h-4 w-4 animate-spin" />
      Contester
    </button>
  </div>

  <Teleport to="body">
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
      data-testid="absence-contest-dialog"
      @click.self="closeDialog"
    >
      <div
        class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="absence-contest-title"
      >
        <h3 id="absence-contest-title" class="text-lg font-semibold text-gray-900">
          Contester cette absence ?
        </h3>
        <p class="mt-1 text-sm text-gray-500">
          Vous affirmez avoir été présente à cette mission. Un administrateur étudiera votre contestation et tranchera entre vous et le Producteur.
        </p>

        <div class="mt-4 space-y-2">
          <label class="text-sm font-medium text-gray-700" for="absence-contest-message">
            Expliquez ce qui s'est passé
          </label>
          <Textarea
            id="absence-contest-message"
            v-model="message"
            rows="5"
            :maxlength="MESSAGE_MAX"
            :disabled="isContesting"
            placeholder="Ex. : J'étais sur le lieu du tournage à l'heure convenue, voici ce qui s'est passé…"
            data-testid="absence-contest-message"
          />
          <div class="flex items-start justify-between gap-3 text-xs">
            <p class="text-gray-500">{{ MESSAGE_MIN }} caractères minimum.</p>
            <p
              class="shrink-0 tabular-nums text-gray-500"
              data-testid="absence-contest-counter"
            >
              {{ message.length }}/{{ MESSAGE_MAX }}
            </p>
          </div>
          <p
            v-if="error"
            class="text-sm text-red-600"
            role="alert"
            data-testid="absence-contest-error"
          >
            {{ error }}
          </p>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            data-testid="absence-contest-cancel"
            class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 disabled:opacity-50"
            :disabled="isContesting"
            @click="closeDialog"
          >
            Retour
          </button>
          <button
            type="button"
            data-testid="absence-contest-submit"
            class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!canSubmit"
            @click="handleSubmit"
          >
            <Loader2 v-if="isContesting" class="h-4 w-4 animate-spin" />
            Envoyer ma contestation
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
