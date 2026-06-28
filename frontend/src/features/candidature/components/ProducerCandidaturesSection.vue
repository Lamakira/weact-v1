<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  Loader2,
  AlertCircle,
  FileText,
  ChevronLeft,
  ChevronRight,
  Inbox,
  CheckSquare,
} from 'lucide-vue-next'
import { useProducerCandidatures, useRejectCandidature, useAcceptCandidature, useReleaseCandidature } from '../composables'
import { useMissionPayment } from '@/features/mission/composables'
import ProducerCandidatureCard from './ProducerCandidatureCard.vue'
import UgcCandidaturePaymentOverlay from './UgcCandidaturePaymentOverlay.vue'
import MissionSelectionSummary from '@/features/mission/components/MissionSelectionSummary.vue'
import StatusFilter from './StatusFilter.vue'
import { useToast } from '@/composables/useToast'
import { useUgcShipment } from '@/composables/useUgcShipment'
import { UgcShipmentForm, type ConfirmShipmentPayload } from '@/components/ugc'
import { CandidatureStatusLabel } from '../types'
import type { CandidatureStatusType, ProducerCandidature } from '../types'

/**
 * Props
 */
const props = defineProps<{
  missionId: string
  missionBudget?: number
  missionStatus?: string
  nombreFacesVoulu?: number
  allowRetrySelection?: boolean
  isUgcMission?: boolean
  ugcProductName?: string | null
  ugcCompensationType?: string | null
  /** Cash par-Face d'une mission UGC hybride (aperçu pricing overlay, D-8.5.i/j). */
  missionMontantRemuneration?: number | null
}>()

const emit = defineEmits<{
  'selection-confirmed': [checkoutUrl: string]
  /**
   * Raised when a payment initiation attempt fails. The parent page can use
   * this hook to re-evaluate mission + payment state so the "Paiement en
   * attente de confirmation..." banner is never shown on a stuck-pending
   * mission. (FIX-19.3 AC #1, #4.)
   */
  'selection-failed': [message: string]
}>()

/**
 * Composable for candidatures list
 */
const {
  candidatures,
  isLoading,
  error,
  currentPage,
  lastPage,
  total,
  hasNextPage,
  hasPrevPage,
  isEmpty,
  statusFilter,
  fetchCandidatures,
  nextPage,
  prevPage,
  goToPage,
  setStatusFilter,
  refresh,
} = useProducerCandidatures(props.missionId)

/**
 * Composable for rejecting candidatures
 */
const {
  error: rejectError,
  successMessage: rejectSuccessMessage,
  rejectCandidature,
  reset: resetReject,
} = useRejectCandidature()

/**
 * Composable for accepting candidatures (UGC product-only, 8-3, D-8.3.b)
 */
const {
  error: acceptError,
  errorCode: acceptErrorCode,
  successMessage: acceptSuccessMessage,
  acceptCandidature,
  reset: resetAccept,
} = useAcceptCandidature()

/**
 * Composable for releasing accepted candidatures (UGC, 9-2, D-9.2.d)
 */
const {
  error: releaseError,
  successMessage: releaseSuccessMessage,
  releaseCandidature,
  reset: resetRelease,
} = useReleaseCandidature()

/**
 * Mission payment selection composable
 */
const {
  isConfirming,
  error: paymentError,
  pricing,
  selectedCount,
  selectedFaces,
  selectionLimitReached,
  toggleSelection,
  removeSelection,
  isSelected,
  confirmAndPay,
} = useMissionPayment(props.missionBudget ?? 0, props.missionId, props.nombreFacesVoulu)

/**
 * Whether selection mode should be active
 * Only for published missions, except the FIX-19.3 retry path where a stuck
 * pending payment must still allow the producer to reconfirm the preserved
 * selection.
 */
const isSelectionMode = computed(
  () =>
    !props.isUgcMission &&
    (props.missionStatus === 'published' || props.allowRetrySelection === true),
)

const toast = useToast()

/**
 * Card refs for resetting loading state
 */
const cardRefs = ref<Record<string, InstanceType<typeof ProducerCandidatureCard>>>({})

/**
 * Handle reject candidature
 */
async function handleReject(candidatureId: string): Promise<void> {
  const result = await rejectCandidature(candidatureId)

  // Reset the card's loading state
  cardRefs.value[candidatureId]?.resetRejecting()

  if (result) {
    toast.success(rejectSuccessMessage.value || 'Candidature refusée')
    // Refresh the list to show updated status
    await refresh()
  } else {
    toast.error(rejectError.value || 'Erreur lors du refus')
  }

  resetReject()
}

/**
 * Handle release candidature (UGC, 9-2, D-9.2.d) — calque exact de handleReject.
 * The producer manually frees a slot blocked by an accepted-but-never-reconfirmed
 * Face; the backend refunds the escrow + reopens the mission, then we refresh.
 */
