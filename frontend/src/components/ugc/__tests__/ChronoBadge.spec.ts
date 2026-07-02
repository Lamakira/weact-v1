import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ChronoBadge from '../ChronoBadge.vue'

// NOW figé : useChrono lit new Date() au montage (useNow). On pilote le progress
// via les timestamps start/deadline autour de ce NOW.
const NOW = new Date('2026-06-14T12:00:00.000Z').getTime()
const H = 3_600_000

/** Mappe le hex d'escalade vers sa forme rgb (jsdom/cssstyle normalise les
 *  couleurs inline) — l'assertion accepte les deux formes. */
const RGB: Record<string, string> = {
  '#198496': 'rgb(25, 132, 150)',
  '#F59E0B': 'rgb(245, 158, 11)',
  '#EA580C': 'rgb(234, 88, 12)',
  '#DC2626': 'rgb(220, 38, 38)',
}

function iso(offsetMs: number): string {
  return new Date(NOW + offsetMs).toISOString()
}

function mountBadge(props: Record<string, unknown> = {}) {
  return mount(ChronoBadge, { props })
}

function badgeStyle(wrapper: ReturnType<typeof mountBadge>): string {
  return (wrapper.get('[data-testid="chrono-badge"]').attributes('style') ?? '')
    .replace(/\s/g, '')
    .toLowerCase()
}

function hasColor(wrapper: ReturnType<typeof mountBadge>, hex: string): boolean {
  const style = badgeStyle(wrapper)
  return style.includes(hex.toLowerCase()) || style.includes(RGB[hex].replace(/\s/g, '').toLowerCase())
}

describe('ChronoBadge', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(NOW)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders the remaining label derived from the timestamps', () => {
    // deadline dans 8 h → "8h"
    const wrapper = mountBadge({ startAt: iso(-2 * H), deadlineAt: iso(8 * H) })
    expect(wrapper.text()).toContain('8h')
  })

  it('renders a days label when more than a day remains', () => {
    const wrapper = mountBadge({ startAt: iso(0), deadlineAt: iso(72 * H) })
    expect(wrapper.text()).toContain('3j')
  })

  it('stays teal below the first threshold (progress 0.2)', () => {
    // elapsed 2h / span 10h = 0.2
    const wrapper = mountBadge({ startAt: iso(-2 * H), deadlineAt: iso(8 * H) })
    expect(hasColor(wrapper, '#198496')).toBe(true)
  })

  it('escalates to amber at progress 0.4', () => {
    // elapsed 4h / span 10h = 0.4
    const wrapper = mountBadge({ startAt: iso(-4 * H), deadlineAt: iso(6 * H) })
    expect(hasColor(wrapper, '#F59E0B')).toBe(true)
  })

  it('escalates to orange at progress 0.6', () => {
    // elapsed 6h / span 10h = 0.6
    const wrapper = mountBadge({ startAt: iso(-6 * H), deadlineAt: iso(4 * H) })
    expect(hasColor(wrapper, '#EA580C')).toBe(true)
  })

  it('escalates to red at progress 0.85', () => {
    // elapsed 510min / span 600min = 0.85
    const wrapper = mountBadge({ startAt: iso(-510 * 60_000), deadlineAt: iso(90 * 60_000) })
    expect(hasColor(wrapper, '#DC2626')).toBe(true)
  })

  it('falls back to teal (progress 0) when no timestamps are given', () => {
    const wrapper = mountBadge()
    expect(hasColor(wrapper, '#198496')).toBe(true)
    // Sans deadline, remainingLabel = '' (useChrono).
    expect(wrapper.text().trim()).toBe('')
  })

  it('applies sm sizing classes by default and lg when requested', () => {
    const sm = mountBadge({ startAt: iso(-2 * H), deadlineAt: iso(8 * H) })
    expect(sm.get('[data-testid="chrono-badge"]').classes()).toContain('text-[11px]')

    const lg = mountBadge({ startAt: iso(-2 * H), deadlineAt: iso(8 * H), size: 'lg' })
    expect(lg.get('[data-testid="chrono-badge"]').classes()).toContain('text-xs')
  })

  it('exposes a readable aria-label', () => {
    const wrapper = mountBadge({ startAt: iso(-2 * H), deadlineAt: iso(8 * H) })
    expect(wrapper.get('[data-testid="chrono-badge"]').attributes('aria-label')).toBe('SLA : 8h restant')
  })
})
