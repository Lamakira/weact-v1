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

const MESSAGE = "L'accès aux missions UGC est réservé aux Faces abonnées Pro ou Élite."

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

  it('renders the suspended variant with a link to the suspension page', () => {
    const wrapper = mount(UgcPaywallBanner, {
      props: { message: 'Votre accès est suspendu.', suspended: true },
      ...mountOptions,
    })

    expect(wrapper.text()).toContain('Accès UGC suspendu')
    expect(wrapper.text()).not.toContain('réservé aux abonnés')
    expect(wrapper.text()).toContain('Voir ma suspension')
    expect(wrapper.text()).not.toContain('Voir les abonnements')
    expect(wrapper.findComponent(RouterLink).props('to')).toBe('/face/ugc/suspension')
  })

  it('respects a custom suspensionUrl in the suspended variant', () => {
    const wrapper = mount(UgcPaywallBanner, {
      props: { message: MESSAGE, suspended: true, suspensionUrl: '/autre' },
      ...mountOptions,
    })

    expect(wrapper.findComponent(RouterLink).props('to')).toBe('/autre')
  })

  it('respects a custom pricingUrl', () => {
    const wrapper = mount(UgcPaywallBanner, {
      props: { message: MESSAGE, pricingUrl: '/custom-pricing' },
      ...mountOptions,
    })

    expect(wrapper.findComponent(RouterLink).props('to')).toBe('/custom-pricing')
  })
})
