<script setup lang="ts">
/**
 * Dashboard-wide « Compte suspendu » banner shown on every Face page while a soft
 * UGC suspension is active (écran 10A, story 5.2). Self-fetches its state via
 * useUgcSuspension (server-authoritative, suspension-aware — D-2.2.b), mirroring the
 * PendingSubscriptionPaymentBanner pattern: FaceLayout only adds one template line.
 *
 * Renders nothing until the fetch resolves (no optimistic flash).
 */
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Ban } from 'lucide-vue-next'
import { useUgcSuspension } from '@/composables/useUgcSuspension'

const router = useRouter()
const { isSuspended, fetchStatus } = useUgcSuspension()

onMounted(fetchStatus)
</script>

<template>
  <div
    v-if="isSuspended"
    class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6"
    role="alert"
    data-testid="ugc-suspension-banner"
  >
    <div class="flex items-start gap-3">
      <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
        <Ban class="w-5 h-5 text-red-600" aria-hidden="true" />
      </div>
      <div class="flex-1 min-w-0">
        <h3 class="text-sm font-semibold text-red-800">Compte suspendu</h3>
        <p class="mt-1 text-sm text-red-700">
          Tu as dépassé une deadline UGC. Tes missions sont en pause et ton accès UGC est bloqué.
        </p>
        <div class="mt-3">
          <button
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors"
            data-testid="ugc-suspension-banner-cta"
            @click="router.push({ name: 'face-ugc-suspension' })"
          >
            Voir les détails
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
