import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { Crown } from 'lucide-vue-next'
import TierCard from '../TierCard.vue'
import { buildTierFeatureLines } from '@/features/face/tierPresentation'
import type { FaceSubscriptionTier, SubscriptionOffer, TierCapabilities } from '../../types'

const CAPABILITIES: Record<FaceSubscriptionTier, TierCapabilities> = {
  free: {
    max_album_photos: 1,
    max_presentation_videos: 0,
    max_acting_videos: 0,
    max_ugc_videos: 0,
    ugc_access: false,
    commission_rate: 0.1,
    sort_priority: 4,
    has_elite_badge: false,
  },
  starter: {
    max_album_photos: 2,
    max_presentation_videos: 1,
    max_acting_videos: 0,
    max_ugc_videos: 0,
    ugc_access: true,
    commission_rate: 0.1,
    sort_priority: 3,
    has_elite_badge: false,
  },
  pro: {
    max_album_photos: 4,
    max_presentation_videos: 1,
    max_acting_videos: 1,
    max_ugc_videos: 0,
    ugc_access: true,
    commission_rate: 0.1,
    sort_priority: 2,
    has_elite_badge: false,
  },
  elite: {
    max_album_photos: 6,
    max_presentation_videos: 1,
    max_acting_videos: 2,
    max_ugc_videos: 1,
    ugc_access: true,
    commission_rate: 0.05,
    sort_priority: 1,
    has_elite_badge: true,
  },
}

const PRICES: Record<FaceSubscriptionTier, number> = {
  free: 0,
  starter: 12000,
  pro: 25000,
  elite: 40000,
}

function baseOffer(tier: FaceSubscriptionTier): SubscriptionOffer {
  return { tier, price: PRICES[tier], currency: 'XOF', capabilities: CAPABILITIES[tier] }
}

describe('TierCard', () => {
  it('renders the tier name, tagline and formatted price', () => {
    const wrapper = mount(TierCard, {
      props: { offer: baseOffer('pro'), relation: 'upgrade', ctaEnabled: true, ladderIndex: 2 },
    })

    expect(wrapper.text()).toContain('Pro')
    expect(wrapper.text()).toContain('Acting + UGC, le combo sérieux')
    expect(wrapper.find('[data-testid="tier-card-price"]').text()).toBe(
      new Intl.NumberFormat('fr-FR').format(25000),
    )
    expect(wrapper.text()).toContain('F CFA')
  })

  it('renders "Gratuit" and no "F CFA" suffix for the free offer', () => {
    const wrapper = mount(TierCard, {
      props: { offer: baseOffer('free'), relation: 'current', ctaEnabled: false, ladderIndex: 0 },
    })

    expect(wrapper.find('[data-testid="tier-card-price"]').text()).toBe('Gratuit')
    expect(wrapper.text()).not.toContain('F CFA')
  })

  it('renders the "Populaire" pill on the Pro card', () => {
    const wrapper = mount(TierCard, {
      props: { offer: baseOffer('pro'), relation: 'upgrade', ctaEnabled: true, ladderIndex: 2 },
    })

    expect(wrapper.text()).toContain('Populaire')
  })

  it('renders the Crown icon and the dark variant on the Élite card', () => {
    const wrapper = mount(TierCard, {
      props: { offer: baseOffer('elite'), relation: 'upgrade', ctaEnabled: true, ladderIndex: 3 },
    })

    expect(wrapper.findComponent(Crown).exists()).toBe(true)
    expect(wrapper.get('[data-testid="tier-card-elite"]').classes()).toContain('bg-[#0F1419]')
  })

  it('highlights the current tier with a teal ring and a "Palier actuel" marker', () => {
    const wrapper = mount(TierCard, {
      props: { offer: baseOffer('pro'), relation: 'current', ctaEnabled: false, ladderIndex: 2 },
    })

    expect(wrapper.get('[data-testid="tier-card-pro"]').classes()).toContain('ring-2')
    expect(wrapper.find('[data-testid="tier-card-current-marker"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Palier actuel')
  })

  it('labels the CTA "Passer à …" for an upgrade relation', () => {
    const wrapper = mount(TierCard, {
      props: { offer: baseOffer('elite'), relation: 'upgrade', ctaEnabled: true, ladderIndex: 3 },
    })

    expect(wrapper.get('[data-testid="tier-card-cta-elite"]').text()).toBe('Passer à Élite')
  })

  it('labels the CTA "Revenir à …" for a downgrade relation', () => {
    const wrapper = mount(TierCard, {
      props: { offer: baseOffer('starter'), relation: 'downgrade', ctaEnabled: true, ladderIndex: 1 },
    })

    expect(wrapper.get('[data-testid="tier-card-cta-starter"]').text()).toBe('Revenir à Starter')
  })

  it('renders no CTA for an unavailable relation (free card while on a paid tier)', () => {
    const wrapper = mount(TierCard, {
      props: { offer: baseOffer('free'), relation: 'unavailable', ctaEnabled: false, ladderIndex: 0 },
    })

    expect(wrapper.find('[data-testid="tier-card-cta-free"]').exists()).toBe(false)
  })

  it('emits select only when the CTA is enabled', async () => {
    const disabled = mount(TierCard, {
      props: { offer: baseOffer('pro'), relation: 'upgrade', ctaEnabled: false, ladderIndex: 2 },
    })
    await disabled.get('[data-testid="tier-card-cta-pro"]').trigger('click')
    expect(disabled.emitted('select')).toBeFalsy()

    const enabled = mount(TierCard, {
      props: { offer: baseOffer('pro'), relation: 'upgrade', ctaEnabled: true, ladderIndex: 2 },
    })
    await enabled.get('[data-testid="tier-card-cta-pro"]').trigger('click')
    expect(enabled.emitted('select')).toBeTruthy()
  })

  describe('buildTierFeatureLines', () => {
    it('builds the full Élite feature list with the two highlighted entries', () => {
      const lines = buildTierFeatureLines('elite', CAPABILITIES.elite)
      const texts = lines.map((l) => l.text)

      expect(texts).toContain('6 photos dans la galerie')
      expect(texts).toContain('1 vidéo de présentation')
      expect(texts).toContain('2 vidéos Acting')
      expect(texts).toContain('1 vidéo modèle UGC')
      expect(texts).toContain('Accès complet au module UGC')
      expect(texts).toContain('Mise en avant Prioritaire')
      expect(texts).toContain('Commission réduite à 5% (au lieu de 10%)')
      expect(texts).toContain('Badge VIP / Élite sur le profil')
      expect(lines.filter((l) => l.highlight)).toHaveLength(2)
    })

    it('builds a minimal free feature list with a singular photo line', () => {
      const lines = buildTierFeatureLines('free', CAPABILITIES.free)
      const texts = lines.map((l) => l.text)

      expect(texts).toContain('1 photo dans la galerie')
      expect(texts).toContain('Mise en avant Standard')
      expect(lines.filter((l) => l.highlight)).toHaveLength(0)
    })
  })
})
