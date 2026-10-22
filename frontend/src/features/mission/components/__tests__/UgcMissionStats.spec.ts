import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import UgcMissionStats from '../UgcMissionStats.vue'

function mountStats(props: { valeurProduit: number | null; montantRemuneration: number | null; nombreVideos: number | null }) {
  return mount(UgcMissionStats, { props })
}

// toLocaleString('fr-FR') insère U+202F — normaliser en espace simple (leçon 1.6/2.2)
function normalizedText(wrapper: ReturnType<typeof mountStats>): string {
  return wrapper.text().replace(/\s/g, ' ')
}

describe('UgcMissionStats', () => {
  it('renders Produit + Vidéos without Cash for a product-only mission (2-column grid)', () => {
    const wrapper = mountStats({ valeurProduit: 35000, montantRemuneration: null, nombreVideos: 2 })

    expect(wrapper.find('[data-testid="ugc-mission-stats"]').classes()).toContain('grid-cols-2')
    expect(wrapper.find('[data-testid="ugc-stat-cash"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Produit')
    expect(wrapper.text()).toContain('Vidéos')
  })

  it('renders the 3 cells for a hybrid mission (3-column grid)', () => {
    const wrapper = mountStats({ valeurProduit: 35000, montantRemuneration: 20000, nombreVideos: 3 })

    expect(wrapper.find('[data-testid="ugc-mission-stats"]').classes()).toContain('grid-cols-3')
    expect(wrapper.find('[data-testid="ugc-stat-cash"]').exists()).toBe(true)
    expect(normalizedText(wrapper)).toContain('20 000 F CFA')
  })

  it('formats amounts as fr-FR F CFA', () => {
    const wrapper = mountStats({ valeurProduit: 35000, montantRemuneration: null, nombreVideos: 2 })

    expect(normalizedText(wrapper)).toContain('35 000 F CFA')
  })

  it('falls back to 0 F CFA when valeur_produit is null (defensive)', () => {
    const wrapper = mountStats({ valeurProduit: null, montantRemuneration: null, nombreVideos: 2 })

    expect(normalizedText(wrapper)).toContain('0 F CFA')
  })

  it('renders nombre_videos in the Vidéos cell', () => {
    const wrapper = mountStats({ valeurProduit: 35000, montantRemuneration: null, nombreVideos: 4 })

    expect(wrapper.text()).toContain('4')
  })
})
