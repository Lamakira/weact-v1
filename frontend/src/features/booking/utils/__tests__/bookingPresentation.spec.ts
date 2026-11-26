import { afterEach, describe, expect, it, vi } from 'vitest'
import { BookingStatus, type Booking } from '../../types'
import {
  bookingAmountLabels,
  bookingListAction,
  bookingListSubtitle,
  buildBookingView,
  formatDeadlineLong,
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
    ['completed', 5],
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

describe('buildBookingView — « Et ensuite ? » d’un booking payé selon la date du tournage', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('avant le tournage : rendez-vous, prochaine étape le tournage', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-20T10:00:00Z'))
    const next = buildBookingView(makeBooking({ status: 'paid' }), 'producer').next
    expect(next.headline).toMatch(/^Rendez-vous /)
    expect(next.who).toBe('Prochaine étape : le tournage')
  })

  it('tournage commencé ou passé : plus de « rendez-vous », il faut confirmer la réalisation', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-24T10:00:00Z'))
    const producer = buildBookingView(makeBooking({ status: 'paid' }), 'producer').next
    expect(producer.headline).toMatch(/^Tournage du .*\u00a0: confirmez qu'il a eu lieu$/)
    expect(producer.who).toBe("À vous d'agir")
    const face = buildBookingView(makeBooking({ status: 'paid' }), 'face').next
    expect(face.text).toMatch(/^Confirmez la réalisation pour recevoir /)
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
    // Avant le tournage (fixé le 23 oct. 2026) : après, le texte invite à confirmer la réalisation.
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-20T10:00:00Z'))
    try {
      const next = buildBookingView(makeBooking({ status: 'paid' }), 'face').next
      expect(next.text).toMatch(/85\s000\sF\sCFA sont bloqués en séquestre/)
    } finally {
      vi.useRealTimers()
    }
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

describe('formatDeadlineLong', () => {
  it('formate une échéance « ven. 23 oct. à 14h00 » dans le fuseau métier (UTC+1)', () => {
    expect(formatDeadlineLong('2026-10-23T13:00:00Z')).toBe('ven. 23 oct. à 14h00')
    expect(formatDeadlineLong('2026-10-23T07:05:00+00:00')).toBe('ven. 23 oct. à 08h05')
  })
})

describe('bookingListAction (lot D7)', () => {
  const now = new Date('2026-10-20T10:00:00Z').getTime()
  const action = (overrides: Record<string, unknown>, role: BookingRole) =>
    bookingListAction(makeBooking(overrides), role, now)

  it('requires_my_action=false (serveur) : aucune action ni échéance, même sur un UGC « à payer » en apparence', () => {
    const elapsed = { status: 'pending', type_contenu: 'UGC', ugc_commission_payment_deadline: '2026-10-18T10:00:00Z' }
    expect(action({ ...elapsed, requires_my_action: false }, 'producer')).toBeNull()
    expect(action({ ...elapsed, requires_my_action: true }, 'producer')).toEqual({
      label: 'Payer la commission',
      deadline: 'Payer la commission avant le dim. 18 oct. à 11h00',
    })
  })

  it('Face : répondre à une demande cash, avec son échéance', () => {
    expect(action({ status: 'pending', can_accept: true, face_response_deadline: '2026-10-23T13:00:00Z' }, 'face')).toEqual({
      label: 'Répondre',
      deadline: 'Répondre avant le ven. 23 oct. à 14h00',
    })
  })

  it('Face : pas d’action quand la policy refuse (délai écoulé) ou pour un UGC dont la commission n’est pas réglée', () => {
    expect(action({ status: 'pending', can_accept: false }, 'face')).toBeNull()
    expect(action({ status: 'pending', type_contenu: 'UGC', can_accept: false }, 'face')).toBeNull()
  })

  it('Face : répondre à une demande UGC dont la commission est réglée (sans échéance exposée)', () => {
    expect(action({ status: 'commission_paid', type_contenu: 'UGC', can_accept: true }, 'face')).toEqual({
      label: 'Répondre',
      deadline: null,
    })
    expect(action({ status: 'commission_paid', type_contenu: 'UGC', can_accept: true }, 'producer')).toBeNull()
  })

  it('Producer : payer un booking accepté (échéance de paiement) ou la commission UGC', () => {
    expect(action({ status: 'accepted', can_pay: true, payment_deadline: '2026-10-21T10:00:00Z' }, 'producer')).toEqual({
      label: 'Payer',
      deadline: 'Payer avant le mer. 21 oct. à 11h00',
    })
    expect(action({ status: 'accepted', can_pay: false }, 'producer')).toBeNull()
    expect(
      action({ status: 'pending', type_contenu: 'UGC', ugc_commission_payment_deadline: '2026-10-22T09:30:00Z' }, 'producer'),
    ).toEqual({ label: 'Payer la commission', deadline: 'Payer la commission avant le jeu. 22 oct. à 10h30' })
    expect(action({ status: 'accepted', can_pay: true }, 'face')).toBeNull()
  })

  it('confirmer la fin : booking payé une fois le tournage commencé, ou déjà confirmé par l’autre partie', () => {
    const started = '2026-10-19T00:00:00Z'
    const future = '2026-10-25T00:00:00Z'
    expect(action({ status: 'paid', date_debut: started }, 'face')?.label).toBe('Confirmer la fin')
    expect(action({ status: 'paid', date_debut: started }, 'producer')?.label).toBe('Confirmer la fin')
    expect(action({ status: 'paid', date_debut: future }, 'face')).toBeNull()
    expect(action({ status: 'confirmed_by_face' }, 'producer')?.label).toBe('Confirmer la fin')
    expect(action({ status: 'confirmed_by_face' }, 'face')).toBeNull()
    expect(action({ status: 'confirmed_by_producer' }, 'face')?.label).toBe('Confirmer la fin')
    expect(action({ status: 'confirmed_by_producer' }, 'producer')).toBeNull()
  })

  it('tunnel UGC : réception et vidéo côté Face, expédition et validation côté Producer', () => {
    const ugc = (shipment: unknown) => ({ status: 'accepted', type_contenu: 'UGC', shipment })

    expect(action(ugc({ tunnel_status: 'shipped' }), 'face')?.label).toBe('Confirmer la réception')
    expect(action(ugc({ tunnel_status: 'received' }), 'face')?.label).toBe('Envoyer la vidéo')
    expect(action(ugc({ tunnel_status: 'avis_pending' }), 'face')?.label).toBe('Envoyer la vidéo')
    expect(action(ugc({ tunnel_status: 'unboxing_in_review' }), 'face')).toBeNull()
    expect(action(ugc(undefined), 'face')).toBeNull()

    expect(action(ugc(undefined), 'producer')?.label).toBe("Confirmer l'expédition")
    expect(action(ugc({ tunnel_status: 'unboxing_in_review' }), 'producer')?.label).toBe('Valider la vidéo')
    expect(action(ugc({ tunnel_status: 'avis_in_review' }), 'producer')?.label).toBe('Valider la vidéo')
    expect(action(ugc({ tunnel_status: 'shipped' }), 'producer')).toBeNull()
  })

  it('Face : contester une absence ou une annulation tardive dans le délai', () => {
    const open = { settlement_due_at: '2026-10-21T10:00:00Z' }
    expect(action({ status: 'no_show', ...open }, 'face')).toEqual({
      label: 'Contester',
      deadline: 'Contester avant le mer. 21 oct. à 11h00',
    })
    expect(action({ status: 'cancelled_by_producer', ...open }, 'face')?.label).toBe('Contester')
    expect(action({ status: 'no_show', ...open }, 'producer')).toBeNull()
    expect(action({ status: 'no_show', settlement_due_at: '2026-10-20T09:00:00Z' }, 'face')).toBeNull()
    expect(action({ status: 'no_show', ...open, disputed_at: '2026-10-20T08:00:00Z' }, 'face')).toBeNull()
    expect(action({ status: 'no_show', settlement_due_at: null }, 'face')).toBeNull()
  })

  it('aucune action pour les statuts clos', () => {
    for (const status of ['completed', 'refused', 'expired', 'cancelled_by_face']) {
      expect(action({ status }, 'face')).toBeNull()
      expect(action({ status }, 'producer')).toBeNull()
    }
  })
})

describe('bookingAmountLabels : libellés du montant selon le statut et le rôle', () => {
  type Tone = 'due' | 'paid' | 'planned'

  // Un cas par statut : un statut oublié ici fait échouer le test d'exhaustivité.
  const PRODUCER: Record<string, [string, string, Tone]> = {
    pending: ['À payer', 'Total à payer', 'due'],
    accepted: ['À payer', 'Total à payer', 'due'],
    commission_paid: ['À payer', 'Total à payer', 'due'],
    paid: ['Votre paiement', 'Total payé', 'paid'],
    in_progress: ['Votre paiement', 'Total payé', 'paid'],
    confirmed_by_face: ['Votre paiement', 'Total payé', 'paid'],
    confirmed_by_producer: ['Votre paiement', 'Total payé', 'paid'],
    completed: ['Votre paiement', 'Total payé', 'paid'],
    no_show: ['Votre paiement', 'Total payé', 'paid'],
    refused: ['Montant prévu', 'Non payé', 'planned'],
    expired: ['Montant prévu', 'Non payé', 'planned'],
    cancelled_by_producer: ['Montant prévu', 'Non payé', 'planned'],
    cancelled_by_face: ['Montant prévu', 'Non payé', 'planned'],
  }

  const FACE: Record<string, [string, Tone]> = {
    pending: ['Vous recevrez', 'due'],
    accepted: ['Vous recevrez', 'due'],
    commission_paid: ['Vous recevrez', 'due'],
    paid: ['Vous recevrez', 'due'],
    in_progress: ['Vous recevrez', 'due'],
    confirmed_by_face: ['Vous recevrez', 'due'],
    confirmed_by_producer: ['Vous recevrez', 'due'],
    completed: ['Vous avez reçu', 'paid'],
    no_show: ['Montant prévu', 'planned'],
    refused: ['Montant prévu', 'planned'],
    expired: ['Montant prévu', 'planned'],
    cancelled_by_producer: ['Montant prévu', 'planned'],
    cancelled_by_face: ['Montant prévu', 'planned'],
  }

  const labels = (overrides: Record<string, unknown>, role: BookingRole) =>
    bookingAmountLabels(makeBooking(overrides), role)

  it('les tables couvrent exactement tous les statuts connus', () => {
    const all = Object.values(BookingStatus).sort()
    expect(Object.keys(PRODUCER).sort()).toEqual(all)
    expect(Object.keys(FACE).sort()).toEqual(all)
  })

  it.each(Object.entries(PRODUCER))('Producteur, statut %s, sans was_paid', (status, [title, total, tone]) => {
    expect(labels({ status }, 'producer')).toEqual({ title, total, tone })
    expect(labels({ status, was_paid: false }, 'producer')).toEqual({ title, total, tone })
  })

  it.each(Object.keys(PRODUCER))('Producteur, statut %s, was_paid vrai : payé', (status) => {
    expect(labels({ status, was_paid: true }, 'producer')).toEqual({
      title: 'Votre paiement',
      total: 'Total payé',
      tone: 'paid',
    })
  })

  it.each(Object.keys(PRODUCER))('Producteur, statut %s, règlement en attente sans was_paid : payé', (status) => {
    expect(labels({ status, settlement_due_at: '2026-10-20T09:00:00Z' }, 'producer')).toEqual({
      title: 'Votre paiement',
      total: 'Total payé',
      tone: 'paid',
    })
  })

  it.each(['refused', 'expired', 'cancelled_by_producer', 'cancelled_by_face'])(
    'Producteur, statut clos %s, paiement tenté mais non confirmé : « Total prévu », jamais « Non payé »',
    (status) => {
      expect(labels({ status, fedapay_transaction_id: 4242 }, 'producer')).toEqual({
        title: 'Montant prévu',
        total: 'Total prévu',
        tone: 'planned',
      })
    },
  )

  it('Producteur, statut en cours de paiement avec transaction FedaPay : reste « À payer »', () => {
    expect(labels({ status: 'accepted', fedapay_transaction_id: 4242 }, 'producer').tone).toBe('due')
  })

  it.each(Object.entries(FACE))('Face, statut %s, sans litige', (status, [total, tone]) => {
    expect(labels({ status }, 'face')).toEqual({ title: 'Votre rémunération', total, tone })
    expect(labels({ status, was_paid: true }, 'face')).toEqual({ title: 'Votre rémunération', total, tone })
  })

  it.each(['refused', 'expired', 'cancelled_by_producer', 'cancelled_by_face', 'no_show'])(
    'Face, statut clos %s, litige tranché en sa faveur : « Vous avez reçu »',
    (status) => {
      expect(
        labels({ status, dispute_resolved_at: '2026-10-25T10:00:00Z', dispute_outcome: 'favor_face' }, 'face'),
      ).toEqual({ title: 'Votre rémunération', total: 'Vous avez reçu', tone: 'paid' })
    },
  )

  it.each(['cancelled_by_producer', 'no_show'])(
    'Face, statut clos %s, litige tranché en faveur du Producteur : « Montant prévu »',
    (status) => {
      expect(
        labels({ status, dispute_resolved_at: '2026-10-25T10:00:00Z', dispute_outcome: 'favor_producer' }, 'face'),
      ).toEqual({ title: 'Votre rémunération', total: 'Montant prévu', tone: 'planned' })
    },
  )

  it('Face, issue favor_face sans date de résolution : « Montant prévu »', () => {
    expect(labels({ status: 'no_show', dispute_resolved_at: null, dispute_outcome: 'favor_face' }, 'face').total).toBe(
      'Montant prévu',
    )
  })

  it('Face, litige favor_face sur un booking non clos : inchangé', () => {
    expect(
      labels({ status: 'paid', dispute_resolved_at: '2026-10-25T10:00:00Z', dispute_outcome: 'favor_face' }, 'face')
        .total,
    ).toBe('Vous recevrez')
  })
})
