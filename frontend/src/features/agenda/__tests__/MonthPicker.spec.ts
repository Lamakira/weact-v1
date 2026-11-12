import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MonthPicker from '../components/MonthPicker.vue'

function mountPicker(props: { month?: string; today?: string; variant?: 'popover' | 'sheet' } = {}) {
  return mount(MonthPicker, {
    attachTo: document.body,
    props: { month: '2026-11-01', today: '2026-11-08', variant: 'popover', ...props },
    global: { stubs: { Teleport: { template: '<div><slot /></div>' } } },
  })
}

const option = (iso: string) => `[data-testid="month-option-${iso}"]`

describe('MonthPicker', () => {
  it('affiche l\'année du mois affiché et une grille de 12 mois aux libellés courts', () => {
    const wrapper = mountPicker()
    expect(wrapper.get('[data-testid="month-picker-year"]').text()).toBe('2026')
    const labels = wrapper.findAll('[data-testid^="month-option-"]').map((b) => b.text())
    expect(labels).toEqual(['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'])
    wrapper.unmount()
  })

  it('marque le mois affiché (fond foncé) et le mois courant (contour)', () => {
    const wrapper = mountPicker({ month: '2026-12-01' })
    expect(wrapper.get(option('2026-12-01')).classes()).toContain('bg-slate-900')
    expect(wrapper.get(option('2026-12-01')).classes()).toContain('text-white')
    expect(wrapper.get(option('2026-11-01')).classes()).toContain('ring-1')
    expect(wrapper.get(option('2026-11-01')).classes()).not.toContain('bg-slate-900')
    expect(wrapper.get(option('2026-10-01')).classes()).not.toContain('ring-1')
    wrapper.unmount()
  })

  it('limite les années à l\'année d\'aujourd\'hui moins 1 et plus 1', async () => {
    const wrapper = mountPicker()
    const prev = () => wrapper.get('[data-testid="month-picker-prev-year"]')
    const next = () => wrapper.get('[data-testid="month-picker-next-year"]')
    expect(prev().attributes('disabled')).toBeUndefined()

    await next().trigger('click')
    expect(wrapper.get('[data-testid="month-picker-year"]').text()).toBe('2027')
    expect(next().attributes('disabled')).toBeDefined()

    await prev().trigger('click')
    await prev().trigger('click')
    expect(wrapper.get('[data-testid="month-picker-year"]').text()).toBe('2025')
    expect(prev().attributes('disabled')).toBeDefined()
    wrapper.unmount()
  })

  it('émet le premier jour du mois choisi, dans l\'année affichée', async () => {
    const wrapper = mountPicker()
    await wrapper.get('[data-testid="month-picker-next-year"]').trigger('click')
    await wrapper.get(option('2027-03-01')).trigger('click')
    expect(wrapper.emitted('select')).toEqual([['2027-03-01']])
    wrapper.unmount()
  })

  it('se ferme avec Échap et avec la croix', async () => {
    const wrapper = mountPicker()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(wrapper.emitted('close')).toHaveLength(1)

    await wrapper.get('[data-testid="month-picker-close"]').trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(2)
    wrapper.unmount()
  })

  it('en panneau du bas, un clic sur le fond ferme', async () => {
    const wrapper = mountPicker({ variant: 'sheet' })
    await wrapper.get('[data-testid="month-picker-backdrop"]').trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })
})
