/** Texte d'un badge de navigation : plafonné à « {max}+ » quand `max` est défini. */
export function formatBadge(count: number, max?: number): string {
  return max !== undefined && count > max ? `${max}+` : String(count)
}
