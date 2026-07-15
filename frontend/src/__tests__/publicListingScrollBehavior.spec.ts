/**
 * Prove-it regression test for review finding #10 (keep-alive v2).
 *
 * The Group A public listings (/faces, /missions, /ressources) are cached by
 * <keep-alive>, and their window scroll is restored by savedPosition on
 * browser BACK — but the detail pages' own "back to list" links are PUSH
 * navigations, for which scrollBehavior returned a flat { top: 0 }: the grid
 * came back from cache while the viewport jumped to the top, losing the
 * browse position on the very path the feature nurtures.
 *
 * Contract: scrollBehavior remembers the listing's window offset when leaving
 * it, and restores it when a push comes back from the listing's own detail
 * page to the same fullPath. savedPosition (back/forward) keeps priority, and
 * unknown/fresh targets still land at the top.
 */
import { describe, it, expect } from 'vitest'
import type { RouteLocationNormalized } from 'vue-router'
import router from '@/router'
import {
  beginPublicScrollNavigation,
  cancelPublicRouteEnter,
  finishPublicRouteEnter,
  getPublicScrollNavigationToken,
} from '@/router/publicScrollRestoration'

const scrollBehavior = router.options.scrollBehavior!

function loc(name: string, fullPath: string): RouteLocationNormalized {
  return { name, fullPath, hash: '' } as unknown as RouteLocationNormalized
}

function setWindowScrollY(value: number): void {
  Object.defineProperty(window, 'scrollY', { value, configurable: true })
}

function callScrollBehavior(
  to: RouteLocationNormalized,
  from: RouteLocationNormalized,
  savedPosition: { left: number; top: number } | null,
) {
  beginPublicScrollNavigation(to.fullPath)
  return scrollBehavior(to, from, savedPosition)
}

describe('scrollBehavior — push back to a cached public listing (finding #10)', () => {
  it('defers the remembered offset until the cached listing has finished entering', async () => {
    const listing = loc('public-faces-list', '/faces?page=deferred')
    const detail = loc(
      'public-face-profile',
      '/faces/adjoua?returnTo=%2Ffaces%3Fpage%3Ddeferred',
    )

    setWindowScrollY(900)
    callScrollBehavior(detail, listing, null)

    setWindowScrollY(0)
    const restoration = callScrollBehavior(listing, detail, null)
    expect(restoration).toBeInstanceOf(Promise)

    let settled = false
    void Promise.resolve(restoration).then(() => {
      settled = true
    })
    await Promise.resolve()

    // With mode="out-in", the detail page is still the document whose short
    // scroll range would clamp 900 here. The promise must stay pending until
    // the entering listing's transition-completion hook releases it.
    expect(settled).toBe(false)
    const navigationToken = getPublicScrollNavigationToken(listing.fullPath)
    expect(navigationToken).not.toBeNull()
    cancelPublicRouteEnter(navigationToken!)
  })

  it('restores the remembered window offset on the "back to list" push', async () => {
    // Leave /faces?page=3 scrolled at 500 towards a profile → lands at top.
    setWindowScrollY(500)
    expect(
      callScrollBehavior(
        loc('public-face-profile', '/faces/adjoua?returnTo=%2Ffaces%3Fpage%3D3'),
        loc('public-faces-list', '/faces?page=3'),
        null,
      ),
    ).toEqual({ top: 0 })

    // The profile's "Retour aux talents" link is a PUSH back to the listing:
    // the cached grid must reappear at the remembered offset, not the top.
    setWindowScrollY(0)
    const restoration = callScrollBehavior(
      loc('public-faces-list', '/faces?page=3'),
      loc('public-face-profile', '/faces/adjoua?returnTo=%2Ffaces%3Fpage%3D3'),
      null,
    )
    const navigationToken = getPublicScrollNavigationToken('/faces?page=3')
    expect(navigationToken).not.toBeNull()
    finishPublicRouteEnter(navigationToken!)
    await expect(restoration).resolves.toEqual({ top: 500 })
  })

  it('cancels a pending restoration when a newer navigation targets the same fullPath', async () => {
    const listing = loc('public-faces-list', '/faces?page=same')
    const detail = loc('public-face-profile', '/faces/adjoua')

    setWindowScrollY(700)
    callScrollBehavior(detail, listing, null)
    const staleRestoration = callScrollBehavior(listing, detail, null)

    beginPublicScrollNavigation(listing.fullPath)
    await expect(staleRestoration).resolves.toBe(false)
  })

  it('savedPosition (browser back/forward) keeps priority over the memory', () => {
    setWindowScrollY(500)
    callScrollBehavior(
      loc('public-face-profile', '/faces/adjoua'),
      loc('public-faces-list', '/faces?page=2'),
      null,
    )

    setWindowScrollY(0)
    expect(
      callScrollBehavior(
        loc('public-faces-list', '/faces?page=2'),
        loc('public-face-profile', '/faces/adjoua'),
        { left: 0, top: 250 },
      ),
    ).toEqual({ left: 0, top: 250 })
  })

  it('a listing fullPath without memory (or reached from elsewhere) lands at the top', () => {
    // Never-visited query combination → no memory.
    expect(
      callScrollBehavior(
        loc('public-faces-list', '/faces?page=9'),
        loc('public-face-profile', '/faces/adjoua'),
        null,
      ),
    ).toEqual({ top: 0 })

    // Same fullPath but coming from a non-detail route (navbar/dashboard):
    // the cache may have been purged there — start at the top.
    setWindowScrollY(500)
    callScrollBehavior(loc('home', '/'), loc('public-faces-list', '/faces?page=3'), null)
    expect(
      callScrollBehavior(loc('public-faces-list', '/faces?page=3'), loc('home', '/'), null),
    ).toEqual({ top: 0 })
  })
})
