import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import FaceResponseNotice from '../components/FaceResponseNotice.vue'
import { messagingApi } from '../services/messagingApi'
import type { FaceResponseNotice as Notice } from '../types'

vi.mock('../services/messagingApi', () => ({
  messagingApi: {
    getBookingFaceResponse: vi.fn(),
    getConversationFaceResponse: vi.fn(),
  },
}))

const api = vi.mocked(messagingApi)

function makeNotice(overrides: Partial<Notice> = {}): Notice {
  return {
    face_first_name: 'Aminata',
    unread_email_after_minutes: 60,
    urgent: false,
    typical_response_minutes: 165,
    overdue: false,
    ...overrides,
  }
}

function deferred<T>() {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((res) => {
    resolve = res
  })
  return { promise, resolve }
}

const SELECTOR = '[data-testid="face-response-notice"]'

async function mountNotice(props: Record<string, unknown> = {}) {
  const wrapper = mount(FaceResponseNotice, {
    props: { kind: 'booking', threadId: 'b-1', ...props },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('FaceResponseNotice', () => {
  it('état normal : e-mail après 1 heure et délai habituel', async () => {
    api.getBookingFaceResponse.mockResolvedValue(makeNotice())
    const wrapper = await mountNotice()

    const notice = wrapper.get(SELECTOR)
    expect(notice.attributes('data-state')).toBe('normal')
    expect(notice.attributes('role')).toBe('status')
    expect(notice.text()).toContain(
      'Aminata reçoit un e-mail si votre message reste non lu pendant 1 heure.',
    )
    expect(notice.text()).toContain('Délai de réponse habituel : sous 3 h.')
    expect(api.getBookingFaceResponse).toHaveBeenCalledWith('b-1')
  })

  it('sans historique suffisant : pas de phrase de délai habituel', async () => {
    api.getBookingFaceResponse.mockResolvedValue(makeNotice({ typical_response_minutes: null }))
    const wrapper = await mountNotice()

    expect(wrapper.get(SELECTOR).text()).not.toContain('Délai de réponse habituel')
  })

  it('état urgent : tournage proche et e-mail après 15 minutes', async () => {
    api.getBookingFaceResponse.mockResolvedValue(
      makeNotice({ urgent: true, unread_email_after_minutes: 15 }),
    )
    const wrapper = await mountNotice()

    const notice = wrapper.get(SELECTOR)
    expect(notice.attributes('data-state')).toBe('urgent')
    expect(notice.text()).toContain(
      'Tournage proche : Aminata reçoit un e-mail si votre message reste non lu pendant 15 minutes.',
    )
    expect(notice.text()).toContain('Délai de réponse habituel : sous 3 h.')
  })

  it('état overdue (prioritaire sur urgent) : l’équipe WeAct prend contact', async () => {
    api.getBookingFaceResponse.mockResolvedValue(makeNotice({ urgent: true, overdue: true }))
    const wrapper = await mountNotice()

    const notice = wrapper.get(SELECTOR)
    expect(notice.attributes('data-state')).toBe('overdue')
    expect(notice.text()).toBe(
      "Aminata n'a pas encore répondu. L'équipe WeAct va prendre contact directement.",
    )
  })

  it('n’affiche rien pendant le chargement', async () => {
    const pending = deferred<Notice>()
    api.getBookingFaceResponse.mockReturnValue(pending.promise)
    const wrapper = mount(FaceResponseNotice, { props: { kind: 'booking', threadId: 'b-1' } })
    await flushPromises()

    expect(wrapper.find(SELECTOR).exists()).toBe(false)

    pending.resolve(makeNotice())
    await flushPromises()
    expect(wrapper.find(SELECTOR).exists()).toBe(true)
  })

  it('n’affiche rien en cas d’erreur', async () => {
    api.getBookingFaceResponse.mockRejectedValue(new Error('403'))
    const wrapper = await mountNotice()

    expect(wrapper.find(SELECTOR).exists()).toBe(false)
  })

  it('kind=conversation appelle la route de conversation', async () => {
    api.getConversationFaceResponse.mockResolvedValue(makeNotice())
    await mountNotice({ kind: 'conversation', threadId: 'c-9' })

    expect(api.getConversationFaceResponse).toHaveBeenCalledWith('c-9')
    expect(api.getBookingFaceResponse).not.toHaveBeenCalled()
  })

  it('recharge quand refreshKey change', async () => {
    api.getBookingFaceResponse.mockResolvedValueOnce(makeNotice())
    const wrapper = await mountNotice({ refreshKey: 1 })

    api.getBookingFaceResponse.mockResolvedValueOnce(makeNotice({ overdue: true }))
    await wrapper.setProps({ refreshKey: 2 })
    await flushPromises()

    expect(api.getBookingFaceResponse).toHaveBeenCalledTimes(2)
    expect(wrapper.get(SELECTOR).attributes('data-state')).toBe('overdue')
  })

  it('ignore la réponse périmée quand l’identifiant change pendant la requête', async () => {
    const first = deferred<Notice>()
    const second = deferred<Notice>()
    api.getBookingFaceResponse.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise)
    const wrapper = mount(FaceResponseNotice, { props: { kind: 'booking', threadId: 'b-1' } })
    await flushPromises()

    await wrapper.setProps({ threadId: 'b-2' })
    await flushPromises()

    second.resolve(makeNotice({ face_first_name: 'Rachida' }))
    await flushPromises()
    first.resolve(makeNotice({ face_first_name: 'Aminata' }))
    await flushPromises()

    expect(wrapper.get(SELECTOR).text()).toContain('Rachida')
    expect(wrapper.get(SELECTOR).text()).not.toContain('Aminata')
  })
})
