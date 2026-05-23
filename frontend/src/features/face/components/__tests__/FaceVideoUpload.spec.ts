import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import FaceVideoUpload from '../FaceVideoUpload.vue'
import type { FaceVideo } from '../../types'

global.URL.createObjectURL = vi.fn(() => 'blob:mock-url')
global.URL.revokeObjectURL = vi.fn()

function makeVideo(overrides: Partial<FaceVideo>): FaceVideo {
  return {
    id: overrides.id ?? 'uuid-default',
    type: overrides.type ?? 'acting',
    video_url: overrides.video_url ?? 'http://x/v.mp4',
    thumbnail_url: overrides.thumbnail_url ?? 'http://x/v.jpg',
    position: overrides.position ?? 1,
  }
}

describe.each([
  { type: 'acting' as const, label: 'Acting' },
  { type: 'ugc' as const, label: 'UGC' },
])('FaceVideoUpload — type=$type', ({ type, label }) => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders the tier-locked banner with no drop zone, no file input, when maxForType=0', () => {
    const wrapper = mount(FaceVideoUpload, {
      props: { type, videos: [], maxForType: 0 },
    })

    expect(wrapper.find('[data-testid="face-video-tier-locked-banner"]').exists()).toBe(true)
    expect(wrapper.find(`[data-testid="face-video-dropzone-${type}"]`).exists()).toBe(false)
    expect(wrapper.find('[data-testid="face-video-add-cta"]').exists()).toBe(false)
    // AC #8 invariant: no hidden file input when section is tier-locked
    expect(wrapper.find(`[data-testid="face-video-file-input-${type}"]`).exists()).toBe(false)

    const banner = wrapper.find('[data-testid="face-video-tier-locked-banner"]')
    if (type === 'acting') {
      expect(banner.text()).toContain("L'ajout d'une vidéo Acting est réservé aux abonnés payants.")
    } else {
      expect(banner.text()).toContain("L'ajout d'une vidéo UGC est réservé aux abonnés Élite.")
    }
  })

  it('renders the drop zone + add CTA when empty and maxForType=1', () => {
    const wrapper = mount(FaceVideoUpload, {
      props: { type, videos: [], maxForType: 1 },
    })

    expect(wrapper.find(`[data-testid="face-video-dropzone-${type}"]`).exists()).toBe(true)
    expect(wrapper.find('[data-testid="face-video-add-cta"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="face-video-tier-locked-banner"]').exists()).toBe(false)
    expect(wrapper.find(`[data-testid="face-video-file-input-${type}"]`).exists()).toBe(true)
    expect(wrapper.find('[data-testid="face-video-add-cta"]').text()).toBe(`Ajouter une vidéo ${label}`)
  })

  it('renders a card without lock badge, hides add CTA, when one video at position 1 with maxForType=1', () => {
    const video = makeVideo({ id: 'v1', type, position: 1 })
    const wrapper = mount(FaceVideoUpload, {
      props: { type, videos: [video], maxForType: 1 },
    })

    expect(wrapper.find(`[data-testid="face-video-card-${video.id}"]`).exists()).toBe(true)
    expect(wrapper.find(`[data-testid="face-video-lock-badge-${video.id}"]`).exists()).toBe(false)
    expect(wrapper.find('[data-testid="face-video-add-cta"]').exists()).toBe(false)
    expect(wrapper.find(`[data-testid="face-video-dropzone-${type}"]`).exists()).toBe(false)
  })

  it('renders a card with tier_below_required lock + banner when maxForType=0 and the video exists', () => {
    const video = makeVideo({ id: 'v1', type, position: 1 })
    const wrapper = mount(FaceVideoUpload, {
      props: { type, videos: [video], maxForType: 0 },
    })

    const card = wrapper.find(`[data-testid="face-video-card-${video.id}"]`)
    expect(card.exists()).toBe(true)
    expect(card.attributes('data-lock-reason')).toBe('tier_below_required')

    const badge = wrapper.find(`[data-testid="face-video-lock-badge-${video.id}"]`)
    expect(badge.exists()).toBe(true)
    expect(badge.text()).toContain('Visible en privé')
    expect(badge.attributes('title')).toBe(
      `Cette vidéo n'est pas visible publiquement — votre formule actuelle ne permet pas de vidéo ${label}.`,
    )

    expect(wrapper.find('[data-testid="face-video-tier-locked-banner"]').exists()).toBe(true)
    // Delete button stays enabled (owner can free their own quota).
    const deleteBtn = wrapper.find(`[data-testid="face-video-delete-${video.id}"]`)
    expect(deleteBtn.attributes('disabled')).toBeUndefined()
  })
})

