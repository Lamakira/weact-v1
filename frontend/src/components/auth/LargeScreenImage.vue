<script setup lang="ts">
/**
 * Image décorative affichée uniquement à partir du breakpoint lg (1024px).
 * En dessous, le navigateur retient le pixel transparent de repli : l'illustration
 * (plusieurs centaines de Ko) n'est jamais téléchargée sur mobile, contrairement
 * à une <img> simplement masquée par `display: none`.
 */
defineOptions({ inheritAttrs: false })

defineProps<{
  src: string
  alt: string
}>()

const TRANSPARENT_PIXEL
  = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
</script>

<template>
  <picture class="contents">
    <source media="(min-width: 1024px)" :srcset="src" />
    <img
      :src="TRANSPARENT_PIXEL"
      :alt="alt"
      decoding="async"
      v-bind="$attrs"
    />
  </picture>
</template>
