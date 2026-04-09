import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, nextTick } from 'vue'
import { useBookingChat, type UseBookingChatReturn } from '../useBookingChat'
import type { BookingMessage, BookingMessageListResponse } from '../../types'

// ──────────────────────────────────────────────────────────────
// Mocks — hoisted to top by Vitest
// ──────────────────────────────────────────────────────────────

const { mockListenCallback, mockChannel, mockEcho } = vi.hoisted(() => {
  const mockListenCallback = vi.fn()
  const mockChannel = {
    listen: vi.fn().mockImplementation((_event: string, cb: unknown) => {
      mockListenCallback.mockImplementation(cb as (...args: unknown[]) => unknown)
      return mockChannel
    }),
    error: vi.fn().mockReturnThis(),
  }
  const mockEcho = {
    private: vi.fn(() => mockChannel),
    leave: vi.fn(),
  }
  return { mockListenCallback, mockChannel, mockEcho }
})

vi.mock('@/plugins/echo', () => ({
  echo: mockEcho,
}))

vi.mock('../../services/bookingChatApi', () => ({
  bookingChatApi: {
    fetchMessages: vi.fn(),
    sendMessage: vi.fn(),
  },
}))

import { bookingChatApi } from '../../services/bookingChatApi'

const mountedWrappers: Array<ReturnType<typeof mount>> = []

// ──────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────

function makeMessage(overrides: Partial<BookingMessage> = {}): BookingMessage {
  return {
    id: 1,
    booking_id: 10,
    sender_id: 100,
    sender_name: 'Alice',
    content: 'Hello!',
    is_own_message: false,
    created_at: new Date().toISOString(),
    ...overrides,
  }
}

function makeListResponse(messages: BookingMessage[]): BookingMessageListResponse {
  return {
    data: messages,
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, last_page: 1, per_page: 30, total: messages.length },
  }
}

async function mountComposable(
  bookingId: string,
  realtimeChannelKey: number,
  currentUserId: number,
): Promise<{ wrapper: ReturnType<typeof mount>; result: UseBookingChatReturn; listenCallStart: number }> {
  let result!: UseBookingChatReturn
  const listenCallStart = mockChannel.listen.mock.calls.length

  const wrapper = mount(
    defineComponent({
      setup() {
        result = useBookingChat(bookingId, realtimeChannelKey, currentUserId)
        return result
      },
      template: '<div />',
    }),
  )
  mountedWrappers.push(wrapper)

  // Wait for onMounted async operations to complete
  await flushPromises()
  await flushPromises()
  for (let i = 0; i < 6 && mockChannel.listen.mock.calls.length === 0; i += 1) {
    await flushPromises()
    await nextTick()
  }
  await nextTick()

  return { wrapper, result, listenCallStart }
}

type WsPayload = {
  id: number
  booking_id: number
  sender_id: number
  sender_name: string
  content: string
  created_at: string
}

async function emitWsPayload(_fromCall: number, payload: WsPayload): Promise<void> {
  for (let i = 0; i < 15 && mockChannel.listen.mock.calls.length === 0; i += 1) {
    await flushPromises()
    await nextTick()
  }

  const callback = mockListenCallback.getMockImplementation() as ((event: WsPayload) => void) | undefined

  expect(mockChannel.listen).toHaveBeenCalled()
  expect(typeof callback).toBe('function')

  callback!(payload)
}

// ──────────────────────────────────────────────────────────────
// Tests
// ──────────────────────────────────────────────────────────────

