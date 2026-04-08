import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CancellationDialog from '../CancellationDialog.vue'
import { BookingStatus, type Booking } from '../../types'

function makeBooking(): Booking {
  return {
    id: 1,
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
  }
}

function mountDialog() {
  return mount(CancellationDialog, {
    props: {
      booking: makeBooking(),
      isOpen: true,
      isCancelling: false,
      isFace: false,
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
    const wrapper = mount(CancellationDialog, {
      props: {
        booking: makeBooking(),
        isOpen: true,
        isCancelling: false,
        isFace: true,
      },
      global: {
        stubs: {
          Teleport: true,
        },
      },
    })

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
})
