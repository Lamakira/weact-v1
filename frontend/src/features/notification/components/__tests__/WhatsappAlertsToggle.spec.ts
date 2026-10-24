import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { reactive } from 'vue'

const h = vi.hoisted(() => ({
  setWhatsappAlerts: vi.fn(),
  refreshUser: vi.fn().mockResolvedValue(true),
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
    refreshUser: h.refreshUser,
  }),
}))
vi.mock('@/composables/useToast', () => ({ useToast: () => h.toast }))
vi.mock('../../services/whatsappAlertsApi', () => ({
  whatsappAlertsApi: { setWhatsappAlerts: h.setWhatsappAlerts },
  whatsappErrorMessage: (err: { response?: { data?: { error?: { message?: string } } } }, fallback: string) =>
    err?.response?.data?.error?.message ?? fallback,
}))

import WhatsappAlertsToggle from '../WhatsappAlertsToggle.vue'

const NUMBER = '+229 01 97 45 12 33'

function setAlerts(alerts: Record<string, unknown> | undefined) {
  authState.user = { whatsapp_alerts: alerts }
}

function mountToggle(props: { typedNumber?: string | null; savedNumber?: string | null } = {}) {
  return mount(WhatsappAlertsToggle, {
    props: { typedNumber: NUMBER, savedNumber: NUMBER, ...props },
  })
}

describe('WhatsappAlertsToggle', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setAlerts({ available: true, enabled: false, has_valid_number: true, number: NUMBER })
  })

  it('ne rend rien quand la fonctionnalité est indisponible', () => {
    setAlerts({ available: false })
    expect(mountToggle().find('[data-testid="whatsapp-alerts-toggle"]').exists()).toBe(false)
  })

  it('ne rend rien quand whatsapp_alerts est absent (backend non déployé)', () => {
    setAlerts(undefined)
    expect(mountToggle().find('[data-testid="whatsapp-alerts-toggle"]').exists()).toBe(false)
  })

  it('est grisé avec le message « Ajoutez d\'abord votre numéro » sans numéro valide', async () => {
    setAlerts({ available: true, enabled: false, has_valid_number: false })
    const wrapper = mountToggle({ typedNumber: '', savedNumber: '' })
    const sw = wrapper.find('[data-testid="whatsapp-alerts-switch"]')
    expect(sw.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain("Ajoutez d'abord votre numéro WhatsApp")
    await sw.trigger('click')
    expect(h.setWhatsappAlerts).not.toHaveBeenCalled()
  })

  it('est grisé avec « Enregistrez votre numéro » quand la saisie diffère de l\'enregistré', () => {
    const wrapper = mountToggle({ typedNumber: '+229 01 11 11 11 11' })
    expect(wrapper.find('[data-testid="whatsapp-alerts-switch"]').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Enregistrez votre numéro pour activer les alertes')
  })

  it('affiche les textes de la maquette et l\'état désactivé', () => {
    const wrapper = mountToggle()
    const text = wrapper.text()
    expect(text).toContain('Recevoir mes alertes WeAct sur WhatsApp')
    expect(text).toContain(
      'Demandes de booking, messages non lus et paiements. Pas de publicité. Vous pouvez arrêter à tout moment ici ou en répondant STOP.',
    )
    expect(text).toContain('Changer de numéro désactive les alertes.')
    const sw = wrapper.find('[data-testid="whatsapp-alerts-switch"]')
    expect(sw.attributes('aria-checked')).toBe('false')
    expect(sw.attributes('disabled')).toBeUndefined()
  })

  it('active les alertes : appel API et toast', async () => {
    h.setWhatsappAlerts.mockResolvedValue({ available: true, enabled: true })
    const wrapper = mountToggle()
    await wrapper.find('[data-testid="whatsapp-alerts-switch"]').trigger('click')
    await flushPromises()
    expect(h.setWhatsappAlerts).toHaveBeenCalledWith(true)
    expect(h.toast.success).toHaveBeenCalledWith('Alertes WhatsApp activées')
  })

  it('affiche « Activé le … » en date longue française quand activé et désactive au clic', async () => {
    setAlerts({
      available: true,
      enabled: true,
      enabled_at: '2026-10-24T09:12:00Z',
      has_valid_number: true,
    })
    h.setWhatsappAlerts.mockResolvedValue({ available: true, enabled: false })
    const wrapper = mountToggle()
    expect(wrapper.find('[data-testid="whatsapp-alerts-switch"]').attributes('aria-checked')).toBe('true')
    expect(wrapper.find('[data-testid="whatsapp-alerts-enabled-at"]').text()).toBe('Activé le 24 octobre 2026')
    await wrapper.find('[data-testid="whatsapp-alerts-switch"]').trigger('click')
    await flushPromises()
    expect(h.setWhatsappAlerts).toHaveBeenCalledWith(false)
    expect(h.toast.info).toHaveBeenCalledWith('Alertes WhatsApp désactivées')
  })

  it('affiche le message du serveur en toast sur 422 WHATSAPP_NUMBER_REQUIRED', async () => {
    h.setWhatsappAlerts.mockRejectedValue({
      response: {
        status: 422,
        data: { error: { code: 'WHATSAPP_NUMBER_REQUIRED', message: "Ajoutez d'abord votre numéro WhatsApp." } },
      },
    })
    const wrapper = mountToggle()
    await wrapper.find('[data-testid="whatsapp-alerts-switch"]').trigger('click')
    await flushPromises()
    expect(h.toast.error).toHaveBeenCalledWith("Ajoutez d'abord votre numéro WhatsApp.")
    expect(h.toast.success).not.toHaveBeenCalled()
  })

  it('recharge l\'utilisateur quand le numéro enregistré change', async () => {
    const wrapper = mountToggle()
    await wrapper.setProps({ savedNumber: '+229 01 11 11 11 11', typedNumber: '+229 01 11 11 11 11' })
    expect(h.refreshUser).toHaveBeenCalledTimes(1)
  })
})
