import { describe, expect, it } from 'vitest'
import type { Booking } from '../../types'
import {
  bookingListSubtitle,
  buildBookingView,
  formatRemaining,
  type BookingRole,
} from '../bookingPresentation'

function makeBooking(overrides: Record<string, unknown> = {}): Booking {
  return {
    id: 'uuid-1',
    status: 'pending',
    type_contenu: 'Publicité',
    date_debut: '2026-10-23T08:00:00+00:00',
    accepted_at: null,
    montant_face_recoit: 85000,
    montant_total_producteur: 110000,
    face: { userable: { prenom: 'Adjoua', nom: 'Kouassi' } },
    producer: { userable: { display_name: 'Tropical Bénin' } },
    can_rate: false,
    ...overrides,
  } as unknown as Booking
}

function title(overrides: Record<string, unknown>, role: BookingRole): string {
  return buildBookingView(makeBooking(overrides), role).title
}

describe('buildBookingView — titres selon le statut et le rôle', () => {
  it.each([
    [{ status: 'pending' }, 'producer', 'Demande envoyée à Adjoua'],
    [{ status: 'pending' }, 'face', 'Nouvelle demande de Tropical Bénin'],
    [{ status: 'accepted', payment_deadline: '2026-10-20T14:00:00+01:00' }, 'producer', 'Adjoua a accepté : payez avant 14:00'],
    [{ status: 'accepted' }, 'face', 'Booking accepté : en attente du paiement'],
    [{ status: 'paid' }, 'producer', 'Booking confirmé avec Adjoua'],
    [{ status: 'paid' }, 'face', 'Booking confirmé avec Tropical Bénin'],
    [{ status: 'confirmed_by_face' }, 'producer', 'Booking confirmé avec Adjoua'],
    [{ status: 'confirmed_by_producer' }, 'face', 'Booking confirmé avec Tropical Bénin'],
    [{ status: 'completed' }, 'producer', 'Booking terminé avec Adjoua'],
    [{ status: 'completed' }, 'face', 'Booking terminé avec Tropical Bénin'],
    [{ status: 'refused' }, 'producer', 'Adjoua a refusé la demande'],
    [{ status: 'refused' }, 'face', 'Demande refusée'],
    [{ status: 'expired' }, 'producer', 'Demande expirée sans réponse de Adjoua'],
    [{ status: 'expired', accepted_at: '2026-10-19T10:00:00Z' }, 'producer', 'Demande expirée : paiement non effectué'],
    [{ status: 'expired', accepted_at: '2026-10-19T10:00:00Z' }, 'face', 'Demande expirée : paiement non reçu'],
    [{ status: 'cancelled_by_producer' }, 'producer', 'Booking annulé'],
    [{ status: 'cancelled_by_producer' }, 'face', 'Booking annulé par Tropical Bénin'],
    [{ status: 'cancelled_by_face' }, 'producer', 'Adjoua a annulé le booking'],
    [{ status: 'cancelled_by_face' }, 'face', 'Vous avez annulé le booking'],
    [{ status: 'no_show' }, 'producer', 'Absence de Adjoua signalée'],
    [{ status: 'no_show' }, 'face', 'Votre absence a été signalée'],
    [{ status: 'commission_paid', type_contenu: 'UGC' }, 'producer', 'Commission réglée : en attente de Adjoua'],
    [{ status: 'commission_paid', type_contenu: 'UGC' }, 'face', 'Nouvelle demande de Tropical Bénin'],
  ] as const)('%j vu par %s → « %s »', (overrides, role, expected) => {
    expect(title(overrides as Record<string, unknown>, role)).toBe(expected)
  })

  it('« payez avant » retombe sur une formule sans heure quand aucune échéance n’est fournie', () => {
    expect(title({ status: 'accepted' }, 'producer')).toBe('Adjoua a accepté : payez pour confirmer')
  })
})

