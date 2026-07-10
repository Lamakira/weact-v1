/**
 * Regression guard for detail→detail navigation inside the dashboard/admin
 * layouts (keep-alive v2 review, finding #1).
 *
 * The three layouts (FaceLayout/ProducerLayout/AdminLayout) render children as:
 *
 *   <router-view v-slot="{ Component }">
 *     <keep-alive>
 *       <component :is="Component" v-if="route.meta.keepAlive" />
 *     </keep-alive>
 *     <component :is="Component" v-if="!route.meta.keepAlive" />
 *   </router-view>
 *
 * The bug: the non-cached sibling has NO :key. Since App.vue keys the depth-0
 * branch by `route.matched[0]?.path` (stable across child navigations — that is
 * what makes the nested keep-alive work at all), a detail→detail navigation on
 * the SAME route record (e.g. /face/bookings/A → /face/bookings/B via two
 * notification clicks) PATCHES the existing component instance instead of
 * remounting it: onMounted never replays, the page keeps showing entity A under
 * URL B (and EditMissionPage could save A's data onto B).
 *
 * The fix: add `:key="route.path"` on the non-cached sibling — a path change
 * (params included) forces a fresh mount, while a query-only change does NOT
 * remount (pages' query watchers remain the intended mechanism for that).
 *
 * These tests reproduce the structure with a real router (no browser) and
 * assert instance reuse/remount via an onMounted counter plus the id captured
 * at setup time (the fetch-on-mount-only behavior of real detail pages).
 */
import { describe, it, expect, vi } from 'vitest'
import { defineComponent, h, onMounted, KeepAlive } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory, RouterView, useRoute } from 'vue-router'

function makeSetup(withKey: boolean) {
  const detailMounted = vi.fn()
  const listMounted = vi.fn()

  const DetailPage = defineComponent({
    name: 'DetailPage',
    setup() {
      const route = useRoute()
      onMounted(detailMounted)
      // Real detail pages fetch their entity in onMounted using the id at that
      // moment: capture it ONCE at setup. If the instance is reused across a
      // params change, this stale id is exactly what the user keeps seeing.
      const idAtMount = route.params.id
      return () => h('div', { class: 'detail' }, String(idAtMount))
    },
  })

  const ListPage = defineComponent({
    name: 'ListPage',
    setup() {
      onMounted(listMounted)
      return () => h('div', { class: 'list' }, 'list')
    },
  })

  // Mirrors the FaceLayout/ProducerLayout/AdminLayout template: an always-mounted
  // keep-alive whose inner child is gated by route.meta.keepAlive, plus a sibling
  // bare component for non-flagged routes — keyed by route.path only when the fix
  // is applied (withKey).
  const Layout = defineComponent({
    name: 'Layout',
    setup() {
      const route = useRoute()
      return () =>
        h('div', { class: 'layout' }, [
          h(RouterView, null, {
            default: ({ Component }: { Component: unknown }) => [
              h(KeepAlive, null, () =>
                route.meta.keepAlive && Component ? h(Component as never) : null,
              ),
              !route.meta.keepAlive && Component
                ? h(Component as never, (withKey ? { key: route.path } : {}) as never)
                : null,
            ],
          }),
        ])
    },
  })

  // Mirrors App.vue's dashboard/admin branch: the depth-0 component (the layout)
  // is keyed by the stable `route.matched[0]?.path`, so the layout instance —
  // and the keep-alive inside it — survives child navigations.
  const App = defineComponent({
    setup() {
      const route = useRoute()
      return () =>
        h(RouterView, null, {
          default: ({ Component }: { Component: unknown }) =>
            Component
              ? h(Component as never, { key: route.matched[0]?.path as never })
              : null,
        })
    },
  })

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/parent',
        component: Layout,
        children: [
          { path: 'list', name: 'list', component: ListPage, meta: { keepAlive: true } },
          { path: 'detail/:id', name: 'detail', component: DetailPage },
        ],
      },
    ],
  })

  return { App, router, detailMounted, listMounted }
}

describe('detail→detail remount on the non-cached layout sibling', () => {
  it('BROKEN (no :key on the non-cached sibling): a detail→detail navigation on the same record reuses the instance (documents the bug)', async () => {
    const { App, router, detailMounted } = makeSetup(false)

    await router.push('/parent/detail/1')
    await router.isReady()
    const wrapper = mount(App, { global: { plugins: [router] } })
    await flushPromises()
    expect(detailMounted).toHaveBeenCalledTimes(1)
    expect(wrapper.find('.detail').text()).toBe('1')

    await router.push('/parent/detail/2')
    await flushPromises()

    // Same route record + unkeyed vnode → Vue patches the existing instance:
    // onMounted never replays and the page still shows entity 1 under URL /2.
    expect(detailMounted).toHaveBeenCalledTimes(1)
    expect(wrapper.find('.detail').text()).toBe('1')
  })

  it('CORRECT (:key=route.path): detail→detail remounts and shows the right entity', async () => {
    const { App, router, detailMounted } = makeSetup(true)

    await router.push('/parent/detail/1')
    await router.isReady()
    const wrapper = mount(App, { global: { plugins: [router] } })
    await flushPromises()
    expect(detailMounted).toHaveBeenCalledTimes(1)
    expect(wrapper.find('.detail').text()).toBe('1')

    await router.push('/parent/detail/2')
    await flushPromises()

    // The path changed → the key changed → fresh mount with the new params.
    expect(detailMounted).toHaveBeenCalledTimes(2)
    expect(wrapper.find('.detail').text()).toBe('2')
  })

  it('CORRECT: a query-only change does NOT remount', async () => {
    const { App, router, detailMounted } = makeSetup(true)

    await router.push('/parent/detail/1')
    await router.isReady()
    mount(App, { global: { plugins: [router] } })
    await flushPromises()
    expect(detailMounted).toHaveBeenCalledTimes(1)

    await router.push('/parent/detail/1?tab=x')
    await flushPromises()

    // route.path excludes the query string → same key → instance preserved.
    // Pages' own query watchers remain the intended reaction mechanism.
    expect(detailMounted).toHaveBeenCalledTimes(1)
  })

  it('CORRECT: the flagged list stays cached across detail visits (the key does not break keep-alive)', async () => {
    const { App, router, listMounted } = makeSetup(true)

    await router.push('/parent/list')
    await router.isReady()
    mount(App, { global: { plugins: [router] } })
    await flushPromises()
    expect(listMounted).toHaveBeenCalledTimes(1)

    await router.push('/parent/detail/1')
    await flushPromises()
    await router.push('/parent/detail/2')
    await flushPromises()

    await router.push('/parent/list') // restored from cache, NOT remounted
    await flushPromises()
    expect(listMounted).toHaveBeenCalledTimes(1)
  })
})
