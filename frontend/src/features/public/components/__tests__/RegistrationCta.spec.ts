import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import RegistrationCta from '../RegistrationCta.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', component: { template: '<div>Home</div>' } },
    { path: '/register/face', name: 'register-face', component: { template: '<div>Face</div>' } },
    { path: '/register/producer', name: 'register-producer', component: { template: '<div>Producer</div>' } },
  ],
})

const mountCta = (variant: 'faces' | 'missions') =>
  mount(RegistrationCta, {
    props: { variant },
    global: { plugins: [router] },
  })

describe('RegistrationCta', () => {
  describe('faces variant', () => {
    it('renders the section with data-testid', () => {
      const wrapper = mountCta('faces')
      expect(wrapper.find('[data-testid="registration-cta"]').exists()).toBe(true)
    })

    it('displays faces-specific heading', () => {
      const wrapper = mountCta('faces')
      expect(wrapper.find('h2').text()).toBe('Rejoignez notre communauté')
    })

    it('displays faces-specific description', () => {
      const wrapper = mountCta('faces')
      expect(wrapper.text()).toContain('Créez votre profil pour être visible')
    })

    it('renders face registration CTA linking to /register/face', () => {
      const wrapper = mountCta('faces')
      const link = wrapper.find('[data-testid="register-face-cta"]')
      expect(link.exists()).toBe(true)
      expect(link.attributes('href')).toBe('/register/face')
      expect(link.text()).toContain('Créer mon profil')
    })

    it('renders producer registration CTA linking to /register/producer', () => {
      const wrapper = mountCta('faces')
      const link = wrapper.find('[data-testid="register-producer-cta"]')
      expect(link.exists()).toBe(true)
      expect(link.attributes('href')).toBe('/register/producer')
      expect(link.text()).toContain('Poster une mission')
    })
  })

  describe('missions variant', () => {
    it('displays missions-specific heading', () => {
      const wrapper = mountCta('missions')
      expect(wrapper.find('h2').text()).toContain("Prêt(e) à passer à l'action")
    })

    it('displays missions-specific description', () => {
      const wrapper = mountCta('missions')
      expect(wrapper.text()).toContain('Postulez aux missions')
    })

    it('renders face registration CTA with missions-specific text', () => {
      const wrapper = mountCta('missions')
      const link = wrapper.find('[data-testid="register-face-cta"]')
      expect(link.exists()).toBe(true)
      expect(link.attributes('href')).toBe('/register/face')
      expect(link.text()).toContain('Postuler aux missions')
    })

    it('renders producer registration CTA linking to /register/producer', () => {
      const wrapper = mountCta('missions')
      const link = wrapper.find('[data-testid="register-producer-cta"]')
      expect(link.exists()).toBe(true)
      expect(link.attributes('href')).toBe('/register/producer')
      expect(link.text()).toContain('Publier une mission')
    })
  })

  describe('accessibility', () => {
    it('has a semantic section element', () => {
      const wrapper = mountCta('faces')
      expect(wrapper.find('section').exists()).toBe(true)
    })

    it('has aria-hidden on decorative icons', () => {
      const wrapper = mountCta('faces')
      const icons = wrapper.findAll('[aria-hidden="true"]')
      expect(icons.length).toBeGreaterThanOrEqual(2)
    })
  })
})
