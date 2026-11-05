<script setup lang="ts">
import { MapPin } from 'lucide-vue-next'

/**
 * Consigne de tête de discussion UGC : la Face envoie son adresse de livraison
 * dans le fil tant que le produit n'est pas expédié. Encadré sans message créé ; côté Face, un bouton propose d'envoyer sa position.
 */
withDefaults(
  defineProps<{
    role: 'face' | 'producer'
    /** Nom de l'autre partie (Producteur pour la Face, Face pour le Producteur). */
    otherPartyName: string
    /** Faux quand la saisie est indisponible (chat en lecture seule, chargement) : pas de bouton. */
    canShareLocation?: boolean
  }>(),
  { canShareLocation: true },
)

defineEmits<{ (e: 'share-location'): void }>()
</script>

<template>
  <div
    class="mx-3 mt-3 flex items-start gap-3 rounded-lg border border-weact-100 bg-weact-50 px-4 py-3 lg:mx-5"
    data-testid="delivery-address-hint"
  >
    <MapPin class="mt-0.5 size-4 shrink-0 text-weact-600" />
    <div class="min-w-0">
      <p class="text-[12.5px] font-semibold text-ink">Première étape</p>
      <template v-if="role === 'face'">
        <p class="mt-0.5 text-[12.5px] text-ink-2">
          Envoyez votre adresse de livraison à {{ otherPartyName }} : quartier, point de repère et numéro à joindre pour la livraison.
        </p>
        <button
          v-if="canShareLocation"
          type="button"
          class="mt-2 inline-flex min-h-11 items-center gap-1.5 rounded-[10px] bg-white px-3.5 text-[12.5px] font-semibold text-weact-700 ring-1 ring-weact-200 hover:bg-weact-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600"
          data-testid="hint-share-location"
          @click="$emit('share-location')"
        >
          <MapPin class="size-4" aria-hidden="true" />
          Envoyer ma position
        </button>
      </template>
      <p v-else class="mt-0.5 text-[12.5px] text-ink-2">
        {{ otherPartyName }} va vous envoyer son adresse de livraison ici. Confirmez l'expédition sur WeAct une fois le produit envoyé.
      </p>
    </div>
  </div>
</template>
