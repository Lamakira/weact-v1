import { describe, it, expect, vi } from 'vitest'
import { defineComponent, h, onMounted, KeepAlive } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory, RouterView } from 'vue-router'
import { useRefreshOnReturn } from '../useRefreshOnReturn'

describe('useRefreshOnReturn', () => {
  it('runs refresh on RETURN to a cached page, but not on first mount', async () => {
    const initialLoad = vi.fn()
    const refresh = vi.fn()

    const ListPage = defineComponent({
      name: 'ListPage',
      setup() {
        onMounted(initialLoad)
        useRefreshOnReturn(refresh)
        return () => h('div', 'list')
      },
    })
    const OtherPage = defineComponent({
      name: 'OtherPage',
      setup: () => () => h('div', 'other'),
    })
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
        { path: '/other', name: 'other', component: OtherPage },
      ],
    })

    router.push('/list')
    await router.isReady()
    mount(Root, { global: { plugins: [router] } })
    await flushPromises()

    // First mount: onMounted did the initial load; refresh must NOT fire yet.
    expect(initialLoad).toHaveBeenCalledTimes(1)
    expect(refresh).not.toHaveBeenCalled()

    await router.push('/other')
    await flushPromises()
    await router.push('/list')
    await flushPromises()

    // Cached (not remounted) → onMounted still once; refresh fired on return.
    expect(initialLoad).toHaveBeenCalledTimes(1)
    expect(refresh).toHaveBeenCalledTimes(1)
  })
})
