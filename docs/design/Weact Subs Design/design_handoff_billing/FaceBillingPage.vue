<script setup lang="ts">
/**
 * FaceBillingPage
 * Onglet "Facturation & Abonnement" du dashboard Face.
 *
 * Trois sections :
 *  1. Abonnement en cours (statut, dates, temps restant, "Changer de plan")
 *  2. Historique des abonnements (lignes repliables "Voir détails")
 *  3. Annuler le plan (+ modal de confirmation)
 *
 * NOTE INTÉGRATION : les données sont mockées ici (currentSubscription, history).
 * Brancher sur l'API d'abonnement Face réelle :
 *  - GET  /face/subscription          → abonnement courant
 *  - GET  /face/subscriptions/history → historique
 *  - POST /face/subscription/cancel   → annulation
 *  - changement de plan → redirige vers /pricing (ou tunnel de paiement)
 */
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  CreditCard,
  Calendar,
  Check,
  Receipt,
  ChevronDown,
  FileText,
  AlertTriangle,
  X,
} from 'lucide-vue-next'
import WBadge from '@/components/ui/WBadge.vue'

// =============================================================
// Types
// =============================================================
type Tier = 'decouverte' | 'starter' | 'pro' | 'elite'
type SubStatus = 'active' | 'expiring' | 'cancelled'
type HistoryStatus = 'expired' | 'cancelled' | 'completed'

interface CurrentSubscription {
  tier: Tier
  status: SubStatus
  startDate: string // ISO
  endDate: string // ISO
  autoRenew: boolean
}

interface HistoryItem {
  id: string
  tier: Tier
  startDate: string
  endDate: string
  status: HistoryStatus
  method: string
  ref: string
}

// =============================================================
// Tier metadata (aligné sur la grille tarifaire /pricing)
// =============================================================
const TIER_META: Record<Tier, { name: string; price: number; badge: 'pro' | 'elite' | null }> = {
  decouverte: { name: 'Découverte', price: 0, badge: null },
  starter: { name: 'Starter', price: 12000, badge: null },
  pro: { name: 'Pro', price: 25000, badge: 'pro' },
  elite: { name: 'Élite', price: 40000, badge: 'elite' },
}

// =============================================================
// Mock data — à remplacer par les appels API
// =============================================================
const currentSubscription = ref<CurrentSubscription>({
  tier: 'pro',
  status: 'active',
  startDate: '2026-01-15',
  endDate: '2027-01-15',
  autoRenew: true,
})

const history = ref<HistoryItem[]>([
  { id: 'h2', tier: 'starter', startDate: '2025-01-12', endDate: '2026-01-12', status: 'completed', method: 'Mobile Money · Moov', ref: 'WACT-2025-1B7K92' },
  { id: 'h1', tier: 'starter', startDate: '2024-01-12', endDate: '2025-01-12', status: 'expired', method: 'Mobile Money · MTN', ref: 'WACT-2024-0X8F31' },
  { id: 'h0', tier: 'decouverte', startDate: '2023-09-03', endDate: '2024-01-12', status: 'expired', method: 'Gratuit', ref: '—' },
])

