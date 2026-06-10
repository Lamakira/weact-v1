/**
 * Shared UGC commission helpers (front mirror of `backend/config/ugc.php`
 * + `App\Services\Ugc\UgcCommissionService::compute`).
 *
 * These constants/helpers are display-only: the persisted commission is ALWAYS
 * recomputed server-side. The front never sends `commission_ugc`.
 */

export const UGC_COMMISSION_RATE = 0.1
export const UGC_COMMISSION_FLOOR = 2500
export const UGC_PRODUCT_ONLY_VIDEO_COUNT = 2

export type UgcCompensationType = 'product' | 'hybrid'

/** Commission WeAct (live preview ; le serveur reste autoritatif). */
export function computeUgcCommission(productValue: number): number {
  return Math.max(UGC_COMMISSION_FLOOR, Math.round((productValue || 0) * UGC_COMMISSION_RATE))
}

/** Kinds de la primitive StatusPill (UX-DR3) — états du tunnel booking UGC. */
export type StatusPillKind =
  | 'pending'
  | 'paid'
  | 'accepted'
  | 'shipped'
  | 'received'
  | 'delivered'
  | 'completed'
  | 'overdue'
  | 'suspended'
