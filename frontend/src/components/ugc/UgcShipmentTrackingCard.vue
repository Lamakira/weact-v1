<script setup lang="ts">
import { computed } from 'vue'
import { Package, Truck } from 'lucide-vue-next'
import StatusPill from './StatusPill.vue'
import ProductPhotoGallery from './ProductPhotoGallery.vue'
import { tunnelStatusToPillKind, type Shipment } from './ugc'

const props = defineProps<{ shipment: Shipment }>()

const pillKind = computed(() => tunnelStatusToPillKind(props.shipment.tunnel_status))

const destinataireLine = computed(() =>
  [props.shipment.destinataire.nom, props.shipment.destinataire.ville, props.shipment.destinataire.pays]
    .filter((part): part is string => !!part)
    .join(' · '),
)

function formatDateTime(iso: string): string {
  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso))
}
</script>

<template>
  <div class="rounded-xl border border-gray-200 bg-white p-5" data-testid="ugc-shipment-tracking-card">
    <div class="mb-3 flex items-center justify-between">
      <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-700">
        <Truck class="h-4 w-4 text-weact" />
        Expédition
      </h2>
      <StatusPill :kind="pillKind">{{ shipment.tunnel_status_label }}</StatusPill>
    </div>

    <dl class="space-y-2 text-sm">
      <div class="flex justify-between">
        <dt class="text-gray-500">Transporteur</dt>
        <dd class="font-medium text-gray-900">{{ shipment.transporteur }}</dd>
      </div>
      <div class="flex justify-between">
        <dt class="text-gray-500">Numéro de suivi</dt>
        <dd class="font-medium text-gray-900">{{ shipment.numero_suivi }}</dd>
      </div>
      <div class="flex justify-between">
        <dt class="text-gray-500">Expédié le</dt>
        <dd class="text-gray-900">{{ formatDateTime(shipment.shipped_at) }}</dd>
      </div>
      <div v-if="shipment.recu_le" class="flex justify-between">
        <dt class="text-gray-500">Reçu le</dt>
        <dd class="text-gray-900">{{ formatDateTime(shipment.recu_le) }}</dd>
      </div>
      <div class="flex justify-between gap-4">
        <dt class="shrink-0 text-gray-500">Destinataire</dt>
        <dd class="text-right text-gray-900">{{ destinataireLine }}</dd>
      </div>
    </dl>

    <p v-if="shipment.note_envoi" class="mt-3 rounded-lg bg-gray-50 p-3 text-sm text-gray-700">{{ shipment.note_envoi }}</p>

    <!-- Preuve « produit reçu » (spec réception) : photos jointes par la Face, URLs
         signées. Tolère l'absence (shipments pré-deploy : aucune section). -->
    <ProductPhotoGallery
      v-if="shipment.reception_photos?.length"
      class="mt-4 border-t border-gray-100 pt-4"
      title="Photos du produit reçu"
      :photos="shipment.reception_photos"
    />

    <p
      v-if="shipment.tunnel_status === 'shipped'"
      class="mt-3 flex items-center gap-1.5 border-t border-gray-100 pt-3 text-[11px] text-gray-500"
      data-testid="chrono-reminder"
    >
      <Package class="h-3 w-3 text-weact" />
      Le chrono démarrera quand la Face confirmera la réception du produit.
    </p>
  </div>
</template>