// =============================================================
// Helpers
// =============================================================
const MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.']
function fmtDate(iso: string): string {
  const d = new Date(iso)
  return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`
}
function fmtFCFA(n: number): string {
  return new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'
}
function daysBetween(a: string | Date, b: string | Date): number {
  return Math.round((new Date(b).getTime() - new Date(a).getTime()) / 86400000)
}

// =============================================================
// Current subscription computed
// =============================================================
const router = useRouter()

const currentMeta = computed(() => TIER_META[currentSubscription.value.tier])
const isCancelled = computed(() => currentSubscription.value.status === 'cancelled')
const isExpiring = computed(() => currentSubscription.value.status === 'expiring')

const totalDays = computed(() =>
  daysBetween(currentSubscription.value.startDate, currentSubscription.value.endDate),
)
const elapsedDays = computed(() =>
  Math.max(0, Math.min(totalDays.value, daysBetween(currentSubscription.value.startDate, new Date()))),
)
const remainingDays = computed(() => Math.max(0, totalDays.value - elapsedDays.value))
const progressPct = computed(() =>
  Math.max(0, Math.min(100, Math.round((elapsedDays.value / totalDays.value) * 100))),
)

const statusPill = computed(() => {
  if (isCancelled.value) return { label: 'Annulé', bg: '#FEF2F2', fg: '#B91C1C', dot: '#DC2626' }
  if (isExpiring.value) return { label: 'Expire bientôt', bg: '#FFF7ED', fg: '#C2410C', dot: '#EA580C' }
  return { label: 'Actif', bg: '#ECFDF5', fg: '#047857', dot: '#10B981' }
})

// =============================================================
// History expand/collapse
// =============================================================
const openId = ref<string | null>(null)
function toggleHistory(id: string): void {
  openId.value = openId.value === id ? null : id
}

const HISTORY_STATUS: Record<HistoryStatus, { label: string; bg: string; fg: string }> = {
  expired: { label: 'Expiré', bg: '#F3F4F6', fg: '#6B7280' },
  cancelled: { label: 'Annulé', bg: '#FEF2F2', fg: '#B91C1C' },
  completed: { label: 'Terminé', bg: '#F3F4F6', fg: '#6B7280' },
}

// =============================================================
// Actions
// =============================================================
const showCancelModal = ref(false)

function goToChangePlan(): void {
  // Redirige vers la grille tarifaire (ou un tunnel de paiement dédié)
  router.push({ name: 'pricing' })
}

function confirmCancel(): void {
  // TODO: POST /face/subscription/cancel
  currentSubscription.value.status = 'cancelled'
  currentSubscription.value.autoRenew = false
  showCancelModal.value = false
}

function reactivate(): void {
  // TODO: POST /face/subscription/reactivate
  currentSubscription.value.status = 'active'
  currentSubscription.value.autoRenew = true
}
</script>

<template>
  <div data-testid="face-billing-page">
    <!-- Page header -->
    <div class="mb-8">
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Facturation &amp; Abonnement</h1>
      <p class="text-sm text-gray-500 mt-1.5">Gère ton abonnement, consulte ton historique et tes reçus.</p>
    </div>

    <div class="flex flex-col gap-6">
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
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ currentMeta.name }}</h2>
                <WBadge v-if="currentMeta.badge" :size="20" :tier="currentMeta.badge" :title="`Membre ${currentMeta.name}`" />
                <span
                  class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full"
                  :style="{ background: statusPill.bg, color: statusPill.fg }"
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
              Changer de plan
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
                  {{ fmtFCFA(currentMeta.price) }}<span class="text-xs font-medium text-gray-400"> / an</span>
                </p>
              </div>
            </div>
            <div class="flex items-center gap-3 p-4 rounded-xl bg-gray-50/60">
              <div class="w-10 h-10 rounded-lg bg-[#198496]/10 flex items-center justify-center text-[#198496]">
                <Calendar :size="18" />
              </div>
              <div>
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Souscrit le</p>
                <p class="text-base font-bold text-gray-900 mt-0.5">{{ fmtDate(currentSubscription.startDate) }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3 p-4 rounded-xl bg-gray-50/60">
              <div class="w-10 h-10 rounded-lg bg-[#198496]/10 flex items-center justify-center text-[#198496]">
                <Calendar :size="18" />
              </div>
              <div>
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                  {{ isCancelled ? "Accès jusqu'au" : 'Se termine le' }}
                </p>
                <p class="text-base font-bold text-gray-900 mt-0.5">{{ fmtDate(currentSubscription.endDate) }}</p>
              </div>
            </div>
          </div>

          <!-- Time remaining bar -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-medium text-gray-500">
                {{ isCancelled ? "Période d'accès restante" : 'Temps restant sur la période' }}
              </span>
              <span class="text-xs font-semibold text-gray-900">{{ remainingDays }} jours restants</span>
            </div>
            <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :style="{ width: progressPct + '%', background: isExpiring || isCancelled ? '#EA580C' : '#198496' }"
              ></div>
            </div>
            <div class="flex items-center justify-between mt-2">
              <span class="text-[11px] text-gray-400">{{ fmtDate(currentSubscription.startDate) }}</span>
              <span class="text-[11px] text-gray-400">{{ fmtDate(currentSubscription.endDate) }}</span>
            </div>
          </div>

          <!-- Renewal note -->
          <div v-if="!isCancelled" class="mt-5 flex items-center gap-2 text-xs text-gray-500">
            <Check :size="14" class="text-green-500" />
            <span v-if="currentSubscription.autoRenew">
              Renouvellement automatique activé — tu seras prélevé de {{ fmtFCFA(currentMeta.price) }} le {{ fmtDate(currentSubscription.endDate) }}.
            </span>
            <span v-else>
              Renouvellement automatique désactivé — ton abonnement prendra fin le {{ fmtDate(currentSubscription.endDate) }}.
            </span>
          </div>
          <div v-else class="mt-5 flex items-center gap-2 text-xs" style="color: #C2410C">
            <AlertTriangle :size="14" />
            <span>
              Abonnement annulé. Tu gardes l'accès complet jusqu'au {{ fmtDate(currentSubscription.endDate) }}, puis ton profil basculera en offre Découverte.
            </span>
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

        <div v-if="history.length === 0" class="text-center py-10 text-sm text-gray-400">
          Aucun abonnement passé pour le moment.
        </div>

        <div v-else class="flex flex-col gap-2.5">
          <div
            v-for="item in history"
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
                  <span class="text-sm font-semibold text-gray-900">{{ TIER_META[item.tier].name }}</span>
                  <WBadge v-if="TIER_META[item.tier].badge" :size="14" :tier="TIER_META[item.tier].badge!" :title="TIER_META[item.tier].name" />
                </div>
                <p class="text-xs text-gray-500 mt-0.5">{{ fmtDate(item.startDate) }} — {{ fmtDate(item.endDate) }}</p>
              </div>
              <div class="hidden sm:block text-sm font-semibold text-gray-900 shrink-0">{{ fmtFCFA(TIER_META[item.tier].price) }}</div>
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
                  <dd class="text-sm font-medium text-gray-900 sm:mt-0.5">{{ TIER_META[item.tier].name }}</dd>
                </div>
                <div class="flex items-center justify-between sm:block">
                  <dt class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Montant payé</dt>
                  <dd class="text-sm font-medium text-gray-900 sm:mt-0.5">{{ fmtFCFA(TIER_META[item.tier].price) }}</dd>
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
              <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
                <span class="text-xs text-gray-400">Reçu disponible au format PDF</span>
                <button class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#198496] hover:text-[#146c7a] transition-colors">
                  <FileText :size="14" /> Télécharger le reçu
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===================================================== -->
      <!-- SECTION 3 — Annuler le plan                           -->
      <!-- ===================================================== -->
      <section
        class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 sm:px-8 py-6"
        data-testid="cancel-section"
      >
        <div class="flex items-center justify-between gap-6 flex-wrap">
          <div class="max-w-xl">
            <h2 class="text-base font-bold text-gray-900">
              {{ isCancelled ? 'Abonnement annulé' : "Annuler l'abonnement" }}
            </h2>
            <p class="text-sm text-gray-500 mt-1 leading-relaxed">
              <template v-if="isCancelled">
                Ton abonnement ne sera pas renouvelé. Tu conserves l'accès complet jusqu'au {{ fmtDate(currentSubscription.endDate) }}.
              </template>
              <template v-else>
                Si tu annules, tu conserves l'accès complet à ton plan jusqu'à la fin de ta période de facturation.
              </template>
            </p>
          </div>
          <button
            v-if="isCancelled"
            @click="reactivate"
            class="text-sm font-semibold text-[#198496] border border-[#198496] hover:bg-[#198496]/5 px-5 py-2.5 rounded-md transition-colors shrink-0"
          >
            Réactiver
          </button>
          <button
            v-else
            @click="showCancelModal = true"
            class="text-sm font-semibold px-5 py-2.5 rounded-md border border-red-600 text-red-600 hover:bg-red-50 transition-colors shrink-0"
            data-testid="cancel-button"
          >
            Annuler
          </button>
        </div>
      </section>
    </div>

    <!-- ===================================================== -->
    <!-- Modal — Confirmation d'annulation                     -->
    <!-- ===================================================== -->
    <Teleport to="body">
      <div
        v-if="showCancelModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="background: rgba(15,20,25,0.45)"
        @click.self="showCancelModal = false"
      >
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 sm:p-7">
          <div class="flex items-start justify-between gap-4 mb-5">
            <h3 class="text-lg font-bold text-gray-900">Annuler ton abonnement ?</h3>
            <button @click="showCancelModal = false" class="text-gray-400 hover:text-gray-600 transition-colors shrink-0">
              <X :size="20" />
            </button>
          </div>
          <div class="flex items-start gap-3 p-4 rounded-xl mb-5" style="background: #FFF7ED">
            <AlertTriangle :size="18" style="color: #EA580C; margin-top: 1px; flex-shrink: 0" />
            <p class="text-sm leading-relaxed" style="color: #9A3412">
              Tu conserveras l'accès complet à toutes les fonctionnalités de ton plan jusqu'au
              <strong>{{ fmtDate(currentSubscription.endDate) }}</strong>. Après cette date, ton profil basculera
              automatiquement en offre Découverte gratuite et tu perdras l'accès aux missions UGC.
            </p>
          </div>
          <div class="flex items-center justify-end gap-3">
            <button
              @click="showCancelModal = false"
              class="text-sm font-semibold text-gray-700 hover:bg-gray-100 px-5 py-2.5 rounded-md transition-colors"
            >
              Garder mon plan
            </button>
            <button
              @click="confirmCancel"
              class="text-sm font-semibold text-white bg-red-600 hover:bg-red-700 px-5 py-2.5 rounded-md transition-colors"
            >
              Confirmer l'annulation
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
