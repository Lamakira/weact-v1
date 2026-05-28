<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Crown, Loader2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useSubscriptionStatus } from '@/features/face/composables/useSubscriptionStatus'
import { useSubscriptionPayment } from '@/features/face/composables/useSubscriptionPayment'
import { useAuthStore } from '@/stores/auth'
import { TIER_PRESENTATION } from '@/features/face/tierPresentation'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'

const emit = defineEmits<{ 'subscription-changed': []; 'payment-cancelled': [] }>()

const router = useRouter()

const {
  current,
  cta,
  tier,
  statusValue,
  currentPlan,
  expiresAt,
  cancelledAt,
  isLoading,
  error,
  fetchStatus,
} = useSubscriptionStatus()

const {
  isInitiating,
  isPolling,
  isVerifying,
  isCancelling,
  pendingCheckoutAvailable,
  paymentState,
  error: paymentError,
  verifyPayment,
  resumePayment,
  cancelPending,
  dismissPaymentError,
} = useSubscriptionPayment()

const authStore = useAuthStore()

// P1 — defensive self-fetch (preserved from v1's patches). The shared
// createSharedCachedResource dedupes if the host (ProfileEditPage) already called.
// Round 2 P2 — swallow the rejection: fetchStatus surfaces errors via `error.value`,
// but the resource layer may throw on unexpected paths (network unreachable, fetch
// abort). Without .catch(), Vue logs an unhandled rejection on every panel mount.
fetchStatus().catch(() => {
  // Error is already surfaced via the `error` ref by the resource layer.
})

const isEmailVerified = computed(() => authStore.isEmailVerified)

// P2 — FP-2.3 forces `cta` all-false whenever a pending_payment row exists. This
// signal catches both the "free → paid" pending case AND the "active + pending
// tier-change" case (where statusValue stays 'active' but cta is forced all-false).
const hasPendingPayment = computed(() => {
  if (!current.value) return false
  const c = cta.value
  return !c.upgrade_available && !c.downgrade_available && !c.renew_available
})

// Findings #3 + Round 2 D2 — for expired/cancelled/failed historical states, the
// entitlement tier drops to 'free' but currentPlan still carries the lapsed paid
// plan. AC #5 requires the displayed name to be the lapsed plan ("Pro a expiré
// le ..."), not "Découverte". A failed payment retains the user's last paid plan
// context (Pro who tried to upgrade to Élite and failed sees "Pro" + the failed
// status line). For active states we keep the current entitlement tier.
const displayTier = computed(() => {
  if (
    (statusValue.value === 'expired' ||
      statusValue.value === 'cancelled' ||
      statusValue.value === 'failed') &&
    currentPlan.value
  ) {
    return currentPlan.value
  }
  return tier.value
})
const tierName = computed(() => TIER_PRESENTATION[displayTier.value].name)
const isElite = computed(() => displayTier.value === 'elite')

function formatDate(iso: string | null): string {
  if (!iso) return ''
  // Round 2 P7 — guard against malformed backend payloads. `new Date('garbage')`
  // returns an Invalid Date whose toLocaleDateString renders the literal string
  // "Invalid Date" — surfacing it unfiltered would put a debug string in the UI.
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })
}

// Round 2 P7 — drop the date entirely if formatDate could not produce one,
// instead of rendering "Actif jusqu'au " with a trailing space.
const statusLine = computed(() => {
  const expires = formatDate(expiresAt.value)
  const cancelled = formatDate(cancelledAt.value)
  switch (statusValue.value) {
    case 'active':
      return expires ? `Actif jusqu'au ${expires}` : 'Actif'
    case 'expired':
      return expires ? `Expiré le ${expires}` : 'Expiré'
    case 'cancelled':
      return cancelled ? `Annulé le ${cancelled}` : 'Annulé'
    case 'failed':
      return 'Dernier paiement non abouti'
    case 'free':
      return 'Offre Découverte (gratuite)'
    case 'pending_payment':
      return '' // pending banner takes over
    default:
      return ''
  }
})

const primaryCtaLabel = computed(() =>
  statusValue.value === 'free' && currentPlan.value === null
    ? 'Choisir un abonnement'
    : 'Changer de plan',
)

const primaryCtaDisabled = computed(
  () => hasPendingPayment.value || !isEmailVerified.value || isLoading.value,
)

