import { describe, it, expect, beforeEach } from 'vitest'
import { createGoogleOAuthNonce, takeGoogleOAuthNonce } from '../googleOAuthNonce'

describe('googleOAuthNonce', () => {
  beforeEach(() => {
    sessionStorage.clear()
  })

  it('creates a 43-char base64url nonce and stores it', () => {
    const nonce = createGoogleOAuthNonce()

    expect(nonce).toMatch(/^[A-Za-z0-9_-]{43}$/)
    expect(sessionStorage.getItem('weact.oauth_nonce')).toBe(nonce)
  })

  it('creates a different nonce each time', () => {
    expect(createGoogleOAuthNonce()).not.toBe(createGoogleOAuthNonce())
  })

  it('takes the nonce once: read then removed', () => {
    const nonce = createGoogleOAuthNonce()

    expect(takeGoogleOAuthNonce()).toBe(nonce)
    expect(takeGoogleOAuthNonce()).toBeNull()
    expect(sessionStorage.getItem('weact.oauth_nonce')).toBeNull()
  })

  it('does not live under the weact.auth. prefix', () => {
    createGoogleOAuthNonce()

    expect(sessionStorage.getItem('weact.oauth_nonce')).not.toBeNull()
    expect('weact.oauth_nonce'.startsWith('weact.auth.')).toBe(false)
  })
})
