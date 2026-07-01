<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import AppHeader from '@/components/layout/AppHeader.vue'
import AppFooter from '@/components/layout/AppFooter.vue'
import NavigationProgress from '@/components/layout/NavigationProgress.vue'
import CookieConsentBanner from '@/components/cookie/CookieConsentBanner.vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'

const route = useRoute()
const authStore = useAuthStore()
const notificationStore = useNotificationStore()

// Bootstrap notification store on app reload for authenticated users
onMounted(() => {
  if (authStore.isAuthenticated && !notificationStore.isSubscribed) {
    notificationStore.subscribe()
    notificationStore.fetchUnreadCount()
  }
})

/** Check if current route uses dashboard layout (no AppHeader/footer) */
const isDashboardRoute = computed(() => {
  return route.path.startsWith('/face/') || route.path.startsWith('/producer/')
})

/** Check if current route is an admin page (uses AdminLayout, no public AppHeader/footer) */
const isAdminRoute = computed(() => {
  return route.path.startsWith('/admin/')
})

/** Check if current route is an auth page (full-screen, no header/footer) */
const isAuthRoute = computed(() => {
  return route.meta.guest === true
})

/** Check if current route is landing page (needs full-width sections) */
const isLandingPage = computed(() => {
  return route.name === 'home'
})
</script>

<template>
  <!-- Navigation progress bar (global, all layouts, fixed at top) -->
  <NavigationProgress />

  <!-- Dashboard routes: full-screen, no header/footer -->
  <template v-if="isDashboardRoute">
    <RouterView v-slot="{ Component }">
      <Transition name="page" mode="out-in">
        <component :is="Component" :key="route.path" />
      </Transition>
    </RouterView>
  </template>

  <!-- Admin routes: full-screen, uses AdminLayout internally -->
  <template v-else-if="isAdminRoute">
    <RouterView v-slot="{ Component }">
      <Transition name="page" mode="out-in">
        <component :is="Component" :key="route.path" />
      </Transition>
    </RouterView>
  </template>

  <!-- Auth routes: full-screen, no header/footer -->
  <template v-else-if="isAuthRoute">
    <RouterView v-slot="{ Component }">
      <Transition name="page" mode="out-in">
        <component :is="Component" :key="route.path" />
      </Transition>
    </RouterView>
  </template>

  <!-- Landing page: full-width sections with header/footer -->
  <template v-else-if="isLandingPage">
    <div class="min-h-screen bg-white flex flex-col">
      <!-- Header (sticky on landing page) -->
      <div class="sticky top-0 z-50">
        <AppHeader />
      </div>

      <!-- Main Content - Full width for landing page -->
      <main class="flex-1">
        <RouterView v-slot="{ Component }">
          <Transition name="page" mode="out-in">
            <component :is="Component" :key="route.path" />
          </Transition>
        </RouterView>
      </main>

      <!-- Footer -->
      <AppFooter />
    </div>
  </template>

  <!-- Regular routes: with header/footer and constrained width -->
  <template v-else>
    <div class="min-h-screen bg-gray-50 flex flex-col">
      <!-- Header -->
      <AppHeader />

      <!-- Main Content -->
      <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-8">
        <RouterView v-slot="{ Component }">
          <Transition name="page" mode="out-in">
            <component :is="Component" :key="route.path" />
          </Transition>
        </RouterView>
      </main>

      <!-- Footer -->
      <AppFooter />
    </div>
  </template>

  <!-- Cookie Consent Banner (global, all layouts) -->
  <CookieConsentBanner />
</template>
