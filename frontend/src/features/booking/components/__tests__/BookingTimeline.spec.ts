import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BookingTimeline from '../BookingTimeline.vue'
import { BookingStatus, type BookingStatusType } from '@/features/booking/types'

function stepsOf(status: BookingStatusType): Array<[string, string]> {
  const wrapper = mount(BookingTimeline, { props: { status } })
  return wrapper.findAll('[data-testid="timeline-step"]').map((el) => [
    el.text(),
    el.attributes('data-state') ?? '',
  ])
}

const C = 'completed'
const U = 'current'
const F = 'future'

const CASH_LABELS = [
  'Demande envoyée',
  'Acceptation',
  'Paiement',
  'Confirmation Face',
  'Confirmation Producteur',
  'Terminé',
]

function expectCash(status: BookingStatusType, states: string[]): void {
  expect(stepsOf(status)).toEqual(CASH_LABELS.map((label, i) => [label, states[i]]))
}

describe('BookingTimeline — statuts positifs', () => {
  it('pending : demande envoyée faite, acceptation attendue', () => {
    expectCash(BookingStatus.PENDING, [C, U, F, F, F, F])
  })

  it('accepted : acceptation faite, paiement attendu', () => {
    expectCash(BookingStatus.ACCEPTED, [C, C, U, F, F, F])
  })

  it('paid : paiement fait (coche), les deux confirmations sont attendues', () => {
    expectCash(BookingStatus.PAID, [C, C, C, U, U, F])
  })

  it('commission_paid (UGC) : rendu identique à paid', () => {
    expectCash(BookingStatus.COMMISSION_PAID, [C, C, C, U, U, F])
  })

  it('in_progress : rendu identique à paid', () => {
    expectCash(BookingStatus.IN_PROGRESS, [C, C, C, U, U, F])
  })

  it('confirmed_by_face : Face faite, Producteur attendu', () => {
    expectCash(BookingStatus.CONFIRMED_BY_FACE, [C, C, C, C, U, F])
  })

  it('confirmed_by_producer : Producteur fait, Face attendue (la Face n’a pas confirmé)', () => {
    expectCash(BookingStatus.CONFIRMED_BY_PRODUCER, [C, C, C, U, C, F])
  })

  it('completed : tout est terminé', () => {
    expectCash(BookingStatus.COMPLETED, [C, C, C, C, C, C])
  })
})

describe('BookingTimeline — statuts négatifs (dernière étape rouge)', () => {
  it.each([
    [BookingStatus.REFUSED, 'Refusée'],
    [BookingStatus.EXPIRED, 'Expirée'],
    [BookingStatus.CANCELLED_BY_FACE, 'Annulée par la Face'],
    [BookingStatus.CANCELLED_BY_PRODUCER, 'Annulée par le Producteur'],
  ] as const)('%s : demande envoyée puis étape rouge « %s »', (status, label) => {
    expect(stepsOf(status)).toEqual([
      ['Demande envoyée', C],
      [label, 'failed'],
    ])
  })

  it('no_show : étapes atteintes jusqu’au paiement puis « Absence signalée » rouge', () => {
    expect(stepsOf(BookingStatus.NO_SHOW)).toEqual([
      ['Demande envoyée', C],
      ['Acceptation', C],
      ['Paiement', C],
      ['Absence signalée', 'failed'],
    ])
  })

  it('l’étape finale rouge réutilise le style rouge existant', () => {
    const wrapper = mount(BookingTimeline, { props: { status: BookingStatus.REFUSED } })
    const html = wrapper.findAll('[data-testid="timeline-step"]').at(-1)!.html()
    expect(html).toContain('bg-red-500')
    expect(html).toContain('text-red-700')
  })
})
