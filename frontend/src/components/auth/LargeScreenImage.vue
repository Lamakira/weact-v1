<script setup lang="ts">
/**
 * Image décorative affichée uniquement à partir du breakpoint lg (1024px).
 * En dessous, le navigateur retient le pixel transparent de repli : l'illustration
 * (plusieurs centaines de Ko) n'est jamais téléchargée sur mobile, contrairement
 * à une <img> simplement masquée par `display: none`.
 *
 * `<picture class="contents">` fait de ses enfants les éléments du parent : sans
 * `hidden`, la `<source>` occuperait une cellule quand le parent est une grille
 * (hero de l'accueil : la 2ᵉ photo passait sur une ligne invisible). Masquer la
 * source n'influe pas sur le choix de l'image par le navigateur.
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
    <source class="hidden" media="(min-width: 1024px)" :srcset="src" />
    <img
      :src="TRANSPARENT_PIXEL"
      :alt="alt"
      decoding="async"
      v-bind="$attrs"
    />
  </picture>
</template>
