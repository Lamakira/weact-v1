<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { CheckCircle2, Loader2, Lock, X } from 'lucide-vue-next'
import type { SubscriptionStatus, SubscriptionPaymentState } from '@/features/face/types'

interface Props {
  status: SubscriptionStatus
  isPremium: boolean
  expiresAt: string | null
  albumUploadLimit: number
  publicAlbumPhotoLimit: number
  currentAlbumPhotoCount: number
  lockedAlbumPhotoCount: number
  hasActingVideo: boolean
  canUploadActingVideo: boolean
  isActingVideoPubliclyVisible: boolean
  canRenew: boolean
  planAmount: number
  planCurrency: string
  planIsAvailable: boolean
  isLoading: boolean
  isInitiating: boolean
  isPolling: boolean
  paymentState: SubscriptionPaymentState
  paymentError: string | null
  canInitiatePayment?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  canInitiatePayment: true,
})

const emit = defineEmits<{
  'initiate-payment': []
  'refresh-status': []
  'dismiss-error': []
}>()

const formatCurrency = (amount: number, currency: string) =>
  new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)

const formatExpiry = (iso: string | null): string | null => {
  if (!iso) return null
  try {
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    })
  } catch {
    return null
  }
}

const formattedExpiry = computed(() => formatExpiry(props.expiresAt))
const formattedPrice = computed(() =>
  formatCurrency(props.planAmount, props.planCurrency || 'XOF'),
)

const isExpiredOrCancelled = computed(
  () => props.status === 'expired' || props.status === 'cancelled',
)

function onCtaClick(): void {
  if (!props.planIsAvailable || !props.canInitiatePayment) return
  emit('initiate-payment')
}

const actingVideoStatus = computed(() => {
  if (!props.hasActingVideo) return 'Non ajoutée'
  return props.isActingVideoPubliclyVisible ? 'Publique' : 'Privée'
})

const cannotInitiateReason = computed(() => {
  if (!props.canInitiatePayment) {
    return 'Vérifiez votre email pour activer le paiement Premium.'
  }

  if (!props.planIsAvailable) {
    return "L'abonnement annuel n'est pas disponible pour le moment."
  }

  return null
})

function onRefreshClick(): void {
  if (props.isPolling) return
  emit('refresh-status')
}

function onDismissError(): void {
  emit('dismiss-error')
}
</script>

