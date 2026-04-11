import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import BookingCard from '../BookingCard.vue'
import type { Booking } from '@/features/booking/types'

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: {
      userable_type: 'Face',
    },
  }),
}))

function makeBooking(overrides: Partial<Booking> = {}): Booking {
  return {
    id: 'booking-uuid-1',
    realtime_channel_key: 1,
    face_id: 2,
    producer_id: 3,
    status: 'pending',
    status_label: 'En attente',
    date_debut: '2026-04-10T08:00:00Z',
    date_fin: '2026-04-10T12:00:00Z',
    duree_heures: 4,
    type_contenu: 'Publicité',
    lieu: null,
    message: null,
    tarif_base: 50000,
    montant_total_producteur: 55000,
    montant_face_recoit: 45000,
    cancellation_reason: null,
    custom_cancellation_reason: null,
    fedapay_transaction_id: null,
    payment_mode: null,
    accepted_at: null,
    face: {
      id: 2,
      email: 'face@example.com',
      userable_type: 'Face',
      userable_id: 20,
      userable: {
        id: 20,
        nom: 'Doe',
        prenom: 'Jane',
        username: 'jane',
        profile_photo_url: null,
        thumbnail_url: null,
        average_rating: null,
        ratings_count: 0,
      },
    },
    producer: {
      id: 3,
      email: 'producer@example.com',
      userable_type: 'Producer',
      userable_id: 30,
      userable: {
        id: 30,
        display_name: 'Studio Cotonou',
        profile_photo_url: null,
        thumbnail_url: null,
        average_rating: null,
        ratings_count: 0,
      },
    },
    can_accept: false,
    can_refuse: false,
    can_pay: false,
    can_rate: false,
    my_rating: null,
    created_at: '2026-04-08T10:00:00Z',
    updated_at: '2026-04-08T10:00:00Z',
    ...overrides,
  }
}

describe('BookingCard', () => {
  it('displays duration with the max prefix', () => {
    const wrapper = mount(BookingCard, {
      props: {
        booking: makeBooking(),
      },
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
          },
        },
      },
    })

    expect(wrapper.text()).toContain('max 4h')
  })

  it('displays the shooting location when present', () => {
    const wrapper = mount(BookingCard, {
      props: {
        booking: makeBooking({ lieu: 'Porto-Novo' }),
      },
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
          },
        },
      },
    })

    expect(wrapper.text()).toContain('Porto-Novo')
  })

  it('hides the shooting location when absent', () => {
    const wrapper = mount(BookingCard, {
      props: {
        booking: makeBooking({ lieu: null }),
      },
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
          },
        },
      },
    })

    expect(wrapper.text()).not.toContain('Porto-Novo')
  })
})
