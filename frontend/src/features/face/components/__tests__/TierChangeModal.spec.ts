import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import TierChangeModal from '../TierChangeModal.vue'
import type { FaceSubscriptionTier, SubscriptionOffer, TierCapabilities } from '../../types'

const PRO_CAPS: TierCapabilities = {
  max_album_photos: 4,
  max_presentation_videos: 1,
  max_acting_videos: 1,
  max_ugc_videos: 0,
  ugc_access: true,
  commission_rate: 0.1,
  sort_priority: 2,
  has_elite_badge: false,
}

function offer(tier: FaceSubscriptionTier = 'pro', price = 25000): SubscriptionOffer {
  return { tier, price, currency: 'XOF', capabilities: PRO_CAPS }
}

type ModalProps = {
  open: boolean
  mode: 'activate' | 'renew' | 'upgrade' | 'downgrade'
  targetOffer: SubscriptionOffer
  currentTierLabel: string
  forfeitedDays: number
  isSubmitting: boolean
}

function mountModal(overrides: Partial<ModalProps> = {}) {
  const props: ModalProps = {
    open: true,
    mode: 'upgrade',
    targetOffer: offer(),
    currentTierLabel: 'Starter',
    forfeitedDays: 0,
    isSubmitting: false,
    ...overrides,
  }
  return mount(TierChangeModal, {
    props,
    global: { stubs: { teleport: true } },
  })
}

describe('TierChangeModal', () => {
  it('renders nothing when open is false', () => {
    const wrapper = mountModal({ open: false })
    expect(wrapper.find('[data-testid="tier-change-modal"]').exists()).toBe(false)
  })

  it('renders the dialog with the target tier name and price when open', () => {
    const wrapper = mountModal({ open: true })
    expect(wrapper.find('[data-testid="tier-change-modal"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Pro')
    expect(wrapper.find('[data-testid="tier-change-price"]').text()).toContain(
      new Intl.NumberFormat('fr-FR').format(25000),
    )
    expect(wrapper.text()).toContain('12 mois')
  })

  it('renders the right title for each mode', () => {
    expect(mountModal({ mode: 'activate' }).text()).toContain('Souscrire à Pro')
    expect(mountModal({ mode: 'renew' }).text()).toContain('Renouveler Pro')
    expect(mountModal({ mode: 'upgrade' }).text()).toContain('Passer à Pro')
    expect(mountModal({ mode: 'downgrade' }).text()).toContain('Revenir à Pro')
  })

  it('hides the loss-of-days warning when forfeitedDays is 0', () => {
    const wrapper = mountModal({ forfeitedDays: 0 })
    expect(wrapper.find('[data-testid="tier-change-forfeit-warning"]').exists()).toBe(false)
  })

  it('shows the loss-of-days warning with correct plural for 12 days', () => {
    const wrapper = mountModal({ forfeitedDays: 12, currentTierLabel: 'Starter' })
    const warning = wrapper.find('[data-testid="tier-change-forfeit-warning"]')
    expect(warning.exists()).toBe(true)
    expect(warning.text()).toContain('12 jours restants')
    expect(warning.text()).toContain('Starter')
  })

  it('shows the loss-of-days warning with correct singular for 1 day', () => {
    const wrapper = mountModal({ forfeitedDays: 1 })
    const warning = wrapper.find('[data-testid="tier-change-forfeit-warning"]')
    expect(warning.text()).toContain('1 jour restant')
    expect(warning.text()).not.toContain('1 jours')
  })

  it('emits confirm on Confirmer and cancel on Annuler / backdrop / Escape', async () => {
    const wrapper = mountModal()

    await wrapper.find('[data-testid="tier-change-confirm"]').trigger('click')
    expect(wrapper.emitted('confirm')).toBeTruthy()

    await wrapper.find('[data-testid="tier-change-cancel"]').trigger('click')
    await wrapper.find('[data-testid="tier-change-backdrop"]').trigger('click')
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    expect(wrapper.emitted('cancel')).toHaveLength(3)
  })

  it('disables the confirm button and suppresses cancel while isSubmitting', async () => {
    const wrapper = mountModal({ isSubmitting: true })

    expect(
      wrapper.find('[data-testid="tier-change-confirm"]').attributes('disabled'),
    ).toBeDefined()

    await wrapper.find('[data-testid="tier-change-cancel"]').trigger('click')
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    expect(wrapper.emitted('cancel')).toBeFalsy()
  })
})
