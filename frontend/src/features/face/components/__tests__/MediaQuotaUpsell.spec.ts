import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MediaQuotaUpsell from '../MediaQuotaUpsell.vue'
import type { SubscriptionOffer, TierCapabilities } from '@/features/face/types'

function caps(p: Partial<TierCapabilities>): TierCapabilities {
  return {
    max_album_photos: 0, max_presentation_videos: 0, max_acting_videos: 0, max_ugc_videos: 0,
    ugc_access: false, commission_rate: 0.1, sort_priority: 0, has_elite_badge: false, ...p,
  }
}
const OFFERS: SubscriptionOffer[] = [
  { tier: 'free', price: 0, currency: 'XOF', capabilities: caps({ max_album_photos: 1 }) },
  { tier: 'starter', price: 12000, currency: 'XOF', capabilities: caps({ max_album_photos: 2, max_presentation_videos: 1 }) },
  { tier: 'pro', price: 25000, currency: 'XOF', capabilities: caps({ max_album_photos: 4, max_presentation_videos: 1, max_acting_videos: 1 }) },
  { tier: 'elite', price: 40000, currency: 'XOF', capabilities: caps({ max_album_photos: 6, max_presentation_videos: 1, max_acting_videos: 2, max_ugc_videos: 1 }) },
]

describe('MediaQuotaUpsell', () => {
  it('intermediate tier: quota line + upsell with the correct next tier', () => {
    const wrapper = mount(MediaQuotaUpsell, {
      props: {
        mediaKey: 'max_acting_videos',
        description: "Démontrez votre talent d'acteur.",
        capabilities: caps({ max_acting_videos: 1 }),
        currentTier: 'pro',
        offers: OFFERS,
      },
    })
    expect(wrapper.find('[data-testid="media-quota-current-max_acting_videos"]').text()).toContain("Ajoutez jusqu'à 1 vidéo d'acting")
    const upsell = wrapper.find('[data-testid="media-quota-upsell-max_acting_videos"]')
    expect(upsell.exists()).toBe(true)
    expect(upsell.text()).toContain('Élite')
    expect(upsell.text()).toContain('2')
    expect(upsell.text()).toContain("vidéos d'acting")
  })

  it('Élite: no upsell line, only the quota line', () => {
    const wrapper = mount(MediaQuotaUpsell, {
      props: {
        mediaKey: 'max_album_photos',
        description: 'Album.',
        capabilities: caps({ max_album_photos: 6 }),
        currentTier: 'elite',
        offers: OFFERS,
      },
    })
    expect(wrapper.find('[data-testid="media-quota-current-max_album_photos"]').text()).toContain("Ajoutez jusqu'à 6 photos")
    expect(wrapper.find('[data-testid="media-quota-upsell-max_album_photos"]').exists()).toBe(false)
  })

  it('locked section (quota 0): no quota line, but upsell still points to the unlocking tier', () => {
    const wrapper = mount(MediaQuotaUpsell, {
      props: {
        mediaKey: 'max_acting_videos',
        description: "Démontrez votre talent d'acteur.",
        capabilities: caps({ max_acting_videos: 0 }),
        currentTier: 'free',
        offers: OFFERS,
      },
    })
    expect(wrapper.find('[data-testid="media-quota-current-max_acting_videos"]').exists()).toBe(false)
    const upsell = wrapper.find('[data-testid="media-quota-upsell-max_acting_videos"]')
    expect(upsell.exists()).toBe(true)
    expect(upsell.text()).toContain('Pro')
    expect(wrapper.text()).toContain("Démontrez votre talent d'acteur.")
  })
})
