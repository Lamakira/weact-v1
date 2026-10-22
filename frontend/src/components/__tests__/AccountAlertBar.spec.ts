import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import AccountAlertBar from '../AccountAlertBar.vue'

const ctx = vi.hoisted(() => ({
  isSuspended: { value: false } as { value: boolean },
  fetchStatus: vi.fn(),
  hasPendingPayment: { value: false } as { value: boolean },
  paymentFailed: { value: false } as { value: boolean },
  dismissFailure: vi.fn(),
  emailVerified: { value: true } as { value: boolean },
  routerPush: vi.fn(),
  handleResend: vi.fn(),
  cooldownSeconds: { value: 0 } as { value: number },
  reducedMotion: false,
  reconcilerMounted: vi.fn(),
}))

vi.mock('@/composables/useUgcSuspension', () => ({
  useUgcSuspension: () => ({ isSuspended: ctx.isSuspended, fetchStatus: ctx.fetchStatus }),
}))
vi.mock('@/features/face/composables/useSubscriptionReconciler', () => ({
  useSubscriptionReconciler: () => {
    ctx.reconcilerMounted()
    return {
      hasPendingPayment: ctx.hasPendingPayment,
      paymentFailed: ctx.paymentFailed,
      dismissFailure: ctx.dismissFailure,
    }
  },
}))
vi.mock('@/composables/useResendVerificationEmail', () => ({
  useResendVerificationEmail: () => ({
    isLoading: ref(false),
    cooldownSeconds: ctx.cooldownSeconds,
    canResend: { value: ctx.cooldownSeconds.value === 0 },
    handleResend: ctx.handleResend,
  }),
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    get isEmailVerified() { return ctx.emailVerified.value },
  }),
}))
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: ctx.routerPush }),
}))

function setReducedMotion(value: boolean): void {
  ctx.reducedMotion = value
  window.matchMedia = vi.fn().mockImplementation((query: string) => ({
    matches: query.includes('prefers-reduced-motion') ? value : false,
    media: query,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  })) as unknown as typeof window.matchMedia
}

async function mountBar(props: Record<string, unknown> = {}) {
  const wrapper = mount(AccountAlertBar, { props })
  await flushPromises()
  return wrapper
}

const title = (w: ReturnType<typeof mount>) => w.get('[data-testid="account-alert-content"]').text()
const dots = (w: ReturnType<typeof mount>) => w.findAll('[data-testid="account-alert-dot"]')

