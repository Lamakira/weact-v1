import { describe, it, expect, beforeEach } from 'vitest'
import {
  setGoogleReauthTicket,
  takeGoogleReauthTicket,
  setPendingReauthPurpose,
  takePendingReauthPurpose,
} from '../googleReauth'

describe('googleReauth', () => {
  beforeEach(() => {
    sessionStorage.clear()
  })

  it('returns and removes the ticket when the purpose matches', () => {
    setGoogleReauthTicket('tok', 'delete_account')

    expect(takeGoogleReauthTicket('delete_account')).toBe('tok')
    expect(takeGoogleReauthTicket('delete_account')).toBeNull()
  })

  it('leaves the ticket in place when the purpose differs', () => {
    setGoogleReauthTicket('tok', 'delete_account')

    expect(takeGoogleReauthTicket('set_password')).toBeNull()
    expect(takeGoogleReauthTicket('delete_account')).toBe('tok')
  })

  it('returns null when nothing is stored', () => {
    expect(takeGoogleReauthTicket('set_password')).toBeNull()
  })

  it('stores the ticket under the weact.auth. prefix', () => {
    setGoogleReauthTicket('tok', 'set_password')

    expect(JSON.parse(sessionStorage.getItem('weact.auth.google_reauth') ?? 'null')).toEqual({
      token: 'tok',
      purpose: 'set_password',
    })
  })

  it('carries the pending purpose once', () => {
    setPendingReauthPurpose('set_password')

    expect(sessionStorage.getItem('weact.auth.google_reauth_purpose')).toBe('set_password')
    expect(takePendingReauthPurpose()).toBe('set_password')
    expect(takePendingReauthPurpose()).toBeNull()
  })

  it('ignores an unknown stored purpose', () => {
    sessionStorage.setItem('weact.auth.google_reauth_purpose', 'bogus')

    expect(takePendingReauthPurpose()).toBeNull()
  })
})
