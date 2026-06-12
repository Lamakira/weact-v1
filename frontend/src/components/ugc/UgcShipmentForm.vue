<script setup lang="ts">
import { computed, ref } from 'vue'
import { Info, Loader2 } from 'lucide-vue-next'
import { UGC_CARRIER_CHIPS, ugcShipmentSchema, type ConfirmShipmentPayload } from './ugc'

withDefaults(defineProps<{ isSubmitting?: boolean }>(), { isSubmitting: false })

const emit = defineEmits<{ submit: [payload: ConfirmShipmentPayload] }>()

const selectedChip = ref<string | null>(null)
const transporteurLibre = ref('')
const numeroSuivi = ref('')
const noteEnvoi = ref('')
const fieldErrors = ref<Partial<Record<'transporteur' | 'numero_suivi' | 'note_envoi', string>>>({})

const isAutre = computed(() => selectedChip.value === 'Autre')
const transporteurValue = computed(() =>
  isAutre.value ? transporteurLibre.value : (selectedChip.value ?? ''),
)

function selectChip(chip: string): void {
  selectedChip.value = chip
  if (!isAutre.value) transporteurLibre.value = ''
  // Une erreur « transporteur obligatoire » d'un submit raté est obsolète dès qu'un chip est choisi.
  if (fieldErrors.value.transporteur) {
    fieldErrors.value = { ...fieldErrors.value, transporteur: undefined }
  }
}

function handleSubmit(): void {
  const parsed = ugcShipmentSchema.safeParse({
    transporteur: transporteurValue.value,
    numero_suivi: numeroSuivi.value,
    note_envoi: noteEnvoi.value,
  })

  if (!parsed.success) {
    const errors: typeof fieldErrors.value = {}
    for (const issue of parsed.error.issues) {
      const field = issue.path[0] as keyof typeof errors
      if (!errors[field]) errors[field] = issue.message
    }
    fieldErrors.value = errors
    return
  }

  fieldErrors.value = {}
  const payload: ConfirmShipmentPayload = {
    transporteur: parsed.data.transporteur,
    numero_suivi: parsed.data.numero_suivi,
  }
  if (parsed.data.note_envoi) payload.note_envoi = parsed.data.note_envoi

  emit('submit', payload)
}
</script>

<template>
  <div class="space-y-4">
    <!-- Transporteur (chips, écran 4A) -->
    <div>
      <label class="mb-1.5 block text-sm font-medium text-gray-700">
        Transporteur <span class="text-red-500">*</span>
      </label>
      <div class="grid grid-cols-4 gap-2">
        <button
          v-for="chip in UGC_CARRIER_CHIPS"
          :key="chip"
          type="button"
          class="rounded-md border px-3 py-2.5 text-xs transition-colors"
          :class="
            selectedChip === chip
              ? 'border-weact bg-weact font-medium text-white'
              : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'
          "
          :data-testid="`carrier-chip-${chip}`"
          @click="selectChip(chip)"
        >
          {{ chip }}
        </button>
      </div>
      <input
        v-if="isAutre"
        v-model="transporteurLibre"
        type="text"
        maxlength="100"
        placeholder="Nom du transporteur"
        class="mt-2 w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-weact focus:outline-none focus:ring-2 focus:ring-weact/20"
        data-testid="carrier-free-text"
      />
      <p v-if="fieldErrors.transporteur" class="mt-1 text-xs text-red-600">{{ fieldErrors.transporteur }}</p>
    </div>

    <!-- Numéro de suivi -->
    <div>
      <label for="numero-suivi" class="mb-1.5 block text-sm font-medium text-gray-700">
        Numéro de suivi <span class="text-red-500">*</span>
        <span class="ml-1 font-normal text-gray-400">· Visible par la Face</span>
      </label>
      <input
        id="numero-suivi"
        v-model="numeroSuivi"
        type="text"
        maxlength="100"
        class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-weact focus:outline-none focus:ring-2 focus:ring-weact/20"
        data-testid="tracking-number-input"
      />
      <p v-if="fieldErrors.numero_suivi" class="mt-1 text-xs text-red-600">{{ fieldErrors.numero_suivi }}</p>
    </div>

    <!-- Notes pour la Face -->
    <div>
      <label for="note-envoi" class="mb-1.5 block text-sm font-medium text-gray-700">
        Notes pour la Face <span class="font-normal text-gray-400">· Optionnel</span>
      </label>
      <textarea
        id="note-envoi"
        v-model="noteEnvoi"
        rows="2"
        maxlength="500"
        class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-weact focus:outline-none focus:ring-2 focus:ring-weact/20"
        data-testid="note-envoi-input"
      />
      <p v-if="fieldErrors.note_envoi" class="mt-1 text-xs text-red-600">{{ fieldErrors.note_envoi }}</p>
    </div>

    <!-- Rappel chrono (AC épic) + CTA -->
    <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-4">
      <p class="flex items-center gap-1.5 text-[11px] text-gray-500" data-testid="chrono-reminder-hint">
        <Info class="h-3 w-3 shrink-0 text-weact" />
        Le chrono démarrera quand la Face cliquera sur « Produit reçu »
      </p>
      <button
        type="button"
        class="flex shrink-0 items-center gap-2 rounded-lg bg-weact px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-weact/90 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="isSubmitting"
        data-testid="confirm-shipment-btn"
        @click="handleSubmit"
      >
        <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
        Confirmer l'envoi
      </button>
    </div>
  </div>
</template>
