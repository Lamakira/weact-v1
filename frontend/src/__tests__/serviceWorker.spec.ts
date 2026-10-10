import { describe, it, expect, vi, beforeEach } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

/**
 * public/sw.js is a classic script: evaluate it against a fake `self` and drive
 * its listeners directly (push display, notification click routing).
 */
const ORIGIN = 'https://weact.example'
const source = readFileSync(resolve(process.cwd(), 'public/sw.js'), 'utf8')

type Listener = (event: Record<string, unknown>) => void

interface FakeClient {
  url: string
  focus: ReturnType<typeof vi.fn>
  navigate?: ReturnType<typeof vi.fn>
}

function loadWorker(clients: FakeClient[] = []) {
  const listeners: Record<string, Listener> = {}
  const showNotification = vi.fn().mockResolvedValue(undefined)
  const openWindow = vi.fn().mockResolvedValue(null)
  const fakeSelf = {
    location: { origin: ORIGIN },
    registration: { showNotification },
    clients: {
      claim: vi.fn().mockResolvedValue(undefined),
      matchAll: vi.fn().mockResolvedValue(clients),
      openWindow,
    },
    skipWaiting: vi.fn(),
    addEventListener: (name: string, listener: Listener) => {
      listeners[name] = listener
    },
  }

  new Function('self', source)(fakeSelf)

  return { listeners, showNotification, openWindow, fakeSelf }
}

function pushEvent(payload: unknown, raw?: string) {
  const waits: Promise<unknown>[] = []
  return {
    event: {
      data: {
        json: () => {
          if (raw !== undefined) throw new Error('not json')
          return payload
        },
        text: () => raw ?? '',
      },
      waitUntil: (p: Promise<unknown>) => waits.push(p),
    },
    settled: () => Promise.all(waits),
  }
}

function clickEvent(url?: string) {
  const waits: Promise<unknown>[] = []
  const close = vi.fn()
  return {
    event: {
      notification: { close, data: url === undefined ? {} : { url } },
      waitUntil: (p: Promise<unknown>) => waits.push(p),
    },
    close,
    settled: () => Promise.all(waits),
  }
}

describe('service worker (push only)', () => {
  beforeEach(() => vi.clearAllMocks())

  it('registers no fetch handler (no caching)', () => {
    const { listeners } = loadWorker()

    expect(Object.keys(listeners).sort()).toEqual(['activate', 'install', 'notificationclick', 'push'])
    expect(listeners.fetch).toBeUndefined()
  })

  it('takes control immediately on install/activate', async () => {
    const { listeners, fakeSelf } = loadWorker()

    listeners.install!({})
    const waits: Promise<unknown>[] = []
    listeners.activate!({ waitUntil: (p: Promise<unknown>) => waits.push(p) })
    await Promise.all(waits)

    expect(fakeSelf.skipWaiting).toHaveBeenCalled()
    expect(fakeSelf.clients.claim).toHaveBeenCalled()
  })

  it('shows the notification with title, body, icon, badge, tag and url', async () => {
    const { listeners, showNotification } = loadWorker()
    const { event, settled } = pushEvent({
      title: 'Booking',
      body: 'Une Face a accepté',
      tag: 'booking_accepted:abc',
      data: { url: '/producer/bookings/abc' },
    })

    listeners.push!(event)
    await settled()

    expect(showNotification).toHaveBeenCalledWith('Booking', {
      body: 'Une Face a accepté',
      icon: '/icons/icon-192.png',
      badge: '/icons/badge-96.png',
      tag: 'booking_accepted:abc',
      renotify: true,
      data: { url: '/producer/bookings/abc' },
    })
  })

  it('stays silent when a focused window already shows the target screen', async () => {
    const focusedSame: FakeClient & { focused: boolean } = {
      url: `${ORIGIN}/face/conversations/abc?x=1`,
      focus: vi.fn(),
      focused: true,
    }
    const { listeners, showNotification } = loadWorker([focusedSame])
    const { event, settled } = pushEvent({ title: 'Nouveau message', data: { url: '/face/conversations/abc' } })

    listeners.push!(event)
    await settled()

    expect(showNotification).not.toHaveBeenCalled()
  })

  it('still shows it when the matching window is not focused or shows another screen', async () => {
    const unfocusedSame = { url: `${ORIGIN}/face/conversations/abc`, focus: vi.fn(), focused: false }
    const focusedOther = { url: `${ORIGIN}/face/dashboard`, focus: vi.fn(), focused: true }
    const { listeners, showNotification } = loadWorker([unfocusedSame, focusedOther])
    const { event, settled } = pushEvent({ title: 'Nouveau message', data: { url: '/face/conversations/abc' } })

    listeners.push!(event)
    await settled()

    expect(showNotification).toHaveBeenCalledOnce()
  })

  it('falls back to a generic notification on an empty or non-JSON payload', async () => {
    const { listeners, showNotification } = loadWorker()

    const empty = pushEvent({})
    listeners.push!(empty.event)
    await empty.settled()
    expect(showNotification).toHaveBeenLastCalledWith(
      'WeAct',
      expect.objectContaining({ body: '', data: { url: '/' }, renotify: false }),
    )

    const text = pushEvent(null, 'texte brut')
    listeners.push!(text.event)
    await text.settled()
    expect(showNotification).toHaveBeenLastCalledWith(
      'WeAct',
      expect.objectContaining({ body: 'texte brut' }),
    )
  })

  it('focuses and navigates an open WeAct window on click', async () => {
    const client: FakeClient = {
      url: `${ORIGIN}/face/dashboard`,
      focus: vi.fn().mockResolvedValue(undefined),
      navigate: vi.fn().mockResolvedValue(null),
    }
    client.focus.mockResolvedValue(client)
    const { listeners, openWindow } = loadWorker([{ url: 'https://other.example/', focus: vi.fn() }, client])
    const { event, close, settled } = clickEvent('/face/bookings/abc')

    listeners.notificationclick!(event)
    await settled()

    expect(close).toHaveBeenCalled()
    expect(client.focus).toHaveBeenCalled()
    expect(client.navigate).toHaveBeenCalledWith(`${ORIGIN}/face/bookings/abc`)
    expect(openWindow).not.toHaveBeenCalled()
  })

  it('opens a new window when no WeAct tab is open', async () => {
    const { listeners, openWindow } = loadWorker([{ url: 'https://other.example/', focus: vi.fn() }])
    const { event, settled } = clickEvent('/producer/conversations/xyz')

    listeners.notificationclick!(event)
    await settled()

    expect(openWindow).toHaveBeenCalledWith(`${ORIGIN}/producer/conversations/xyz`)
  })

  it('opens a new window when the existing tab cannot be navigated', async () => {
    const client: FakeClient = {
      url: `${ORIGIN}/`,
      focus: vi.fn().mockRejectedValue(new Error('no focus')),
    }
    const { listeners, openWindow } = loadWorker([client])
    const { event, settled } = clickEvent('/face/wallet')

    listeners.notificationclick!(event)
    await settled()

    expect(openWindow).toHaveBeenCalledWith(`${ORIGIN}/face/wallet`)
  })

  it('never navigates off-origin: foreign or missing urls fall back to the home page', async () => {
    const { listeners, openWindow } = loadWorker([])

    for (const url of ['https://evil.example/phish', undefined]) {
      const { event, settled } = clickEvent(url)
      listeners.notificationclick!(event)
      await settled()
    }

    expect(openWindow).toHaveBeenNthCalledWith(1, `${ORIGIN}/`)
    expect(openWindow).toHaveBeenNthCalledWith(2, `${ORIGIN}/`)
  })
})
