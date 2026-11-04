<script setup lang="ts">
import { computed } from 'vue'
import { DUREE_OPTIONS, FINALITE_MAX_LENGTH, SUPPORT_OPTIONS, TERRITOIRE_OPTIONS } from '../constants'
import type { ImageDuree, ImageSupport, ImageTerritoire } from '../types'

const props = defineProps<{
  finalite: string
  supports: ImageSupport[]
  territoire: ImageTerritoire
  dureeAnnees: ImageDuree
  /** Message d'erreur global de la section (finalité ou supports manquants). */
  error?: string | null
}>()

const emit = defineEmits<{
  'update:finalite': [value: string]
  'update:supports': [value: ImageSupport[]]
  'update:territoire': [value: ImageTerritoire]
  'update:dureeAnnees': [value: ImageDuree]
}>()

const selectedSupports = computed(() => new Set(props.supports))

// « Tous supports » est exclusif : le choisir efface le reste, choisir autre chose le retire.
function toggleSupport(value: ImageSupport): void {
  if (value === 'all') {
    emit('update:supports', selectedSupports.value.has('all') ? [] : ['all'])
    return
  }
  const next = props.supports.filter((support) => support !== 'all')
  emit(
    'update:supports',
    next.includes(value) ? next.filter((support) => support !== value) : [...next, value],
  )
}

function onFinaliteInput(event: Event): void {
  emit('update:finalite', (event.target as HTMLInputElement).value)
}

const pillBase =
  'rounded-lg border px-2 py-2 text-center text-sm font-medium leading-tight transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-weact-500'
const pillOn = 'border-weact-500 bg-weact-50 text-weact-700'
const pillOff = 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'
</script>

<template>
  <section class="space-y-4 rounded-xl border border-gray-200 p-4" data-testid="image-terms-section">
    <div>
      <h3 class="text-sm font-semibold text-gray-900">Utilisation de l'image</h3>
      <p class="mt-0.5 text-xs text-gray-500" data-testid="image-terms-intro">
        La Face verra ces conditions avant d'accepter. Les droits démarrent une fois la Face payée.
      </p>
    </div>

    <div>
      <label for="image_finalite" class="mb-1 block text-xs font-medium text-gray-700">Finalité</label>
      <input
        id="image_finalite"
        type="text"
        :value="finalite"
        :maxlength="FINALITE_MAX_LENGTH"
        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-weact-500 focus:outline-none focus:ring-1 focus:ring-weact-500"
        placeholder="Ex. Publicité pour la marque X"
        data-testid="image-finalite"
        @input="onFinaliteInput"
      />
    </div>

    <fieldset>
      <legend class="mb-1 text-xs font-medium text-gray-700">Supports</legend>
      <div class="grid grid-cols-2 gap-2 sm:grid-cols-3" data-testid="image-supports">
        <button
          v-for="option in SUPPORT_OPTIONS"
          :key="option.value"
          type="button"
          :class="[pillBase, selectedSupports.has(option.value) ? pillOn : pillOff]"
          :aria-pressed="selectedSupports.has(option.value)"
          :data-testid="`image-support-${option.value}`"
          @click="toggleSupport(option.value)"
        >
          {{ option.label }}
        </button>
      </div>
    </fieldset>

    <fieldset>
      <legend class="mb-1 text-xs font-medium text-gray-700">Territoire</legend>
      <div class="grid grid-cols-3 gap-2" data-testid="image-territoires">
        <button
          v-for="option in TERRITOIRE_OPTIONS"
          :key="option.value"
          type="button"
          :class="[pillBase, territoire === option.value ? pillOn : pillOff]"
          :aria-pressed="territoire === option.value"
          :data-testid="`image-territoire-${option.value}`"
          @click="emit('update:territoire', option.value)"
        >
          {{ option.label }}
        </button>
      </div>
    </fieldset>

    <fieldset>
      <legend class="mb-1 text-xs font-medium text-gray-700">Durée</legend>
      <div class="grid grid-cols-4 gap-2" data-testid="image-durees">
        <button
          v-for="option in DUREE_OPTIONS"
          :key="option.value"
          type="button"
          :class="[pillBase, dureeAnnees === option.value ? pillOn : pillOff]"
          :aria-pressed="dureeAnnees === option.value"
          :data-testid="`image-duree-${option.value}`"
          @click="emit('update:dureeAnnees', option.value)"
        >
          {{ option.label }}
        </button>
      </div>
    </fieldset>

    <p v-if="error" class="text-sm text-red-600" role="alert" data-testid="image-terms-error">{{ error }}</p>
  </section>
</template>
