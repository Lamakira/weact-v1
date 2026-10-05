/**
 * Browser binding of the Google round-trip (login-CSRF protection).
 *
 * A random nonce is generated before leaving for Google, sent with the redirect
 * request, kept in this tab's `sessionStorage`, and sent again with the one-shot
 * code on return: a code minted in another browser cannot be exchanged here.
 *
 * Deliberately NOT under the `weact.auth.` prefix: it must survive a clearAuth()
 * triggered by a stale token during the round-trip.
 */
const STORAGE_KEY = 'weact.oauth_nonce'

function toBase64Url(bytes: Uint8Array): string {
  let binary = ''
  bytes.forEach((byte) => {
    binary += String.fromCharCode(byte)
  })

  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

export function createGoogleOAuthNonce(): string {
  const bytes = new Uint8Array(32)
  crypto.getRandomValues(bytes)

  const nonce = toBase64Url(bytes)
  sessionStorage.setItem(STORAGE_KEY, nonce)

  return nonce
}

export function takeGoogleOAuthNonce(): string | null {
  const nonce = sessionStorage.getItem(STORAGE_KEY)
  sessionStorage.removeItem(STORAGE_KEY)

  return nonce
}
