import { beforeEach, describe, expect, it } from 'vitest'
import {
  SUBSCRIPTION_RETURN_KEY,
  consumeSubscriptionReturn,
  sanitizeFaceRedirect,
  storeSubscriptionReturn,
} from '../subscriptionReturn'
import { clearAuthScopedSessionStorage } from '@/lib/authScopedSessionStorage'

describe('sanitizeFaceRedirect', () => {
  it.each(['/face/missions/abc', '/face/bookings/12?tab=x', '/face/missions/abc#top'])('accepte %s', (p) => {
    expect(sanitizeFaceRedirect(p)).toBe(p)
  })

  it.each([
    'https://evil.com',
    '//evil.com',
    '/\\evil.com',
    '/face//evil.com',
    '/face/../producer',
    '/producer/missions',
    '/facebook',
    'javascript:alert(1)',
    '/face/x\ny',
    '',
    '/face',
    null,
    undefined,
    42,
    ['/face/a'],
  ])('rejette %s', (p) => {
    expect(sanitizeFaceRedirect(p)).toBeNull()
  })
})

describe('store / consume', () => {
  beforeEach(() => sessionStorage.clear())

  it('mémorise puis consomme une seule fois', () => {
    storeSubscriptionReturn('/face/missions/abc')
    expect(consumeSubscriptionReturn()).toBe('/face/missions/abc')
    expect(consumeSubscriptionReturn()).toBeNull()
  })

  it('une origine invalide efface la précédente', () => {
    storeSubscriptionReturn('/face/missions/abc')
    storeSubscriptionReturn('https://evil.com')
    expect(sessionStorage.getItem(SUBSCRIPTION_RETURN_KEY)).toBeNull()
  })

  it('revalide à la lecture une valeur falsifiée', () => {
    sessionStorage.setItem(SUBSCRIPTION_RETURN_KEY, '//evil.com')
    expect(consumeSubscriptionReturn()).toBeNull()
  })

  it('est purgée à la déconnexion (préfixe auth-scopé)', () => {
    storeSubscriptionReturn('/face/missions/abc')
    clearAuthScopedSessionStorage()
    expect(consumeSubscriptionReturn()).toBeNull()
  })
})
