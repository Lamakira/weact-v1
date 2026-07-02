import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { RouterLink } from 'vue-router'
import UgcPaywallBanner from '../UgcPaywallBanner.vue'

// Stub RouterLink : ne rend pas de href — asserter la cible via props('to')
const mountOptions = {
  global: {
    stubs: {
      RouterLink: {
        template: '<a><slot /></a>',
        props: ['to'],
      },
    },
  },
}

const MESSAGE = "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus)."

describe('UgcPaywallBanner', () => {
  it('renders the backend-driven paywall message and the static title', () => {
    const wrapper = mount(UgcPaywallBanner, {
      props: { message: MESSAGE },
      ...mountOptions,
    })

    expect(wrapper.text()).toContain('Accès UGC réservé aux abonnés')
    expect(wrapper.text()).toContain(MESSAGE)
    expect(wrapper.text()).toContain('Voir les abonnements')
  })

  it('targets /pricing by default on the CTA', () => {
    const wrapper = mount(UgcPaywallBanner, {
      props: { message: MESSAGE },
      ...mountOptions,
    })

    expect(wrapper.findComponent(RouterLink).props('to')).toBe('/pricing')
  })

  it('respects a custom pricingUrl', () => {
    const wrapper = mount(UgcPaywallBanner, {
      props: { message: MESSAGE, pricingUrl: '/custom-pricing' },
      ...mountOptions,
    })

    expect(wrapper.findComponent(RouterLink).props('to')).toBe('/custom-pricing')
  })
})
