import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import type { Mission } from '@/features/mission/types'

const routerPushSpy = vi.fn()
const routerReplaceSpy = vi.fn()
const toastErrorSpy = vi.fn()
const loadMissionSpy = vi.fn().mockResolvedValue(undefined)
const missionRef = ref<Mission | null>(null)

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: routerPushSpy, replace: routerReplaceSpy }),
  useRoute: () => ({ params: { id: 'm1' } }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: toastErrorSpy }),
}))

vi.mock('@/features/mission/composables', () => ({
  useMissionEdit: () => ({
    isLoading: ref(false),
    isSubmitting: ref(false),
    error: ref<string | null>(null),
    mission: missionRef,
    loadMission: loadMissionSpy,
    updateMission: vi.fn(),
  }),
  useDeleteMission: () => ({ isDeleting: ref(false), deleteMission: vi.fn() }),
}))

vi.mock('@/features/mission/components', () => ({
  MissionForm: defineComponent({
    name: 'MissionFormStub',
    setup: () => () => h('div', { 'data-testid': 'mission-form' }),
  }),
  DeleteMissionDialog: defineComponent({
    name: 'DeleteMissionDialogStub',
    setup: () => () => h('div'),
  }),
}))

import EditMissionPage from '../EditMissionPage.vue'

const baseMission: Mission = {
  id: 'm1',
  titre: 'Mission test',
  description: 'fixture',
  date_tournage: '2026-05-01',
  profil_recherche: 'Face',
  budget: 100000,
  date_limite_candidature: '2026-04-25',
  nombre_faces_voulu: 1,
  type_mission: 'publicite',
  type_mission_label: 'Publicité',
  type_mission_autre: null,
  genre_voulu: 'tous',
  genre_voulu_label: 'Homme et Femme',
  lieu: 'Cotonou',
  duree: '1 jour',
  status: 'published',
  status_label: 'Publiée',
  is_accepting_candidatures: true,
  type_compensation: null,
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
} as unknown as Mission

describe('EditMissionPage — UGC edit dead-end (ugc-3-5)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    missionRef.value = null
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('redirects a UGC mission to the detail page with a toast, never showing the form', async () => {
    missionRef.value = { ...baseMission, type_mission: 'ugc', type_compensation: 'product', commission_ugc: 2500 } as unknown as Mission

    const wrapper = mount(EditMissionPage, { attachTo: document.body })
    await flushPromises()

    expect(wrapper.find('[data-testid="mission-form"]').exists()).toBe(false)
    expect(toastErrorSpy).toHaveBeenCalledWith('Cette mission ne peut plus être modifiée.')
    expect(routerReplaceSpy).toHaveBeenCalledWith({ name: 'producer-mission-detail', params: { id: 'm1' } })
  })

  it('redirects when the server says summary.can_edit is false (closed mission)', async () => {
    missionRef.value = {
      ...baseMission,
      status: 'closed',
      summary: { can_edit: false },
    } as unknown as Mission

    const wrapper = mount(EditMissionPage, { attachTo: document.body })
    await flushPromises()

    expect(wrapper.find('[data-testid="mission-form"]').exists()).toBe(false)
    expect(toastErrorSpy).toHaveBeenCalledWith('Cette mission ne peut plus être modifiée.')
    expect(routerReplaceSpy).toHaveBeenCalledWith({ name: 'producer-mission-detail', params: { id: 'm1' } })
  })

  it('renders the edit form for an editable standard mission', async () => {
    missionRef.value = { ...baseMission, type_compensation: null, summary: { can_edit: true } } as unknown as Mission

    const wrapper = mount(EditMissionPage, { attachTo: document.body })
    await flushPromises()

    expect(wrapper.find('[data-testid="mission-form"]').exists()).toBe(true)
    expect(routerReplaceSpy).not.toHaveBeenCalled()
  })
})