describe('useBookingChat', () => {
  const BOOKING_ID = 'booking-uuid-10'
  const REALTIME_CHANNEL_KEY = 10
  const CURRENT_USER_ID = 42

  beforeEach(() => {
    vi.clearAllMocks()
    // Restore listen behavior after clearAllMocks
    mockChannel.listen.mockImplementation((_event: string, cb: unknown) => {
      mockListenCallback.mockImplementation(cb as (...args: unknown[]) => unknown)
      return mockChannel
    })
    mockChannel.error.mockReturnValue(mockChannel)
    mockEcho.private.mockReturnValue(mockChannel)
    // Default: fetchMessages returns empty list
    vi.mocked(bookingChatApi.fetchMessages).mockResolvedValue(makeListResponse([]))
  })

  afterEach(() => {
    while (mountedWrappers.length > 0) {
      const wrapper = mountedWrappers.pop()
      wrapper?.unmount()
    }
  })

  it('loads messages on mount', async () => {
    const messages = [makeMessage({ id: 1 }), makeMessage({ id: 2, sender_id: 99 })]
    vi.mocked(bookingChatApi.fetchMessages).mockResolvedValue(makeListResponse(messages))

    const { result } = await mountComposable(BOOKING_ID, REALTIME_CHANNEL_KEY, CURRENT_USER_ID)

    expect(bookingChatApi.fetchMessages).toHaveBeenCalledWith(BOOKING_ID)
    expect(mockEcho.private).toHaveBeenCalledWith(`booking.${REALTIME_CHANNEL_KEY}`)
    expect(result.messages.value).toHaveLength(2)
    expect(result.messages.value[0].id).toBe(1)
  })

  it('isLoading is false after messages are loaded', async () => {
    const { result } = await mountComposable(BOOKING_ID, REALTIME_CHANNEL_KEY, CURRENT_USER_ID)
    expect(result.isLoading.value).toBe(false)
  })

  it('sendMessage calls API and appends the message to the list', async () => {
    const newMessage = makeMessage({ id: 5, content: 'Hi there', is_own_message: true })
    vi.mocked(bookingChatApi.sendMessage).mockResolvedValue({ data: newMessage, message: 'Sent' })

    const { result } = await mountComposable(BOOKING_ID, REALTIME_CHANNEL_KEY, CURRENT_USER_ID)
    await result.sendMessage('Hi there')

    expect(bookingChatApi.sendMessage).toHaveBeenCalledWith(BOOKING_ID, 'Hi there')
    expect(result.messages.value).toContainEqual(newMessage)
  })

  it('sendMessage does nothing if content is empty', async () => {
    const { result } = await mountComposable(BOOKING_ID, REALTIME_CHANNEL_KEY, CURRENT_USER_ID)
    await result.sendMessage('   ')
    expect(bookingChatApi.sendMessage).not.toHaveBeenCalled()
  })

  it('sendMessage does nothing if already sending', async () => {
    let resolveP!: (v: { data: BookingMessage; message: string }) => void
    vi.mocked(bookingChatApi.sendMessage).mockReturnValue(
      new Promise((res) => { resolveP = res }),
    )

    const { result } = await mountComposable(BOOKING_ID, REALTIME_CHANNEL_KEY, CURRENT_USER_ID)

    const p1 = result.sendMessage('first')
    await result.sendMessage('second')
    expect(bookingChatApi.sendMessage).toHaveBeenCalledTimes(1)

    resolveP({ data: makeMessage(), message: 'ok' })
    await p1
  })

  it('incoming WS event adds message to list', async () => {
    const { result, listenCallStart } = await mountComposable(BOOKING_ID, REALTIME_CHANNEL_KEY, CURRENT_USER_ID)

    await emitWsPayload(listenCallStart, {
      id: 99,
      booking_id: BOOKING_ID,
      sender_id: 77,
      sender_name: 'Bob',
      content: 'Hey!',
      created_at: new Date().toISOString(),
    })

    await nextTick()
    const added = result.messages.value.find((m) => m.id === 99)
    expect(added).toBeDefined()
    expect(added!.content).toBe('Hey!')
  })

  it('WS event sets is_own_message=true when sender_id matches currentUserId', async () => {
    const { result, listenCallStart } = await mountComposable(BOOKING_ID, REALTIME_CHANNEL_KEY, CURRENT_USER_ID)

    await emitWsPayload(listenCallStart, {
      id: 50,
      booking_id: BOOKING_ID,
      sender_id: CURRENT_USER_ID,
      sender_name: 'Me',
      content: 'My message',
      created_at: new Date().toISOString(),
    })

    await nextTick()
    const msg = result.messages.value.find((m) => m.id === 50)!
    expect(msg.is_own_message).toBe(true)
  })

  it('WS event sets is_own_message=false when sender_id differs from currentUserId', async () => {
    const { result, listenCallStart } = await mountComposable(BOOKING_ID, REALTIME_CHANNEL_KEY, CURRENT_USER_ID)

    await emitWsPayload(listenCallStart, {
      id: 51,
      booking_id: BOOKING_ID,
      sender_id: 999,
      sender_name: 'Other',
      content: 'Their message',
      created_at: new Date().toISOString(),
    })

    await nextTick()
    const msg = result.messages.value.find((m) => m.id === 51)!
    expect(msg.is_own_message).toBe(false)
  })

  it('duplicate WS event (same id) is ignored', async () => {
    const existingMsg = makeMessage({ id: 10, content: 'Original' })
    vi.mocked(bookingChatApi.fetchMessages).mockResolvedValue(makeListResponse([existingMsg]))

    const { result, listenCallStart } = await mountComposable(BOOKING_ID, REALTIME_CHANNEL_KEY, CURRENT_USER_ID)
    expect(result.messages.value).toHaveLength(1)

    await emitWsPayload(listenCallStart, {
      id: 10,
      booking_id: BOOKING_ID,
      sender_id: 1,
      sender_name: 'A',
      content: 'Duplicate',
      created_at: new Date().toISOString(),
    })
    await nextTick()

    expect(result.messages.value).toHaveLength(1)
    expect(result.messages.value[0].content).toBe('Original')
  })

  it('refreshMessages resets reverbError and reloads messages', async () => {
    const { result } = await mountComposable(BOOKING_ID, REALTIME_CHANNEL_KEY, CURRENT_USER_ID)

    result.reverbError.value = true

    const freshMessages = [makeMessage({ id: 20 })]
    vi.mocked(bookingChatApi.fetchMessages).mockResolvedValue(makeListResponse(freshMessages))

    await result.refreshMessages()
    await nextTick()

    expect(result.reverbError.value).toBe(false)
    expect(result.messages.value).toHaveLength(1)
    expect(result.messages.value[0].id).toBe(20)
  })

  it('channel is left on unmount', async () => {
    const { wrapper } = await mountComposable(BOOKING_ID, REALTIME_CHANNEL_KEY, CURRENT_USER_ID)

    wrapper.unmount()
    await flushPromises()
    await nextTick()

    expect(mockEcho.leave).toHaveBeenCalledWith(`booking.${REALTIME_CHANNEL_KEY}`)
  })
})
