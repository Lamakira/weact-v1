import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { watch } from 'vue'
import PublicMissionFiltersBar from '../PublicMissionFiltersBar.vue'

vi.mock('@vueuse/core', () => ({
  watchDebounced: (
    source: Parameters<typeof watch>[0],
    cb: Parameters<typeof watch>[1],
    _opts?: unknown,
  ) => watch(source, cb),
}))

const missionTypes = [
  { value: 'publicite', label: 'Publicité' },
  { value: 'film', label: 'Film' },
]

function mountFiltersBar(currentFilters = {}) {
  return mount(PublicMissionFiltersBar, {
    props: {
      currentFilters,
      missionTypes,
    },
  })
}

describe('PublicMissionFiltersBar', () => {
  it('does not emit budget filter changes on each keystroke', async () => {
    const wrapper = mountFiltersBar()
    const minInput = wrapper.find('[data-testid="mission-filter-budget-min"]')

    await minInput.setValue('150000')

    expect(wrapper.emitted('filter-change')).toBeFalsy()
  })

  it('emits budget filters when the user commits the value', async () => {
    const wrapper = mountFiltersBar()
    const minInput = wrapper.find('[data-testid="mission-filter-budget-min"]')
    const maxInput = wrapper.find('[data-testid="mission-filter-budget-max"]')

    await minInput.setValue('150000')
    await maxInput.setValue('300000')
    await minInput.trigger('change')
    await maxInput.trigger('change')

    const emitted = wrapper.emitted('filter-change')

    expect(emitted).toBeTruthy()
    expect(emitted?.at(-1)?.[0]).toEqual({
      budget_min: 150000,
      budget_max: 300000,
    })
  })

  it('does not emit the same budget change twice on blur after change', async () => {
    const wrapper = mountFiltersBar()
    const minInput = wrapper.find('[data-testid="mission-filter-budget-min"]')

    await minInput.setValue('150000')
    await minInput.trigger('change')
    await minInput.trigger('blur')

    expect(wrapper.emitted('filter-change')).toHaveLength(1)
  })
})
