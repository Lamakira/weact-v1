<script setup lang="ts">
/**
 * Site-wide mount point for the subscription payment banner on the PUBLIC
 * branches of App.vue (landing + regular routes).
 *
 * Gate BEFORE rendering PendingSubscriptionPaymentBanner: the banner mounts
 * useSubscriptionReconciler (status fetch + verify polling), so the v-if must
 * prevent any mount — and therefore any API call — for guests, Producers and
 * Admins. Routes that own their subscription surface (pricing, face-billing…)
 * declare `meta.ownSubscriptionSurface` in the router — colocated with the
 * route definition, rename-proof, and shared with the FaceLayout mount.
 *
 * The FaceLayout mount (dashboard /face/* pages) is separate and unchanged.
 *
 * The inner banner loads lazily: this wrapper is imported statically by
 * App.vue, and a static PendingSubscriptionPaymentBanner import would drag
 * the whole features/face service layer (faceApi & co.) into the entry chunk
 * for every visitor — guests and Producers included — who can never render it.
 */
import { computed, defineAsyncComponent } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const PendingSubscriptionPaymentBanner = defineAsyncComponent(
  () => import('@/components/PendingSubscriptionPaymentBanner.vue'),
)

const route = useRoute()
const authStore = useAuthStore()

const shouldRender = computed(() => {
  if (!authStore.isFace) return false
  // Unresolved initial navigation (START_LOCATION): route.name is undefined
  // and matched is empty — mounting here would flash the banner (and fire a
  // status fetch) even when the destination is an excluded route.
  if (route.matched.length === 0) return false
  return !route.meta.ownSubscriptionSurface
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
