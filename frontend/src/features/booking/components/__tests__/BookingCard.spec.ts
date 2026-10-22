import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import BookingCard from '../BookingCard.vue'
import type { Booking } from '@/features/booking/types'

const viewer = vi.hoisted(() => ({ type: 'Face' }))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: {
      get userable_type() {
        return viewer.type
      },
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

  it('renders a UGC booking with no shoot date / duration without crashing', () => {
    const wrapper = mount(BookingCard, {
      props: {
        booking: makeBooking({
          type_contenu: 'UGC',
          date_debut: null,
          date_fin: null,
          duree_heures: null,
          lieu: null,
        }),
      },
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
          },
        },
      },
    })

    // No "Invalid Date", no date-range / duration rows for a UGC dotation.
    expect(wrapper.text()).not.toContain('Invalid Date')
    expect(wrapper.text()).not.toContain('max')
    expect(wrapper.text()).toContain('En attente') // the card still renders
  })

  describe('sous-titre selon le statut et le rôle (lot C6)', () => {
    const mountCard = (booking: Booking) =>
      mount(BookingCard, {
        props: { booking },
        global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } },
      })
    const subtitle = (booking: Booking) => mountCard(booking).find('[data-testid="booking-card-subtitle"]').text()

    it('ne répète plus « Demande de booking » quel que soit le statut', () => {
      viewer.type = 'Face'
      expect(mountCard(makeBooking({ status: 'paid' })).text()).not.toContain('Demande de booking')
    })

    it('la pastille d’un booking payé dit « Paiement sécurisé », pas « Payée »', () => {
      viewer.type = 'Face'
      const text = mountCard(makeBooking({ status: 'paid' })).text()
      expect(text).toContain('Paiement sécurisé')
      expect(text).not.toContain('Payée')
    })

    it('Face : demande en attente → à elle de répondre ; payé → paiement sécurisé', () => {
      viewer.type = 'Face'
      expect(subtitle(makeBooking({ status: 'pending' }))).toBe('À vous de répondre')
      expect(subtitle(makeBooking({ status: 'accepted' }))).toBe('En attente du paiement')
      expect(subtitle(makeBooking({ status: 'paid' }))).toBe('Confirmé, paiement sécurisé')
    })

    it('Producer : demande en attente → sa réponse ; accepté → à payer', () => {
      viewer.type = 'Producer'
      expect(subtitle(makeBooking({ status: 'pending' }))).toBe('En attente de sa réponse')
      expect(subtitle(makeBooking({ status: 'accepted' }))).toBe('À payer')
      viewer.type = 'Face'
    })
  })

  describe('statut, montant et méta UGC (lot F4)', () => {
    const mountCard = (booking: Booking) =>
      mount(BookingCard, {
        props: { booking },
        global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } },
      })
    const ugcProductOnly = {
      type_contenu: 'UGC',
      date_debut: null,
      date_fin: null,
      duree_heures: null,
      lieu: null,
      nom_produit: 'coffret Bissap',
      nombre_videos: 2,
      montant_face_recoit: 0,
    }

    it('booking UGC produit seul côté Face : « Produit seul » au lieu de « 0 F CFA », produit et vidéos en méta', () => {
      viewer.type = 'Face'
      const wrapper = mountCard(makeBooking({ ...ugcProductOnly, status: 'accepted' }))

      expect(wrapper.get('[data-testid="booking-card-amount"]').text()).toContain('Produit seul')
      expect(wrapper.text()).not.toContain('0 F CFA')
      expect(wrapper.get('[data-testid="booking-card-product"]').text()).toBe('Produit : coffret Bissap')
      expect(wrapper.get('[data-testid="booking-card-videos"]').text()).toBe('2 vidéos')
    })

    it('booking cash : le montant passe par le formateur et reste affiché', () => {
      viewer.type = 'Face'
      const wrapper = mountCard(makeBooking())

      expect(wrapper.get('[data-testid="booking-card-amount"]').text().replace(/\s/g, '')).toContain('45000FCFA')
      expect(wrapper.find('[data-testid="booking-card-product"]').exists()).toBe(false)
    })

    it('statut dit du point de vue de la personne qui lit', () => {
      viewer.type = 'Face'
      expect(mountCard(makeBooking({ status: 'confirmed_by_producer' })).text()).toContain('À confirmer par vous')
      expect(mountCard(makeBooking({ status: 'refused' })).text()).toContain('Refusé par vous')

      viewer.type = 'Producer'
      expect(mountCard(makeBooking({ status: 'confirmed_by_producer' })).text()).toContain('Confirmé par vous')
      expect(mountCard(makeBooking({ status: 'refused' })).text()).toContain('Refusé par la Face')
      viewer.type = 'Face'
    })
  })

  describe('échéance et action principale (lot D7)', () => {
    // Le vrai RouterLink n'est pas monté : le stub rend un <a> portant les attributs reçus.
    const mountCard = (booking: Booking) =>
      mount(BookingCard, {
        props: { booking },
        global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } },
      })

    it('Face : demande à traiter → ligne d’échéance et bouton « Répondre »', () => {
      viewer.type = 'Face'
      const wrapper = mountCard(
        makeBooking({ status: 'pending', can_accept: true, face_response_deadline: '2026-10-23T13:00:00Z' }),
      )

      expect(wrapper.find('[data-testid="booking-card-deadline"]').text()).toBe('Répondre avant le ven. 23 oct. à 14h00')
      expect(wrapper.find('[data-testid="booking-card-action"]').text()).toBe('Répondre')
    })

    it('Producer : booking accepté à payer → « Payer avant le … » et bouton « Payer »', () => {
      viewer.type = 'Producer'
      const wrapper = mountCard(
        makeBooking({ status: 'accepted', can_pay: true, payment_deadline: '2026-10-21T10:00:00Z' }),
      )
      viewer.type = 'Face'

      expect(wrapper.find('[data-testid="booking-card-deadline"]').text()).toBe('Payer avant le mer. 21 oct. à 11h00')
      expect(wrapper.find('[data-testid="booking-card-action"]').text()).toBe('Payer')
    })

    it('action sans échéance : bouton seul, pas de ligne d’échéance', () => {
      viewer.type = 'Face'
      const wrapper = mountCard(makeBooking({ status: 'confirmed_by_producer' }))

      expect(wrapper.find('[data-testid="booking-card-deadline"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="booking-card-action"]').text()).toBe('Confirmer la fin')
    })

    it('requires_my_action=false : ni bouton ni échéance, même si le statut suggère une action', () => {
      viewer.type = 'Face'
      const wrapper = mountCard(
        makeBooking({
          status: 'pending',
          can_accept: true,
          face_response_deadline: '2026-10-23T13:00:00Z',
          requires_my_action: false,
        }),
      )

      expect(wrapper.find('[data-testid="booking-card-action"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="booking-card-deadline"]').exists()).toBe(false)
    })

    it('rien à faire : ni bouton ni échéance', () => {
      viewer.type = 'Face'
      const wrapper = mountCard(makeBooking({ status: 'completed' }))

      expect(wrapper.find('[data-testid="booking-card-action"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="booking-card-deadline"]').exists()).toBe(false)
    })

    it('le reste de la carte reste cliquable sans lien imbriqué dans un lien', () => {
      viewer.type = 'Face'
      const wrapper = mountCard(makeBooking({ status: 'pending', can_accept: true }))

      expect(wrapper.find('[data-testid="booking-card-link"]').exists()).toBe(true)
      expect(wrapper.find('a a').exists()).toBe(false)
    })
  })
})
