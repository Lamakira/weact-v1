import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, nextTick } from 'vue'
import PublishMissionPage from '../PublishMissionPage.vue'
import { captureAnalyticsEvent } from '@/lib/analytics'

/**
 * Toast honnête sur le statut (story 1.4 AC8 / D-1.4.e) :
 *  - mission `pending_payment` → message UGC (« Réglez la commission pour la publier »)
 *  - mission `published`       → message standard (« Mission publiée avec succès! »)
 * MissionForm est stubbé pour piloter directement son @success.
 */
const { successSpy, errorSpy, pushSpy, getMissionSpy, routeState } = vi.hoisted(() => ({
  successSpy: vi.fn(),
  errorSpy: vi.fn(),
  pushSpy: vi.fn(),
  getMissionSpy: vi.fn(),
  routeState: { query: {} as Record<string, unknown> },
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushSpy }),
  useRoute: () => routeState,
}))

vi.mock('@/features/mission/services/missionApi', () => ({
  missionApi: { getMission: getMissionSpy },
}))

vi.mock('@/lib/analytics', () => ({ captureAnalyticsEvent: vi.fn() }))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { email_verified: true },
    isEmailVerified: true,
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: successSpy, error: errorSpy, warning: vi.fn(), info: vi.fn() }),
}))

const MissionFormStub = defineComponent({
  name: 'MissionForm',
  props: { initialValues: { type: Object, default: undefined } },
  emits: ['success', 'cancel'],
  template: '<div data-testid="mission-form-stub"><input data-testid="stub-input" /><input data-testid="stub-input-2" /></div>',
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

  it('1. mission pending_payment → toast UGC honnête + page détail avec ?pay pour ouvrir le tunnel', async () => {
    const wrapper = mountPage()

    wrapper.findComponent(MissionFormStub).vm.$emit('success', { status: 'pending_payment', id: 'm-1' })
    await nextTick()

    expect(successSpy).toHaveBeenCalledWith('Mission UGC créée. Réglez la commission pour la publier.')
    expect(pushSpy).toHaveBeenCalledWith({
      name: 'producer-mission-detail',
      params: { id: 'm-1' },
      query: { pay: 'm-1' },
    })
  })

  it('2. mission published → toast standard + page détail de la mission', async () => {
    const wrapper = mountPage()

    wrapper.findComponent(MissionFormStub).vm.$emit('success', { status: 'published', id: 'm-2' })
    await nextTick()

    expect(successSpy).toHaveBeenCalledWith('Mission publiée avec succès!')
    expect(pushSpy).toHaveBeenCalledWith({ name: 'producer-mission-detail', params: { id: 'm-2' } })
  })
})

