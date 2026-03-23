<script setup lang="ts">
import { onMounted } from 'vue'
import {
  RefreshCw,
  Wallet,
  TrendingUp,
  ArrowDownLeft,
  Users,
  Lock,
  BarChart3,
  AlertCircle,
} from 'lucide-vue-next'
import { useAdminFinance } from '@/features/admin/composables/useAdminFinance'
import { Skeleton } from '@/components/ui/skeleton'

const { overview, withdrawals, isLoadingOverview, isLoadingWithdrawals, error, fetchOverview, fetchWithdrawals } =
  useAdminFinance()

onMounted(() => {
  fetchOverview()
  fetchWithdrawals()
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

function handleRefresh(): void {
  fetchOverview()
  fetchWithdrawals()
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Vue financière</h1>
        <p class="mt-1 text-sm text-gray-500">Suivi en temps réel des flux financiers de la plateforme</p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50"
        :disabled="isLoadingOverview"
        @click="handleRefresh"
      >
        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isLoadingOverview }" />
        Actualiser
      </button>
    </div>

    <!-- Error -->
    <div
      v-if="error"
      class="flex items-center gap-3 rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-700"
    >
      <AlertCircle class="h-5 w-5 shrink-0" />
      {{ error }}
    </div>

    <!-- Main 3 Financial Cards -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

      <!-- Rétractable -->
      <div class="rounded-2xl border border-green-100 bg-gradient-to-br from-green-50 to-white p-6 shadow-sm">
        <div class="flex items-start justify-between">
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-100">
            <Wallet class="h-6 w-6 text-green-600" />
          </div>
          <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">Disponible</span>
        </div>
        <div class="mt-4">
          <Skeleton v-if="isLoadingOverview" class="h-8 w-40" />
          <p v-else class="text-2xl font-bold text-gray-900">
            {{ formatCurrency(overview?.retirable.amount ?? 0) }}
          </p>
          <p class="mt-1 text-sm text-gray-500">Soldes wallets des Faces</p>
          <p class="mt-3 text-xs text-gray-400">Montant que les Faces peuvent retirer immédiatement</p>
        </div>
      </div>

      <!-- Appartient aux Faces -->
      <div class="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-white p-6 shadow-sm">
        <div class="flex items-start justify-between">
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100">
            <Users class="h-6 w-6 text-blue-600" />
          </div>
          <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">Faces</span>
        </div>
        <div class="mt-4">
          <Skeleton v-if="isLoadingOverview" class="h-8 w-40" />
          <p v-else class="text-2xl font-bold text-gray-900">
            {{ formatCurrency(overview?.faces.amount ?? 0) }}
          </p>
          <p class="mt-1 text-sm text-gray-500">Total appartenant aux Faces</p>
          <!-- Breakdown -->
          <div v-if="overview && !isLoadingOverview" class="mt-3 space-y-1.5">
            <div class="flex items-center justify-between text-xs">
              <span class="flex items-center gap-1 text-gray-500"><Wallet class="h-3 w-3" /> Wallets</span>
              <span class="font-medium text-gray-700">{{ formatCurrency(overview.faces.wallet_balance) }}</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="flex items-center gap-1 text-gray-500"><Lock class="h-3 w-3" /> Escrow bookings</span>
              <span class="font-medium text-gray-700">{{ formatCurrency(overview.faces.escrow_booking) }}</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="flex items-center gap-1 text-gray-500"><Lock class="h-3 w-3" /> Escrow missions</span>
              <span class="font-medium text-gray-700">{{ formatCurrency(overview.faces.escrow_mission) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Revenus plateforme -->
      <div class="rounded-2xl border border-violet-100 bg-gradient-to-br from-violet-50 to-white p-6 shadow-sm">
        <div class="flex items-start justify-between">
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-100">
            <TrendingUp class="h-6 w-6 text-violet-600" />
          </div>
          <span class="rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-medium text-violet-700">Revenus</span>
        </div>
        <div class="mt-4">
          <Skeleton v-if="isLoadingOverview" class="h-8 w-40" />
          <p v-else class="text-2xl font-bold text-gray-900">
            {{ formatCurrency(overview?.platform.amount ?? 0) }}
          </p>
          <p class="mt-1 text-sm text-gray-500">Commissions encaissées</p>
          <!-- Breakdown -->
          <div v-if="overview && !isLoadingOverview" class="mt-3 space-y-1.5">
            <div class="flex items-center justify-between text-xs">
              <span class="text-gray-500">Via bookings</span>
              <span class="font-medium text-gray-700">{{ formatCurrency(overview.platform.from_bookings) }}</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="text-gray-500">Via missions</span>
              <span class="font-medium text-gray-700">{{ formatCurrency(overview.platform.from_missions) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Secondary Stats -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

      <!-- Volume total -->
      <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50">
            <BarChart3 class="h-5 w-5 text-amber-500" />
          </div>
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wider">Volume total traité</p>
            <Skeleton v-if="isLoadingOverview" class="mt-1 h-6 w-32" />
            <p v-else class="text-lg font-bold text-gray-900">{{ formatCurrency(overview?.volume.total ?? 0) }}</p>
          </div>
        </div>
        <div v-if="overview && !isLoadingOverview" class="mt-3 flex gap-6 border-t border-gray-50 pt-3">
          <div class="text-xs">
            <span class="text-gray-400">Bookings</span>
            <p class="font-semibold text-gray-700">{{ formatCurrency(overview.volume.bookings) }}</p>
          </div>
          <div class="text-xs">
            <span class="text-gray-400">Missions</span>
            <p class="font-semibold text-gray-700">{{ formatCurrency(overview.volume.missions) }}</p>
          </div>
        </div>
      </div>

      <!-- Retraits -->
      <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-50">
            <ArrowDownLeft class="h-5 w-5 text-orange-500" />
          </div>
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wider">Retraits effectués</p>
            <Skeleton v-if="isLoadingOverview" class="mt-1 h-6 w-32" />
            <p v-else class="text-lg font-bold text-gray-900">{{ formatCurrency(overview?.withdrawals.total_amount ?? 0) }}</p>
          </div>
        </div>
        <div v-if="overview && !isLoadingOverview" class="mt-3 border-t border-gray-50 pt-3">
          <p class="text-xs text-gray-500">
            <span class="font-semibold text-gray-700">{{ overview.withdrawals.count }}</span>
            retrait{{ overview.withdrawals.count > 1 ? 's' : '' }} complété{{ overview.withdrawals.count > 1 ? 's' : '' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Recent Withdrawals Table -->
    <div class="rounded-2xl border border-gray-100 bg-white shadow-sm">
      <div class="border-b border-gray-100 px-6 py-4">
        <h2 class="text-base font-semibold text-gray-900">Retraits récents</h2>
        <p class="mt-0.5 text-sm text-gray-500">Les 20 derniers retraits complétés</p>
      </div>

      <!-- Loading -->
      <div v-if="isLoadingWithdrawals" class="space-y-3 p-6">
        <Skeleton v-for="i in 5" :key="i" class="h-10 w-full" />
      </div>

      <!-- Empty -->
      <div v-else-if="withdrawals.length === 0" class="px-6 py-12 text-center text-sm text-gray-400">
        Aucun retrait enregistré.
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-50 bg-gray-50/50">
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Face</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
              <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Montant</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr
              v-for="tx in withdrawals"
              :key="tx.id"
              class="transition-colors hover:bg-gray-50/50"
            >
              <td class="whitespace-nowrap px-6 py-3 text-gray-500">{{ formatDate(tx.created_at) }}</td>
              <td class="px-6 py-3 text-gray-700">{{ tx.user_email ?? '—' }}</td>
              <td class="px-6 py-3 text-gray-500">{{ tx.description }}</td>
              <td class="whitespace-nowrap px-6 py-3 text-right font-semibold text-orange-600">
                − {{ formatCurrency(tx.amount) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
