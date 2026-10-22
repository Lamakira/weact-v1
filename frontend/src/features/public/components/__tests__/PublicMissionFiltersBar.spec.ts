import { afterEach, describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { watch } from 'vue'
import PublicMissionFiltersBar from '../PublicMissionFiltersBar.vue'
import {
  chooseOption,
  cleanupPortals,
  getOptionLabels,
  openSelect,
} from '@/components/ui/__tests__/selectTestUtils'

vi.mock('@vueuse/core', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@vueuse/core')>()),
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
    attachTo: document.body,
    props: {
      currentFilters,
      missionTypes,
    },
  })
}

describe('PublicMissionFiltersBar', () => {
  afterEach(() => {
    cleanupPortals()
  })

  it('liste les types de mission et émet la valeur choisie', async () => {
    const wrapper = mountFiltersBar()
    const trigger = wrapper.get('[data-testid="mission-filter-type"]')
    expect(trigger.text()).toBe('Type de mission')
    expect(trigger.attributes('aria-label')).toBe('Filtrer par type de mission')

    await openSelect(trigger)
    expect(getOptionLabels()).toEqual(['Tous les types', 'Publicité', 'Film'])

    await chooseOption(trigger, 'Film')

    expect(wrapper.emitted('filter-change')?.at(-1)?.[0]).toEqual({ type_mission: 'film' })
    wrapper.unmount()
  })

  it('émet un filtre vide avec « Tous les types »', async () => {
    const wrapper = mountFiltersBar({ type_mission: 'film' })
    const trigger = wrapper.get('[data-testid="mission-filter-type"]')
    expect(trigger.text()).toBe('Film')

    await chooseOption(trigger, 'Tous les types')

    expect(wrapper.emitted('filter-change')?.at(-1)?.[0]).toEqual({})
    wrapper.unmount()
  })

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
