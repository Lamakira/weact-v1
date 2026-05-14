import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SubscriptionCard from '../SubscriptionCard.vue'
import type { SubscriptionStatus, SubscriptionPaymentState } from '../../types'

interface PropsOverrides {
  status?: SubscriptionStatus
  isPremium?: boolean
  expiresAt?: string | null
  albumUploadLimit?: number
  publicAlbumPhotoLimit?: number
  currentAlbumPhotoCount?: number
  lockedAlbumPhotoCount?: number
  hasActingVideo?: boolean
  canUploadActingVideo?: boolean
  isActingVideoPubliclyVisible?: boolean
  canRenew?: boolean
  planAmount?: number
  planCurrency?: string
  planIsAvailable?: boolean
  isLoading?: boolean
  isInitiating?: boolean
  isPolling?: boolean
  paymentState?: SubscriptionPaymentState
  paymentError?: string | null
}

const baseProps = (overrides: PropsOverrides = {}) => ({
  status: 'free' as SubscriptionStatus,
  isPremium: false,
  expiresAt: null,
  albumUploadLimit: 2,
  publicAlbumPhotoLimit: 2,
  currentAlbumPhotoCount: 0,
  lockedAlbumPhotoCount: 0,
  hasActingVideo: false,
  canUploadActingVideo: false,
  isActingVideoPubliclyVisible: false,
  canRenew: true,
  planAmount: 50000,
  planCurrency: 'XOF',
  planIsAvailable: true,
  isLoading: false,
  isInitiating: false,
  isPolling: false,
  paymentState: 'idle' as SubscriptionPaymentState,
  paymentError: null,
  ...overrides,
})

