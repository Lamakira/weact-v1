<script setup lang="ts">
import { onMounted } from 'vue'
import { RefreshCw, Scale } from 'lucide-vue-next'
import { useAdminAttendanceDisputes } from '@/features/admin/composables/useAdminAttendanceDisputes'
import AdminAttendanceDisputesTable from '@/features/admin/components/AdminAttendanceDisputesTable.vue'
import { useToast } from '@/composables/useToast'
import type { DisputeOutcome } from '@/features/admin/services/adminAttendanceDisputesApi'

const {
  disputes,
  pagination,
  currentPage,
  isLoading,
  isResolving,
  error,
  resolveError,
  resolveSuccess,
  fetchDisputes,
  resolveDispute,
} = useAdminAttendanceDisputes()

const toast = useToast()

onMounted(() => {
  fetchDisputes()
})

async function handleResolve(id: number, outcome: DisputeOutcome, notes: string): Promise<void> {
  const success = await resolveDispute(id, outcome, notes)
  if (success) {
    toast.success(resolveSuccess.value ?? 'Litige résolu avec succès')
    if (resolveError.value) {
      toast.error(resolveError.value)
    }
  } else {
    toast.error(resolveError.value ?? 'Erreur lors de la résolution.')
  }
}

function handleRefresh(): void {
  fetchDisputes(currentPage.value)
}

function handlePageChange(page: number): void {
  fetchDisputes(page)
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50">
          <Scale class="h-5 w-5 text-amber-600" />
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Litiges présence</h1>
          <p class="mt-1 text-sm text-gray-500">Arbitrage des contestations Face</p>
        </div>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50"
        :disabled="isLoading"
        @click="handleRefresh"
      >
        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isLoading }" />
        Actualiser
      </button>
    </div>

    <AdminAttendanceDisputesTable
      :disputes="disputes"
      :pagination="pagination"
      :is-loading="isLoading"
      :is-submitting="isResolving"
      :error="error"
      :success-message="resolveSuccess"
      @refresh="handleRefresh"
      @page-change="handlePageChange"
      @resolve="handleResolve"
    />
  </div>
</template>
