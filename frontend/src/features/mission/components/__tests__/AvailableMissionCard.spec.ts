import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AvailableMissionCard from '../AvailableMissionCard.vue'
import RatingDisplay from '@/components/RatingDisplay.vue'
import type { Mission, MissionProducer } from '../../types'

// Mock vue-router
const mockPush = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: mockPush,
  }),
}))

// Factory for creating test mission data
function createMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 1,
    titre: 'Test Mission',
    description: 'Test mission description',
    date_tournage: '2026-02-15',
    profil_recherche: 'Femme 25-35 ans',
    budget: 150000,
    date_limite_candidature: '2026-02-01',
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    genre_voulu: 'femme',
    genre_voulu_label: 'Femme',
    lieu: 'Cotonou',
    duree: '2 jours',
    status: 'published',
    status_label: 'Publiée',
    is_accepting_candidatures: true,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    producer: createProducer(),
    ...overrides,
  }
}

function createProducer(overrides: Partial<MissionProducer> = {}): MissionProducer {
  return {
    id: 1,
    type: 'agency',
    agency_name: 'Test Agency',
    first_name: null,
    last_name: null,
    display_name: 'Test Agency',
    bio: null,
    profile_photo_url: null,
    thumbnail_url: null,
    agency_logo_url: null,
    agency_logo_thumbnail_url: null,
    average_rating: null,
    ratings_count: 0,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('AvailableMissionCard', () => {
  beforeEach(() => {
    mockPush.mockClear()
  })

  describe('producer rating display', () => {
    it('renders RatingDisplay component in footer', () => {
      const mission = createMission({
        producer: createProducer({
          average_rating: 4.5,
          ratings_count: 10,
        }),
      })

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      expect(wrapper.findComponent(RatingDisplay).exists()).toBe(true)
    })

    it('passes correct average_rating to RatingDisplay', () => {
      const mission = createMission({
        producer: createProducer({
          average_rating: 4.2,
          ratings_count: 15,
        }),
      })

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      const ratingDisplay = wrapper.findComponent(RatingDisplay)
      expect(ratingDisplay.props('averageRating')).toBe(4.2)
    })

    it('passes correct ratings_count to RatingDisplay', () => {
      const mission = createMission({
        producer: createProducer({
          average_rating: 4.2,
          ratings_count: 15,
        }),
      })

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      const ratingDisplay = wrapper.findComponent(RatingDisplay)
      expect(ratingDisplay.props('reviewCount')).toBe(15)
    })

    it('passes null average_rating when producer has no ratings', () => {
      const mission = createMission({
        producer: createProducer({
          average_rating: null,
          ratings_count: 0,
        }),
      })

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      const ratingDisplay = wrapper.findComponent(RatingDisplay)
      expect(ratingDisplay.props('averageRating')).toBe(null)
      expect(ratingDisplay.props('reviewCount')).toBe(0)
    })

    it('handles missing producer gracefully', () => {
      const mission = createMission()
      // Remove producer
      delete (mission as any).producer

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      const ratingDisplay = wrapper.findComponent(RatingDisplay)
      expect(ratingDisplay.props('averageRating')).toBe(null)
      expect(ratingDisplay.props('reviewCount')).toBe(0)
    })
  })

  describe('producer click navigation', () => {
    it('navigates to producer profile on click', async () => {
      const mission = createMission({
        producer: createProducer({ id: 42 }),
      })

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      // Find the producer section (div with role="button")
      const producerSection = wrapper.find('[role="button"]')
      expect(producerSection.exists()).toBe(true)

      await producerSection.trigger('click')
      expect(mockPush).toHaveBeenCalledWith('/producers/42')
    })

    it('stops propagation when clicking producer section', async () => {
      const mission = createMission({
        producer: createProducer({ id: 42 }),
      })

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
      })

      const clickHandler = vi.fn()
      wrapper.vm.$emit = clickHandler

      const producerSection = wrapper.find('[role="button"]')
      await producerSection.trigger('click')

      // Should navigate to producer, not emit card click
      expect(mockPush).toHaveBeenCalledWith('/producers/42')
    })

    it('does not navigate when producer is missing', async () => {
      const mission = createMission()
      // Remove producer
      delete (mission as any).producer

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      const producerSection = wrapper.find('[role="button"]')
      await producerSection.trigger('click')

      expect(mockPush).not.toHaveBeenCalled()
    })

    it('navigates on Enter key press', async () => {
      const mission = createMission({
        producer: createProducer({ id: 99 }),
      })

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      const producerSection = wrapper.find('[role="button"]')
      await producerSection.trigger('keydown.enter')

      expect(mockPush).toHaveBeenCalledWith('/producers/99')
    })
  })

  describe('producer section accessibility', () => {
    it('has role="button" for keyboard accessibility', () => {
      const mission = createMission()

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      const producerSection = wrapper.find('[role="button"]')
      expect(producerSection.exists()).toBe(true)
    })

    it('has tabindex for keyboard focus', () => {
      const mission = createMission()

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      const producerSection = wrapper.find('[role="button"]')
      expect(producerSection.attributes('tabindex')).toBe('0')
    })

    it('has aria-label describing the action', () => {
      const mission = createMission({
        producer: createProducer({
          display_name: 'Studio XYZ',
          agency_name: 'Studio XYZ',
        }),
      })

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      const producerSection = wrapper.find('[role="button"]')
      expect(producerSection.attributes('aria-label')).toBe('Voir le profil de Studio XYZ')
    })
  })

  describe('producer display', () => {
    it('displays producer name', () => {
      const mission = createMission({
        producer: createProducer({
          agency_name: 'Amazing Studio',
          display_name: 'Amazing Studio',
        }),
      })

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      expect(wrapper.text()).toContain('Amazing Studio')
    })

    it('displays particulier name correctly', () => {
      const mission = createMission({
        producer: createProducer({
          type: 'particulier',
          agency_name: null,
          first_name: 'Jean',
          last_name: 'Dupont',
          display_name: 'Jean Dupont',
        }),
      })

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      expect(wrapper.text()).toContain('Jean Dupont')
    })

    it('shows fallback when producer is unknown', () => {
      const mission = createMission()
      delete (mission as any).producer

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      expect(wrapper.text()).toContain('Producteur inconnu')
    })
  })

  describe('card click emission', () => {
    it('emits click event with mission id when card is clicked', async () => {
      const mission = createMission({ id: 123 })

      const wrapper = mount(AvailableMissionCard, {
        props: { mission },
        global: {
          stubs: {
            RatingDisplay: true,
          },
        },
      })

      // Click the main card (not the producer section)
      await wrapper.trigger('click')

      expect(wrapper.emitted('click')).toBeTruthy()
      expect(wrapper.emitted('click')![0]).toEqual([123])
    })
  })
})
