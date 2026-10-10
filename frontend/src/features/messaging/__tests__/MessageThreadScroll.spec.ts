import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import MessageThread from '../components/MessageThread.vue'
import { makeContext, makeListItem, makeMessage } from './fixtures'
import type { Message } from '../types'

async function mountThread(messages: Message[]) {
  const wrapper = mount(MessageThread, {
    props: {
      participant: makeListItem().other_participant,
      context: makeContext('face'),
      role: 'face',
      messages,
      isLoading: false,
      isRefreshing: false,
      error: null,
      refreshError: null,
      isSending: false,
      sendError: null,
      modelValue: '',
      compact: false,
    },
  })
  await flushPromises() // laisse passer le défilement initial (onMounted)
  return wrapper
}

function setScrollGeometry(wrapper: Awaited<ReturnType<typeof mountThread>>, scrollTop: number) {
  const el = wrapper.find('[data-testid="thread-scroll"]').element as HTMLElement
  Object.defineProperty(el, 'scrollHeight', { configurable: true, value: 1000 })
  Object.defineProperty(el, 'clientHeight', { configurable: true, value: 400 })
  Object.defineProperty(el, 'scrollTop', { configurable: true, writable: true, value: scrollTop })
  return el
}

let scrollTo: ReturnType<typeof vi.fn>

beforeEach(() => {
  scrollTo = vi.fn()
  Element.prototype.scrollTo = scrollTo as unknown as typeof Element.prototype.scrollTo
})

describe('MessageThread — défilement et pastille « Nouveau message »', () => {
  it('fait défiler vers le bas si l\'utilisateur est déjà proche du bas', async () => {
    const wrapper = await mountThread([makeMessage({ id: 1 })])
    const el = setScrollGeometry(wrapper, 560) // 1000 - 560 - 400 = 40px du bas
    await el.dispatchEvent(new Event('scroll'))
    scrollTo.mockClear()

    await wrapper.setProps({ messages: [makeMessage({ id: 1 }), makeMessage({ id: 2 })] })
    await flushPromises()

    expect(scrollTo).toHaveBeenCalled()
    expect(wrapper.find('[data-testid="new-message-pill"]').exists()).toBe(false)
  })

  it('affiche la pastille sans défiler si l\'utilisateur lit plus haut', async () => {
    const wrapper = await mountThread([makeMessage({ id: 1 })])
    const el = setScrollGeometry(wrapper, 100) // loin du bas
    await el.dispatchEvent(new Event('scroll'))
    scrollTo.mockClear()

    await wrapper.setProps({ messages: [makeMessage({ id: 1 }), makeMessage({ id: 2 })] })
    await flushPromises()

    expect(scrollTo).not.toHaveBeenCalled()
    const pill = wrapper.find('[data-testid="new-message-pill"]')
    expect(pill.exists()).toBe(true)
    expect(pill.text()).toContain('Nouveau message')

    await pill.trigger('click')
    await flushPromises()
    expect(scrollTo).toHaveBeenCalled()
    expect(wrapper.find('[data-testid="new-message-pill"]').exists()).toBe(false)
  })

  it('défile toujours après l\'envoi d\'un message de soi, même en lisant plus haut', async () => {
    const wrapper = await mountThread([makeMessage({ id: 1 })])
    const el = setScrollGeometry(wrapper, 100)
    await el.dispatchEvent(new Event('scroll'))
    scrollTo.mockClear()

    await wrapper.setProps({
      messages: [makeMessage({ id: 1 }), makeMessage({ id: 2, is_own_message: true })],
    })
    await flushPromises()

    expect(scrollTo).toHaveBeenCalled()
    expect(wrapper.find('[data-testid="new-message-pill"]').exists()).toBe(false)
  })

  it('la pastille disparaît quand l\'utilisateur revient en bas', async () => {
    const wrapper = await mountThread([makeMessage({ id: 1 })])
    const el = setScrollGeometry(wrapper, 100)
    await el.dispatchEvent(new Event('scroll'))
    await wrapper.setProps({ messages: [makeMessage({ id: 1 }), makeMessage({ id: 2 })] })
    await flushPromises()
    expect(wrapper.find('[data-testid="new-message-pill"]').exists()).toBe(true)

    el.scrollTop = 600
    await el.dispatchEvent(new Event('scroll'))
    await flushPromises()

    expect(wrapper.find('[data-testid="new-message-pill"]').exists()).toBe(false)
  })
})
