<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import AppHeader from '@/components/layout/AppHeader.vue'
import AppFooter from '@/components/layout/AppFooter.vue'

const route = useRoute()

/** Check if current route uses dashboard layout (no AppHeader/footer) */
const isDashboardRoute = computed(() => {
  return route.path.startsWith('/face/') || route.path.startsWith('/producer/')
})
</script>

<template>
  <!-- Dashboard routes: full-screen, no header/footer -->
  <template v-if="isDashboardRoute">
    <RouterView />
  </template>

  <!-- Regular routes: with header/footer -->
  <template v-else>
    <div class="min-h-screen bg-gray-50 flex flex-col">
      <!-- Header -->
      <AppHeader />

      <!-- Main Content -->
      <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-8">
        <RouterView />
      </main>

      <!-- Footer -->
      <AppFooter />
    </div>
  </template>
</template>
