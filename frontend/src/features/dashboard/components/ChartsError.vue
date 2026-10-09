<script setup lang="ts">
/**
 * Affiché si le chunk des graphiques (chart.js) n'a pas pu être chargé
 * (chunk périmé après un déploiement, réseau coupé) : la section ne disparaît pas en silence.
 */
function reload(): void {
  window.location.reload()
}
</script>

<template>
  <div
    data-testid="charts-error"
    role="alert"
    class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm min-h-[400px] flex flex-col items-center justify-center text-center"
  >
    <p class="text-sm text-gray-600 mb-4">Graphiques indisponibles</p>
    <button
      type="button"
      class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
      data-testid="charts-reload-button"
      @click="reload"
    >
      Recharger la page
    </button>
  </div>
</template>
