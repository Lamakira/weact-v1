const categoryLabels: Record<string, string> = {
  acteur: 'Acteur',
  influenceur: 'Influenceur',
  createur: 'Créateur de contenu',
  mannequin: 'Mannequin',
  figurant: 'Figurant',
  modele_photo: 'Modèle Photo',
  egerie: 'Égérie',
}

const categoryColors: Record<string, string> = {
  acteur: 'bg-blue-100 text-blue-700',
  influenceur: 'bg-purple-100 text-purple-700',
  createur: 'bg-amber-100 text-amber-700',
  mannequin: 'bg-pink-100 text-pink-700',
  figurant: 'bg-gray-100 text-gray-700',
  modele_photo: 'bg-rose-100 text-rose-700',
  egerie: 'bg-indigo-100 text-indigo-700',
}

const nicheLabels: Record<string, string> = {
  beaute: 'Beauté',
  nourriture: 'Nourriture',
  decouverte: 'Découverte',
  mode: 'Mode',
}

export function getCategoryLabel(category: string | null): string {
  return category ? categoryLabels[category] ?? category : '—'
}

export function getCategoryColor(category: string | null): string {
  return category ? categoryColors[category] ?? 'bg-gray-100 text-gray-700' : 'bg-gray-100 text-gray-400'
}

export function getNicheLabel(niche: string | null): string {
  return niche ? nicheLabels[niche] ?? niche : '—'
}

/**
 * Get labels for an array of category values.
 */
export function getCategoryLabels(categories: Array<{ value: string; label: string }> | null): string {
  if (!categories || categories.length === 0) return '—'
  return categories.map((c) => c.label).join(', ')
}

/**
 * Get labels for an array of niche values.
 */
export function getNicheLabels(niches: Array<{ value: string; label: string }> | null): string {
  if (!niches || niches.length === 0) return '—'
  return niches.map((n) => n.label).join(', ')
}
