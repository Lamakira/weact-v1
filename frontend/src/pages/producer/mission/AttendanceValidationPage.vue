<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Users, AlertCircle } from 'lucide-vue-next'
import { Skeleton } from '@/components/ui/skeleton'
import { useToast } from '@/composables/useToast'
import { useValidateAttendance } from '@/features/mission/composables/useValidateAttendance'
import ValidateAttendanceDialog from '@/features/mission/components/ValidateAttendanceDialog.vue'
import {
  AttendanceStatus,
  type AttendanceDecision,
  type AttendanceEntry,
} from '@/features/mission/types/attendance'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const missionUuid = computed<string>(() => {
  const raw = route.params.id
  if (Array.isArray(raw)) return raw[0] ?? ''
  return raw ?? ''
})

const {
  isLoading,
  isSubmitting,
  error,
  fieldErrors,
  data,
  fetchForm,
  submitAttendance,
} = useValidateAttendance()

const decisions = reactive<Record<number, AttendanceDecision>>({})
const failedImages = reactive(new Set<number>())
const isDialogOpen = ref(false)

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

function formatDate(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  return new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(d)
}

function isEntryEditable(entry: AttendanceEntry): boolean {
  return entry.attendance_status === AttendanceStatus.PENDING
}

function hydrateDecisions(entries: AttendanceEntry[]): void {
  for (const entry of entries) {
    if (entry.attendance_status === AttendanceStatus.PRESENT) {
      decisions[entry.id] = 'present'
    } else if (entry.attendance_status === AttendanceStatus.ABSENT) {
      decisions[entry.id] = 'absent'
    } else if (entry.attendance_status === AttendanceStatus.DISPUTED) {
      // Disputed = audit trail of an absent decision; show it as absent (read-only).
      decisions[entry.id] = 'absent'
    }
  }
}

function resetState(): void {
  for (const k of Object.keys(decisions)) {
    delete decisions[Number(k)]
  }
  failedImages.clear()
}

function markAllPresent(): void {
  if (!data.value) return
  for (const entry of data.value.entries) {
    if (isEntryEditable(entry)) decisions[entry.id] = 'present'
  }
}

function handleImageError(entryId: number): void {
  failedImages.add(entryId)
}

// Cumul mission — recap bas-de-page (entries pré-tranchées hydratées incluses).
const presentEntries = computed<AttendanceEntry[]>(() => {
  if (!data.value) return []
  return data.value.entries.filter((e) => decisions[e.id] === 'present')
})

const absentEntries = computed<AttendanceEntry[]>(() => {
  if (!data.value) return []
  return data.value.entries.filter((e) => decisions[e.id] === 'absent')
})

const totalReleased = computed(() =>
  presentEntries.value.reduce((sum, e) => sum + e.montant_face_recoit, 0)
)

const totalRefunded = computed(() =>
  absentEntries.value.reduce((sum, e) => sum + e.montant_face_recoit, 0)
)

// This-action only — dialog + toast (entries éditables avec décision posée par cette validation).
const submittedPresentEntries = computed<AttendanceEntry[]>(() => {
  if (!data.value) return []
  return data.value.entries.filter(
    (e) => isEntryEditable(e) && decisions[e.id] === 'present'
  )
})

const submittedAbsentEntries = computed<AttendanceEntry[]>(() => {
  if (!data.value) return []
  return data.value.entries.filter(
    (e) => isEntryEditable(e) && decisions[e.id] === 'absent'
  )
})

const submittedReleased = computed(() =>
  submittedPresentEntries.value.reduce((sum, e) => sum + e.montant_face_recoit, 0)
)

const submittedRefunded = computed(() =>
  submittedAbsentEntries.value.reduce((sum, e) => sum + e.montant_face_recoit, 0)
)

const submittablePayload = computed<Array<{ entry_id: number; status: AttendanceDecision }>>(
  () => {
    if (!data.value) return []
    const items: Array<{ entry_id: number; status: AttendanceDecision }> = []
    for (const entry of data.value.entries) {
      if (!isEntryEditable(entry)) continue
      const decision = decisions[entry.id]
      if (decision === undefined) continue
      items.push({ entry_id: entry.id, status: decision })
    }
    return items
  }
)

