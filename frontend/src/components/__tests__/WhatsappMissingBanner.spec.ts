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
    expect(mockPush).toHaveBeenCalledWith('/face/profile')
  })
})
