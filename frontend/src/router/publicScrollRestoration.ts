interface PublicScrollPosition {
  left?: number
  top?: number
}

interface PendingPublicScrollRestoration {
  navigationToken: symbol
  position: PublicScrollPosition
  resolve: (position: PublicScrollPosition | false) => void
}

let pendingRestoration: PendingPublicScrollRestoration | null = null
let activeNavigation: { fullPath: string; token: symbol } | null = null

export function beginPublicScrollNavigation(fullPath: string): void {
  pendingRestoration?.resolve(false)
  pendingRestoration = null
  activeNavigation = { fullPath, token: Symbol(fullPath) }
}

export function getPublicScrollNavigationToken(fullPath: string): symbol | null {
  return activeNavigation?.fullPath === fullPath ? activeNavigation.token : null
}

export function deferPublicScrollRestoration(
  fullPath: string,
  position: PublicScrollPosition,
): Promise<PublicScrollPosition | false> {
  pendingRestoration?.resolve(false)
  const navigationToken = getPublicScrollNavigationToken(fullPath) ?? Symbol(fullPath)

  return new Promise((resolve) => {
    pendingRestoration = { navigationToken, position, resolve }
  })
}

export function finishPublicRouteEnter(navigationToken: symbol): void {
  if (pendingRestoration?.navigationToken !== navigationToken) return

  const { position, resolve } = pendingRestoration
  pendingRestoration = null
  resolve(position)
}

export function cancelPublicRouteEnter(navigationToken: symbol): void {
  if (pendingRestoration?.navigationToken !== navigationToken) return

  const { resolve } = pendingRestoration
  pendingRestoration = null
  resolve(false)
}
