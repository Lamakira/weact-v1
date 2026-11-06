import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
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
vi.mock('@/features/auth/services/authApi', () => ({ authApi: { resendVerificationEmail: vi.fn() } }))
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}))

const waitForValidation = async () => {
  await flushPromises()
  await nextTick()
  await new Promise((resolve) => setTimeout(resolve, 10))
  await flushPromises()
}

const router = createRouter({
  history: createWebHistory(),
  routes: [{ path: '/', component: { template: '<div />' } }],
})

// Clôture passée, tournage dans le futur : le cas d'une mission payée dont on déplace le tournage.
const pastDeadline = addDaysIso(tomorrowIso(), -6)
const initialValues = {
  titre: 'Spot boutique',
  description: 'Brief',
  date_tournage: addDaysIso(tomorrowIso(), 10),
  profil_recherche: 'Face',
  budget: 25000,
  date_limite_candidature: pastDeadline,
  nombre_faces_voulu: 2,
  type_mission: 'publicite' as const,
  genre_voulu: 'tous' as const,
  lieu: 'Cotonou',
}

function mountForm(mode: 'create' | 'edit') {
  return mount(MissionForm, {
    props: { mode, initialValues },
    global: {
      plugins: [router, createTestingPinia({ createSpy: vi.fn })],
      stubs: { NotificationBell: true },
    },
  })
}

describe('MissionForm — clôture passée en édition', () => {
  beforeEach(async () => {
    await router.push('/')
    await router.isReady()
  })

  it('édition : la clôture inchangée ne bloque pas l’enregistrement', async () => {
    const wrapper = mountForm('edit')
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(wrapper.emitted('submit')).toHaveLength(1)
    const payload = wrapper.emitted('submit')![0]![0] as { date_limite_candidature: string }
    expect(payload.date_limite_candidature).toBe(pastDeadline)
    expect(wrapper.find('[data-testid="date_limite_candidature-error"]').exists()).toBe(false)
  })

  it('édition : la borne min du champ laisse la valeur initiale sélectionnable', () => {
    const wrapper = mountForm('edit')
    expect(wrapper.find('input#date_limite_candidature').attributes('min')).toBe(pastDeadline)
  })

  it('édition : une clôture modifiée vers une autre date passée est refusée', async () => {
    const wrapper = mountForm('edit')
    await wrapper.find('input#date_limite_candidature').setValue(addDaysIso(pastDeadline, 2))
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(wrapper.emitted('submit')).toBeUndefined()
    expect(wrapper.find('[data-testid="date_limite_candidature-error"]').text()).toContain('postérieure à aujourd')
  })

  it('édition : la borne min du tournage est demain quand la clôture est passée', () => {
    const wrapper = mountForm('edit')
    expect(wrapper.find('input#date_tournage').attributes('min')).toBe(tomorrowIso())
  })

  it('création : la borne min du tournage reste clôture + 1 jour', () => {
    const wrapper = mountForm('create')
    expect(wrapper.find('input#date_tournage').attributes('min')).toBe(addDaysIso(pastDeadline, 1))
  })

  it('création : la clôture passée reste refusée et la borne min est demain', async () => {
    const wrapper = mountForm('create')
    expect(wrapper.find('input#date_limite_candidature').attributes('min')).toBe(tomorrowIso())

    await wrapper.find('form').trigger('submit')
    await waitForValidation()
    expect(wrapper.emitted('submit')).toBeUndefined()
  })
})
