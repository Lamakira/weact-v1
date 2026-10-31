<script setup lang="ts">
import { MapPin } from 'lucide-vue-next'

/**
 * Consigne de tête de discussion UGC : la Face envoie son adresse de livraison
 * dans le fil tant que le produit n'est pas expédié. Simple encadré, aucun message créé.
 */
defineProps<{
  role: 'face' | 'producer'
  /** Nom de l'autre partie (Producteur pour la Face, Face pour le Producteur). */
  otherPartyName: string
}>()
</script>

<template>
  <div
    class="mx-3 mt-3 flex items-start gap-3 rounded-lg border border-weact-100 bg-weact-50 px-4 py-3 lg:mx-5"
    data-testid="delivery-address-hint"
  >
    <MapPin class="mt-0.5 size-4 shrink-0 text-weact-600" />
    <div class="min-w-0">
      <p class="text-[12.5px] font-semibold text-ink">Première étape</p>
      <p v-if="role === 'face'" class="mt-0.5 text-[12.5px] text-ink-2">
        Envoyez votre adresse de livraison à {{ otherPartyName }} : quartier, point de repère et numéro à joindre pour la livraison.
      </p>
      <p v-else class="mt-0.5 text-[12.5px] text-ink-2">
        {{ otherPartyName }} va vous envoyer son adresse de livraison ici. Confirmez l'expédition sur WeAct une fois le produit envoyé.
      </p>
    </div>
  </div>
</template>
