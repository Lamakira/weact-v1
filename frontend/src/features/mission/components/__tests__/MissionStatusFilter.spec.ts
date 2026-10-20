import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MissionStatusFilter from '../MissionStatusFilter.vue'

describe('MissionStatusFilter (lot D7)', () => {
  it('place la pastille « À traiter » juste après « Tous »', () => {
    const wrapper = mount(MissionStatusFilter, { props: { modelValue: '' } })

    expect(wrapper.findAll('button').map((b) => b.text()).slice(0, 3)).toEqual(['Tous', 'À traiter', 'Publiée'])
  })

  it('émet to_process au clic et marque la pastille active', async () => {
    const wrapper = mount(MissionStatusFilter, { props: { modelValue: 'to_process' } })
    const pill = wrapper.findAll('button').find((b) => b.text() === 'À traiter')!

    expect(pill.classes()).toContain('bg-primary')

    await wrapper.findAll('button').find((b) => b.text() === 'Tous')!.trigger('click')
    await pill.trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([[''], ['to_process']])
  })
})
