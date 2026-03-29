import nationalityFr from 'i18n-nationality/langs/fr.json'

type SelectOption = {
  value: string
  label: string
}

const TERRITORY_CODES = [
  'AC', 'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX',
  'AZ', 'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR',
  'BS', 'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM',
  'CN', 'CO', 'CP', 'CR', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DG', 'DJ', 'DK', 'DM', 'DO',
  'DZ', 'EC', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB',
  'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW',
  'GY', 'HK', 'HM', 'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS',
  'IT', 'JE', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ',
  'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF',
  'MG', 'MH', 'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX',
  'MY', 'MZ', 'NA', 'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA',
  'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO',
  'RS', 'RU', 'RW', 'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN',
  'SO', 'SR', 'SS', 'ST', 'SV', 'SX', 'SY', 'SZ', 'TA', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK',
  'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US', 'UY', 'UZ', 'VA',
  'VC', 'VE', 'VG', 'VI', 'VN', 'VU', 'WF', 'WS', 'XK', 'YE', 'YT', 'ZA', 'ZM', 'ZW',
] as const

const regionDisplayNames = new Intl.DisplayNames(['fr-FR'], { type: 'region' })

function buildTerritoryOptions(): SelectOption[] {
  return TERRITORY_CODES
    .map((code) => {
      const label = regionDisplayNames.of(code) ?? code

      return {
        value: label,
        label,
      }
    })
    .sort((left, right) => left.label.localeCompare(right.label, 'fr', { sensitivity: 'base' }))
}

const feminineNationalityOverrides: Record<string, string> = {
  Afghan: 'Afghane',
  Allemand: 'Allemande',
  Andorran: 'Andorrane',
  Argentin: 'Argentine',
  Grec: 'Grecque',
  Letton: 'Lettone',
  Turc: 'Turque',
}

function toFeminineNationalityWord(word: string): string {
  if (feminineNationalityOverrides[word]) {
    return feminineNationalityOverrides[word]
  }

  if (word.endsWith('ien')) {
    return `${word.slice(0, -3)}ienne`
  }

  if (word.endsWith('éen')) {
    return `${word.slice(0, -3)}éenne`
  }

  if (word.endsWith('ain')) {
    return `${word.slice(0, -3)}aine`
  }

  if (word.endsWith('ois') || word.endsWith('ais')) {
    return `${word.slice(0, -1)}se`
  }

  if (word.endsWith('and')) {
    return `${word}e`
  }

  if (word.endsWith('on')) {
    return `${word}ne`
  }

  if (word.endsWith('an') || word.endsWith('in')) {
    return `${word}e`
  }

  if (word.endsWith('ol')) {
    return `${word}e`
  }

  if (word.endsWith('en')) {
    return `${word}ne`
  }

  if (word.endsWith('e') || word.endsWith('que')) {
    return word
  }

  return word
}

function toFeminineNationalityLabel(label: string): string {
  const match = label.match(/^(.*?)(\s*\(.*\))?$/)

  if (!match) {
    return label
  }

  const [, matchedMainLabel = label, matchedSuffix = ''] = match
  const mainLabel = matchedMainLabel.trim()
  const suffix = matchedSuffix
  const words = mainLabel.split(' ')

  if (words.length === 0) {
    return label
  }

  const lastWord = words[words.length - 1]

  if (!lastWord) {
    return label
  }

  words[words.length - 1] = toFeminineNationalityWord(lastWord)

  return `${words.join(' ')}${suffix}`
}

function buildNationalityOptions(): SelectOption[] {
  return TERRITORY_CODES
    .map((code) => {
      const masculineNationality = nationalityFr.nationalities[code as keyof typeof nationalityFr.nationalities]

      if (!masculineNationality) {
        return null
      }

      const label = toFeminineNationalityLabel(masculineNationality)

      return {
        value: label,
        label,
      }
    })
    .filter((option): option is SelectOption => option !== null)
    .sort((left, right) => left.label.localeCompare(right.label, 'fr', { sensitivity: 'base' }))
}

export const COUNTRY_OPTIONS = buildTerritoryOptions()
export const NATIONALITY_OPTIONS = buildNationalityOptions()

export const COUNTRY_OPTION_VALUES = new Set(COUNTRY_OPTIONS.map((option) => option.value))
export const NATIONALITY_OPTION_VALUES = new Set(NATIONALITY_OPTIONS.map((option) => option.value))
