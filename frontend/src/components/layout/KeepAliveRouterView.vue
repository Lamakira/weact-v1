<script setup lang="ts">
/**
 * KeepAliveRouterView
 *
 * Shared child-route outlet for the dashboard/admin layouts (FaceLayout,
 * ProducerLayout, AdminLayout) — extracted so the meta-driven keep-alive
 * pattern lives in ONE place instead of three verbatim copies. Rendering the
 * <router-view> from this child SFC is depth-safe: RouterView resolves its
 * depth via inject(viewDepthKey), which traverses intermediate components
 * (vue-router 4.6), so it matches the SAME route depth as inlining it in the
 * layout template. Requires the layout instance itself to persist across
 * child navigations (App.vue keys the dashboard/admin branch by
 * matched[0].path), otherwise the cache dies with the layout.
 *
 * Which routes cache is driven by their router `meta.keepAlive` flag (not a
 * name-based :include): renaming a page can no longer silently disable its
 * cache, and generic component names (e.g. AdminListPage, MissionsListPage)
 * can't collide.
 *
 * <keep-alive> stays ALWAYS mounted (no v-if on it) so its cache survives
 * visits to non-cached child routes (a detail page, the dashboard, …) — a
 * v-if moved onto <keep-alive> itself would unmount it and WIPE the cache.
 * The inner v-if only decides whether the CURRENT route enters the cache;
 * non-flagged routes render in the sibling below, outside keep-alive.
 *
 * The cached branch MUST carry an explicit :key — do not "simplify" it away.
 * <keep-alive> indexes its cache by `vnode.key ?? vnode.type`, and one would
 * expect the type to discriminate the pages here. It does NOT: the compiler
 * injects a hardcoded `{ key: 0 }` on a keyed-less v-if branch, so EVERY cached
 * page collides on the single entry `0`. Navigating cached→cached then hits a
 * false cache hit, activate() patches two different component types, and Vue
 * throws `parentComponent.ctx.deactivate is not a function` — the render aborts
 * and the layout stays frozen on the first page visited (regression introduced
 * by the keep-alive v2 extraction, reported from the admin panel — it hit the
 * Face and Producer layouts too). route.name gives one entry per page and is
 * param/query-insensitive, so a parameterised route promoted to keepAlive later
 * cannot grow the cache unboundedly (there is no :max here).
 * Covered by keepAliveCacheKeyCollision.spec — it needs TWO cached routes; a
 * single one cannot collide with itself, which is why the older specs missed it.
 *
 * The sibling is keyed by route.path: detail pages fetch in onMounted only,
 * so a detail→detail navigation on the same record (e.g. two notification
 * clicks) must remount, not patch the instance in place — while query-only
 * changes keep the instance (the pages' query watchers handle those).
 *
 * Transitions: a single Transition can't wrap both siblings (2 children is
 * invalid), so each sibling gets its OWN <transition name="dash-page">.
 * Transition > KeepAlive > component is the documented order (Transition
 * "sees" the inner child): cached↔cached and non-cached↔non-cached each
 * animate inside one Transition; crossing the cached↔non-cached boundary
 * runs T1's leave and T2's enter simultaneously, which the ABSOLUTE
 * .dash-page-leave-active (main.css) turns into an in-place crossfade
 * instead of a vertical stack.
 */
import { inject } from 'vue'
import { useRoute } from 'vue-router'
import {
  restoreDashboardScrollKey,
  type DashboardScrollDestination,
} from './dashboardScrollRestoration'

const route = useRoute()
const restoreDashboardScroll = inject(restoreDashboardScrollKey, () => {})
const enteringDestinations = new WeakMap<Element, DashboardScrollDestination>()

function rememberEnteringDestination(element: Element): void {
  enteringDestinations.set(element, {
    fullPath: route.fullPath,
    keepAlive: Boolean(route.meta.keepAlive),
  })
}

function restoreEnteringDestination(element: Element): void {
  const destination = enteringDestinations.get(element)
  if (destination) restoreDashboardScroll(destination)
}
</script>

<template>
  <!-- position:relative : ancre la leave absolue de dash-page — les bannières
       éventuelles au-dessus (WhatsApp, email) restent hors de la zone. -->
  <div class="relative">
    <router-view v-slot="{ Component }">
      <transition
        name="dash-page"
        mode="out-in"
        @before-enter="rememberEnteringDestination"
        @after-enter="restoreEnteringDestination"
      >
        <keep-alive>
          <component :is="Component" v-if="route.meta.keepAlive" :key="route.name" />
        </keep-alive>
      </transition>
      <transition
        name="dash-page"
        mode="out-in"
        @before-enter="rememberEnteringDestination"
        @after-enter="restoreEnteringDestination"
      >
        <component :is="Component" v-if="!route.meta.keepAlive" :key="route.path" />
      </transition>
    </router-view>
  </div>
</template>
