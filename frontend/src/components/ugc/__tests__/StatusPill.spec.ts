import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import StatusPill from '../StatusPill.vue'
import type { StatusPillKind } from '../ugc'

// Map de référence — shared.jsx:170-190 (design handoff)
const EXPECTED_STYLES: Record<StatusPillKind, { color: string; background: string }> = {
  pending: { color: '#0F1419', background: '#F3F4F6' },
  paid: { color: '#198496', background: 'rgba(25,132,150,0.10)' },
  accepted: { color: '#198496', background: 'rgba(25,132,150,0.10)' },
  shipped: { color: '#1D4ED8', background: 'rgba(29,78,216,0.08)' },
  received: { color: '#7C3AED', background: 'rgba(124,58,237,0.08)' },
  delivered: { color: '#059669', background: 'rgba(5,150,105,0.10)' },
  completed: { color: '#059669', background: 'rgba(5,150,105,0.10)' },
  overdue: { color: '#DC2626', background: 'rgba(220,38,38,0.10)' },
  suspended: { color: '#DC2626', background: 'rgba(220,38,38,0.10)' },
}

describe('StatusPill', () => {
  it('renders the slot content', () => {
    const wrapper = mount(StatusPill, {
      props: { kind: 'accepted' },
      slots: { default: 'Candidatures ouvertes' },
    })

    expect(wrapper.text()).toContain('Candidatures ouvertes')
  })

  it('defaults to the pending kind when no kind prop is given', () => {
    const wrapper = mount(StatusPill, { slots: { default: 'En attente' } })

    const pill = wrapper.get('[data-testid="status-pill"]')
    expect(pill.attributes('style')).toContain('color: #0F1419')
  })

  it.each(Object.entries(EXPECTED_STYLES))(
    'applies the exact design colors for kind "%s"',
    (kind, expected) => {
      const wrapper = mount(StatusPill, {
        props: { kind: kind as StatusPillKind },
        slots: { default: 'Label' },
      })

      const style = wrapper.get('[data-testid="status-pill"]').attributes('style') ?? ''
      expect(style).toContain(`color: ${expected.color}`)
      // happy-dom normalise rgba(...) avec des espaces après les virgules
      expect(style.replace(/,\s+/g, ',')).toContain(`background-color: ${expected.background}`)
    },
  )

  it('renders the colored dot matching the kind color', () => {
    const wrapper = mount(StatusPill, {
      props: { kind: 'overdue' },
      slots: { default: 'Délai dépassé' },
    })

    const dot = wrapper.get('[data-testid="status-pill-dot"]')
    expect(dot.attributes('style')).toContain('background-color: #DC2626')
  })
})
