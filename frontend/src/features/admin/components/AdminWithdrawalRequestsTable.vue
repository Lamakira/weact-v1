<script setup lang="ts">
import { computed, ref } from 'vue'
import { CircleAlert, Loader2, RefreshCw } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import Pagination from '@/components/ui/pagination/Pagination.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import { Textarea } from '@/components/ui/textarea'
import type { WithdrawalRequestEntry } from '../services/adminFinanceApi'
import type { PaginationMeta, WithdrawalRequestStatusFilter } from '../composables/useAdminFinance'

const props = defineProps<{
  requests: WithdrawalRequestEntry[]
  pagination: PaginationMeta | null
  selectedStatus: WithdrawalRequestStatusFilter
  isLoading: boolean
  isSubmitting: boolean
  error: string | null
}>()

const emit = defineEmits<{
  'refresh': []
  'page-change': [page: number]
  'status-change': [status: WithdrawalRequestStatusFilter]
  'approve': [id: string]
  'reject': [id: string, notes: string]
}>()

const approveTarget = ref<WithdrawalRequestEntry | null>(null)
const rejectTarget = ref<WithdrawalRequestEntry | null>(null)
const rejectNotes = ref('')
const rejectFormError = ref<string | null>(null)

const filters: Array<{ value: WithdrawalRequestStatusFilter; label: string }> = [
  { value: 'pending', label: 'En attente' },
  { value: 'all', label: 'Toutes' },
  { value: 'approved', label: 'Approuvées' },
  { value: 'rejected', label: 'Rejetées' },
]

const emptyMessage = computed(() => {
  if (props.selectedStatus === 'pending') {
    return 'Aucune demande en attente.'
  }

  return 'Aucune demande de retrait trouvée.'
})

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

function formatDate(iso: string): string {
  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(iso))
}

function paymentModeLabel(mode: string): string {
  return (
    {
      mtn: 'MTN MoMo',
      moov: 'Moov Money',
      momo_test: 'Test MoMo',
    }[mode] ?? mode
  )
}

function statusLabel(status: WithdrawalRequestEntry['status']): string {
  return (
    {
      pending: 'En attente',
      approved: 'Approuvée',
      rejected: 'Rejetée',
    }[status] ?? status
  )
}

function statusVariant(status: WithdrawalRequestEntry['status']): 'warning' | 'success' | 'destructive' {
  if (status === 'approved') return 'success'
  if (status === 'rejected') return 'destructive'
  return 'warning'
}

function openRejectModal(request: WithdrawalRequestEntry): void {
  rejectTarget.value = request
  rejectNotes.value = request.notes ?? ''
  rejectFormError.value = null
}

function closeRejectModal(): void {
  rejectTarget.value = null
  rejectNotes.value = ''
  rejectFormError.value = null
}

function confirmApprove(): void {
  if (!approveTarget.value) return

  emit('approve', approveTarget.value.id)
  approveTarget.value = null
}

function submitReject(): void {
  const notes = rejectNotes.value.trim()

  if (notes.length === 0) {
    rejectFormError.value = 'Une raison est obligatoire pour rejeter la demande.'
    return
  }

  if (!rejectTarget.value) return

  emit('reject', rejectTarget.value.id, notes)
  closeRejectModal()
}
</script>

