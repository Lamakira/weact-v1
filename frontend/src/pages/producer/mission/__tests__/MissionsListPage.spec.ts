import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import type { Mission } from '@/features/mission/types'

const routerPushSpy = vi.fn()
// maybeOpenPayTunnel consumes ?pay via router.replace — the mock must resolve
// (the page fires it with `void`, an undefined return would reject unhandled).
const routerReplaceSpy = vi.fn().mockResolvedValue(undefined)
const fetchMissionsSpy = vi.fn().mockResolvedValue(undefined)
const routeQuery: { value: Record<string, unknown> } = { value: {} }

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: routerPushSpy, replace: routerReplaceSpy }),
  useRoute: () => ({ query: routeQuery.value }),
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

// UGC mission awaiting commission payment (story 1.6).
const ugcMissionFixture: Mission = {
  ...missionFixture,
  id: 'ugc-mission-1',
  titre: 'Appel UGC — Unboxing',
  status: 'pending_payment',
  status_label: 'En attente de paiement',
  has_paid_payment: false,
  commission_ugc: 2500,
}

vi.mock('@/features/mission/composables', () => ({
  useMissionsList: () => ({
    missions: ref<Mission[]>([missionFixture, ugcMissionFixture]),
    allMissions: ref<Mission[]>([missionFixture, ugcMissionFixture]),
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
    emits: ['edit', 'delete', 'view-candidatures', 'close', 'reopen', 'complete', 'view-attendance', 'pay-commission'],
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
            h(
              'button',
              {
                'data-testid': `pay-commission-${props.mission.id}`,
                onClick: () => emit('pay-commission', props.mission.id),
              },
              'Régler la commission',
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
    routeQuery.value = {}
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

describe('MissionsListPage — UGC commission tunnel (story 1.6)', () => {
  const overlayStub = defineComponent({
    name: 'UgcPaymentOverlay',
    // RH.2 : prop renommée commission → amount (mission paie toujours commission_ugc).
    props: { modelValue: { type: Boolean, required: true }, amount: { type: Number, default: 0 } },
    setup: (props) => () => (props.modelValue ? h('div', { 'data-testid': 'ugc-overlay-stub' }) : null),
  })

  const mountPage = () =>
    mount(MissionsListPage, {
      attachTo: document.body,
      global: { stubs: { UgcPaymentOverlay: overlayStub } },
    })

  beforeEach(() => {
    vi.clearAllMocks()
    routeQuery.value = {}
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('opens the commission overlay when a card emits pay-commission', async () => {
    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-overlay-stub"]').exists()).toBe(false)

    await wrapper.find('[data-testid="pay-commission-ugc-mission-1"]').trigger('click')

    expect(wrapper.find('[data-testid="ugc-overlay-stub"]').exists()).toBe(true)
  })

  it('auto-opens the commission overlay when arriving with ?pay={missionId}', async () => {
    routeQuery.value = { pay: 'ugc-mission-1' }

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-overlay-stub"]').exists()).toBe(true)
  })
})
