import { describe, it, expect } from 'vitest'
import { deriveFaceHubState, formatRemaining, formatShortDate } from '../faceMissionHub'
import type { Mission, MissionCandidature } from '@/features/mission/types'

function mission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 'm1',
    titre: 'Spot TV',
    date_tournage: '2026-10-23',
    budget: 100000,
    lieu: 'Cotonou',
    ...overrides,
  } as Mission
}

function cand(overrides: Partial<MissionCandidature> = {}): MissionCandidature {
  return {
    id: 'c1',
    mission_id: 'm1',
    face_id: 'f1',
    status: 'pending',
    status_label: 'En attente',
    message_motivation: null,
    created_at: '2026-10-01T08:00:00Z',
    updated_at: '2026-10-01T08:00:00Z',
    ...overrides,
  }
}

const std = { isUgc: false, producerName: 'Tropical Bénin' }
const ugc = { isUgc: true, producerName: 'Tropical Bénin' }

describe('deriveFaceHubState — mission standard', () => {
  it('pending : candidature envoyée, étape Sélection, retrait possible', () => {
    const s = deriveFaceHubState(mission(), cand(), std)
    expect(s.steps).toEqual(['Candidature', 'Sélection', 'Confirmation', 'Tournage', 'Payée'])
    expect(s.currentStep).toBe(1)
    expect(s.pill).toEqual({ tone: 'info', label: 'Candidature envoyée' })
    expect(s.action.key).toBe('cancel')
    expect(s.stopped).toBe(false)
  })

  it('selection_payment_pending : ambre, « Le producteur finalise sa sélection »', () => {
    const s = deriveFaceHubState(mission(), cand({ display_status: 'selection_payment_pending' }), std)
    expect(s.pill).toEqual({ tone: 'warn', label: 'Sélection en cours de paiement' })
    expect(s.next.headline).toBe('Le producteur finalise sa sélection')
    expect(s.next.who).toBe('Le producteur doit agir')
  })

  it('accepted : confirmation directe avec échéance et compte à rebours', () => {
    const s = deriveFaceHubState(
      mission(),
      cand({ status: 'accepted', confirmation_deadline: '2026-10-22T08:51:00+00:00', montant_face_recoit: 85000 }),
      std,
    )
    expect(s.currentStep).toBe(2)
    expect(s.pill).toEqual({ tone: 'warn', label: 'À confirmer' })
    expect(s.action.key).toBe('confirm')
    expect(s.action.label).toBe('Confirmer ma participation')
    expect(s.action.sub).toContain('Avant le')
    expect(s.next.deadline).toBe('2026-10-22T08:51:00+00:00')
    expect(s.next.text).toContain('85 000 FCFA')
  })

  it('accepted sans échéance : pas de sous-libellé ni de compte à rebours', () => {
    const s = deriveFaceHubState(mission(), cand({ status: 'accepted' }), std)
    expect(s.action.sub).toBe('')
    expect(s.next.deadline).toBeNull()
  })

  it('confirmed avec conversation : vert, Rendez-vous le …, chat ouvert', () => {
    const s = deriveFaceHubState(mission(), cand({ status: 'confirmed', conversation_id: 'conv-1' }), std)
    expect(s.currentStep).toBe(3)
    expect(s.pill).toEqual({ tone: 'ok', label: 'Confirmée' })
    expect(s.next.headline).toMatch(/^Rendez-vous le /)
    expect(s.action).toMatchObject({ key: 'chat', sub: 'Producteur : Tropical Bénin' })
    expect(s.chatOpen).toBe(true)
  })

  it('in_progress sans conversation : le chat reste verrouillé, action de repli', () => {
    const s = deriveFaceHubState(mission(), cand({ status: 'in_progress' }), std)
    expect(s.pill.tone).toBe('ok')
    expect(s.chatOpen).toBe(false)
    expect(s.action.key).toBe('browse')
  })

  it('completed : tout est accompli, payée', () => {
    const s = deriveFaceHubState(mission(), cand({ status: 'completed', conversation_id: 'c', montant_face_recoit: 90000 }), std)
    expect(s.currentStep).toBe(s.steps.length)
    expect(s.pill).toEqual({ tone: 'ok', label: 'Payée' })
    expect(s.next.text).toContain('90 000 FCFA')
  })

  it('rejected : « Non retenue » grise, jamais verte, parcours interrompu', () => {
    const s = deriveFaceHubState(mission(), cand({ status: 'rejected', status_label: 'Refusée' }), std)
    expect(s.pill).toEqual({ tone: 'mute', label: 'Non retenue' })
    expect(s.stopped).toBe(true)
    expect(s.action.key).toBe('browse')
  })

  it('expired sans réponse du producteur : libellé du backend, étape Sélection', () => {
    const s = deriveFaceHubState(
      mission(),
      cand({ status: 'expired', display_status: 'expired', display_status_label: 'Sans réponse du producteur' }),
      std,
    )
    expect(s.pill).toEqual({ tone: 'mute', label: 'Sans réponse du producteur' })
    expect(s.currentStep).toBe(1)
    expect(s.next.headline).toBe('Le producteur n’a pas répondu')
  })

  it('expired après sélection : libellé « Expirée », étape Confirmation', () => {
    const s = deriveFaceHubState(
      mission(),
      cand({ status: 'expired', display_status: 'expired', display_status_label: 'Expirée' }),
      std,
    )
    expect(s.pill).toEqual({ tone: 'mute', label: 'Expirée' })
    expect(s.currentStep).toBe(2)
    expect(s.stopped).toBe(true)
  })

  it('cancelled : grise, interrompue', () => {
    const s = deriveFaceHubState(mission(), cand({ status: 'cancelled' }), std)
    expect(s.pill).toEqual({ tone: 'mute', label: 'Annulée' })
    expect(s.stopped).toBe(true)
  })

  it('mission_closed : grise, pas d’annulation proposée', () => {
    const s = deriveFaceHubState(mission(), cand({ display_status: 'mission_closed' }), std)
    expect(s.pill.label).toBe('Mission clôturée')
    expect(s.action.key).toBe('browse')
  })
})

