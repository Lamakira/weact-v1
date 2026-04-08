import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import type { AdminMissionData } from '@/features/admin/services/adminMissionsApi'
import AdminMissionDetailPage from '../AdminMissionDetailPage.vue'

const mockFetchMission = vi.fn()

let missionRef = ref<AdminMissionData | null>(null)
let isLoadingRef = ref(false)
let errorRef = ref<string | null>(null)

vi.mock('@/features/admin/composables/useAdminMissions', () => ({
  useAdminMissions: () => ({
    mission: missionRef,
    isLoading: isLoadingRef,
    error: errorRef,
    fetchMission: mockFetchMission,
  }),
}))

function makeMission(overrides: Partial<AdminMissionData> = {}): AdminMissionData {
  return {
    id: 1,
    titre: 'Pub TV Savon',
    description: 'Une publicité pour du savon',
    date_tournage: '2026-03-01T00:00:00.000Z',
    profil_recherche: 'Jeune femme dynamique',
    budget: 150000,
    date_limite_candidature: '2026-02-20T00:00:00.000Z',
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    type_mission_autre: null,
    genre_voulu: 'femme',
    genre_voulu_label: 'Femme',
    lieu: 'Cotonou, Bénin',
    duree: '2 journées (16h)',
    status: 'published',
    status_label: 'Publiée',
    is_accepting_candidatures: true,
    candidatures_count: 5,
    producer: {
      id: 10,
      type: 'agency',
      agency_name: 'Studio Lumière',
      first_name: 'Jean',
      last_name: 'Dupont',
      display_name: 'Studio Lumière',
      profile_photo_url: null,
      thumbnail_url: null,
    },
    created_at: '2026-01-15T00:00:00.000Z',
    updated_at: '2026-01-15T00:00:00.000Z',
    ...overrides,
  }
}

async function mountPage(mission: AdminMissionData) {
  missionRef.value = mission
  isLoadingRef.value = false
  errorRef.value = null

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/missions', name: 'admin-missions-list', component: { template: '<div>List</div>' } },
      { path: '/admin/producers/:id', name: 'admin-producer-detail', component: { template: '<div>Producer</div>' } },
      { path: '/admin/missions/:id', name: 'admin-mission-detail', component: AdminMissionDetailPage },
    ],
  })

  await router.push('/admin/missions/1')
  await router.isReady()

  const wrapper = mount(AdminMissionDetailPage, {
    global: {
      plugins: [router],
    },
  })

  await flushPromises()

  return wrapper
}

describe('AdminMissionDetailPage', () => {
  beforeEach(() => {
    missionRef = ref<AdminMissionData | null>(null)
    isLoadingRef = ref(false)
    errorRef = ref<string | null>(null)
    mockFetchMission.mockReset()
  })

  it('normalizes legacy mission duration labels with the max prefix', async () => {
    const wrapper = await mountPage(makeMission())

    expect(wrapper.text()).toContain('2 journées (max 16h)')
  })
})
