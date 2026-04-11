<script setup lang="ts">
import { MessageCircle } from 'lucide-vue-next'
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div
    class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6"
    role="status"
    data-testid="whatsapp-missing-banner"
  >
    <div class="flex items-start gap-3">
      <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
        <MessageCircle class="w-5 h-5 text-blue-600" aria-hidden="true" />
      </div>

      <div class="flex-1 min-w-0">
        <h3 class="text-sm font-semibold text-blue-800">
          Renseignez votre numéro WhatsApp
        </h3>
        <p class="mt-1 text-sm text-blue-700">
          Votre numéro WhatsApp est utilisé uniquement pour vous contacter lorsque vous êtes retenu(e) pour une mission ou booké(e). Renseignez-le pour ne manquer aucune opportunité.
        </p>

        <div class="mt-3">
          <button
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors"
            data-testid="whatsapp-banner-cta"
            @click="router.push('/face/profile?focus=whatsapp')"
          >
            Renseigner mon WhatsApp
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
