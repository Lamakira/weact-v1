import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { ref } from 'vue'
import ProducerDashboardPage from '../ProducerDashboardPage.vue'
import { dashboardApi } from '@/features/dashboard/services/dashboardApi'
import { producerApi } from '@/features/producer/services/producerApi'
import { messagingApi } from '@/features/messaging/services/messagingApi'
import { useUgcValidationCountStore } from '@/stores/ugcValidationCount'
import type { ProducerDashboardStats, ProducerActiveMission } from '@/features/dashboard/types'
import type { DeliverableReviewItem } from '@/components/ugc/ugc'
import type { ConversationListItem } from '@/features/messaging/types'

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { email: 'producer@example.com', userable: { id: 1, slug: 'studio-awale' } },
  }),
}))

const mockProfile = ref<Record<string, unknown> | null>(null)
const mockFetchProfile = vi.fn().mockResolvedValue(undefined)
vi.mock('@/features/producer/composables/useProducerProfilePhoto', () => ({
  useProducerProfilePhoto: () => ({
    profile: mockProfile,
    isLoading: ref(false),
    fetchProfile: mockFetchProfile,
  }),
}))

vi.mock('@/features/dashboard/services/dashboardApi', () => ({
  dashboardApi: {
    getProducerStats: vi.fn(),
    getProducerActiveMissions: vi.fn(),
  },
}))

vi.mock('@/features/producer/services/producerApi', () => ({
  producerApi: {
    listDeliverablesToReview: vi.fn(),
  },
}))

vi.mock('@/features/messaging/services/messagingApi', () => ({
  messagingApi: {
    getProducerConversations: vi.fn(),
  },
}))

const stub = { template: '<div />' }
function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: stub },
      { path: '/producer/missions', name: 'producer-missions', component: stub },
      { path: '/producer/missions/publish', name: 'publish-mission', component: stub },
      { path: '/producer/missions/:id/candidatures', name: 'producer-mission-candidatures', component: stub },
      { path: '/producer/messages', name: 'producer-messages', component: stub },
      { path: '/producer/conversations/:conversationId', name: 'producer-conversation', component: stub },
      { path: '/producer/ugc/validation', name: 'producer-ugc-validation', component: stub },
      { path: '/producers/:slug', name: 'public-producer-profile', component: stub },
    ],
  })
}

const baseStats: ProducerDashboardStats = {
  published: 4,
  in_progress: 2,
  closed: 3,
  completed: 11,
  total_candidatures: 38,
  unique_collaborators: 31,
  average_rating: 4.8,
  ratings_count: 23,
  acceptance_rate: 87.4,
  average_response_time_hours: 3,
  completed_missions_count: 11,
  rating_distribution: { '5': 19, '4': 3, '3': 1, '2': 0, '1': 0 },
}

function deliverable(overrides: Partial<DeliverableReviewItem> = {}): DeliverableReviewItem {
  return {
    id: 'd-1',
    kind: 'unboxing',
    kind_label: 'Unboxing',
    validation_status: 'in_review',
    validation_status_label: 'En relecture',
    review_note: null,
    submitted_at: new Date(Date.now() - 2 * 3600_000).toISOString(),
    chrono_started_at: new Date().toISOString(),
    deadline_at: new Date().toISOString(),
    review_due_at: new Date(Date.now() + 40 * 3600_000).toISOString(),
    duree_seconds: 30,
    owner_type: 'candidature',
    owner_id: 'c-1',
    face_name: 'Sènami A.',
    product_name: 'Crème Karité Doux',
    video_url: 'https://example.test/v',
    thumbnail_url: null,
    ...overrides,
  }
}

function conversation(overrides: Partial<ConversationListItem> = {}): ConversationListItem {
  return {
    id: 'conv-1',
    candidature_id: 'cand-1',
    mission_title: 'Lookbook Wax',
    other_participant: {
      id: 'f-1',
      name: 'Afiavi Hounkpatin',
      photo_url: null,
      profile_photo_thumbnail_url: null,
      type: 'face',
    },
    latest_message: {
      content: 'Je serai là à 8 h 30',
      sender_name: 'Afiavi',
      is_mine: false,
      created_at: new Date().toISOString(),
    },
    unread_count: 1,
    updated_at: new Date().toISOString(),
    ...overrides,
  }
}

function mission(overrides: Partial<ProducerActiveMission> = {}): ProducerActiveMission {
  return {
    id: 'm-1',
    titre: 'Lookbook Wax',
    type_mission: 'shooting',
    status: 'published',
    status_label: 'Publiée',
    candidatures_count: 12,
    new_candidatures_count: 3,
    confirmed_count: 1,
    faces_wanted: 3,
    ...overrides,
  }
}

