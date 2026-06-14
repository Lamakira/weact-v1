<script setup lang="ts">
import { computed, ref } from 'vue'
import { AlertTriangle, BadgeCheck, Loader2, PackageCheck, Truck, UploadCloud } from 'lucide-vue-next'
import StatusPill from './StatusPill.vue'
import ChronoRing from './ChronoRing.vue'
import UgcBookingTimeline from './UgcBookingTimeline.vue'
import { tunnelStatusToPillKind, UGC_UNBOXING_DAYS, type Shipment, type UgcUploadProgress } from './ugc'
import { useChrono } from '@/composables/useChrono'

// Carte de suivi Face (écran 8A, story 3.4) — présentationnel pur : l'API,
// la modal de confirmation et les toasts vivent dans les pages (D-3.4.a).
// 4.2 : la carte émet `upload: [file]` (upload-on-select) ; la validation,
// l'appel API et les toasts restent dans les pages (D-4.2.a/D-4.2.l).
const props = withDefaults(
  defineProps<{
    shipment: Shipment
    /** Étape timeline dérivée par la page (ugcTunnelStep / ugcCandidatureTunnelStep). */
    current: number
    isSubmitting?: boolean
    isUploading?: boolean
    uploadProgress?: UgcUploadProgress | null
  }>(),
  { isSubmitting: false, isUploading: false, uploadProgress: null },
)

const emit = defineEmits<{ 'confirm-receipt': []; upload: [file: File] }>()

const pillKind = computed(() => tunnelStatusToPillKind(props.shipment.tunnel_status))
const isShipped = computed(() => props.shipment.tunnel_status === 'shipped')
// Strict `received` : aux épics 4-5 (in_review…) le chrono Unboxing n'est plus
// l'action courante — ces états auront leur UI en 4.x (D-3.4.j).
const isChronoActive = computed(
  () =>
    props.shipment.tunnel_status === 'received'
    && props.shipment.recu_le !== null
    && props.shipment.unboxing_deadline_at !== null,
)

const { progress, remainingLabel } = useChrono(
  () => props.shipment.recu_le,
  () => props.shipment.unboxing_deadline_at,
)

function formatDateTime(iso: string): string {
  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso))
}

// 4.2 — état post-upload « en attente de validation », dérivé du SEUL
// tunnel_status (aucune lecture de deliverables[] côté Face, D-4.2.e).
const isUnboxingInReview = computed(() => props.shipment.tunnel_status === 'unboxing_in_review')
const uploadPercentage = computed(() => props.uploadProgress?.percentage ?? 0)

// Dropzone : sélection/drop ⇒ émet le File brut (la validation vit dans le
// composable). isUploading garde le double-upload (D-4.2.i).
const fileInputRef = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)

function triggerFileInput(): void {
  if (props.isUploading) return
  fileInputRef.value?.click()
}
function emitFile(file: File | undefined | null): void {
  if (props.isUploading || !file) return
  emit('upload', file)
}
function handleFileSelect(event: Event): void {
  const input = event.target as HTMLInputElement
  emitFile(input.files?.[0])
  input.value = '' // permet de re-sélectionner le même fichier
}
function handleDrop(event: DragEvent): void {
  isDragging.value = false
  emitFile(event.dataTransfer?.files?.[0])
}
function handleDragOver(): void {
  if (props.isUploading) return
  isDragging.value = true
}
function handleDragLeave(): void {
  isDragging.value = false
}
</script>

