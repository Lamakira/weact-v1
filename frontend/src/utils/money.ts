/**
 * Formatage unique des montants et des nombres affichés.
 *
 * Format monétaire : « 12 500 F CFA » — milliers séparés par une espace fine
 * insécable (U+202F), espaces insécables (U+00A0) autour de « F CFA » pour
 * qu'un montant ne se coupe jamais en fin de ligne.
 */

const NNBSP = ' '
const NBSP = ' '

/** Entier avec séparateur de milliers français (« 3 941 »). */
export function formatNumber(value: number | null | undefined): string {
  const n = Math.round(Number(value ?? 0))
  const safe = Number.isFinite(n) ? n : 0
  const digits = String(Math.abs(safe)).replace(/\B(?=(\d{3})+(?!\d))/g, NNBSP)
  return (safe < 0 ? '-' : '') + digits
}

/** Montant en francs CFA : « 12 500 F CFA ». */
export function formatMoney(amount: number | null | undefined): string {
  return `${formatNumber(amount)}${NBSP}F${NBSP}CFA`
}

/** Tarif par période : « 25 000 F CFA / jour ». */
export function formatMoneyPer(amount: number | null | undefined, period: string): string {
  return `${formatMoney(amount)}${NBSP}/${NBSP}${period}`
}
