import { describe, expect, it } from 'vitest'
import type { Mission } from '../../types'
import { buildRepublishValues } from '../republishPrefill'

const standard = {
  id: 'mission-uuid',
  titre: 'Spot TV jus de bissap',
  description: 'Brief détaillé',
  profil_recherche: 'Face polyvalente',
  budget: 110000,
  nombre_faces_voulu: 3,
  type_mission: 'publicite',
  type_mission_autre: null,
  genre_voulu: 'femme',
  lieu: 'Cotonou, Haie Vive',
  duree: '1 jour',
  date_tournage: '2026-10-23T09:00:00+00:00',
  date_limite_candidature: '2026-10-19T12:00:00+00:00',
  status: 'closed',
  has_paid_payment: true,
  candidatures_count: 3,
  summary: { step: 'attendance' },
  type_compensation: null,
  nom_produit: null,
  valeur_produit: null,
  nombre_videos: null,
  montant_remuneration: null,
} as unknown as Mission

describe('buildRepublishValues (lot C9)', () => {
  it('reprend le brief d\'une mission standard et laisse les dates vides', () => {
    expect(buildRepublishValues(standard)).toEqual({
      titre: 'Spot TV jus de bissap',
      description: 'Brief détaillé',
      profil_recherche: 'Face polyvalente',
      nombre_faces_voulu: 3,
      type_mission: 'publicite',
      type_mission_autre: undefined,
      genre_voulu: 'femme',
      budget: 110000,
      lieu: 'Cotonou, Haie Vive',
      duree: '1 jour',
      date_tournage: '',
      date_limite_candidature: '',
    })
  })

  it('ne copie ni paiement, ni candidatures, ni statut, ni identifiant', () => {
    const values = buildRepublishValues(standard) as Record<string, unknown>

    for (const forbidden of ['id', 'status', 'has_paid_payment', 'candidatures_count', 'summary', 'product_photos']) {
      expect(values).not.toHaveProperty(forbidden)
    }
    expect(values.date_tournage).toBe('')
    expect(values.date_limite_candidature).toBe('')
  })

  it('reprend la dotation d\'une mission UGC sans budget ni champs de tournage', () => {
    const ugc = {
      ...standard,
      type_mission: 'ugc',
      type_compensation: 'hybrid',
      nom_produit: 'Sneakers',
      valeur_produit: 45000,
      nombre_videos: 2,
      montant_remuneration: 15000,
      date_tournage: null,
      lieu: '',
      duree: '',
    } as unknown as Mission

    const values = buildRepublishValues(ugc)

    expect(values).toMatchObject({
      type_mission: 'ugc',
      type_compensation: 'hybrid',
      nom_produit: 'Sneakers',
      valeur_produit: 45000,
      nombre_videos: 2,
      montant_remuneration: 15000,
      date_tournage: '',
      date_limite_candidature: '',
    })
    expect(values).not.toHaveProperty('budget')
    expect(values).not.toHaveProperty('lieu')
    expect(values).not.toHaveProperty('duree')
  })

  it('reprend le libellé « autre » du type de mission', () => {
    expect(buildRepublishValues({ ...standard, type_mission: 'autre', type_mission_autre: 'Défilé' } as unknown as Mission))
      .toMatchObject({ type_mission: 'autre', type_mission_autre: 'Défilé' })
  })
})
