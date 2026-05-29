<script setup lang="ts">
/**
 * FaceBillingPage
 * Onglet "Facturation & Abonnement" du dashboard Face.
 *
 * Sections :
 *  0. Bannière de paiement en attente (reprendre / vérifier / annuler) — c'est le
 *     FOYER de gestion d'un paiement non confirmé. Le dashboard affiche un nudge
 *     (PendingSubscriptionPaymentBanner) sur toutes les pages Face qui pointe ici.
 *  1. Abonnement en cours (plan, statut, dates, temps restant, "Changer de plan")
 *  2. Historique des abonnements (lignes repliables "Voir détails")
 *
 * Sources de données (composables partagés — même source que la page Tarifs et le
 * nudge dashboard, donc une seule vérité de statut) :
 *  - useSubscriptionStatus   → abonnement courant (current / cta)
 *  - useSubscriptionPayment  → flux de paiement en attente (resume / verify / cancel)
 *  - faceBillingApi.getHistory → GET /face/subscriptions/history (historique)
 */
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { CreditCard, Calendar, Check, Receipt, ChevronDown, AlertTriangle, Loader2 } from 'lucide-vue-next'
import WBadge from '@/components/ui/WBadge.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import { faceBillingApi, type FaceBillingHistoryItem } from '@/features/face/services/faceBillingApi'
import { useSubscriptionStatus } from '@/features/face/composables/useSubscriptionStatus'
import { useSubscriptionPayment } from '@/features/face/composables/useSubscriptionPayment'
import { useToast } from '@/composables/useToast'
import { getApiErrorMessage } from '@/features/auth/services/authApi'

// =============================================================
// Types (présentation)
// =============================================================
type DisplayTier = 'decouverte' | 'starter' | 'pro' | 'elite'
type CardState = 'active' | 'expiring' | 'cancelled' | 'expired' | 'free'

const EXPIRING_THRESHOLD_DAYS = 30

// Le badge W est une exclusivité Élite (capability `has_elite_badge` → Élite seul ;
// cf. grille /pricing "Badge VIP / Élite"). Les autres paliers n'en ont pas.
const TIER_META: Record<DisplayTier, { name: string; price: number; badge: 'elite' | null }> = {
  decouverte: { name: 'Découverte', price: 0, badge: null },
  starter: { name: 'Starter', price: 12000, badge: null },
  pro: { name: 'Pro', price: 25000, badge: null },
  elite: { name: 'Élite', price: 40000, badge: 'elite' },
}

// =============================================================
// Composables (shared sources of truth)
// =============================================================
const router = useRouter()
const toast = useToast()

const { current, cta, fetchStatus, refreshStatus } = useSubscriptionStatus()
const {
  isInitiating,
  isPolling,
  isVerifying,
  isCancelling,
  paymentState,
  error: paymentError,
  verifyPayment,
  resumePayment,
  cancelPending,
  dismissPaymentError,
} = useSubscriptionPayment()

// =============================================================
// Page load (status + history)
// =============================================================
const isLoading = ref(true)
const error = ref<string | null>(null)
const historyItems = ref<FaceBillingHistoryItem[]>([])

async function loadHistory(): Promise<void> {
  const response = await faceBillingApi.getHistory()
  historyItems.value = response.data
}

async function load(force = false): Promise<void> {
  isLoading.value = true
  error.value = null
  try {
    await Promise.all([force ? refreshStatus() : fetchStatus(), loadHistory()])
  } catch (err) {
    error.value = getApiErrorMessage(err)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void load()
})

// A confirmed payment mints a new active row + flips the old pending one — refresh
// the history list so it reflects the new state without a manual reload.
watch(paymentState, (state) => {
  if (state === 'confirmed') {
    toast.success('Paiement confirmé — ton abonnement est actif.')
    void loadHistory().catch(() => {
      // Non-blocking — the status card already reflects the activation.
    })
  }
})

