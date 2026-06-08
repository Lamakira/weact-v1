import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PayTile from '../PayTile.vue'

describe('PayTile', () => {
  it('renders the MTN method', () => {
    const wrapper = mount(PayTile, { props: { provider: 'mtn' } })
    expect(wrapper.find('[data-testid="pay-tile"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('MTN MoMo')
  })

  it('renders the Moov method', () => {
    const wrapper = mount(PayTile, { props: { provider: 'moov' } })
    expect(wrapper.text()).toContain('Moov Money')
  })

  it('renders the card method', () => {
    const wrapper = mount(PayTile, { props: { provider: 'fedapay' } })
    expect(wrapper.text()).toContain('Carte bancaire')
  })
})
