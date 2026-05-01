<script setup lang="ts">
import { ref } from 'vue'
import { AlertCircle, CheckCircle2, Loader2 } from 'lucide-vue-next'
import { Textarea } from '@/components/ui/textarea'
import Pagination from '@/components/ui/pagination/Pagination.vue'
import type { AdminDispute, DisputeOutcome } from '../services/adminAttendanceDisputesApi'
import type { PaginationMeta } from '../services/adminFinanceApi'

defineProps<{
  disputes: AdminDispute[]
  pagination: PaginationMeta | null
  isLoading: boolean
  isSubmitting: boolean
  error: string | null
  successMessage: string | null
}>()

const emit = defineEmits<{
  'refresh': []
  'page-change': [page: number]
  'resolve': [id: number, outcome: DisputeOutcome, notes: string]
}>()

const resolveTarget = ref<AdminDispute | null>(null)
const resolveOutcome = ref<DisputeOutcome | null>(null)
const resolveNotes = ref('')
const resolveFormError = ref<string | null>(null)

function formatCurrency(amount: number): string {
  return (
    new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'XOF',
      currencyDisplay: 'code',
    })
      .format(amount)
      .replace('XOF', '')
      .trim() + ' XOF'
  )
}

function formatDate(iso: string | null): string {
  if (!iso) return '—'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

function openResolveModal(dispute: AdminDispute, outcome: DisputeOutcome): void {
  resolveTarget.value = dispute
  resolveOutcome.value = outcome
  resolveNotes.value = ''
  resolveFormError.value = null
}

function closeResolveModal(): void {
  resolveTarget.value = null
  resolveOutcome.value = null
  resolveNotes.value = ''
  resolveFormError.value = null
}

function submitResolve(): void {
  const notes = resolveNotes.value.trim()
  if (notes.length < 5) {
    resolveFormError.value = 'Une note d\'au moins 5 caractères est requise.'
    return
  }
  if (!resolveTarget.value || !resolveOutcome.value) return
  emit('resolve', resolveTarget.value.id, resolveOutcome.value, notes)
  closeResolveModal()
}
</script>

<template>
  <div class="space-y-4">
    <!-- Banner: success / error -->
    <div
      v-if="successMessage"
      class="flex items-start gap-3 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
    >
      <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" />
      <span>{{ successMessage }}</span>
    </div>
    <div
      v-if="error"
      class="flex items-start gap-3 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800"
    >
      <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
      <span>{{ error }}</span>
    </div>

    <!-- Loading -->
    <div v-if="isLoading && disputes.length === 0" class="flex items-center justify-center py-12 text-gray-400">
      <Loader2 class="h-6 w-6 animate-spin" />
    </div>

    <!-- Empty state -->
    <div
      v-else-if="disputes.length === 0"
      class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-10 text-center text-sm text-gray-500"
    >
      Aucun litige en attente.
    </div>

    <!-- Table -->
    <div v-else class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
      <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50">
          <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
            <th class="px-4 py-3">Mission</th>
            <th class="px-4 py-3">Face</th>
            <th class="px-4 py-3">Montant</th>
            <th class="px-4 py-3">Notifiée le</th>
            <th class="px-4 py-3">Contestée le</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-sm">
          <tr v-for="dispute in disputes" :key="dispute.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 align-top">
              <div class="font-medium text-gray-900">
                {{ dispute.mission?.titre ?? '—' }}
              </div>
              <div class="text-xs text-gray-500">
                {{ dispute.mission?.producer?.display_name ?? '—' }}
              </div>
            </td>
            <td class="px-4 py-3 align-top">
              <div class="flex items-center gap-2">
                <img
                  v-if="dispute.face?.profile_photo_url"
                  :src="dispute.face.profile_photo_url"
                  :alt="dispute.face.display_name"
                  class="h-8 w-8 rounded-full object-cover"
                  @error="($event.target as HTMLImageElement).style.display = 'none'"
                />
                <span class="font-medium text-gray-900">
                  {{ dispute.face?.display_name ?? '—' }}
                </span>
              </div>
            </td>
            <td class="px-4 py-3 align-top font-semibold text-gray-900">
              {{ formatCurrency(dispute.montant_face_recoit) }}
            </td>
            <td class="px-4 py-3 align-top text-gray-700">
              {{ formatDate(dispute.notified_at) }}
            </td>
            <td class="px-4 py-3 align-top text-gray-700">
              {{ formatDate(dispute.disputed_at) }}
            </td>
            <td class="px-4 py-3 align-top">
              <div class="flex flex-wrap items-center justify-end gap-2">
                <button
                  type="button"
                  class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
                  :disabled="isSubmitting"
                  @click="openResolveModal(dispute, 'face')"
                >
                  Trancher en faveur de la Face
                </button>
                <button
                  type="button"
                  class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-amber-700"
                  :disabled="isSubmitting"
                  @click="openResolveModal(dispute, 'producer')"
                >
                  Trancher en faveur du Producer
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination && pagination.last_page > 1" class="flex justify-center">
      <Pagination
        :current-page="pagination.current_page"
        :total-pages="pagination.last_page"
        @page-change="(page) => emit('page-change', page)"
      />
    </div>

    <!-- Resolve modal -->
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
          v-if="resolveTarget"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
          @click.self="closeResolveModal"
        >
          <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-gray-100 px-6 py-4">
              <h3 class="text-lg font-semibold text-gray-900">
                {{ resolveOutcome === 'face' ? 'Trancher en faveur de la Face' : 'Trancher en faveur du Producer' }}
              </h3>
              <p class="mt-1 text-sm text-gray-500">
                Cette décision est définitive. Une note d'audit sera enregistrée.
              </p>
            </div>

            <div class="space-y-4 px-6 py-5">
              <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Mission : <strong>{{ resolveTarget.mission?.titre ?? '—' }}</strong> ·
                Face : <strong>{{ resolveTarget.face?.display_name ?? '—' }}</strong> ·
                Montant : <strong>{{ formatCurrency(resolveTarget.montant_face_recoit) }}</strong>
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="dispute-resolve-notes">
                  Motif de la décision (min 5 caractères)
                </label>
                <Textarea
                  id="dispute-resolve-notes"
                  v-model="resolveNotes"
                  rows="4"
                  placeholder="Pourquoi tranchez-vous ainsi ? Référez-vous aux preuves fournies (vidéo, message, attestation…)."
                />
                <p v-if="resolveFormError" class="text-xs text-red-600">
                  {{ resolveFormError }}
                </p>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
              <button
                type="button"
                class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                @click="closeResolveModal"
              >
                Annuler
              </button>
              <button
                type="button"
                :class="[
                  'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-50',
                  resolveOutcome === 'face' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-amber-600 hover:bg-amber-700',
                ]"
                :disabled="isSubmitting || resolveNotes.trim().length < 5"
                @click="submitResolve"
              >
                <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                Confirmer
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
