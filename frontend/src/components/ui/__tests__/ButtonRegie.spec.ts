import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { Button } from '@/components/ui/button'

describe('Button variantes Régie', () => {
  it('regie : teal 600, 32 px desktop / 40 px tactile', () => {
    const wrapper = mount(Button, { props: { variant: 'regie', size: 'regie' }, slots: { default: 'Publier' } })
    const classes = wrapper.classes()
    expect(classes).toEqual(expect.arrayContaining(['bg-weact-600', 'text-white', 'h-10', 'md:h-8']))
  })

  it('regie-secondary : blanc avec anneau 1 px', () => {
    const wrapper = mount(Button, { props: { variant: 'regie-secondary', size: 'regie' }, slots: { default: 'Modifier' } })
    expect(wrapper.classes()).toEqual(expect.arrayContaining(['bg-white', 'ring-1', 'ring-line']))
  })

  it('ne change pas la variante par défaut', () => {
    const wrapper = mount(Button, { slots: { default: 'OK' } })
    expect(wrapper.classes()).toContain('bg-primary')
    expect(wrapper.classes()).toContain('h-9')
  })
})