describe('buildBookingView — pastille', () => {
  it('dit « Paiement sécurisé » pour un booking payé, aux deux rôles', () => {
    expect(buildBookingView(makeBooking({ status: 'paid' }), 'producer').pill).toEqual({ tone: 'ok', label: 'Paiement sécurisé' })
    expect(buildBookingView(makeBooking({ status: 'paid' }), 'face').pill).toEqual({ tone: 'ok', label: 'Paiement sécurisé' })
  })

  it('distingue qui doit agir sur un booking accepté', () => {
    expect(buildBookingView(makeBooking({ status: 'accepted' }), 'producer').pill.label).toBe('À payer')
    expect(buildBookingView(makeBooking({ status: 'accepted' }), 'face').pill.label).toBe('Paiement attendu')
  })
})

describe('buildBookingView — étapes', () => {
  it('cash : Demande, Acceptation, Paiement, Tournage, Terminé', () => {
    const steps = buildBookingView(makeBooking({ status: 'accepted' }), 'producer').steps
    expect(steps.labels).toEqual(['Demande', 'Acceptation', 'Paiement', 'Tournage', 'Terminé'])
    expect(steps.current).toBe(2)
    expect(steps.halted).toBe(false)
  })

  it('UGC : Demande, Commission, Acceptation, Livraison, Terminé', () => {
    const view = buildBookingView(makeBooking({ status: 'commission_paid', type_contenu: 'UGC' }), 'face')
    expect(view.steps.labels).toEqual(['Demande', 'Commission', 'Acceptation', 'Livraison', 'Terminé'])
    expect(view.steps.current).toBe(2)
  })

  it.each([
    ['pending', 1],
    ['accepted', 2],
    ['paid', 3],
    ['confirmed_by_face', 3],
    ['completed', 4],
  ])('cash %s → étape %i', (status, expected) => {
    expect(buildBookingView(makeBooking({ status }), 'face').steps.current).toBe(expected)
  })

  it('un parcours arrêté reste sur son étape et est signalé', () => {
    const refused = buildBookingView(makeBooking({ status: 'refused' }), 'face').steps
    expect(refused.halted).toBe(true)
    expect(refused.current).toBe(1)

    const expiredUnpaid = buildBookingView(makeBooking({ status: 'expired', accepted_at: '2026-10-19T10:00:00Z' }), 'face').steps
    expect(expiredUnpaid.halted).toBe(true)
    expect(expiredUnpaid.current).toBe(2)
  })
})

