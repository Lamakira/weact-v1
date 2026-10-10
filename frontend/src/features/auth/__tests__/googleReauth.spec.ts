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
    setGoogleReauthTicket('tok', 'delete_account', 1)

    expect(takeGoogleReauthTicket('delete_account', 1)).toBe('tok')
    expect(takeGoogleReauthTicket('delete_account', 1)).toBeNull()
  })

  it('leaves the ticket in place when the purpose differs', () => {
    setGoogleReauthTicket('tok', 'delete_account', 1)

    expect(takeGoogleReauthTicket('set_password', 1)).toBeNull()
    expect(takeGoogleReauthTicket('delete_account', 1)).toBe('tok')
  })

  it('returns null when nothing is stored', () => {
    expect(takeGoogleReauthTicket('set_password', 1)).toBeNull()
  })

  it('stores the ticket under the weact.auth. prefix', () => {
    setGoogleReauthTicket('tok', 'set_password', 1)

    expect(JSON.parse(sessionStorage.getItem('weact.auth.google_reauth') ?? 'null')).toEqual({
      token: 'tok',
      purpose: 'set_password',
      userId: 1,
    })
  })

  it('returns null and removes a ticket minted for another account', () => {
    setGoogleReauthTicket('tok', 'delete_account', 1)

    expect(takeGoogleReauthTicket('delete_account', 2)).toBeNull()
    // Gone for good: the rightful owner does not get it back either.
    expect(sessionStorage.getItem('weact.auth.google_reauth')).toBeNull()
    expect(takeGoogleReauthTicket('delete_account', 1)).toBeNull()
  })

  it('returns null and removes the ticket when no account is logged in', () => {
    setGoogleReauthTicket('tok', 'delete_account', 1)

    expect(takeGoogleReauthTicket('delete_account', undefined)).toBeNull()
    expect(sessionStorage.getItem('weact.auth.google_reauth')).toBeNull()
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
