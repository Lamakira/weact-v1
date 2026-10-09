import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import WhatsappMissingBanner from '../WhatsappMissingBanner.vue'

const mockPush = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush }),
}))

describe('WhatsappMissingBanner', () => {
  function mountBanner() {
    return mount(WhatsappMissingBanner)
  }

  it('renders with correct message text', () => {
    const wrapper = mountBanner()
    expect(wrapper.text()).toContain(
      'Votre numéro WhatsApp est utilisé uniquement pour vous contacter lorsque vous êtes retenu(e) pour une mission ou booké(e).',
    )
    expect(wrapper.text()).toContain('Renseignez-le pour ne manquer aucune opportunité.')
  })

  it('has the correct data-testid', () => {
    const wrapper = mountBanner()
    expect(wrapper.find('[data-testid="whatsapp-missing-banner"]').exists()).toBe(true)
  })

  it('renders CTA button that navigates to /face/profile', async () => {
    const wrapper = mountBanner()
    const cta = wrapper.find('[data-testid="whatsapp-banner-cta"]')

    expect(cta.exists()).toBe(true)
    expect(cta.text()).toBe('Renseigner mon WhatsApp')

    await cta.trigger('click')
    expect(mockPush).toHaveBeenCalledWith('/face/profile?focus=whatsapp')
  })

  it('accepts custom copy and target (Producer layout)', async () => {
    mockPush.mockClear()
    const wrapper = mount(WhatsappMissingBanner, {
      props: {
        title: 'Titre producteur',
        message: "Ajoutez votre numéro WhatsApp pour que l'équipe WeAct puisse vous joindre rapidement.",
        ctaLabel: 'Ajouter mon numéro',
        to: '/producer/profile?focus=whatsapp',
      },
    })

    expect(wrapper.text()).toContain('Titre producteur')
    expect(wrapper.text()).toContain("l'équipe WeAct puisse vous joindre rapidement.")
    expect(wrapper.text()).not.toContain('retenu(e)')

    const cta = wrapper.get('[data-testid="whatsapp-banner-cta"]')
    expect(cta.text()).toBe('Ajouter mon numéro')
    await cta.trigger('click')
    expect(mockPush).toHaveBeenCalledWith('/producer/profile?focus=whatsapp')
  })

  it('uses the Régie look: neutral white panel, no off-system blue/indigo', () => {
    const wrapper = mountBanner()
    const root = wrapper.get('[data-testid="whatsapp-missing-banner"]')

    expect(root.classes()).toEqual(expect.arrayContaining(['bg-white', 'ring-1', 'ring-line']))
    expect(wrapper.html()).not.toMatch(/blue-|indigo-/)
    expect(wrapper.get('[data-testid="whatsapp-banner-cta"]').classes()).toContain('bg-weact-600')
  })
})
