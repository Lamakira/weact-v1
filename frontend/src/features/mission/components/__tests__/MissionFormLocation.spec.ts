import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createRouter, createWebHistory } from 'vue-router'
import MissionForm from '../MissionForm.vue'
import { BENIN_CITY_OPTIONS } from '@/shared/constants/beninCities'
import { getSelectLabels, getSelectValue } from '@/components/ui/__tests__/selectTestUtils'

const LIEU = '[data-testid="lieu-select"]'

// Mock composables
vi.mock('../../composables/useMissionCreate', () => ({
  useMissionCreate: () => ({
    createMission: vi.fn().mockResolvedValue({ success: true, data: {} }),
    isSubmitting: { value: false },
    error: { value: null },
    errorCode: { value: null },
  }),
}))

vi.mock('@/features/auth/services/authApi', () => ({
  authApi: {
    resendVerificationEmail: vi.fn(),
  },
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
  }),
}))

const router = createRouter({
  history: createWebHistory(),
  routes: [{ path: '/', component: { template: '<div />' } }],
})

describe('MissionForm — Location Select', () => {
  function mountForm(initialValues = {}) {
    return mount(MissionForm, {
      props: {
        mode: 'create',
        initialValues,
      },
      global: {
        plugins: [
          router,
          createTestingPinia({ createSpy: vi.fn }),
        ],
        stubs: {
          NotificationBell: true,
        },
      },
    })
  }

  beforeEach(async () => {
    await router.push('/')
    await router.isReady()
  })

  it('renders location select with Benin city options', () => {
    const wrapper = mountForm()
    const select = wrapper.find('[data-testid="lieu-select"]')
    expect(select.exists()).toBe(true)
    expect(select.attributes('data-slot')).toBe('select-trigger')

    const labels = getSelectLabels(wrapper, LIEU)
    // 1 default "Sélectionnez" + city options
    expect(labels.length).toBe(1 + BENIN_CITY_OPTIONS.length)
    expect(labels).toContain('Cotonou')
    expect(labels).toContain('Porto-Novo')
  })

  it('does not render old free-text lieu input', () => {
    const wrapper = mountForm()
    const oldInput = wrapper.find('[data-testid="lieu-input"]')
    expect(oldInput.exists()).toBe(false)
  })

  it('pre-populates select with matching city in edit mode', () => {
    const wrapper = mountForm({ lieu: 'Cotonou' })
    expect(getSelectValue(wrapper, LIEU)).toBe('Cotonou')
    expect(wrapper.find(LIEU).text()).toBe('Cotonou')
  })

  it('shows empty state for legacy free-text lieu value not in cities list', () => {
    const wrapper = mountForm({ lieu: 'Cotonou centre-ville' })
    const select = wrapper.find('[data-testid="lieu-select"]')
    // La liste retombe sur l'option par défaut quand la valeur ne correspond à aucune option
    expect(select.text()).toBe('Sélectionnez')
  })
})
