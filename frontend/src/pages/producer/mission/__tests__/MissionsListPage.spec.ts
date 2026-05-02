import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import type { Mission } from '@/features/mission/types'

const routerPushSpy = vi.fn()
const fetchMissionsSpy = vi.fn().mockResolvedValue(undefined)

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: routerPushSpy }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { email_verified: true, email_verified_at: '2026-04-01T00:00:00Z' },
    isEmailVerified: true,
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn(), clear: vi.fn(), toast: {} }),
}))

const missionFixture: Mission = {
  id: 'mission-uuid-handoff',
  titre: 'Tournage Handoff',
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
  status: 'closed',
  status_label: 'Clôturée',
  is_accepting_candidatures: false,
  has_paid_payment: true,
  candidatures_count: 0,
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
}

vi.mock('@/features/mission/composables', () => ({
  useMissionsList: () => ({
    missions: ref<Mission[]>([missionFixture]),
    allMissions: ref<Mission[]>([missionFixture]),
    isLoading: ref(false),
    error: ref(null),
    isEmpty: ref(false),
    hasNoMissions: ref(false),
    statusFilter: ref<string>(''),
    fetchMissions: fetchMissionsSpy,
    refreshMissions: vi.fn(),
    setStatusFilter: vi.fn(),
  }),
  useDeleteMission: () => ({ deleteMission: vi.fn(), isDeleting: ref(false) }),
  useCloseMission: () => ({ closeMission: vi.fn(), isClosing: ref(false) }),
  useReopenMission: () => ({ reopenMission: vi.fn(), isReopening: ref(false) }),
  useCompleteMission: () => ({ completeMission: vi.fn(), isCompleting: ref(false) }),
}))

vi.mock('@/features/mission/components', () => ({
  MissionCard: defineComponent({
    name: 'MissionCardStub',
    props: { mission: { type: Object, required: true }, emailVerified: { type: Boolean, required: true } },
    emits: ['edit', 'delete', 'view-candidatures', 'close', 'reopen', 'complete', 'view-attendance'],
    setup(props, { emit }) {
      return () =>
        h(
          'div',
          { 'data-testid': `mission-card-${props.mission.id}` },
          [
            h(
              'button',
              {
                'data-testid': `view-attendance-${props.mission.id}`,
                onClick: () => emit('view-attendance', props.mission.id),
              },
              'Valider les présences',
            ),
          ],
        )
    },
  }),
  DeleteMissionDialog: defineComponent({ name: 'DeleteMissionDialogStub', setup: () => () => h('div') }),
  CloseMissionDialog: defineComponent({ name: 'CloseMissionDialogStub', setup: () => () => h('div') }),
  ReopenMissionDialog: defineComponent({ name: 'ReopenMissionDialogStub', setup: () => () => h('div') }),
  CompleteMissionDialog: defineComponent({ name: 'CompleteMissionDialogStub', setup: () => () => h('div') }),
  MissionStatusFilter: defineComponent({ name: 'MissionStatusFilterStub', setup: () => () => h('div') }),
}))

import MissionsListPage from '../MissionsListPage.vue'

describe('MissionsListPage — viewAttendance route handoff (FIX-26.7)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('routes to producer-mission-attendance with mission id when MissionCard emits view-attendance', async () => {
    const wrapper = mount(MissionsListPage, { attachTo: document.body })
    await flushPromises()

    await wrapper
      .find('[data-testid="view-attendance-mission-uuid-handoff"]')
      .trigger('click')

    expect(routerPushSpy).toHaveBeenCalledWith({
      name: 'producer-mission-attendance',
      params: { id: 'mission-uuid-handoff' },
    })
  })
})
