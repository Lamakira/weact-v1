import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import CandidateProfilePage from '../CandidateProfilePage.vue'
import { agendaApi } from '@/features/agenda/services/agendaApi'

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: 'face-uuid-9' } }),
  useRouter: () => ({ back: vi.fn(), push: vi.fn() }),
}))

vi.mock('@/features/candidature/composables', () => ({
  useCandidateProfile: () => ({
    candidate: ref({ id: 'face-uuid-9', username: '', prenom: 'Awa', nom: 'D', photos: [], experiences: [] }),
    isLoading: ref(false),
    error: ref(null),
  }),
}))

vi.mock('@/features/agenda/services/agendaApi', () => ({
  agendaApi: {
    getAgenda: vi.fn(),
    updateUnavailableDays: vi.fn(),
    getFaceAvailability: vi.fn().mockResolvedValue({
      data: { from: '2026-11-01', to: '2026-11-30', today: '2026-11-08', unavailable_days: [] },
    }),
  },
}))

const stubs = {
  CandidateProfileHeader: true,
  CandidateVideosSection: true,
  CandidatePhotoGallery: true,
  CandidateInfoSection: true,
  CandidateExperiencesSection: true,
  RatingDisplay: true,
  ReviewsList: true,
}

describe('CandidateProfilePage — disponibilités', () => {
  it('affiche le calendrier de disponibilités de la Face (uuid de la route)', async () => {
    const wrapper = mount(CandidateProfilePage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.find('[data-testid="face-availability-calendar"]').exists()).toBe(true)
    expect(agendaApi.getFaceAvailability).toHaveBeenCalledWith('face-uuid-9', expect.any(String), expect.any(String))
  })
})
