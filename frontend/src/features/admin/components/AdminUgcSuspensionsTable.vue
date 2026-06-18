<script setup lang="ts">
import { computed, ref } from 'vue'
import { AlertCircle, CheckCircle2, Loader2 } from 'lucide-vue-next'
import Pagination from '@/components/ui/pagination/Pagination.vue'
import type { AdminUgcSuspension } from '../services/adminUgcSuspensionsApi'
import type { PaginationMeta } from '../services/adminFinanceApi'

defineProps<{
  suspensions: AdminUgcSuspension[]
  pagination: PaginationMeta | null
  isLoading: boolean
  isActing: boolean
  error: string | null
  successMessage: string | null
}>()

const emit = defineEmits<{
  'refresh': []
  'page-change': [page: number]
  'reactivate': [uuid: string]
  'reject': [uuid: string]
}>()

type Action = 'reactivate' | 'reject'
const target = ref<AdminUgcSuspension | null>(null)
const action = ref<Action | null>(null)

const modalTitle = computed(() =>
  action.value === 'reactivate' ? 'Réactiver le compte Face' : "Rejeter l'appel",
)

function faceName(suspension: AdminUgcSuspension): string {
  if (!suspension.face) return '—'
  return `${suspension.face.prenom} ${suspension.face.nom}`.trim()
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

function openModal(suspension: AdminUgcSuspension, a: Action): void {
  target.value = suspension
  action.value = a
}

function closeModal(): void {
  target.value = null
  action.value = null
}

function confirm(): void {
  if (!target.value || !action.value) return
  if (action.value === 'reactivate') emit('reactivate', target.value.uuid)
  else emit('reject', target.value.uuid)
  closeModal()
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
    <div
      v-if="isLoading && suspensions.length === 0"
      class="flex items-center justify-center py-12 text-gray-400"
    >
      <Loader2 class="h-6 w-6 animate-spin" />
    </div>

    <!-- Empty state -->
    <div
      v-else-if="suspensions.length === 0"
      class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-10 text-center text-sm text-gray-500"
    >
      Aucun appel en attente.
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto rounded-2xl border border-gray-100 bg-white shadow-sm">
      <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50">
          <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
            <th class="px-4 py-3">Face</th>
            <th class="px-4 py-3">Deal</th>
            <th class="px-4 py-3">Raison</th>
            <th class="px-4 py-3">Suspendue le</th>
            <th class="px-4 py-3">Appel</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-sm">
          <tr
            v-for="suspension in suspensions"
            :key="suspension.uuid"
            class="hover:bg-gray-50"
            data-testid="ugc-suspension-row"
          >
            <td class="px-4 py-3 align-top font-medium text-gray-900">
              {{ faceName(suspension) }}
            </td>
            <td class="px-4 py-3 align-top">
              <div class="text-gray-900">{{ suspension.deal?.product_name || '—' }}</div>
              <div v-if="suspension.deal" class="text-xs text-gray-500">
                {{ suspension.deal.owner_kind }}
              </div>
            </td>
            <td class="px-4 py-3 align-top text-gray-700">
              {{ suspension.reason_label }}
            </td>
            <td class="px-4 py-3 align-top text-gray-700">
              {{ formatDate(suspension.suspended_at) }}
            </td>
            <td class="px-4 py-3 align-top text-gray-700">
              {{ suspension.appeal_status_label }}
            </td>
            <td class="px-4 py-3 align-top">
              <div class="flex flex-wrap items-center justify-end gap-2">
                <button
                  type="button"
                  class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                  :disabled="isActing"
                  data-testid="ugc-suspension-reactivate-btn"
                  @click="openModal(suspension, 'reactivate')"
                >
                  Réactiver
                </button>
                <button
                  type="button"
                  class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-red-700 disabled:opacity-50"
                  :disabled="isActing"
                  data-testid="ugc-suspension-reject-btn"
                  @click="openModal(suspension, 'reject')"
                >
                  Rejeter l'appel
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

    <!-- Confirmation modal (SANS notes — D-5.4.e) -->
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
          v-if="target && action"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
          @click.self="closeModal"
        >
          <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-gray-100 px-6 py-4">
              <h3 class="text-lg font-semibold text-gray-900">{{ modalTitle }}</h3>
              <p class="mt-1 text-sm text-gray-500">
                {{
                  action === 'reactivate'
                    ? 'La Face pourra de nouveau accéder à l\'UGC. Décision définitive.'
                    : 'La Face reste suspendue. Décision définitive.'
                }}
              </p>
            </div>

            <div class="space-y-4 px-6 py-5">
              <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Face : <strong>{{ faceName(target) }}</strong> ·
                Produit : <strong>{{ target.deal?.product_name || '—' }}</strong> ·
                Raison : <strong>{{ target.reason_label }}</strong>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
              <button
                type="button"
                class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                @click="closeModal"
              >
                Annuler
              </button>
              <button
                type="button"
                :class="[
                  'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-50',
                  action === 'reactivate' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700',
                ]"
                :disabled="isActing"
                data-testid="ugc-suspension-confirm-btn"
                @click="confirm"
              >
                <Loader2 v-if="isActing" class="h-4 w-4 animate-spin" />
                Confirmer
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
