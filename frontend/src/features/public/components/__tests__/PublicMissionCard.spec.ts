import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import PublicMissionCard from '../PublicMissionCard.vue'
import type { PublicMission } from '../../services/publicMissionsApi'

// Mock router
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
    { path: '/missions/:id', name: 'public-mission-detail', component: { template: '<div>Mission</div>' } },
  ],
})

const mockMission: PublicMission = {
  id: 42,
  titre: 'Publicité Coca-Cola Bénin',
  description: 'Nous recherchons des acteurs et figurants pour une publicité télévisée Coca-Cola au Bénin.',
  date_tournage: '2025-03-15',
  profil_recherche: 'Homme ou femme, 20-35 ans',
  budget: 150000,
  date_limite_candidature: '2025-02-28',
  nombre_faces_voulu: 5,
  type_mission: 'publicite',
  type_mission_label: 'Publicité',
  genre_voulu: 'mixte',
  genre_voulu_label: 'Mixte',
  lieu: 'Cotonou',
  duree: '2 jours',
  status: 'published',
  status_label: 'Publiée',
  created_at: '2025-01-15T10:00:00+00:00',
  producer: {
    id: 7,
    display_name: 'Agence Bénin Créatif',
    profile_photo_thumbnail_url: 'https://example.com/producer.jpg',
    average_rating: 4.2,
    ratings_count: 8,
  },
}

describe('PublicMissionCard', () => {
  const mountCard = (mission: Partial<PublicMission> = {}) => {
    return mount(PublicMissionCard, {
      props: {
        mission: { ...mockMission, ...mission } as PublicMission,
      },
      global: {
        plugins: [router],
      },
    })
  }

  describe('Rendering', () => {
    it('renders mission title', () => {
      const wrapper = mountCard()
      expect(wrapper.find('[data-testid="mission-title"]').text()).toBe('Publicité Coca-Cola Bénin')
    })

    it('renders type badge with label', () => {
      const wrapper = mountCard()
      expect(wrapper.find('[data-testid="mission-type-badge"]').text()).toBe('Publicité')
    })

    it('renders formatted date', () => {
      const wrapper = mountCard()
      const dateText = wrapper.find('[data-testid="mission-date"]').text()
      // French date format: "15 mars 2025"
      expect(dateText).toContain('15')
      expect(dateText).toContain('2025')
    })

    it('renders lieu', () => {
      const wrapper = mountCard()
      expect(wrapper.find('[data-testid="mission-lieu"]').text()).toBe('Cotonou')
    })

    it('renders formatted budget in XOF', () => {
      const wrapper = mountCard()
      const budgetText = wrapper.find('[data-testid="mission-budget"]').text()
      expect(budgetText).toContain('150')
      expect(budgetText).toContain('000')
    })

    it('renders nombre_faces_voulu', () => {
      const wrapper = mountCard()
      expect(wrapper.find('[data-testid="mission-faces"]').text()).toContain('5')
      expect(wrapper.find('[data-testid="mission-faces"]').text()).toContain('Faces')
    })

    it('uses singular "Face" when nombre_faces_voulu is 1', () => {
      const wrapper = mountCard({ nombre_faces_voulu: 1 })
      const text = wrapper.find('[data-testid="mission-faces"]').text()
      expect(text).toContain('1 Face')
      expect(text).not.toContain('Faces')
    })

    it('displays the correct data-testid', () => {
      const wrapper = mountCard()
      expect(wrapper.attributes('data-testid')).toBe('mission-card-42')
    })
  })

  describe('Description truncation', () => {
    it('shows full description when under 120 chars', () => {
      const shortDesc = 'Une courte description.'
      const wrapper = mountCard({ description: shortDesc })
      expect(wrapper.find('[data-testid="mission-description"]').text()).toBe(shortDesc)
    })

    it('truncates description at 120 chars with ellipsis', () => {
      const longDesc = 'A'.repeat(150)
      const wrapper = mountCard({ description: longDesc })
      const text = wrapper.find('[data-testid="mission-description"]').text()
      expect(text).toHaveLength(123) // 120 + '...'
      expect(text).toMatch(/\.{3}$/)
    })

    it('handles empty description', () => {
      const wrapper = mountCard({ description: '' })
      expect(wrapper.find('[data-testid="mission-description"]').text()).toBe('')
    })
  })

  describe('Producer info', () => {
    it('displays producer name', () => {
      const wrapper = mountCard()
      expect(wrapper.find('[data-testid="mission-producer-name"]').text()).toBe('Agence Bénin Créatif')
    })

    it('displays producer avatar image', () => {
      const wrapper = mountCard()
      const img = wrapper.find('img')
      expect(img.exists()).toBe(true)
      expect(img.attributes('src')).toBe('https://example.com/producer.jpg')
    })

    it('shows initials when no avatar', () => {
      const wrapper = mountCard({
        producer: {
          id: 7,
          display_name: 'Agence Bénin Créatif',
          profile_photo_thumbnail_url: null,
          average_rating: 4.2,
          ratings_count: 8,
        },
      })
      expect(wrapper.find('img').exists()).toBe(false)
      expect(wrapper.text()).toContain('A') // First letter of 'Agence'
    })

    it('falls back to "Producteur" when producer is null', () => {
      const wrapper = mountCard({ producer: null })
      expect(wrapper.find('[data-testid="mission-producer-name"]').text()).toBe('Producteur')
    })
  })

  describe('Navigation', () => {
    it('links to the correct mission detail page', () => {
      const wrapper = mountCard()
      expect(wrapper.attributes('href')).toBe('/missions/42')
    })

    it('renders as an anchor element via RouterLink', () => {
      const wrapper = mountCard()
      expect(wrapper.element.tagName).toBe('A')
    })
  })

  describe('Accessibility', () => {
    it('has proper aria-label with mission title', () => {
      const wrapper = mountCard()
      expect(wrapper.attributes('aria-label')).toBe('Mission : Publicité Coca-Cola Bénin')
    })

    it('has aria-hidden on decorative icons', () => {
      const wrapper = mountCard()
      const icons = wrapper.findAll('[aria-hidden="true"]')
      // Calendar, MapPin, Wallet, Users icons should all be aria-hidden
      expect(icons.length).toBeGreaterThanOrEqual(4)
    })
  })

  describe('Hover effects', () => {
    it('has hover shadow class', () => {
      const wrapper = mountCard()
      expect(wrapper.classes()).toContain('hover:shadow-md')
    })

    it('has hover border color class', () => {
      const wrapper = mountCard()
      expect(wrapper.classes()).toContain('hover:border-primary/30')
    })
  })
})
