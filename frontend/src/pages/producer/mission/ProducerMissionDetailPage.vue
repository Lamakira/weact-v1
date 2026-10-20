<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  AlertCircle,
  ArrowLeft,
  CalendarDays,
  CheckCircle2,
  ChevronDown,
  Coins,
  Loader2,
  MapPin,
  Pencil,
  RefreshCw,
  Trash2,
  XCircle,
} from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Skeleton } from '@/components/ui/skeleton'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import { UgcPaymentOverlay } from '@/components/ugc'
import UgcDeliverableReviewInbox from '@/components/ugc/UgcDeliverableReviewInbox.vue'
import { ProducerCandidaturesSection } from '@/features/candidature/components'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useMissionPaymentFlow } from '@/features/mission/composables/useMissionPaymentFlow'
import { useDeleteMission } from '@/features/mission/composables/useDeleteMission'
import { useCloseMission } from '@/features/mission/composables/useCloseMission'
import { useReopenMission } from '@/features/mission/composables/useReopenMission'
import { useCompleteMission } from '@/features/mission/composables/useCompleteMission'
import { getMissionActionState } from '@/features/mission/composables/missionActionState'
import DeleteMissionDialog from '@/features/mission/components/DeleteMissionDialog.vue'
import CloseMissionDialog from '@/features/mission/components/CloseMissionDialog.vue'
import ReopenMissionDialog from '@/features/mission/components/ReopenMissionDialog.vue'
import CompleteMissionDialog from '@/features/mission/components/CompleteMissionDialog.vue'
import AttendanceValidationPanel from '@/features/mission/components/AttendanceValidationPanel.vue'
import MissionStepBar from '@/features/mission/components/MissionStepBar.vue'
import MissionNextBlock from '@/features/mission/components/MissionNextBlock.vue'
import MissionPaymentCard from '@/features/mission/components/MissionPaymentCard.vue'
import MissionPaymentBanners from '@/features/mission/components/MissionPaymentBanners.vue'
import MissionDetailTabs, { type MissionTab } from '@/features/mission/components/MissionDetailTabs.vue'
import {
  buildNextBlock,
  buildPaymentCard,
  formatFcfa,
  formatShortDate,
  getCurrentStepIndex,
  getMissionSteps,
  isUgcMissionType,
  resolvePrimaryAction,
  type DetailContext,
  type PrimaryAction,
} from '@/features/mission/utils/missionDetailModel'
import { isUgcMission } from '@/features/mission/types'

// Pas de keep-alive sur cette route : KeepAliveRouterView met en cache par nom de route,
// toutes les missions partageraient la même page en cache.
defineOptions({ name: 'ProducerMissionDetailPage' })

type TabId = 'candidatures' | 'presences' | 'livrables'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const missionId = computed(() => route.params.id as string)

const sectionRef = ref<InstanceType<typeof ProducerCandidaturesSection> | null>(null)

const {
  mission,
  isLoading,
  error,
  fetchMission,
  paymentReturn,
  paymentSuccessBanner,
  paymentInitializationFailed,
  paymentResumable,
  trackablePending,
  pendingStalled,
  paymentStatusUnavailable,
  paymentDeadlineLabel,
  freedPlacesRefundLabel,
  isResumingPayment,
  isCancelSelectionDialogOpen,
  isCancellingSelection,
  handleSelectionConfirmed,
  handleResumePayment,
  handleCancelSelection,
  handleSelectionFailed,
} = useMissionPaymentFlow(missionId, {
  onRetryCandidaturePayment: (candidatureId) => {
    setTab('candidatures')
    void nextTick(() => sectionRef.value?.openCandidaturePayment(candidatureId))
  },
})

// ---------------------------------------------------------------------------
// Compte à rebours : un tic par minute, sans animation
// ---------------------------------------------------------------------------
const now = ref(new Date())
let clockTimer: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  clockTimer = setInterval(() => {
    now.value = new Date()
  }, 60_000)
})
onBeforeUnmount(() => {
  if (clockTimer !== null) clearInterval(clockTimer)
})

// ---------------------------------------------------------------------------
// Modèle de la page
// ---------------------------------------------------------------------------
const summary = computed(() => mission.value?.summary ?? null)
const isUgc = computed(() => (mission.value ? isUgcMissionType(mission.value) : false))
const actionState = computed(() =>
  mission.value ? getMissionActionState(mission.value, authStore.isEmailVerified) : null,
)

