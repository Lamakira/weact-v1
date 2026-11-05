import type { ImageDuree, ImageSupport, ImageTerritoire } from './types'

export const SUPPORT_OPTIONS: { value: ImageSupport; label: string }[] = [
  { value: 'social', label: 'Réseaux sociaux' },
  { value: 'web', label: 'Site web' },
  { value: 'tv', label: 'Télévision' },
  { value: 'print', label: 'Affichage' },
  { value: 'cinema', label: 'Cinéma' },
  { value: 'all', label: 'Tous supports' },
]

export const TERRITOIRE_OPTIONS: { value: ImageTerritoire; label: string }[] = [
  { value: 'benin', label: 'Bénin' },
  { value: 'west_africa', label: "Afrique de l'Ouest" },
  { value: 'world', label: 'Monde entier' },
]

export const DUREE_OPTIONS: { value: ImageDuree; label: string }[] = [
  { value: 1, label: '1 an' },
  { value: 3, label: '3 ans' },
  { value: 5, label: '5 ans' },
  { value: 10, label: '10 ans' },
]

export const FINALITE_MAX_LENGTH = 200

export function supportLabel(value: ImageSupport): string {
  return SUPPORT_OPTIONS.find((option) => option.value === value)?.label ?? value
}

export function territoireLabel(value: ImageTerritoire): string {
  return TERRITOIRE_OPTIONS.find((option) => option.value === value)?.label ?? value
}

export function dureeLabel(years: number): string {
  return years > 1 ? `${years} ans` : `${years} an`
}

/** Finalité proposée d'après le type de contenu ; vide si le type est trop vague (« Autre »). */
const FINALITE_BY_CONTENT_TYPE: Record<string, string> = {
  'Publicité': 'Publicité',
  'Film': 'Film',
  'Clip musical': 'Clip musical',
  'Court-métrage': 'Court-métrage',
  'Shooting photo': 'Shooting photo',
  'Contenu réseaux sociaux': 'Contenu pour les réseaux sociaux',
  'UGC': 'Vidéos de présentation du produit (UGC)',
}

export function defaultFinalite(contentType: string): string {
  return FINALITE_BY_CONTENT_TYPE[contentType] ?? ''
}
