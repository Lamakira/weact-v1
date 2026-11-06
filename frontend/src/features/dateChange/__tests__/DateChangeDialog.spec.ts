import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import DateChangeDialog from '../components/DateChangeDialog.vue'
import { dateChangeApi } from '../services/dateChangeApi'
import { minNewDateIso } from '../utils/dateChangeFormat'
import { addDaysIso } from '@/lib/dates'

vi.mock('../services/dateChangeApi', () => ({
  dateChangeApi: {
    proposeMission: vi.fn(),
    proposeBooking: vi.fn(),
    respondMission: vi.fn(),
    respondBooking: vi.fn(),
    withdrawBooking: vi.fn(),
  },
}))

// La liste déroulante (Reka) est testée ailleurs : un <select> natif suffit pour piloter le motif.
const FloatingSelectStub = {
  props: ['modelValue', 'options', 'label', 'id'],
  emits: ['update:modelValue'],
  template:
    '<select :id="id" data-testid="date-change-reason" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option value="">-</option><option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option></select>',
}

let wrapper: VueWrapper | null = null

function mountDialog(props: Record<string, unknown> = {}) {
  wrapper = mount(DateChangeDialog, {
    props: {
      isOpen: true,
      kind: 'mission',
      targetId: 'mission-uuid-1',
      currentDate: '2026-11-15',
      used: 0,
      max: 2,
      facesToConfirm: 3,
      ...props,
    },
    global: { stubs: { teleport: true, FloatingSelect: FloatingSelectStub } },
  })
  return wrapper
}

const validDate = () => addDaysIso(minNewDateIso(new Date()), 6)

async function fill(w: VueWrapper, date: string, reason: string) {
  await w.get('input#date-change-new-date').setValue(date)
  await w.get('[data-testid="date-change-reason"]').setValue(reason)
}

beforeEach(() => {
  vi.mocked(dateChangeApi.proposeMission).mockReset()
  vi.mocked(dateChangeApi.proposeBooking).mockReset()
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
})

describe('DateChangeDialog — mission', () => {
  it('affiche la date actuelle en lecture seule, le compteur et les conséquences', () => {
    const w = mountDialog()
    expect(w.get('[data-testid="date-change-current"]').text()).toBe('Dimanche 15 novembre 2026')
    expect(w.get('[data-testid="date-change-counter"]').text()).toBe('Changement 1 sur 2')
    expect(w.get('[data-testid="date-change-subtitle"]').text()).toBe('La nouvelle date s’applique tout de suite.')
    expect(w.get('[data-testid="date-change-effect-confirm"]').text()).toContain('3 Faces engagées')
    expect(w.get('[data-testid="date-change-effect-refund"]').text()).toBe(
      'Vous pourrez donner sa place à un autre candidat. Sinon, le budget de la place vous revient.',
    )
    expect(w.get('[data-testid="date-change-fees-warning"]').text()).toContain('Frais de service non remboursés')
    expect(w.get('[data-testid="date-change-submit"]').text()).toBe('Déplacer le tournage')
  })

  it('compteur : le deuxième changement', () => {
    const w = mountDialog({ used: 1 })
    expect(w.get('[data-testid="date-change-counter"]').text()).toBe('Changement 2 sur 2')
  })

  it('la borne min du champ date est le premier jour à 12 h de préavis', () => {
    const w = mountDialog()
    expect(w.get('input#date-change-new-date').attributes('min')).toBe(minNewDateIso(new Date()))
  })

  it('envoi bloqué sans date ni motif', async () => {
    const w = mountDialog()
    expect(w.get('[data-testid="date-change-submit"]').attributes('disabled')).toBeDefined()
    await w.get('input#date-change-new-date').setValue(validDate())
    expect(w.get('[data-testid="date-change-submit"]').attributes('disabled')).toBeDefined()
    await w.get('[data-testid="date-change-reason"]').setValue('meteo')
    expect(w.get('[data-testid="date-change-submit"]').attributes('disabled')).toBeUndefined()
  })

  it('refuse la même date et une date trop proche', async () => {
    const w = mountDialog()
    await fill(w, '2026-11-15', 'meteo')
    expect(w.get('[data-testid="date-change-new-date-error"]').exists()).toBe(true)
    expect(w.text()).toContain('Choisissez une date différente de la date actuelle.')
    expect(w.get('[data-testid="date-change-submit"]').attributes('disabled')).toBeDefined()

    await w.get('input#date-change-new-date').setValue('2020-01-01')
    expect(w.text()).toContain('le tournage doit commencer 12 h au moins après votre demande')
    expect(w.get('[data-testid="date-change-submit"]').attributes('disabled')).toBeDefined()
  })

  it('envoie la nouvelle date et le motif, puis émet la mission rafraîchie', async () => {
    const updated = { id: 'mission-uuid-1', date_tournage: validDate() }
    vi.mocked(dateChangeApi.proposeMission).mockResolvedValue({ data: updated, message: 'Date déplacée.' } as never)
    const w = mountDialog()
    const date = validDate()
    await fill(w, date, 'lieu_indisponible')

    await w.get('[data-testid="date-change-submit"]').trigger('click')
    await flushPromises()

    expect(dateChangeApi.proposeMission).toHaveBeenCalledWith('mission-uuid-1', {
      new_date: date,
      reason: 'lieu_indisponible',
    })
    expect(w.emitted('missionChanged')?.[0]).toEqual([updated, 'Date déplacée.'])
  })

  it('affiche l’erreur 422 du serveur dans la fenêtre', async () => {
    vi.mocked(dateChangeApi.proposeMission).mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: {
          error: { code: 'VALIDATION_ERROR', message: 'Les données fournies ne sont pas valides', details: { date: ['Le maximum de 2 changements de date est atteint.'] } },
        },
      },
    })
    const w = mountDialog()
    await fill(w, validDate(), 'meteo')
    await w.get('[data-testid="date-change-submit"]').trigger('click')
    await flushPromises()

    expect(w.get('[data-testid="date-change-error"]').text()).toBe('Le maximum de 2 changements de date est atteint.')
    expect(w.emitted('missionChanged')).toBeUndefined()
  })

  it('borne haute : max du champ et refus clair au-delà de max_date', async () => {
    const w = mountDialog({ maxDate: '2026-12-15' })
    expect(w.get('input#date-change-new-date').attributes('max')).toBe('2026-12-15')

    await fill(w, '2026-12-16', 'meteo')
    expect(w.get('[data-testid="date-change-new-date-error"]').text()).toBe(
      'La nouvelle date ne peut pas dépasser de plus de 30 jours la date actuelle.',
    )
    expect(w.get('[data-testid="date-change-submit"]').attributes('disabled')).toBeDefined()
  })

  it('mission : la limite est bornée par le début de la nouvelle date moins 1 h', async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-06T08:00:00Z'))
    try {
      const w = mountDialog()
      await w.get('input#date-change-new-date').setValue('2026-11-07')
      // minuit au Bénin le 7 = 23:00 UTC le 6 ; moins 1 h = 23:00 Bénin le 6
      expect(w.get('[data-testid="date-change-effect-confirm"]').text()).toContain('jusqu’au vendredi 6 novembre à 23:00')
    } finally {
      vi.useRealTimers()
    }
  })

  it('Retour émet cancel', async () => {
    const w = mountDialog()
    await w.get('[data-testid="date-change-back"]').trigger('click')
    expect(w.emitted('cancel')).toHaveLength(1)
  })
})

