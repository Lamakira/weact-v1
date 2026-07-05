<script setup lang="ts">
/**
 * Site-wide mount point for the subscription payment banner on the PUBLIC
 * branches of App.vue (landing + regular routes).
 *
 * Gate BEFORE rendering PendingSubscriptionPaymentBanner: the banner mounts
 * useSubscriptionReconciler (status fetch + verify polling), so the v-if must
 * prevent any mount — and therefore any API call — for guests, Producers and
 * Admins. Routes with their own local subscription controls (pricing,
 * face-billing) are excluded to avoid duplicate banners.
 *
 * The FaceLayout mount (dashboard /face/* pages) is separate and unchanged.
 */
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PendingSubscriptionPaymentBanner from '@/components/PendingSubscriptionPaymentBanner.vue'
import { useAuthStore } from '@/stores/auth'

const EXCLUDED_ROUTE_NAMES = ['pricing', 'face-billing']

const route = useRoute()
const authStore = useAuthStore()

const shouldRender = computed(() => {
  if (!authStore.isFace) return false
  // Unresolved initial navigation (START_LOCATION): route.name is undefined
  // and matched is empty — mounting here would flash the banner (and fire a
  // status fetch) even when the destination is an excluded route.
  if (route.matched.length === 0) return false
  const name = typeof route.name === 'string' ? route.name : ''
  return !EXCLUDED_ROUTE_NAMES.includes(name)
})
</script>

<template>
  <!-- empty:hidden — when the inner banner renders nothing (Face with no
       pending/failed payment), Vue leaves only a comment placeholder, which
       does not count for CSS :empty: the container collapses instead of
       adding a 16px phantom spacer to every public page. -->
  <div
    v-if="shouldRender"
    class="max-w-7xl w-full mx-auto px-4 pt-4 empty:hidden"
    data-testid="sitewide-subscription-banner-container"
  >
    <PendingSubscriptionPaymentBanner />
  </div>
</template>