describe('deriveFaceHubState — mission UGC', () => {
  it('utilise les étapes UGC', () => {
    expect(deriveFaceHubState(mission(), cand(), ugc).steps).toEqual([
      'Candidature',
      'Acceptée',
      'Reconfirmée',
      'Livrables',
      'Terminée',
    ])
  })

  it('accepted : reconfirmation avec reconfirm_deadline', () => {
    const s = deriveFaceHubState(
      mission(),
      cand({ status: 'accepted', reconfirm_deadline: '2026-10-22T08:51:00+00:00', confirmation_deadline: null }),
      ugc,
    )
    expect(s.action.key).toBe('reconfirm')
    expect(s.next.deadline).toBe('2026-10-22T08:51:00+00:00')
    expect(s.currentStep).toBe(2)
  })

  it('confirmed sans expédition vs avec expédition', () => {
    const waiting = deriveFaceHubState(mission(), cand({ status: 'confirmed' }), ugc)
    expect(waiting.next.headline).toBe('Le producteur prépare l’envoi')
    const shipped = deriveFaceHubState(
      mission(),
      cand({ status: 'confirmed', shipment: { id: 's1' } as unknown as MissionCandidature['shipment'] }),
      ugc,
    )
    expect(shipped.next.headline).toBe('Suivez votre livraison et vos vidéos')
  })
})

describe('formatRemaining', () => {
  const now = new Date('2026-10-20T10:00:00Z')

  it('jours + heures', () => {
    expect(formatRemaining('2026-10-22T02:30:00Z', now)).toBe('Reste 1 j 16 h')
  })
  it('heures + minutes', () => {
    expect(formatRemaining('2026-10-20T13:40:00Z', now)).toBe('Reste 3 h 40')
  })
  it('minutes seules', () => {
    expect(formatRemaining('2026-10-20T10:12:00Z', now)).toBe('Reste 12 min')
  })
  it('échéance passée', () => {
    expect(formatRemaining('2026-10-20T09:00:00Z', now)).toBe('Échéance dépassée')
  })
})

describe('formatShortDate', () => {
  it('formate sans décalage de fuseau', () => {
    expect(formatShortDate('2026-10-23')).toMatch(/23/)
    expect(formatShortDate('2026-10-23')).toMatch(/oct/)
  })
  it('retourne une chaîne vide pour une valeur absente', () => {
    expect(formatShortDate(null)).toBe('')
  })
})