async function handleRelease(candidatureId: string): Promise<void> {
  const result = await releaseCandidature(candidatureId)

  // Reset the card's loading state
  cardRefs.value[candidatureId]?.resetReleasing()

  if (result) {
    // Le message backend est inconditionnel (« ...et le règlement remboursé. »), mais
    // en produit-seul `unwindUgcCandidatureSlot` ne rembourse aucun escrow. Toast honnête,
    // calque de `releaseDialogConsequence` (D-9.2.c) : clause remboursement réservée à l'hybride.
    const successCopy =
      props.ugcCompensationType === 'hybrid'
        ? releaseSuccessMessage.value || 'La place a été libérée et le règlement remboursé.'
        : 'La place a été libérée.'
    toast.success(successCopy)
    await refresh()
  } else {
    toast.error(releaseError.value || 'Erreur lors de la libération')
  }

  resetRelease()
}

/**
 * Handle accept candidature (UGC, 8-3/8-5). Branches on compensation type:
 * - hybrid (8-5, D-8.5.h): open the FedaPay payment overlay instead of a free
 *   accept — payment (escrow) precedes acceptation; the candidature stays pending
 *   until the webhook/self-heal settles, then the list is refreshed.
 * - product-only (8-3, D-8.3.b): free direct accept (unchanged).
 */
async function handleAccept(candidatureId: string): Promise<void> {
  // Hybride : pas d'accept gratuit — l'overlay de paiement prend le relais.
  if (props.ugcCompensationType === 'hybrid') {
    const candidature = candidatures.value.find((c) => c.id === candidatureId)
    paymentTarget.value = { id: candidatureId, faceName: candidature?.face.display_name ?? '' }
    // La carte a posé isAccepting=true (handleAccept) ; libère son spinner — sinon
    // elle reste figée derrière l'overlay.
    cardRefs.value[candidatureId]?.resetAccepting()
    return
  }

  // Produit-seul : accept gratuit direct (inchangé).
  const result = await acceptCandidature(candidatureId)

  // Reset the card's loading state
  cardRefs.value[candidatureId]?.resetAccepting()

  if (result) {
    toast.success(acceptSuccessMessage.value || 'Candidature acceptée')
    await refresh()
  } else {
    toast.error(acceptError.value || "Erreur lors de l'acceptation")
    // Resync capacity / auto-close on capacity errors (AC9)
    if (['MISSION_FULL', 'ALREADY_ACCEPTED'].includes(acceptErrorCode.value ?? '')) {
      await refresh()
    }
  }

  resetAccept()
}

/**
 * UGC hybrid payment overlay (8-5, D-8.5.h) — a single instance at section level
 * (calque the shipment modal): N cards must not each mount an overlay/composable.
 * `paymentTarget` holds the candidature whose règlement is being paid.
 */
const paymentTarget = ref<{ id: string; faceName: string } | null>(null)

/** Payment confirmed → the candidature is now accepted; refresh the list. */
async function handlePaymentSuccess(): Promise<void> {
  await refresh()
}

/** Overlay closed (cancel / success / failed-dismiss) → drop the target. */
function handlePaymentOverlayClose(value: boolean): void {
  if (!value) {
    paymentTarget.value = null
  }
}

/**
 * Expédition UGC (3.2) — modal unique au niveau section : N cartes ne doivent
 * pas instancier N modals/composables (un colis PAR Face engagée, D-3.1.d).
 */
const shipmentTarget = ref<ProducerCandidature | null>(null)
const {
  isSubmitting: isSubmittingShipment,
  error: shipmentError,
  errorCode: shipmentErrorCode,
  confirmShipment,
  clearError: clearShipmentError,
} = useUgcShipment()

function openShipmentModal(candidature: ProducerCandidature): void {
  clearShipmentError()
  shipmentTarget.value = candidature
}

// Verrouille la fermeture pendant un submit en vol : sinon, fermer/rouvrir pour
// une autre candidature ferait fermer SA modal à la résolution tardive du premier appel.
function closeShipmentModal(): void {
  if (isSubmittingShipment.value) return
  shipmentTarget.value = null
}

async function handleConfirmShipment(payload: ConfirmShipmentPayload): Promise<void> {
  if (!shipmentTarget.value) return

  const shipment = await confirmShipment('candidature', shipmentTarget.value.id, payload)
  if (shipment) {
    toast.success('Expédition confirmée')
    shipmentTarget.value = null
    await refresh()
    return
  }

  if (shipmentErrorCode.value === 'ALREADY_SHIPPED') {
    // Multi-onglets (D-3.1.b/D-3.2.e) : la liste refetchée porte le shipment existant.
    toast.info(shipmentError.value || "L'expédition de ce deal a déjà été confirmée.")
    shipmentTarget.value = null
    await refresh()
    return
  }

  // Autre erreur : la modal reste ouverte, re-tentative possible.
  toast.error(shipmentError.value || "Erreur lors de la confirmation de l'expédition")
}

