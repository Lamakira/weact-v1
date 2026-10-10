import { vi } from 'vitest'

type Callback = (payload: unknown) => void

/** Canal Echo factice : enregistre les écouteurs et permet de simuler les événements. */
export class FakeChannel {
  listeners = new Map<string, Callback[]>()
  errorCallbacks: Array<() => void> = []
  subscribedCallbacks: Array<() => void> = []

  listen(event: string, callback: Callback): this {
    this.listeners.set(event, [...(this.listeners.get(event) ?? []), callback])
    return this
  }

  stopListening(event: string, callback?: Callback): this {
    const remaining = callback
      ? (this.listeners.get(event) ?? []).filter((c) => c !== callback)
      : []
    this.listeners.set(event, remaining)
    return this
  }

  error(callback: () => void): this {
    this.errorCallbacks.push(callback)
    return this
  }

  subscribed(callback: () => void): this {
    this.subscribedCallbacks.push(callback)
    return this
  }

  emit(event: string, payload: unknown): void {
    for (const callback of this.listeners.get(event) ?? []) callback(payload)
  }

  listenerCount(event: string): number {
    return (this.listeners.get(event) ?? []).length
  }

  fail(): void {
    this.errorCallbacks.forEach((callback) => callback())
  }

  connect(): void {
    this.subscribedCallbacks.forEach((callback) => callback())
  }
}

export const channels = new Map<string, FakeChannel>()

type StateChange = (states: { previous: string; current: string }) => void

/** Connexion Pusher factice : état + `state_change`. */
export const fakeConnection = {
  state: 'connecting',
  handlers: [] as StateChange[],
  bind: vi.fn((event: string, callback: StateChange) => {
    if (event === 'state_change') fakeConnection.handlers.push(callback)
  }),
  unbind: vi.fn((event: string, callback: StateChange) => {
    if (event === 'state_change') {
      fakeConnection.handlers = fakeConnection.handlers.filter((h) => h !== callback)
    }
  }),
  setState(next: string): void {
    const previous = fakeConnection.state
    fakeConnection.state = next
    for (const handler of [...fakeConnection.handlers]) handler({ previous, current: next })
  },
}

export const fakeEcho = {
  connector: { options: {} as Record<string, unknown>, pusher: { connection: fakeConnection } },
  private: vi.fn((name: string) => {
    let channel = channels.get(name)
    if (!channel) {
      channel = new FakeChannel()
      channels.set(name, channel)
    }
    return channel
  }),
  leave: vi.fn((name: string) => {
    channels.delete(name)
  }),
  socketId: vi.fn(() => '123.456'),
}

export function resetFakeEcho(): void {
  channels.clear()
  fakeConnection.handlers = []
  fakeConnection.state = 'connecting'
  fakeEcho.private.mockClear()
  fakeEcho.leave.mockClear()
}
