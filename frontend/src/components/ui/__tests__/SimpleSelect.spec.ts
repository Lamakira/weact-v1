import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { SimpleSelect } from '@/components/ui/select'
import { chooseOption, cleanupPortals, getOptionLabels, openSelect } from './selectTestUtils'

const OPTIONS = [
  { value: 'a', label: 'Option A' },
  { value: 10, label: 'Dix' },
]

function mountSelect(modelValue: string | number = '', extra: Record<string, unknown> = {}) {
  return mount(SimpleSelect, {
    attachTo: document.body,
    props: {
      modelValue,
      options: OPTIONS,
      placeholder: 'Choisir',
      emptyLabel: 'Toutes',
      'data-testid': 'simple',
      ...extra,
    },
  })
}

describe('SimpleSelect', () => {
  afterEach(() => {
    cleanupPortals()
  })

  it('affiche le placeholder et porte les attributs sur le déclencheur', () => {
    const wrapper = mountSelect()
    const trigger = wrapper.get('[data-testid="simple"]')

    expect(trigger.text()).toBe('Choisir')
    wrapper.unmount()
  })

  it('liste l’option « vide » en premier puis les options', async () => {
    const wrapper = mountSelect()
    await openSelect(wrapper.get('[data-testid="simple"]'))

    expect(getOptionLabels()).toEqual(['Toutes', 'Option A', 'Dix'])
    wrapper.unmount()
  })

  it('émet la valeur choisie en chaîne', async () => {
    const wrapper = mountSelect()
    await chooseOption(wrapper.get('[data-testid="simple"]'), 'Dix')

    expect(wrapper.emitted('update:modelValue')).toEqual([['10']])
    wrapper.unmount()
  })

  it('émet une chaîne vide pour l’option « vide »', async () => {
    const wrapper = mountSelect('a')
    expect(wrapper.get('[data-testid="simple"]').text()).toBe('Option A')

    await chooseOption(wrapper.get('[data-testid="simple"]'), 'Toutes')

    expect(wrapper.emitted('update:modelValue')).toEqual([['']])
    wrapper.unmount()
  })
})
