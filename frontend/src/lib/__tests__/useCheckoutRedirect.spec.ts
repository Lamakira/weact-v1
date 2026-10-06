import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { useCheckoutRedirect } from '../useCheckoutRedirect'

function pageshow(persisted: boolean): void {
  const event = new Event('pageshow') as Event & { persisted: boolean }
  Object.defineProperty(event, 'persisted', { value: persisted })
  window.dispatchEvent(event)
}

function mountHook(cb: () => void) {
  return mount(
    defineComponent({
      setup() {
        useCheckoutRedirect(cb)
        return () => h('div')
      },
    }),
  )
}

describe('useCheckoutRedirect', () => {
  it('calls the callback on a persisted pageshow (bfcache restore)', () => {
    const cb = vi.fn()
    const wrapper = mountHook(cb)

    pageshow(true)

    expect(cb).toHaveBeenCalledOnce()
    wrapper.unmount()
  })

  it('does nothing on a non-persisted pageshow', () => {
    const cb = vi.fn()
    const wrapper = mountHook(cb)

    pageshow(false)

    expect(cb).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('stops listening after unmount', () => {
    const cb = vi.fn()
    mountHook(cb).unmount()

    pageshow(true)

    expect(cb).not.toHaveBeenCalled()
  })
})