<template>
  <div class="rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="border-b border-gray-100 px-6 py-4">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 class="text-base font-semibold text-gray-900">Demandes de retrait</h2>
          <p class="mt-0.5 text-sm text-gray-500">
            Traitez les demandes manuelles et gardez une trace des décisions prises.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            v-for="filter in filters"
            :key="filter.value"
            type="button"
            class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
            :class="
              selectedStatus === filter.value
                ? 'border-[#198496] bg-[#198496]/10 text-[#198496]'
                : 'border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-gray-50'
            "
            @click="emit('status-change', filter.value)"
          >
            {{ filter.label }}
          </button>

          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50"
            :disabled="isLoading"
            @click="emit('refresh')"
          >
            <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isLoading }" />
            Actualiser
          </button>
        </div>
      </div>
    </div>

    <div v-if="error" class="mx-6 mt-6 flex items-center gap-3 rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-700">
      <CircleAlert class="h-5 w-5 shrink-0" />
      {{ error }}
    </div>

    <!-- Le succès d'une approbation/rejet passe par un toast : plus de bandeau
         inline qui décale le tableau au moment où on lit une ligne. -->

    <div v-if="isLoading" class="space-y-3 p-6">
      <div v-for="i in 5" :key="i" class="h-12 rounded-xl bg-gray-100/80" />
    </div>

    <div v-else-if="requests.length === 0" class="px-6 py-12 text-center text-sm text-gray-400">
      {{ emptyMessage }}
    </div>

    <div v-else>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[920px] text-sm">
          <thead>
            <tr class="border-b border-gray-50 bg-gray-50/60">
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Utilisateur</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Montant</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Opérateur</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Numéro</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Soumise le</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Statut</th>
              <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="request in requests" :key="request.id" class="align-top transition-colors hover:bg-gray-50/40">
              <td class="px-6 py-4">
                <div class="font-medium text-gray-900">{{ request.user_name || '—' }}</div>
                <div class="mt-1 text-xs text-gray-500">{{ request.user_email || '—' }}</div>
              </td>
              <td class="whitespace-nowrap px-6 py-4 font-semibold text-gray-900">
                {{ formatCurrency(request.amount) }}
              </td>
              <td class="px-6 py-4 text-gray-600">{{ paymentModeLabel(request.payment_mode) }}</td>
              <td class="px-6 py-4">
                <div class="text-gray-700">{{ request.phone_number }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-gray-400">{{ request.phone_country }}</div>
              </td>
              <td class="whitespace-nowrap px-6 py-4 text-gray-500">{{ formatDate(request.created_at) }}</td>
              <td class="px-6 py-4">
                <Badge :variant="statusVariant(request.status)">
                  {{ statusLabel(request.status) }}
                </Badge>
                <p v-if="request.status === 'rejected' && request.notes" class="mt-2 max-w-xs text-xs leading-relaxed text-gray-500">
                  {{ request.notes }}
                </p>
              </td>
              <td class="px-6 py-4">
                <div v-if="request.status === 'pending'" class="flex justify-end gap-2">
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-green-700 disabled:opacity-50"
                    :disabled="isSubmitting"
                    @click="approveTarget = request"
                  >
                    <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                    Approuver
                  </button>
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-red-700 disabled:opacity-50"
                    :disabled="isSubmitting"
                    @click="openRejectModal(request)"
                  >
                    Rejeter
                  </button>
                </div>
                <div v-else class="text-right text-xs text-gray-400">
                  {{ request.processed_at ? `Traité le ${formatDate(request.processed_at)}` : '—' }}
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination && pagination.last_page > 1" class="border-t border-gray-100 px-6 py-4">
        <Pagination
          :current-page="pagination.current_page"
          :total-pages="pagination.last_page"
          @page-change="emit('page-change', $event)"
        />
      </div>
    </div>
  </div>

  <ConfirmModal
    :is-open="approveTarget !== null"
    title="Approuver la demande"
    message="Le solde sera débité immédiatement et l'utilisateur recevra un email de confirmation."
    confirm-text="Approuver"
    cancel-text="Annuler"
    variant="warning"
    @cancel="approveTarget = null"
    @confirm="confirmApprove"
  />

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
        v-if="rejectTarget"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        @click.self="closeRejectModal"
      >
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
          <div class="border-b border-gray-100 px-6 py-4">
            <h3 class="text-lg font-semibold text-gray-900">Rejeter la demande</h3>
            <p class="mt-1 text-sm text-gray-500">
              Cette note sera envoyée à l'utilisateur par email.
            </p>
          </div>

          <div class="space-y-4 px-6 py-5">
            <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
              {{ rejectTarget.user_name || 'L\'utilisateur' }} a demandé {{ formatCurrency(rejectTarget.amount) }} via
              {{ paymentModeLabel(rejectTarget.payment_mode) }}.
            </div>

            <div class="space-y-2">
              <label class="text-sm font-medium text-gray-700" for="withdrawal-reject-notes">Raison du rejet</label>
              <Textarea
                id="withdrawal-reject-notes"
                v-model="rejectNotes"
                rows="5"
                placeholder="Ex: numéro invalide, informations incohérentes, pièce justificative manquante..."
              />
              <p v-if="rejectFormError" class="text-xs text-red-600">
                {{ rejectFormError }}
              </p>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
            <button
              type="button"
              class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
              @click="closeRejectModal"
            >
              Annuler
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-50"
              :disabled="isSubmitting"
              @click="submitReject"
            >
              <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
              Confirmer le rejet
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
