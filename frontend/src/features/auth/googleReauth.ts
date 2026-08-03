/**
 * Carries a Google re-authentication ticket from the callback page back to the
 * screen that asked for it.
 *
 * `sessionStorage`, never `localStorage`: it confirms one irreversible action and
 * has no business outliving the tab. The backend also expires it after 5 minutes
 * and binds it to the account it was minted for.
 */
const STORAGE_KEY = 'google_reauth_token'

export function setGoogleReauthToken(token: string): void {
  sessionStorage.setItem(STORAGE_KEY, token)
}

export function takeGoogleReauthToken(): string | null {
  const token = sessionStorage.getItem(STORAGE_KEY)
  sessionStorage.removeItem(STORAGE_KEY)

  return token
}

export function hasGoogleReauthToken(): boolean {
  return sessionStorage.getItem(STORAGE_KEY) !== null
}

export function clearGoogleReauthToken(): void {
  sessionStorage.removeItem(STORAGE_KEY)
}
