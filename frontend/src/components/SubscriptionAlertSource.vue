<script setup lang="ts">
/**
 * Source d'alerte « abonnement » de la barre d'alertes (composant sans rendu).
 *
 * Monte useSubscriptionReconciler (lecture du statut + vérification automatique d'un
 * paiement en attente). Il est isolé dans son propre composant pour que la barre puisse
 * ne pas le monter sur les routes qui portent leur propre surface d'abonnement
 * (`meta.ownSubscriptionSurface` : Facturation, Tarifs) — sinon le polling de
 * vérification doublonnerait avec celui de la page.
 */
import { computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useSubscriptionReconciler } from '@/features/face/composables/useSubscriptionReconciler'
import type { AccountAlert } from './accountAlerts'

const emit = defineEmits<{
  change: [alert: AccountAlert | null]
}>()

const router = useRouter()
const { hasPendingPayment, paymentFailed, dismissFailure } = useSubscriptionReconciler()

const alert = computed<AccountAlert | null>(() => {
  if (hasPendingPayment.value) {
    return {
      id: 'payment-pending',
      tone: 'warn',
      title: 'Paiement d\'abonnement en attente.',
      message: 'Votre paiement n\'a pas encore été confirmé. Reprenez-le pour activer votre abonnement.',
      actionLabel: 'Reprendre le paiement',
      onAction: () => { void router.push({ name: 'face-billing' }) },
    }
  }
  if (paymentFailed.value) {
    return {
      id: 'payment-failed',
      tone: 'danger',
      title: 'Paiement non abouti.',
      message: 'Votre dernier paiement d\'abonnement n\'a pas abouti. Vous pouvez réessayer.',
      actionLabel: 'Réessayer',
      onAction: () => { void router.push({ name: 'pricing' }) },
      secondaryLabel: 'Ignorer',
      onSecondary: dismissFailure,
    }
  }
  return null
})

watch(alert, (value) => emit('change', value), { immediate: true })
</script>

<template>
  <span v-if="false" />
</template>
