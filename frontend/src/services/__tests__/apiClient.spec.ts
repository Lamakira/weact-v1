import { describe, it, expect, beforeEach, vi } from 'vitest'
import type { AxiosError } from 'axios'
import { createPinia } from 'pinia'

const callOrder: string[] = []
const mockNotificationUnsubscribe = vi.fn(() => {
  callOrder.push('unsubscribe')
})
const mockAuthClearAuth = vi.fn(() => {
  callOrder.push('clearAuth')
})

vi.mock('@/stores/notification', () => ({
  useNotificationStore: vi.fn(() => ({
    unsubscribe: mockNotificationUnsubscribe,
  })),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: vi.fn(() => ({
    clearAuth: mockAuthClearAuth,
  })),
}))

import apiClient, { setPinia } from '../apiClient'

interface ResponseInterceptorManager {
  handlers: Array<{
    rejected?: (error: AxiosError) => Promise<never>
  }>
}

function getRejectedHandler(): (error: AxiosError) => Promise<never> {
  const responseInterceptors = apiClient.interceptors.response as unknown as ResponseInterceptorManager
  const rejected = responseInterceptors.handlers[0]?.rejected

  if (!rejected) {
    throw new Error('Response rejected interceptor not registered')
  }

  return rejected
}

describe('apiClient 401 interceptor', () => {
  beforeEach(() => {
    callOrder.length = 0
    vi.clearAllMocks()
    setPinia(createPinia())
    localStorage.clear()
  })

  it('unsubscribes notifications before clearing auth on 401', async () => {
    localStorage.setItem('auth_token', 'test-token')
    localStorage.setItem('auth_user', JSON.stringify({ id: 42 }))

    const rejected = getRejectedHandler()
    const error = {
      response: {
        status: 401,
      },
    } as AxiosError

    await rejected(error).catch(() => undefined)
    await vi.dynamicImportSettled()

    expect(mockNotificationUnsubscribe).toHaveBeenCalledOnce()
    expect(mockAuthClearAuth).toHaveBeenCalledOnce()
    expect(callOrder).toEqual(['unsubscribe', 'clearAuth'])
    expect(localStorage.getItem('auth_token')).toBeNull()
    expect(localStorage.getItem('auth_user')).toBeNull()
  })
})
