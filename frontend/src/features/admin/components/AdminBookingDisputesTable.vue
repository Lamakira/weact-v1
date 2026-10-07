<script setup lang="ts">
import { ref } from 'vue'
import { AlertCircle, Loader2 } from 'lucide-vue-next'
import { Textarea } from '@/components/ui/textarea'
import type {
  AdminBookingDispute,
  AdminStalePaidBooking,
  BookingDisputeOutcome,
} from '../services/adminBookingDisputesApi'

const props = defineProps<{
  disputes: AdminBookingDispute[]
  stalePaid: AdminStalePaidBooking[]
  isLoading: boolean
  isSubmitting: boolean
  error: string | null
  // Erreur de la dernière résolution, affichée dans la modale (qui reste ouverte avec les notes)
  resolveError?: string | null
  // La modale reste ouverte jusqu'à la fin de la requête ; true = résolu, false = échec (notes conservées)
  resolveHandler: (id: string, outcome: BookingDisputeOutcome, notes: string) => Promise<boolean>
}>()

const resolveTarget = ref<AdminBookingDispute | null>(null)
const resolveOutcome = ref<BookingDisputeOutcome | null>(null)
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

function formatDate(iso: string | null, withTime = true): string {
  if (!iso) return '—'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  }).format(date)
}

function kindLabel(status: string): string {
  return status === 'no_show' ? 'Absence signalée' : 'Annulation tardive'
}