describe('SubscriptionCard', () => {
  it('renders Gratuit badge, price line, and "Passer en Premium" CTA in free state', () => {
    const wrapper = mount(SubscriptionCard, { props: baseProps() })

    expect(wrapper.find('[data-testid="subscription-status-badge"]').text()).toBe('Gratuit')
    expect(wrapper.find('[data-testid="subscription-cta"]').text()).toContain('Passer en Premium')
    // fr-FR Intl currency may render as "F CFA" (JSDom locale data) or "XOF" depending on ICU.
    expect(wrapper.text()).toContain('50')
    expect(wrapper.text()).toContain('000')
    expect(wrapper.text()).toMatch(/F\s*CFA|XOF/)
  })

  it('emits initiate-payment when free-tier CTA is clicked', async () => {
    const wrapper = mount(SubscriptionCard, { props: baseProps() })

    await wrapper.find('[data-testid="subscription-cta"]').trigger('click')
    expect(wrapper.emitted('initiate-payment')).toBeTruthy()
  })

  it('renders quota summary from entitlement props', () => {
    const wrapper = mount(SubscriptionCard, {
      props: baseProps({
        albumUploadLimit: 4,
        publicAlbumPhotoLimit: 2,
        currentAlbumPhotoCount: 4,
        lockedAlbumPhotoCount: 2,
        hasActingVideo: true,
        isActingVideoPubliclyVisible: false,
      }),
    })

    expect(wrapper.text()).toContain('4/4 photos')
    expect(wrapper.text()).toContain('2 max')
    expect(wrapper.text()).toContain('2 privées')
    expect(wrapper.text()).toContain('Privée')
  })

  it('disables CTA and does not emit when email verification blocks payment initiation', async () => {
    const wrapper = mount(SubscriptionCard, {
      props: baseProps({ canInitiatePayment: false }),
    })

    const cta = wrapper.find('[data-testid="subscription-cta"]')
    expect(cta.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Vérifiez votre email pour activer le paiement Premium.')

    await cta.trigger('click')
    expect(wrapper.emitted('initiate-payment')).toBeFalsy()
  })

  it('renders pending_payment branch with refresh button and no CTA', async () => {
    const wrapper = mount(
      SubscriptionCard,
      { props: baseProps({ status: 'pending_payment' }) },
    )

    expect(wrapper.find('[data-testid="subscription-status-badge"]').text()).toBe('Paiement en attente')
    expect(wrapper.find('[data-testid="subscription-cta"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="subscription-refresh"]').exists()).toBe(true)

    await wrapper.find('[data-testid="subscription-refresh"]').trigger('click')
    expect(wrapper.emitted('refresh-status')).toBeTruthy()
  })

  it('renders active premium with expiry date and no CTA', () => {
    const wrapper = mount(SubscriptionCard, {
      props: baseProps({
        status: 'active',
        isPremium: true,
        expiresAt: '2027-05-12T10:00:00Z',
        albumUploadLimit: 4,
        publicAlbumPhotoLimit: 4,
        canUploadActingVideo: true,
      }),
    })

    expect(wrapper.find('[data-testid="subscription-status-badge"]').text()).toBe('Premium actif')
    const expiry = wrapper.find('[data-testid="subscription-expires-at"]')
    expect(expiry.exists()).toBe(true)
    expect(expiry.text()).toContain('mai 2027')
    expect(wrapper.find('[data-testid="subscription-cta"]').exists()).toBe(false)
  })

  it('renders expired with "Renouveler en Premium" CTA', async () => {
    const wrapper = mount(SubscriptionCard, { props: baseProps({ status: 'expired' }) })

    expect(wrapper.find('[data-testid="subscription-status-badge"]').text()).toBe('Expiré')
    const cta = wrapper.find('[data-testid="subscription-cta"]')
    expect(cta.text()).toContain('Renouveler en Premium')

    await cta.trigger('click')
    expect(wrapper.emitted('initiate-payment')).toBeTruthy()
  })

  it('renders cancelled with "Renouveler en Premium" CTA', () => {
    const wrapper = mount(SubscriptionCard, { props: baseProps({ status: 'cancelled' }) })

    expect(wrapper.find('[data-testid="subscription-status-badge"]').text()).toBe('Annulé')
    expect(wrapper.find('[data-testid="subscription-cta"]').text()).toContain('Renouveler en Premium')
  })

  it('renders failed with "Réessayer le paiement" CTA', () => {
    const wrapper = mount(SubscriptionCard, { props: baseProps({ status: 'failed' }) })

    expect(wrapper.find('[data-testid="subscription-status-badge"]').text()).toBe('Paiement échoué')
    expect(wrapper.find('[data-testid="subscription-cta"]').text()).toContain('Réessayer le paiement')
  })

  it('renders skeleton when isLoading=true and hides badge/CTA', () => {
    const wrapper = mount(SubscriptionCard, { props: baseProps({ isLoading: true }) })

    expect(wrapper.find('[data-testid="subscription-card-loading"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="subscription-status-badge"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="subscription-cta"]').exists()).toBe(false)
  })

  it('renders payment error banner and emits dismiss-error on close', async () => {
    const wrapper = mount(
      SubscriptionCard,
      { props: baseProps({ paymentError: 'Boom' }) },
    )

    const banner = wrapper.find('[data-testid="subscription-payment-error"]')
    expect(banner.exists()).toBe(true)
    expect(banner.text()).toContain('Boom')

    await banner.find('button').trigger('click')
    expect(wrapper.emitted('dismiss-error')).toBeTruthy()
  })

  it('disables CTA + does not emit when planIsAvailable=false', async () => {
    const wrapper = mount(
      SubscriptionCard,
      { props: baseProps({ planIsAvailable: false, planAmount: 0 }) },
    )

    const cta = wrapper.find('[data-testid="subscription-cta"]')
    expect(cta.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain("L'abonnement annuel n'est pas disponible pour le moment.")

    await cta.trigger('click')
    expect(wrapper.emitted('initiate-payment')).toBeFalsy()
  })

  it('formats price in fr-FR XOF with non-breaking space tolerance', () => {
    const wrapper = mount(
      SubscriptionCard,
      { props: baseProps({ planAmount: 75000 }) },
    )

    const text = wrapper.text()
    expect(text).toContain('75')
    expect(text).toContain('000')
    expect(text).toMatch(/F\s*CFA|XOF/)
  })
})
