<script setup lang="ts">
import { Lock } from 'lucide-vue-next'
import type { Component } from 'vue'

interface Props {
  title: string
  description?: string
  ctaText: string
  ctaLink: string
  icon?: Component
}

withDefaults(defineProps<Props>(), {
  description: '',
})
</script>

<template>
  <div
    class="relative overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm"
    data-testid="locked-content-teaser"
  >
    <!-- Blurred Background Content Layer -->
    <div class="absolute inset-0 z-0 select-none pointer-events-none overflow-hidden bg-gray-50">
      <!-- Abstract shapes to simulate "content" behind the blur -->
      <div class="absolute top-4 left-4 w-32 h-32 bg-[#198496]/10 rounded-full blur-2xl" />
      <div class="absolute bottom-8 right-10 w-40 h-40 bg-gray-200 rounded-full blur-3xl" />
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full opacity-30">
        <div class="grid grid-cols-3 gap-2 p-4">
          <div v-for="i in 6" :key="i" class="aspect-square bg-gray-200 rounded-md" />
        </div>
      </div>
      <!-- Primary Blur Overlay -->
      <div class="absolute inset-0 backdrop-blur-[6px] bg-white/40" />
      <!-- Gradient Fade for Depth -->
      <div class="absolute inset-0 bg-gradient-to-b from-transparent via-white/60 to-white" />
    </div>

    <!-- Foreground Content -->
    <div class="relative z-10 p-8 flex flex-col items-center text-center">
      <!-- Icon Wrapper -->
      <div
        class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-white shadow-sm border border-gray-100 text-[#198496]"
        aria-hidden="true"
      >
        <component :is="icon || Lock" :size="20" stroke-width="2.5" />
      </div>

      <!-- Textual Content -->
      <div class="max-w-xs mb-6">
        <h3
          class="text-sm font-semibold text-gray-900 mb-1.5 tracking-tight"
          data-testid="locked-content-title"
        >
          {{ title }}
        </h3>
        <p
          v-if="description"
          class="text-sm text-gray-500 leading-relaxed"
          data-testid="locked-content-description"
        >
          {{ description }}
        </p>
      </div>

      <!-- CTA Button -->
      <RouterLink
        :to="ctaLink"
        class="text-sm font-medium bg-[#198496] text-white px-6 py-2 rounded-md hover:bg-[#146c7a] transition-all duration-200 shadow-sm active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-[#198496]/20"
        data-testid="locked-content-cta"
      >
        {{ ctaText }}
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
/* Ensure the blur doesn't bleed outside the border radius in some browsers */
.rounded-2xl {
  transform: translateZ(0);
}
</style>
