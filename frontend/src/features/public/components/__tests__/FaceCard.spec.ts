import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import FaceCard from '../FaceCard.vue'
import WBadge from '@/components/ui/WBadge.vue'
import type { PublicFace } from '../../services/publicFacesApi'

// Mock router
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
    { path: '/faces', name: 'public-faces-list', component: { template: '<div>Faces List</div>' } },
    { path: '/faces/:username', name: 'public-face-profile', component: { template: '<div>Face Profile</div>' } },
  ],
})

const mockFace: PublicFace = {
  id: 1,
  username: 'adjoua-dossou',
  prenom: 'Adjoua',
  nom: 'Dossou',
  ville: 'Cotonou',
  categories: [{ value: 'acteur', label: 'Acteur' }],
  is_available: true,
  profile_photo_thumbnail_url: 'https://example.com/photo.jpg',
  average_rating: 4.5,
  has_elite_badge: false,
}

describe('FaceCard', () => {
  beforeEach(async () => {
    await router.push('/faces?page=5&search=Adjoua')
  })

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

      // Should not render img element
      expect(wrapper.find('img').exists()).toBe(false)

      // Should show placeholder with "Pas de photo" text
      expect(wrapper.text()).toContain('Pas de photo')

      // Should have aria-label for accessibility
      const placeholder = wrapper.find('[aria-label="Pas de photo pour Adjoua"]')
      expect(placeholder.exists()).toBe(true)
    })

    it('displays category badges from categories array', () => {
      const wrapper = mountCard()
      expect(wrapper.text()).toContain('Acteur')
    })

    it('displays multiple category badges', () => {
      const wrapper = mountCard({
        categories: [
          { value: 'acteur', label: 'Acteur' },
          { value: 'mannequin', label: 'Mannequin' },
        ],
      })
      expect(wrapper.text()).toContain('Acteur')
      expect(wrapper.text()).toContain('Mannequin')
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

      expect(wrapper.attributes('href')).toBe('/faces/adjoua-dossou?returnTo=%2Ffaces%3Fpage%3D5%26search%3DAdjoua')
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

  describe('FP-2.12.1 — Élite badge (WBadge V13, overlay top-left)', () => {
    it('renders WBadge in elite tier at 22px with halo when face.has_elite_badge is true', () => {
      const wrapper = mountCard({ has_elite_badge: true })
      const badge = wrapper.findComponent(WBadge)
      expect(badge.exists()).toBe(true)
      expect(badge.props('tier')).toBe('elite')
      expect(badge.props('size')).toBe(22)
      // Top-left overlay placement (not bottom-inline with name) for visibility on dark photos
      expect(badge.classes()).toContain('top-3')
      expect(badge.classes()).toContain('left-3')
      // White drop-shadow halo for contrast on any background photo
      expect(badge.classes()).toContain('drop-shadow-[0_0_3px_rgba(255,255,255,0.95)]')
    })

    it('does not render WBadge when face.has_elite_badge is false', () => {
      const wrapper = mountCard({ has_elite_badge: false })
      expect(wrapper.findComponent(WBadge).exists()).toBe(false)
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