function openResolveModal(dispute: AdminBookingDispute, outcome: BookingDisputeOutcome): void {
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

async function submitResolve(): Promise<void> {
  const notes = resolveNotes.value.trim()
  if (notes.length < 5) {
    resolveFormError.value = 'Une note d\'au moins 5 caractères est requise.'
    return
  }
  if (!resolveTarget.value || !resolveOutcome.value || props.isSubmitting) return

  resolveFormError.value = null
  const ok = await props.resolveHandler(resolveTarget.value.id, resolveOutcome.value, notes)
  if (ok) {
    closeResolveModal()
  } else {
    // Échec (ex. 422) : la modale reste ouverte, notes conservées.
    resolveFormError.value = props.resolveError ?? 'Impossible de résoudre le litige.'
  }
}

function autoPaymentLabel(booking: AdminStalePaidBooking): string {
  if (booking.is_legacy) return 'Ancien booking : jamais payé automatiquement'
  if (booking.auto_complete_due_at) {
    return `Paiement automatique prévu le ${formatDate(booking.auto_complete_due_at, false)}`
  }
  return 'Relance pas encore envoyée'
}
</script>

<template>
  <div class="space-y-8">
    <div
      v-if="error"
      class="flex items-start gap-3 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800"
    >
      <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
      <span>{{ error }}</span>
    </div>

    <!-- Litiges à trancher -->
    <section class="space-y-3" data-testid="booking-disputes-section">
      <h2 class="text-lg font-semibold text-gray-900">Litiges à trancher</h2>

      <div v-if="isLoading && disputes.length === 0" class="flex items-center justify-center py-12 text-gray-400">
        <Loader2 class="h-6 w-6 animate-spin" />
      </div>

      <div
        v-else-if="disputes.length === 0 && !error"
        class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-10 text-center text-sm text-gray-500"
      >
        Aucun litige en attente.
      </div>

      <div v-else-if="disputes.length > 0" class="overflow-x-auto rounded-2xl border border-gray-100 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-100">
          <thead class="bg-gray-50">
            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
              <th class="px-4 py-3">Booking</th>
              <th class="px-4 py-3">Montants</th>
              <th class="px-4 py-3">Signalé le</th>
              <th class="px-4 py-3">Contestation</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-sm" data-testid="booking-disputes-rows">
            <tr v-for="dispute in disputes" :key="dispute.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 align-top">
                <div class="font-medium text-gray-900">{{ kindLabel(dispute.status) }}</div>
                <div class="text-xs text-gray-500">
                  Producteur : {{ dispute.producer.display_name }} · Face : {{ dispute.face.display_name }}
                </div>
                <div class="text-xs text-gray-500">Tournage : {{ formatDate(dispute.date_debut, false) }}</div>
                <div v-if="dispute.cancellation_reason" class="text-xs text-gray-500">
                  Raison : {{ dispute.cancellation_reason }}
                </div>
                <RouterLink
                  :to="{ name: 'admin-booking-detail', params: { id: dispute.id } }"
                  class="text-xs font-medium text-[#198496] hover:underline"
                  data-testid="dispute-booking-link"
                >
                  Voir la réservation
                </RouterLink>
              </td>
              <td class="px-4 py-3 align-top text-gray-700">
                <div>Producteur : {{ formatCurrency(dispute.montant_total_producteur) }}</div>
                <div>Face : {{ formatCurrency(dispute.montant_face_recoit) }}</div>
              </td>
              <td class="px-4 py-3 align-top text-gray-700">{{ formatDate(dispute.reported_at) }}</td>
              <td class="px-4 py-3 align-top text-gray-700">
                <div class="text-xs text-gray-500">{{ formatDate(dispute.disputed_at) }}</div>
                <p class="mt-1 max-w-xs whitespace-pre-line">{{ dispute.dispute_message ?? '—' }}</p>
              </td>
              <td class="px-4 py-3 align-top">
                <div class="flex flex-wrap items-center justify-end gap-2">
                  <button
                    type="button"
                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
                    :disabled="isSubmitting"
                    data-testid="resolve-favor-face"
                    @click="openResolveModal(dispute, 'favor_face')"
                  >
                    Trancher en faveur de la Face
                  </button>
                  <button
                    type="button"
                    class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-amber-700"
                    :disabled="isSubmitting"
                    data-testid="resolve-favor-producer"
                    @click="openResolveModal(dispute, 'favor_producer')"
                  >
                    Trancher en faveur du Producteur
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Bookings payés sans suite (lecture seule) -->
    <section class="space-y-3" data-testid="stale-paid-section">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Bookings payés sans suite</h2>
        <p class="text-sm text-gray-500">
          Lecture seule. Les bookings dont la fin remonte à plus de 30 jours ne sont jamais payés automatiquement.
        </p>
      </div>

      <div
        v-if="isLoading && stalePaid.length === 0"
        class="flex items-center justify-center py-8 text-gray-400"
        data-testid="stale-paid-loading"
      >
        <Loader2 class="h-6 w-6 animate-spin" />
      </div>

      <div
        v-else-if="stalePaid.length === 0 && !error"
        class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-8 text-center text-sm text-gray-500"
      >
        Aucun booking payé sans suite.
      </div>

      <div v-else-if="stalePaid.length > 0" class="overflow-x-auto rounded-2xl border border-gray-100 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-100">
          <thead class="bg-gray-50">
            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
              <th class="px-4 py-3">Booking</th>
              <th class="px-4 py-3">Fin du tournage</th>
              <th class="px-4 py-3">Montant payé</th>
              <th class="px-4 py-3">Ancienneté</th>
              <th class="px-4 py-3">Paiement automatique</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-sm" data-testid="stale-paid-rows">
            <tr v-for="booking in stalePaid" :key="booking.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 align-top">
                <div class="font-medium text-gray-900">{{ booking.producer.display_name }}</div>
                <div class="text-xs text-gray-500">Face : {{ booking.face.display_name }}</div>
                <RouterLink
                  :to="{ name: 'admin-booking-detail', params: { id: booking.id } }"
                  class="text-xs font-medium text-[#198496] hover:underline"
                  data-testid="stale-booking-link"
                >
                  Voir la réservation
                </RouterLink>
              </td>
              <td class="px-4 py-3 align-top text-gray-700">{{ formatDate(booking.date_fin, false) }}</td>
              <td class="px-4 py-3 align-top font-semibold text-gray-900">
                {{ formatCurrency(booking.montant_total_producteur) }}
              </td>
              <td class="px-4 py-3 align-top text-gray-700">{{ booking.days_since_date_fin }} jours</td>
              <td class="px-4 py-3 align-top text-gray-700" data-testid="stale-auto-payment">
                {{ autoPaymentLabel(booking) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Resolve modal -->
    <Teleport to="body">
      <div
        v-if="resolveTarget"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        data-testid="booking-resolve-modal"
        @click.self="closeResolveModal"
      >
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
          <div class="border-b border-gray-100 px-6 py-4">
            <h3 class="text-lg font-semibold text-gray-900">
              {{ resolveOutcome === 'favor_face' ? 'Trancher en faveur de la Face' : 'Trancher en faveur du Producteur' }}
            </h3>
            <p class="mt-1 text-sm text-gray-500">
              Cette décision est définitive. Une note d'audit sera enregistrée.
            </p>
          </div>

          <div class="space-y-4 px-6 py-5">
            <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
              {{ kindLabel(resolveTarget.status) }} ·
              Face : <strong>{{ resolveTarget.face.display_name }}</strong> ·
              Producteur : <strong>{{ resolveTarget.producer.display_name }}</strong>
            </div>

            <div class="space-y-2">
              <label class="text-sm font-medium text-gray-700" for="booking-dispute-resolve-notes">
                Motif de la décision (min 5 caractères)
              </label>
              <Textarea
                id="booking-dispute-resolve-notes"
                v-model="resolveNotes"
                rows="4"
                placeholder="Pourquoi tranchez-vous ainsi ? Référez-vous aux preuves fournies."
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
              data-testid="booking-resolve-confirm"
              :class="[
                'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-50',
                resolveOutcome === 'favor_face' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-amber-600 hover:bg-amber-700',
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
    </Teleport>
  </div>
</template>
