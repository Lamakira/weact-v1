import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { MapPin } from 'lucide-vue-next'
import { FloatingSelect } from '@/components/ui/form'
import { chooseOption, cleanupPortals, getOptionLabels, openSelect } from './selectTestUtils'

const OPTIONS = [
  { value: 'Cotonou', label: 'Cotonou' },
  { value: 'Parakou', label: 'Parakou' },
  { value: 4, label: 'Quatre' },
]

function mountSelect(props: Record<string, unknown> = {}) {
  return mount(FloatingSelect, {
    attachTo: document.body,
    props: {
      id: 'ville',
      label: 'Ville',
      modelValue: '',
      options: OPTIONS,
      ...props,
    },
    attrs: { 'data-testid': 'ville-select' },
  })
}

describe('FloatingSelect (API inchangée, Select shadcn à l’intérieur)', () => {
  afterEach(() => {
    cleanupPortals()
  })

  it('affiche l’étiquette, l’astérisque requis, et porte id et data-testid sur le déclencheur', () => {
    const wrapper = mountSelect({ required: true, icon: MapPin })

    const label = wrapper.get('label[for="ville"]')
    expect(label.text()).toContain('Ville')
    expect(label.text()).toContain('*')

    const trigger = wrapper.get('#ville')
    expect(trigger.attributes('data-testid')).toBe('ville-select')
    expect(trigger.attributes('data-slot')).toBe('select-trigger')
    wrapper.unmount()
  })

  it('propose « Sélectionnez » puis les options, et affiche le libellé de la valeur courante', async () => {
    const wrapper = mountSelect({ modelValue: 'Parakou' })
    const trigger = wrapper.get('#ville')
    expect(trigger.text()).toBe('Parakou')

    await openSelect(trigger)

    expect(getOptionLabels()).toEqual(['Sélectionnez', 'Cotonou', 'Parakou', 'Quatre'])
    wrapper.unmount()
  })

  it('avec un placeholder, ne propose plus l’option « Sélectionnez »', async () => {
    const wrapper = mountSelect({ placeholder: 'Choisissez une ville' })

    await openSelect(wrapper.get('#ville'))

    expect(getOptionLabels()).toEqual(['Cotonou', 'Parakou', 'Quatre'])
    wrapper.unmount()
  })

  it('émet update:modelValue avec la valeur choisie (chaîne, comme un select natif)', async () => {
    const wrapper = mountSelect()

    await chooseOption(wrapper.get('#ville'), 'Quatre')

    expect(wrapper.emitted('update:modelValue')).toEqual([['4']])
    wrapper.unmount()
  })

  it('émet une chaîne vide avec « Sélectionnez »', async () => {
    const wrapper = mountSelect({ modelValue: 'Cotonou' })

    await chooseOption(wrapper.get('#ville'), 'Sélectionnez')

    expect(wrapper.emitted('update:modelValue')).toEqual([['']])
    wrapper.unmount()
  })

  it('affiche le message d’erreur et passe en rouge', () => {
    const wrapper = mountSelect({ error: 'La ville est requise.' })

    const error = wrapper.get('[data-testid="ville-error"]')
    expect(error.text()).toBe('La ville est requise.')
    expect(wrapper.get('#ville').classes().join(' ')).toContain('border-red-300')
    wrapper.unmount()
  })

  it('désactivé : le déclencheur est désactivé', () => {
    const wrapper = mountSelect({ disabled: true })

    expect(wrapper.get('#ville').attributes('disabled')).toBeDefined()
    wrapper.unmount()
  })
})