// =============================================================
// Helpers
// =============================================================
const MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.']
function fmtDate(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`
}
function fmtFCFA(n: number): string {
  return new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'
}
function fmtAmount(n: number): string {
  return n > 0 ? fmtFCFA(n) : 'Gratuit'
}
function daysBetween(a: string | Date, b: string | Date): number {
  return Math.round((new Date(b).getTime() - new Date(a).getTime()) / 86400000)
}
function methodLabel(provider: string | null): string {
  if (provider === 'fedapay') return 'Paiement en ligne · Fedapay'
  if (!provider) return 'Activé par WeAct'
  return provider
}
function planToTier(plan: string | null): DisplayTier {
  if (plan === 'starter' || plan === 'pro' || plan === 'elite') return plan
  return 'decouverte'
}

// =============================================================
// Pending payment (resume / verify / cancel home)
// =============================================================
// FP-2.3 forces every CTA false while a payment is pending — broader than
// statusValue === 'pending_payment' (also catches an active + pending tier-change).
const hasPendingPayment = computed(() => {
  if (!current.value) return false
  const c = cta.value
  return !c.upgrade_available && !c.downgrade_available && !c.renew_available
})

function onResumeClick(): void {
  void resumePayment()
}

const cancelConfirmOpen = ref(false)
function openCancelConfirm(): void {
  cancelConfirmOpen.value = true
}
async function onCancelConfirm(): Promise<void> {
  cancelConfirmOpen.value = false
  const ok = await cancelPending()
  if (ok) {
    toast.success('Paiement annulé.')
  }
}
function onCancelDismiss(): void {
  cancelConfirmOpen.value = false
}

// =============================================================
// Current subscription card (from useSubscriptionStatus.current)
// =============================================================
const displayTier = computed<DisplayTier>(() => {
  const c = current.value
  if (!c) return 'decouverte'
  if ((c.status === 'cancelled' || c.status === 'expired' || c.status === 'failed') && c.plan) {
    return c.plan
  }
  return c.tier === 'free' ? 'decouverte' : c.tier
})
const currentMeta = computed(() => TIER_META[displayTier.value])

const startDate = computed(() => current.value?.starts_at ?? null)
const endDate = computed(() => current.value?.expires_at ?? null)
const hasPeriod = computed(() => !!(startDate.value && endDate.value))

const totalDays = computed(() =>
  hasPeriod.value ? Math.max(1, daysBetween(startDate.value!, endDate.value!)) : 0,
)
const elapsedDays = computed(() =>
  hasPeriod.value
    ? Math.max(0, Math.min(totalDays.value, daysBetween(startDate.value!, new Date())))
    : 0,
)
const remainingDays = computed(() => Math.max(0, totalDays.value - elapsedDays.value))
const progressPct = computed(() =>
  totalDays.value > 0
    ? Math.max(0, Math.min(100, Math.round((elapsedDays.value / totalDays.value) * 100)))
    : 0,
)

const cardState = computed<CardState>(() => {
  const s = current.value?.status
  if (s === 'cancelled') return 'cancelled'
  if (s === 'expired') return 'expired'
  if (s === 'active') return remainingDays.value <= EXPIRING_THRESHOLD_DAYS ? 'expiring' : 'active'
  // 'free' | 'pending_payment' | 'failed' | undefined → pas de plan payant actif
  return 'free'
})

// Backend contract: cancellation revokes premium IMMEDIATELY (a Cancelled row
// fails scopeActive's `status = Active` check regardless of its future expires_at
// — pinned by FaceSubscriptionRegressionMatrixTest). So a cancelled/expired plan
// has NO remaining access period: no "access until" date, no countdown bar.
const showTimeBar = computed(() => cardState.value === 'active' || cardState.value === 'expiring')

const subscribedTile = computed(() => (startDate.value ? fmtDate(startDate.value) : null))
const endTile = computed<{ label: string; value: string } | null>(() => {
  switch (cardState.value) {
    case 'cancelled': {
      const d = fmtDate(current.value?.cancelled_at ?? null)
      return d ? { label: 'Annulé le', value: d } : null
    }
    case 'expired': {
      const d = fmtDate(endDate.value)
      return d ? { label: 'Expiré le', value: d } : null
    }
    case 'active':
    case 'expiring': {
      const d = fmtDate(endDate.value)
      return d ? { label: 'Se termine le', value: d } : null
    }
    default:
      return null
  }
})

const statusPill = computed(() => {
  switch (cardState.value) {
    case 'cancelled':
      return { label: 'Annulé', bg: '#FEF2F2', fg: '#B91C1C', dot: '#DC2626' }
    case 'expiring':
      return { label: 'Expire bientôt', bg: '#FFF7ED', fg: '#C2410C', dot: '#EA580C' }
    case 'expired':
      return { label: 'Expiré', bg: '#F3F4F6', fg: '#6B7280', dot: '#9CA3AF' }
    case 'free':
      return { label: 'Découverte', bg: '#F3F4F6', fg: '#6B7280', dot: '#9CA3AF' }
    default:
      return { label: 'Actif', bg: '#ECFDF5', fg: '#047857', dot: '#10B981' }
  }
})

const primaryCtaLabel = computed(() => (cardState.value === 'free' ? 'Choisir un abonnement' : 'Changer de plan'))

const infoNote = computed<{ tone: 'positive' | 'warning'; text: string }>(() => {
  const end = fmtDate(endDate.value)
  switch (cardState.value) {
    case 'cancelled': {
      const cd = fmtDate(current.value?.cancelled_at ?? null)
      return {
        tone: 'warning',
        text: cd
          ? `Abonnement annulé le ${cd}. Ton profil est repassé en offre Découverte (gratuite).`
          : 'Abonnement annulé. Ton profil est repassé en offre Découverte (gratuite).',
      }
    }
    case 'expired':
      return {
        tone: 'warning',
        text: end
          ? `Ton abonnement a expiré le ${end}. Ton profil est repassé en offre Découverte (gratuite).`
          : 'Ton abonnement a expiré. Ton profil est repassé en offre Découverte (gratuite).',
      }
    case 'free':
      return {
        tone: 'positive',
        text: 'Tu es en offre Découverte (gratuite). Choisis un abonnement pour débloquer plus de visibilité et les missions UGC.',
      }
    default:
      return {
        tone: cardState.value === 'expiring' ? 'warning' : 'positive',
        text: end
          ? `Abonnement annuel — il prend fin le ${end}. Pense à le renouveler avant cette date pour conserver tes avantages.`
          : 'Abonnement annuel sans engagement.',
      }
  }
})

function goToChangePlan(): void {
  router.push({ name: 'pricing' })
}

// =============================================================
// History
// =============================================================
const HISTORY_STATUS: Record<'expired' | 'cancelled', { label: string; bg: string; fg: string }> = {
  expired: { label: 'Expiré', bg: '#F3F4F6', fg: '#6B7280' },
  cancelled: { label: 'Annulé', bg: '#FEF2F2', fg: '#B91C1C' },
}

interface DisplayHistoryItem {
  id: string
  tier: DisplayTier
  name: string
  badge: 'elite' | null
  startDate: string | null
  endDate: string | null
  status: 'expired' | 'cancelled'
  amount: string
  method: string
  ref: string
}

// Best-effort dedup: the current card already highlights the representative row,
// so when that row is itself terminal (cancelled / expired) we drop its twin from
// the history list. subscription-status exposes no row id, so we match on the
// (status, plan, expires_at) tuple.
function isCurrentRepresentative(item: FaceBillingHistoryItem): boolean {
  const c = current.value
  if (!c) return false
  return c.status === item.status && c.plan === item.plan && c.expires_at === item.expires_at
}

const historyRows = computed<DisplayHistoryItem[]>(() =>
  historyItems.value
    .filter((it): it is FaceBillingHistoryItem & { status: 'expired' | 'cancelled' } =>
      it.status === 'expired' || it.status === 'cancelled',
    )
    .filter((it) => !isCurrentRepresentative(it))
    .map((it) => {
      const tier = planToTier(it.plan)
      return {
        id: it.id,
        tier,
        name: TIER_META[tier].name,
        badge: TIER_META[tier].badge,
        startDate: it.starts_at,
        endDate: it.expires_at,
        status: it.status,
        amount: fmtAmount(it.paid_amount ?? TIER_META[tier].price),
        method: methodLabel(it.provider),
        ref: it.provider_reference ?? '—',
      }
    }),
)

const openId = ref<string | null>(null)
function toggleHistory(id: string): void {
  openId.value = openId.value === id ? null : id
}
</script>

<template>
  <div data-testid="face-billing-page">
    <!-- Page header -->
    <div class="mb-8">
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Facturation &amp; Abonnement</h1>
      <p class="text-sm text-gray-500 mt-1.5">Gère ton abonnement, consulte ton historique et tes reçus.</p>
    </div>

    <!-- Loading skeleton -->
    <div v-if="isLoading && !current" data-testid="billing-loading" class="flex flex-col gap-6">
      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-8 space-y-4">
        <div class="h-5 w-40 rounded bg-gray-100 animate-pulse" />
        <div class="h-7 w-32 rounded bg-gray-100 animate-pulse" />
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="h-16 rounded-xl bg-gray-100 animate-pulse" />
          <div class="h-16 rounded-xl bg-gray-100 animate-pulse" />
          <div class="h-16 rounded-xl bg-gray-100 animate-pulse" />
        </div>
        <div class="h-2 w-full rounded-full bg-gray-100 animate-pulse" />
      </div>
    </div>

    <!-- Error state -->
    <div
      v-else-if="error"
      data-testid="billing-error"
      class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 sm:px-8 py-10 text-center"
    >
      <p class="text-sm text-red-700 mb-3">{{ error }}</p>
      <button
        type="button"
        class="text-sm font-semibold text-[#198496] border border-[#198496] hover:bg-[#198496]/5 px-5 py-2.5 rounded-md transition-colors"
        @click="load(true)"
      >
        Réessayer
      </button>
    </div>

    <div v-else class="flex flex-col gap-6">
      <!-- ===================================================== -->
      <!-- SECTION 0 — Pending payment (resume / verify / cancel) -->
      <!-- Banner cascade: waiting > failed > pending.            -->
      <!-- ===================================================== -->
      <div
        v-if="paymentState === 'waiting'"
        class="rounded-2xl border border-blue-200 bg-blue-50 p-4 sm:p-5 text-sm text-blue-800"
        data-testid="billing-banner-waiting"
      >
        <div class="flex items-start gap-2">
          <Loader2 class="mt-0.5 h-4 w-4 flex-shrink-0 animate-spin" />
          <span>
            Finalise le paiement dans l'onglet Fedapay. La confirmation s'affichera ici
            automatiquement.
          </span>
        </div>
        <div class="mt-3">
          <button
            type="button"
            class="text-sm font-semibold px-4 py-2 rounded-md text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            :disabled="isInitiating || isVerifying || isCancelling"
            data-testid="billing-banner-waiting-cancel"
            @click="openCancelConfirm"
          >
            Annuler le paiement
          </button>
        </div>
      </div>

      <div
        v-else-if="paymentState === 'failed' && paymentError"
        class="flex items-start justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 sm:p-5 text-sm text-red-700"
        data-testid="billing-banner-failed"
      >
        <span>{{ paymentError }}</span>
        <button
          type="button"
          class="flex-shrink-0 text-xs font-semibold text-red-700 underline hover:text-red-900"
          data-testid="billing-banner-failed-dismiss"
          @click="dismissPaymentError"
        >
          Fermer
        </button>
      </div>

      <div
        v-else-if="hasPendingPayment"
        class="rounded-2xl border border-blue-200 bg-blue-50 p-4 sm:p-5 text-sm text-blue-800"
        data-testid="billing-banner-pending"
      >
        <div class="flex items-start gap-2">
          <Loader2 class="mt-0.5 h-4 w-4 flex-shrink-0 animate-spin" />
          <span>
            Ton paiement est en attente de confirmation. Reprends-le ou vérifie son état ci-dessous.
          </span>
        </div>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
          <button
            type="button"
            class="text-sm font-semibold px-4 py-2 rounded-md bg-[#198496] text-white hover:bg-[#146c7a] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            :disabled="isInitiating || isPolling || isVerifying || isCancelling"
            data-testid="billing-banner-resume"
            @click="onResumeClick"
          >
            Continuer le paiement
          </button>
          <button
            type="button"
            class="text-sm font-semibold px-4 py-2 rounded-md border border-[#198496] text-[#198496] hover:bg-[#198496]/5 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            :disabled="isInitiating || isPolling || isVerifying || isCancelling"
            data-testid="billing-banner-verify"
            @click="verifyPayment({ manual: true })"
          >
            Vérifier maintenant
          </button>
          <button
            type="button"
            class="text-sm font-semibold px-4 py-2 rounded-md text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            :disabled="isInitiating || isPolling || isVerifying || isCancelling"
            data-testid="billing-banner-cancel"
            @click="openCancelConfirm"
          >
            Annuler le paiement
          </button>
        </div>
        <p
          v-if="paymentError && paymentState !== 'failed'"
          class="mt-3 text-xs text-red-700"
          data-testid="billing-banner-pending-error"
        >
          {{ paymentError }}
        </p>
      </div>

      <!-- ===================================================== -->
      <!-- SECTION 1 — Abonnement en cours                       -->
      <!-- ===================================================== -->
      <section
        class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden"
        data-testid="current-subscription"
      >
        <div
          class="px-6 sm:px-8 pt-6 sm:pt-7"
          style="background: linear-gradient(180deg, rgba(25,132,150,0.05), rgba(25,132,150,0))"
        >
          <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="flex flex-col gap-1">
              <span class="text-xs font-semibold text-[#198496] uppercase tracking-[0.12em]">Abonnement en cours</span>
              <div class="flex items-center gap-2.5 mt-1 flex-wrap">
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight" data-testid="current-tier-name">{{ currentMeta.name }}</h2>
                <WBadge v-if="currentMeta.badge" :size="20" :tier="currentMeta.badge" :title="`Membre ${currentMeta.name}`" />
                <span
                  class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full"
                  :style="{ background: statusPill.bg, color: statusPill.fg }"
                  data-testid="current-status-pill"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :style="{ background: statusPill.dot }"></span>
                  {{ statusPill.label }}
                </span>
              </div>
            </div>
            <button
              @click="goToChangePlan"
              class="text-sm font-semibold text-white bg-[#198496] hover:bg-[#146c7a] px-5 py-2.5 rounded-md transition-colors shrink-0"
              data-testid="change-plan-button"
            >
              {{ primaryCtaLabel }}
            </button>
          </div>
        </div>

        <div class="px-6 sm:px-8 py-6 sm:py-7">
          <!-- Price + dates -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="flex items-center gap-3 p-4 rounded-xl bg-gray-50/60">
              <div class="w-10 h-10 rounded-lg bg-[#198496]/10 flex items-center justify-center text-[#198496]">
                <CreditCard :size="18" />
              </div>
              <div>
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Montant</p>
                <p class="text-base font-bold text-gray-900 mt-0.5">
                  <template v-if="currentMeta.price > 0">
                    {{ fmtFCFA(currentMeta.price) }}<span class="text-xs font-medium text-gray-400"> / an</span>
                  </template>
                  <template v-else>Gratuit</template>
                </p>
              </div>
            </div>
            <div v-if="subscribedTile" class="flex items-center gap-3 p-4 rounded-xl bg-gray-50/60">
              <div class="w-10 h-10 rounded-lg bg-[#198496]/10 flex items-center justify-center text-[#198496]">
                <Calendar :size="18" />
              </div>
              <div>
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Souscrit le</p>
                <p class="text-base font-bold text-gray-900 mt-0.5">{{ subscribedTile }}</p>
              </div>
            </div>
            <div v-if="endTile" class="flex items-center gap-3 p-4 rounded-xl bg-gray-50/60">
              <div class="w-10 h-10 rounded-lg bg-[#198496]/10 flex items-center justify-center text-[#198496]">
                <Calendar :size="18" />
              </div>
              <div>
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">{{ endTile.label }}</p>
                <p class="text-base font-bold text-gray-900 mt-0.5">{{ endTile.value }}</p>
              </div>
            </div>
          </div>

          <!-- Time remaining bar — only for a live period (active / expiring). A
               cancelled or expired plan has no remaining premium access. -->
          <div v-if="showTimeBar">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-medium text-gray-500">Temps restant sur la période</span>
              <span class="text-xs font-semibold text-gray-900">{{ remainingDays }} jours restants</span>
            </div>
            <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :style="{ width: progressPct + '%', background: cardState === 'expiring' ? '#EA580C' : '#198496' }"
              ></div>
            </div>
            <div class="flex items-center justify-between mt-2">
              <span class="text-[11px] text-gray-400">{{ fmtDate(startDate) }}</span>
              <span class="text-[11px] text-gray-400">{{ fmtDate(endDate) }}</span>
            </div>
          </div>

          <!-- Info note (pas d'auto-renew) -->
          <div
            class="mt-5 flex items-center gap-2 text-xs"
            :class="infoNote.tone === 'warning' ? '' : 'text-gray-500'"
            :style="infoNote.tone === 'warning' ? 'color: #C2410C' : ''"
            data-testid="current-info-note"
          >
            <Check v-if="infoNote.tone === 'positive'" :size="14" class="text-green-500 shrink-0" />
            <AlertTriangle v-else :size="14" class="shrink-0" />
            <span>{{ infoNote.text }}</span>
          </div>
        </div>
      </section>

      <!-- ===================================================== -->
      <!-- SECTION 2 — Historique                                -->
      <!-- ===================================================== -->
      <section
        class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 sm:px-8 py-6 sm:py-7"
        data-testid="history-section"
      >
        <div class="mb-5">
          <h2 class="text-lg font-bold text-gray-900">Historique des abonnements</h2>
          <p class="text-sm text-gray-500 mt-0.5">Tes abonnements passés et leurs détails de facturation.</p>
        </div>

        <div v-if="historyRows.length === 0" class="text-center py-10 text-sm text-gray-400" data-testid="history-empty">
          Aucun abonnement passé pour le moment.
        </div>

        <div v-else class="flex flex-col gap-2.5">
          <div
            v-for="item in historyRows"
            :key="item.id"
            class="border border-gray-200 rounded-xl overflow-hidden transition-all"
            :data-testid="`history-row-${item.id}`"
          >
            <button
              @click="toggleHistory(item.id)"
              class="w-full flex items-center gap-4 px-4 sm:px-5 py-4 hover:bg-gray-50/60 transition-colors text-left"
              :aria-expanded="openId === item.id"
            >
              <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 shrink-0">
                <Receipt :size="18" />
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-semibold text-gray-900">{{ item.name }}</span>
                  <WBadge v-if="item.badge" :size="14" :tier="item.badge" :title="item.name" />
                </div>
                <p class="text-xs text-gray-500 mt-0.5">{{ fmtDate(item.startDate) }} — {{ fmtDate(item.endDate) }}</p>
              </div>
              <div class="hidden sm:block text-sm font-semibold text-gray-900 shrink-0">{{ item.amount }}</div>
              <span
                class="text-[11px] font-semibold px-2.5 py-1 rounded-full shrink-0"
                :style="{ background: HISTORY_STATUS[item.status].bg, color: HISTORY_STATUS[item.status].fg }"
              >
                {{ HISTORY_STATUS[item.status].label }}
              </span>
              <span class="flex items-center gap-1 text-xs font-medium text-[#198496] shrink-0">
                <span class="hidden sm:inline">Voir détails</span>
                <ChevronDown :size="16" :style="{ transform: openId === item.id ? 'rotate(180deg)' : 'none', transition: 'transform 200ms' }" />
              </span>
            </button>

            <div v-if="openId === item.id" class="px-4 sm:px-5 pb-5 pt-1 border-t border-gray-100 bg-gray-50/30">
              <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 mt-4">
                <div class="flex items-center justify-between sm:block">
                  <dt class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Plan</dt>
                  <dd class="text-sm font-medium text-gray-900 sm:mt-0.5">{{ item.name }}</dd>
                </div>
                <div class="flex items-center justify-between sm:block">
                  <dt class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Montant payé</dt>
                  <dd class="text-sm font-medium text-gray-900 sm:mt-0.5">{{ item.amount }}</dd>
                </div>
                <div class="flex items-center justify-between sm:block">
                  <dt class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Date de début</dt>
                  <dd class="text-sm font-medium text-gray-900 sm:mt-0.5">{{ fmtDate(item.startDate) }}</dd>
                </div>
                <div class="flex items-center justify-between sm:block">
                  <dt class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Date de fin</dt>
                  <dd class="text-sm font-medium text-gray-900 sm:mt-0.5">{{ fmtDate(item.endDate) }}</dd>
                </div>
                <div class="flex items-center justify-between sm:block">
                  <dt class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Moyen de paiement</dt>
                  <dd class="text-sm font-medium text-gray-900 sm:mt-0.5">{{ item.method }}</dd>
                </div>
                <div class="flex items-center justify-between sm:block">
                  <dt class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Référence transaction</dt>
                  <dd class="text-xs font-mono font-medium text-gray-900 sm:mt-0.5">{{ item.ref }}</dd>
                </div>
              </dl>
            </div>
          </div>
        </div>
      </section>
    </div>

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
  </div>
</template>
