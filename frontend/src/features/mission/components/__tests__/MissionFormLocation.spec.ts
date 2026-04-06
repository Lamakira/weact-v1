import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createRouter, createWebHistory } from 'vue-router'
import MissionForm from '../MissionForm.vue'
import { BENIN_CITY_OPTIONS } from '@/shared/constants/beninCities'

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
    expect(select.element.tagName).toBe('SELECT')

    const options = select.findAll('option')
    // 1 default "Sélectionnez" + city options
    expect(options.length).toBe(1 + BENIN_CITY_OPTIONS.length)
    expect(select.text()).toContain('Cotonou')
    expect(select.text()).toContain('Porto-Novo')
  })

  it('does not render old free-text lieu input', () => {
    const wrapper = mountForm()
    const oldInput = wrapper.find('[data-testid="lieu-input"]')
    expect(oldInput.exists()).toBe(false)
  })

  it('pre-populates select with matching city in edit mode', () => {
    const wrapper = mountForm({ lieu: 'Cotonou' })
    const select = wrapper.find('[data-testid="lieu-select"]')
    expect((select.element as HTMLSelectElement).value).toBe('Cotonou')
  })

  it('shows empty state for legacy free-text lieu value not in cities list', () => {
    const wrapper = mountForm({ lieu: 'Cotonou centre-ville' })
    const select = wrapper.find('[data-testid="lieu-select"]')
    // Browser renders the default placeholder when value doesn't match any option
    expect((select.element as HTMLSelectElement).value).toBe('')
  })
})
