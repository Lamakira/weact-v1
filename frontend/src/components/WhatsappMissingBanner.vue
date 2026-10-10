<script setup lang="ts">
import { MessageCircle } from 'lucide-vue-next'
import { useRouter } from 'vue-router'

// Defaults = the Face wording/target. The Producer layout overrides them.
withDefaults(
  defineProps<{
    title?: string
    message?: string
    ctaLabel?: string
    to?: string
  }>(),
  {
    title: 'Renseignez votre numéro WhatsApp',
    message:
      'Votre numéro WhatsApp est utilisé uniquement pour vous contacter lorsque vous êtes retenu(e) pour une mission ou booké(e). Renseignez-le pour ne manquer aucune opportunité.',
    ctaLabel: 'Renseigner mon WhatsApp',
    to: '/face/profile?focus=whatsapp',
  },
)

const router = useRouter()
</script>

<template>
  <div
    class="mb-6 rounded-panel bg-white p-4 ring-1 ring-line"
    role="status"
    data-testid="whatsapp-missing-banner"
  >
    <div class="flex items-start gap-3">
      <MessageCircle class="mt-0.5 h-4 w-4 shrink-0 text-weact-600" aria-hidden="true" />

      <div class="min-w-0 flex-1">
        <h3 class="text-[13.5px] font-semibold text-ink">
          {{ title }}
        </h3>
        <p class="mt-1 text-[13px] text-ink-2">
          {{ message }}
        </p>

        <div class="mt-3">
          <button
            type="button"
            class="inline-flex h-8 items-center rounded-control bg-weact-600 px-3 text-[13px] font-semibold text-white transition-colors hover:bg-weact-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600 focus-visible:ring-offset-2 max-md:h-11"
            data-testid="whatsapp-banner-cta"
            @click="router.push(to)"
          >
            {{ ctaLabel }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