const allEditableDecided = computed(() => {
  if (!data.value) return false
  const editable = data.value.entries.filter(isEntryEditable)
  if (editable.length === 0) return false
  return editable.every((e) => decisions[e.id] !== undefined)
})

const canSubmit = computed(() => allEditableDecided.value && !isSubmitting.value)

const hasEditableEntries = computed(() => {
  if (!data.value) return false
  return data.value.entries.some(isEntryEditable)
})

async function loadForm(): Promise<void> {
  resetState()
  if (!missionUuid.value) {
    // Empty/missing route param — bail early instead of hitting the backend with ''.
    router.push({ name: 'producer-missions' })
    return
  }
  const result = await fetchForm(missionUuid.value)
  if (!result.success || !result.data) {
    // Route by HTTP status (deterministic; never substring-match localized messages).
    // 422 → stay on page, banner via `error.value`. Anything else → toast + redirect.
    if (result.status === 422) {
      return
    }
    toast.error(error.value || 'Impossible de charger le formulaire de présence.')
    router.push({ name: 'producer-missions' })
    return
  }
  hydrateDecisions(result.data.entries)
}

function openDialog(): void {
  if (!canSubmit.value) return
  isDialogOpen.value = true
}

function cancelDialog(): void {
  isDialogOpen.value = false
}

async function confirmDialog(): Promise<void> {
  if (!data.value || isSubmitting.value || !canSubmit.value) return
  const result = await submitAttendance(missionUuid.value, {
    entries: submittablePayload.value,
  })
  isDialogOpen.value = false

  if (result.success) {
    const presentCount = submittedPresentEntries.value.length
    const absentCount = submittedAbsentEntries.value.length
    const creditPart = `${presentCount} Face(s) créditée(s).`
    const refundPart = absentCount > 0
      ? ` ${absentCount} remboursement(s) en cours (72h pour contestation).`
      : ''
    toast.success(`Présences validées avec succès. ${creditPart}${refundPart}`)
    router.push({ name: 'producer-missions' })
    return
  }

  if (result.status === 422) {
    if (Object.keys(fieldErrors.value).length > 0) {
      toast.error('La validation a échoué. Vérifiez les détails ci-dessus.')
    } else {
      toast.error(error.value || 'La validation a échoué.')
    }
    return
  }
  toast.error(error.value || 'La validation a échoué.')
  router.push({ name: 'producer-missions' })
}

watch(missionUuid, loadForm, { immediate: true })
</script>

