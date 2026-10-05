import { describe, it, expect, beforeEach } from 'vitest'
import { AUTH_SESSION_PREFIX, clearAuthScopedSessionStorage } from '../authScopedSessionStorage'

describe('authScopedSessionStorage', () => {
  beforeEach(() => {
    sessionStorage.clear()
  })

  it('uses the weact.auth. prefix', () => {
    expect(AUTH_SESSION_PREFIX).toBe('weact.auth.')
  })

  it('removes every prefixed key and nothing else', () => {
    sessionStorage.setItem('weact.auth.a', '1')
    sessionStorage.setItem('weact.auth.b', '2')
    sessionStorage.setItem('weact.oauth_nonce', 'keep')
    sessionStorage.setItem('other', 'keep')

    clearAuthScopedSessionStorage()

    expect(sessionStorage.getItem('weact.auth.a')).toBeNull()
    expect(sessionStorage.getItem('weact.auth.b')).toBeNull()
    expect(sessionStorage.getItem('weact.oauth_nonce')).toBe('keep')
    expect(sessionStorage.getItem('other')).toBe('keep')
  })
})
