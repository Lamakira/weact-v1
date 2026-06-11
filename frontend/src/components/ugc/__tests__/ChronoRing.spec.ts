import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ChronoRing from '../ChronoRing.vue'

function mountRing(props: Record<string, unknown> = {}) {
  return mount(ChronoRing, { props })
}

function arcStroke(wrapper: ReturnType<typeof mountRing>): string | undefined {
  return wrapper.find('[data-testid="chrono-ring-arc"]').attributes('stroke')
}

describe('ChronoRing', () => {
  it('renders the teal arc below the first threshold (progress 0 and 0.39)', () => {
    expect(arcStroke(mountRing({ progress: 0 }))).toBe('#198496')
    expect(arcStroke(mountRing({ progress: 0.39 }))).toBe('#198496')
  })

  it('escalates to amber at progress 0.4', () => {
    expect(arcStroke(mountRing({ progress: 0.4 }))).toBe('#F59E0B')
  })

  it('escalates to orange at progress 0.6', () => {
    expect(arcStroke(mountRing({ progress: 0.6 }))).toBe('#EA580C')
  })

  it('escalates to red at progress 0.85', () => {
    expect(arcStroke(mountRing({ progress: 0.85 }))).toBe('#DC2626')
  })

  it('forces red when danger=true even at progress 0', () => {
    expect(arcStroke(mountRing({ progress: 0, danger: true }))).toBe('#DC2626')
  })

  it('only overrides the red threshold when danger=false (progress 0.9 stays orange)', () => {
    expect(arcStroke(mountRing({ progress: 0.9, danger: false }))).toBe('#EA580C')
  })

  it('clamps progress outside [0,1] on the dash offset', () => {
    // size 96, stroke 8 → radius 44 → circumference 2π·44
    const circumference = 2 * Math.PI * 44

    const over = mountRing({ progress: 1.5 }).find('[data-testid="chrono-ring-arc"]')
    expect(Number.parseFloat(over.attributes('stroke-dashoffset') ?? '')).toBeCloseTo(0)

    const under = mountRing({ progress: -0.5 }).find('[data-testid="chrono-ring-arc"]')
    expect(Number.parseFloat(under.attributes('stroke-dashoffset') ?? '')).toBeCloseTo(circumference)
  })

  it('falls back to progress 0 when progress is not finite (NaN)', () => {
    // size 96, stroke 8 → radius 44 → circonférence pleine = arc vide (progress 0)
    const circumference = 2 * Math.PI * 44

    const arc = mountRing({ progress: Number.NaN }).find('[data-testid="chrono-ring-arc"]')
    expect(Number.parseFloat(arc.attributes('stroke-dashoffset') ?? '')).toBeCloseTo(circumference)
    expect(arc.attributes('stroke')).toBe('#198496')
  })

  it('clamps the radius to 0 when stroke >= size', () => {
    const arc = mountRing({ size: 4, stroke: 8 }).find('[data-testid="chrono-ring-arc"]')
    expect(arc.attributes('r')).toBe('0')
  })

  it("rotates the SVG -90deg so the arc starts at 12 o'clock", () => {
    // La rotation vit en CSS scopé (.chrono-ring-svg) — on fixe la présence de la classe
    expect(mountRing().find('svg').classes()).toContain('chrono-ring-svg')
  })

  it('renders label and sublabel centered in the ring', () => {
    const wrapper = mountRing({ label: '7', sublabel: 'jours' })
    expect(wrapper.text()).toContain('7')
    expect(wrapper.text()).toContain('jours')
  })

  it('reflects size and stroke props on the SVG attributes', () => {
    const wrapper = mountRing({ size: 36, stroke: 4 })
    const svg = wrapper.find('svg')
    expect(svg.attributes('width')).toBe('36')
    expect(svg.attributes('height')).toBe('36')
    const arc = wrapper.find('[data-testid="chrono-ring-arc"]')
    expect(arc.attributes('stroke-width')).toBe('4')
    // radius = (36 - 4) / 2
    expect(arc.attributes('r')).toBe('16')
  })

  it('is aria-hidden without ariaLabel and role="img" with one', () => {
    const hidden = mountRing().find('[data-testid="chrono-ring"]')
    expect(hidden.attributes('aria-hidden')).toBe('true')
    expect(hidden.attributes('role')).toBeUndefined()

    const labelled = mountRing({ ariaLabel: 'Chrono Unboxing : 7 jours' }).find('[data-testid="chrono-ring"]')
    expect(labelled.attributes('role')).toBe('img')
    expect(labelled.attributes('aria-label')).toBe('Chrono Unboxing : 7 jours')
    expect(labelled.attributes('aria-hidden')).toBeUndefined()
  })
})
