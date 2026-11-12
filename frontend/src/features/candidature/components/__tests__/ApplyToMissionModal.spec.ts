import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, ref } from 'vue'
import ApplyToMissionModal from '../ApplyToMissionModal.vue'

const applyMock = vi.fn()
const isSuccess = ref(false)
const errorRef = ref<string | null>(null)
const errorCodeRef = ref<string | null>(null)

vi.mock('../../composables/useApplyToMission', () => ({
  useApplyToMission: () => ({
    isLoading: ref(false),
    error: errorRef,
    errorCode: errorCodeRef,
    isSuccess,
    apply: applyMock,
    reset: vi.fn(),
  }),
}))
vi.mock('@/features/auth/services/authApi', () => ({ authApi: { resendVerificationEmail: vi.fn() } }))
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}))

// La carte réelle est couverte par PushMomentCard.spec : on pilote ici sa visibilité.
let cardVisible = false
vi.mock('@/features/notification/components/PushMomentCard.vue', () => ({
  default: defineComponent({
    props: { producerName: { type: String, default: null }, scope: String, scopeId: String },
    emits: ['visibility'],
    mounted() {
      this.$emit('visibility', cardVisible)
    },
    template: '<div data-testid="push-moment-card">{{ scope }}|{{ scopeId }}|{{ producerName }}</div>',
  }),
}))

async function submit(producerName: string | null = 'Agence Lumière') {
  const wrapper = mount(ApplyToMissionModal, {
    props: { isOpen: true, missionId: 'mission-1', missionTitle: 'Lookbook', producerName },
    attachTo: document.body,
  })
  applyMock.mockImplementation(async () => {
    isSuccess.value = true
    return { success: true, data: { id: 'cand-1', status: 'pending', status_label: 'En attente' } }
  })
  const submitButton = document.body.querySelectorAll('button')
  const send = Array.from(submitButton).find((b) => b.textContent?.includes('Envoyer ma candidature'))
  send!.click()
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.useFakeTimers()
  vi.setSystemTime(new Date('2026-10-24T12:00:00Z'))
  isSuccess.value = false
  errorRef.value = null
  errorCodeRef.value = null
  cardVisible = false
  applyMock.mockReset()
})

afterEach(() => {
  vi.useRealTimers()
  document.body.innerHTML = ''
})

describe('ApplyToMissionModal — carte d’activation des notifications', () => {
  it('conserve la fermeture automatique quand la carte n’est pas visible', async () => {
    const wrapper = await submit()

    expect(document.body.querySelector('[data-testid="push-moment-card"]')).not.toBeNull()
    vi.advanceTimersByTime(1500)
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })

  it('ne ferme pas la modale seule quand la carte est visible', async () => {
    cardVisible = true
    const wrapper = await submit()

    const card = document.body.querySelector('[data-testid="push-moment-card"]')
    expect(card?.textContent).toBe('candidature|mission-1|Agence Lumière')
    vi.advanceTimersByTime(5000)
    expect(wrapper.emitted('close')).toBeUndefined()

    ;(document.body.querySelector('[data-testid="apply-modal-close"]') as HTMLButtonElement).click()
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })
})

describe('ApplyToMissionModal — suspension UGC', () => {
  it('n\'ouvre pas le parcours d\'abonnement et propose le lien vers la suspension', async () => {
    const message = 'Votre accès aux missions UGC est suspendu.'
    applyMock.mockImplementation(async () => {
      errorRef.value = message
      errorCodeRef.value = 'UGC_SUSPENDED'
      return { success: false, error: { code: 'UGC_SUSPENDED', message } }
    })
    const wrapper = mount(ApplyToMissionModal, {
      props: { isOpen: true, missionId: 'mission-1', missionTitle: 'Lookbook', producerName: null },
      attachTo: document.body,
      global: { stubs: { RouterLink: { template: '<a><slot /></a>', props: ['to'] } } },
    })
    const send = Array.from(document.body.querySelectorAll('button')).find((b) =>
      b.textContent?.includes('Envoyer ma candidature'),
    )
    send!.click()
    await flushPromises()

    expect(wrapper.emitted('subscription-required')).toBeUndefined()
    const link = document.body.querySelector('[data-testid="apply-modal-suspension-link"]')
    expect(link).not.toBeNull()
    expect(link?.textContent).toContain('Voir ma suspension')
    expect(document.body.textContent).toContain(message)
    wrapper.unmount()
  })
})
