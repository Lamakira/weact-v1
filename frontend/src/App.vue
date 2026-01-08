<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-primary-500 text-white shadow-lg">
      <div class="max-w-7xl mx-auto px-4 py-4">
        <div class="flex items-center justify-between">
          <h1 class="text-2xl font-bold">WEACT</h1>
          <nav class="flex gap-4">
            <RouterLink
              to="/"
              class="px-3 py-2 rounded-md hover:bg-primary-600 transition-colors"
            >
              Home
            </RouterLink>
            <RouterLink
              to="/about"
              class="px-3 py-2 rounded-md hover:bg-primary-600 transition-colors"
            >
              About
            </RouterLink>
          </nav>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-8">
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

<style scoped>
.router-link-exact-active {
  background-color: var(--color-primary-600);
}
</style>

