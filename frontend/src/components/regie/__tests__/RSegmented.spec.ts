import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import RSegmented from '../RSegmented.vue'

const options = [
  { value: 'candidatures', label: 'Candidatures' },
  { value: 'bookings', label: 'Bookings' },
  { value: 'missions', label: 'Missions' },
]

function mountSeg(modelValue = 'candidatures') {
  return mount(RSegmented, {
    props: { options, groupLabel: 'Type', modelValue, 'onUpdate:modelValue': (v: string) => wrapper.setProps({ modelValue: v }) },
    attachTo: document.body,
  })
}
let wrapper: ReturnType<typeof mountSeg>

describe('RSegmented', () => {
  it('a la sémantique radiogroup / radio avec tabindex itinérant', () => {
    wrapper = mountSeg('bookings')
    expect(wrapper.attributes('role')).toBe('radiogroup')
    expect(wrapper.attributes('aria-label')).toBe('Type')
    const radios = wrapper.findAll('[role="radio"]')
    expect(radios.map((r) => r.attributes('aria-checked'))).toEqual(['false', 'true', 'false'])
    expect(radios.map((r) => r.attributes('tabindex'))).toEqual(['-1', '0', '-1'])
    wrapper.unmount()
  })

  it('un clic sélectionne', async () => {
    wrapper = mountSeg()
    await wrapper.findAll('[role="radio"]')[1]!.trigger('click')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['bookings'])
    wrapper.unmount()
  })

  it('les flèches déplacent la sélection et le focus, avec rebouclage', async () => {
    wrapper = mountSeg()
    const radios = () => wrapper.findAll('[role="radio"]')
    await radios()[0]!.trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.props('modelValue')).toBe('bookings')
    expect(document.activeElement).toBe(radios()[1]!.element)

    await radios()[1]!.trigger('keydown', { key: 'ArrowLeft' })
    expect(wrapper.props('modelValue')).toBe('candidatures')

    await radios()[0]!.trigger('keydown', { key: 'ArrowLeft' })
    expect(wrapper.props('modelValue')).toBe('missions')

    await radios()[2]!.trigger('keydown', { key: 'ArrowDown' })
    expect(wrapper.props('modelValue')).toBe('candidatures')
    wrapper.unmount()
  })

  it('Home et End vont aux extrémités', async () => {
    wrapper = mountSeg('bookings')
    await wrapper.findAll('[role="radio"]')[1]!.trigger('keydown', { key: 'End' })
    expect(wrapper.props('modelValue')).toBe('missions')
    await wrapper.findAll('[role="radio"]')[2]!.trigger('keydown', { key: 'Home' })
    expect(wrapper.props('modelValue')).toBe('candidatures')
    wrapper.unmount()
  })
})
