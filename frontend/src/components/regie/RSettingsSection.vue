<script setup lang="ts">
/**
 * RSettingsSection — ligne de réglages en deux colonnes (titre + description |
 * champs). Empilée sur mobile.
 */
interface Props {
  title: string
  description?: string
}

withDefaults(defineProps<Props>(), {
  description: undefined,
})
</script>

<template>
  <section
    class="grid gap-4 border-b border-line py-6 last:border-b-0 md:grid-cols-[minmax(0,260px)_minmax(0,1fr)] md:gap-10"
    data-testid="r-settings-section"
  >
    <div>
      <h3 class="text-[15px] font-semibold tracking-[-0.02em] text-ink">{{ title }}</h3>
      <p v-if="description" class="mt-1 text-[12.5px] text-ink-3">{{ description }}</p>
    </div>
    <div class="min-w-0 space-y-4">
      <slot />
    </div>
  </section>
</template>