<template>
  <div class="rounded-xl border border-gray-200 bg-white p-5" data-testid="ugc-face-tracking-card">
    <div class="mb-4 flex items-center justify-between">
      <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-700">
        <Truck class="h-4 w-4 text-weact" />
        Suivi du colis
      </h2>
      <StatusPill :kind="pillKind">{{ shipment.tunnel_status_label }}</StatusPill>
    </div>

    <!-- Première consommation de la variante verticale (factorisée 3.2, écran 8A).
         :overdue volontairement non bindée (defer review 3.2 — épics 4-5, D-3.4.j). -->
    <UgcBookingTimeline :current="current" variant="vertical" class="mb-4" />

    <dl class="space-y-2 border-t border-gray-100 pt-4 text-sm">
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
    </dl>

    <p v-if="shipment.note_envoi" class="mt-3 rounded-lg bg-gray-50 p-3 text-sm text-gray-700">{{ shipment.note_envoi }}</p>

    <!-- Étape 4 : « Produit reçu » (AC épic — présent avant réception) -->
    <div v-if="isShipped" class="mt-4 border-t border-gray-100 pt-4" data-testid="ugc-receipt-cta-zone">
      <p class="mb-3 text-xs text-gray-500">
        Confirme uniquement quand le produit est entre tes mains — le chrono Unboxing
        ({{ UGC_UNBOXING_DAYS }} jours) démarre immédiatement.
      </p>
      <button
        type="button"
        class="flex w-full items-center justify-center gap-2 rounded-lg bg-weact px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-weact/90 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="isSubmitting"
        data-testid="confirm-receipt-btn"
        @click="emit('confirm-receipt')"
      >
        <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
        <PackageCheck v-else class="h-4 w-4" />
        Produit reçu
      </button>
    </div>

    <!-- Étape 5 : chrono Unboxing + dropzone d'upload (écran 8A, face.jsx:341-367) -->
    <div
      v-else-if="isChronoActive"
      class="mt-4 rounded-lg border border-[rgba(25,132,150,0.3)] bg-[rgba(25,132,150,0.04)] p-3"
      data-testid="ugc-chrono-section"
    >
      <div class="flex items-start gap-3">
        <ChronoRing :progress="progress" :size="52" :stroke="5" :label="remainingLabel" sublabel="rest." />
        <div class="flex-1">
          <p class="text-[10px] font-bold uppercase tracking-widest text-weact">À faire maintenant</p>
          <p class="mt-0.5 text-sm font-semibold leading-tight text-gray-900">Uploade ta vidéo Unboxing</p>
          <p class="mt-0.5 text-[11px] text-gray-600">30-60s · format vertical 9:16</p>
          <p v-if="shipment.unboxing_deadline_at" class="mt-1 text-xs font-medium text-gray-900">
            À envoyer avant le {{ formatDateTime(shipment.unboxing_deadline_at) }}
          </p>
        </div>
      </div>

      <!-- Dropzone (8A face.jsx:350-353) — sélection/drop ⇒ émet `upload` (D-4.2.l) -->
      <div class="mt-3">
        <div
          class="relative rounded-md border-2 border-dashed bg-white p-4 text-center transition-colors"
          :class="isDragging ? 'border-weact ring-2 ring-weact/20' : 'border-weact/40'"
          role="button"
          tabindex="0"
          aria-label="Choisir une vidéo Unboxing à uploader"
          data-testid="ugc-upload-dropzone"
          @click="triggerFileInput"
          @keydown.enter.prevent="triggerFileInput"
          @keydown.space.prevent="triggerFileInput"
          @dragover.prevent="handleDragOver"
          @dragleave="handleDragLeave"
          @drop.prevent="handleDrop"
        >
          <UploadCloud class="mx-auto h-6 w-6 text-weact" />
          <p class="mt-1 text-xs font-medium text-gray-900">Choisir une vidéo</p>
          <p class="text-[10px] text-gray-500">MP4, MOV ou AVI · max 200 Mo</p>

          <div
            v-if="isUploading"
            class="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-md bg-white/90"
            data-testid="ugc-upload-progress"
          >
            <div class="h-2 w-3/4 overflow-hidden rounded-full bg-gray-200">
              <div class="h-full rounded-full bg-weact transition-all duration-300" :style="{ width: `${uploadPercentage}%` }" />
            </div>
            <p class="text-[11px] font-medium text-gray-700">{{ uploadPercentage }}% · envoi en cours…</p>
          </div>
        </div>
        <input
          ref="fileInputRef"
          type="file"
          accept="video/mp4,video/quicktime,video/x-msvideo,.mp4,.mov,.avi"
          class="hidden"
          data-testid="ugc-upload-input"
          @change="handleFileSelect"
        />
      </div>

      <div class="mt-3 flex items-start gap-2 rounded-md border border-orange-200 bg-orange-50 p-3">
        <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#EA580C]" />
        <p class="text-[11px] leading-snug text-orange-900">
          Si tu dépasses la deadline, ton compte sera <strong>automatiquement suspendu</strong> et ton abonnement bloqué.
        </p>
      </div>
    </div>

    <!-- Post-upload : livrable déposé, en attente de validation Producteur (4.2, D-4.2.e) -->
    <div
      v-else-if="isUnboxingInReview"
      class="mt-4 flex items-start gap-3 rounded-lg border border-[rgba(25,132,150,0.3)] bg-[rgba(25,132,150,0.04)] p-3"
      data-testid="ugc-deliverable-review-section"
    >
      <BadgeCheck class="mt-0.5 h-5 w-5 shrink-0 text-weact" />
      <div>
        <p class="text-sm font-semibold text-gray-900">Vidéo Unboxing déposée</p>
        <p class="mt-0.5 text-xs text-gray-600">
          En attente de validation du Producteur (sous 48&nbsp;h). Tu seras notifiée dès qu'il aura statué.
        </p>
      </div>
    </div>
  </div>
</template>
