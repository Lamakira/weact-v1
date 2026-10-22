import { afterEach, describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { SearchableSelect } from '@/components/ui/combobox'
import { cleanupPortals, clickOption, getOptionLabels, openSelect } from './selectTestUtils'

const CITIES = [
  { value: 'Cotonou', label: 'Cotonou' },
  { value: 'Porto-Novo', label: 'Porto-Novo' },
  { value: 'Parakou', label: 'Parakou' },
  { value: 'Sèmè-Kpodji', label: 'Sèmè-Kpodji' },
]

function mountCombobox(modelValue = '') {
  return mount(SearchableSelect, {
    attachTo: document.body,
    props: {
      modelValue,
      options: CITIES,
      placeholder: 'Ville',
      emptyLabel: 'Toutes les villes',
      searchPlaceholder: 'Rechercher une ville…',
      emptyText: 'Aucune ville trouvée',
      'data-testid': 'filter-city',
      'aria-label': 'Filtrer par ville',
    },
  })
}

function searchInput(): HTMLInputElement {
  return document.body.querySelector('input[placeholder="Rechercher une ville…"]') as HTMLInputElement
}

async function typeSearch(value: string): Promise<void> {
  const input = searchInput()
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

describe('SearchableSelect (Combobox de ville)', () => {
  afterEach(() => {
    cleanupPortals()
  })

  it('affiche le placeholder puis liste toutes les options à l’ouverture', async () => {
    const wrapper = mountCombobox()
    const trigger = wrapper.get('[data-testid="filter-city"]')

    expect(trigger.text()).toBe('Ville')
    expect(trigger.attributes('aria-label')).toBe('Filtrer par ville')

    await openSelect(trigger)

    expect(getOptionLabels()).toEqual(['Toutes les villes', 'Cotonou', 'Porto-Novo', 'Parakou', 'Sèmè-Kpodji'])
    wrapper.unmount()
  })

  it('filtre les options par saisie', async () => {
    const wrapper = mountCombobox()
    await openSelect(wrapper.get('[data-testid="filter-city"]'))

    await typeSearch('par')

    expect(getOptionLabels()).toEqual(['Parakou'])
    wrapper.unmount()
  })

  it('affiche l’état vide quand aucune ville ne correspond', async () => {
    const wrapper = mountCombobox()
    await openSelect(wrapper.get('[data-testid="filter-city"]'))

    await typeSearch('zzz')

    expect(getOptionLabels()).toEqual([])
    expect(document.body.textContent).toContain('Aucune ville trouvée')
    wrapper.unmount()
  })

  it('émet la valeur de la ville choisie', async () => {
    const wrapper = mountCombobox()
    await openSelect(wrapper.get('[data-testid="filter-city"]'))

    await clickOption('Porto-Novo')

    expect(wrapper.emitted('update:modelValue')).toEqual([['Porto-Novo']])
    wrapper.unmount()
  })

  it('émet une chaîne vide avec « Toutes les villes »', async () => {
    const wrapper = mountCombobox('Cotonou')
    const trigger = wrapper.get('[data-testid="filter-city"]')
    expect(trigger.text()).toBe('Cotonou')

    await openSelect(trigger)
    await clickOption('Toutes les villes')

    expect(wrapper.emitted('update:modelValue')).toEqual([['']])
    wrapper.unmount()
  })
})
