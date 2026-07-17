/**
 * Regression guard for the keep-alive architecture used by the dashboard/admin
 * listings (see project memory `project_keepalive_listings_reverted`).
 *
 * The bug: App.vue renders the LAYOUT as the depth-0 routed component. If that
 * component is keyed by `route.path`, the layout is destroyed+recreated on every
 * CHILD navigation, which destroys the `<keep-alive>` placed inside the layout —
 * so nothing is ever cached. Keying by the layout's OWN route (`matched[0].path`)
 * keeps the layout instance alive across child navigations, letting the nested
 * keep-alive actually cache the listing.
 *
 * These tests reproduce that structure with a real router (no browser) and assert
 * instance preservation via an onMounted counter — the signal the earlier manual
 * "smoke"/probe attempts failed to measure correctly.
 */
import { describe, it, expect, vi } from 'vitest'
import { defineComponent, h, onMounted, onActivated, KeepAlive } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory, RouterView, useRoute } from 'vue-router'

function makeSetup(keyFn: (route: ReturnType<typeof useRoute>) => unknown) {
  const listMounted = vi.fn()
  const listActivated = vi.fn()

  const ListPage = defineComponent({
    name: 'ListPage',
    setup() {
      onMounted(listMounted)
      onActivated(listActivated)
      return () => h('div', { class: 'list' }, 'list')
    },
  })

  const OtherPage = defineComponent({
    name: 'OtherPage',
    setup: () => () => h('div', { class: 'other' }, 'other'),
  })

  // Mirrors FaceLayout/ProducerLayout/AdminLayout: renders a nested <RouterView>
  // wrapped in <keep-alive> that includes the listing.
  const Layout = defineComponent({
    name: 'Layout',
    setup() {
      return () =>
        h('div', { class: 'layout' }, [
          h(RouterView, null, {
            default: ({ Component }: { Component: unknown }) =>
              h(KeepAlive, { include: ['ListPage'] }, () =>
                Component ? h(Component as never) : null,
              ),
          }),
        ])
    },
  })

  // Mirrors App.vue's dashboard/admin branch: the depth-0 component (the layout)
  // is keyed by `keyFn(route)`.
  const App = defineComponent({
    setup() {
      const route = useRoute()
      return () =>
        h(RouterView, null, {
          default: ({ Component }: { Component: unknown }) =>
            Component ? h(Component as never, { key: keyFn(route) as never }) : null,
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
          { path: 'list', name: 'list', component: ListPage },
          { path: 'other', name: 'other', component: OtherPage },
        ],
      },
    ],
  })

  return { App, router, listMounted, listActivated }
}

async function browseListThenBack(app: ReturnType<typeof makeSetup>) {
  const { App, router } = app
  await router.push('/parent/list')
  await router.isReady()
  mount(App, { global: { plugins: [router] } })
  await flushPromises()

  await router.push('/parent/other')
  await flushPromises()

  await router.push('/parent/list')
  await flushPromises()
}

describe('keep-alive across nested-layout child navigation', () => {
  it('CORRECT key (matched[0].path): layout persists, listing is cached (not remounted) on return', async () => {
    const app = makeSetup((route) => route.matched[0]?.path)
    await browseListThenBack(app)

    // Mounted exactly once → the ListPage instance was cached, not recreated.
    expect(app.listMounted).toHaveBeenCalledTimes(1)
    // Activated on first mount and again on return.
    expect(app.listActivated).toHaveBeenCalledTimes(2)
  })

  it('BROKEN key (route.path): layout remounts, listing is NOT cached (documents the reverted bug)', async () => {
    const app = makeSetup((route) => route.path)
    await browseListThenBack(app)

    // The full path changes per child nav → layout remounts → keep-alive is
    // destroyed → ListPage is mounted afresh on return. This is exactly the
    // no-op that shipped in the first attempt.
    expect(app.listMounted.mock.calls.length).toBeGreaterThan(1)
  })
})
