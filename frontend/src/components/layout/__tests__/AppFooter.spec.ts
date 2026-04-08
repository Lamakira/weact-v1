import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AppFooter from '../AppFooter.vue'

vi.mock('@/assets/images/logoblanc.png', () => ({
  default: '/mock-logo.png',
}))

function mountFooter() {
  return mount(AppFooter, {
    global: {
      stubs: {
        RouterLink: {
          template: '<a v-bind="$attrs" :href="to"><slot /></a>',
          props: ['to'],
          inheritAttrs: false,
        },
      },
    },
  })
}

describe('AppFooter', () => {
  it('renders the footer shell, logo, and tagline', () => {
    const wrapper = mountFooter()

    expect(wrapper.find('[data-testid="app-footer"]').attributes('role')).toBe('contentinfo')
    expect(wrapper.find('[data-testid="footer-logo"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="footer-logo-link"]').attributes('href')).toBe('/')
    expect(wrapper.find('[data-testid="footer-tagline"]').text()).toBe('Marketplace béninoise du casting.')
  })

  it('renders the current social links', () => {
    const wrapper = mountFooter()

    expect(wrapper.find('[data-testid="social-link-instagram"]').attributes('aria-label')).toBe('Suivez-nous sur Instagram')
    expect(wrapper.find('[data-testid="social-link-tiktok"]').attributes('aria-label')).toBe('Suivez-nous sur TikTok')
    expect(wrapper.find('[data-testid="social-link-instagram"]').attributes('target')).toBe('_blank')
    expect(wrapper.find('[data-testid="social-link-instagram"]').attributes('rel')).toBe('noopener noreferrer')
  })

  it('renders the faces links', () => {
    const wrapper = mountFooter()

    expect(wrapper.find('[data-testid="footer-link-faces"]').attributes('href')).toBe('/faces')
    expect(wrapper.find('[data-testid="footer-link-publish"]').attributes('href')).toBe('/register/producer')
  })

  it('renders the current company links', () => {
    const wrapper = mountFooter()

    expect(wrapper.find('[data-testid="footer-link-mentions-legales"]').attributes('href')).toBe('/mentions-legales')
    expect(wrapper.find('[data-testid="footer-link-cgu"]').attributes('href')).toBe('/cgu')
    expect(wrapper.find('[data-testid="footer-link-confidentialite"]').attributes('href')).toBe('/politique-confidentialite')
    expect(wrapper.find('[data-testid="footer-link-about"]').attributes('href')).toBe('/about')
    expect(wrapper.find('[data-testid="footer-link-contact"]').attributes('href')).toBe('/contact')
  })

  it('renders the resources links and bottom bar', () => {
    const wrapper = mountFooter()

    expect(wrapper.find('[data-testid="footer-link-blog"]').attributes('href')).toBe('/ressources')
    expect(wrapper.find('[data-testid="footer-link-faq"]').attributes('href')).toBe('/faq')
    expect(wrapper.find('[data-testid="footer-link-guide"]').attributes('href')).toBe('/guide')
    expect(wrapper.find('[data-testid="footer-link-support"]').attributes('href')).toBe('/support')
    expect(wrapper.find('[data-testid="footer-bottom-bar"]').classes()).toContain('justify-center')
    expect(wrapper.find('[data-testid="footer-copyright"]').text()).toBe('© 2026 WeAct. Tous droits réservés.')
  })
})
