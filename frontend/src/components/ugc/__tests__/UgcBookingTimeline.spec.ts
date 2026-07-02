import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import UgcBookingTimeline from '../UgcBookingTimeline.vue'

const STEP_LABELS = ['Paiement', 'Acceptation', 'Expédition', 'Réception', 'Unboxing', 'Avis']

function mountTimeline(props: { current: number; overdue?: boolean; variant?: 'horizontal' | 'vertical' }) {
  return mount(UgcBookingTimeline, { props })
}

describe('UgcBookingTimeline', () => {
  it('renders the six UGC step labels', () => {
    const wrapper = mountTimeline({ current: 3 })

    for (const label of STEP_LABELS) {
      expect(wrapper.text()).toContain(label)
    }
  })

  it('marks steps before current as done with a check icon', () => {
    const wrapper = mountTimeline({ current: 4 })

    const done = wrapper.findAll('[data-step-state="done"]')
    expect(done).toHaveLength(3)
    // Les pastilles done rendent l'icône Check (SVG), pas le numéro d'étape.
    for (const pill of done) {
      expect(pill.find('svg').exists()).toBe(true)
    }
  })

  it('marks the current step as active', () => {
    const wrapper = mountTimeline({ current: 4 })

    const active = wrapper.findAll('[data-step-state="active"]')
    expect(active).toHaveLength(1)
    expect(active[0]!.text()).toContain('4')
    expect(active[0]!.classes()).toContain('border-[#198496]')
  })

  it('marks steps after current as future', () => {
    const wrapper = mountTimeline({ current: 4 })

    const future = wrapper.findAll('[data-step-state="future"]')
    expect(future).toHaveLength(2)
    for (const pill of future) {
      expect(pill.classes()).toContain('border-gray-200')
    }
  })

  it('applies the overdue style to the active step', () => {
    const wrapper = mountTimeline({ current: 5, overdue: true })

    const active = wrapper.find('[data-step-state="active"]')
    expect(active.classes()).toContain('border-red-500')
    expect(active.classes()).not.toContain('border-[#198496]')
  })

  it('renders step descriptions in the vertical variant', () => {
    const wrapper = mountTimeline({ current: 3, variant: 'vertical' })

    expect(wrapper.find('[data-testid="ugc-timeline-v"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="ugc-timeline-h"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Commission WeAct payée')
    expect(wrapper.text()).toContain('Produit reçu — chrono démarre')
    expect(wrapper.text()).toContain('Vidéo 2 sous 14 jours')
  })

  it('marks all six steps as done when current is 7 (tunnel completed)', () => {
    const wrapper = mountTimeline({ current: 7 })

    expect(wrapper.findAll('[data-step-state="done"]')).toHaveLength(6)
    expect(wrapper.findAll('[data-step-state="active"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-step-state="future"]')).toHaveLength(0)
  })

  it('renders a fully neutral timeline when current is 0', () => {
    const wrapper = mountTimeline({ current: 0 })

    expect(wrapper.findAll('[data-step-state="future"]')).toHaveLength(6)
    expect(wrapper.findAll('[data-step-state="done"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-step-state="active"]')).toHaveLength(0)
  })
})
