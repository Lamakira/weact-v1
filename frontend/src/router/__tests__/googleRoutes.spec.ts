import { describe, it, expect } from 'vitest'
import router from '../index'

function findRoute(path: string) {
  return router.getRoutes().find((route) => route.path === path)
}

describe('Google OAuth routes', () => {
  /**
   * An already-authenticated user (re-authentication before deleting an account or
   * setting a password) lands here: a guest guard would redirect them to their
   * dashboard before the one-shot code is exchanged.
   */
  it('/auth/google/callback is NOT a guest-only route', () => {
    const route = findRoute('/auth/google/callback')

    expect(route).toBeDefined()
    expect(route?.meta.guest).toBeUndefined()
  })

  it('/auth/finaliser is a guest-only route', () => {
    const route = findRoute('/auth/finaliser')

    expect(route).toBeDefined()
    expect(route?.meta.guest).toBe(true)
  })
})