describe('PublishMissionPage — republication (lot C9)', () => {
  const closedMission = {
    id: 'old-uuid',
    titre: 'Spot TV jus de bissap',
    description: 'Brief détaillé',
    profil_recherche: 'Face polyvalente',
    budget: 110000,
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_autre: null,
    genre_voulu: 'femme',
    lieu: 'Cotonou',
    duree: '1 jour',
    date_tournage: '2026-10-23',
    date_limite_candidature: '2026-10-19',
    status: 'closed',
    has_paid_payment: true,
    type_compensation: null,
  }

  beforeEach(() => {
    successSpy.mockClear()
    errorSpy.mockClear()
    pushSpy.mockClear()
    getMissionSpy.mockReset()
    routeState.query = {}
  })

  it('sans ?from : formulaire vierge, aucune lecture de mission', async () => {
    const wrapper = mountPage()
    await flushPromises()

    expect(getMissionSpy).not.toHaveBeenCalled()
    expect(wrapper.findComponent(MissionFormStub).props('initialValues')).toBeUndefined()
    expect(wrapper.find('[data-testid="republish-banner"]').exists()).toBe(false)
  })

  it('?from={uuid} : lit la mission et pré-remplit le formulaire, dates vides, sans paiement ni statut', async () => {
    routeState.query = { from: 'old-uuid' }
    getMissionSpy.mockResolvedValue({ data: closedMission, message: 'ok' })

    const wrapper = mountPage()
    expect(wrapper.find('[data-testid="republish-loading"]').exists()).toBe(true)
    await flushPromises()

    expect(getMissionSpy).toHaveBeenCalledWith('old-uuid')
    const initial = wrapper.findComponent(MissionFormStub).props('initialValues') as Record<string, unknown>
    expect(initial).toMatchObject({
      titre: 'Spot TV jus de bissap',
      description: 'Brief détaillé',
      budget: 110000,
      nombre_faces_voulu: 3,
      type_mission: 'publicite',
      genre_voulu: 'femme',
      lieu: 'Cotonou',
      date_tournage: '',
      date_limite_candidature: '',
    })
    expect(initial).not.toHaveProperty('status')
    expect(initial).not.toHaveProperty('has_paid_payment')
    expect(wrapper.get('[data-testid="republish-banner"]').text()).toContain('Spot TV jus de bissap')
  })

  it('republier publie une NOUVELLE mission et atterrit sur sa page détail', async () => {
    routeState.query = { from: 'old-uuid' }
    getMissionSpy.mockResolvedValue({ data: closedMission, message: 'ok' })
    const wrapper = mountPage()
    await flushPromises()

    wrapper.findComponent(MissionFormStub).vm.$emit('success', { status: 'published', id: 'new-uuid' })
    await nextTick()

    expect(pushSpy).toHaveBeenCalledWith({ name: 'producer-mission-detail', params: { id: 'new-uuid' } })
  })

  it('?from illisible (introuvable, autre Producteur) : toast et formulaire vierge', async () => {
    routeState.query = { from: 'someone-elses' }
    getMissionSpy.mockRejectedValue(new Error('403'))

    const wrapper = mountPage()
    await flushPromises()

    expect(errorSpy).toHaveBeenCalled()
    expect(wrapper.findComponent(MissionFormStub).props('initialValues')).toBeUndefined()
  })
})

describe('PublishMissionPage — mesure d\'usage', () => {
  beforeEach(() => {
    vi.mocked(captureAnalyticsEvent).mockClear()
    routeState.query = {}
  })

  it('mission_publication_commencee au premier focus du formulaire, une seule fois', async () => {
    const wrapper = mountPage()
    expect(captureAnalyticsEvent).not.toHaveBeenCalled()

    await wrapper.get('[data-testid="stub-input"]').trigger('focusin')
    await wrapper.get('[data-testid="stub-input-2"]').trigger('focusin')
    await wrapper.get('[data-testid="stub-input"]').trigger('focusin')

    expect(captureAnalyticsEvent).toHaveBeenCalledTimes(1)
    expect(captureAnalyticsEvent).toHaveBeenCalledWith('mission_publication_commencee', {})
  })

  it('mission_publiee classique : publiée, sans paiement requis', async () => {
    const wrapper = mountPage()

    wrapper.findComponent(MissionFormStub).vm.$emit('success', {
      status: 'published',
      id: 'm-3',
      type_compensation: null,
    })
    await nextTick()

    expect(captureAnalyticsEvent).toHaveBeenCalledTimes(1)
    expect(captureAnalyticsEvent).toHaveBeenCalledWith('mission_publiee', {
      type: 'classique',
      paiement_requis: false,
    })
  })

  it('mission_publiee UGC : en attente de paiement', async () => {
    const wrapper = mountPage()

    wrapper.findComponent(MissionFormStub).vm.$emit('success', {
      status: 'pending_payment',
      id: 'm-4',
      type_compensation: 'product',
    })
    await nextTick()

    expect(captureAnalyticsEvent).toHaveBeenCalledWith('mission_publiee', {
      type: 'ugc',
      paiement_requis: true,
    })
  })
})
