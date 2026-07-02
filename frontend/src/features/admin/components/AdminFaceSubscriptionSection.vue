<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  AlertCircle,
  Calendar,
  ChevronDown,
  CreditCard,
  Crown,
  Loader2,
  Plus,
  X,
} from 'lucide-vue-next'
import {
  useAdminFaceSubscriptions,
  type AdminSubscriptionActionResult,
} from '../composables/useAdminFaceSubscriptions'
import type {
  ActivatePayload,
  AdminFaceSubscription,
  AdminFaceSubscriptionAudit,
  AdminSubscriptionStatus,
  ChangeTierPayload,
} from '../services/adminFaceSubscriptionsApi'
import { useToast } from '@/composables/useToast'

type TierKey = 'free' | 'starter' | 'pro' | 'elite'

const TIER_LABEL = {
  free: 'Découverte',
  starter: 'Starter',
  pro: 'Pro',
  elite: 'Élite',
} as const satisfies Record<TierKey, string>

const PAID_PLANS = ['starter', 'pro', 'elite'] as const satisfies readonly TierKey[]
type PaidPlan = (typeof PAID_PLANS)[number]

const objectHasOwn: (object: object, propertyKey: PropertyKey) => boolean =
  (Object as ObjectConstructor & { hasOwn?: (object: object, propertyKey: PropertyKey) => boolean }).hasOwn ??
  ((object, propertyKey) => Object.prototype.hasOwnProperty.call(object, propertyKey))

function isTierKey(value: string): value is TierKey {
  return objectHasOwn(TIER_LABEL, value)
}

function isPaidPlan(value: unknown): value is PaidPlan {
  return typeof value === 'string' && PAID_PLANS.includes(value as PaidPlan)
}

function formatTier(state: Record<string, unknown> | null | undefined): TierKey | null {
  if (!state) return null
  const tier = state.tier
  return typeof tier === 'string' && isTierKey(tier) ? tier : null
}

interface Props {
  faceId: string
}

const props = defineProps<Props>()
const toast = useToast()
const lastFocusedElement = ref<HTMLElement | null>(null)
const activateModalRef = ref<HTMLDivElement | null>(null)
const extendModalRef = ref<HTMLDivElement | null>(null)
const cancelModalRef = ref<HTMLDivElement | null>(null)
const correctModalRef = ref<HTMLDivElement | null>(null)
const changeTierModalRef = ref<HTMLDivElement | null>(null)

const {
  subscriptions,
  isLoading,
  error,
  fetchSubscriptions,
  abortRequests,
  activate,
  extend,
  cancel,
  correct,
  changeTier,
} = useAdminFaceSubscriptions()

async function loadSubscriptions(): Promise<void> {
  if (!props.faceId) {
    subscriptions.value = []
    error.value = null
    return
  }

  try {
    await fetchSubscriptions(props.faceId)
  } catch {
    // 401s are handled globally by adminApiClient; do not surface a duplicate modal/toast error here.
  }
}

onMounted(() => {
  void loadSubscriptions()
})

onBeforeUnmount(() => {
  abortRequests()
})

watch(
  () => props.faceId,
  (newId, oldId) => {
    if (newId !== oldId) {
      abortRequests()
      closeAllModals(true)
      expandedRows.value = {}
      expandedAudits.value = {}
      expandedAuditDiffs.value = {}
      void loadSubscriptions()
    }
  },
)

// ---------- Helpers ----------

const STATUS_BADGE_CLASS: Record<AdminSubscriptionStatus, string> = {
  active: 'bg-green-100 text-green-700',
  pending_payment: 'bg-amber-100 text-amber-700',
  expired: 'bg-red-100 text-red-700',
  cancelled: 'bg-gray-100 text-gray-600',
  failed: 'bg-red-100 text-red-700',
}

function statusBadgeClass(status: AdminSubscriptionStatus | null): string {
  if (!status) return 'bg-gray-100 text-gray-600'
  return STATUS_BADGE_CLASS[status] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(iso: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) {
    return '—'
  }

  return d.toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })
}

function formatDateTime(iso: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) {
    return '—'
  }

  return d.toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatAmount(amount: number | null, currency: string): string {
  if (amount === null || !Number.isFinite(amount)) return '—'
  try {
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount)
  } catch {
    return `${new Intl.NumberFormat('fr-FR').format(amount)} ${currency || 'XOF'}`
  }
}

function toDateInputValue(iso: string | null): string {
  if (!iso) return ''
  const datePart = iso.match(/^\d{4}-\d{2}-\d{2}/)?.[0]
  if (datePart) {
    const parsedDate = new Date(`${datePart}T00:00:00Z`)
    if (!Number.isNaN(parsedDate.getTime()) && parsedDate.toISOString().slice(0, 10) === datePart) {
      return datePart
    }
    return ''
  }

  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) {
    return ''
  }

  return d.toISOString().slice(0, 10)
}

function safeStringify(value: unknown): string {
  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return 'JSON indisponible'
  }
}

function isExtendable(sub: AdminFaceSubscription): boolean {
  return (
    sub.status === 'active' &&
    sub.expires_at !== null &&
    new Date(sub.expires_at).getTime() > Date.now()
  )
}

function isCancellable(sub: AdminFaceSubscription): boolean {
  return sub.status === 'active' || sub.status === 'pending_payment'
}

async function prepareModalFocus(modalRef: typeof activateModalRef): Promise<void> {
  lastFocusedElement.value =
    document.activeElement instanceof HTMLElement ? document.activeElement : null
  await nextTick()
  modalRef.value?.focus()
}

function restoreFocus(): void {
  if (lastFocusedElement.value && document.contains(lastFocusedElement.value)) {
    lastFocusedElement.value.focus({ preventScroll: true })
  }
  lastFocusedElement.value = null
}

