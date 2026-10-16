<script setup lang="ts">
/**
 * MissionAbsenceContestBlock
 *
 * État « Le Producteur vous a déclarée absente » + action « Contester » (fenêtre 72 h,
 * échéance calculée par le backend : `attendance_dispute.deadline`). Monté sur la
 * carte candidature (liste) et sur la page détail mission.
 * Passé l'échéance ou une fois tranché, le backend n'envoie plus `attendance_dispute`.
 */
import { computed, ref } from 'vue'
import { AlertTriangle, CheckCircle, Loader2 } from 'lucide-vue-next'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
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

const showModal = ref(false)
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

async function handleConfirm(): Promise<void> {
  const ok = await contestAbsence(props.missionId)
  showModal.value = false

  if (ok) {
    justContested.value = true
    toast.success('Contestation envoyée.')
    emit('contested')
  } else {
    toast.error(error.value || 'Impossible d\'envoyer votre contestation.')
  }
  reset()
}
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
      @click.stop.prevent="showModal = true"
    >
      <Loader2 v-if="isContesting" class="h-4 w-4 animate-spin" />
      Contester
    </button>
  </div>

  <ConfirmModal
    :is-open="showModal"
    title="Contester cette absence ?"
    message="Vous affirmez avoir été présente à cette mission. Un administrateur étudiera votre contestation et tranchera entre vous et le Producteur."
    confirm-text="Oui, contester"
    cancel-text="Retour"
    variant="warning"
    :confirm-disabled="isContesting"
    @confirm="handleConfirm"
    @cancel="showModal = false"
  />
</template>
