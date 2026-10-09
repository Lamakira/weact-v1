<script setup lang="ts">
/**
 * RStickySaveBar — barre collante « Modifications non enregistrées · Annuler ·
 * Enregistrer ». N'apparaît que lorsque `dirty` est vrai. À placer en dernier enfant
 * du conteneur qui défile (position: sticky; bottom: 0).
 */
interface Props {
  dirty: boolean
  saving?: boolean
  message?: string
}

withDefaults(defineProps<Props>(), {
  saving: false,
  message: 'Modifications non enregistrées',
})

const emit = defineEmits<{
  cancel: []
  save: []
}>()
</script>

<template>
  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="translate-y-2 opacity-0"
    leave-active-class="transition duration-150 ease-in"
    leave-to-class="translate-y-2 opacity-0"
  >
    <div
      v-if="dirty"
      class="sticky bottom-0 z-20 -mx-4 border-t border-line bg-white px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:mx-0 sm:rounded-panel sm:border sm:pb-3"
      role="region"
      aria-label="Enregistrement des modifications"
      data-testid="r-sticky-save-bar"
    >
      <div class="flex items-center justify-between gap-3">
        <p class="min-w-0 truncate text-dash text-ink" role="status" aria-live="polite">
          {{ message }}
        </p>
        <div class="flex shrink-0 items-center gap-2">
          <button
            type="button"
            class="inline-flex h-8 items-center rounded-control bg-white px-3 text-[13px] font-semibold text-ink ring-1 ring-line transition-colors hover:bg-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600 disabled:opacity-50 max-md:h-11"
            :disabled="saving"
            data-testid="r-save-cancel"
            @click="emit('cancel')"
          >
            Annuler
          </button>
          <button
            type="button"
            class="inline-flex h-8 items-center rounded-control bg-weact-600 px-3 text-[13px] font-semibold text-white transition-colors hover:bg-weact-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600 focus-visible:ring-offset-2 disabled:opacity-50 max-md:h-11"
            :disabled="saving"
            :aria-busy="saving"
            data-testid="r-save-submit"
            @click="emit('save')"
          >
            {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>
