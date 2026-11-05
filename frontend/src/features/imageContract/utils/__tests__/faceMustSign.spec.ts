import { describe, it, expect } from 'vitest'
import { faceMustSignMission } from '../faceMustSign'
import type { ImageContract, MissionImageTerms } from '../../types'

const terms: MissionImageTerms = {
  finalite: 'Publicité',
  supports: [{ value: 'social', label: 'Réseaux sociaux' }],
  territoire: { value: 'benin', label: 'Bénin' },
  duree_annees: 1,
  producer_signed_at: '2026-11-04T10:00:00Z',
  producer_signed_name: 'Jean Producteur',
}

const contract = { id: 'ic-1', status: 'signed' } as ImageContract

describe('faceMustSignMission', () => {
  it('oui : conditions, candidature retenue, pas de contrat', () => {
    expect(faceMustSignMission({ image_terms: terms }, { status: 'accepted', image_contract: null })).toBe(true)
    expect(faceMustSignMission({ image_terms: terms }, { status: 'accepted' })).toBe(true)
  })

  it('non : mission sans conditions (null ou absentes)', () => {
    expect(faceMustSignMission({ image_terms: null }, { status: 'accepted' })).toBe(false)
    expect(faceMustSignMission({}, { status: 'accepted' })).toBe(false)
  })

  it('non : contrat déjà présent', () => {
    expect(faceMustSignMission({ image_terms: terms }, { status: 'accepted', image_contract: contract })).toBe(false)
  })

  it.each(['pending', 'confirmed', 'in_progress', 'completed', 'rejected', 'cancelled', 'expired'])(
    'non : candidature au statut %s',
    (status) => {
      expect(faceMustSignMission({ image_terms: terms }, { status })).toBe(false)
    },
  )

  it('non : mission ou candidature absente', () => {
    expect(faceMustSignMission(null, { status: 'accepted' })).toBe(false)
    expect(faceMustSignMission(undefined, { status: 'accepted' })).toBe(false)
    expect(faceMustSignMission({ image_terms: terms }, null)).toBe(false)
    expect(faceMustSignMission({ image_terms: terms }, undefined)).toBe(false)
  })
})
