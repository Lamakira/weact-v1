/**
 * Délai de réponse habituel d'une Face, arrondi pour l'affichage Producteur
 * (« moins d'1 h », « sous 3 h », « sous 1 jour », « plus d'un jour »).
 */
export function formatTypicalResponse(minutes: number): string {
  if (minutes < 60) return "moins d'1 h"
  if (minutes <= 1380) return `sous ${Math.ceil(minutes / 60)} h`
  if (minutes <= 1440) return 'sous 1 jour'
  return "plus d'un jour"
}
