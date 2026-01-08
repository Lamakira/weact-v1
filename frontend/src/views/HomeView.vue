<script setup lang="ts">
import { useCounterStore } from '@/stores/counter'
import { computed } from 'vue'

const counter = useCounterStore()
const env = computed(() => import.meta.env)
</script>

<template>
  <div class="space-y-8">
    <!-- Hero Section -->
    <section class="text-center py-12 bg-white rounded-lg shadow-md">
      <h2 class="text-4xl font-bold text-gray-900 mb-4">
        Welcome to <span class="text-primary-500">WEACT</span>
      </h2>
      <p class="text-xl text-gray-600 max-w-2xl mx-auto">
        La plateforme qui connecte les Faces (talents) avec les Producteurs pour des missions créatives.
      </p>
    </section>

    <!-- Design System Demo -->
    <section class="bg-white rounded-lg shadow-md p-6">
      <h3 class="text-2xl font-semibold mb-4">Design System - Primary Color Palette</h3>
      <div class="flex flex-wrap gap-2">
        <div class="w-20 h-20 bg-primary-50 rounded flex items-center justify-center text-xs">50</div>
        <div class="w-20 h-20 bg-primary-100 rounded flex items-center justify-center text-xs">100</div>
        <div class="w-20 h-20 bg-primary-200 rounded flex items-center justify-center text-xs">200</div>
        <div class="w-20 h-20 bg-primary-300 rounded flex items-center justify-center text-xs">300</div>
        <div class="w-20 h-20 bg-primary-400 rounded flex items-center justify-center text-xs text-white">400</div>
        <div class="w-20 h-20 bg-primary-500 rounded flex items-center justify-center text-xs text-white">500</div>
        <div class="w-20 h-20 bg-primary-600 rounded flex items-center justify-center text-xs text-white">600</div>
        <div class="w-20 h-20 bg-primary-700 rounded flex items-center justify-center text-xs text-white">700</div>
        <div class="w-20 h-20 bg-primary-800 rounded flex items-center justify-center text-xs text-white">800</div>
        <div class="w-20 h-20 bg-primary-900 rounded flex items-center justify-center text-xs text-white">900</div>
      </div>
    </section>

    <!-- Pinia Store Demo -->
    <section class="bg-white rounded-lg shadow-md p-6">
      <h3 class="text-2xl font-semibold mb-4">Pinia Store Test</h3>
      <div class="flex items-center gap-4">
        <button
          @click="counter.decrement()"
          class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 transition-colors"
        >
          -
        </button>
        <span class="text-2xl font-bold text-primary-500 min-w-[60px] text-center">
          {{ counter.count }}
        </span>
        <button
          @click="counter.increment()"
          class="px-4 py-2 bg-primary-500 text-white rounded hover:bg-primary-600 transition-colors"
        >
          +
        </button>
      </div>
      <p class="mt-2 text-gray-600">Double count: {{ counter.doubleCount }}</p>
    </section>

    <!-- Environment Variables Demo -->
    <section class="bg-white rounded-lg shadow-md p-6">
      <h3 class="text-2xl font-semibold mb-4">Environment Configuration</h3>
      <div class="space-y-2 text-sm font-mono bg-gray-100 p-4 rounded">
        <p><span class="text-gray-500">VITE_APP_NAME:</span> {{ env.VITE_APP_NAME }}</p>
        <p><span class="text-gray-500">VITE_API_BASE_URL:</span> {{ env.VITE_API_BASE_URL }}</p>
      </div>
    </section>
  </div>
</template>