function mockAll(opts: {
  stats?: ProducerDashboardStats
  deliverables?: DeliverableReviewItem[]
  conversations?: ConversationListItem[]
  missions?: ProducerActiveMission[]
  missionsTotal?: number
} = {}) {
  vi.mocked(dashboardApi.getProducerStats).mockResolvedValue({ data: opts.stats ?? baseStats, message: 'ok' })
  vi.mocked(producerApi.listDeliverablesToReview).mockResolvedValue({ data: opts.deliverables ?? [] })
  vi.mocked(messagingApi.getProducerConversations).mockResolvedValue({
    data: opts.conversations ?? [],
    meta: { current_page: 1, last_page: 1, per_page: 15, total: (opts.conversations ?? []).length },
  })
  vi.mocked(dashboardApi.getProducerActiveMissions).mockResolvedValue({
    data: opts.missions ?? [],
    meta: { total: opts.missionsTotal ?? (opts.missions ?? []).length },
    message: 'ok',
  })
}

async function mountPage() {
  const router = makeRouter()
  router.push('/')
  await router.isReady()
  const wrapper = mount(ProducerDashboardPage, { global: { plugins: [router] } })
  await flushPromises()
  return { wrapper, router }
}

describe('ProducerDashboardPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    mockProfile.value = {
      display_name: 'Studio Awalé',
      type: 'agency',
      agency_name: 'Studio Awalé',
      agency_logo_url: 'https://example.test/logo.png',
      profile_photo_url: null,
    }
    mockAll()
  })

  describe('header', () => {
    it('shows the agency logo, the name and the rating subtitle', async () => {
      const { wrapper } = await mountPage()

      expect(wrapper.get('[data-testid="profile-name"]').text()).toBe('Studio Awalé')
      expect(wrapper.get('[data-testid="profile-photo-card"] img').attributes('src')).toBe('https://example.test/logo.png')
      expect(wrapper.get('[data-testid="profile-subtitle"]').text()).toBe('Agence · ★ 4,8 (23 avis)')
    })

    it('uses the profile photo for an individual producer', async () => {
      mockProfile.value = {
        display_name: 'Koffi Mensah',
        type: 'individual',
        agency_logo_url: null,
        profile_photo_url: 'https://example.test/photo.png',
      }
      const { wrapper } = await mountPage()

      expect(wrapper.get('[data-testid="profile-photo-card"] img').attributes('src')).toBe('https://example.test/photo.png')
      expect(wrapper.get('[data-testid="profile-subtitle"]').text()).toContain('Producteur indépendant')
    })

    it('falls back to initials without any image', async () => {
      mockProfile.value = { display_name: 'Koffi Mensah', type: 'individual', agency_logo_url: null, profile_photo_url: null }
      const { wrapper } = await mountPage()

      expect(wrapper.find('[data-testid="profile-photo-card"] img').exists()).toBe(false)
      expect(wrapper.get('[data-testid="profile-photo-card"]').text()).toBe('KM')
    })

    it('links « Fiche publique » to /producers/:slug and hides it on mobile', async () => {
      const { wrapper } = await mountPage()
      const link = wrapper.get('[data-testid="public-profile-button"]')

      expect(link.attributes('href')).toBe('/producers/studio-awale')
      expect(link.classes()).toContain('hidden')
      expect(link.classes()).toContain('md:inline-flex')
    })

    it('keeps « Publier une mission » as the primary action, always visible', async () => {
      const { wrapper } = await mountPage()
      const button = wrapper.get('[data-testid="publish-mission-button"]')

      expect(button.attributes('href')).toBe('/producer/missions/publish')
      expect(button.attributes('aria-label')).toBe('Publier une mission')
      expect(button.classes()).not.toContain('hidden')
    })
  })

  describe('KPI row', () => {
    it('renders the 4 mission KPIs with their values and the candidatures context', async () => {
      const { wrapper } = await mountPage()

      expect(wrapper.get('[data-testid="kpi-card-published-value"]').text()).toBe('4')
      expect(wrapper.get('[data-testid="kpi-card-in_progress-value"]').text()).toBe('2')
      expect(wrapper.get('[data-testid="kpi-card-closed-value"]').text()).toBe('3')
      expect(wrapper.get('[data-testid="kpi-card-completed-value"]').text()).toBe('11')
      expect(wrapper.get('[data-testid="kpi-card-published-context"]').text()).toBe('38 candidatures reçues')
    })

    it('shows an error with a working retry when the stats fail', async () => {
      vi.mocked(dashboardApi.getProducerStats).mockRejectedValueOnce(new Error('boom'))
      const { wrapper } = await mountPage()

      expect(wrapper.find('[data-testid="stats-error"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="kpi-cards-grid"]').exists()).toBe(false)

      await wrapper.get('[data-testid="retry-button"]').trigger('click')
      await flushPromises()

      expect(dashboardApi.getProducerStats).toHaveBeenCalledTimes(2)
      expect(wrapper.find('[data-testid="kpi-cards-grid"]').exists()).toBe(true)
    })
  })

  describe('« Livrables à valider » module', () => {
    it('lists at most 5 deliverables with face, product, age and an Examiner link', async () => {
      mockAll({ deliverables: Array.from({ length: 7 }, (_, i) => deliverable({ id: `d-${i}` })) })
      const { wrapper } = await mountPage()

      const rows = wrapper.findAll('[data-testid="to-validate-row"]')
      expect(rows).toHaveLength(5)
      expect(rows[0]!.text()).toContain('Unboxing')
      expect(rows[0]!.text()).toContain('Crème Karité Doux')
      expect(rows[0]!.text()).toContain('Sènami A.')
      expect(rows[0]!.get('[data-testid="to-validate-age"]').text()).toBe('2 h')
      expect(rows[0]!.get('a').attributes('href')).toBe('/producer/ugc/validation')
      expect(wrapper.get('[data-testid="to-validate-count"]').text()).toBe('7')
    })

    it('flags an overdue review in amber', async () => {
      mockAll({
        deliverables: [
          deliverable({
            submitted_at: new Date(Date.now() - 3 * 86_400_000).toISOString(),
            review_due_at: new Date(Date.now() - 86_400_000).toISOString(),
          }),
        ],
      })
      const { wrapper } = await mountPage()

      const age = wrapper.get('[data-testid="to-validate-age"]')
      expect(age.text()).toBe('3 j')
      expect(age.attributes('data-overdue')).toBe('true')
    })

    it('keeps the sidebar badge count in sync with the inbox total', async () => {
      mockAll({ deliverables: [deliverable(), deliverable({ id: 'd-2' })] })
      await mountPage()

      expect(useUgcValidationCountStore().count).toBe(2)
    })

    it('shows an empty state', async () => {
      const { wrapper } = await mountPage()

      expect(wrapper.find('[data-testid="to-validate-empty"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="to-validate-count"]').exists()).toBe(false)
    })

    it('fails alone: the other modules still render', async () => {
      vi.mocked(producerApi.listDeliverablesToReview).mockRejectedValue(new Error('boom'))
      mockAll({ missions: [mission()] })
      vi.mocked(producerApi.listDeliverablesToReview).mockRejectedValue(new Error('boom'))
      const { wrapper } = await mountPage()

      expect(wrapper.find('[data-testid="to-validate-error"]').exists()).toBe(true)
      expect(wrapper.findAll('[data-testid="active-mission-row"]')).toHaveLength(1)
    })
  })

  describe('« Messages non lus » module', () => {
    it('shows the 3 latest unread conversations only, linking to the conversation', async () => {
      mockAll({
        conversations: [
          conversation({ id: 'c1' }),
          conversation({ id: 'c2', unread_count: 0 }),
          conversation({ id: 'c3' }),
          conversation({ id: 'c4' }),
          conversation({ id: 'c5' }),
        ],
      })
      const { wrapper } = await mountPage()

      const rows = wrapper.findAll('[data-testid="unread-message-row"]')
      expect(rows).toHaveLength(3)
      expect(rows[0]!.get('a').attributes('href')).toBe('/producer/conversations/c1')
      expect(rows[1]!.get('a').attributes('href')).toBe('/producer/conversations/c3')
      expect(rows[0]!.text()).toContain('Afiavi Hounkpatin')
      expect(rows[0]!.text()).toContain('Lookbook Wax')
      expect(rows[0]!.text()).toContain('Je serai là à 8 h 30')
      expect(wrapper.get('[data-testid="unread-messages-count"]').text()).toBe('4')
    })

    it('shows initials when the participant has no photo', async () => {
      mockAll({ conversations: [conversation()] })
      const { wrapper } = await mountPage()

      expect(wrapper.get('[data-testid="unread-message-row"]').text()).toContain('AH')
    })

    it('shows an empty state when nothing is unread', async () => {
      mockAll({ conversations: [conversation({ unread_count: 0 })] })
      const { wrapper } = await mountPage()

      expect(wrapper.find('[data-testid="unread-messages-empty"]').exists()).toBe(true)
    })
  })

  describe('« Missions actives » module', () => {
    it('shows candidatures, new ones, faces confirmed vs wanted and a progress bar', async () => {
      mockAll({ missions: [mission()], missionsTotal: 6 })
      const { wrapper } = await mountPage()

      const row = wrapper.get('[data-testid="active-mission-row"]')
      expect(row.text()).toContain('Lookbook Wax')
      expect(row.text()).toContain('Publiée')
      expect(row.get('[data-testid="active-mission-candidatures"]').text()).toBe('12 candidatures')
      expect(row.get('[data-testid="active-mission-new"]').text()).toBe('3 nouvelles')
      expect(row.get('[data-testid="active-mission-faces"]').text()).toBe('1 / 3 Faces confirmées')
      expect(row.get('[data-testid="active-mission-progress"]').attributes('style')).toContain('width: 33%')
      expect(row.get('a[aria-label="Voir la mission Lookbook Wax"]').attributes('href')).toBe('/producer/missions/m-1/candidatures')
      expect(wrapper.get('[data-testid="active-missions-count"]').text()).toBe('6')
    })

    it('labels an in-progress mission « En cours » and omits the new-candidatures note when zero', async () => {
      mockAll({ missions: [mission({ status: 'closed', new_candidatures_count: 0, confirmed_count: 3 })] })
      const { wrapper } = await mountPage()

      const row = wrapper.get('[data-testid="active-mission-row"]')
      expect(row.text()).toContain('En cours')
      expect(row.find('[data-testid="active-mission-new"]').exists()).toBe(false)
      expect(row.get('[data-testid="active-mission-progress"]').attributes('style')).toContain('width: 100%')
    })

    it('hides the progress bar when the mission has no Faces target (UGC)', async () => {
      mockAll({ missions: [mission({ faces_wanted: null })] })
      const { wrapper } = await mountPage()

      expect(wrapper.find('[data-testid="active-mission-progress"]').exists()).toBe(false)
    })

    it('shows an empty state with a publish link', async () => {
      const { wrapper } = await mountPage()

      expect(wrapper.find('[data-testid="active-missions-empty"]').exists()).toBe(true)
      expect(wrapper.get('[data-testid="active-missions-empty"] a').attributes('href')).toBe('/producer/missions/publish')
    })
  })

  describe('« Réputation » module', () => {
    it('shows the average, the reviews count, the distribution and the 3 secondary KPIs', async () => {
      const { wrapper } = await mountPage()

      expect(wrapper.get('[data-testid="reputation-average"]').text()).toBe('4,8')
      expect(wrapper.get('[data-testid="reputation-reviews"]').text()).toBe('23 avis')
      const rows = wrapper.findAll('[data-testid="reputation-distribution"] li')
      expect(rows.map((r) => r.attributes('data-score'))).toEqual(['5', '4', '3', '2', '1'])
      expect(rows[0]!.get('[data-testid="reputation-bar-count"]').text()).toBe('19')
      expect(rows[0]!.get('div > div').attributes('style')).toContain('width: 83%')
      expect(wrapper.get('[data-testid="reputation-acceptance"]').text()).toBe('87 %')
      expect(wrapper.get('[data-testid="reputation-candidatures"]').text()).toBe('38')
      expect(wrapper.get('[data-testid="reputation-collaborators"]').text()).toBe('31')
    })

    it('shows an empty state without reviews but keeps the KPIs', async () => {
      mockAll({
        stats: {
          ...baseStats,
          average_rating: null,
          ratings_count: 0,
          rating_distribution: { '5': 0, '4': 0, '3': 0, '2': 0, '1': 0 },
        },
      })
      const { wrapper } = await mountPage()

      expect(wrapper.get('[data-testid="reputation-average"]').text()).toBe('—')
      expect(wrapper.get('[data-testid="reputation-reviews"]').text()).toBe('Aucun avis')
      expect(wrapper.find('[data-testid="reputation-distribution"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="reputation-empty"]').exists()).toBe(true)
      expect(wrapper.get('[data-testid="reputation-candidatures"]').text()).toBe('38')
    })
  })

  describe('layout', () => {
    it('stacks the modules in one column and splits 2 x 2 from lg', async () => {
      const { wrapper } = await mountPage()
      const grid = wrapper.get('[data-testid="producer-dashboard-modules"]')

      expect(grid.classes()).toContain('grid-cols-1')
      expect(grid.classes()).toContain('lg:grid-cols-2')
      expect(grid.findAll('[data-testid^="module-"]')).toHaveLength(4)
    })

    it('no longer renders the 4 shortcut tiles', async () => {
      const { wrapper } = await mountPage()

      expect(wrapper.find('[data-testid="quick-access-cards-grid"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="candidatures-kpi-grid"]').exists()).toBe(false)
    })
  })
})
