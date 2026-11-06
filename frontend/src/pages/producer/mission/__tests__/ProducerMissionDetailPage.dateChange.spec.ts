import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import ProducerMissionDetailPage from '../ProducerMissionDetailPage.vue'
import { missionApi } from '@/features/mission/services/missionApi'
import { dateChangeApi } from '@/features/dateChange/services/dateChangeApi'
import { useAuthStore } from '@/stores/auth'
import type { ProducerMissionDateChange } from '@/features/dateChange/types'
import type { Mission } from '@/features/mission/types'

/** Changement de date côté Producteur : bouton d'en-tête, fenêtre, réponses des Faces. */

globalThis.ResizeObserver ??= class {
  observe() {}
  unobserve() {}
  disconnect() {}
} as unknown as typeof ResizeObserver

const routeState = vi.hoisted(() => ({
  query: {} as Record<string, unknown>,
  replace: vi.fn().mockResolvedValue(undefined),
  push: vi.fn().mockResolvedValue(undefined),
}))
const toastState = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: 'mission-uuid-under-test' }, query: routeState.query }),
  useRouter: () => ({ push: routeState.push, replace: routeState.replace }),
}))
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ ...toastState, clear: vi.fn(), toast: {} }),
}))
vi.mock('@/features/candidature/components', () => ({
  ProducerCandidaturesSection: defineComponent({
    name: 'ProducerCandidaturesSectionStub',
    setup: () => () => h('div', { 'data-testid': 'candidatures-section-stub' }),
  }),
}))
vi.mock('@/features/mission/components/AttendanceValidationPanel.vue', () => ({
  default: defineComponent({ name: 'AttendanceValidationPanelStub', setup: () => () => h('div') }),
}))
vi.mock('@/components/ugc/UgcDeliverableReviewInbox.vue', () => ({
  default: defineComponent({ name: 'UgcDeliverableReviewInboxStub', setup: () => () => h('div') }),
}))
vi.mock('@/features/mission/composables/useMissionPayment', () => ({
  useMissionPayment: () => ({ startPolling: vi.fn(), stopPolling: vi.fn() }),
}))
vi.mock('@/features/mission/services/missionApi', () => ({
  missionApi: {
    getMission: vi.fn(),
    getPaymentStatus: vi.fn(),
    getCommissionStatus: vi.fn(),
    confirmSelection: vi.fn(),
    cancelSelection: vi.fn(),
    addPlaces: vi.fn(),
  },
}))
vi.mock('@/features/dateChange/services/dateChangeApi', () => ({
  dateChangeApi: {
    proposeMission: vi.fn(),
    respondMission: vi.fn(),
    proposeBooking: vi.fn(),
    respondBooking: vi.fn(),
    withdrawBooking: vi.fn(),
  },
}))
vi.mock('@/lib/redirectToCheckout', () => ({ redirectToCheckout: vi.fn() }))
vi.mock('@/features/candidature/services/candidatureApi', () => ({
  candidatureApi: { getCandidaturePaymentStatus: vi.fn() },
}))

function makeDateChange(overrides: Partial<ProducerMissionDateChange> = {}): ProducerMissionDateChange {
  return {
    can_change: true,
    used: 0,
    max: 2,
    faces_to_confirm: 3,
    pending: false,
    latest: null,
    ...overrides,
  }
}

function makeMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 'mission-uuid-under-test',
    titre: 'Spot boutique Haie Vive',
    description: 'Brief',
    date_tournage: '2026-11-15',
    profil_recherche: 'Face',
    budget: 25000,
    date_limite_candidature: '2026-10-19',
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    genre_voulu: 'tous',
    genre_voulu_label: 'Homme et Femme',
    lieu: 'Cotonou',
    duree: '1 jour',
    status: 'closed',
    status_label: 'Clôturée',
    type_compensation: null,
    nom_produit: null,
    montant_remuneration: null,
    commission_ugc: null,
    commission_paid_at: null,
    is_accepting_candidatures: false,
    has_paid_payment: true,
    created_at: '2026-10-01T00:00:00Z',
    updated_at: '2026-10-01T00:00:00Z',
    summary: {
      step: 'confirmations',
      candidatures: { pending: 0, accepted: 0, confirmed: 3, in_progress: 0, completed: 0, rejected: 0, cancelled: 0, expired: 0 },
      selection: { faces_wanted: 3, faces_selected: 3, faces_confirmed: 3, freed_places: 0 },
      payment: { status: 'paid', expires_at: null, paid_at: null, montant_total_producteur: null },
      attendance: null,
      ugc_deliverables: null,
      completion_blocker: null,
      faces_to_rate: 0,
      all_places_refunded: false,
      can_edit: false,
    },
    date_change: makeDateChange(),
    ...overrides,
  } as Mission
}

// La liste déroulante (Reka) est testée ailleurs : un <select> natif suffit pour piloter le motif.
const FloatingSelectStub = {
  props: ['modelValue', 'options', 'id'],
  emits: ['update:modelValue'],
  template:
    '<select :id="id" data-testid="date-change-reason" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option value="">-</option><option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option></select>',
}

