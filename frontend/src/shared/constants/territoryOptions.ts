import countries from './countries.json'
import nationalities from './nationalities.json'

type SelectOption = {
  value: string
  label: string
}

/**
 * Listes fermées (une seule nationalité, un seul pays par Face). Les valeurs vivent dans
 * countries.json / nationalities.json, copies exactes de backend/resources/data (parité
 * vérifiée par territoryOptions.spec.ts) : Intl.DisplayNames dépend de la version d'ICU,
 * le serveur ne peut pas les recalculer.
 */
function toOptions(values: readonly string[], first: string): SelectOption[] {
  // Bénin / Béninoise en tête (public principal), le reste dans l'ordre du fichier.
  const ordered = [first, ...values.filter((value) => value !== first)]

  return ordered.map((value) => ({ value, label: value }))
}

export const COUNTRY_OPTIONS = toOptions(countries, 'Bénin')
export const NATIONALITY_OPTIONS = toOptions(nationalities, 'Béninoise')

export const COUNTRY_OPTION_VALUES = new Set(COUNTRY_OPTIONS.map((option) => option.value))
export const NATIONALITY_OPTION_VALUES = new Set(NATIONALITY_OPTIONS.map((option) => option.value))