function onChangePlanClick(): void {
  if (primaryCtaDisabled.value) return
  router.push({ name: 'pricing' })
}

function onResumeClick(): void {
  void resumePayment()
}

// Round 2 P6 — dismissing the failed banner must NOT call reset(): reset()
// clears the sessionStorage stash (per Decision #5 of the spec, the stash should
// stay for 24h so the user can retry "Continuer le paiement" after a transient
// failure or timeout). Use the surgical dismiss that only clears the error +
// resets paymentState back to 'idle'.
function onDismissPaymentError(): void {
  dismissPaymentError()
}

const cancelConfirmOpen = ref(false)

function openCancelConfirm(): void {
  cancelConfirmOpen.value = true
}

async function onCancelConfirm(): Promise<void> {
  // Close the modal before awaiting — the network round-trip is ~100-500 ms and
  // keeping the modal open during the await produces a visible mid-confirm lag.
  cancelConfirmOpen.value = false
  const ok = await cancelPending()
  if (ok) {
    emit('payment-cancelled')
  }
}

function onCancelDismiss(): void {
  cancelConfirmOpen.value = false
}

// Notify the host page (ProfileEditPage) when a resume-pending payment confirms
// so it can refresh profile-completion / dependent state.
watch(paymentState, (state) => {
  if (state === 'confirmed') {
    emit('subscription-changed')
  }
})
</script>