describe('buildBookingView — « Et ensuite ? »', () => {
  it('Face, demande en attente : à elle d’agir, avec l’échéance de réponse', () => {
    const next = buildBookingView(
      makeBooking({ status: 'pending', face_response_deadline: '2026-10-21T07:00:00+00:00' }),
      'face',
    ).next
    expect(next.headline).toMatch(/^Répondez avant le /)
    expect(next.who).toBe("À vous d'agir")
    expect(next.deadlineAt).toBe('2026-10-21T07:00:00+00:00')
  })

  it('Producer, demande en attente : la Face doit agir', () => {
    const next = buildBookingView(
      makeBooking({ status: 'pending', face_response_deadline: '2026-10-21T07:00:00+00:00' }),
      'producer',
    ).next
    expect(next.headline).toMatch(/^Adjoua a jusqu'au /)
    expect(next.who).toBe('La Face doit agir')
  })

  it('Producer, booking accepté : à lui de payer avant l’échéance de paiement', () => {
    const next = buildBookingView(
      makeBooking({ status: 'accepted', payment_deadline: '2026-10-20T12:00:00+00:00' }),
      'producer',
    ).next
    expect(next.who).toBe("À vous d'agir")
    expect(next.deadlineAt).toBe('2026-10-20T12:00:00+00:00')
  })

  it('Face, booking accepté : le Producteur doit agir', () => {
    const next = buildBookingView(makeBooking({ status: 'accepted', payment_deadline: '2026-10-20T12:00:00+00:00' }), 'face').next
    expect(next.who).toBe('Le Producteur doit agir')
  })

  it('UGC en attente : la commission du Producteur, avec son échéance', () => {
    const next = buildBookingView(
      makeBooking({ status: 'pending', type_contenu: 'UGC', ugc_commission_payment_deadline: '2026-10-22T10:00:00+00:00' }),
      'producer',
    ).next
    expect(next.headline).toContain('commission')
    expect(next.deadlineAt).toBe('2026-10-22T10:00:00+00:00')
  })

  it('payé : la date de clôture automatique alimente le décompte quand elle existe', () => {
    const next = buildBookingView(makeBooking({ status: 'paid', auto_completion_at: '2026-10-30T00:00:00+00:00' }), 'producer').next
    expect(next.deadlineAt).toBe('2026-10-30T00:00:00+00:00')
    expect(next.text).toContain('clôture est automatique')

    const noDate = buildBookingView(makeBooking({ status: 'paid' }), 'producer').next
    expect(noDate.deadlineAt).toBeNull()
    expect(noDate.text).not.toContain('clôture est automatique')
  })

  it('Face, booking payé : annonce le montant mis en séquestre pour elle', () => {
    const next = buildBookingView(makeBooking({ status: 'paid' }), 'face').next
    expect(next.text).toMatch(/85\s000 FCFA sont bloqués en séquestre/)
  })

  it('Face, double confirmation : celle qui doit confirmer à son tour', () => {
    expect(buildBookingView(makeBooking({ status: 'confirmed_by_producer' }), 'face').next.who).toBe("À vous d'agir")
    expect(buildBookingView(makeBooking({ status: 'confirmed_by_producer' }), 'producer').next.who).toBe('La Face doit agir')
    expect(buildBookingView(makeBooking({ status: 'confirmed_by_face' }), 'producer').next.who).toBe("À vous d'agir")
  })

  it('un booking clos n’a plus d’échéance', () => {
    for (const status of ['completed', 'refused', 'expired', 'cancelled_by_face', 'cancelled_by_producer', 'no_show']) {
      expect(buildBookingView(makeBooking({ status }), 'face').next.deadlineAt).toBeNull()
    }
  })
})

describe('formatRemaining', () => {
  const now = new Date('2026-10-20T10:00:00Z').getTime()

  it('formate jours + heures, heures + minutes et minutes', () => {
    expect(formatRemaining('2026-10-21T12:00:00Z', now)).toBe('Reste 1 j 2 h')
    expect(formatRemaining('2026-10-20T13:40:00Z', now)).toBe('Reste 3 h 40')
    expect(formatRemaining('2026-10-20T10:42:00Z', now)).toBe('Reste 42 min')
  })

  it('renvoie null une fois l’échéance passée', () => {
    expect(formatRemaining('2026-10-20T09:59:00Z', now)).toBeNull()
  })
})

describe('bookingListSubtitle', () => {
  it('ne dit plus « Demande de booking » partout', () => {
    expect(bookingListSubtitle(makeBooking({ status: 'pending' }), 'producer')).toBe('En attente de sa réponse')
    expect(bookingListSubtitle(makeBooking({ status: 'pending' }), 'face')).toBe('À vous de répondre')
    expect(bookingListSubtitle(makeBooking({ status: 'accepted' }), 'producer')).toBe('À payer')
    expect(bookingListSubtitle(makeBooking({ status: 'accepted' }), 'face')).toBe('En attente du paiement')
    expect(bookingListSubtitle(makeBooking({ status: 'paid' }), 'face')).toBe('Confirmé, paiement sécurisé')
    expect(bookingListSubtitle(makeBooking({ status: 'completed' }), 'face')).toBe('Terminé')
    expect(bookingListSubtitle(makeBooking({ status: 'expired' }), 'face')).toBe('Demande expirée')
  })
})
