<script setup lang="ts">
/**
 * Fondu court avec léger flou quand le contenu clé change (action principale, titre,
 * pastille, bloc « Et ensuite ? »). L'ancien contenu disparaît tout de suite, le nouveau
 * apparaît au même emplacement : aucun saut de mise en page. Opacité, flou et décalage de
 * 2 px seulement ; réduit sous prefers-reduced-motion.
 */
</script>

<template>
  <Transition name="booking-swap" mode="out-in">
    <slot />
  </Transition>
</template>

<style scoped>
:slotted(.booking-swap-enter-active) {
  animation: booking-swap-in 240ms cubic-bezier(0.23, 1, 0.32, 1);
}

@keyframes booking-swap-in {
  from {
    opacity: 0;
    filter: blur(3px);
    transform: translateY(2px);
  }
  to {
    opacity: 1;
    filter: blur(0);
    transform: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  :slotted(.booking-swap-enter-active) {
    animation-name: booking-swap-fade;
    animation-duration: 120ms;
  }

  @keyframes booking-swap-fade {
    from {
      opacity: 0;
    }
    to {
      opacity: 1;
    }
  }
}
</style>
