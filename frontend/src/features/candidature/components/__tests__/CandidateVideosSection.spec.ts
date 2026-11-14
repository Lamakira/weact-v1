import { describe, it, expect, afterEach } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import { nextTick } from 'vue'
import CandidateVideosSection from '../CandidateVideosSection.vue'
import type { CandidateFullProfile } from '../../types'

function makeCandidate(overrides: Record<string, unknown> = {}): CandidateFullProfile {
  return {
    presentation_video_url: null,
    presentation_video_thumbnail_url: null,
    videos: [],
    ...overrides,
  } as unknown as CandidateFullProfile
}

const acting1 = { id: 'v1', type: 'acting', video_url: 'https://x/a.mp4', thumbnail_url: 'https://x/a.jpg', position: 1 }

describe('CandidateVideosSection', () => {
  let wrapper: VueWrapper | null = null

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    document.body.innerHTML = ''
  })

  it("affiche une tuile Vidéo d'acting pour une vidéo de portfolio (reproduction)", () => {
    wrapper = mount(CandidateVideosSection, {
      props: { candidate: makeCandidate({ videos: [acting1] }) },
    })
    expect(wrapper.text()).toContain("Vidéo d'acting")
    expect(wrapper.text()).not.toContain('Aucune vidéo disponible')
  })

  it('ordonne présentation, acting 1, acting 2, UGC avec les libellés exacts', () => {
    wrapper = mount(CandidateVideosSection, {
      props: {
        candidate: makeCandidate({
          presentation_video_url: 'https://x/p.mp4',
          videos: [
            { id: 'u1', type: 'ugc', video_url: 'https://x/u.mp4', thumbnail_url: null, position: 1 },
            { id: 'a2', type: 'acting', video_url: 'https://x/a2.mp4', thumbnail_url: null, position: 2 },
            acting1,
          ],
        }),
      },
    })

    const tiles = wrapper.findAll('[data-testid^="video-tile-"]')
    expect(tiles.map((t) => t.attributes('data-testid'))).toEqual([
      'video-tile-presentation',
      'video-tile-acting-1',
      'video-tile-acting-2',
      'video-tile-ugc-1',
    ])
    expect(tiles.map((t) => t.text())).toEqual([
      'Vidéo de présentation',
      "Vidéo d'acting",
      "Vidéo d'acting 2",
      'Vidéo UGC',
    ])
  })

  it("marque la tuile verrouillée avec l'étiquette", () => {
    wrapper = mount(CandidateVideosSection, {
      props: {
        candidate: makeCandidate({
          videos: [acting1, { ...acting1, id: 'v2', position: 2, is_locked: true, lock_reason: 'tier' }],
        }),
      },
    })

    expect(wrapper.get('[data-testid="video-tile-acting-2"]').text()).toContain('Non visible : palier actuel')
    expect(wrapper.get('[data-testid="video-tile-acting-1"]').text()).not.toContain('Non visible')
  })

  it("ouvre le lecteur avec la bonne URL et le bon titre au clic", async () => {
    wrapper = mount(CandidateVideosSection, {
      props: {
        candidate: makeCandidate({
          presentation_video_url: 'https://x/p.mp4',
          videos: [acting1],
        }),
      },
      attachTo: document.body,
    })

    await wrapper.get('[data-testid="video-tile-acting-1"]').trigger('click')
    await nextTick()

    const dialog = document.body.querySelector('[role="dialog"]')
    expect(dialog).not.toBeNull()
    expect(dialog?.getAttribute('aria-label')).toBe("Vidéo d'acting")
    expect(dialog?.querySelector('video')?.getAttribute('src')).toBe('https://x/a.mp4')
  })

  it("affiche l'état vide sans présentation ni vidéo de portfolio", () => {
    wrapper = mount(CandidateVideosSection, {
      props: { candidate: makeCandidate() },
    })
    expect(wrapper.text()).toContain('Aucune vidéo disponible')
    expect(wrapper.find('[data-testid^="video-tile-"]').exists()).toBe(false)
  })
})
