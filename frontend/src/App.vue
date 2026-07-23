<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import AppHeader from '@/components/layout/AppHeader.vue'
import AppFooter from '@/components/layout/AppFooter.vue'
import NavigationProgress from '@/components/layout/NavigationProgress.vue'
import CookieConsentBanner from '@/components/cookie/CookieConsentBanner.vue'
import SitewideSubscriptionPaymentBanner from '@/components/SitewideSubscriptionPaymentBanner.vue'
import { Toaster } from '@/components/ui/sonner'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import {
  cancelPublicRouteEnter,
  finishPublicRouteEnter,
  getPublicScrollNavigationToken,
} from '@/router/publicScrollRestoration'

const route = useRoute()
const authStore = useAuthStore()
const notificationStore = useNotificationStore()
const enteringPublicRoutes = new WeakMap<Element, symbol>()

function rememberEnteringPublicRoute(element: Element): void {
  const navigationToken = getPublicScrollNavigationToken(route.fullPath)
  if (navigationToken) enteringPublicRoutes.set(element, navigationToken)
}

function finishEnteringPublicRoute(element: Element): void {
  const navigationToken = enteringPublicRoutes.get(element)
  if (navigationToken) finishPublicRouteEnter(navigationToken)
}

function cancelEnteringPublicRoute(element: Element): void {
  const navigationToken = enteringPublicRoutes.get(element)
  if (navigationToken) cancelPublicRouteEnter(navigationToken)
}

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

  <!-- Dashboard routes: full-screen, no header/footer.
       Key by the LAYOUT's own route (matched[0]) so the layout instance PERSISTS
       across child navigations. With :key="route.path", the layout remounts on
       every child nav and destroys the <keep-alive> inside Face/ProducerLayout. -->
  <template v-if="isDashboardRoute">
    <RouterView v-slot="{ Component }">
      <Transition name="page" mode="out-in">
        <component :is="Component" :key="route.matched[0]?.path" />
      </Transition>
    </RouterView>
  </template>

  <!-- Admin routes: full-screen, uses AdminLayout internally.
       Key by the layout's own route (matched[0]) so AdminLayout persists across
       child navigations and its inner <keep-alive> can cache the admin listings. -->
  <template v-else-if="isAdminRoute">
    <RouterView v-slot="{ Component }">
      <Transition name="page" mode="out-in">
        <component :is="Component" :key="route.matched[0]?.path" />
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

      <!-- Site-wide subscription payment banner (Face only, gated routes) -->
      <SitewideSubscriptionPaymentBanner />

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

      <!-- Site-wide subscription payment banner (Face only, gated routes) -->
      <SitewideSubscriptionPaymentBanner />

      <!-- Main Content -->
      <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-8">
        <RouterView v-slot="{ Component }">
          <Transition
            name="page"
            mode="out-in"
            @before-enter="rememberEnteringPublicRoute"
            @after-enter="finishEnteringPublicRoute"
            @enter-cancelled="cancelEnteringPublicRoute"
          >
            <!-- keep-alive caches the "browse a listing → open a detail → back"
                 public listings (Group A) so back-nav restores the grid + scroll
                 instead of remounting + refetching (skeleton). Rotation-sensitive
                 too: a refetch of /faces would reshuffle the rotated grid.

                 NOTE: unlike the dashboard/admin layouts (which drive caching by
                 route meta.keepAlive), this branch keeps a name-based :include. The
                 <Transition mode="out-in"> forces a SINGLE child, so the keep-alive
                 must be that child and stay always-mounted (its cache has to survive
                 the visit to the non-cached profile/detail route). Selective caching
                 in an always-mounted keep-alive can only be name-based (include), and
                 a meta-driven v-if would either unmount the keep-alive (wiping the
                 cache) or need a second sibling (two children → invalid under the
                 transition). The three public view names are specific (no collision).

                 :key="route.path" is stable per listing (/faces, /missions,
                 /ressources carry no path params) so each caches into one slot; :max
                 is a defensive cap (footgun guard if a listed view ever gains params). -->
            <keep-alive
              :include="['PublicFacesView', 'PublicMissionsView', 'RessourcesView']"
              :max="5"
            >
              <component :is="Component" :key="route.path" />
            </keep-alive>
          </Transition>
        </RouterView>
      </main>

      <!-- Footer -->
      <AppFooter />
    </div>
  </template>

  <!-- Cookie Consent Banner (global, all layouts) -->
  <CookieConsentBanner />

  <!-- Toasts (global, all layouts) — réglages repris à l'identique de l'ancien
       plugin vue-toastification : haut-droite, 5 s, couleurs par type, fermeture
       possible. Monté ici et nulle part ailleurs : un second <Toaster> dupliquerait
       chaque notification. -->
  <Toaster
    position="top-right"
    :duration="5000"
    rich-colors
    close-button
    container-aria-label="Notifications"
  />
</template>
