import { describe, expect, it } from 'vitest'
import type { FaceCandidature } from '../../types'
import { candidatureFaceStatusLabel, candidatureFacts, candidatureNextStep } from '../candidaturePresentation'

/** Lot F4 : faits en libellé / valeur et statut vu par la Face d'une carte de candidature. */
function makeCandidature(overrides: Partial<FaceCandidature> = {}, mission: Partial<FaceCandidature['mission']> = {}): FaceCandidature {
  return {
    id: 'cand-1',
    status: 'confirmed',
    status_label: 'Confirmée',
    message_motivation: null,
    created_at: '2026-06-10T08:00:00+00:00',
    mission: { id: 'm-1', titre: 'Mission', date_tournage: '2026-12-05', lieu: 'Abomey-Calavi', budget: 100000, ...mission },
    producer: { id: 'p-1', display_name: 'Abakar Mahamat', type: 'agency', profile_photo_url: null, profile_photo_thumbnail_url: null },
    conversation_id: null,
    ...overrides,
  }
}

const ugcMission = { type_compensation: 'product', date_tournage: null, lieu: null, budget: 0, nom_produit: 'crème Jolyderm', nombre_videos: 2, montant_remuneration: null }

describe('candidatureFacts', () => {
  it('mission UGC produit seul : Producteur, Type, Vous recevez, Prochaine étape, sans champ vide', () => {
    const facts = candidatureFacts(makeCandidature({ status: 'confirmed' }, ugcMission))

    expect(facts.map((f) => [f.label, f.value])).toEqual([
      ['Producteur', 'Abakar Mahamat'],
      ['Type', 'UGC · 2 vidéos'],
      ['Vous recevez', 'Produit : crème Jolyderm'],
      ['Prochaine étape', 'Expédition du produit'],
    ])
    expect(facts.every((f) => f.value.trim() !== '')).toBe(true)
  })

  it('mission UGC hybride : le produit et le montant sont réunis dans « Vous recevez »', () => {
    const facts = candidatureFacts(makeCandidature({}, { ...ugcMission, type_compensation: 'hybrid', montant_remuneration: 25000 }))
    const receives = facts.find((f) => f.key === 'receives')!.value

    expect(receives.startsWith('Produit : crème Jolyderm + 25')).toBe(true)
    expect(receives).toContain('F')
  })

  it('mission UGC sans produit ni montant connus : la ligne « Vous recevez » est omise, pas vide', () => {
    const facts = candidatureFacts(makeCandidature({}, { ...ugcMission, nom_produit: null, nombre_videos: null }))

    expect(facts.find((f) => f.key === 'receives')).toBeUndefined()
    expect(facts.find((f) => f.key === 'type')!.value).toBe('UGC')
  })

  it('mission standard : Producteur, Tournage, Lieu, Rémunération', () => {
    const facts = candidatureFacts(makeCandidature())

    expect(facts.map((f) => f.label)).toEqual(['Producteur', 'Tournage', 'Lieu', 'Rémunération'])
    expect(facts.find((f) => f.key === 'pay')!.value.replace(/\s/g, '')).toBe('100000FCFA')
  })
})

describe('candidatureNextStep', () => {
  it('suit le tunnel UGC selon l’expédition', () => {
    const step = (tunnel_status?: string) =>
      candidatureNextStep(makeCandidature({ shipment: tunnel_status ? ({ tunnel_status } as never) : undefined }, ugcMission))

    expect(step()).toBe('Expédition du produit')
    expect(step('shipped')).toBe('Réception du produit')
    expect(step('received')).toBe('Envoi de vos vidéos')
    expect(step('avis_in_review')).toBe('Validation par le Producteur')
    expect(step('completed')).toBeNull()
  })

  it('aucune étape pour une candidature terminée, refusée, annulée ou expirée', () => {
    for (const status of ['completed', 'rejected', 'cancelled', 'expired'] as const) {
      expect(candidatureNextStep(makeCandidature({ status }, ugcMission))).toBeNull()
    }
  })
})

describe('candidatureFaceStatusLabel', () => {
  it('« À confirmer par vous » quand le Producteur l’a retenue, « Non retenue » sinon', () => {
    expect(candidatureFaceStatusLabel(makeCandidature({ status: 'accepted', status_label: 'Acceptée' }))).toBe('À confirmer par vous')
    expect(candidatureFaceStatusLabel(makeCandidature({ status: 'rejected', status_label: 'Refusée' }))).toBe('Non retenue')
  })

  it('garde le libellé du serveur pour les autres statuts (mission clôturée, sans réponse…)', () => {
    expect(
      candidatureFaceStatusLabel(
        makeCandidature({ status: 'pending', display_status: 'mission_closed', display_status_label: 'Mission clôturée' }),
      ),
    ).toBe('Mission clôturée')
    expect(candidatureFaceStatusLabel(makeCandidature({ status: 'expired', status_label: 'Expirée', display_status_label: 'Sans réponse du producteur' }))).toBe(
      'Sans réponse du producteur',
    )
  })
})
