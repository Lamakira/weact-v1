/**
 * Guards the pattern used by cached listings that sync state from route.query
 * (FaceBookings, FaceCandidatures, ProducerBookings): a `watch(() => route.query)`
 * on a <keep-alive>-cached page keeps running while off-screen (Vue 3.5), so it
 * reacts to the query churn of navigating AWAY to a detail page — reading an
 * empty query and corrupting pagination. Guarding the callback by `route.name`
 * makes it a no-op unless we're actually on the list, and it fires exactly once
 * on return (the intended refresh).
 *
 * (Pausing the watcher on `deactivated` does NOT work: the pre-flush watcher
 * fires before `onDeactivated` runs. Verified — hence the route.name guard.)
 */
import { describe, it, expect, vi } from 'vitest'
import { defineComponent, h, watch, KeepAlive } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory, RouterView, useRoute } from 'vue-router'

describe('route.name-guarded query watch on a cached page', () => {
  it('ignores query churn from navigating away, re-syncs exactly once on return', async () => {
    const sync = vi.fn()

    const ListPage = defineComponent({
      name: 'ListPage',
      setup() {
        const route = useRoute()
        watch(
          () => route.query,
          () => {
            if (route.name !== 'list') return
            sync()
          },
        )
        return () => h('div', 'list')
      },
    })
    const DetailPage = defineComponent({ name: 'DetailPage', setup: () => () => h('div', 'detail') })
    const Root = defineComponent({
      setup: () => () =>
        h(RouterView, null, {
          default: ({ Component }: { Component: unknown }) =>
            h(KeepAlive, { include: ['ListPage'] }, () => (Component ? h(Component as never) : null)),
        }),
    })

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/list', name: 'list', component: ListPage },
        { path: '/detail', name: 'detail', component: DetailPage },
      ],
    })

    router.push('/list?page=2')
    await router.isReady()
    mount(Root, { global: { plugins: [router] } })
    await flushPromises()
    expect(sync).not.toHaveBeenCalled()

    await router.push('/detail') // away → route.name = 'detail' → guarded out
    await flushPromises()
    expect(sync).not.toHaveBeenCalled()

    await router.push('/list?page=2') // return → route.name = 'list' → syncs once
    await flushPromises()
    expect(sync).toHaveBeenCalledTimes(1)
  })
})