describe('DateChangeDialog — booking', () => {
  const bookingProps = { kind: 'booking', targetId: 'booking-uuid-1', otherPartyName: 'Awa D.', facesToConfirm: 0 }

  it('textes du parcours booking, sans avertissement de frais', () => {
    const w = mountDialog(bookingProps)
    expect(w.get('h3').text()).toBe('Proposer une autre date')
    expect(w.get('[data-testid="date-change-subtitle"]').text()).toBe('La date change seulement si Awa D. accepte.')
    expect(w.get('[data-testid="date-change-counter"]').text()).toBe('Proposition 1 sur 2')
    expect(w.text()).toContain('Sans accord de sa part, le tournage reste le dimanche 15 novembre.')
    expect(w.text()).toContain('Le montant du booking ne change pas.')
    expect(w.find('[data-testid="date-change-fees-warning"]').exists()).toBe(false)
    expect(w.get('[data-testid="date-change-submit"]').text()).toBe('Envoyer la proposition')
  })

  it('la limite affichée est bornée par le début de l’ancienne date', async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-06T08:00:00Z'))
    try {
      const w = mountDialog({ ...bookingProps, currentDate: '2026-11-08' })
      await w.get('input#date-change-new-date').setValue('2026-11-20')
      // maintenant + 48 h serait le 8 novembre à 09:00 ; l'ancienne date commence le 8 novembre à 00:00 (Bénin)
      expect(w.get('[data-testid="date-change-effect-confirm"]').text()).toContain('jusqu’au dimanche 8 novembre à 00:00')
    } finally {
      vi.useRealTimers()
    }
  })

  it('mission : la limite est maintenant + 48 h', async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-06T08:00:00Z'))
    try {
      const w = mountDialog()
      await w.get('input#date-change-new-date').setValue('2026-11-20')
      expect(w.get('[data-testid="date-change-effect-confirm"]').text()).toContain('jusqu’au dimanche 8 novembre à 09:00')
    } finally {
      vi.useRealTimers()
    }
  })

  it('envoie la proposition au booking et émet le booking mis à jour', async () => {
    const updated = { id: 'booking-uuid-1' }
    vi.mocked(dateChangeApi.proposeBooking).mockResolvedValue({ data: updated } as never)
    const w = mountDialog(bookingProps)
    const date = validDate()
    await fill(w, date, 'imprevu_personnel')
    await w.get('[data-testid="date-change-submit"]').trigger('click')
    await flushPromises()

    expect(dateChangeApi.proposeBooking).toHaveBeenCalledWith('booking-uuid-1', { new_date: date, reason: 'imprevu_personnel' })
    expect(dateChangeApi.proposeMission).not.toHaveBeenCalled()
    expect(w.emitted('bookingProposed')?.[0]?.[0]).toEqual(updated)
  })
})
