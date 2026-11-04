<script setup lang="ts">
import { computed, ref } from 'vue'
import { Download, FileSignature, Loader2 } from 'lucide-vue-next'
import { useToast } from '@/composables/useToast'
import { dureeLabel } from '../constants'
import { imageContractApi } from '../services/imageContractApi'
import { saveBlob } from '../utils/saveBlob'
import type { ImageContract } from '../types'

const props = defineProps<{
  contract: ImageContract
  /** Booking porteur du contrat (PDF via la route booking). */
  bookingId?: string
  /** Candidature de mission porteuse du contrat (PDF via la route candidature). */
  candidatureId?: string
  /** Mission UGC produit seul : l'image devient utilisable à la réception du produit, sinon au paiement de la Face. */
  productOnly?: boolean
  role: 'face' | 'producer'
}>()

const toast = useToast()
const isDownloading = ref(false)

function formatDate(iso: string | null): string {
  if (!iso) return ''
  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(iso))
}

const usage = computed(() => {
  switch (props.contract.status) {
    case 'effective':
      return {
        label: `Autorisée depuis le ${formatDate(props.contract.effective_at)}`,
        hint: null,
        tone: 'text-green-700',
      }
    case 'void':
      return { label: 'Annulée', hint: null, tone: 'text-gray-500' }
    default:
      return {
        label: 'Pas encore autorisée',
        hint: props.candidatureId
          ? props.productOnly
            ? 'Elle devient possible à la réception du produit.'
            : 'Elle devient possible après votre paiement.'
          : props.role === 'face'
            ? 'Elle devient possible après votre paiement.'
            : 'Elle devient possible une fois la Face payée.',
        tone: 'text-amber-700',
      }
  }
})

async function downloadPdf(): Promise<void> {
  if (isDownloading.value) return
  isDownloading.value = true
  try {
    const ownerId = props.candidatureId ?? props.bookingId ?? ''
    const blob = props.candidatureId
      ? await imageContractApi.downloadCandidaturePdf(props.candidatureId)
      : await imageContractApi.downloadPdf(props.bookingId ?? '')
    saveBlob(blob, `contrat-image-${ownerId}.pdf`)
  } catch {
    toast.error("Le contrat n'a pas pu être téléchargé. Réessayez dans un instant.")
  } finally {
    isDownloading.value = false
  }
}
</script>

<template>
  <section class="rounded-[10px] border border-gray-200 bg-white p-4" data-testid="image-contract-card">
    <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700">
      <FileSignature :size="16" aria-hidden="true" />
      Contrat d'image
    </h2>

    <dl class="space-y-2 text-sm">
      <div>
        <dt class="text-xs text-gray-500">Producteur</dt>
        <dd class="font-medium text-gray-900" data-testid="contract-producer-signature">
          {{ contract.producer_signed_name }}
          <span v-if="contract.producer_signed_at" class="font-normal text-gray-500">
            , signé le {{ formatDate(contract.producer_signed_at) }}
          </span>
        </dd>
      </div>
      <div>
        <dt class="text-xs text-gray-500">Face</dt>
        <dd class="font-medium text-gray-900" data-testid="contract-face-signature">
          <template v-if="contract.face_signed_at">
            {{ contract.face_signed_name }}
            <span class="font-normal text-gray-500">, signé le {{ formatDate(contract.face_signed_at) }}</span>
          </template>
          <span v-else class="font-normal text-gray-500">En attente de signature</span>
        </dd>
      </div>
      <div v-if="contract.guardian_name">
        <dt class="text-xs text-gray-500">Parent ou tuteur</dt>
        <dd class="font-medium text-gray-900" data-testid="contract-guardian">{{ contract.guardian_name }}</dd>
      </div>
      <div class="border-t border-gray-100 pt-2">
        <dt class="text-xs text-gray-500">Conditions</dt>
        <dd class="text-gray-700" data-testid="contract-terms">
          {{ contract.finalite }}. {{ contract.supports.map((s) => s.label).join(', ') }}.
          {{ contract.territoire.label }}, {{ dureeLabel(contract.duree_annees) }}.
        </dd>
      </div>
      <div class="border-t border-gray-100 pt-2">
        <dt class="text-xs text-gray-500">Utilisation de l'image</dt>
        <dd :class="['font-semibold', usage.tone]" data-testid="contract-usage">{{ usage.label }}</dd>
        <p v-if="usage.hint" class="mt-0.5 text-xs text-gray-500" data-testid="contract-usage-hint">{{ usage.hint }}</p>
      </div>
    </dl>

    <button
      v-if="contract.pdf_ready"
      type="button"
      class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-50"
      :disabled="isDownloading"
      data-testid="contract-download"
      @click="downloadPdf"
    >
      <Loader2 v-if="isDownloading" :size="16" class="animate-spin" aria-hidden="true" />
      <Download v-else :size="16" aria-hidden="true" />
      Télécharger le contrat (PDF)
    </button>
  </section>
</template>