<template>
  <section
    data-testid="subscription-panel"
    class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6"
  >
    <!-- Loading skeleton -->
    <div
      v-if="isLoading && !current"
      data-testid="subscription-panel-loading"
      class="space-y-3"
    >
      <div class="h-5 w-32 rounded bg-gray-100 animate-pulse" />
      <div class="h-7 w-40 rounded bg-gray-100 animate-pulse" />
      <div class="h-4 w-56 rounded bg-gray-100 animate-pulse" />
      <div class="h-10 w-44 rounded bg-gray-100 animate-pulse" />
    </div>

    <!-- Error state -->
    <div
      v-else-if="error"
      data-testid="subscription-panel-error"
      class="py-6 text-center"
    >
      <p class="mb-3 text-sm text-red-700">{{ error }}</p>
      <Button type="button" variant="outline" @click="fetchStatus">Réessayer</Button>
    </div>

    <!-- Loaded -->
    <template v-else>
      <header class="mb-6">
        <h2 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-400 mb-2.5">
          Abonnement
        </h2>

        <div class="flex items-center gap-2.5">
          <div class="relative pb-1">
            <span
              class="text-xl font-bold tracking-tight text-gray-900"
              data-testid="subscription-panel-tier-name"
            >
              {{ tierName }}
            </span>
            <div
              v-if="isElite"
              class="absolute bottom-0 left-0 h-0.5 w-1/2 bg-[#198496] rounded-full"
              aria-hidden="true"
            />
          </div>
          <Crown
            v-if="isElite"
            class="h-5 w-5 text-[#198496] shrink-0"
            data-testid="subscription-panel-tier-crown"
            aria-hidden="true"
          />
        </div>

        <p
          v-if="statusLine"
          class="mt-1.5 text-sm text-gray-500 leading-snug"
          data-testid="subscription-panel-status-line"
        >
          {{ statusLine }}
        </p>
      </header>

      <!-- Email-not-verified note -->
      <div
        v-if="!isEmailVerified"
        class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
        data-testid="subscription-panel-email-note"
      >
        Vérifiez votre adresse email pour souscrire un abonnement.
      </div>

      <!-- Findings #5 — single banner cascade: waiting > failed > pending.
           Without `v-else-if` chaining the failed bloc, a timeout would render
           both the red failed banner AND the blue pending banner simultaneously
           because backend status remains 'pending_payment' until the FP-2.8.1
           stale-pending cron lands. -->

      <!-- Payment-waiting banner — session-local feedback, highest priority.
           FP-2.15.1 L2 — exposes the cancel button here too so a Face who closed
           the Fedapay tab without paying can abort immediately instead of waiting
           for the 120 s polling timeout. The composable's cancelPending aborts
           polling and flips paymentState back to 'idle' before the backend round-trip. -->
      <div
        v-if="paymentState === 'waiting'"
        class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800"
        data-testid="subscription-panel-waiting"
      >
        <div class="flex items-start gap-2">
          <Loader2 class="mt-0.5 h-4 w-4 flex-shrink-0 animate-spin" />
          <span>
            Finalisez le paiement dans l'onglet Fedapay. La confirmation s'affichera ici
            automatiquement.
          </span>
        </div>
        <div class="mt-3">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            :disabled="isInitiating || isVerifying || isCancelling"
            data-testid="subscription-panel-waiting-cancel"
            @click="openCancelConfirm"
          >
            Annuler le paiement
          </Button>
        </div>
      </div>

      <!-- Payment-failed banner — dismissible, suppresses the pending banner so
           we don't show both colors at once after a timeout. -->
      <div
        v-else-if="paymentState === 'failed' && paymentError"
        class="mb-4 flex items-start justify-between gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
        data-testid="subscription-panel-payment-error"
      >
        <span>{{ paymentError }}</span>
        <button
          type="button"
          class="flex-shrink-0 text-xs font-semibold text-red-700 underline hover:text-red-900"
          @click="onDismissPaymentError"
        >
          Fermer
        </button>
      </div>

      <!-- Pending-payment banner — covers both 'pending_payment' status AND active+pending tier change -->
      <div
        v-else-if="hasPendingPayment"
        class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800"
        data-testid="subscription-panel-pending"
      >
        <div class="flex items-start gap-2">
          <Loader2 class="mt-0.5 h-4 w-4 flex-shrink-0 animate-spin" />
          <span>
            Votre paiement est en cours de confirmation. Cette page se mettra à jour
            automatiquement dès que Fedapay aura confirmé la transaction.
          </span>
        </div>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
          <!-- Findings #4 — disable both buttons across all in-flight states
               (initiating / polling / verifying) so resume and verify cannot
               race against each other. -->
          <Button
            v-if="pendingCheckoutAvailable"
            type="button"
            variant="default"
            size="sm"
            :disabled="isInitiating || isPolling || isVerifying || isCancelling"
            data-testid="subscription-panel-resume"
            @click="onResumeClick"
          >
            Continuer le paiement
          </Button>
          <Button
            type="button"
            variant="outline"
            size="sm"
            :disabled="isInitiating || isPolling || isVerifying || isCancelling"
            data-testid="subscription-panel-verify"
            @click="verifyPayment({ manual: true })"
          >
            Vérifier maintenant
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            :disabled="isInitiating || isPolling || isVerifying || isCancelling"
            data-testid="subscription-panel-cancel"
            @click="openCancelConfirm"
          >
            Annuler le paiement
          </Button>
        </div>
        <!-- Findings #2 + Round 2 P10 — surface inline errors from both manual
             verify AND resume actions. The composable writes `error.value` on a
             manual-click failure but does NOT flip paymentState='failed' (kept
             idle for polling continuity), so the dedicated failed banner above
             doesn't render. Renamed from "verify-error" to "pending-error"
             because resumePayment's "Aucun paiement à reprendre…" landed here
             too — the previous testid was semantically misleading. -->
        <p
          v-if="paymentError && paymentState !== 'failed'"
          class="mt-3 text-xs text-red-700"
          data-testid="subscription-panel-pending-error"
        >
          {{ paymentError }}
        </p>
      </div>

      <!-- Primary CTA: routes to /pricing for tier selection + payment -->
      <Button
        type="button"
        variant="default"
        class="w-full sm:w-auto bg-[#198496] text-white hover:bg-[#146c7a] text-sm font-semibold py-2.5 px-6 rounded-md shadow-sm transition-all duration-200"
        :disabled="primaryCtaDisabled"
        data-testid="subscription-panel-change-plan"
        @click="onChangePlanClick"
      >
        {{ primaryCtaLabel }}
      </Button>
    </template>
  </section>

  <ConfirmModal
    :is-open="cancelConfirmOpen"
    title="Annuler le paiement en cours ?"
    message="Cette action annule l'initiation du paiement. Tu pourras en relancer un nouveau depuis la page Tarifs."
    confirm-text="Oui, annuler"
    cancel-text="Continuer le paiement"
    variant="warning"
    @confirm="onCancelConfirm"
    @cancel="onCancelDismiss"
  />
</template>
