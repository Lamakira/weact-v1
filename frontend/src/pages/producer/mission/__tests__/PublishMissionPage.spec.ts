import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, nextTick } from 'vue'
import PublishMissionPage from '../PublishMissionPage.vue'

/**
 * Toast honnête sur le statut (story 1.4 AC8 / D-1.4.e) :
 *  - mission `pending_payment` → message UGC (« Réglez la commission pour la publier »)
 *  - mission `published`       → message standard (« Mission publiée avec succès! »)
 * MissionForm est stubbé pour piloter directement son @success.
 */
const { successSpy, pushSpy } = vi.hoisted(() => ({ successSpy: vi.fn(), pushSpy: vi.fn() }))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushSpy }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { email_verified: true },
    isEmailVerified: true,
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: successSpy, error: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}))

const MissionFormStub = defineComponent({
  name: 'MissionForm',
  emits: ['success', 'cancel'],
  template: '<div data-testid="mission-form-stub" />',
})

const mountPage = () =>
  mount(PublishMissionPage, {
    global: {
      stubs: { MissionForm: MissionFormStub },
    },
  })

describe('PublishMissionPage — toast honnête', () => {
  beforeEach(() => {
    successSpy.mockClear()
    pushSpy.mockClear()
  })

  it('1. mission pending_payment → toast UGC honnête + navigation avec ?pay pour ouvrir le tunnel', async () => {
    const wrapper = mountPage()

    wrapper.findComponent(MissionFormStub).vm.$emit('success', { status: 'pending_payment', id: 'm-1' })
    await nextTick()

    expect(successSpy).toHaveBeenCalledWith('Mission UGC créée. Réglez la commission pour la publier.')
    expect(pushSpy).toHaveBeenCalledWith({ name: 'producer-missions', query: { pay: 'm-1' } })
  })

  it('2. mission published → toast standard + navigation', async () => {
    const wrapper = mountPage()

    wrapper.findComponent(MissionFormStub).vm.$emit('success', { status: 'published' })
    await nextTick()

    expect(successSpy).toHaveBeenCalledWith('Mission publiée avec succès!')
    expect(pushSpy).toHaveBeenCalledWith({ name: 'producer-missions' })
  })
})