describe('AccountAlertBar', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.clearAllMocks()
    ctx.isSuspended = ref(false)
    ctx.hasPendingPayment = ref(false)
    ctx.paymentFailed = ref(false)
    ctx.emailVerified = ref(true)
    ctx.cooldownSeconds = ref(0)
    setReducedMotion(false)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('ne rend rien sans alerte', async () => {
    const wrapper = await mountBar()
    expect(wrapper.find('[data-testid="account-alert-bar"]').exists()).toBe(false)
  })

  it('une seule alerte : pas de points, pas de défilement, texte et action d\'origine', async () => {
    ctx.emailVerified = ref(false)
    const wrapper = await mountBar()

    expect(wrapper.get('[role="region"]').attributes('aria-label')).toBe('Alertes du compte')
    expect(title(wrapper)).toContain('Vérifiez votre adresse email')
    expect(wrapper.get('[data-testid="account-alert-action"]').text()).toBe('Renvoyer l\'email')
    expect(dots(wrapper)).toHaveLength(0)
    expect(wrapper.find('[data-testid="account-alert-progress"]').exists()).toBe(false)

    await wrapper.get('[data-testid="account-alert-action"]').trigger('click')
    expect(ctx.handleResend).toHaveBeenCalledOnce()
  })

  it('affiche le délai de renvoi dans l\'action e-mail et la désactive', async () => {
    ctx.emailVerified = ref(false)
    ctx.cooldownSeconds = ref(42)
    const wrapper = await mountBar()

    const action = wrapper.get('[data-testid="account-alert-action"]')
    expect(action.text()).toBe('Renvoyer (42s)')
    expect(action.attributes('disabled')).toBeDefined()
  })

  it('ordonne les alertes : suspension, paiement, e-mail ; points et compteur', async () => {
    ctx.emailVerified = ref(false)
    ctx.hasPendingPayment = ref(true)
    ctx.isSuspended = ref(true)
    const wrapper = await mountBar()

    // première alerte = suspension
    expect(title(wrapper)).toContain('Compte suspendu')
    expect(dots(wrapper)).toHaveLength(3)
    expect(wrapper.get('[data-testid="account-alert-count"]').text()).toBe('1/3')
    expect(dots(wrapper)[0]!.attributes('aria-current')).toBe('true')

    await dots(wrapper)[1]!.trigger('click')
    expect(title(wrapper)).toContain('Paiement d\'abonnement en attente')
    expect(wrapper.get('[data-testid="account-alert-count"]').text()).toBe('2/3')

    await dots(wrapper)[2]!.trigger('click')
    expect(title(wrapper)).toContain('Vérifiez votre adresse email')
  })

  it('un clic sur un point affiche cette alerte et la teinte selon la gravité', async () => {
    ctx.emailVerified = ref(false)
    ctx.hasPendingPayment = ref(true)
    const wrapper = await mountBar()

    expect(wrapper.get('[data-testid="account-alert-bar"]').attributes('data-tone')).toBe('warn')
    await dots(wrapper)[1]!.trigger('click')
    expect(wrapper.get('[data-testid="account-alert-bar"]').attributes('data-tone')).toBe('info')
  })

  it('une alerte plus urgente chargée après coup prend la tête de la barre', async () => {
    ctx.emailVerified = ref(false)
    const wrapper = await mountBar()
    expect(title(wrapper)).toContain('Vérifiez votre adresse email')

    ctx.isSuspended.value = true
    await flushPromises()

    expect(title(wrapper)).toContain('Compte suspendu')
    expect(wrapper.get('[data-testid="account-alert-count"]').text()).toBe('1/2')
  })

  it('conserve l\'action d\'origine de la suspension et du paiement', async () => {
    ctx.isSuspended = ref(true)
    ctx.hasPendingPayment = ref(true)
    const wrapper = await mountBar()

    await wrapper.get('[data-testid="account-alert-action"]').trigger('click')
    expect(ctx.routerPush).toHaveBeenLastCalledWith({ name: 'face-ugc-suspension' })

    await dots(wrapper)[1]!.trigger('click')
    expect(wrapper.get('[data-testid="account-alert-action"]').text()).toBe('Reprendre le paiement')
    await wrapper.get('[data-testid="account-alert-action"]').trigger('click')
    expect(ctx.routerPush).toHaveBeenLastCalledWith({ name: 'face-billing' })
  })

  it('paiement échoué : « Réessayer » et « Ignorer »', async () => {
    ctx.paymentFailed = ref(true)
    const wrapper = await mountBar()

    expect(title(wrapper)).toContain('Paiement non abouti')
    await wrapper.get('[data-testid="account-alert-action"]').trigger('click')
    expect(ctx.routerPush).toHaveBeenLastCalledWith({ name: 'pricing' })
    await wrapper.get('[data-testid="account-alert-secondary"]').trigger('click')
    expect(ctx.dismissFailure).toHaveBeenCalledOnce()
  })

  describe('défilement automatique', () => {
    it('passe à l\'alerte suivante toutes les 6 s, avec un trait de progression', async () => {
      ctx.emailVerified = ref(false)
      ctx.hasPendingPayment = ref(true)
      const wrapper = await mountBar()

      expect(wrapper.find('[data-testid="account-alert-progress"]').exists()).toBe(true)
      expect(title(wrapper)).toContain('Paiement d\'abonnement en attente')

      await vi.advanceTimersByTimeAsync(5999)
      expect(title(wrapper)).toContain('Paiement d\'abonnement en attente')
      await vi.advanceTimersByTimeAsync(1)
      expect(title(wrapper)).toContain('Vérifiez votre adresse email')

      // boucle
      await vi.advanceTimersByTimeAsync(6000)
      expect(title(wrapper)).toContain('Paiement d\'abonnement en attente')
    })

    it('ne défile pas quand une suspension (alerte bloquante) est présente', async () => {
      ctx.isSuspended = ref(true)
      ctx.hasPendingPayment = ref(true)
      ctx.emailVerified = ref(false)
      const wrapper = await mountBar()

      await vi.advanceTimersByTimeAsync(20000)

      expect(title(wrapper)).toContain('Compte suspendu')
      expect(wrapper.find('[data-testid="account-alert-progress"]').exists()).toBe(false)
    })

    it('se met en pause au survol puis reprend à la sortie', async () => {
      ctx.emailVerified = ref(false)
      ctx.hasPendingPayment = ref(true)
      const wrapper = await mountBar()
      const bar = wrapper.get('[data-testid="account-alert-bar"]')

      await bar.trigger('mouseenter')
      await vi.advanceTimersByTimeAsync(20000)
      expect(title(wrapper)).toContain('Paiement d\'abonnement en attente')
      expect(wrapper.find('[data-testid="account-alert-progress"]').exists()).toBe(false)

      await bar.trigger('mouseleave')
      await vi.advanceTimersByTimeAsync(6000)
      expect(title(wrapper)).toContain('Vérifiez votre adresse email')
    })

    it('se met en pause au focus clavier dans la barre', async () => {
      ctx.emailVerified = ref(false)
      ctx.hasPendingPayment = ref(true)
      const wrapper = await mountBar()

      await wrapper.get('[data-testid="account-alert-bar"]').trigger('focusin')
      await vi.advanceTimersByTimeAsync(20000)

      expect(title(wrapper)).toContain('Paiement d\'abonnement en attente')
    })

    it('ne défile pas sous prefers-reduced-motion', async () => {
      setReducedMotion(true)
      ctx.emailVerified = ref(false)
      ctx.hasPendingPayment = ref(true)
      const wrapper = await mountBar()

      await vi.advanceTimersByTimeAsync(20000)

      expect(title(wrapper)).toContain('Paiement d\'abonnement en attente')
      expect(wrapper.find('[data-testid="account-alert-progress"]').exists()).toBe(false)
    })
  })

  describe('accessibilité', () => {
    it('n\'annonce (aria-live) qu\'après un changement manuel, jamais au défilement automatique', async () => {
      ctx.emailVerified = ref(false)
      ctx.hasPendingPayment = ref(true)
      const wrapper = await mountBar()
      const live = () => wrapper.get('[aria-live]').attributes('aria-live')

      expect(live()).toBe('off')
      await vi.advanceTimersByTimeAsync(6000)
      expect(live()).toBe('off')

      await dots(wrapper)[0]!.trigger('click')
      expect(live()).toBe('polite')
    })

    it('les points sont des boutons étiquetés, atteignables au clavier', async () => {
      ctx.emailVerified = ref(false)
      ctx.hasPendingPayment = ref(true)
      const wrapper = await mountBar()

      const first = dots(wrapper)[0]!
      expect(first.element.tagName).toBe('BUTTON')
      expect(first.attributes('aria-label')).toBe('Alerte 1 sur 2')
      expect(first.attributes('tabindex')).toBeUndefined()
    })
  })

  describe('audience et surfaces', () => {
    it('producteur : e-mail seulement, ni suspension ni abonnement montés', async () => {
      ctx.isSuspended = ref(true)
      ctx.hasPendingPayment = ref(true)
      ctx.emailVerified = ref(false)
      const wrapper = await mountBar({ audience: 'producer' })

      expect(title(wrapper)).toContain('Vérifiez votre adresse email')
      expect(dots(wrapper)).toHaveLength(0)
      expect(ctx.fetchStatus).not.toHaveBeenCalled()
      expect(ctx.reconcilerMounted).not.toHaveBeenCalled()
    })

    it('face : récupère la suspension au montage', async () => {
      await mountBar()
      expect(ctx.fetchStatus).toHaveBeenCalledOnce()
    })

    it('hideSubscription : pas d\'alerte de paiement et le réconciliateur n\'est pas monté', async () => {
      ctx.hasPendingPayment = ref(true)
      ctx.emailVerified = ref(false)
      const wrapper = await mountBar({ hideSubscription: true })

      expect(title(wrapper)).toContain('Vérifiez votre adresse email')
      expect(dots(wrapper)).toHaveLength(0)
      expect(ctx.reconcilerMounted).not.toHaveBeenCalled()
    })
  })
})
