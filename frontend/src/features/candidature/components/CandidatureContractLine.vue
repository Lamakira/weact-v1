<script setup lang="ts">
/**
 * Ligne d'état du contrat d'image d'une Face retenue sur une mission à conditions (Producteur).
 * Libellé et état empilés à gauche, échéance en dessous, PDF à droite sur la même ligne.
 */
import { computed, ref } from 'vue'
import { Download, Loader2 } from 'lucide-vue-next'
import { useToast } from '@/composables/useToast'
import { imageContractApi } from '@/features/imageContract/services/imageContractApi'
import { saveBlob } from '@/features/imageContract/utils/saveBlob'
import type { ImageContract } from '@/features/imageContract/types'
import { formatConfirmationDeadline } from '../utils/formatConfirmationDeadline'

const props = defineProps<{
  candidatureId: string
  /** Statut de la candidature (le « Signature attendue » ne concerne que `accepted`). */
  status: string
  /** La mission a des conditions d'image : sans contrat, une Face retenue doit encore signer. */
  hasConditions?: boolean
  contract?: ImageContract | null
  /** Échéance de confirmation (cash) ou de reconfirmation (UGC), ISO 8601. */
  deadline?: string | null
}>()

const toast = useToast()
const isDownloading = ref(false)

type Tone = 'amber' | 'green' | 'gray'

const state = computed<{ label: string; tone: Tone; pdf: boolean } | null>(() => {
  const contract = props.contract
  if (contract) {
    switch (contract.status) {
      case 'effective':
        return { label: 'Image utilisable', tone: 'green', pdf: contract.pdf_ready }
      case 'signed':
        return { label: 'Contrat signé', tone: 'green', pdf: contract.pdf_ready }
      case 'void':
        return { label: 'Contrat annulé', tone: 'gray', pdf: false }
      default:
        return { label: 'Signature attendue', tone: 'amber', pdf: false }
    }
  }
  if (props.status === 'accepted' && props.hasConditions) return { label: 'Signature attendue', tone: 'amber', pdf: false }
  return null
})

const deadlineLabel = computed(() =>
  state.value?.tone === 'amber' && !props.contract ? formatConfirmationDeadline(props.deadline) : '',
)

const toneClasses: Record<Tone, string> = {
  amber: 'text-amber-700',
  green: 'text-emerald-700',
  gray: 'text-gray-500',
}
const dotClasses: Record<Tone, string> = {
  amber: 'bg-amber-500',
  green: 'bg-emerald-500',
  gray: 'bg-gray-400',
}

async function downloadPdf(): Promise<void> {
  if (isDownloading.value) return
  isDownloading.value = true
  try {
    const blob = await imageContractApi.downloadCandidaturePdf(props.candidatureId)
    saveBlob(blob, `contrat-image-${props.candidatureId}.pdf`)
  } catch {
    toast.error("Le contrat n'a pas pu être téléchargé. Réessayez dans un instant.")
  } finally {
    isDownloading.value = false
  }
}
</script>

<template>
  <div
    v-if="state"
    class="mt-3 flex items-center justify-between gap-3 border-t border-border/50 pt-3"
    data-testid="candidature-contract-line"
  >
    <div class="min-w-0">
      <p class="text-xs text-muted-foreground">Contrat d'image</p>
      <p
        class="mt-0.5 flex items-center gap-1.5 text-xs font-semibold"
        :class="toneClasses[state.tone]"
        data-testid="contract-line-state"
        :data-tone="state.tone"
      >
        <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="dotClasses[state.tone]" aria-hidden="true" />
        <span>{{ state.label }}</span>
      </p>
      <p v-if="deadlineLabel" class="mt-0.5 text-xs text-amber-700" data-testid="contract-line-deadline">
        Avant le {{ deadlineLabel }}
      </p>
    </div>
    <button
      v-if="state.pdf"
      type="button"
      class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted disabled:opacity-50"
      :disabled="isDownloading"
      data-testid="contract-line-pdf"
      @click="downloadPdf"
    >
      <Loader2 v-if="isDownloading" :size="14" class="animate-spin" aria-hidden="true" />
      <Download v-else :size="14" aria-hidden="true" />
      PDF
    </button>
  </div>
</template>
