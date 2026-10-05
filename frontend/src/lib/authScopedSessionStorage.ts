/**
 * Per-account state kept in `sessionStorage` (Google re-auth ticket, pending
 * registration…) lives under this prefix so the auth store can purge it on
 * logout / account switch without knowing the features that own the keys.
 */
export const AUTH_SESSION_PREFIX = 'weact.auth.'

export function clearAuthScopedSessionStorage(): void {
  try {
    const keys: string[] = []

    for (let i = 0; i < sessionStorage.length; i += 1) {
      const key = sessionStorage.key(i)
      if (key !== null && key.startsWith(AUTH_SESSION_PREFIX)) keys.push(key)
    }

    keys.forEach((key) => sessionStorage.removeItem(key))
  } catch {
    // Storage unavailable (private mode, quota…): nothing to purge.
  }
}