// « Reprendre le paiement » n'existe que si une transaction est vivante (hors retour checkout en cours)
// ou si la ligne de paiement est relançable.
const canResumePayment = computed(
  () =>
    mission.value?.status === 'pending_payment' &&
    (paymentResumable.value || (trackablePending.value && paymentReturn.state.value === 'idle')),
)

const detailContext = computed<DetailContext | null>(() => {
  if (!mission.value || !summary.value) return null
  return {
    mission: mission.value,
    summary: summary.value,
    emailVerified: authStore.isEmailVerified,
    payment: {
      canResume: canResumePayment.value,
      resumable: paymentResumable.value,
      initializationFailed: paymentInitializationFailed.value,
    },
  }
})

const steps = computed(() => getMissionSteps(isUgc.value))
const currentStep = computed(() => (summary.value ? getCurrentStepIndex(summary.value.step, isUgc.value) : -1))
const stepWhens = computed<(string | null)[]>(() => {
  if (!mission.value) return []
  if (isUgc.value) return steps.value.map(() => null)
  return [
    formatShortDate(mission.value.date_limite_candidature),
    null,
    null,
    formatShortDate(mission.value.date_tournage),
    null,
  ]
})

const nextBlock = computed(() => (detailContext.value ? buildNextBlock(detailContext.value) : null))
const primaryAction = computed<PrimaryAction | null>(() =>
  detailContext.value ? resolvePrimaryAction(detailContext.value) : null,
)
const paymentCard = computed(() =>
  mission.value && summary.value ? buildPaymentCard(mission.value, summary.value) : null,
)

const statusPillClass = computed(() => {
  const status = mission.value?.status
  return {
    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300': status === 'draft',
    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': status === 'published',
    'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400': status === 'pending_payment',
    'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': status === 'closed',
    'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-400': status === 'pending_attendance_validation',
    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400': status === 'completed',
  }
})

const hasPaymentBanner = computed(
  () =>
    paymentReturn.state.value !== 'idle' ||
    paymentSuccessBanner.value ||
    mission.value?.status === 'pending_payment' ||
    (mission.value?.freed_places_count ?? 0) > 0,
)

// ---------------------------------------------------------------------------
// Onglets (état dans l'URL : ?tab=candidatures|presences|livrables)
// ---------------------------------------------------------------------------
const tabs = computed<MissionTab[]>(() => {
  if (!mission.value || !summary.value) return []
  const s = summary.value
  const totalCandidatures = Object.values(s.candidatures).reduce((sum, n) => sum + n, 0)
  const list: MissionTab[] = [{ id: 'candidatures', label: 'Candidatures', count: totalCandidatures }]
  if (isUgc.value) {
    const toReview = s.ugc_deliverables?.to_review ?? 0
    list.push({ id: 'livrables', label: 'Livrables', count: toReview, hot: toReview > 0 })
  } else {
    const pending = s.step === 'attendance' ? (s.attendance?.pending ?? 0) : 0
    list.push({ id: 'presences', label: 'Présences', count: pending > 0 ? pending : null, hot: pending > 0 })
  }
  return list
})

const activeTab = computed<TabId>(() => {
  const raw = route.query.tab
  const requested = Array.isArray(raw) ? raw[0] : raw
  return (tabs.value.find((t) => t.id === requested)?.id ?? 'candidatures') as TabId
})

function setTab(id: TabId): void {
  if (route.query.tab === id) return
  void router.replace({ query: { ...route.query, tab: id } })
}

// Les panneaux restent montés une fois visités (v-show) : changer d'onglet ne perd ni la
// sélection de Faces en cours ni le formulaire de présences.
const visitedTabs = reactive(new Set<string>())
watch(activeTab, (id) => visitedTabs.add(id), { immediate: true })

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------
const isUgcPayOpen = ref(false)

// Arrivée depuis la publication d'une mission UGC (?pay={id}) : ouvre le tunnel de commission
// puis consomme le paramètre (un retour arrière ne doit pas rouvrir le tunnel). Jamais en
// même temps qu'un retour de paiement en cours de vérification.
watch(
  mission,
  (m) => {
    if (!m || route.query.pay === undefined || route.query.payment_return !== undefined) return
    if (route.query.pay !== m.id) return
    const query = { ...route.query }
    delete query.pay
    void router.replace({ query })
    if (isUgcMissionType(m) && m.status === 'pending_payment') isUgcPayOpen.value = true
  },
  { immediate: true },
)

