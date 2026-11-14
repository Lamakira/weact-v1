import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@/lib/analytics', () => ({ syncAnalyticsConsent: vi.fn() }))

async function mountBanner() {
  vi.resetModules()
  const { default: Banner } = await import('../CookieConsentBanner.vue')
  return mount(Banner, {
    global: {
      stubs: {
        Teleport: true,
        Transition: { template: '<slot />' },
        RouterLink: {
          template: '<a v-bind="$attrs" :href="to"><slot /></a>',
          props: ['to'],
          inheritAttrs: false,
        },
      },
    },
  })
}

beforeEach(() => {
  localStorage.clear()
})

describe('CookieConsentBanner', () => {
  it('affiche le titre, le texte, le lien et deux boutons', async () => {
    const wrapper = await mountBanner()
    const banner = wrapper.find('[data-testid="cookie-banner"]')
    expect(banner.exists()).toBe(true)
    expect(banner.text()).toContain('Nous aider à améliorer WeAct')
    expect(banner.text()).toContain(
      'Avec votre accord, nous mesurons les pages consultées et les clics, et enregistrons certaines sessions de façon anonymisée. Vos saisies et vos messages ne sont jamais enregistrés.',
    )
    expect(wrapper.find('[data-testid="cookie-banner-learn-more"]').attributes('href')).toBe(
      '/politique-confidentialite',
    )
    expect(wrapper.find('[data-testid="cookie-banner-reject"]').text()).toBe('Refuser')
    expect(wrapper.find('[data-testid="cookie-banner-accept"]').text()).toBe('Accepter')
    expect(banner.findAll('button')).toHaveLength(2)
  })

  it('Accepter enregistre un consentement analytique et masque le bandeau', async () => {
    const wrapper = await mountBanner()
    await wrapper.find('[data-testid="cookie-banner-accept"]').trigger('click')
    expect(JSON.parse(localStorage.getItem('weact_cookie_consent')!).state.preferences.analytics).toBe(true)
    expect(wrapper.find('[data-testid="cookie-banner"]').exists()).toBe(false)
  })

  it('Refuser enregistre un refus analytique et masque le bandeau', async () => {
    const wrapper = await mountBanner()
    await wrapper.find('[data-testid="cookie-banner-reject"]').trigger('click')
    expect(JSON.parse(localStorage.getItem('weact_cookie_consent')!).state.preferences.analytics).toBe(false)
    expect(wrapper.find('[data-testid="cookie-banner"]').exists()).toBe(false)
  })
})
