<script setup lang="ts">
/**
 * AdminDashboardPage
 * Placeholder page for the admin dashboard.
 * Will be fully implemented in Story 13-3 (Admin Dashboard KPIs).
 */
import { Shield } from 'lucide-vue-next'
import { useAdminAuthStore } from '@/stores/adminAuth'

const adminAuthStore = useAdminAuthStore()
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
      <p class="mt-1 text-sm text-gray-500">
        Bienvenue, {{ adminAuthStore.adminName }}
      </p>
    </div>

    <!-- Placeholder content -->
    <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center">
      <Shield class="mx-auto h-12 w-12 text-gray-400" />
      <h3 class="mt-4 text-lg font-medium text-gray-900">Administration WEACT</h3>
      <p class="mt-2 text-sm text-gray-500">
        Le tableau de bord d'administration sera disponible prochainement.
      </p>
    </div>
  </div>
</template>