function focusTabs(): void {
  void nextTick(() => document.getElementById('mission-tabs')?.scrollIntoView?.({ block: 'start' }))
}

function runPrimary(action: PrimaryAction): void {
  switch (action.key) {
    case 'resume-payment':
      void handleResumePayment()
      return
    case 'pay-commission':
      isUgcPayOpen.value = true
      return
    case 'validate-attendance':
      setTab('presences')
      break
    case 'review-deliverables':
      setTab('livrables')
      break
    case 'republish':
      // Nouvelle mission pré-remplie avec le brief de celle-ci (dates vides) ; l'ancienne reste clôturée.
      if (mission.value) void router.push({ name: 'publish-mission', query: { from: mission.value.id } })
      return
    case 'rate-faces':
      // Notation facultative : onglet Candidatures, focus sur le premier bouton « Noter ».
      setTab('candidatures')
      void nextTick(() => sectionRef.value?.focusFirstToRate())
      focusTabs()
      return
    default:
      // select-faces / reconfirm-selection / replace-freed-place : tout se passe dans l'onglet Candidatures.
      setTab('candidatures')
  }
  focusTabs()
}

const { deleteMission, isDeleting } = useDeleteMission()
const { closeMission, isClosing } = useCloseMission()
const { reopenMission, isReopening } = useReopenMission()
const { completeMission, isCompleting } = useCompleteMission()

const isDeleteDialogOpen = ref(false)
const isCloseDialogOpen = ref(false)
const isReopenDialogOpen = ref(false)
const isCompleteDialogOpen = ref(false)

// « Terminer » grisé avec la raison du serveur quand la mission est au stade où on le proposerait.
const completeBlocked = computed(
  () =>
    !!summary.value?.completion_blocker &&
    actionState.value?.canComplete !== true &&
    ['closed', 'pending_attendance_validation'].includes(mission.value?.status ?? ''),
)

// « Modifier la sélection » n'entre dans le menu que sur téléphone (sur ordinateur,
// c'est un bouton visible à côté) : sans autre entrée, le menu n'existe que sur téléphone.
const hasMobileOnlyMenuItem = computed(
  () => mission.value?.status === 'pending_payment' && !isUgc.value,
)

const hasDesktopMenuItems = computed(() => {
  const s = actionState.value
  if (!s) return false
  return s.canEdit || s.canClose || s.canReopen || s.canComplete || completeBlocked.value || s.canDelete
})

const hasMenu = computed(() => hasDesktopMenuItems.value || hasMobileOnlyMenuItem.value)

async function confirmDelete(): Promise<void> {
  if (!mission.value) return
  const result = await deleteMission(mission.value.id)
  isDeleteDialogOpen.value = false
  if (result.success) {
    toast.success('Mission supprimée avec succès!')
    await router.push({ name: 'producer-missions' })
  } else {
    toast.error(result.message)
  }
}

async function confirmClose(): Promise<void> {
  if (!mission.value) return
  const result = await closeMission(mission.value.id)
  isCloseDialogOpen.value = false
  if (result.success) toast.success('Mission clôturée avec succès!')
  else toast.error(result.message)
  await fetchMission()
}

async function confirmReopen(): Promise<void> {
  if (!mission.value) return
  const result = await reopenMission(mission.value.id)
  isReopenDialogOpen.value = false
  if (result.success) toast.success('Mission réouverte avec succès!')
  else toast.error(result.message)
  await fetchMission()
}

async function confirmComplete(): Promise<void> {
  if (!mission.value) return
  const result = await completeMission(mission.value.id)
  isCompleteDialogOpen.value = false
  if (result.success) toast.success('Mission marquée comme terminée!')
  else toast.error(result.message)
  await fetchMission()
}

function goBack(): void {
  void router.push({ name: 'producer-missions' })
}

function goToEdit(): void {
  if (mission.value) void router.push({ name: 'edit-mission', params: { id: mission.value.id } })
}
</script>

