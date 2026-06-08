<script setup lang="ts">
import { computed } from 'vue'
import { Calendar, Wallet, Users, Pencil, Trash2, XCircle, RefreshCw, CheckCircle2, ClipboardCheck, ArrowRight } from 'lucide-vue-next'
import type { Mission, MissionStatusType } from '../types'

const props = withDefaults(defineProps<{
  mission: Mission
  emailVerified?: boolean
}>(), {
  emailVerified: true, // Default to true for backwards compatibility
})

const emit = defineEmits<{
  edit: [id: string]
  delete: [id: string]
  close: [id: string]
  reopen: [id: string]
  complete: [id: string]
  viewCandidatures: [id: string]
  viewAttendance: [id: string]
  payCommission: [id: string]
}>()

// A UGC mission always carries a `commission_ugc` (null for standard missions).
const isUgcMission = computed<boolean>(() => props.mission.commission_ugc !== null)

// Computed: Only show actions for editable statuses
// Note: When email is not verified, only delete is allowed.
// UGC missions are never editable (backend `UpdateMissionRequest` rejects type_mission='ugc'),
// so editing a published UGC mission must not be offered (dead-end fix, deferred-work § ugc-1-4).
const canEdit = computed<boolean>(
  () => props.emailVerified && ['draft', 'published'].includes(props.mission.status) && !isUgcMission.value,
)

// A UGC mission stays `pending_payment` until its commission is settled; offer the payment CTA there.
// Guard on `isUgcMission`: a STANDARD mission also sits in `pending_payment` during its escrow
// checkout (MissionPaymentService sets it), so `pending_payment` alone is NOT a UGC discriminator
// — without `&& isUgcMission` the CTA would surface on standard missions with `commission_ugc = 0`
// FCFA and 403 server-side (review finding F1).
const canPayCommission = computed<boolean>(
  () => props.emailVerified && props.mission.status === 'pending_payment' && isUgcMission.value,
)
const canDelete = computed<boolean>(() => ['draft', 'published'].includes(props.mission.status))
const canClose = computed<boolean>(() => props.emailVerified && props.mission.status === 'published')
const canReopen = computed<boolean>(() => props.emailVerified && props.mission.status === 'closed' && !props.mission.has_paid_payment)
const canComplete = computed<boolean>(() => props.emailVerified && props.mission.status === 'closed')
const canValidateAttendance = computed<boolean>(() =>
  props.emailVerified
    && props.mission.has_paid_payment
    && ['closed', 'pending_attendance_validation'].includes(props.mission.status)
)

// Formatters
function formatDate(dateString: string): string {
  if (!dateString) return ''
  return new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(new Date(dateString))
}

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

// Status Color Mapping
const statusClasses = computed<string>(() => {
  const mapping: Record<MissionStatusType, string> = {
    draft: 'bg-gray-100 text-gray-800 border-gray-200',
    published: 'bg-green-100 text-green-800 border-green-200',
    pending_payment: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    closed: 'bg-orange-100 text-orange-800 border-orange-200',
    pending_attendance_validation: 'bg-amber-100 text-amber-800 border-amber-200',
    completed: 'bg-blue-100 text-blue-800 border-blue-200',
  }
  return mapping[props.mission.status] ?? mapping.draft
})

// Get candidatures count (default to 0 if not set)
const candidaturesCount = computed<number>(() => props.mission.candidatures_count ?? 0)
</script>