describe('FaceVideoUpload — acting-specific (2-position quota scenarios)', () => {
  it('renders 2 cards without lock badges and hides the add CTA for Élite (2/2)', () => {
    const videos: FaceVideo[] = [
      makeVideo({ id: 'a1', type: 'acting', position: 1 }),
      makeVideo({ id: 'a2', type: 'acting', position: 2 }),
    ]
    const wrapper = mount(FaceVideoUpload, {
      props: { type: 'acting', videos, maxForType: 2 },
    })

    expect(wrapper.findAll('[data-testid^="face-video-card-"]').length).toBe(2)
    expect(wrapper.find('[data-testid="face-video-lock-badge-a1"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="face-video-lock-badge-a2"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="face-video-add-cta"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="face-video-tier-locked-banner"]').exists()).toBe(false)
  })

  it('renders a quota_exceeded badge on position-2 after a Élite→Pro downgrade (maxForType=1)', () => {
    const videos: FaceVideo[] = [
      makeVideo({ id: 'a1', type: 'acting', position: 1 }),
      makeVideo({ id: 'a2', type: 'acting', position: 2 }),
    ]
    const wrapper = mount(FaceVideoUpload, {
      props: { type: 'acting', videos, maxForType: 1 },
    })

    // Position-1 card has no badge.
    expect(wrapper.find('[data-testid="face-video-lock-badge-a1"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="face-video-card-a1"]').attributes('data-lock-reason')).toBe('')

    // Position-2 card has quota_exceeded badge.
    const card2 = wrapper.find('[data-testid="face-video-card-a2"]')
    expect(card2.attributes('data-lock-reason')).toBe('quota_exceeded')
    const badge2 = wrapper.find('[data-testid="face-video-lock-badge-a2"]')
    expect(badge2.exists()).toBe(true)
    expect(badge2.attributes('title')).toContain('limite à 1 vidéo Acting')

    // Add CTA hidden (2 >= 1).
    expect(wrapper.find('[data-testid="face-video-add-cta"]').exists()).toBe(false)
    // Banner NOT shown (the type is still available, just over-quota).
    expect(wrapper.find('[data-testid="face-video-tier-locked-banner"]').exists()).toBe(false)
  })
})

describe('FaceVideoUpload — interactions', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('emits navigate-pricing once when the tier-locked CTA is clicked', async () => {
    const wrapper = mount(FaceVideoUpload, {
      props: { type: 'acting', videos: [], maxForType: 0 },
    })

    await wrapper.find('[data-testid="face-video-tier-locked-cta"]').trigger('click')

    expect(wrapper.emitted('navigate-pricing')).toHaveLength(1)
    expect(wrapper.emitted('navigate-pricing')?.[0]).toEqual([])
  })

  it('opens the confirm modal and emits delete with the videoId on confirm', async () => {
    const video = makeVideo({ id: 'v1', type: 'acting', position: 1 })
    const wrapper = mount(FaceVideoUpload, {
      props: { type: 'acting', videos: [video], maxForType: 1 },
      attachTo: document.body,
    })

    await wrapper.find(`[data-testid="face-video-delete-${video.id}"]`).trigger('click')
    await flushPromises()

    // ConfirmModal is teleported to body; query body for its buttons.
    const confirmBtn = document.body.querySelector('[data-testid="confirm-modal-confirm"]') as HTMLElement | null
    // ConfirmModal exposes confirm via a button — fallback to clicking a button with text "Supprimer"
    if (confirmBtn) {
      confirmBtn.click()
    } else {
      // Find the modal's confirm button (text "Supprimer")
      const buttons = Array.from(document.body.querySelectorAll('button')) as HTMLButtonElement[]
      const target = buttons.find((b) => b.textContent?.trim() === 'Supprimer' && b.closest('.fixed') !== null)
      target?.click()
    }
    await flushPromises()

    expect(wrapper.emitted('delete')).toBeTruthy()
    expect(wrapper.emitted('delete')?.[0]).toEqual([video.id])

    wrapper.unmount()
  })

  it('emits upload with the file when a file is selected via the hidden input', async () => {
    const wrapper = mount(FaceVideoUpload, {
      props: { type: 'acting', videos: [], maxForType: 1 },
    })

    const input = wrapper.find('[data-testid="face-video-file-input-acting"]')
    const file = new File(['x'], 'video.mp4', { type: 'video/mp4' })
    Object.defineProperty(input.element, 'files', { value: [file], writable: false })

    await input.trigger('change')

    expect(wrapper.emitted('upload')).toHaveLength(1)
    expect(wrapper.emitted('upload')?.[0]).toEqual([file])
    expect(URL.createObjectURL).toHaveBeenCalledWith(file)
  })

  it('shows the upload progress overlay with the percentage when isUploading=true', () => {
    const wrapper = mount(FaceVideoUpload, {
      props: {
        type: 'acting',
        videos: [],
        maxForType: 1,
        isUploading: true,
        uploadProgress: { loaded: 42, total: 100, percentage: 42 },
      },
    })

    const overlay = wrapper.find('[data-testid="face-video-upload-progress"]')
    expect(overlay.exists()).toBe(true)
    expect(overlay.text()).toContain('42%')
    // Add CTA disabled during upload
    const addBtn = wrapper.find('[data-testid="face-video-add-cta"]')
    expect(addBtn.attributes('disabled')).toBeDefined()
  })

  it('revokes a dangling previewUrl when the component unmounts (P3 — blob URL leak fix)', async () => {
    const revokeSpy = vi.mocked(URL.revokeObjectURL)
    revokeSpy.mockClear()

    const wrapper = mount(FaceVideoUpload, {
      props: { type: 'acting', videos: [], maxForType: 1 },
    })

    // Simulate a file pick → previewUrl is set internally.
    const input = wrapper.find('[data-testid="face-video-file-input-acting"]')
    const file = new File(['x'], 'video.mp4', { type: 'video/mp4' })
    Object.defineProperty(input.element, 'files', { value: [file], writable: false })
    await input.trigger('change')

    // Unmount the component — the onBeforeUnmount hook should revoke the URL.
    wrapper.unmount()

    expect(revokeSpy).toHaveBeenCalledWith('blob:mock-url')
  })
})
