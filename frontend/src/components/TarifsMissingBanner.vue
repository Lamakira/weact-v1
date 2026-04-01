<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next'
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div
    class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6"
    role="alert"
    data-testid="tarifs-missing-banner"
  >
    <div class="flex items-start gap-3">
      <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
        <AlertTriangle class="w-5 h-5 text-amber-600" aria-hidden="true" />
      </div>

      <div class="flex-1 min-w-0">
        <h3 class="text-sm font-semibold text-amber-800">
          Renseignez vos tarifs pour être visible
        </h3>
        <p class="mt-1 text-sm text-amber-700">
          Tant que vous n'avez pas renseigné vos tarifs, vous ne pouvez pas être booké par un producteur.
        </p>

        <div class="mt-3">
          <button
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700 transition-colors"
            @click="router.push('/face/profile')"
          >
            Renseigner mes tarifs
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
