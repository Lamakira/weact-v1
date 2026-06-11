import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import UgcDeliverablesPreview from '../UgcDeliverablesPreview.vue'
import { ChronoRing } from '@/components/ugc'

function mountPreview(nombreVideos: number | null) {
  return mount(UgcDeliverablesPreview, { props: { nombreVideos } })
}

describe('UgcDeliverablesPreview', () => {
  it('renders the two canonical deliverables with their windows', () => {
    const wrapper = mountPreview(2)

    expect(wrapper.text()).toContain('1. Vidéo Unboxing')
    expect(wrapper.text()).toContain('sous 7 jours')
    expect(wrapper.text()).toContain('2. Vidéo Avis')
    expect(wrapper.text()).toContain('sous 14 jours')
  })

  it('renders two ChronoRing at progress 0', () => {
    const wrapper = mountPreview(2)

    const rings = wrapper.findAllComponents(ChronoRing)
    expect(rings).toHaveLength(2)
    expect(rings[0]!.props('progress')).toBe(0)
    expect(rings[1]!.props('progress')).toBe(0)
  })

  it('mentions product reception as the chrono trigger in the header', () => {
    const wrapper = mountPreview(2)

    expect(wrapper.text()).toContain('réception du produit')
  })

  it('shows the extras line when nombre_videos > 2', () => {
    const wrapper = mountPreview(4)

    const extras = wrapper.find('[data-testid="ugc-extra-videos"]')
    expect(extras.exists()).toBe(true)
    expect(extras.text()).toContain('+ 2 vidéos supplémentaires')
  })

  it('hides the extras line for 2 videos and for null', () => {
    expect(mountPreview(2).find('[data-testid="ugc-extra-videos"]').exists()).toBe(false)
    expect(mountPreview(null).find('[data-testid="ugc-extra-videos"]').exists()).toBe(false)
  })
})
