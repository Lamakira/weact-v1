<script setup lang="ts">
import { RouterView } from 'vue-router'
import AppHeader from '@/components/layout/AppHeader.vue'
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <!-- Header -->
    <AppHeader />

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-8">
      <RouterView />
    </main>

    <!-- Footer -->
    <footer class="bg-gray-100 border-t mt-auto">
      <div class="max-w-7xl mx-auto px-4 py-4 text-center text-gray-600">
        <p>&copy; 2026 WEACT - Connecting Faces with Producers</p>
      </div>
    </footer>
  </div>
</template>
