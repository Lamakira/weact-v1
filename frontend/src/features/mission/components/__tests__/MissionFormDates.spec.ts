import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createTestingPinia } from '@pinia/testing'
import { createRouter, createWebHistory } from 'vue-router'
import MissionForm from '../MissionForm.vue'
import { addDaysIso, tomorrowIso } from '@/lib/dates'

vi.mock('../../composables/useMissionCreate', () => ({
  useMissionCreate: () => ({
    createMission: vi.fn().mockResolvedValue({ success: true, data: {} }),
    isSubmitting: { value: false },
    error: { value: null },
    errorCode: { value: null },
  }),
}))

vi.mock('@/features/auth/services/authApi', () => ({
  authApi: { resendVerificationEmail: vi.fn() },
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}))

const router = createRouter({
  history: createWebHistory(),
  routes: [{ path: '/', component: { template: '<div />' } }],
})

function mountForm() {
  return mount(MissionForm, {
    props: { mode: 'create', initialValues: {} },
    global: {
      plugins: [router, createTestingPinia({ createSpy: vi.fn })],
      stubs: { NotificationBell: true },
    },
  })
}

describe('MissionForm — bornes des dates', () => {
  beforeEach(async () => {
    await router.push('/')
    await router.isReady()
  })

  it('clôture des candidatures : min = demain (backend after:today)', () => {
    const wrapper = mountForm()
    expect(wrapper.find('input#date_limite_candidature').attributes('min')).toBe(tomorrowIso())
  })

  it('date de tournage : min = surlendemain tant que la clôture est vide (backend after:date_limite)', () => {
    const wrapper = mountForm()
    expect(wrapper.find('input#date_tournage').attributes('min')).toBe(addDaysIso(tomorrowIso(), 1))
  })

  it('clôture choisie : tournage min = clôture + 1 jour ; tournage choisi : clôture max = tournage - 1 jour', async () => {
    const wrapper = mountForm()
    const cloture = addDaysIso(tomorrowIso(), 3)
    await wrapper.find('input#date_limite_candidature').setValue(cloture)
    await nextTick()
    expect(wrapper.find('input#date_tournage').attributes('min')).toBe(addDaysIso(cloture, 1))

    const tournage = addDaysIso(cloture, 10)
    await wrapper.find('input#date_tournage').setValue(tournage)
    await nextTick()
    expect(wrapper.find('input#date_limite_candidature').attributes('max')).toBe(addDaysIso(tournage, -1))
  })
})