<template>
  <div class="container mx-auto px-4 py-6 max-w-3xl">
    <button
      type="button"
      class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground mb-4"
      @click="router.push({ name: 'producer-missions' })"
    >
      <ArrowLeft :size="16" /> Retour aux missions
    </button>

    <!-- Loading skeleton -->
    <div v-if="isLoading" class="space-y-4" data-testid="attendance-skeleton">
      <Skeleton class="h-8 w-2/3" />
      <Skeleton class="h-4 w-1/3" />
      <Skeleton class="h-24 w-full" />
      <Skeleton class="h-24 w-full" />
    </div>

    <!-- Full-page error banner (e.g. 422 status non éligible / payment non Paid) -->
    <div
      v-else-if="error && !data"
      class="bg-destructive/10 border border-destructive/20 text-destructive rounded-lg p-4 flex items-start gap-3"
      role="alert"
    >
      <AlertCircle :size="20" class="flex-shrink-0 mt-0.5" />
      <div class="flex-1">
        <p class="font-semibold">Cette mission ne peut pas être validée pour le moment.</p>
        <p class="text-sm mt-1">{{ error }}</p>
        <button
          type="button"
          class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 bg-destructive text-destructive-foreground rounded-md text-sm hover:bg-destructive/90"
          @click="router.push({ name: 'producer-missions' })"
        >
          Retour aux missions
        </button>
      </div>
    </div>

    <!-- Form -->
    <div v-else-if="data" class="space-y-6">
      <header>
        <h1 class="text-2xl font-bold text-foreground">{{ data.mission.titre }}</h1>
        <p class="text-sm text-muted-foreground mt-1">
          <span v-if="data.mission.date_tournage">
            Tournage du {{ formatDate(data.mission.date_tournage) }} —
          </span>
          {{ data.payment.nombre_faces_retenues }} Face(s) sélectionnée(s) —
          Paiement total {{ formatCurrency(data.payment.montant_total_producteur) }}
        </p>
      </header>

      <!-- Field errors banner (422) -->
      <div
        v-if="Object.keys(fieldErrors).length > 0"
        class="bg-destructive/10 border border-destructive/20 text-destructive rounded-lg p-3 text-sm space-y-1"
        role="alert"
        data-testid="attendance-field-errors"
      >
        <div v-for="(messages, field) in fieldErrors" :key="field">
          <template v-if="messages.length > 0">
            <strong>{{ field }} :</strong> {{ messages.join(' ') }}
          </template>
        </div>
      </div>

      <!-- Mark all present -->
      <div>
        <button
          type="button"
          :disabled="!hasEditableEntries"
          class="inline-flex items-center gap-2 px-4 py-2 bg-blue-500 text-white rounded-lg font-medium hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
          data-testid="mark-all-present-button"
          @click="markAllPresent"
        >
          <Users :size="16" /> Toutes présentes
        </button>
      </div>

      <!-- Entries list -->
      <ul class="space-y-3">
        <li
          v-for="entry in data.entries"
          :key="entry.id"
          class="bg-card border border-border rounded-lg p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
          :data-testid="`attendance-entry-${entry.id}`"
        >
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <img
              v-if="entry.face.profile_photo_url && !failedImages.has(entry.id)"
              :src="entry.face.profile_photo_url"
              :alt="entry.face.display_name"
              class="w-12 h-12 rounded-full object-cover"
              @error="handleImageError(entry.id)"
            />
            <div
              v-else
              class="w-12 h-12 rounded-full bg-muted flex items-center justify-center text-muted-foreground text-sm font-semibold"
              aria-hidden="true"
            >
              {{ ((entry.face.display_name || '').trim() || '?').charAt(0).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-medium text-foreground truncate">{{ entry.face.display_name }}</p>
              <p class="text-sm text-muted-foreground">
                {{ formatCurrency(entry.montant_face_recoit) }}
              </p>
              <p
                v-if="!isEntryEditable(entry) && entry.notified_at"
                class="text-xs text-amber-700 mt-1"
              >
                Notifiée le {{ formatDate(entry.notified_at) }} — fenêtre contestation 72h
              </p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-sm">
              <input
                type="radio"
                :name="`decision-${entry.id}`"
                :checked="decisions[entry.id] === 'present'"
                :disabled="!isEntryEditable(entry)"
                :data-testid="`decision-${entry.id}-present`"
                @change="decisions[entry.id] = 'present'"
              />
              Présente
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input
                type="radio"
                :name="`decision-${entry.id}`"
                :checked="decisions[entry.id] === 'absent'"
                :disabled="!isEntryEditable(entry)"
                :data-testid="`decision-${entry.id}-absent`"
                @change="decisions[entry.id] = 'absent'"
              />
              Absente
            </label>
          </div>
        </li>
      </ul>

      <!-- Financial recap -->
      <div class="bg-muted/50 border border-border rounded-lg p-4 space-y-1" data-testid="attendance-recap">
        <p class="text-sm">
          <strong>{{ presentEntries.length }} Face(s) présente(s)</strong> —
          {{ formatCurrency(totalReleased) }} versés aux Faces présentes.
        </p>
        <p class="text-sm">
          <strong>{{ absentEntries.length }} Face(s) absente(s)</strong> —
          {{ formatCurrency(totalRefunded) }} remboursement(s) en cours (72h pour contestation).
        </p>
      </div>

      <!-- Submit -->
      <button
        type="button"
        :disabled="!canSubmit"
        class="w-full px-4 py-3 bg-amber-500 text-white rounded-lg font-medium hover:bg-amber-600 disabled:opacity-50 disabled:cursor-not-allowed"
        data-testid="attendance-submit-button"
        @click="openDialog"
      >
        Valider les présences
      </button>
    </div>

    <ValidateAttendanceDialog
      :is-open="isDialogOpen"
      :mission-title="data?.mission.titre || ''"
      :present-count="submittedPresentEntries.length"
      :absent-count="submittedAbsentEntries.length"
      :total-released="submittedReleased"
      :total-refunded="submittedRefunded"
      :is-loading="isSubmitting"
      @cancel="cancelDialog"
      @confirm="confirmDialog"
    />
  </div>
</template>
