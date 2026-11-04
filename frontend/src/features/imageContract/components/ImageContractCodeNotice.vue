<script setup lang="ts">
import { Mail } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    title?: string
    text?: string
  }>(),
  {
    title: 'Un code vous sera envoyé par e-mail',
    text: 'Vous devrez le saisir pour signer. Ouvrez votre boîte e-mail.',
  },
)
</script>

<template>
  <!-- Encadré orange volontairement très visible : bord 2px #d97706, fond #fef3c7, texte #78350f -->
  <div
    class="flex items-start gap-3 rounded-xl border-2 border-[#d97706] bg-[#fef3c7] p-3.5 text-[#78350f]"
    data-testid="image-contract-code-notice"
  >
    <Mail :size="22" class="mt-0.5 shrink-0" aria-hidden="true" />
    <div class="min-w-0">
      <p class="text-sm font-bold" data-testid="image-contract-code-notice-title">{{ title }}</p>
      <p class="mt-0.5 text-sm" data-testid="image-contract-code-notice-text">{{ text }}</p>
    </div>
  </div>
</template>
