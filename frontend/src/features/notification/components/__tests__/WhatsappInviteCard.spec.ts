import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { reactive } from 'vue'

const h = vi.hoisted(() => ({
  setWhatsappAlerts: vi.fn(),
  dismissWhatsappInvite: vi.fn(),
  toast: { success: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const authState = reactive<{ user: { whatsapp_alerts?: Record<string, unknown> } | null }>({
  user: null,
})

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    get user() {
      return authState.user
    },
  }),
}))
vi.mock('@/composables/useToast', () => ({ useToast: () => h.toast }))
vi.mock('../../services/whatsappAlertsApi', () => ({
  whatsappAlertsApi: {
    setWhatsappAlerts: h.setWhatsappAlerts,
    dismissWhatsappInvite: h.dismissWhatsappInvite,
  },
  whatsappErrorMessage: (_err: unknown, fallback: string) => fallback,
}))

import WhatsappInviteCard from '../WhatsappInviteCard.vue'

const base = {
  available: true,
  enabled: false,
  has_valid_number: true,
  invite_dismissed: false,
  number: '+229 01 97 45 12 33',
}

function mountCard(audience: 'face' | 'producer', ...rest: [Record<string, unknown> | undefined?]) {
  const alerts = rest.length ? rest[0] : base
  authState.user = { whatsapp_alerts: alerts }
  return mount(WhatsappInviteCard, { props: { audience } })
}

const CARD = '[data-testid="whatsapp-invite-card"]'

describe('WhatsappInviteCard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('Face : affiche les textes de la maquette avec le numéro', () => {
    const wrapper = mountCard('face')
    expect(wrapper.find(CARD).exists()).toBe(true)
    expect(wrapper.text()).toContain('Recevez vos demandes de booking sur WhatsApp')
    expect(wrapper.text()).toContain(
      'Nous vous prévenons au +229 01 97 45 12 33 quand un Producteur vous propose un booking, vous écrit ou paie. Pas de publicité.',
    )
    expect(wrapper.text()).toContain('Activer les alertes WhatsApp')
    expect(wrapper.text()).toContain('Plus tard')
  })

  it('Producteur avec numéro : affiche le titre Producteur', () => {
    const wrapper = mountCard('producer')
    expect(wrapper.find(CARD).exists()).toBe(true)
    expect(wrapper.text()).toContain('Soyez prévenu sur WhatsApp quand une Face répond')
    expect(wrapper.text()).toContain('+229 01 97 45 12 33')
  })

  it('Producteur sans numéro : pas de carte', () => {
    const wrapper = mountCard('producer', { ...base, has_valid_number: false, number: null })
    expect(wrapper.find(CARD).exists()).toBe(false)
  })

  it.each([
    ['indisponible', { ...base, available: false }],
    ['absent', undefined],
    ['numéro non valide', { ...base, has_valid_number: false }],
    ['déjà activé', { ...base, enabled: true }],
    ['invitation écartée', { ...base, invite_dismissed: true }],
  ])('est masquée : %s', (_label, alerts) => {
    expect(mountCard('face', alerts).find(CARD).exists()).toBe(false)
  })

  it('« Activer » appelle l\'API (enabled true) et affiche le toast', async () => {
    h.setWhatsappAlerts.mockResolvedValue({ ...base, enabled: true })
    const wrapper = mountCard('face')
    await wrapper.find('[data-testid="whatsapp-invite-enable"]').trigger('click')
    await flushPromises()
    expect(h.setWhatsappAlerts).toHaveBeenCalledWith(true)
    expect(h.toast.success).toHaveBeenCalledWith('Alertes WhatsApp activées')
  })

  it('« Activer » en échec : toast d\'erreur, pas de succès', async () => {
    h.setWhatsappAlerts.mockRejectedValue(new Error('boom'))
    const wrapper = mountCard('face')
    await wrapper.find('[data-testid="whatsapp-invite-enable"]').trigger('click')
    await flushPromises()
    expect(h.toast.error).toHaveBeenCalled()
    expect(h.toast.success).not.toHaveBeenCalled()
  })

  it('« Plus tard » appelle dismiss-invite', async () => {
    h.dismissWhatsappInvite.mockResolvedValue(undefined)
    const wrapper = mountCard('producer')
    await wrapper.find('[data-testid="whatsapp-invite-later"]').trigger('click')
    await flushPromises()
    expect(h.dismissWhatsappInvite).toHaveBeenCalledTimes(1)
    expect(h.setWhatsappAlerts).not.toHaveBeenCalled()
  })
})
