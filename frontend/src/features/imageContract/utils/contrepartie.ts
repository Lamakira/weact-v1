import { formatMoney } from '@/utils/money'

/**
 * Textes de la contrepartie dans la fenêtre de signature du contrat d'image.
 * Source unique : ces mots sont les mêmes pour le Producteur, la Face et le PDF
 * (le test contrepartie.spec.ts les compare à la fixture partagée avec le backend).
 */
export interface Contrepartie {
  kind: 'cash' | 'ugc_hybrid' | 'ugc_product'
  cachet?: number | null
  nom_produit?: string | null
  valeur_produit?: number | null
}

/** Lignes de contrepartie : le cachet brut, avant commission WeAct, et le produit remis. */
export function contrepartieLines(
  c: Contrepartie,
  { parFace }: { parFace?: boolean } = {},
): string[] {
  const hasCachet = c.cachet !== null && c.cachet !== undefined
  const cachetLine = (): string =>
    `Cachet : ${formatMoney(c.cachet)}${parFace ? ' par Face' : ''}, avant commission WeAct`

  if (c.kind === 'cash') {
    return [hasCachet ? cachetLine() : 'Cachet selon le tarif de la Face, avant commission WeAct']
  }

  const nom = (c.nom_produit ?? '').trim() || 'Produit offert'
  const produit = `Produit remis : ${nom} (valeur ${formatMoney(c.valeur_produit ?? 0)})`
  if (c.kind === 'ugc_hybrid' && hasCachet && Number(c.cachet) > 0) {
    return [produit, cachetLine()]
  }
  return [produit]
}

/** Net perçu par la Face, ou null quand il n'y en a pas (produit seul, montant inconnu ou nul). */
export function netLine(net: number | null | undefined): string | null {
  if (net === null || net === undefined || Number(net) === 0) return null
  return `Vous recevez ${formatMoney(net)}`
}
