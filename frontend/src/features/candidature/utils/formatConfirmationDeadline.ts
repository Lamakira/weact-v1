/**
 * Formate l'échéance de confirmation d'une Face (ISO 8601, lot B3) en heure locale :
 * « jeudi 19 octobre à 08h51 ». Retourne '' si la valeur est absente ou invalide.
 */
export function formatConfirmationDeadline(iso: string | null | undefined): string {
  if (!iso) return ''
  const date = new Date(iso)
  if (isNaN(date.getTime())) return ''

  const day = new Intl.DateTimeFormat('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }).format(date)
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')

  return `${day} à ${hours}h${minutes}`
}
