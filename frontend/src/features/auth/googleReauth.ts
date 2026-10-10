/**
 * Carries a Google re-authentication ticket from the callback page back to the
 * screen that asked for it.
 *
 * `sessionStorage`, never `localStorage`: it confirms one irreversible action and
 * has no business outliving the tab. The backend also expires it after 5 minutes
 * and binds it to the account it was minted for; the stored user id enforces the same
 * binding client-side. Both keys live under the
 * `weact.auth.` prefix, so logout / account switch purges them.
 *
 * The ticket is stamped with the purpose it was requested for: a screen only
 * consumes a ticket of its own purpose, so a "set password" confirmation can
 * never open the account-deletion dialog (and vice versa).
 */
export type GoogleReauthPurpose = 'delete_account' | 'set_password'

const TICKET_KEY = 'weact.auth.google_reauth'
const PURPOSE_KEY = 'weact.auth.google_reauth_purpose'

const PURPOSES: readonly string[] = ['delete_account', 'set_password']

function isPurpose(value: unknown): value is GoogleReauthPurpose {
  return typeof value === 'string' && PURPOSES.includes(value)
}

/** Remembered by the button before leaving for Google, read by the callback page. */
export function setPendingReauthPurpose(purpose: GoogleReauthPurpose): void {
  sessionStorage.setItem(PURPOSE_KEY, purpose)
}

export function takePendingReauthPurpose(): GoogleReauthPurpose | null {
  const purpose = sessionStorage.getItem(PURPOSE_KEY)
  sessionStorage.removeItem(PURPOSE_KEY)

  return isPurpose(purpose) ? purpose : null
}

export function setGoogleReauthTicket(
  token: string,
  purpose: GoogleReauthPurpose,
  userId: number
): void {
  sessionStorage.setItem(TICKET_KEY, JSON.stringify({ token, purpose, userId }))
}

/**
 * Returns and removes the token only when both the stored purpose and the stored
 * user id match. A ticket minted for another account is removed and never shown.
 */
export function takeGoogleReauthTicket(
  purpose: GoogleReauthPurpose,
  userId: number | null | undefined
): string | null {
  const stored = sessionStorage.getItem(TICKET_KEY)

  if (stored === null) return null

  try {
    const parsed = JSON.parse(stored) as { token?: unknown; purpose?: unknown; userId?: unknown }

    if (typeof parsed.userId !== 'number' || parsed.userId !== userId) {
      sessionStorage.removeItem(TICKET_KEY)

      return null
    }

    if (parsed.purpose !== purpose || typeof parsed.token !== 'string') return null

    sessionStorage.removeItem(TICKET_KEY)

    return parsed.token
  } catch {
    sessionStorage.removeItem(TICKET_KEY)

    return null
  }
}
