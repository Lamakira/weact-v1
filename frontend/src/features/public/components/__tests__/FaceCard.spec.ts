import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import FaceCard from '../FaceCard.vue'
import type { PublicFace } from '../../services/publicFacesApi'

// Mock router
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
    { path: '/faces/:id', name: 'public-face-profile', component: { template: '<div>Face Profile</div>' } },
  ],
})

const mockFace: PublicFace = {
  id: 1,
  prenom: 'Adjoua',
  ville: 'Cotonou',
  categorie: 'acteur',
  categorie_label: 'Acteur',
  is_available: true,
  profile_photo_thumbnail_url: 'https://example.com/photo.jpg',
  average_rating: 4.5,
}

describe('FaceCard', () => {
  const mountCard = (face: Partial<PublicFace> = {}) => {
    return mount(FaceCard, {
      props: {
        face: { ...mockFace, ...face },
      },
      global: {
        plugins: [router],
      },
    })
  }

  describe('Rendering', () => {
    it('renders all required fields', () => {
      const wrapper = mountCard()

      expect(wrapper.text()).toContain('Adjoua')
      expect(wrapper.text()).toContain('Cotonou')
      expect(wrapper.text()).toContain('Acteur')
      expect(wrapper.text()).toContain('Disponible')
    })

    it('displays the correct data-testid', () => {
      const wrapper = mountCard()
      expect(wrapper.attributes('data-testid')).toBe('face-card-1')
    })

    it('displays profile photo with correct alt text', () => {
      const wrapper = mountCard()
      const img = wrapper.find('img')

      expect(img.exists()).toBe(true)
      expect(img.attributes('alt')).toBe('Photo de Adjoua')
      expect(img.attributes('src')).toBe('https://example.com/photo.jpg')
    })

    it('uses lazy loading for images', () => {
      const wrapper = mountCard()
      const img = wrapper.find('img')

      expect(img.attributes('loading')).toBe('lazy')
    })

    it('displays placeholder when no photo provided', () => {
      const wrapper = mountCard({ profile_photo_thumbnail_url: null })
      const img = wrapper.find('img')

      expect(img.attributes('src')).toBe('/placeholder-avatar.png')
    })

    it('displays category badge with categorie_label', () => {
      const wrapper = mountCard()
      expect(wrapper.text()).toContain('Acteur')
    })

    it('falls back to categorie when categorie_label is missing', () => {
      const wrapper = mountCard({ categorie_label: '', categorie: 'mannequin' })
      expect(wrapper.text()).toContain('mannequin')
    })

    it('hides ville when not provided', () => {
      const wrapper = mountCard({ ville: null })
      expect(wrapper.text()).not.toContain('Cotonou')
    })
  })

  describe('Availability indicator', () => {
    it('shows green indicator when available', () => {
      const wrapper = mountCard({ is_available: true })

      expect(wrapper.text()).toContain('Disponible')
      expect(wrapper.find('.bg-green-500').exists()).toBe(true)
    })

    it('shows gray indicator when unavailable', () => {
      const wrapper = mountCard({ is_available: false })

      expect(wrapper.text()).toContain('Indisponible')
      expect(wrapper.find('.bg-gray-400').exists()).toBe(true)
    })
  })

  describe('Navigation', () => {
    it('links to the correct face profile page', () => {
      const wrapper = mountCard()

      expect(wrapper.attributes('href')).toBe('/faces/1')
    })

    it('is keyboard accessible with RouterLink', () => {
      const wrapper = mountCard()

      // RouterLink is inherently focusable and keyboard accessible
      expect(wrapper.element.tagName).toBe('A')
      expect(wrapper.attributes('aria-label')).toBe('Voir le profil de Adjoua')
    })
  })

  describe('Accessibility', () => {
    it('has proper aria-label for screen readers', () => {
      const wrapper = mountCard()
      expect(wrapper.attributes('aria-label')).toBe('Voir le profil de Adjoua')
    })

    it('has focus-visible styles', () => {
      const wrapper = mountCard()
      expect(wrapper.classes()).toContain('focus-visible:outline-none')
      expect(wrapper.classes()).toContain('focus-visible:ring-2')
    })
  })

  describe('Hover effects', () => {
    it('has hover scale class', () => {
      const wrapper = mountCard()
      expect(wrapper.classes()).toContain('hover:scale-[1.02]')
    })

    it('has hover shadow class', () => {
      const wrapper = mountCard()
      expect(wrapper.classes()).toContain('hover:shadow-lg')
    })

    it('has hover border color class', () => {
      const wrapper = mountCard()
      expect(wrapper.classes()).toContain('hover:border-[#198496]/30')
    })
  })
})
