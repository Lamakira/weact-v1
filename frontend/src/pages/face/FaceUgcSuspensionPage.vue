<script setup lang="ts">
/**
 * Page de suspension / réactivation UGC (écran 10A, story 5.2). Explique à une Face
 * suspendue POURQUOI son compte est suspendu et COMMENT le réactiver. Source serveur
 * autoritative via useUgcSuspension (suspension-aware, PAS les capabilities cachées —
 * D-2.2.b). Navigation seule (Q3) : « Terminer la mission » route vers le deal existant,
 * « Contacter le support » ouvre un mailto. L'activation réelle de l'upload tardif et le
 * cycle d'appel = story 5.3.
 */
import { onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { Ban, XCircle, Loader2, AlertCircle } from 'lucide-vue-next'
import { useUgcSuspension } from '@/composables/useUgcSuspension'

const router = useRouter()
const { isSuspended, suspension, isLoading, error, fetchStatus } = useUgcSuspension()

onMounted(fetchStatus)

const deal = computed(() => suspension.value?.deal ?? null)

function formatDateTime(iso: string | null): string {
  if (!iso) return ''
  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeStyle: 'short' }).format(
    new Date(iso),
  )
}

function goToDeal(): void {
  const d = deal.value
  if (!d || !d.owner_uuid) return
  router.push(
    d.owner_kind === 'booking'
      ? { name: 'face-booking-detail', params: { id: d.owner_uuid } }
      : { name: 'face-mission-detail', params: { id: d.owner_uuid } },
  )
}

const supportMailto = `mailto:${import.meta.env.VITE_SUPPORT_EMAIL ?? 'contact@weact.bj'}`
</script>

<template>
  <div class="max-w-2xl">
    <!-- Page Header -->
    <div class="mb-6">
      <h1 class="text-xl min-[376px]:text-2xl font-bold text-slate-800">Mon compte</h1>
    </div>

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="flex items-center justify-center gap-3 rounded-xl border border-slate-200 bg-white p-10 text-slate-500"
      data-testid="ugc-suspension-loading"
    >
      <Loader2 class="w-5 h-5 animate-spin" aria-hidden="true" />
      <span class="text-sm">Chargement…</span>
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="rounded-xl border border-red-200 bg-red-50 p-6"
      role="alert"
      data-testid="ugc-suspension-error"
    >
      <div class="flex items-start gap-3">
        <AlertCircle class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" aria-hidden="true" />
        <div class="flex-1 min-w-0">
          <p class="text-sm text-red-700">{{ error }}</p>
          <button
            type="button"
            class="mt-3 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
            data-testid="ugc-suspension-retry"
            @click="fetchStatus"
          >
            Réessayer
          </button>
        </div>
      </div>
    </div>

    <!-- Suspended State (10A) -->
    <div v-else-if="isSuspended && suspension" data-testid="ugc-suspension-page" class="space-y-4">
      <!-- (a) Suspension banner -->
      <div class="rounded-xl bg-red-50 border border-red-200 p-4" role="alert">
        <div class="flex items-start gap-3">
          <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
            <Ban class="w-5 h-5 text-red-600" aria-hidden="true" />
          </div>
          <div class="flex-1 min-w-0">
            <h2 class="text-sm font-semibold text-red-800">Compte suspendu</h2>
            <p class="mt-1 text-sm text-red-700">
              Tu as dépassé une deadline UGC. Tes missions sont en pause.
            </p>
          </div>
        </div>
      </div>

      <!-- (b) Why -->
      <section data-testid="ugc-suspension-why">
        <h2 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">
          Pourquoi cette suspension ?
        </h2>
        <div class="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
          <div class="flex items-start gap-3">
            <XCircle class="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" aria-hidden="true" />
            <div class="min-w-0">
              <p class="text-sm font-semibold text-slate-900">{{ suspension.reason_label }}</p>
              <p v-if="deal && deal.missed_deadline_at" class="text-xs text-slate-500">
                {{ deal.product_name }} · Deadline dépassée le
                {{ formatDateTime(deal.missed_deadline_at) }}
              </p>
              <p v-else-if="deal" class="text-xs text-slate-500">
                {{ deal.product_name }} · Un livrable UGC n'a pas été livré dans les délais.
              </p>
              <p v-else class="text-xs text-slate-500">
                Un livrable UGC n'a pas été livré dans les délais.
              </p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <XCircle class="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" aria-hidden="true" />
            <div class="min-w-0">
              <p class="text-sm font-semibold text-slate-900">Abonnement bloqué</p>
              <p class="text-xs text-slate-500">Accès UGC suspendu</p>
            </div>
          </div>
        </div>
      </section>

      <!-- (c) How to reactivate -->
      <section data-testid="ugc-suspension-how">
        <h2 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">
          Comment réactiver
        </h2>
        <div class="rounded-xl border border-[#198496]/25 bg-[#198496]/[0.03] p-4 space-y-3">
          <div class="flex items-start gap-3">
            <span class="w-5 h-5 rounded-full bg-[#198496] text-white flex items-center justify-center text-[10px] font-bold flex-shrink-0">1</span>
            <p class="text-sm text-slate-700 leading-snug">
              Termine la mission en retard (uploade ta vidéo)
              <span class="text-xs text-slate-500">
                — possible jusqu'au {{ formatDateTime(suspension.reactivation_deadline) }} (J+30)
              </span>
            </p>
          </div>
          <div class="flex items-start gap-3">
            <span class="w-5 h-5 rounded-full bg-[#198496] text-white flex items-center justify-center text-[10px] font-bold flex-shrink-0">2</span>
            <p class="text-sm text-slate-700 leading-snug">
              Fais appel auprès de WeAct si tu as une raison valable
            </p>
          </div>
          <div class="flex items-start gap-3">
            <span class="w-5 h-5 rounded-full bg-[#198496] text-white flex items-center justify-center text-[10px] font-bold flex-shrink-0">3</span>
            <p class="text-sm text-slate-700 leading-snug">
              Ton compte est réactivé environ <strong>24 h</strong> après validation
            </p>
          </div>
        </div>
      </section>

      <!-- CTAs -->
      <div class="grid grid-cols-1 min-[376px]:grid-cols-2 gap-3 pt-1">
        <a
          :href="supportMailto"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg border border-[#198496] text-[#198496] hover:bg-[#198496]/5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
          data-testid="ugc-suspension-support"
        >
          Contacter le support
        </a>
        <button
          v-if="deal && deal.owner_uuid"
          type="button"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg bg-[#198496] text-white hover:bg-[#146c7a] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
          data-testid="ugc-suspension-terminer"
          @click="goToDeal"
        >
          Terminer la mission
        </button>
      </div>
    </div>

    <!-- Not-suspended State (deep-link / bookmark — AC7) -->
    <div
      v-else
      class="rounded-xl border border-slate-200 bg-white p-8 text-center"
      data-testid="ugc-suspension-not-suspended"
    >
      <p class="text-sm text-slate-600">Ton compte n'est pas suspendu.</p>
      <button
        type="button"
        class="mt-4 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#198496] text-white hover:bg-[#146c7a] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
        data-testid="ugc-suspension-dashboard"
        @click="router.push({ name: 'face-dashboard' })"
      >
        Retour au tableau de bord
      </button>
    </div>
  </div>
</template>