<template>
  <div class="mission-detail grid grid-cols-[minmax(0,1fr)] gap-4 pb-6" data-testid="producer-mission-detail">
    <!-- Retour -->
    <button
      type="button"
      class="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
      @click="goBack"
    >
      <ArrowLeft class="h-4 w-4" />
      Retour aux missions
    </button>

    <!-- Chargement initial -->
    <div v-if="!mission && isLoading" class="grid gap-3" data-testid="mission-detail-loading">
      <Skeleton class="h-6 w-1/3" />
      <Skeleton class="h-24 w-full" />
      <Skeleton class="h-64 w-full" />
    </div>

    <!-- Erreur -->
    <div v-else-if="error && !mission" class="flex items-center gap-3 text-destructive" role="alert">
      <AlertCircle class="h-5 w-5" />
      <span>{{ error }}</span>
    </div>

    <template v-else-if="mission">
      <!-- En-tête : contexte à gauche, action principale à droite -->
      <header class="grid gap-3 sm:grid-cols-[1fr_auto] sm:gap-x-5">
        <div class="min-w-0">
          <p class="text-xs text-muted-foreground" data-testid="mission-eyebrow">
            Mission {{ isUgc ? 'UGC' : 'standard' }} · {{ mission.nombre_faces_voulu }}
            {{ mission.nombre_faces_voulu > 1 ? 'Faces' : 'Face' }}
          </p>
          <h1 class="mt-0.5 text-lg font-bold leading-tight text-foreground sm:text-[22px]">{{ mission.titre }}</h1>
          <div class="mt-1.5 flex flex-wrap items-center gap-x-3.5 gap-y-1.5 text-[13px] text-muted-foreground">
            <span
              class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
              :class="statusPillClass"
              data-testid="mission-status-pill"
            >
              {{ mission.display_status_label ?? mission.status_label }}
            </span>
            <template v-if="!isUgc">
              <span v-if="formatShortDate(mission.date_tournage)" class="inline-flex items-center gap-1.5">
                <CalendarDays class="size-3.5" aria-hidden="true" />
                Tournage {{ formatShortDate(mission.date_tournage) }}
              </span>
              <span v-if="mission.lieu" class="inline-flex items-center gap-1.5">
                <MapPin class="size-3.5" aria-hidden="true" />
                {{ mission.lieu }}
              </span>
              <span class="inline-flex items-center gap-1.5">
                <Coins class="size-3.5" aria-hidden="true" />
                {{ formatFcfa(mission.budget) }} par Face
              </span>
            </template>
            <span v-else-if="mission.nom_produit" class="inline-flex items-center gap-1.5">
              <Coins class="size-3.5" aria-hidden="true" />
              {{ mission.nom_produit }}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2 sm:justify-end" data-testid="mission-head-actions">
          <!-- Secondaire (étape paiement) : changer de sélection -->
          <Button
            v-if="mission.status === 'pending_payment' && !isUgc"
            type="button"
            variant="outline"
            class="press hidden h-10 sm:inline-flex"
            data-testid="mission-payment-cancel-selection-btn"
            :disabled="isCancellingSelection"
            @click="isCancelSelectionDialogOpen = true"
          >
            <Loader2 v-if="isCancellingSelection" class="animate-spin" />
            Modifier la sélection
          </Button>

          <!-- Menu « Gérer la mission » -->
          <DropdownMenu v-if="hasMenu">
            <DropdownMenuTrigger as-child>
              <Button
                type="button"
                variant="outline"
                class="press h-10"
                :class="{ 'sm:hidden': !hasDesktopMenuItems }"
                data-testid="mission-more-menu-trigger"
              >
                Gérer la mission
                <ChevronDown class="menu-chevron" aria-hidden="true" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="min-w-52" data-testid="mission-more-menu">
              <DropdownMenuItem
                v-if="hasMobileOnlyMenuItem"
                class="min-h-10 sm:hidden"
                data-testid="mission-menu-cancel-selection"
                @select="isCancelSelectionDialogOpen = true"
              >
                <RefreshCw aria-hidden="true" />
                Modifier la sélection
              </DropdownMenuItem>
              <DropdownMenuItem
                v-if="actionState?.canEdit"
                class="min-h-10"
                data-testid="mission-menu-edit"
                @select="goToEdit"
              >
                <Pencil aria-hidden="true" />
                Modifier
              </DropdownMenuItem>
              <DropdownMenuItem
                v-if="actionState?.canClose"
                class="min-h-10"
                data-testid="mission-menu-close"
                @select="isCloseDialogOpen = true"
              >
                <XCircle aria-hidden="true" />
                Clôturer
              </DropdownMenuItem>
              <DropdownMenuItem
                v-if="actionState?.canReopen"
                class="min-h-10"
                data-testid="mission-menu-reopen"
                @select="isReopenDialogOpen = true"
              >
                <RefreshCw aria-hidden="true" />
                Rouvrir
              </DropdownMenuItem>
              <DropdownMenuItem
                v-if="actionState?.canComplete"
                class="min-h-10"
                data-testid="mission-menu-complete"
                @select="isCompleteDialogOpen = true"
              >
                <CheckCircle2 aria-hidden="true" />
                Terminer
              </DropdownMenuItem>
              <DropdownMenuItem
                v-else-if="completeBlocked"
                disabled
                class="min-h-10 flex-col items-start gap-0.5"
                data-testid="mission-menu-complete-blocked"
              >
                <span class="inline-flex items-center gap-2"><CheckCircle2 aria-hidden="true" />Terminer</span>
                <span class="max-w-64 whitespace-normal text-xs text-muted-foreground">{{ summary?.completion_blocker }}</span>
              </DropdownMenuItem>
              <template v-if="actionState?.canDelete">
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  variant="destructive"
                  class="min-h-10"
                  data-testid="mission-menu-delete"
                  @select="isDeleteDialogOpen = true"
                >
                  <Trash2 aria-hidden="true" />
                  Supprimer
                </DropdownMenuItem>
              </template>
            </DropdownMenuContent>
          </DropdownMenu>

          <!-- Action principale (une seule) : fondu avec léger flou quand elle change -->
          <Transition name="action-swap">
            <Button
              v-if="primaryAction"
              :key="primaryAction.key"
              type="button"
              class="press hidden h-10 flex-col items-center gap-0 px-4 leading-tight sm:inline-flex"
              :data-testid="primaryAction.key === 'resume-payment' ? 'mission-payment-resume-btn' : 'mission-primary-action'"
              :data-action="primaryAction.key"
              :disabled="primaryAction.key === 'resume-payment' && isResumingPayment"
              @click="runPrimary(primaryAction)"
            >
              <span class="inline-flex items-center gap-2 font-semibold">
                <Loader2 v-if="primaryAction.key === 'resume-payment' && isResumingPayment" class="animate-spin" />
                {{ primaryAction.label }}
              </span>
              <span v-if="primaryAction.hint" class="text-[11px] font-normal opacity-85">{{ primaryAction.hint }}</span>
            </Button>
          </Transition>
        </div>
      </header>

      <!-- Bandeaux de paiement (visibles quel que soit l'onglet) -->
      <MissionPaymentBanners
        v-if="hasPaymentBanner"
        :mission="mission"
        :return-state="paymentReturn.state.value"
        :payment-success="paymentSuccessBanner"
        :initialization-failed="paymentInitializationFailed"
        :resumable="paymentResumable"
        :status-unavailable="paymentStatusUnavailable"
        :pending-stalled="pendingStalled"
        :deadline-label="paymentDeadlineLabel"
        :freed-places-refund-label="freedPlacesRefundLabel"
        @retry="paymentReturn.retry"
        @dismiss="paymentReturn.dismiss"
      />

      <!-- Étapes -->
      <MissionStepBar v-if="summary && currentStep >= 0" :steps="steps" :current="currentStep" :whens="stepWhens" />

      <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
        <!-- Carte principale : onglets + panneaux -->
        <section id="mission-tabs" class="min-w-0 rounded-[10px] border border-border bg-card">
          <MissionDetailTabs :tabs="tabs" :model-value="activeTab" @update:model-value="setTab($event as TabId)" />

          <div class="p-3.5 sm:p-4">
            <Transition name="panel">
              <div
                v-show="activeTab === 'candidatures'"
                id="mission-panel-candidatures"
                role="tabpanel"
                aria-labelledby="mission-tab-candidatures"
                class="panel-body"
              >
                <ProducerCandidaturesSection
                  v-if="!isLoading && !error && visitedTabs.has('candidatures')"
                  ref="sectionRef"
                  :mission-id="missionId"
                  :mission-budget="mission.budget"
                  :mission-status="mission.status"
                  :nombre-faces-voulu="mission.nombre_faces_voulu"
                  :allow-retry-selection="mission.status === 'pending_payment' && paymentInitializationFailed && !paymentResumable"
                  :is-ugc-mission="isUgcMission(mission)"
                  :ugc-compensation-type="mission.type_compensation"
                  :ugc-product-name="mission.nom_produit"
                  :mission-montant-remuneration="mission.montant_remuneration"
                  :can-replace-freed-place="mission.can_replace_freed_place === true"
                  @freed-place-replaced="fetchMission"
                  @rated="fetchMission"
                  @selection-confirmed="handleSelectionConfirmed"
                  @selection-failed="handleSelectionFailed"
                />
              </div>
            </Transition>

            <Transition name="panel">
              <div
                v-if="!isUgc && visitedTabs.has('presences')"
                v-show="activeTab === 'presences'"
                id="mission-panel-presences"
                role="tabpanel"
                aria-labelledby="mission-tab-presences"
                class="panel-body"
              >
                <AttendanceValidationPanel
                  v-if="summary?.attendance"
                  :mission-uuid="missionId"
                  @done="fetchMission"
                />
                <p
                  v-else
                  class="rounded-lg border border-dashed border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground"
                  data-testid="mission-presences-empty"
                >
                  Les présences se valident à partir du jour du tournage, une fois la sélection payée.
                </p>
              </div>
            </Transition>

            <Transition name="panel">
              <div
                v-if="isUgc && visitedTabs.has('livrables')"
                v-show="activeTab === 'livrables'"
                id="mission-panel-livrables"
                role="tabpanel"
                aria-labelledby="mission-tab-livrables"
                class="panel-body"
              >
                <UgcDeliverableReviewInbox :mission-id="missionId" compact @changed="fetchMission" />
              </div>
            </Transition>
          </div>
        </section>

        <!-- Récapitulatif : Et ensuite ? + Paiement -->
        <aside v-if="nextBlock && paymentCard" class="grid gap-3 lg:sticky lg:top-3">
          <MissionNextBlock :content="nextBlock" :now="now" />
          <MissionPaymentCard :card="paymentCard" />
        </aside>
      </div>

      <!-- Barre d'action collée en bas (téléphone) -->
      <div
        v-if="nextBlock"
        class="action-bar sticky bottom-0 z-20 -mx-4 flex items-center gap-2 border-t border-border bg-card px-3 pt-2.5 sm:hidden"
        data-testid="mission-action-bar"
      >
        <div class="min-w-0 text-xs leading-snug text-muted-foreground">
          <strong class="block truncate text-[13px] text-foreground">{{ nextBlock.headline }}</strong>
          <span class="block truncate">{{ nextBlock.deadlineLabel ? nextBlock.deadlineLabel : nextBlock.who }}</span>
        </div>
        <Transition name="action-swap">
          <Button
            v-if="primaryAction"
            :key="primaryAction.key"
            type="button"
            class="press ml-auto h-10 shrink-0"
            data-testid="mission-action-bar-primary"
            :data-action="primaryAction.key"
            :disabled="primaryAction.key === 'resume-payment' && isResumingPayment"
            @click="runPrimary(primaryAction)"
          >
            <Loader2 v-if="primaryAction.key === 'resume-payment' && isResumingPayment" class="animate-spin" />
            {{ primaryAction.label }}
          </Button>
        </Transition>
      </div>
    </template>

    <!-- Dialogues -->
    <ConfirmModal
      :is-open="isCancelSelectionDialogOpen"
      title="Annuler le paiement en cours ?"
      message="La sélection actuelle sera annulée et vous pourrez choisir d'autres Faces. Si un paiement aboutit malgré tout, son montant sera crédité sur votre wallet WeAct."
      confirm-text="Annuler le paiement"
      cancel-text="Retour"
      variant="warning"
      :confirm-disabled="isCancellingSelection"
      @confirm="handleCancelSelection"
      @cancel="isCancelSelectionDialogOpen = false"
    />
    <DeleteMissionDialog
      :is-open="isDeleteDialogOpen"
      :mission-title="mission?.titre || ''"
      :is-loading="isDeleting"
      @cancel="isDeleteDialogOpen = false"
      @confirm="confirmDelete"
    />
    <CloseMissionDialog
      :is-open="isCloseDialogOpen"
      :mission-title="mission?.titre || ''"
      :is-loading="isClosing"
      @cancel="isCloseDialogOpen = false"
      @confirm="confirmClose"
    />
    <ReopenMissionDialog
      :is-open="isReopenDialogOpen"
      :mission-title="mission?.titre || ''"
      :is-loading="isReopening"
      @cancel="isReopenDialogOpen = false"
      @confirm="confirmReopen"
    />
    <CompleteMissionDialog
      :is-open="isCompleteDialogOpen"
      :mission-title="mission?.titre || ''"
      :is-loading="isCompleting"
      @cancel="isCompleteDialogOpen = false"
      @confirm="confirmComplete"
    />

    <!-- Commission UGC : même tunnel de paiement que la liste des missions -->
    <UgcPaymentOverlay
      v-if="mission && isUgc"
      v-model="isUgcPayOpen"
      kind="mission"
      :owner-id="mission.id"
      :amount="mission.commission_ugc ?? 0"
    />
  </div>
</template>

<style scoped>
/* Mouvement : transform / opacity / filter uniquement, courbes ease-out fortes, < 300 ms. */

/* Panneau d'onglet : fondu + léger décalage vertical à l'apparition (la sortie est immédiate). */
.panel-enter-active {
  animation: panel-in 200ms cubic-bezier(0.23, 1, 0.32, 1);
}
@keyframes panel-in {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

/* Lignes de candidatures : apparition échelonnée de 40 ms (12 premières). */
.panel-body :deep(.candidature-grid > *) {
  animation: row-in 260ms cubic-bezier(0.23, 1, 0.32, 1) both;
}
@keyframes row-in {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}
.panel-body :deep(.candidature-grid > :nth-child(2)) {
  animation-delay: 40ms;
}
.panel-body :deep(.candidature-grid > :nth-child(3)) {
  animation-delay: 80ms;
}
.panel-body :deep(.candidature-grid > :nth-child(4)) {
  animation-delay: 120ms;
}
.panel-body :deep(.candidature-grid > :nth-child(5)) {
  animation-delay: 160ms;
}
.panel-body :deep(.candidature-grid > :nth-child(6)) {
  animation-delay: 200ms;
}
.panel-body :deep(.candidature-grid > :nth-child(7)) {
  animation-delay: 240ms;
}
.panel-body :deep(.candidature-grid > :nth-child(8)) {
  animation-delay: 280ms;
}
.panel-body :deep(.candidature-grid > :nth-child(9)) {
  animation-delay: 320ms;
}
.panel-body :deep(.candidature-grid > :nth-child(10)) {
  animation-delay: 360ms;
}
.panel-body :deep(.candidature-grid > :nth-child(11)) {
  animation-delay: 400ms;
}
.panel-body :deep(.candidature-grid > :nth-child(n + 12)) {
  animation-delay: 440ms;
}

/* Changement d'action principale : fondu avec léger flou (masque le croisement des deux états). */
.action-swap-enter-active {
  animation: action-swap-in 240ms cubic-bezier(0.23, 1, 0.32, 1);
}
@keyframes action-swap-in {
  from {
    opacity: 0;
    filter: blur(3px);
    transform: translateY(2px);
  }
  to {
    opacity: 1;
    filter: blur(0);
    transform: none;
  }
}

/* Boutons : réaction à l'appui ; survol réservé aux pointeurs précis. */
.press {
  transition:
    transform 160ms cubic-bezier(0.23, 1, 0.32, 1),
    background-color 160ms ease,
    border-color 160ms ease,
    color 160ms ease;
}
.press:active:not(:disabled) {
  transform: scale(0.97);
}
@media (hover: hover) and (pointer: fine) {
  .press:hover:not(:disabled) {
    filter: brightness(1.05);
  }
}

.action-bar {
  padding-bottom: calc(0.625rem + env(safe-area-inset-bottom, 0px));
}

@media (prefers-reduced-motion: reduce) {
  .panel-enter-active,
  .action-swap-enter-active,
  .panel-body :deep(.candidature-grid > *) {
    animation-duration: 1ms;
    animation-delay: 0ms;
  }
  .press {
    transition-property: background-color, border-color, color;
  }
  .press:active:not(:disabled) {
    transform: none;
  }
  .menu-chevron {
    transition: none;
  }
}

/* Le chevron du menu « Gérer la mission » pivote à l'ouverture. */
.menu-chevron {
  transition: transform 200ms cubic-bezier(0.23, 1, 0.32, 1);
}
[data-state='open'] > .menu-chevron {
  transform: rotate(180deg);
}
</style>
