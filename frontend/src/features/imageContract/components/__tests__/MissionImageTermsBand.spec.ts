import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MissionImageTermsBand from '../MissionImageTermsBand.vue'
import type { MissionImageTerms } from '../../types'

const terms: MissionImageTerms = {
  finalite: 'Publicité pour la marque Shade',
  supports: [{ value: 'social', label: 'Réseaux sociaux' }, { value: 'web', label: 'Site web' }],
  territoire: { value: 'west_africa', label: "Afrique de l'Ouest" },
  duree_annees: 3,
  producer_signed_at: '2026-11-04T10:00:00Z',
  producer_signed_name: 'Jean Producteur',
}

describe('MissionImageTermsBand', () => {
  it('affiche la date réelle de signature et les quatre conditions', () => {
    const wrapper = mount(MissionImageTermsBand, { props: { terms } })

    expect(wrapper.get('[data-testid="mission-image-terms-band-title"]').text()).toBe('Conditions signées le 4 nov.')
    expect(wrapper.get('[data-testid="band-finalite"]').text()).toBe('Publicité pour la marque Shade')
    expect(wrapper.get('[data-testid="band-supports"]').text()).toBe('Réseaux sociaux, Site web')
    expect(wrapper.get('[data-testid="band-territoire"]').text()).toBe("Afrique de l'Ouest")
    expect(wrapper.get('[data-testid="band-duree"]').text()).toBe('3 ans')
  })

  it('2 colonnes sur téléphone, 4 sur ordinateur', () => {
    const wrapper = mount(MissionImageTermsBand, { props: { terms } })

    const grid = wrapper.get('dl')
    expect(grid.classes()).toEqual(expect.arrayContaining(['grid-cols-2', 'md:grid-cols-4']))
  })

  it('sans date de signature : titre sans date', () => {
    const wrapper = mount(MissionImageTermsBand, { props: { terms: { ...terms, producer_signed_at: null } } })

    expect(wrapper.get('[data-testid="mission-image-terms-band-title"]').text()).toBe('Conditions signées')
  })

  it.each([null, undefined])('n\'affiche rien sans conditions (%s)', (value) => {
    const wrapper = mount(MissionImageTermsBand, { props: { terms: value } })

    expect(wrapper.find('[data-testid="mission-image-terms-band"]').exists()).toBe(false)
  })
})
