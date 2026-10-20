import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import StatusFilter from '../StatusFilter.vue'

describe('StatusFilter (lot D7)', () => {
  it('Face : place la pastille « À traiter » juste après « Tous » (prop with-to-process)', () => {
    const wrapper = mount(StatusFilter, { props: { modelValue: '', withToProcess: true } })

    expect(wrapper.findAll('button').map((b) => b.text()).slice(0, 3)).toEqual(['Tous', 'À traiter', 'En attente'])
  })

  it('Producer : pas de pastille « À traiter » par défaut', () => {
    const wrapper = mount(StatusFilter, { props: { modelValue: '' } })

    expect(wrapper.findAll('button').map((b) => b.text())).not.toContain('À traiter')
  })

  it('émet to_process au clic', async () => {
    const wrapper = mount(StatusFilter, { props: { modelValue: '', withToProcess: true } })

    await wrapper.findAll('button').find((b) => b.text() === 'À traiter')!.trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([['to_process']])
  })
})
