<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ImagePlus, X } from 'lucide-vue-next'

/**
 * Uploader de photos produit UGC (spec photos produit) — calque
 * AlbumPhotoUpload : jusqu'à 2 photos JPG/PNG de 8 Mo max, avec préviews
 * locales et retrait AVANT envoi (aucune édition après création, v1).
 * Le parent possède la liste (v-model) et l'envoie en FormData à la création.
 */
const MAX_SIZE_BYTES = 8 * 1024 * 1024
const ACCEPTED_TYPES = ['image/jpeg', 'image/png']

const props = withDefaults(
  defineProps<{
    modelValue?: File[]
    maxPhotos?: number
    error?: string
  }>(),
  {
    modelValue: () => [],
    maxPhotos: 2,
    error: undefined,
  },
)

const emit = defineEmits<{
  'update:modelValue': [files: File[]]
}>()

const fileInputRef = ref<HTMLInputElement | null>(null)
const localError = ref<string | null>(null)

// Préviews locales (une URL objet par fichier) — révoquées à chaque
// changement de liste et au démontage (pas de fuite mémoire).
const previews = ref<string[]>([])

watch(
  () => props.modelValue,
  (files) => {
    previews.value.forEach((url) => URL.revokeObjectURL(url))
    previews.value = files.map((file) => URL.createObjectURL(file))
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  previews.value.forEach((url) => URL.revokeObjectURL(url))
})

const isFull = computed(() => props.modelValue.length >= props.maxPhotos)
const displayedError = computed(() => props.error || localError.value)

function triggerFileInput(): void {
  if (!isFull.value) {
    fileInputRef.value?.click()
  }
}

function handleFileSelect(event: Event): void {
  const input = event.target as HTMLInputElement
  const files = Array.from(input.files ?? [])
  // Reset pour permettre de resélectionner le même fichier après retrait.
  input.value = ''

  if (files.length === 0) return

  localError.value = null
  const accepted: File[] = []

  for (const file of files) {
    if (props.modelValue.length + accepted.length >= props.maxPhotos) {
      localError.value = `Vous ne pouvez joindre que ${props.maxPhotos} photos du produit.`
      break
    }
    if (!ACCEPTED_TYPES.includes(file.type)) {
      localError.value = 'Chaque photo doit être au format JPG ou PNG.'
      continue
    }
    if (file.size > MAX_SIZE_BYTES) {
      localError.value = 'Chaque photo ne doit pas dépasser 8 Mo.'
      continue
    }
    accepted.push(file)
  }

  if (accepted.length > 0) {
    emit('update:modelValue', [...props.modelValue, ...accepted])
  }
}

function removePhoto(index: number): void {
  localError.value = null
  emit(
    'update:modelValue',
    props.modelValue.filter((_, i) => i !== index),
  )
}
</script>

<template>
  <div data-testid="product-photos-upload">
    <p class="mb-1.5 text-xs font-medium text-gray-600">
      Photos du produit <span class="font-normal text-gray-400">(facultatif, {{ maxPhotos }} max)</span>
    </p>

    <div class="flex items-start gap-3">
      <!-- Préviews -->
      <div
        v-for="(preview, index) in previews"
        :key="preview"
        class="relative h-20 w-20 shrink-0 overflow-hidden rounded-lg border border-gray-200"
        data-testid="product-photo-preview"
      >
        <img :src="preview" :alt="`Photo du produit ${index + 1}`" class="h-full w-full object-cover" />
        <button
          type="button"
          class="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-black/60 text-white transition-colors hover:bg-black/80"
          :aria-label="`Retirer la photo ${index + 1}`"
          data-testid="remove-product-photo"
          @click="removePhoto(index)"
        >
          <X :size="12" />
        </button>
      </div>

      <!-- Zone d'ajout -->
      <button
        v-if="!isFull"
        type="button"
        class="flex h-20 w-20 shrink-0 flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-gray-300 text-gray-400 transition-colors hover:border-weact hover:text-weact"
        data-testid="add-product-photo"
        @click="triggerFileInput"
      >
        <ImagePlus :size="20" />
        <span class="text-[10px] font-medium">Ajouter</span>
      </button>
    </div>

    <p class="mt-1.5 text-[11px] text-gray-400">Format JPG ou PNG. Taille max : 8 Mo par photo.</p>

    <p
      v-if="displayedError"
      class="mt-1 text-sm text-red-600"
      role="alert"
      data-testid="product-photos-error"
    >
      {{ displayedError }}
    </p>

    <input
      ref="fileInputRef"
      type="file"
      accept="image/jpeg,image/png"
      multiple
      class="hidden"
      :disabled="isFull"
      data-testid="product-photos-input"
      @change="handleFileSelect"
    />
  </div>
</template>
