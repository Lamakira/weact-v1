import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BookingDateChangeBlock from '../components/BookingDateChangeBlock.vue'
import type { BookingDateChangeLatest } from '../types'

const now = new Date('2026-11-06T08:00:00Z').getTime()

function makeLatest(overrides: Partial<BookingDateChangeLatest> = {}): BookingDateChangeLatest {
  return {
    old_date: '2026-11-15',
    new_date: '2026-11-20',
    reason_label: 'Lieu indisponible',
    status: 'pending',
    respond_by: '2026-11-08T07:30:00Z',
    proposed_by_me: false,
    can_respond: true,
    can_withdraw: false,
    conflict: false,
    ...overrides,
  }
}

function mountBlock(latest: BookingDateChangeLatest, extra: Record<string, unknown> = {}) {
  return mount(BookingDateChangeBlock, {
    props: { latest, isFace: true, otherPartyName: 'Awa D.', now, ...extra },
  })
}

describe('BookingDateChangeBlock — destinataire', () => {
  it('Face : deux boutons « J’accepte le … » et « Je garde le … », décompte et mention du silence', async () => {
    const w = mountBlock(makeLatest())

    expect(w.get('[data-testid="booking-date-change-title"]').text()).toBe('Le Producteur propose une autre date')
    expect(w.get('[data-testid="booking-date-change-compare"]').text()).toContain('Dim. 15 novembre')
    expect(w.get('[data-testid="booking-date-change-compare"]').text()).toContain('Ven. 20 novembre')
    expect(w.text()).toContain('Motif indiqué : lieu indisponible.')
    expect(w.get('[data-testid="booking-date-change-countdown"]').text()).toBe('Il vous reste 1 j 23 h')
    expect(w.text()).toContain('Sans réponse, le tournage reste le dimanche 15 novembre.')

    await w.get('[data-testid="booking-date-change-accept"]').trigger('click')
    await w.get('[data-testid="booking-date-change-refuse"]').trigger('click')
    expect(w.get('[data-testid="booking-date-change-accept"]').text()).toBe('J’accepte le 20 novembre')
    expect(w.get('[data-testid="booking-date-change-refuse"]').text()).toBe('Je garde le 15 novembre')
    expect(w.emitted('respond')).toEqual([['accept'], ['refuse']])
  })

  it('Producteur destinataire : la proposition vient de la Face', () => {
    const w = mountBlock(makeLatest(), { isFace: false })
    expect(w.get('[data-testid="booking-date-change-title"]').text()).toBe('La Face propose une autre date')
  })

  it('date déjà prise : « J’accepte » désactivé, « Je garde » reste actif', async () => {
    const w = mountBlock(makeLatest({ conflict: true }))
    expect(w.get('[data-testid="booking-date-change-conflict"]').text()).toContain('est déjà pris dans votre agenda')
    expect(w.get('[data-testid="booking-date-change-accept"]').attributes('disabled')).toBeDefined()
    expect(w.get('[data-testid="booking-date-change-refuse"]').attributes('disabled')).toBeUndefined()
  })

  it('délai écoulé : plus de boutons', () => {
    const w = mountBlock(makeLatest({ can_respond: false }))
    expect(w.find('[data-testid="booking-date-change-accept"]').exists()).toBe(false)
    expect(w.get('[data-testid="booking-date-change-late"]').exists()).toBe(true)
  })

  it('résultat acceptée : le tournage passe à la nouvelle date', () => {
    const w = mountBlock(makeLatest({ status: 'accepted', can_respond: false }))
    expect(w.get('[data-testid="booking-date-change-title"]').text()).toBe('Le tournage passe au vendredi 20 novembre')
    expect(w.text()).toContain('Le Producteur est prévenu. Vous recevrez un rappel la veille.')
  })

  it('résultat ancienne date gardée : texte du destinataire, pas d’annulation côté Face', () => {
    const w = mountBlock(makeLatest({ status: 'refused', can_respond: false }), { canCancel: true })
    expect(w.get('[data-testid="booking-date-change-title"]').text()).toBe('Le tournage reste le dimanche 15 novembre')
    expect(w.get('[data-testid="booking-date-change-text"]').text()).toContain('Votre booking continue comme prévu.')
    expect(w.find('[data-testid="booking-date-change-cancel"]').exists()).toBe(false)
  })
})

