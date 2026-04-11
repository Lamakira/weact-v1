import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CancellationDialog from '../CancellationDialog.vue'
import { BookingStatus, type Booking } from '../../types'

function makeBooking(overrides: Partial<Booking> = {}): Booking {
  return {
    id: 'booking-uuid-1',
    realtime_channel_key: 1,
    face_id: 1,
    producer_id: 2,
    status: BookingStatus.ACCEPTED,
    status_label: 'Acceptée',
    date_debut: new Date().toISOString(),
    date_fin: new Date().toISOString(),
    duree_heures: 4,
    type_contenu: 'Shooting photo',
    message: null,
    tarif_base: 50000,
    montant_total_producteur: 55000,
    montant_face_recoit: 45000,
    cancellation_reason: null,
    custom_cancellation_reason: null,
    fedapay_transaction_id: null,
    payment_mode: null,
    accepted_at: new Date().toISOString(),
    face: undefined,
    producer: undefined,
    can_accept: false,
    can_refuse: false,
    can_pay: false,
    can_rate: false,
    my_rating: null,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
    ...overrides,
  }
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)
}

function mountDialog({
  bookingOverrides = {},
  isFace = false,
}: {
  bookingOverrides?: Partial<Booking>
  isFace?: boolean
} = {}) {
  return mount(CancellationDialog, {
    props: {
      booking: makeBooking(bookingOverrides),
      isOpen: true,
      isCancelling: false,
      isFace,
    },
    global: {
      stubs: {
        Teleport: true,
      },
    },
  })
}

describe('CancellationDialog', () => {
  it('does not show the removed price disagreement reason', () => {
    const wrapper = mountDialog()

    expect(wrapper.text()).not.toContain('Désaccord sur le prix')
  })

  it('shows the acceptance expired reason', () => {
    const wrapper = mountDialog()

    expect(wrapper.text()).toContain("Durée d'acceptation dépassée")
  })

  it('does not show the acceptance expired reason for Face users', () => {
    const wrapper = mountDialog({ isFace: true })

    expect(wrapper.text()).not.toContain("Durée d'acceptation dépassée")
  })

  it('shows a textarea when other reason is selected', async () => {
    const wrapper = mountDialog()

    await wrapper.get('[data-testid="cancellation-reason-select"]').setValue('other')

    expect(wrapper.find('[data-testid="custom-cancellation-reason-textarea"]').exists()).toBe(true)
  })

  it('disables confirm when other reason is selected but textarea is empty', async () => {
    const wrapper = mountDialog()

    await wrapper.get('[data-testid="cancellation-reason-select"]').setValue('other')

    expect(wrapper.get('[data-testid="confirm-cancellation-button"]').attributes('disabled')).toBeDefined()
  })

  it('emits the trimmed custom reason when confirmation is valid', async () => {
    const wrapper = mountDialog()

    await wrapper.get('[data-testid="cancellation-reason-select"]').setValue('other')
    await wrapper.get('[data-testid="custom-cancellation-reason-textarea"]').setValue('  Client indisponible  ')
    await wrapper.get('[data-testid="confirm-cancellation-button"]').trigger('click')

    expect(wrapper.emitted('confirm')).toEqual([
      [
        {
          reason: 'other',
          customReason: 'Client indisponible',
        },
      ],
    ])
  })

  it('shows producer paid cancellation costs only for producers', () => {
    const wrapper = mountDialog({
      bookingOverrides: { status: BookingStatus.PAID },
      isFace: false,
    })

    expect(wrapper.text()).toContain('Conséquences financières')
    expect(wrapper.text()).toContain('Montant payé')
    expect(wrapper.text()).toContain(formatCurrency(55000))
    expect(wrapper.text()).toContain('10% retenus par WEACT')
    expect(wrapper.text()).toContain(formatCurrency(5500))
    expect(wrapper.text()).toContain('Montant remboursé')
    expect(wrapper.text()).toContain(formatCurrency(49500))
    expect(wrapper.text()).not.toContain('Montant que vous auriez perçu')
    expect(wrapper.text()).not.toContain('Votre note moyenne sera pénalisée de -1 étoile')
  })

  it('shows face paid cancellation costs from the face point of view only', () => {
    const wrapper = mountDialog({
      bookingOverrides: { status: BookingStatus.PAID },
      isFace: true,
    })

    expect(wrapper.text()).toContain("Conséquences de l'annulation")
    expect(wrapper.text()).toContain('Montant que vous auriez perçu')
    expect(wrapper.text()).toContain(formatCurrency(45000))
    expect(wrapper.text()).toContain('Votre note moyenne sera pénalisée de -1 étoile')
    expect(wrapper.text()).not.toContain('Montant payé')
    expect(wrapper.text()).not.toContain('10% retenus par WEACT')
    expect(wrapper.text()).not.toContain('Montant remboursé')
  })

  it('shows only the rating penalty for face unpaid cancellations', () => {
    const wrapper = mountDialog({
      bookingOverrides: { status: BookingStatus.ACCEPTED },
      isFace: true,
    })

    expect(wrapper.text()).toContain("Conséquences de l'annulation")
    expect(wrapper.text()).toContain('Votre note moyenne sera pénalisée de -1 étoile')
    expect(wrapper.text()).not.toContain('Montant que vous auriez perçu')
    expect(wrapper.text()).not.toContain('Montant payé')
    expect(wrapper.text()).not.toContain('10% retenus par WEACT')
    expect(wrapper.text()).not.toContain('Montant remboursé')
    expect(wrapper.text()).not.toContain('Le booking sera annulé immédiatement, sans transaction financière.')
  })

  it('keeps the producer unpaid cancellation copy unchanged', () => {
    const wrapper = mountDialog({
      bookingOverrides: { status: BookingStatus.ACCEPTED },
      isFace: false,
    })

    expect(wrapper.text()).toContain('Le booking sera annulé immédiatement, sans transaction financière.')
    expect(wrapper.text()).not.toContain("Conséquences de l'annulation")
    expect(wrapper.text()).not.toContain('Montant que vous auriez perçu')
    expect(wrapper.text()).not.toContain('Votre note moyenne sera pénalisée de -1 étoile')
  })
})
