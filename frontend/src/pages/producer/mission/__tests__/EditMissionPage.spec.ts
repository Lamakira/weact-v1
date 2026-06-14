import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import type { Mission } from '@/features/mission/types'

const routerPushSpy = vi.fn()
const loadMissionSpy = vi.fn().mockResolvedValue(undefined)
const missionRef = ref<Mission | null>(null)

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: routerPushSpy }),
  useRoute: () => ({ params: { id: 'm1' } }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn() }),
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

  it('blocks editing a UGC mission: no form, shows the non-editable message', async () => {
    // discriminant canonique isUgcMission = type_compensation != null
    missionRef.value = { ...baseMission, type_compensation: 'product', commission_ugc: 2500 } as unknown as Mission

    const wrapper = mount(EditMissionPage, { attachTo: document.body })
    await flushPromises()

    expect(wrapper.find('[data-testid="mission-form"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('non modifiable')
  })

  it('renders the edit form for a standard (non-UGC) mission', async () => {
    missionRef.value = { ...baseMission, type_compensation: null } as unknown as Mission

    const wrapper = mount(EditMissionPage, { attachTo: document.body })
    await flushPromises()

    expect(wrapper.find('[data-testid="mission-form"]').exists()).toBe(true)
  })
})