/**
 * Handle filter change
 */
async function handleFilterChange(status: CandidatureStatusType | ''): Promise<void> {
  await setStatusFilter(status)
}

/**
 * Handle confirm and pay.
 * On failure the user's selection is preserved (see `useMissionPayment`) and
 * the parent page is notified so it can re-evaluate whether the mission is
 * stuck in pending_payment with no trackable transaction. (FIX-19.3.)
 */
async function handleConfirmAndPay(): Promise<void> {
  const result = await confirmAndPay(props.missionId)
  if (result) {
    emit('selection-confirmed', result.checkout_url)
    return
  }

  emit('selection-failed',
    paymentError.value ?? 'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.',
  )
}

/**
 * Generate page numbers for pagination
 */
function getPageNumbers(): number[] {
  const pages: number[] = []
  const maxVisiblePages = 5
  let startPage = Math.max(1, currentPage.value - Math.floor(maxVisiblePages / 2))
  const endPage = Math.min(lastPage.value, startPage + maxVisiblePages - 1)

  if (endPage - startPage + 1 < maxVisiblePages) {
    startPage = Math.max(1, endPage - maxVisiblePages + 1)
  }

  for (let i = startPage; i <= endPage; i++) {
    if (i > 0) pages.push(i)
  }
  return pages
}

/**
 * Lifecycle
 */
onMounted(() => {
  fetchCandidatures(1)
})
</script>

