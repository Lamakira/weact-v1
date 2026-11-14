import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { candidatureApi } from '../../services/candidatureApi'
import { useCandidateProfile } from '../useCandidateProfile'

vi.mock('../../services/candidatureApi', () => ({
  candidatureApi: {
    getCandidateProfile: vi.fn(),
  },
}))

async function load(candidate: Record<string, unknown>) {
  vi.mocked(candidatureApi.getCandidateProfile).mockResolvedValue({ data: candidate } as never)
  const result = useCandidateProfile(ref('face-1'))
  await flushPromises()
  return result
}

describe('useCandidateProfile vidéos', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('hasPortfolioVideos et hasAnyVideos vrais avec une vidéo de portfolio seule', async () => {
    const { hasPortfolioVideos, hasAnyVideos, hasPresentationVideo } = await load({
      presentation_video_url: null,
      videos: [{ id: 'v1', type: 'acting', video_url: 'https://x/a.mp4', thumbnail_url: null, position: 1 }],
    })

    expect(hasPresentationVideo.value).toBe(false)
    expect(hasPortfolioVideos.value).toBe(true)
    expect(hasAnyVideos.value).toBe(true)
  })

  it('ignore les vidéos sans URL', async () => {
    const { hasPortfolioVideos, hasAnyVideos } = await load({
      presentation_video_url: null,
      videos: [{ id: 'v1', type: 'ugc', video_url: null, thumbnail_url: null, position: 1 }],
    })

    expect(hasPortfolioVideos.value).toBe(false)
    expect(hasAnyVideos.value).toBe(false)
  })

  it('tolère l\'absence de la clé videos', async () => {
    const { hasPortfolioVideos } = await load({ presentation_video_url: null })

    expect(hasPortfolioVideos.value).toBe(false)
  })
})
