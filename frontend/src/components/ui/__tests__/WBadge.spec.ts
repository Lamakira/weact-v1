import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import WBadge from '../WBadge.vue'

describe('WBadge', () => {
  it('renders the burst on the WeAct green background (#198496)', () => {
    const wrapper = mount(WBadge, { props: { tier: 'elite' } })
    const root = wrapper.find('svg.weact-badge')
    expect(root.exists()).toBe(true)
    // First path = the burst background, filled with the site's primary green.
    const burst = root.find('path')
    expect(burst.attributes('fill')).toBe('#198496')
  })

  it('renders the logo "W" (favicon paths) in white, not a text glyph', () => {
    const wrapper = mount(WBadge, { props: { tier: 'elite' } })
    // No more <text>W</text> — the W is the favicon vector now.
    expect(wrapper.find('text').exists()).toBe(false)
    // Nested <svg> carries the two favicon paths, filled white.
    const whitePaths = wrapper.findAll('path').filter((p) => p.attributes('fill') === '#fff')
    expect(whitePaths).toHaveLength(2)
  })

  it('uses the same green background regardless of tier', () => {
    const pro = mount(WBadge, { props: { tier: 'pro' } })
    expect(pro.find('svg.weact-badge path').attributes('fill')).toBe('#198496')
  })

  it('derives the accessible label from the tier, overridable via title', () => {
    expect(mount(WBadge, { props: { tier: 'elite' } }).find('svg.weact-badge').attributes('aria-label')).toBe(
      'Membre Élite',
    )
    expect(mount(WBadge, { props: { tier: 'pro' } }).find('svg.weact-badge').attributes('aria-label')).toBe(
      'Membre Pro',
    )
    expect(
      mount(WBadge, { props: { tier: 'elite', title: 'VIP' } }).find('svg.weact-badge').attributes('aria-label'),
    ).toBe('VIP')
  })
})
