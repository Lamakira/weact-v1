import { describe, expect, it } from 'vitest'
import type { Booking } from '../../types'
import {
  bookingListAmount,
  bookingListStatusLabel,
  bookingUgcFacts,
  type BookingRole,
} from '../bookingPresentation'

/** Lot F4 : libellés de statut, montant et faits UGC des listes de bookings. */
function makeBooking(overrides: Record<string, unknown> = {}): Booking {
  return {
    id: 'uuid-1',
    status: 'pending',
    status_label: 'En attente',
    type_contenu: 'Publicité',
    date_debut: '2026-10-23T08:00:00+00:00',
    accepted_at: null,
    montant_face_recoit: 85000,
    montant_total_producteur: 110000,
    ...overrides,
  } as unknown as Booking
}

describe('bookingListStatusLabel — statut du point de vue de la personne qui lit', () => {
  const label = (status: string, role: BookingRole, extra: Record<string, unknown> = {}) =>
    bookingListStatusLabel(makeBooking({ status, ...extra }), role)

  it.each([
    ['confirmed_by_producer', 'producer', 'Confirmé par vous'],
    ['confirmed_by_producer', 'face', 'À confirmer par vous'],
    ['confirmed_by_face', 'face', 'Confirmé par vous'],
    ['confirmed_by_face', 'producer', 'À confirmer par vous'],
    ['refused', 'face', 'Refusé par vous'],
    ['refused', 'producer', 'Refusé par la Face'],
    ['cancelled_by_producer', 'producer', 'Annulé par vous'],
    ['cancelled_by_producer', 'face', 'Annulé par le Producteur'],
    ['cancelled_by_face', 'face', 'Annulé par vous'],
    ['cancelled_by_face', 'producer', 'Annulé par la Face'],
    ['pending', 'producer', 'En attente de réponse'],
    ['pending', 'face', 'À répondre par vous'],
    ['commission_paid', 'face', 'À répondre par vous'],
    ['commission_paid', 'producer', 'Commission réglée'],
    ['accepted', 'producer', 'À payer'],
    ['accepted', 'face', 'Paiement attendu'],
    ['paid', 'face', 'Paiement sécurisé'],
    ['in_progress', 'face', 'En cours'],
    ['completed', 'face', 'Terminé'],
    ['expired', 'face', 'Expiré'],
    ['no_show', 'producer', 'Absence signalée'],
  ] as const)('%s vu par %s : « %s »', (status, role, expected) => {
    expect(label(status, role)).toBe(expected)
  })

  it('une demande UGC en attente : le Producteur règle la commission, la Face attend', () => {
    expect(label('pending', 'producer', { type_contenu: 'UGC' })).toBe('Commission à régler')
    expect(label('pending', 'face', { type_contenu: 'UGC' })).toBe('En attente de la commission')
    expect(label('accepted', 'face', { type_contenu: 'UGC' })).toBe('Livraison en cours')
  })
})

describe('bookingListAmount — « Produit seul »', () => {
  const ugc = { type_contenu: 'UGC' }
  const digits = (text: string) => text.replace(/\s/g, '')

  it('UGC produit seul côté Face : « Produit seul » et non « 0 F CFA »', () => {
    const amount = bookingListAmount(makeBooking({ ...ugc, montant_face_recoit: 0 }), 'face')
    expect(amount).toEqual({ text: 'Produit seul', caption: 'sans argent' })
  })

  it('UGC avec argent : montant formaté, légende « produit + argent »', () => {
    const amount = bookingListAmount(makeBooking({ ...ugc, montant_face_recoit: 95000 }), 'face')
    expect(digits(amount.text)).toBe('95000FCFA')
    expect(amount.caption).toBe('produit + argent')
  })

  it('UGC produit seul côté Producteur : la commission payée reste un montant', () => {
    const amount = bookingListAmount(makeBooking({ ...ugc, montant_face_recoit: 0, montant_total_producteur: 2500 }), 'producer')
    expect(digits(amount.text)).toBe('2500FCFA')
    expect(amount.caption).toBe('vous payez')
  })

  it('booking cash : montant de la Face / du Producteur, jamais « Produit seul »', () => {
    expect(digits(bookingListAmount(makeBooking({ montant_face_recoit: 0 }), 'face').text)).toBe('0FCFA')
    expect(bookingListAmount(makeBooking(), 'face').caption).toBe('vous recevez')
  })
})

describe('bookingUgcFacts', () => {
  it('produit et vidéos d’un booking UGC, sans segment vide', () => {
    expect(bookingUgcFacts(makeBooking({ type_contenu: 'UGC', nom_produit: 'Piment 2', nombre_videos: 2 }))).toEqual({
      product: 'Produit : Piment 2',
      videos: '2 vidéos',
    })
    expect(bookingUgcFacts(makeBooking({ type_contenu: 'UGC', nom_produit: null, nombre_videos: 1 }))).toEqual({
      product: null,
      videos: '1 vidéo',
    })
  })

  it('rien pour un booking cash', () => {
    expect(bookingUgcFacts(makeBooking())).toEqual({ product: null, videos: null })
  })
})