<template>
  <section
    class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6"
    data-testid="subscription-card"
  >
    <!-- Loading skeleton -->
    <div v-if="isLoading" data-testid="subscription-card-loading" class="space-y-4">
      <Skeleton class="h-6 w-40" />
      <Skeleton class="h-4 w-64" />
      <Skeleton class="h-10 w-48" />
    </div>

    <template v-else>
      <header class="flex items-start justify-between gap-4 mb-4">
        <h2 class="text-lg font-medium text-gray-900">Abonnement Premium</h2>

        <!-- Status badge -->
        <Badge
          v-if="status === 'active' && isPremium"
          variant="teal"
          data-testid="subscription-status-badge"
        >
          Premium actif
        </Badge>
        <Badge
          v-else-if="status === 'pending_payment'"
          variant="secondary"
          data-testid="subscription-status-badge"
        >
          Paiement en attente
        </Badge>
        <Badge
          v-else-if="status === 'expired'"
          variant="outline"
          class="border-red-200 text-red-600"
          data-testid="subscription-status-badge"
        >
          Expiré
        </Badge>
        <Badge
          v-else-if="status === 'cancelled'"
          variant="outline"
          class="border-red-200 text-red-600"
          data-testid="subscription-status-badge"
        >
          Annulé
        </Badge>
        <Badge
          v-else-if="status === 'failed'"
          variant="outline"
          class="border-red-200 text-red-600"
          data-testid="subscription-status-badge"
        >
          Paiement échoué
        </Badge>
        <Badge
          v-else
          variant="secondary"
          data-testid="subscription-status-badge"
        >
          Gratuit
        </Badge>
      </header>

      <!-- Payment error banner -->
      <div
        v-if="paymentError"
        class="bg-red-50 border border-red-200 p-3 rounded-md text-sm text-red-700 mb-4 flex items-start justify-between gap-3"
        data-testid="subscription-payment-error"
      >
        <span>{{ paymentError }}</span>
        <button
          type="button"
          class="text-red-700 hover:text-red-900 flex-shrink-0"
          aria-label="Fermer l'erreur"
          @click="onDismissError"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Quota summary -->
      <div class="grid gap-3 sm:grid-cols-3 mb-4 text-sm">
        <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Album</p>
          <p class="mt-1 text-gray-900">
            {{ currentAlbumPhotoCount }}/{{ albumUploadLimit }} photos
          </p>
        </div>
        <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Publiques</p>
          <p class="mt-1 text-gray-900">
            {{ publicAlbumPhotoLimit }} max
            <span v-if="lockedAlbumPhotoCount > 0" class="text-amber-700">
              · {{ lockedAlbumPhotoCount }} privée{{ lockedAlbumPhotoCount > 1 ? 's' : '' }}
            </span>
          </p>
        </div>
        <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Vidéo d'acting</p>
          <p class="mt-1 text-gray-900">{{ actingVideoStatus }}</p>
        </div>
      </div>

      <!-- ACTIVE -->
      <template v-if="status === 'active' && isPremium">
        <p
          v-if="formattedExpiry"
          class="text-sm text-gray-700 mb-4"
          data-testid="subscription-expires-at"
        >
          Expire le {{ formattedExpiry }}
        </p>
        <ul class="space-y-2 text-sm text-gray-700">
          <li class="flex items-center gap-2">
            <CheckCircle2 class="w-4 h-4 text-green-600" />
            <span>4 photos d'album publiques</span>
          </li>
          <li class="flex items-center gap-2">
            <CheckCircle2 class="w-4 h-4 text-green-600" />
            <span>Vidéo d'acting publique</span>
          </li>
          <li class="flex items-center gap-2">
            <CheckCircle2 class="w-4 h-4 text-green-600" />
            <span>Mise en avant prioritaire dans la liste publique des Faces</span>
          </li>
        </ul>
      </template>

      <!-- PENDING -->
      <template v-else-if="status === 'pending_payment'">
        <div
          class="bg-blue-50 border border-blue-200 text-blue-800 p-3 rounded-md text-sm flex items-start gap-2 mb-4"
        >
          <Loader2 class="w-4 h-4 animate-spin flex-shrink-0 mt-0.5" />
          <span>
            Votre paiement est en cours de confirmation. Cette page se mettra à jour
            automatiquement dès que Fedapay aura confirmé la transaction.
          </span>
        </div>
        <div class="flex items-center gap-3">
          <Button
            type="button"
            variant="outline"
            :disabled="isPolling"
            data-testid="subscription-refresh"
            @click="onRefreshClick"
          >
            Vérifier maintenant
          </Button>
          <span v-if="isPolling" class="text-xs text-gray-500">(en cours…)</span>
        </div>
      </template>

      <!-- EXPIRED / CANCELLED -->
      <template v-else-if="isExpiredOrCancelled">
        <p class="text-sm text-gray-700 mb-4">
          Votre abonnement annuel n'est plus actif. Vos photos 3-4 et votre vidéo
          d'acting restent enregistrées mais ne sont plus visibles publiquement.
        </p>
        <p v-if="!cannotInitiateReason" class="text-sm text-gray-700 mb-3">
          {{ formattedPrice }} / an
        </p>
        <p v-else class="text-sm italic text-gray-500 mb-3">
          {{ cannotInitiateReason }}
        </p>
        <Button
          type="button"
          variant="default"
          :disabled="!!cannotInitiateReason || isInitiating"
          data-testid="subscription-cta"
          @click="onCtaClick"
        >
          <Loader2 v-if="isInitiating" class="w-4 h-4 animate-spin" />
          Renouveler en Premium
        </Button>
      </template>

      <!-- FAILED -->
      <template v-else-if="status === 'failed'">
        <p class="text-sm text-gray-700 mb-4">
          Votre dernière tentative de paiement n'a pas abouti.
        </p>
        <p v-if="!cannotInitiateReason" class="text-sm text-gray-700 mb-3">
          {{ formattedPrice }} / an
        </p>
        <p v-else class="text-sm italic text-gray-500 mb-3">
          {{ cannotInitiateReason }}
        </p>
        <Button
          type="button"
          variant="default"
          :disabled="!!cannotInitiateReason || isInitiating"
          data-testid="subscription-cta"
          @click="onCtaClick"
        >
          <Loader2 v-if="isInitiating" class="w-4 h-4 animate-spin" />
          Réessayer le paiement
        </Button>
      </template>

      <!-- FREE (default) -->
      <template v-else>
        <ul class="space-y-2 text-sm text-gray-700 mb-4">
          <li class="flex items-center gap-2">
            <CheckCircle2 class="w-4 h-4 text-green-600" />
            <span>2 photos d'album publiques</span>
          </li>
          <li class="flex items-center gap-2">
            <CheckCircle2 class="w-4 h-4 text-green-600" />
            <span>Vidéo de présentation publique</span>
          </li>
        </ul>

        <p class="text-xs uppercase tracking-wide text-gray-500 font-medium mb-2">
          Avantages Premium
        </p>
        <ul class="space-y-2 text-sm text-gray-400 mb-4">
          <li class="flex items-center gap-2">
            <Lock class="w-4 h-4" />
            <span>4 photos d'album publiques</span>
          </li>
          <li class="flex items-center gap-2">
            <Lock class="w-4 h-4" />
            <span>Vidéo d'acting publique</span>
          </li>
          <li class="flex items-center gap-2">
            <Lock class="w-4 h-4" />
            <span>Mise en avant dans la liste publique des Faces</span>
          </li>
        </ul>

        <p v-if="!cannotInitiateReason" class="text-sm text-gray-700 mb-3">
          {{ formattedPrice }} / an
        </p>
        <p v-else class="text-sm italic text-gray-500 mb-3">
          {{ cannotInitiateReason }}
        </p>

        <Button
          type="button"
          variant="default"
          :disabled="!!cannotInitiateReason || isInitiating"
          data-testid="subscription-cta"
          @click="onCtaClick"
        >
          <Loader2 v-if="isInitiating" class="w-4 h-4 animate-spin" />
          Passer en Premium
        </Button>
      </template>
    </template>
  </section>
</template>