describe('BookingDateChangeBlock — auteur', () => {
  const mine = { proposed_by_me: true, can_respond: false, can_withdraw: true }

  it('attente : pas d’élision devant une consonne', () => {
    const w = mountBlock(makeLatest(mine), { isFace: false, otherPartyName: 'Koffi A.' })

    expect(w.get('[data-testid="booking-date-change-title"]').text()).toBe('En attente de la réponse de Koffi A.')
  })

  it('attente : comparaison, décompte « Il lui reste » et retrait de la proposition', async () => {
    const w = mountBlock(makeLatest(mine), { isFace: false })

    expect(w.get('[data-testid="booking-date-change-title"]').text()).toBe('En attente de la réponse d’Awa D.')
    expect(w.get('[data-testid="booking-date-change-countdown"]').text()).toBe('Il lui reste 1 j 23 h')
    expect(w.find('[data-testid="booking-date-change-accept"]').exists()).toBe(false)

    await w.get('[data-testid="booking-date-change-withdraw"]').trigger('click')
    expect(w.emitted('withdraw')).toHaveLength(1)
  })

  it('attente sans droit de retrait : pas de bouton', () => {
    const w = mountBlock(makeLatest({ ...mine, can_withdraw: false }), { isFace: false })
    expect(w.find('[data-testid="booking-date-change-withdraw"]').exists()).toBe(false)
  })

  it('résultat acceptée', () => {
    const w = mountBlock(makeLatest({ ...mine, status: 'accepted' }), { isFace: false })
    expect(w.get('[data-testid="booking-date-change-title"]').text()).toBe('Awa D. accepte le vendredi 20 novembre')
    expect(w.text()).toContain('La date du booking est mise à jour.')
  })

  it('ancienne date gardée côté Producteur payé : texte de la maquette et annulation', async () => {
    const w = mountBlock(makeLatest({ ...mine, status: 'refused' }), { isFace: false, canCancel: true, isPaid: true })

    expect(w.get('[data-testid="booking-date-change-title"]').text()).toBe('Le tournage reste le dimanche 15 novembre')
    expect(w.get('[data-testid="booking-date-change-text"]').text().replace(/\s+/g, ' ')).toBe(
      'Awa D. n’a pas accepté la nouvelle date. Si vous ne pouvez vraiment pas le 15 novembre, vous pouvez annuler ce booking : 90 % du montant vous sont remboursés.',
    )
    await w.get('[data-testid="booking-date-change-cancel"]').trigger('click')
    expect(w.emitted('cancelBooking')).toHaveLength(1)
  })

  it('ancienne date gardée côté Producteur non payé : pas de promesse de remboursement', () => {
    const w = mountBlock(makeLatest({ ...mine, status: 'expired' }), { isFace: false, canCancel: true, isPaid: false })
    expect(w.get('[data-testid="booking-date-change-text"]').text()).not.toContain('remboursés')
    expect(w.find('[data-testid="booking-date-change-cancel"]').exists()).toBe(true)
  })

  it('ancienne date gardée : pas d’annulation si le booking ne le permet pas', () => {
    const w = mountBlock(makeLatest({ ...mine, status: 'refused' }), { isFace: false, canCancel: false, isPaid: true })
    expect(w.find('[data-testid="booking-date-change-cancel"]').exists()).toBe(false)
    expect(w.get('[data-testid="booking-date-change-text"]').text()).not.toContain('annuler')
  })

  it('proposition retirée : rien à afficher', () => {
    const w = mountBlock(makeLatest({ ...mine, status: 'withdrawn' }), { isFace: false })
    expect(w.find('[data-testid="booking-date-change"]').exists()).toBe(false)
  })
})
