<script setup lang="ts">
import { Video, Package, Coins, Film, Wallet } from 'lucide-vue-next'
import { FloatingField } from '@/components/ui/form'
import { CompensationToggle, type UgcCompensationType } from '@/components/ugc'

defineProps<{
  compensationType: UgcCompensationType
  nomProduit: string
  valeurProduit: number | string | undefined
  nombreVideos: number | string | undefined
  montantRemuneration: number | string | undefined
  nomProduitError?: string
  valeurProduitError?: string
  nombreVideosError?: string
  montantRemunerationError?: string
}>()

const emit = defineEmits<{
  'update:compensationType': [value: UgcCompensationType]
  'update:nomProduit': [value: string | number]
  'update:valeurProduit': [value: string | number]
  'update:nombreVideos': [value: string | number]
  'update:montantRemuneration': [value: string | number]
}>()
</script>

<template>
  <div class="space-y-4 rounded-xl bg-weact/[0.025] p-4" data-testid="ugc-booking-fields">
    <!-- Section header -->
    <div class="flex items-center gap-2">
      <Video :size="14" class="text-weact" />
      <div class="text-[10px] font-bold uppercase tracking-widest text-weact">Détails UGC</div>
      <div class="h-px flex-1 bg-weact/15" />
    </div>

    <!-- Type de compensation -->
    <div>
      <p class="mb-1.5 text-xs font-medium text-gray-600">Type de compensation</p>
      <CompensationToggle
        :model-value="compensationType"
        @update:model-value="emit('update:compensationType', $event)"
      />
    </div>

    <!-- Nom du produit -->
    <FloatingField
      id="nom_produit"
      :model-value="nomProduit"
      label="Nom du produit"
      :icon="Package"
      :error="nomProduitError"
      required
      @update:model-value="emit('update:nomProduit', $event)"
    />

    <!-- Valeur marchande -->
    <FloatingField
      id="valeur_produit"
      :model-value="valeurProduit ?? ''"
      type="number"
      label="Valeur marchande (FCFA)"
      :icon="Coins"
      :error="valeurProduitError"
      required
      @update:model-value="emit('update:valeurProduit', $event)"
    />

    <!-- Nombre de vidéos -->
    <div v-if="compensationType === 'product'">
      <p class="mb-1.5 text-xs font-medium text-gray-600">Nombre de vidéos</p>
      <div
        class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900"
      >
        <span class="font-semibold">2 vidéos</span>
        <span class="text-[11px] font-medium text-gray-500">1 Unboxing + 1 Avis</span>
      </div>
    </div>
    <FloatingField
      v-else
      id="nombre_videos"
      :model-value="nombreVideos ?? ''"
      type="number"
      label="Nombre de vidéos"
      :icon="Film"
      :error="nombreVideosError"
      required
      @update:model-value="emit('update:nombreVideos', $event)"
    />

    <!-- Montant de la rémunération Face (hybride uniquement) -->
    <FloatingField
      v-if="compensationType === 'hybrid'"
      id="montant_remuneration"
      :model-value="montantRemuneration ?? ''"
      type="number"
      label="Montant de la rémunération Face"
      :icon="Wallet"
      :error="montantRemunerationError"
      required
      @update:model-value="emit('update:montantRemuneration', $event)"
    />
  </div>
</template>