function trapModalFocus(event: KeyboardEvent, root: HTMLDivElement | null): void {
  if (!root) return

  const focusable = Array.from(
    root.querySelectorAll<HTMLElement>(
      'button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  ).filter((element) => element.offsetParent !== null)

  if (focusable.length === 0) return

  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (!first || !last) return

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

// ---------- Audit disclosure state ----------

const expandedRows = ref<Record<string, boolean>>({})
const expandedAudits = ref<Record<string, boolean>>({})
const expandedAuditDiffs = ref<Record<string, boolean>>({})

function toggleRow(subId: string): void {
  expandedRows.value[subId] = !expandedRows.value[subId]
}

function toggleAudits(subId: string): void {
  expandedAudits.value[subId] = !expandedAudits.value[subId]
}

function toggleAuditDiff(auditId: string): void {
  expandedAuditDiffs.value[auditId] = !expandedAuditDiffs.value[auditId]
}

// Subscriptions and their audits both arrive newest-first from the API
// (controller orderByDesc + FaceSubscription::audits() orderByDesc), so
// audits[0] is the latest operation surfaced in the "Dernière opération" cell.
function latestAudit(sub: AdminFaceSubscription): AdminFaceSubscriptionAudit | null {
  return sub.audits?.[0] ?? null
}

// ---------- Activate modal ----------

const activateOpen = ref(false)
const activatePlan = ref<PaidPlan>('starter')
const activateNotes = ref('')
const activateStartsAt = ref('')
const activateDurationDays = ref<string>('')
const activateSubmitting = ref(false)
const activateConflictMessage = ref<string | null>(null)
const activateErrors = ref<Record<string, string[]>>({})

async function openActivate(): Promise<void> {
  activatePlan.value = 'starter'
  activateNotes.value = ''
  activateStartsAt.value = ''
  activateDurationDays.value = ''
  activateConflictMessage.value = null
  activateErrors.value = {}
  activateOpen.value = true
  await prepareModalFocus(activateModalRef)
}

function closeActivate(): void {
  if (activateSubmitting.value) return
  activateOpen.value = false
  restoreFocus()
}

async function submitActivate(): Promise<void> {
  if (activateSubmitting.value) return
  if (!props.faceId) return
  activateSubmitting.value = true
  activateConflictMessage.value = null
  activateErrors.value = {}

  const payload: ActivatePayload = {
    plan: activatePlan.value,
    notes: activateNotes.value.trim(),
  }
  if (activateStartsAt.value) payload.starts_at = activateStartsAt.value
  if (activateDurationDays.value) {
    const parsedDuration = Number(activateDurationDays.value)
    if (!Number.isInteger(parsedDuration) || parsedDuration < 30 || parsedDuration > 3650) {
      activateErrors.value = {
        duration_days: ['Saisissez un nombre entier entre 30 et 3650.'],
      }
      activateSubmitting.value = false
      return
    }
    payload.duration_days = parsedDuration
  }

  let result: AdminSubscriptionActionResult
  try {
    result = await activate(props.faceId, payload)
  } catch {
    activateSubmitting.value = false
    return
  }

  if (result.success) {
    activateSubmitting.value = false
    activateOpen.value = false
    toast.success(result.message ?? 'Abonnement activé manuellement')
    await loadSubscriptions()
    return
  }

  activateSubmitting.value = false

  if (result.code === 'VALIDATION_ERROR') {
    activateErrors.value = result.errors ?? {}
    activateConflictMessage.value = result.message ?? null
  } else {
    activateErrors.value = {}
    activateConflictMessage.value = result.message ?? 'Une erreur est survenue'
    await loadSubscriptions()
  }
}

// ---------- Extend modal ----------

const extendTarget = ref<AdminFaceSubscription | null>(null)
const extendNotes = ref('')
const extendAdditionalDays = ref<string>('')
const extendSubmitting = ref(false)
const extendConflictMessage = ref<string | null>(null)
const extendErrors = ref<Record<string, string[]>>({})

async function openExtend(sub: AdminFaceSubscription): Promise<void> {
  extendTarget.value = sub
  extendNotes.value = ''
  extendAdditionalDays.value = ''
  extendConflictMessage.value = null
  extendErrors.value = {}
  await prepareModalFocus(extendModalRef)
}

function closeExtend(): void {
  if (extendSubmitting.value) return
  extendTarget.value = null
  restoreFocus()
}

async function submitExtend(): Promise<void> {
  if (extendSubmitting.value) return
  if (!extendTarget.value) return
  extendSubmitting.value = true
  extendConflictMessage.value = null
  extendErrors.value = {}

  const parsedAdditionalDays = Number(extendAdditionalDays.value)
  if (
    !Number.isInteger(parsedAdditionalDays) ||
    parsedAdditionalDays < 1 ||
    parsedAdditionalDays > 3650
  ) {
    extendErrors.value = {
      additional_days: ['Saisissez un nombre entier entre 1 et 3650.'],
    }
    extendSubmitting.value = false
    return
  }

  const payload = {
    notes: extendNotes.value.trim(),
    additional_days: parsedAdditionalDays,
  }

  let result: AdminSubscriptionActionResult
  try {
    result = await extend(extendTarget.value.id, payload)
  } catch {
    extendSubmitting.value = false
    return
  }

  if (result.success) {
    extendSubmitting.value = false
    extendTarget.value = null
    toast.success(result.message ?? 'Abonnement étendu')
    await loadSubscriptions()
    return
  }

  extendSubmitting.value = false

  if (result.code === 'VALIDATION_ERROR') {
    extendErrors.value = result.errors ?? {}
    extendConflictMessage.value = result.message ?? null
  } else {
    extendErrors.value = {}
    extendConflictMessage.value = result.message ?? 'Une erreur est survenue'
    await loadSubscriptions()
  }
}

// ---------- Cancel modal ----------

const cancelTarget = ref<AdminFaceSubscription | null>(null)
const cancelNotes = ref('')
const cancelSubmitting = ref(false)
const cancelConflictMessage = ref<string | null>(null)
const cancelErrors = ref<Record<string, string[]>>({})

async function openCancel(sub: AdminFaceSubscription): Promise<void> {
  cancelTarget.value = sub
  cancelNotes.value = ''
  cancelConflictMessage.value = null
  cancelErrors.value = {}
  await prepareModalFocus(cancelModalRef)
}

function closeCancel(): void {
  if (cancelSubmitting.value) return
  cancelTarget.value = null
  restoreFocus()
}

async function submitCancel(): Promise<void> {
  if (cancelSubmitting.value) return
  if (!cancelTarget.value) return
  cancelSubmitting.value = true
  cancelConflictMessage.value = null
  cancelErrors.value = {}

  const payload = { notes: cancelNotes.value.trim() }
  let result: AdminSubscriptionActionResult
  try {
    result = await cancel(cancelTarget.value.id, payload)
  } catch {
    cancelSubmitting.value = false
    return
  }

  if (result.success) {
    cancelSubmitting.value = false
    cancelTarget.value = null
    toast.success(result.message ?? 'Abonnement annulé')
    await loadSubscriptions()
    return
  }

  cancelSubmitting.value = false

  if (result.code === 'VALIDATION_ERROR') {
    cancelErrors.value = result.errors ?? {}
    cancelConflictMessage.value = result.message ?? null
  } else {
    cancelErrors.value = {}
    cancelConflictMessage.value = result.message ?? 'Une erreur est survenue'
    await loadSubscriptions()
  }
}

// ---------- Correct modal ----------

const correctTarget = ref<AdminFaceSubscription | null>(null)
const correctNotes = ref('')
const correctStartsAt = ref('')
const correctExpiresAt = ref('')
const correctOriginalStartsAt = ref('')
const correctOriginalExpiresAt = ref('')
const correctSubmitting = ref(false)
const correctConflictMessage = ref<string | null>(null)
const correctErrors = ref<Record<string, string[]>>({})
const correctClientError = ref<string | null>(null)

async function openCorrect(sub: AdminFaceSubscription): Promise<void> {
  correctTarget.value = sub
  correctNotes.value = ''
  correctStartsAt.value = toDateInputValue(sub.starts_at)
  correctExpiresAt.value = toDateInputValue(sub.expires_at)
  correctOriginalStartsAt.value = correctStartsAt.value
  correctOriginalExpiresAt.value = correctExpiresAt.value
  correctConflictMessage.value = null
  correctErrors.value = {}
  correctClientError.value = null
  await prepareModalFocus(correctModalRef)
}

function closeCorrect(): void {
  if (correctSubmitting.value) return
  correctTarget.value = null
  restoreFocus()
}

async function submitCorrect(): Promise<void> {
  if (correctSubmitting.value) return
  if (!correctTarget.value) return

  const startsChanged =
    correctStartsAt.value !== '' && correctStartsAt.value !== correctOriginalStartsAt.value
  const expiresChanged =
    correctExpiresAt.value !== '' && correctExpiresAt.value !== correctOriginalExpiresAt.value

  if (!startsChanged && !expiresChanged) {
    correctClientError.value = 'Modifiez au moins une des deux dates.'
    return
  }

  correctClientError.value = null
  correctSubmitting.value = true
  correctConflictMessage.value = null
  correctErrors.value = {}

  const payload: { notes: string; starts_at?: string; expires_at?: string } = {
    notes: correctNotes.value.trim(),
  }
  if (startsChanged) payload.starts_at = correctStartsAt.value
  if (expiresChanged) payload.expires_at = correctExpiresAt.value

  let result: AdminSubscriptionActionResult
  try {
    result = await correct(correctTarget.value.id, payload)
  } catch {
    correctSubmitting.value = false
    return
  }

  if (result.success) {
    correctSubmitting.value = false
    correctTarget.value = null
    toast.success(result.message ?? 'Dates corrigées')
    await loadSubscriptions()
    return
  }

  correctSubmitting.value = false

  if (result.code === 'VALIDATION_ERROR') {
    correctErrors.value = result.errors ?? {}
    correctConflictMessage.value = result.message ?? null
  } else {
    correctErrors.value = {}
    correctConflictMessage.value = result.message ?? 'Une erreur est survenue'
    await loadSubscriptions()
  }
}

// ---------- Change tier modal (FEATURE-FP-2.10) ----------

const changeTierTarget = ref<AdminFaceSubscription | null>(null)
const changeTierNewPlan = ref<PaidPlan>('starter')
const changeTierNotes = ref('')
const changeTierSubmitting = ref(false)
const changeTierConflictMessage = ref<string | null>(null)
const changeTierErrors = ref<Record<string, string[]>>({})

const changeTierCurrentLabel = computed(() => {
  const plan = changeTierTarget.value?.plan
  return isPaidPlan(plan) ? TIER_LABEL[plan] : '—'
})

const changeTierOptions = computed<PaidPlan[]>(() => {
  const current = changeTierTarget.value?.plan
  if (!isPaidPlan(current)) return []
  return PAID_PLANS.filter((plan) => plan !== current)
})

function resetChangeTierState(): void {
  changeTierTarget.value = null
  changeTierNewPlan.value = 'starter'
  changeTierNotes.value = ''
  changeTierConflictMessage.value = null
  changeTierErrors.value = {}
}

async function openChangeTier(sub: AdminFaceSubscription): Promise<void> {
  if (!isPaidPlan(sub.plan)) {
    toast.error('Impossible de changer le palier : le palier actuel est inconnu.')
    return
  }

  changeTierTarget.value = sub
  changeTierNewPlan.value = changeTierOptions.value[0] ?? 'starter'
  changeTierNotes.value = ''
  changeTierConflictMessage.value = null
  changeTierErrors.value = {}
  await prepareModalFocus(changeTierModalRef)
}

function closeChangeTier(): void {
  if (changeTierSubmitting.value) return
  resetChangeTierState()
  restoreFocus()
}

async function submitChangeTier(): Promise<void> {
  if (changeTierSubmitting.value) return
  if (!changeTierTarget.value) return
  changeTierSubmitting.value = true
  changeTierConflictMessage.value = null
  changeTierErrors.value = {}

  const payload: ChangeTierPayload = {
    notes: changeTierNotes.value.trim(),
    new_plan: changeTierNewPlan.value,
  }

  let result: AdminSubscriptionActionResult
  try {
    result = await changeTier(changeTierTarget.value.id, payload)
  } catch {
    changeTierSubmitting.value = false
    return
  }

  if (result.success) {
    changeTierSubmitting.value = false
    resetChangeTierState()
    toast.success(result.message ?? 'Palier modifié')
    await loadSubscriptions()
    return
  }

  changeTierSubmitting.value = false

  if (result.code === 'VALIDATION_ERROR') {
    changeTierErrors.value = result.errors ?? {}
    changeTierConflictMessage.value = result.message ?? null
  } else {
    changeTierErrors.value = {}
    changeTierConflictMessage.value = result.message ?? 'Une erreur est survenue'
    const targetId = changeTierTarget.value?.id
    await loadSubscriptions()
    const refreshedTarget = subscriptions.value.find((sub) => sub.id === targetId)
    if (refreshedTarget && isExtendable(refreshedTarget) && isPaidPlan(refreshedTarget.plan)) {
      changeTierTarget.value = refreshedTarget
      changeTierNewPlan.value = changeTierOptions.value[0] ?? 'starter'
    } else {
      resetChangeTierState()
      restoreFocus()
      toast.error("Cet abonnement n'est plus modifiable.")
    }
  }
}

function closeAllModals(force = false): void {
  if (
    !force &&
    (activateSubmitting.value ||
      extendSubmitting.value ||
      cancelSubmitting.value ||
      correctSubmitting.value ||
      changeTierSubmitting.value)
  ) {
    return
  }

  activateOpen.value = false
  extendTarget.value = null
  cancelTarget.value = null
  correctTarget.value = null
  resetChangeTierState()
  activateSubmitting.value = false
  extendSubmitting.value = false
  cancelSubmitting.value = false
  correctSubmitting.value = false
  changeTierSubmitting.value = false
  restoreFocus()
}

// ---------- Derived state ----------

const isEmpty = computed(() => !isLoading.value && subscriptions.value.length === 0 && !error.value)
</script>

<template>
  <section
    class="bg-white rounded-xl border border-gray-200 p-6 space-y-4"
    data-testid="admin-face-subscription-section"
  >
    <header class="flex items-center justify-between gap-4">
      <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
        <Crown class="h-5 w-5 text-indigo-500" />
        Abonnement Premium
      </h2>
      <button
        v-if="!isLoading && !isEmpty"
        type="button"
        class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-700 transition-colors hover:bg-indigo-100"
        data-testid="open-activate-button"
        @click="openActivate"
      >
        <Plus class="h-4 w-4" />
        Activer manuellement
      </button>
    </header>

    <!-- Error -->
    <div
      v-if="error"
      class="rounded-lg bg-red-50 border border-red-200 p-4 flex items-start gap-3"
      role="alert"
      data-testid="admin-face-subscription-error"
    >
      <AlertCircle class="h-5 w-5 text-red-500 mt-0.5 shrink-0" />
      <div class="space-y-2">
        <p class="text-sm text-red-700">{{ error }}</p>
        <button
          type="button"
          class="text-sm font-medium text-red-700 underline underline-offset-2 hover:text-red-800"
          data-testid="admin-face-subscription-retry"
          @click="loadSubscriptions"
        >
          Réessayer
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div
      v-if="isLoading"
      class="flex items-center justify-center py-8"
      data-testid="admin-face-subscription-loading"
    >
      <Loader2 class="h-6 w-6 text-indigo-500 animate-spin" />
    </div>

    <!-- Empty -->
    <div
      v-else-if="isEmpty"
      class="text-center py-8"
      data-testid="admin-face-subscription-empty"
    >
      <CreditCard class="mx-auto h-10 w-10 text-gray-400" />
      <h3 class="mt-3 text-sm font-medium text-gray-900">Aucun abonnement</h3>
      <p class="mt-1 text-xs text-gray-500">
        Cette Face n'a jamais souscrit à un abonnement Premium.
      </p>
      <button
        type="button"
        class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-600 transition-colors"
        data-testid="open-activate-button"
        @click="openActivate"
      >
        <Plus class="h-4 w-4" />
        Activer manuellement
      </button>
    </div>

    <!-- Populated -->
    <div v-else class="overflow-x-auto" data-testid="admin-face-subscription-list">
      <table class="w-full min-w-[720px] text-sm">
        <thead>
          <tr class="border-b border-gray-50 bg-gray-50/60 text-left">
            <th scope="col" class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Palier</th>
            <th scope="col" class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Statut</th>
            <th scope="col" class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Début</th>
            <th scope="col" class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Expiration</th>
            <th scope="col" class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Dernière opération</th>
            <th scope="col" class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Détails</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="sub in subscriptions" :key="sub.id">
            <tr
              class="border-b border-gray-50"
              :data-testid="`subscription-row-${sub.id}`"
            >
              <td class="px-6 py-3 align-top text-sm font-medium text-gray-900">
                {{ sub.plan_label ?? 'Plan inconnu' }}
              </td>
              <td class="px-6 py-3 align-top">
                <span
                  class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="statusBadgeClass(sub.status)"
                  :data-testid="`subscription-status-${sub.id}`"
                >
                  {{ sub.status_label ?? '—' }}
                </span>
              </td>
              <td class="px-6 py-3 align-top text-gray-700">{{ formatDate(sub.starts_at) }}</td>
              <td class="px-6 py-3 align-top text-gray-700">{{ formatDate(sub.expires_at) }}</td>
              <td
                class="px-6 py-3 align-top text-gray-700"
                :data-testid="`subscription-last-op-${sub.id}`"
              >
                <template v-if="latestAudit(sub)"
                  >{{ latestAudit(sub)!.action_label }} ·
                  {{ latestAudit(sub)!.admin?.name || 'Admin supprimé' }}</template
                >
                <template v-else>—</template>
              </td>
              <td class="px-6 py-3 align-top">
                <div class="flex flex-wrap items-center gap-1">
                  <button
                    v-if="isExtendable(sub)"
                    type="button"
                    class="rounded-lg border border-green-300 bg-green-50 px-3 py-1 text-xs font-medium text-green-700 hover:bg-green-100 transition-colors"
                    data-testid="extend-button"
                    :data-subscription-id="sub.id"
                    @click="openExtend(sub)"
                  >
                    Étendre
                  </button>
                  <button
                    v-if="isExtendable(sub) && isPaidPlan(sub.plan)"
                    type="button"
                    class="rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 transition-colors"
                    data-testid="change-tier-button"
                    :data-subscription-id="sub.id"
                    @click="openChangeTier(sub)"
                  >
                    Changer de palier
                  </button>
                  <button
                    v-if="isCancellable(sub)"
                    type="button"
                    class="rounded-lg border border-red-300 bg-red-50 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-100 transition-colors"
                    data-testid="cancel-button"
                    :data-subscription-id="sub.id"
                    @click="openCancel(sub)"
                  >
                    Annuler
                  </button>
                  <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                    :data-testid="`correct-button-${sub.id}`"
                    @click="openCorrect(sub)"
                  >
                    Corriger les dates
                  </button>
                </div>
              </td>
              <td class="px-6 py-3 align-top text-right">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-50"
                  :data-testid="`subscription-expand-${sub.id}`"
                  :aria-expanded="!!expandedRows[sub.id]"
                  :aria-controls="`subscription-detail-${sub.id}`"
                  @click="toggleRow(sub.id)"
                >
                  {{ expandedRows[sub.id] ? 'Masquer' : 'Détails' }}
                  <ChevronDown
                    class="h-3.5 w-3.5 transition-transform"
                    :class="{ 'rotate-180': expandedRows[sub.id] }"
                  />
                </button>
              </td>
            </tr>

            <tr
              v-if="expandedRows[sub.id]"
              :id="`subscription-detail-${sub.id}`"
              class="border-b border-gray-50 bg-gray-50/40"
              :data-testid="`subscription-detail-${sub.id}`"
            >
              <td :colspan="7" class="px-6 py-4 space-y-3">
                <dl
                  v-if="sub.paid_amount !== null || sub.cancelled_at"
                  class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs sm:max-w-md"
                >
                  <div v-if="sub.paid_amount !== null">
                    <dt class="text-gray-500">Montant payé</dt>
                    <dd class="font-medium text-gray-900">
                      {{ formatAmount(sub.paid_amount, sub.currency) }}
                    </dd>
                  </div>
                  <div v-if="sub.cancelled_at">
                    <dt class="text-gray-500">Annulé le</dt>
                    <dd class="font-medium text-gray-900">{{ formatDate(sub.cancelled_at) }}</dd>
                  </div>
                </dl>

                <div>
                  <button
                    v-if="sub.audits && sub.audits.length > 0"
                    type="button"
                    class="text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors"
                    :data-testid="`audits-toggle-${sub.id}`"
                    @click="toggleAudits(sub.id)"
                  >
                    {{ expandedAudits[sub.id] ? 'Masquer' : 'Afficher' }}
                    {{ sub.audits.length }} opération{{ sub.audits.length > 1 ? 's' : '' }}
                  </button>

                  <ul
                    v-if="expandedAudits[sub.id]"
                    class="mt-2 space-y-2 border-l-2 border-indigo-100 pl-3"
                    :data-testid="`audits-list-${sub.id}`"
                  >
                    <li
                      v-for="audit in sub.audits"
                      :key="audit.id"
                      class="text-xs space-y-1"
                      :data-testid="`audit-row-${audit.id}`"
                    >
                      <div class="flex items-center gap-2">
                        <span class="font-medium text-gray-900">{{ audit.action_label }}</span>
                        <span class="text-gray-500">par {{ audit.admin?.name || 'Admin supprimé' }}</span>
                        <span class="text-gray-400">·</span>
                        <span class="text-gray-500">{{ formatDateTime(audit.created_at) }}</span>
                      </div>
                      <span
                        v-if="audit.action === 'change_tier' && formatTier(audit.previous_state) && formatTier(audit.new_state)"
                        class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-700"
                        :data-testid="`audit-tier-transition-${audit.id}`"
                      >
                        {{ TIER_LABEL[formatTier(audit.previous_state)!] }}
                        <span aria-hidden="true">→</span>
                        {{ TIER_LABEL[formatTier(audit.new_state)!] }}
                      </span>
                      <span
                        v-else-if="audit.action === 'manual_activate' && formatTier(audit.new_state)"
                        class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-700"
                        :data-testid="`audit-tier-new-${audit.id}`"
                      >
                        Palier : {{ TIER_LABEL[formatTier(audit.new_state)!] }}
                      </span>
                      <p class="text-gray-700 whitespace-pre-wrap break-words">{{ audit.notes }}</p>
                      <button
                        type="button"
                        class="text-[11px] text-gray-500 hover:text-gray-700 underline"
                        :data-testid="`audit-diff-toggle-${audit.id}`"
                        @click="toggleAuditDiff(audit.id)"
                      >
                        {{ expandedAuditDiffs[audit.id] ? 'Masquer le diff JSON' : 'Voir le diff JSON' }}
                      </button>
                      <div
                        v-if="expandedAuditDiffs[audit.id]"
                        class="grid grid-cols-1 lg:grid-cols-2 gap-2 mt-1"
                        :data-testid="`audit-diff-${audit.id}`"
                      >
                        <div>
                          <p class="text-[10px] uppercase tracking-wide text-gray-400 mb-1">État précédent</p>
                          <pre class="bg-gray-50 rounded p-2 text-[11px] overflow-x-auto">{{ safeStringify(audit.previous_state) }}</pre>
                        </div>
                        <div>
                          <p class="text-[10px] uppercase tracking-wide text-gray-400 mb-1">Nouvel état</p>
                          <pre class="bg-gray-50 rounded p-2 text-[11px] overflow-x-auto">{{ safeStringify(audit.new_state) }}</pre>
                        </div>
                      </div>
                    </li>
                  </ul>
                  <p
                    v-else
                    class="text-xs text-gray-400"
                    :data-testid="`audit-count-${sub.id}`"
                  >
                    0 opération
                  </p>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- ============ Activate Modal ============ -->
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
          v-if="activateOpen"
          ref="activateModalRef"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
          data-testid="admin-face-subscription-activate-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="activate-subscription-title"
          tabindex="-1"
          @click.self="closeActivate"
          @keydown.esc="closeActivate"
          @keydown.tab="trapModalFocus($event, activateModalRef)"
        >
          <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="border-b border-gray-100 px-6 py-4 flex items-start justify-between">
              <div>
                <h3 id="activate-subscription-title" class="text-lg font-semibold text-gray-900">
                  Activer manuellement un abonnement Premium
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                  Toutes les opérations sont enregistrées dans l'historique d'audit.
                </p>
              </div>
              <button
                type="button"
                class="text-gray-400 hover:text-gray-600"
                aria-label="Fermer"
                @click="closeActivate"
              >
                <X class="h-5 w-5" />
              </button>
            </div>

            <div class="space-y-4 px-6 py-5">
              <div
                v-if="activateConflictMessage"
                class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                data-testid="admin-face-subscription-activate-error"
              >
                {{ activateConflictMessage }}
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="activate-plan">
                  Palier <span class="text-red-500">*</span>
                </label>
                <select
                  id="activate-plan"
                  v-model="activatePlan"
                  required
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  data-testid="activate-plan"
                >
                  <option v-for="plan in PAID_PLANS" :key="plan" :value="plan">
                    {{ TIER_LABEL[plan] }}
                  </option>
                </select>
                <p class="text-xs text-gray-500">
                  Le palier choisi est enregistré sur la nouvelle souscription ; le palier
                  Free n'est pas activable manuellement.
                </p>
                <p
                  v-if="activateErrors.plan?.length"
                  class="text-xs text-red-600"
                  data-testid="admin-face-subscription-activate-field-error-plan"
                >
                  {{ activateErrors.plan[0] }}
                </p>
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="activate-notes">
                  Notes <span class="text-red-500">*</span>
                </label>
                <textarea
                  id="activate-notes"
                  v-model="activateNotes"
                  rows="3"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Raison de l'activation manuelle (5 à 1000 caractères)..."
                  data-testid="activate-notes"
                />
                <p
                  v-if="activateErrors.notes?.length"
                  class="text-xs text-red-600"
                  data-testid="admin-face-subscription-activate-field-error-notes"
                >
                  {{ activateErrors.notes[0] }}
                </p>
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="activate-starts-at">
                  Date de début (optionnel)
                </label>
                <input
                  id="activate-starts-at"
                  v-model="activateStartsAt"
                  type="date"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  data-testid="activate-starts-at"
                />
                <p class="text-xs text-gray-500">
                  Laissez vide pour démarrer maintenant. La valeur ne peut pas être future et doit
                  être ≤ 90 jours dans le passé.
                </p>
                <p
                  v-if="activateErrors.starts_at?.length"
                  class="text-xs text-red-600"
                  data-testid="admin-face-subscription-activate-field-error-starts_at"
                >
                  {{ activateErrors.starts_at[0] }}
                </p>
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="activate-duration-days">
                  Durée en jours (optionnel)
                </label>
                <input
                  id="activate-duration-days"
                  v-model="activateDurationDays"
                  type="number"
                  min="30"
                  max="3650"
                  placeholder="365"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  data-testid="activate-duration-days"
                />
                <p class="text-xs text-gray-500">Par défaut : 365 jours. Min 30, max 3650.</p>
                <p
                  v-if="activateErrors.duration_days?.length"
                  class="text-xs text-red-600"
                  data-testid="admin-face-subscription-activate-field-error-duration_days"
                >
                  {{ activateErrors.duration_days[0] }}
                </p>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
              <button
                type="button"
                class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                @click="closeActivate"
              >
                Annuler
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                :disabled="activateSubmitting"
                data-testid="activate-submit"
                @click="submitActivate"
              >
                <Loader2 v-if="activateSubmitting" class="h-4 w-4 animate-spin" />
                Activer
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ============ Extend Modal ============ -->
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
          v-if="extendTarget"
          ref="extendModalRef"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
          data-testid="admin-face-subscription-extend-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="extend-subscription-title"
          tabindex="-1"
          @click.self="closeExtend"
          @keydown.esc="closeExtend"
          @keydown.tab="trapModalFocus($event, extendModalRef)"
        >
          <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="border-b border-gray-100 px-6 py-4 flex items-start justify-between">
              <div>
                <h3 id="extend-subscription-title" class="text-lg font-semibold text-gray-900">
                  Prolonger l'abonnement
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                  Ajoutera la durée à l'expiration actuelle ({{ formatDate(extendTarget.expires_at) }}).
                </p>
              </div>
              <button
                type="button"
                class="text-gray-400 hover:text-gray-600"
                aria-label="Fermer"
                @click="closeExtend"
              >
                <X class="h-5 w-5" />
              </button>
            </div>

            <div class="space-y-4 px-6 py-5">
              <div
                v-if="extendConflictMessage"
                class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                data-testid="admin-face-subscription-extend-error"
              >
                {{ extendConflictMessage }}
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="extend-notes">
                  Notes <span class="text-red-500">*</span>
                </label>
                <textarea
                  id="extend-notes"
                  v-model="extendNotes"
                  rows="3"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Raison de la prolongation (5 à 1000 caractères)..."
                  data-testid="extend-notes"
                />
                <p
                  v-if="extendErrors.notes?.length"
                  class="text-xs text-red-600"
                  data-testid="admin-face-subscription-extend-field-error-notes"
                >
                  {{ extendErrors.notes[0] }}
                </p>
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="extend-additional-days">
                  Jours supplémentaires <span class="text-red-500">*</span>
                </label>
                <input
                  id="extend-additional-days"
                  v-model="extendAdditionalDays"
                  type="number"
                  min="1"
                  max="3650"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  data-testid="extend-additional-days"
                />
                <p class="text-xs text-gray-500">
                  Nombre de jours à ajouter à la date d'expiration actuelle.
                </p>
                <p
                  v-if="extendErrors.additional_days?.length"
                  class="text-xs text-red-600"
                  data-testid="admin-face-subscription-extend-field-error-additional_days"
                >
                  {{ extendErrors.additional_days[0] }}
                </p>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
              <button
                type="button"
                class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                @click="closeExtend"
              >
                Annuler
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-green-700 disabled:opacity-50"
                :disabled="extendSubmitting"
                data-testid="extend-submit"
                @click="submitExtend"
              >
                <Loader2 v-if="extendSubmitting" class="h-4 w-4 animate-spin" />
                Prolonger
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ============ Cancel Modal ============ -->
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
          v-if="cancelTarget"
          ref="cancelModalRef"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
          data-testid="admin-face-subscription-cancel-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="cancel-subscription-title"
          tabindex="-1"
          @click.self="closeCancel"
          @keydown.esc="closeCancel"
          @keydown.tab="trapModalFocus($event, cancelModalRef)"
        >
          <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="border-b border-gray-100 px-6 py-4 flex items-start justify-between">
              <div>
                <h3 id="cancel-subscription-title" class="text-lg font-semibold text-gray-900">
                  Annuler l'abonnement
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                  Cette action est immédiate. L'utilisateur ne pourra plus accéder aux fonctionnalités
                  Premium, mais ses photos 3-4 et sa vidéo de casting ne seront PAS supprimées (elles
                  deviendront simplement masquées publiquement).
                </p>
              </div>
              <button
                type="button"
                class="text-gray-400 hover:text-gray-600"
                aria-label="Fermer"
                @click="closeCancel"
              >
                <X class="h-5 w-5" />
              </button>
            </div>

            <div class="space-y-4 px-6 py-5">
              <div
                v-if="cancelConflictMessage"
                class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                data-testid="admin-face-subscription-cancel-error"
              >
                {{ cancelConflictMessage }}
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="cancel-notes">
                  Notes <span class="text-red-500">*</span>
                </label>
                <textarea
                  id="cancel-notes"
                  v-model="cancelNotes"
                  rows="3"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Raison de l'annulation (5 à 1000 caractères)..."
                  data-testid="cancel-notes"
                />
                <p
                  v-if="cancelErrors.notes?.length"
                  class="text-xs text-red-600"
                  data-testid="admin-face-subscription-cancel-field-error-notes"
                >
                  {{ cancelErrors.notes[0] }}
                </p>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
              <button
                type="button"
                class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                @click="closeCancel"
              >
                Retour
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-50"
                :disabled="cancelSubmitting"
                data-testid="cancel-submit"
                @click="submitCancel"
              >
                <Loader2 v-if="cancelSubmitting" class="h-4 w-4 animate-spin" />
                Confirmer l'annulation
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ============ Correct Modal ============ -->
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
          v-if="correctTarget"
          ref="correctModalRef"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
          data-testid="admin-face-subscription-correct-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="correct-subscription-title"
          tabindex="-1"
          @click.self="closeCorrect"
          @keydown.esc="closeCorrect"
          @keydown.tab="trapModalFocus($event, correctModalRef)"
        >
          <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="border-b border-gray-100 px-6 py-4 flex items-start justify-between">
              <div>
                <h3 id="correct-subscription-title" class="text-lg font-semibold text-gray-900">
                  Corriger les dates de l'abonnement
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                  Modifiez au moins une des deux dates. La date d'expiration doit rester postérieure
                  à la date de début.
                </p>
              </div>
              <button
                type="button"
                class="text-gray-400 hover:text-gray-600"
                aria-label="Fermer"
                @click="closeCorrect"
              >
                <X class="h-5 w-5" />
              </button>
            </div>

            <div class="space-y-4 px-6 py-5">
              <div
                v-if="correctConflictMessage"
                class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                data-testid="admin-face-subscription-correct-error"
              >
                {{ correctConflictMessage }}
              </div>
              <div
                v-if="correctClientError"
                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700"
                data-testid="admin-face-subscription-correct-client-error"
              >
                {{ correctClientError }}
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="correct-notes">
                  Notes <span class="text-red-500">*</span>
                </label>
                <textarea
                  id="correct-notes"
                  v-model="correctNotes"
                  rows="3"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Raison de la correction (5 à 1000 caractères)..."
                  data-testid="correct-notes"
                />
                <p
                  v-if="correctErrors.notes?.length"
                  class="text-xs text-red-600"
                  data-testid="admin-face-subscription-correct-field-error-notes"
                >
                  {{ correctErrors.notes[0] }}
                </p>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700" for="correct-starts-at">
                    <Calendar class="inline h-4 w-4 mr-1 text-gray-400" />
                    Date de début
                  </label>
                  <input
                    id="correct-starts-at"
                    v-model="correctStartsAt"
                    type="date"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    data-testid="correct-starts-at"
                  />
                  <p class="text-xs text-gray-500">
                    Doit être entre 2020-01-01 et +10 ans.
                  </p>
                  <p
                    v-if="correctErrors.starts_at?.length"
                    class="text-xs text-red-600"
                    data-testid="admin-face-subscription-correct-field-error-starts_at"
                  >
                    {{ correctErrors.starts_at[0] }}
                  </p>
                </div>

                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700" for="correct-expires-at">
                    <Calendar class="inline h-4 w-4 mr-1 text-gray-400" />
                    Date d'expiration
                  </label>
                  <input
                    id="correct-expires-at"
                    v-model="correctExpiresAt"
                    type="date"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    data-testid="correct-expires-at"
                  />
                  <p class="text-xs text-gray-500">
                    Doit être entre 2020-01-01 et +10 ans, et postérieure à starts_at.
                  </p>
                  <p
                    v-if="correctErrors.expires_at?.length"
                    class="text-xs text-red-600"
                    data-testid="admin-face-subscription-correct-field-error-expires_at"
                  >
                    {{ correctErrors.expires_at[0] }}
                  </p>
                </div>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
              <button
                type="button"
                class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                @click="closeCorrect"
              >
                Annuler
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                :disabled="correctSubmitting"
                data-testid="correct-submit"
                @click="submitCorrect"
              >
                <Loader2 v-if="correctSubmitting" class="h-4 w-4 animate-spin" />
                Corriger
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ============ Change Tier Modal (FEATURE-FP-2.10) ============ -->
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
          v-if="changeTierTarget"
          ref="changeTierModalRef"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
          data-testid="admin-face-subscription-change-tier-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="change-tier-subscription-title"
          tabindex="-1"
          @click.self="closeChangeTier"
          @keydown.esc="closeChangeTier"
          @keydown.tab="trapModalFocus($event, changeTierModalRef)"
        >
          <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="border-b border-gray-100 px-6 py-4 flex items-start justify-between">
              <div>
                <h3
                  id="change-tier-subscription-title"
                  class="text-lg font-semibold text-gray-900"
                >
                  Changer le palier de l'abonnement
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                  Mutation du palier uniquement. Les dates de la période en cours restent
                  inchangées (utilisez « Prolonger » pour étendre la couverture, ou
                  « Corriger les dates » pour les ajuster).
                </p>
              </div>
              <button
                type="button"
                class="text-gray-400 hover:text-gray-600"
                aria-label="Fermer"
                @click="closeChangeTier"
              >
                <X class="h-5 w-5" />
              </button>
            </div>

            <div class="space-y-4 px-6 py-5">
              <div
                v-if="changeTierConflictMessage"
                class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                data-testid="admin-face-subscription-change-tier-error"
              >
                {{ changeTierConflictMessage }}
              </div>

              <dl class="grid grid-cols-1 gap-1 text-sm">
                <dt class="text-gray-500">Palier actuel</dt>
                <dd
                  class="font-medium text-gray-900"
                  data-testid="change-tier-current"
                >
                  {{ changeTierCurrentLabel }}
                </dd>
              </dl>

              <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="change-tier-new-plan">
                  Nouveau palier <span class="text-red-500">*</span>
                </label>
                <select
                  id="change-tier-new-plan"
                  v-model="changeTierNewPlan"
                  required
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  data-testid="change-tier-new-plan"
                >
                  <option
                    v-for="plan in changeTierOptions"
                    :key="plan"
                    :value="plan"
                  >
                    {{ TIER_LABEL[plan] }}
                  </option>
                </select>
                <p
                  v-if="changeTierErrors.new_plan?.length"
                  class="text-xs text-red-600"
                  data-testid="admin-face-subscription-change-tier-field-error-new_plan"
                >
                  {{ changeTierErrors.new_plan[0] }}
                </p>
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="change-tier-notes">
                  Notes <span class="text-red-500">*</span>
                </label>
                <textarea
                  id="change-tier-notes"
                  v-model="changeTierNotes"
                  rows="3"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Raison du changement de palier (5 à 1000 caractères)..."
                  data-testid="change-tier-notes"
                />
                <p
                  v-if="changeTierErrors.notes?.length"
                  class="text-xs text-red-600"
                  data-testid="admin-face-subscription-change-tier-field-error-notes"
                >
                  {{ changeTierErrors.notes[0] }}
                </p>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
              <button
                type="button"
                class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                @click="closeChangeTier"
              >
                Annuler
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                :disabled="changeTierSubmitting"
                data-testid="change-tier-submit"
                @click="submitChangeTier"
              >
                <Loader2 v-if="changeTierSubmitting" class="h-4 w-4 animate-spin" />
                Confirmer le changement
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </section>
</template>
