import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BookingStepsBar from '../BookingStepsBar.vue'

const labels = ['Demande', 'Acceptation', 'Paiement', 'Tournage', 'Terminé']

describe('BookingStepsBar', () => {
  it('coche toutes les étapes quand le parcours est terminé', () => {
    const wrapper = mount(BookingStepsBar, { props: { labels, current: labels.length } })
    const steps = wrapper.findAll('li')

    expect(steps.map((step) => step.attributes('data-state'))).toEqual(Array(5).fill('done'))
    expect(wrapper.find('[aria-current="step"]').exists()).toBe(false)
    expect(steps[4]?.find('span:last-child').classes()).toContain('font-semibold')
    expect(steps[4]?.find('span:last-child').classes()).not.toContain('max-sm:hidden')
  })

  it("garde l'étape en cours tant que le parcours n'est pas terminé", () => {
    const wrapper = mount(BookingStepsBar, { props: { labels, current: 3 } })

    expect(wrapper.findAll('li')[3]?.attributes('data-state')).toBe('now')
    expect(wrapper.findAll('li')[4]?.attributes('data-state')).toBe('todo')
  })
})