let wrapper: VueWrapper | null = null

async function mountPage(mission: Mission) {
  vi.mocked(missionApi.getMission).mockResolvedValue({ data: mission, message: 'ok' })
  wrapper = mount(ProducerMissionDetailPage, {
    attachTo: document.body,
    global: { stubs: { teleport: true, FloatingSelect: FloatingSelectStub } },
  })
  await flushPromises()
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  useAuthStore().user = { email_verified: true } as never
  routeState.query = {}
  routeState.replace.mockClear()
  vi.spyOn(console, 'error').mockImplementation(() => {})
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  document.body.innerHTML = ''
  vi.clearAllMocks()
  vi.restoreAllMocks()
})

describe('ProducerMissionDetailPage — changement de date', () => {
  it('bouton « Changer la date » visible dans les actions d’en-tête si can_change', async () => {
    const w = await mountPage(makeMission())
    const button = w.get('[data-testid="mission-head-actions"] [data-testid="mission-change-date-btn"]')
    expect(button.text()).toContain('Changer la date')
    // visible sur téléphone comme sur ordinateur : aucune classe de masquage
    expect(button.classes()).not.toContain('hidden')
    expect(button.classes().some((c) => c.endsWith(':hidden'))).toBe(false)
  })

  it.each([
    ['can_change faux', makeDateChange({ can_change: false })],
    ['date_change absent', undefined],
    ['date_change null', null],
  ])('bouton absent : %s', async (_label, dateChange) => {
    const w = await mountPage(makeMission({ date_change: dateChange as never }))
    expect(w.find('[data-testid="mission-change-date-btn"]').exists()).toBe(false)
    expect(w.find('[data-testid="date-change-dialog"]').exists()).toBe(false)
  })

  it('le bouton ouvre la fenêtre avec la date actuelle, le compteur et les Faces à confirmer', async () => {
    const w = await mountPage(makeMission({ date_change: makeDateChange({ used: 1, faces_to_confirm: 2 }) }))
    await w.get('[data-testid="mission-change-date-btn"]').trigger('click')

    const dialog = w.get('[data-testid="date-change-dialog"]')
    expect(dialog.get('[data-testid="date-change-current"]').text()).toBe('Dimanche 15 novembre 2026')
    expect(dialog.get('[data-testid="date-change-counter"]').text()).toBe('Changement 2 sur 2')
    expect(dialog.get('[data-testid="date-change-effect-confirm"]').text()).toContain('2 Faces engagées')
  })

  it('après le déplacement, la page utilise la mission renvoyée', async () => {
    const updated = makeMission({
      date_tournage: '2026-11-20',
      date_change: makeDateChange({ can_change: true, used: 1, pending: true }),
    })
    vi.mocked(dateChangeApi.proposeMission).mockResolvedValue({ data: updated, message: 'Date déplacée.' })
    const w = await mountPage(makeMission())
    await w.get('[data-testid="mission-change-date-btn"]').trigger('click')

    const date = '2099-01-10'
    await w.get('input#date-change-new-date').setValue(date)
    await w.get('[data-testid="date-change-reason"]').setValue('meteo')
    await w.get('[data-testid="date-change-submit"]').trigger('click')
    await flushPromises()

    expect(dateChangeApi.proposeMission).toHaveBeenCalledWith('mission-uuid-under-test', { new_date: date, reason: 'meteo' })
    expect(toastState.success).toHaveBeenCalledWith('Date déplacée.')
    expect(w.find('[data-testid="date-change-dialog"]').exists()).toBe(false)
    expect(w.text()).toContain('Tournage ven. 20 nov.')
  })

  it('bloc « Réponses à la nouvelle date » : une ligne par Face, « Remplacer » ouvre l’onglet Candidatures', async () => {
    const mission = makeMission({
      can_replace_freed_place: true,
      date_change: makeDateChange({
        can_change: false,
        used: 1,
        pending: true,
        latest: {
          old_date: '2026-11-15',
          new_date: '2026-11-20',
          reason_label: 'Lieu indisponible',
          status: 'pending',
          respond_by: new Date(Date.now() + 40 * 3600 * 1000).toISOString(),
          responses: [
            { candidature_uuid: 'c1', face_name: 'Awa D.', status: 'confirmed', responded_at: new Date().toISOString() },
            { candidature_uuid: 'c2', face_name: 'Rachida S.', status: 'withdrawn', responded_at: new Date().toISOString() },
          ],
        },
      }),
    })
    const w = await mountPage(mission)

    expect(w.get('[data-testid="date-change-responses"]').exists()).toBe(true)
    expect(w.get('[data-testid="date-change-row-c1"]').text()).toContain('Confirmée')

    routeState.replace.mockClear()
    await w.get('[data-testid="date-change-replace"]').trigger('click')
    expect(routeState.replace).toHaveBeenCalledWith({ query: { tab: 'candidatures' } })
  })

  it('pas de bloc de réponses sans demande', async () => {
    const w = await mountPage(makeMission())
    expect(w.find('[data-testid="date-change-responses"]').exists()).toBe(false)
  })
})
