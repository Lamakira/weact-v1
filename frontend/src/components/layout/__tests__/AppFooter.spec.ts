import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import AppFooter from '../AppFooter.vue'

// Mock the logo import
vi.mock('@/assets/images/logonoir.png', () => ({
  default: '/mock-logo.png',
}))

describe('AppFooter', () => {
  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
      { path: '/faces', name: 'faces', component: { template: '<div>Faces</div>' } },
      { path: '/register/producer', name: 'register-producer', component: { template: '<div>Register</div>' } },
      { path: '/pricing', name: 'pricing', component: { template: '<div>Pricing</div>' } },
      { path: '/legal', name: 'legal', component: { template: '<div>Legal</div>' } },
      { path: '/about', name: 'about', component: { template: '<div>About</div>' } },
      { path: '/contact', name: 'contact', component: { template: '<div>Contact</div>' } },
      { path: '/ressources', name: 'ressources', component: { template: '<div>Blog</div>' } },
      { path: '/faq', name: 'faq', component: { template: '<div>FAQ</div>' } },
      { path: '/guide', name: 'guide', component: { template: '<div>Guide</div>' } },
      { path: '/support', name: 'support', component: { template: '<div>Support</div>' } },
    ],
  })

  function mountFooter() {
    return mount(AppFooter, {
      global: {
        plugins: [router],
      },
    })
  }

  describe('Rendering', () => {
    it('renders footer with data-testid', () => {
      const wrapper = mountFooter()
      expect(wrapper.find('[data-testid="app-footer"]').exists()).toBe(true)
    })

    it('renders footer with role="contentinfo"', () => {
      const wrapper = mountFooter()
      const footer = wrapper.find('[data-testid="app-footer"]')
      expect(footer.attributes('role')).toBe('contentinfo')
    })

    it('renders logo', () => {
      const wrapper = mountFooter()
      expect(wrapper.find('[data-testid="footer-logo"]').exists()).toBe(true)
    })

    it('renders logo link to home', () => {
      const wrapper = mountFooter()
      const logoLink = wrapper.find('[data-testid="footer-logo-link"]')
      expect(logoLink.exists()).toBe(true)
      expect(logoLink.attributes('href')).toBe('/')
    })

    it('renders tagline text', () => {
      const wrapper = mountFooter()
      const tagline = wrapper.find('[data-testid="footer-tagline"]')
      expect(tagline.exists()).toBe(true)
      expect(tagline.text()).toBe('Marketplace béninoise du casting.')
    })
  })

  describe('Social Media Icons', () => {
    it('renders social icons container', () => {
      const wrapper = mountFooter()
      expect(wrapper.find('[data-testid="footer-social-icons"]').exists()).toBe(true)
    })

    it('renders Instagram link with aria-label', () => {
      const wrapper = mountFooter()
      const instagram = wrapper.find('[data-testid="social-link-instagram"]')
      expect(instagram.exists()).toBe(true)
      expect(instagram.attributes('aria-label')).toBe('Suivez-nous sur Instagram')
    })

    it('renders Facebook link with aria-label', () => {
      const wrapper = mountFooter()
      const facebook = wrapper.find('[data-testid="social-link-facebook"]')
      expect(facebook.exists()).toBe(true)
      expect(facebook.attributes('aria-label')).toBe('Suivez-nous sur Facebook')
    })

    it('renders Twitter link with aria-label', () => {
      const wrapper = mountFooter()
      const twitter = wrapper.find('[data-testid="social-link-twitter"]')
      expect(twitter.exists()).toBe(true)
      expect(twitter.attributes('aria-label')).toBe('Suivez-nous sur Twitter')
    })

    it('renders YouTube link with aria-label', () => {
      const wrapper = mountFooter()
      const youtube = wrapper.find('[data-testid="social-link-youtube"]')
      expect(youtube.exists()).toBe(true)
      expect(youtube.attributes('aria-label')).toBe('Suivez-nous sur YouTube')
    })

    it('social links open in new tab', () => {
      const wrapper = mountFooter()
      const instagram = wrapper.find('[data-testid="social-link-instagram"]')
      expect(instagram.attributes('target')).toBe('_blank')
      expect(instagram.attributes('rel')).toBe('noopener noreferrer')
    })
  })

  describe('TROUVER DES FACES Column', () => {
    it('renders column container', () => {
      const wrapper = mountFooter()
      expect(wrapper.find('[data-testid="footer-column-faces"]').exists()).toBe(true)
    })

    it('renders column title', () => {
      const wrapper = mountFooter()
      const column = wrapper.find('[data-testid="footer-column-faces"]')
      expect(column.text()).toContain('TROUVER DES FACES')
    })

    it('renders "Parcourir les profils" link', () => {
      const wrapper = mountFooter()
      const link = wrapper.find('[data-testid="footer-link-faces"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toBe('Parcourir les profils')
      expect(link.attributes('href')).toBe('/faces')
    })

    it('renders "Publier une mission" link', () => {
      const wrapper = mountFooter()
      const link = wrapper.find('[data-testid="footer-link-publish"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toBe('Publier une mission')
      expect(link.attributes('href')).toBe('/register/producer')
    })

    it('renders "Tarifs producteurs" link', () => {
      const wrapper = mountFooter()
      const link = wrapper.find('[data-testid="footer-link-pricing"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toBe('Tarifs producteurs')
      expect(link.attributes('href')).toBe('/pricing')
    })
  })

  describe('ENTREPRISE Column', () => {
    it('renders column container', () => {
      const wrapper = mountFooter()
      expect(wrapper.find('[data-testid="footer-column-company"]').exists()).toBe(true)
    })

    it('renders column title', () => {
      const wrapper = mountFooter()
      const column = wrapper.find('[data-testid="footer-column-company"]')
      expect(column.text()).toContain('ENTREPRISE')
    })

    it('renders "Légal" link', () => {
      const wrapper = mountFooter()
      const link = wrapper.find('[data-testid="footer-link-legal"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toBe('Légal')
      expect(link.attributes('href')).toBe('/legal')
    })

    it('renders "À propos" link', () => {
      const wrapper = mountFooter()
      const link = wrapper.find('[data-testid="footer-link-about"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toBe('À propos')
      expect(link.attributes('href')).toBe('/about')
    })

    it('renders "Contact" link', () => {
      const wrapper = mountFooter()
      const link = wrapper.find('[data-testid="footer-link-contact"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toBe('Contact')
      expect(link.attributes('href')).toBe('/contact')
    })
  })

  describe('RESSOURCES Column', () => {
    it('renders column container', () => {
      const wrapper = mountFooter()
      expect(wrapper.find('[data-testid="footer-column-resources"]').exists()).toBe(true)
    })

    it('renders column title', () => {
      const wrapper = mountFooter()
      const column = wrapper.find('[data-testid="footer-column-resources"]')
      expect(column.text()).toContain('RESSOURCES')
    })

    it('renders "Blog" link', () => {
      const wrapper = mountFooter()
      const link = wrapper.find('[data-testid="footer-link-blog"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toBe('Blog')
      expect(link.attributes('href')).toBe('/ressources')
    })

    it('renders "FAQ" link', () => {
      const wrapper = mountFooter()
      const link = wrapper.find('[data-testid="footer-link-faq"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toBe('FAQ')
      expect(link.attributes('href')).toBe('/faq')
    })

    it('renders "Guide de démarrage" link', () => {
      const wrapper = mountFooter()
      const link = wrapper.find('[data-testid="footer-link-guide"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toBe('Guide de démarrage')
      expect(link.attributes('href')).toBe('/guide')
    })

    it('renders "Support" link', () => {
      const wrapper = mountFooter()
      const link = wrapper.find('[data-testid="footer-link-support"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toBe('Support')
      expect(link.attributes('href')).toBe('/support')
    })
  })

  describe('Bottom Bar', () => {
    it('renders bottom bar container', () => {
      const wrapper = mountFooter()
      expect(wrapper.find('[data-testid="footer-bottom-bar"]').exists()).toBe(true)
    })

    it('renders copyright text', () => {
      const wrapper = mountFooter()
      const copyright = wrapper.find('[data-testid="footer-copyright"]')
      expect(copyright.exists()).toBe(true)
      expect(copyright.text()).toBe('© 2026 WeAct. Tous droits réservés.')
    })

    it('renders "Made with ❤️ in Benin" attribution', () => {
      const wrapper = mountFooter()
      const attribution = wrapper.find('[data-testid="footer-attribution"]')
      expect(attribution.exists()).toBe(true)
      expect(attribution.text()).toContain('Made with')
      expect(attribution.text()).toContain('in Benin')
    })
  })

  describe('Responsive Classes', () => {
    it('has responsive grid classes on main container', () => {
      const wrapper = mountFooter()
      const grid = wrapper.find('.grid')
      expect(grid.exists()).toBe(true)
      expect(grid.classes()).toContain('grid-cols-1')
      expect(grid.classes()).toContain('md:grid-cols-2')
      expect(grid.classes()).toContain('lg:grid-cols-4')
    })

    it('bottom bar has responsive flex direction', () => {
      const wrapper = mountFooter()
      const bottomBar = wrapper.find('[data-testid="footer-bottom-bar"]')
      expect(bottomBar.classes()).toContain('flex-col')
      expect(bottomBar.classes()).toContain('md:flex-row')
    })
  })

  describe('Accessibility', () => {
    it('all links have data-testid attributes', () => {
      const wrapper = mountFooter()
      const allLinks = wrapper.findAll('a')

      // Count links that should have data-testid (all internal links + social)
      const linksWithTestId = allLinks.filter(link => link.attributes('data-testid'))
      expect(linksWithTestId.length).toBeGreaterThan(10) // 10 internal + 4 social + 1 logo
    })

    it('all social icons have aria-labels', () => {
      const wrapper = mountFooter()
      const socialIcons = ['instagram', 'facebook', 'twitter', 'youtube']

      socialIcons.forEach(social => {
        const link = wrapper.find(`[data-testid="social-link-${social}"]`)
        expect(link.attributes('aria-label')).toBeTruthy()
      })
    })

    it('social links have visible focus states with focus-visible:ring classes', () => {
      const wrapper = mountFooter()
      const instagram = wrapper.find('[data-testid="social-link-instagram"]')
      expect(instagram.classes()).toContain('focus-visible:ring-2')
      expect(instagram.classes()).toContain('focus-visible:ring-[#198496]')
    })

    it('navigation links have visible focus states with focus-visible:ring classes', () => {
      const wrapper = mountFooter()
      const link = wrapper.find('[data-testid="footer-link-faces"]')
      expect(link.classes()).toContain('focus-visible:ring-2')
      expect(link.classes()).toContain('focus-visible:ring-[#198496]')
    })

    it('heart emoji is hidden from screen readers with aria-hidden', () => {
      const wrapper = mountFooter()
      const attribution = wrapper.find('[data-testid="footer-attribution"]')
      const heartSpan = attribution.find('span[aria-hidden="true"]')
      expect(heartSpan.exists()).toBe(true)
      expect(heartSpan.text()).toContain('❤️')
    })

    it('has sr-only text for screen reader accessibility', () => {
      const wrapper = mountFooter()
      const attribution = wrapper.find('[data-testid="footer-attribution"]')
      const srOnly = attribution.find('.sr-only')
      expect(srOnly.exists()).toBe(true)
      expect(srOnly.text()).toBe('love')
    })
  })
})
