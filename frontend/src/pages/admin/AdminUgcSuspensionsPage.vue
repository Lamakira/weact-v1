<script setup lang="ts">
import { onMounted } from 'vue'
import { RefreshCw, Ban } from 'lucide-vue-next'
import { useAdminUgcSuspensions } from '@/features/admin/composables/useAdminUgcSuspensions'
import AdminUgcSuspensionsTable from '@/features/admin/components/AdminUgcSuspensionsTable.vue'
import { useToast } from '@/composables/useToast'

const {
  suspensions,
  pagination,
  currentPage,
  isLoading,
  isActing,
  error,
  actionError,
  actionSuccess,
  fetchSuspensions,
  reactivate,
  rejectAppeal,
} = useAdminUgcSuspensions()

const toast = useToast()

onMounted(() => {
  fetchSuspensions()
})

async function handleReactivate(uuid: string): Promise<void> {
  const ok = await reactivate(uuid)
  if (ok) {
    toast.success(actionSuccess.value ?? 'Compte réactivé.')
    if (actionError.value) toast.error(actionError.value)
  } else {
    toast.error(actionError.value ?? 'Erreur lors de la réactivation.')
  }
}

async function handleReject(uuid: string): Promise<void> {
  const ok = await rejectAppeal(uuid)
  if (ok) {
    toast.success(actionSuccess.value ?? 'Appel rejeté.')
    if (actionError.value) toast.error(actionError.value)
  } else {
    toast.error(actionError.value ?? "Erreur lors du rejet de l'appel.")
  }
}

function handleRefresh(): void {
  fetchSuspensions(currentPage.value)
}

function handlePageChange(page: number): void {
  fetchSuspensions(page)
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-50">
          <Ban class="h-5 w-5 text-red-600" />
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Suspensions UGC</h1>
          <p class="mt-1 text-sm text-gray-500">Appels en attente de revue</p>
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

    <AdminUgcSuspensionsTable
      :suspensions="suspensions"
      :pagination="pagination"
      :is-loading="isLoading"
      :is-acting="isActing"
      :error="error"
      :success-message="actionSuccess"
      @refresh="handleRefresh"
      @page-change="handlePageChange"
      @reactivate="handleReactivate"
      @reject="handleReject"
    />
  </div>
</template>
