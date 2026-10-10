<script setup lang="ts">
/**
 * RPill — pilule de filtre (bouton à bascule, `aria-pressed`).
 */
interface Props {
  pressed?: boolean
  count?: number
}

withDefaults(defineProps<Props>(), {
  pressed: false,
  count: undefined,
})
</script>

<template>
  <button
    type="button"
    :aria-pressed="pressed"
    class="inline-flex h-8 items-center gap-1.5 whitespace-nowrap rounded-full px-3 text-[13px] font-medium ring-1 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600 max-md:h-10"
    :class="
      pressed
        ? 'bg-ink text-white ring-ink'
        : 'bg-white text-ink-2 ring-line hover:bg-sidebar hover:text-ink'
    "
    data-testid="r-pill"
  >
    <slot />
    <span v-if="count !== undefined" class="text-[11.5px] opacity-70">{{ count }}</span>
  </button>
</template>
