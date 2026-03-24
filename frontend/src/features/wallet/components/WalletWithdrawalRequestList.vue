<script setup lang="ts">
import { Calendar, CircleAlert, Landmark, Smartphone } from 'lucide-vue-next'
import type { WalletWithdrawalRequest } from '../types/wallet'

interface Props {
  requests: WalletWithdrawalRequest[]
}

defineProps<Props>()

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

const statusLabels: Record<WalletWithdrawalRequest['status'], string> = {
  pending: 'En attente',
  approved: 'Traité',
  rejected: 'Rejeté',
}

const paymentModeLabels: Record<WalletWithdrawalRequest['payment_mode'], string> = {
  mtn: 'MTN Mobile Money',
  moov: 'Moov Money',
  momo_test: 'Mobile Money test',
}
</script>

<template>
  <section class="mt-8" data-testid="wallet-withdrawal-requests">
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h2 class="text-sm font-medium uppercase tracking-wider text-gray-900">
          Mes demandes de retrait
        </h2>
        <p class="mt-1 text-sm text-gray-500">
          Suivez vos retraits en attente ou déjà traités par l’équipe.
        </p>
      </div>
    </div>

    <div class="overflow-hidden rounded-md border border-gray-200 bg-white">
      <div v-if="requests.length === 0" class="p-12 text-center">
        <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-full bg-gray-50">
          <Landmark class="h-6 w-6 text-gray-400" />
        </div>
        <p class="text-sm text-gray-500">Aucune demande de retrait pour le moment.</p>
      </div>

      <ul v-else class="divide-y divide-gray-100">
        <li
          v-for="request in requests"
          :key="request.id"
          class="p-4 transition-colors hover:bg-gray-50"
        >
          <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-semibold text-gray-900">
                  {{ formatCurrency(request.amount) }}
                </p>
                <span
                  :class="[
                    'inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium',
                    request.status === 'approved' ? 'bg-green-50 text-green-700' :
                    request.status === 'pending' ? 'bg-amber-50 text-amber-700' :
                    'bg-red-50 text-red-700',
                  ]"
                >
                  {{ statusLabels[request.status] }}
                </span>
              </div>

              <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
                <span class="inline-flex items-center gap-1.5">
                  <Smartphone class="h-3.5 w-3.5" />
                  {{ paymentModeLabels[request.payment_mode] }} · {{ request.phone_number }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                  <Calendar class="h-3.5 w-3.5" />
                  Demandé le {{ formatDate(request.created_at) }}
                </span>
                <span
                  v-if="request.processed_at"
                  class="inline-flex items-center gap-1.5"
                >
                  <Calendar class="h-3.5 w-3.5" />
                  Traité le {{ formatDate(request.processed_at) }}
                </span>
              </div>

              <div
                v-if="request.status === 'pending'"
                class="flex items-start gap-2 rounded-md border border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-800"
              >
                <CircleAlert class="mt-0.5 h-4 w-4 flex-shrink-0" />
                <p>
                  Votre demande est en attente de traitement. Vous recevrez un email dès qu’elle sera traitée.
                </p>
              </div>

              <div
                v-if="request.status === 'rejected' && request.notes"
                class="rounded-md border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-800"
              >
                Motif du rejet: {{ request.notes }}
              </div>
            </div>

            <div class="flex-shrink-0 text-right">
              <p class="text-xs uppercase tracking-wider text-gray-400">Demande</p>
              <p class="mt-1 font-mono text-xs text-gray-500">#{{ request.id }}</p>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </section>
</template>
