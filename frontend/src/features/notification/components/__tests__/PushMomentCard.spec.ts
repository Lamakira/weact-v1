import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const mockSupport = vi.fn()
const mockFetchKey = vi.fn()
const mockExisting = vi.fn()
const mockSubscribe = vi.fn()
vi.mock('../../push/webPush', () => ({
  getPushSupport: () => mockSupport(),
  fetchVapidPublicKey: () => mockFetchKey(),
  getExistingSubscription: () => mockExisting(),
  subscribeThisDevice: () => mockSubscribe(),
  unsubscribeThisDevice: vi.fn(),
  syncSubscriptionToServer: vi.fn().mockResolvedValue(true),
}))

vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ user: { id: 42 }, isEmailVerified: true }) }))
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() }),
}))

import PushMomentCard from '../PushMomentCard.vue'
import { resetWebPushState } from '../../push/useWebPush'

const CARD = '[data-testid="push-moment-card"]'

function setPermission(permission: NotificationPermission): void {
  ;(window as unknown as Record<string, unknown>).Notification = { permission }
}

async function mountCard(props: Record<string, unknown> = {}) {
  const wrapper = mount(PushMomentCard, {
    props: { producerName: 'Agence Lumière', scope: 'booking', scopeId: 'b-1', ...props },
  })
  await flushPromises()
  return wrapper
}

describe('PushMomentCard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    resetWebPushState()
    mockSupport.mockReturnValue('supported')
    mockFetchKey.mockResolvedValue('public-key')
    mockExisting.mockResolvedValue(null)
    mockSubscribe.mockResolvedValue('enabled')
    setPermission('default')
  })

  it('visible quand les notifications sont désactivées, sans demander la permission seule', async () => {
    const wrapper = await mountCard()

    const card = wrapper.get(CARD)
    expect(card.text()).toContain('Ne ratez pas les messages de Agence Lumière')
    expect(card.text()).toContain(
      "Activez les notifications sur ce téléphone pour voir ses messages dès qu'ils arrivent.",
    )
    expect(mockSubscribe).not.toHaveBeenCalled()
  })

  it('repli « du Producteur » sans nom (booking/mission)', async () => {
    const wrapper = await mountCard({ producerName: null, scope: 'mission' })

    expect(wrapper.get(CARD).text()).toContain('Ne ratez pas les messages du Producteur')
  })

  it('formulation candidature, avec et sans nom', async () => {
    const named = await mountCard({ scope: 'candidature', scopeId: 'm-1' })
    expect(named.get(CARD).text()).toContain('Suivez la réponse de Agence Lumière')
    expect(named.get(CARD).text()).toContain(
      "Activez les notifications sur ce téléphone pour voir sa réponse dès qu'elle arrive.",
    )

    const anonymous = await mountCard({ producerName: null, scope: 'candidature', scopeId: 'm-1' })
    expect(anonymous.get(CARD).text()).toContain('Suivez la réponse du Producteur')
  })

  it('masquée quand les notifications sont actives, refusées, non supportées ou indisponibles', async () => {
    setPermission('granted')
    mockExisting.mockResolvedValue({ endpoint: 'https://push.example/x' })
    expect((await mountCard()).find(CARD).exists()).toBe(false)

    resetWebPushState()
    setPermission('denied')
    expect((await mountCard()).find(CARD).exists()).toBe(false)

    resetWebPushState()
    setPermission('default')
    mockSupport.mockReturnValue('unsupported')
    expect((await mountCard()).find(CARD).exists()).toBe(false)

    resetWebPushState()
    mockSupport.mockReturnValue('supported')
    mockFetchKey.mockResolvedValue(null)
    expect((await mountCard()).find(CARD).exists()).toBe(false)
  })

  it('« Plus tard » masque la carte pour cette clé seulement', async () => {
    const wrapper = await mountCard()

    await wrapper.get('[data-testid="push-moment-later"]').trigger('click')

    expect(wrapper.find(CARD).exists()).toBe(false)
    expect(localStorage.getItem('weact.push.moment.booking.b-1.42')).toBe('1')
    expect(mockSubscribe).not.toHaveBeenCalled()

    expect((await mountCard()).find(CARD).exists()).toBe(false)
    // Autre booking, autre mission : la carte revient.
    expect((await mountCard({ scopeId: 'b-2' })).find(CARD).exists()).toBe(true)
    expect((await mountCard({ scope: 'mission' })).find(CARD).exists()).toBe(true)
  })

  it('iPhone hors écran d’accueil : explication, pas de bouton Activer, Plus tard disponible', async () => {
    mockSupport.mockReturnValue('ios-install-required')
    const wrapper = await mountCard()

    expect(wrapper.get('[data-testid="push-moment-ios"]').text()).toBe(
      "Sur iPhone : ajoutez d'abord WeAct à l'écran d'accueil (Partager, puis « Sur l'écran d'accueil »), puis activez les notifications.",
    )
    expect(wrapper.find('[data-testid="push-moment-enable"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="push-moment-later"]').exists()).toBe(true)
  })

  it('disparaît après l’activation', async () => {
    mockSubscribe.mockImplementation(async () => {
      setPermission('granted')
      mockExisting.mockResolvedValue({ endpoint: 'https://push.example/x' })
      return 'enabled'
    })
    const wrapper = await mountCard()

    await wrapper.get('[data-testid="push-moment-enable"]').trigger('click')
    await flushPromises()

    expect(mockSubscribe).toHaveBeenCalledOnce()
    expect(wrapper.find(CARD).exists()).toBe(false)
  })

  it('fonctionne sans localStorage', async () => {
    const getItem = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('denied')
    })
    const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('denied')
    })

    const wrapper = await mountCard()
    expect(wrapper.find(CARD).exists()).toBe(true)
    await wrapper.get('[data-testid="push-moment-later"]').trigger('click')
    expect(wrapper.find(CARD).exists()).toBe(false)

    getItem.mockRestore()
    setItem.mockRestore()
  })
})
