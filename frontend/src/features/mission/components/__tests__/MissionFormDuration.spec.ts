import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createRouter, createWebHistory } from 'vue-router'
import MissionForm from '../MissionForm.vue'
import { getSelectLabels, getSelectValue, setSelectValue } from '@/components/ui/__tests__/selectTestUtils'

const DUREE = '[data-testid="duree-select"]'

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

describe('MissionForm — Duration Select', () => {
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

  it('renders duration select with all preset options', () => {
    const wrapper = mountForm()
    // FloatingSelect passes data-testid via $attrs to the shadcn Select trigger
    const select = wrapper.find('[data-testid="duree-select"]')
    expect(select.exists()).toBe(true)
    expect(select.attributes('data-slot')).toBe('select-trigger')

    const labels = getSelectLabels(wrapper, DUREE)
    // 1 default "Sélectionnez" + 11 presets = 12
    expect(labels.length).toBe(12)
    expect(labels).toContain('½ journée (max 4h)')
    expect(labels).toContain('5 journées (max 40h)')
    expect(labels).toContain('Plus de 5 jours...')
  })

  it('does not show custom days input by default', () => {
    const wrapper = mountForm()
    const customInput = wrapper.find('[data-testid="duree-custom-days"]')
    expect(customInput.exists()).toBe(false)
  })

  it('shows custom days input when "Plus de 5 jours..." is selected', async () => {
    const wrapper = mountForm()
    await setSelectValue(wrapper, DUREE, 'custom')
    await flushPromises()

    const customInput = wrapper.find('[data-testid="duree-custom-days"]')
    expect(customInput.exists()).toBe(true)
  })

  it('pre-populates select with matching preset in edit mode (new format)', () => {
    const wrapper = mountForm({ duree: '2 journées (max 16h)' })
    expect(getSelectValue(wrapper, DUREE)).toBe('2 journées (max 16h)')
  })

  it('pre-populates select with matching preset in edit mode (legacy format)', () => {
    const wrapper = mountForm({ duree: '2 journées (16h)' })
    expect(getSelectValue(wrapper, DUREE)).toBe('2 journées (max 16h)')
  })

  it('pre-populates custom days in edit mode for custom duration', () => {
    const wrapper = mountForm({ duree: '7 jours (56h)' })
    expect(getSelectValue(wrapper, DUREE)).toBe('custom')

    const customInput = wrapper.find('[data-testid="duree-custom-days"]')
    expect(customInput.exists()).toBe(true)
  })

  it('does not render old free-text duree input', () => {
    const wrapper = mountForm()
    const oldInput = wrapper.find('[data-testid="duree-input"]')
    expect(oldInput.exists()).toBe(false)
  })
})
