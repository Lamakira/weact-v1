import type { GoogleIntent } from './types'

/**
 * Carries a not-yet-created Google account from the callback page to the
 * finalisation screen.
 *
 * Held in `sessionStorage`, never `localStorage`: it must die with the tab. It is
 * not a credential — the account does not exist yet — but it is a one-shot ticket
 * to create one, so it has no business outliving the flow.
 */
export interface PendingGoogleRegistration {
  pending_token: string
  email: string
  prenom: string
  nom: string
  intent: GoogleIntent
  redirect: string | null
}

// Under the `weact.auth.` prefix: purged on logout / account switch.
const STORAGE_KEY = 'weact.auth.google_pending_registration'

export function setPendingGoogleRegistration(value: PendingGoogleRegistration): void {
  sessionStorage.setItem(STORAGE_KEY, JSON.stringify(value))
}

export function getPendingGoogleRegistration(): PendingGoogleRegistration | null {
  const stored = sessionStorage.getItem(STORAGE_KEY)

  if (stored === null) return null

  try {
    const parsed = JSON.parse(stored) as PendingGoogleRegistration

    return parsed.pending_token ? parsed : null
  } catch {
    return null
  }
}

export function clearPendingGoogleRegistration(): void {
  sessionStorage.removeItem(STORAGE_KEY)
}
