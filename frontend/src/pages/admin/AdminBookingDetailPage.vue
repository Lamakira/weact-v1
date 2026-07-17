<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, AlertCircle, Loader2, Mail } from 'lucide-vue-next'
import { useAdminBookings } from '@/features/admin/composables/useAdminBookings'
import { getBookingStatusClass, formatBookingAmount } from '@/features/admin/utils/bookingDisplay'

const route = useRoute()
const router = useRouter()
const { booking, isLoading, error, fetchBooking } = useAdminBookings()

const bookingId = computed(() => route.params.id as string)

onMounted(() => {
  if (!bookingId.value) {
    router.replace({ name: 'admin-bookings-list' })
    return
  }
  fetchBooking(bookingId.value)
})

function formatDateTime(dateString: string | null): string {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Back Link -->
    <router-link
      :to="{ name: 'admin-bookings-list' }"
      class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-900 transition-colors"
    >
      <ArrowLeft class="h-4 w-4" />
      Retour à la liste
    </router-link>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-12">
      <Loader2 class="h-8 w-8 text-primary-500 animate-spin" />
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="rounded-lg bg-red-50 border border-red-200 p-4 flex items-start gap-3"
      role="alert"
    >
      <AlertCircle class="h-5 w-5 text-red-500 mt-0.5 shrink-0" />
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Booking Detail -->
    <template v-else-if="booking">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Réservation</h1>
          <p class="mt-1 text-sm text-gray-500">
            #{{ booking.id }} — Créée le {{ formatDateTime(booking.created_at) }}
          </p>
        </div>
        <span
          class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium shrink-0"
          :class="getBookingStatusClass(booking.status)"
        >
          {{ booking.status_label }}
        </span>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Main Info -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Parties -->
          <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Parties</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div>
                <p class="text-xs font-medium uppercase tracking-wider text-gray-400 mb-1">Face</p>
                <p class="text-sm font-medium text-gray-900">{{ booking.face?.name ?? '—' }}</p>
                <p v-if="booking.face?.email" class="mt-0.5 flex items-center gap-1.5 text-xs text-gray-500">
                  <Mail class="h-3.5 w-3.5" />
                  {{ booking.face.email }}
                </p>
              </div>
              <div>
                <p class="text-xs font-medium uppercase tracking-wider text-gray-400 mb-1">Producteur</p>
                <p class="text-sm font-medium text-gray-900">{{ booking.producer?.name ?? '—' }}</p>
                <p v-if="booking.producer?.email" class="mt-0.5 flex items-center gap-1.5 text-xs text-gray-500">
                  <Mail class="h-3.5 w-3.5" />
                  {{ booking.producer.email }}
                </p>
              </div>
            </div>
          </div>

          <!-- Booking Details -->
          <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Détails</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
              <div>
                <dt class="text-sm font-medium text-gray-500">Type de contenu</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ booking.type_contenu ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500">Compensation</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ booking.type_compensation_label ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500">Lieu</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ booking.lieu ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500">Durée</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ booking.duree_heures ? `${booking.duree_heures} h` : '—' }}</dd>
              </div>
              <div v-if="booking.nom_produit">
                <dt class="text-sm font-medium text-gray-500">Produit</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ booking.nom_produit }}</dd>
              </div>
              <div v-if="booking.nombre_videos">
                <dt class="text-sm font-medium text-gray-500">Nombre de vidéos</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ booking.nombre_videos }}</dd>
              </div>
            </dl>
            <div v-if="booking.message" class="mt-4 border-t border-gray-100 pt-4">
              <dt class="text-sm font-medium text-gray-500">Message</dt>
              <dd class="mt-1 text-sm text-gray-700 whitespace-pre-line">{{ booking.message }}</dd>
            </div>
          </div>

          <!-- Cancellation (when applicable) -->
          <div
            v-if="booking.cancellation_reason || booking.custom_cancellation_reason"
            class="rounded-xl border border-red-200 bg-red-50 p-6"
          >
            <h2 class="text-lg font-semibold text-red-900 mb-2">Annulation</h2>
            <p class="text-sm text-red-800">{{ booking.cancellation_reason ?? '—' }}</p>
            <p v-if="booking.custom_cancellation_reason" class="mt-1 text-sm text-red-700">
              {{ booking.custom_cancellation_reason }}
            </p>
          </div>
        </div>

        <!-- Right Column: Money + Escrow + Timeline -->
        <div class="space-y-6">
          <!-- Money Card -->
          <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Montants</h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-500">Tarif de base</span>
                <span class="text-gray-900">{{ formatBookingAmount(booking.tarif_base) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Total producteur</span>
                <span class="font-semibold text-gray-900">{{ formatBookingAmount(booking.montant_total_producteur) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Net Face</span>
                <span class="text-gray-900">{{ formatBookingAmount(booking.montant_face_recoit) }}</span>
              </div>
              <div v-if="booking.commission_ugc" class="flex justify-between">
                <span class="text-gray-500">Commission UGC</span>
                <span class="text-gray-900">{{ formatBookingAmount(booking.commission_ugc) }}</span>
              </div>
              <div v-if="booking.payment_mode" class="flex justify-between border-t border-gray-100 pt-2">
                <span class="text-gray-500">Mode de paiement</span>
                <span class="text-gray-900">{{ booking.payment_mode }}</span>
              </div>
              <div v-if="booking.fedapay_transaction_id" class="flex justify-between">
                <span class="text-gray-500">ID FedaPay</span>
                <span class="text-gray-900 font-mono text-xs">{{ booking.fedapay_transaction_id }}</span>
              </div>
            </div>
          </div>

          <!-- Escrow Card -->
          <div v-if="booking.escrow" class="rounded-xl border border-gray-200 bg-white p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Escrow</h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-500">Statut</span>
                <span class="text-gray-900">{{ booking.escrow.status }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Montant</span>
                <span class="text-gray-900">{{ formatBookingAmount(booking.escrow.amount) }}</span>
              </div>
              <div v-if="booking.escrow.locked_at" class="flex justify-between">
                <span class="text-gray-500">Bloqué le</span>
                <span class="text-gray-900">{{ formatDateTime(booking.escrow.locked_at) }}</span>
              </div>
              <div v-if="booking.escrow.released_at" class="flex justify-between">
                <span class="text-gray-500">Libéré le</span>
                <span class="text-gray-900">{{ formatDateTime(booking.escrow.released_at) }}</span>
              </div>
              <div v-if="booking.escrow.refunded_at" class="flex justify-between">
                <span class="text-gray-500">Remboursé le</span>
                <span class="text-gray-900">{{ formatDateTime(booking.escrow.refunded_at) }}</span>
              </div>
            </div>
          </div>

          <!-- Timeline Card -->
          <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Chronologie</h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-500">Début</span>
                <span class="text-gray-900">{{ formatDateTime(booking.date_debut) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Fin</span>
                <span class="text-gray-900">{{ formatDateTime(booking.date_fin) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Acceptée le</span>
                <span class="text-gray-900">{{ formatDateTime(booking.accepted_at) }}</span>
              </div>
              <div class="flex justify-between border-t border-gray-100 pt-2">
                <span class="text-gray-500">Mise à jour</span>
                <span class="text-gray-900">{{ formatDateTime(booking.updated_at) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
