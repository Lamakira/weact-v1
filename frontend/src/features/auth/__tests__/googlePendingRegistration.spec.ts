import { describe, it, expect, beforeEach } from 'vitest'
import {
  clearPendingGoogleRegistration,
  getPendingGoogleRegistration,
  setPendingGoogleRegistration,
  type PendingGoogleRegistration,
} from '../googlePendingRegistration'

const KEY = 'weact.auth.google_pending_registration'

const pending: PendingGoogleRegistration = {
  pending_token: 'pending-abc',
  email: 'jean@gmail.com',
  prenom: 'Jean',
  nom: 'Dupont',
  intent: 'face',
  redirect: null,
}

describe('googlePendingRegistration', () => {
  beforeEach(() => {
    sessionStorage.clear()
    localStorage.clear()
  })

  it('stores the ticket in sessionStorage under the weact.auth. key', () => {
    setPendingGoogleRegistration(pending)

    expect(JSON.parse(sessionStorage.getItem(KEY) ?? 'null')).toEqual(pending)
  })

  it('never touches localStorage', () => {
    setPendingGoogleRegistration(pending)
    getPendingGoogleRegistration()
    clearPendingGoogleRegistration()

    expect(localStorage.length).toBe(0)
    expect(localStorage.getItem(KEY)).toBeNull()
  })

  it('does not read a ticket that only exists in localStorage', () => {
    localStorage.setItem(KEY, JSON.stringify(pending))

    expect(getPendingGoogleRegistration()).toBeNull()
  })

  it('round-trips and clears', () => {
    setPendingGoogleRegistration(pending)
    expect(getPendingGoogleRegistration()).toEqual(pending)

    clearPendingGoogleRegistration()
    expect(getPendingGoogleRegistration()).toBeNull()
    expect(sessionStorage.getItem(KEY)).toBeNull()
  })
})
