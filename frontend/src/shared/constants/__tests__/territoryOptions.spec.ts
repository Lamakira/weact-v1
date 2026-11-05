import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import countries from '../countries.json'
import nationalities from '../nationalities.json'
import {
  COUNTRY_OPTIONS,
  COUNTRY_OPTION_VALUES,
  NATIONALITY_OPTIONS,
  NATIONALITY_OPTION_VALUES,
} from '../territoryOptions'

// Les tests tournent depuis frontend/ : le dépôt est son parent.
function backendList(file: string): unknown {
  const path = resolve(process.cwd(), '..', 'backend', 'resources', 'data', file)

  return JSON.parse(readFileSync(path, 'utf8'))
}

describe('territoryOptions : parité frontend / backend', () => {
  it('les pays sont identiques au fichier du backend', () => {
    expect(countries).toEqual(backendList('countries.json'))
  })

  it('les nationalités sont identiques au fichier du backend', () => {
    expect(nationalities).toEqual(backendList('nationalities.json'))
  })
})

describe('territoryOptions : contenu', () => {
  it('place Bénin et Béninoise en tête, sans doublon', () => {
    expect(COUNTRY_OPTIONS[0]).toEqual({ value: 'Bénin', label: 'Bénin' })
    expect(NATIONALITY_OPTIONS[0]).toEqual({ value: 'Béninoise', label: 'Béninoise' })
    expect(COUNTRY_OPTIONS).toHaveLength(countries.length)
    expect(NATIONALITY_OPTIONS).toHaveLength(nationalities.length)
  })

  it('contient les valeurs de référence', () => {
    for (const pays of ['Bénin', 'Côte d’Ivoire', 'Congo-Kinshasa', 'Congo-Brazzaville', 'Togo']) {
      expect(COUNTRY_OPTION_VALUES.has(pays)).toBe(true)
    }
    for (const nationalite of [
      'Béninoise',
      'Togolaise',
      'Burkinabé',
      'Ivoirienne',
      'Congolaise (République du Congo)',
      'Congolaise (République démocratique du Congo)',
    ]) {
      expect(NATIONALITY_OPTION_VALUES.has(nationalite)).toBe(true)
    }
  })
})