<template>
  <section class="space-y-6">
    <!-- Header & Filter -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div class="space-y-1">
        <h2 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
          Candidatures
        </h2>
        <p v-if="!isLoading && !error" class="text-sm text-muted-foreground">
          {{ total }} candidature{{ total > 1 ? 's' : '' }} reçue{{ total > 1 ? 's' : '' }}
        </p>
      </div>
      <div v-if="isSelectionMode" class="flex items-center gap-2 text-sm text-muted-foreground">
        <CheckSquare class="h-4 w-4 text-primary" />
        <span>Sélectionnez les faces à retenir</span>
      </div>
    </div>

    <!-- Status Filter -->
    <StatusFilter :model-value="statusFilter" @update:model-value="handleFilterChange" />

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="flex flex-col items-center justify-center py-24 text-center"
    >
      <Loader2 class="h-12 w-12 animate-spin text-primary" />
      <p class="mt-4 text-muted-foreground">Chargement des candidatures...</p>
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-destructive/20 bg-destructive/5 px-6 py-24 text-center"
    >
      <div class="mb-4 rounded-full bg-destructive/10 p-4 text-destructive">
        <AlertCircle class="h-10 w-10" />
      </div>
      <h3 class="text-xl font-bold text-foreground">Oups ! Une erreur est survenue</h3>
      <p class="mt-2 max-w-sm text-muted-foreground">{{ error }}</p>
      <button
        type="button"
        class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-6 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90"
        @click="refresh"
      >
        Réessayer
      </button>
    </div>

    <!-- Empty State (No Candidatures at all) -->
    <div
      v-else-if="isEmpty && !statusFilter"
      class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-muted py-24 text-center"
    >
      <div class="mb-4 rounded-full bg-muted p-4">
        <Inbox class="h-10 w-10 text-muted-foreground" />
      </div>
      <h3 class="text-xl font-bold text-foreground">Aucune candidature reçue</h3>
      <p class="mt-2 max-w-xs text-muted-foreground">
        Les Faces n'ont pas encore postulé à cette mission.
      </p>
    </div>

    <!-- Empty State (Filter with no results) -->
    <div
      v-else-if="isEmpty && statusFilter"
      class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-muted py-24 text-center"
    >
      <div class="mb-4 rounded-full bg-muted p-4">
        <FileText class="h-10 w-10 text-muted-foreground" />
      </div>
      <h3 class="text-xl font-bold text-foreground">Aucun résultat</h3>
      <p class="mt-2 max-w-xs text-muted-foreground">
        Aucune candidature avec le statut
        <span class="font-semibold">"{{ CandidatureStatusLabel[statusFilter] }}"</span>
        trouvée.
      </p>
      <button
        type="button"
        class="mt-6 text-sm font-medium text-primary hover:underline"
        @click="handleFilterChange('')"
      >
        Réinitialiser le filtre
      </button>
    </div>

    <!-- Results List -->
    <template v-else>
      <!-- Cross-page selection counter + removable chips -->
      <div
        v-if="isSelectionMode && selectedCount > 0"
        class="mb-4 rounded-lg bg-primary/10 px-4 py-3 text-sm"
      >
        <div class="flex items-center gap-2 font-medium text-primary mb-2">
          <CheckSquare class="h-4 w-4" />
          <span>{{ selectedCount }}{{ nombreFacesVoulu ? ` / ${nombreFacesVoulu}` : '' }} Face(s) sélectionnée(s)</span>
        </div>
        <div class="flex flex-wrap gap-1.5">
          <span
            v-for="face in selectedFaces"
            :key="face.candidatureId"
            class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-xs font-medium text-gray-700 border border-gray-200 shadow-sm"
          >
            {{ face.label }}
            <button
              type="button"
              class="ml-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full text-gray-400 hover:bg-red-100 hover:text-red-600 transition-colors"
              @click="removeSelection(face.candidatureId)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
              </svg>
            </button>
          </span>
        </div>
      </div>
      <!-- Selection limit reached toast -->
      <div
        v-if="selectionLimitReached"
        class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-2.5 text-sm text-amber-700"
      >
        Vous avez atteint le nombre maximum de Faces pour cette mission ({{ nombreFacesVoulu }}).
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <ProducerCandidatureCard
          v-for="candidature in candidatures"
          :key="candidature.id"
          :ref="(el) => { if (el) cardRefs[candidature.id] = el as InstanceType<typeof ProducerCandidatureCard> }"
          :candidature="candidature"
          :selection-mode="isSelectionMode"
          :is-selected="isSelected(candidature.id)"
          :is-ugc-mission="isUgcMission"
          :ugc-compensation-type="ugcCompensationType"
          @reject="handleReject"
          @accept="handleAccept"
          @release="handleRelease"
          @toggle-selection="(id: string) => toggleSelection(id, candidature.face.display_name)"
          @confirm-shipment="openShipmentModal(candidature)"
        />
      </div>

      <!-- Selection Summary Panel -->
      <div v-if="isSelectionMode && pricing" class="mt-6">
        <MissionSelectionSummary
          :pricing="pricing"
          :is-confirming="isConfirming"
          @confirm="handleConfirmAndPay"
        />
      </div>

      <!-- Pagination Controls -->
      <div
        v-if="lastPage > 1"
        class="mt-8 flex items-center justify-center gap-2"
      >
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-lg border border-border bg-card p-2 text-sm transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="!hasPrevPage"
          @click="prevPage"
        >
          <ChevronLeft class="h-5 w-5" />
        </button>

        <div class="flex items-center gap-1">
          <button
            v-for="page in getPageNumbers()"
            :key="page"
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium transition-colors"
            :class="[
              page === currentPage
                ? 'bg-primary text-white'
                : 'border border-border bg-card hover:bg-muted',
            ]"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
        </div>

        <button
          type="button"
          class="inline-flex items-center justify-center rounded-lg border border-border bg-card p-2 text-sm transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="!hasNextPage"
          @click="nextPage"
        >
          <ChevronRight class="h-5 w-5" />
        </button>
      </div>
    </template>

    <!-- Overlay de paiement hybride par-Face (8-5 — un seul au niveau section) -->
    <UgcCandidaturePaymentOverlay
      v-if="paymentTarget"
      :candidature-id="paymentTarget.id"
      :face-name="paymentTarget.faceName"
      :montant-remuneration="missionMontantRemuneration ?? null"
      :model-value="true"
      @update:model-value="handlePaymentOverlayClose"
      @payment-success="handlePaymentSuccess"
    />

    <!-- Modal d'expédition UGC (3.2 — calque dialog reject ProducerCandidatureCard) -->
    <Teleport to="body">
      <div
        v-if="shipmentTarget"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        data-testid="shipment-modal"
        @click.self="closeShipmentModal()"
      >
        <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl" role="dialog" aria-modal="true">
          <p class="mb-1 text-[10px] font-bold uppercase tracking-widest text-weact">Étape 3 sur 6 · Expédition</p>
          <h3 class="text-lg font-semibold text-foreground">
            Vous envoyez à {{ shipmentTarget.face.display_name }}
          </h3>
          <p v-if="ugcProductName" class="mt-0.5 text-sm text-muted-foreground">{{ ugcProductName }}</p>
          <div class="mt-4">
            <UgcShipmentForm :is-submitting="isSubmittingShipment" @submit="handleConfirmShipment" />
          </div>
          <button
            type="button"
            class="mt-3 text-sm text-muted-foreground hover:underline disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="isSubmittingShipment"
            @click="closeShipmentModal()"
          >
            Annuler
          </button>
        </div>
      </div>
    </Teleport>
  </section>
</template>