<template>
  <div
    class="group relative bg-card border border-border rounded-lg overflow-hidden transition-all duration-300 hover:shadow-md hover:border-primary/30"
    :class="{ 'cursor-pointer': canEdit }"
    @click="canEdit && emit('edit', mission.id)"
  >
    <!-- Card Content -->
    <div class="p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
      <!-- Left Section: Information -->
      <div class="flex-1 space-y-4">
        <div class="flex flex-wrap items-center gap-3">
          <span
            :class="[
              statusClasses,
              'px-2.5 py-0.5 rounded-full text-xs font-semibold border uppercase tracking-wider',
            ]"
          >
            {{ mission.status_label }}
          </span>

          <button
            type="button"
            class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground bg-muted/50 px-2.5 py-0.5 rounded-full border border-border hover:bg-primary/10 hover:text-primary hover:border-primary/30 transition-colors"
            @click.stop="emit('viewCandidatures', mission.id)"
          >
            <Users :size="14" class="text-primary" />
            <span>{{ candidaturesCount }} candidature{{ candidaturesCount > 1 ? 's' : '' }}</span>
          </button>
        </div>

        <div>
          <h3
            class="text-lg sm:text-xl font-bold text-foreground leading-tight group-hover:text-primary transition-colors"
          >
            {{ mission.titre }}
          </h3>

          <div class="mt-3 flex flex-wrap gap-x-6 gap-y-2">
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
              <Calendar :size="16" class="text-primary/70" />
              <span>{{ formatDate(mission.date_tournage) }}</span>
            </div>

            <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
              <Wallet :size="16" class="text-primary/70" />
              <span>{{ formatCurrency(mission.budget) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Section: Actions -->
      <div class="flex flex-wrap items-center gap-3 pt-4 md:pt-0 border-t md:border-t-0 border-border">
        <button
          type="button"
          class="flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-muted text-foreground border border-border rounded-lg font-medium transition-all hover:bg-primary/10 hover:text-primary hover:border-primary/30 active:scale-95"
          @click.stop="emit('viewCandidatures', mission.id)"
        >
          <Users :size="16" />
          <span>Candidatures</span>
          <span class="ml-0.5 rounded-full bg-primary/15 px-1.5 py-0.5 text-xs font-bold text-primary">{{ candidaturesCount }}</span>
          <ArrowRight :size="14" class="opacity-50" />
        </button>

        <button
          v-if="canEdit"
          type="button"
          class="flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-primary-foreground rounded-lg font-medium transition-all hover:bg-primary/90 active:scale-95 focus:ring-2 focus:ring-primary/20"
          @click.stop="emit('edit', mission.id)"
        >
          <Pencil :size="16" />
          <span>Modifier</span>
        </button>

        <button
          v-if="canPayCommission"
          type="button"
          data-testid="pay-commission-button"
          class="flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-weact text-white rounded-lg font-medium transition-all hover:bg-weact/90 active:scale-95 focus:ring-2 focus:ring-weact/20"
          @click.stop="emit('payCommission', mission.id)"
        >
          <Wallet :size="16" />
          <span>Régler la commission</span>
        </button>

        <button
          v-if="canClose"
          type="button"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-orange-500 text-white rounded-lg font-medium transition-all hover:bg-orange-600 active:scale-95 focus:ring-2 focus:ring-orange-500/20"
          title="Clôturer les candidatures"
          @click.stop="emit('close', mission.id)"
        >
          <XCircle :size="16" />
          <span>Clôturer</span>
        </button>

        <button
          v-if="canReopen"
          type="button"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-green-500 text-white rounded-lg font-medium transition-all hover:bg-green-600 active:scale-95 focus:ring-2 focus:ring-green-500/20"
          title="Réouvrir la mission"
          @click.stop="emit('reopen', mission.id)"
        >
          <RefreshCw :size="16" />
          <span>Réouvrir</span>
        </button>

        <button
          v-if="canValidateAttendance"
          type="button"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-500 text-white rounded-lg font-medium transition-all hover:bg-amber-600 active:scale-95 focus:ring-2 focus:ring-amber-500/20"
          title="Valider les présences"
          @click.stop="emit('viewAttendance', mission.id)"
        >
          <ClipboardCheck :size="16" />
          <span>Valider les présences</span>
        </button>

        <button
          v-if="canComplete"
          type="button"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-500 text-white rounded-lg font-medium transition-all hover:bg-blue-600 active:scale-95 focus:ring-2 focus:ring-blue-500/20"
          title="Marquer comme terminée"
          @click.stop="emit('complete', mission.id)"
        >
          <CheckCircle2 :size="16" />
          <span>Terminer</span>
        </button>

        <button
          v-if="canDelete"
          type="button"
          class="inline-flex items-center justify-center p-2.5 text-destructive bg-destructive/10 border border-destructive/20 rounded-lg transition-all hover:bg-destructive hover:text-destructive-foreground active:scale-95"
          title="Supprimer la mission"
          @click.stop="emit('delete', mission.id)"
        >
          <Trash2 :size="18" />
        </button>
      </div>
    </div>

    <!-- Interactive subtle bottom bar -->
    <div class="h-1 w-full bg-muted overflow-hidden">
      <div
        class="h-full bg-primary w-0 group-hover:w-full transition-all duration-500 ease-out opacity-60"
      ></div>
    </div>
  </div>
</template>
