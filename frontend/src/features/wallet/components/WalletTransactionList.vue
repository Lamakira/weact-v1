<script setup lang="ts">
import { ArrowDownLeft, ArrowUpRight, Receipt, Calendar } from 'lucide-vue-next'
import type { WalletTransaction } from '../types/wallet'

interface Props {
  transactions: WalletTransaction[]
  isLoading: boolean
  hasMore: boolean
}

defineProps<Props>()
defineEmits<{ 'load-more': [] }>()

const formatCurrency = (value: number) =>
  new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)

const formatDate = (dateString: string) =>
  new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(dateString))
</script>

<template>
  <div class="mt-8">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-sm font-medium text-gray-900 uppercase tracking-wider">
        Historique des transactions
      </h2>
    </div>

    <div class="bg-white border border-gray-200 rounded-md overflow-hidden">
      <!-- Empty state -->
      <div v-if="transactions.length === 0 && !isLoading" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 mb-4">
          <Receipt class="w-6 h-6 text-gray-400" />
        </div>
        <p class="text-gray-500 text-sm">Aucune transaction pour le moment.</p>
      </div>

      <ul v-else class="divide-y divide-gray-100">
        <li
          v-for="transaction in transactions"
          :key="transaction.id"
          class="p-4 hover:bg-gray-50 transition-colors"
        >
          <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
              <!-- Type icon -->
              <div
                :class="[
                  'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center',
                  transaction.type === 'credit'
                    ? 'bg-green-50 text-green-600'
                    : 'bg-red-50 text-red-600',
                ]"
              >
                <ArrowDownLeft v-if="transaction.type === 'credit'" class="w-5 h-5" />
                <ArrowUpRight v-else class="w-5 h-5" />
              </div>

              <!-- Description + meta -->
              <div>
                <div class="flex items-center gap-2">
                  <p class="text-sm font-medium text-gray-900">{{ transaction.description }}</p>
                  <!-- Withdrawal status badge (only for debits with a status) -->
                  <span
                    v-if="transaction.type === 'debit' && transaction.status"
                    :class="[
                      'inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium',
                      transaction.status === 'completed' ? 'bg-green-50 text-green-700' :
                      transaction.status === 'pending'   ? 'bg-amber-50 text-amber-700' :
                                                           'bg-red-50 text-red-700',
                    ]"
                  >
                    {{ transaction.status === 'completed' ? 'Terminé' : transaction.status === 'pending' ? 'En cours' : 'Échoué' }}
                  </span>
                </div>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-0.5">
                  <span class="text-xs text-gray-400 font-mono">{{ transaction.reference }}</span>
                  <span
                    v-if="transaction.booking_id"
                    class="text-xs text-[#198496]"
                  >
                    Réservation #{{ transaction.booking_id }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Amount + date -->
            <div class="text-right flex-shrink-0">
              <p
                :class="[
                  'text-sm font-semibold',
                  transaction.type === 'credit' ? 'text-green-600' : 'text-gray-900',
                ]"
              >
                {{ transaction.type === 'credit' ? '+' : '-' }}
                {{ formatCurrency(transaction.amount) }}
              </p>
              <p class="text-[11px] text-gray-400 mt-0.5 flex items-center justify-end gap-1">
                <Calendar class="w-3 h-3" />
                {{ formatDate(transaction.created_at) }}
              </p>
            </div>
          </div>
        </li>

        <!-- Loading skeleton rows -->
        <template v-if="isLoading">
          <li v-for="i in 3" :key="`skeleton-${i}`" class="p-4 animate-pulse">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-gray-100 rounded-full"></div>
                <div class="space-y-2">
                  <div class="h-3 w-32 bg-gray-100 rounded"></div>
                  <div class="h-2 w-20 bg-gray-100 rounded"></div>
                </div>
              </div>
              <div class="space-y-2 flex flex-col items-end">
                <div class="h-3 w-24 bg-gray-100 rounded"></div>
                <div class="h-2 w-16 bg-gray-100 rounded"></div>
              </div>
            </div>
          </li>
        </template>
      </ul>

      <!-- Load more -->
      <div v-if="hasMore" class="p-4 border-t border-gray-100 bg-gray-50/50 flex justify-center">
        <button
          :disabled="isLoading"
          class="text-sm font-medium text-[#198496] hover:text-[#146c7a] px-4 py-2 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          @click="$emit('load-more')"
        >
          {{ isLoading ? 'Chargement...' : 'Charger plus' }}
        </button>
      </div>
    </div>
  </div>
</template>
