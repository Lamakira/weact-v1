import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ProducerUgcLibraryPage from '../ProducerUgcLibraryPage.vue'
import type { DeliverableAssetItem } from '@/components/ugc'

// Mock du service : la page appelle producerApi.listValidatedDeliverables() au onMounted.
const mockListValidated = vi.fn()
vi.mock('@/features/producer/services/producerApi', () => ({
  producerApi: {
    listValidatedDeliverables: () => mockListValidated(),
  },
}))

function makeAssetItem(overrides: Partial<DeliverableAssetItem> = {}): DeliverableAssetItem {
  return {
    id: 'a1',
    kind: 'unboxing',
    kind_label: 'Unboxing',
    validation_status: 'validated',
    validation_status_label: 'Validé',
    validated_at: '2026-06-14T10:00:00+00:00',
    duree_seconds: 42,
    owner_type: 'booking',
    owner_id: 'booking-uuid-1',
    face_name: 'Aïcha Bello',
    product_name: 'Tenue Shade Fit',
    video_url: 'http://localhost/video?signature=abc',
    thumbnail_url: 'http://localhost/thumb?signature=def',
    download_url: 'http://localhost/download?signature=xyz',
    ...overrides,
  }
}

const item2 = makeAssetItem({
  id: 'a2',
  owner_type: 'candidature',
  face_name: 'Bénédicte Koffi',
  product_name: 'Sneakers Shade Fit',
  kind_label: 'Avis',
  download_url: 'http://localhost/download?signature=second',
})

describe('ProducerUgcLibraryPage', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    mockListValidated.mockResolvedValue({ data: [] })
  })

  it('fetches validated deliverables on mount and renders the grid', async () => {
    mockListValidated.mockResolvedValue({ data: [makeAssetItem(), item2] })
    const wrapper = mount(ProducerUgcLibraryPage)
    await flushPromises()

    expect(mockListValidated).toHaveBeenCalledOnce()
    expect(wrapper.findAll('[data-testid="ugc-library-card"]')).toHaveLength(2)

    const grid = wrapper.get('[data-testid="ugc-library-grid"]').text()
    expect(grid).toContain('Aïcha Bello')
    expect(grid).toContain('Tenue Shade Fit')
    expect(grid).toContain('Bénédicte Koffi')
    expect(grid).toContain('Sneakers Shade Fit')
    expect(grid).toContain('Avis')
  })

  it('renders the empty state when there are no validated videos', async () => {
    mockListValidated.mockResolvedValue({ data: [] })
    const wrapper = mount(ProducerUgcLibraryPage)
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-library-empty"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="ugc-library-grid"]').exists()).toBe(false)
  })

  it('renders a download button per card that triggers the signed download_url', async () => {
    mockListValidated.mockResolvedValue({ data: [makeAssetItem(), item2] })

    let clickedHref: string | null = null
    const clickSpy = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(function (this: HTMLAnchorElement) {
        clickedHref = this.href
      })

    const wrapper = mount(ProducerUgcLibraryPage)
    await flushPromises()

    const downloadButtons = wrapper.findAll('[data-testid="ugc-library-download"]')
    expect(downloadButtons).toHaveLength(2)

    await downloadButtons[0]!.trigger('click')
    expect(clickSpy).toHaveBeenCalledOnce()
    expect(clickedHref).toBe('http://localhost/download?signature=xyz')

    clickSpy.mockRestore()
  })

  it('reveals the inline video preview (signed video_url) when the thumbnail is clicked', async () => {
    mockListValidated.mockResolvedValue({ data: [makeAssetItem()] })
    const wrapper = mount(ProducerUgcLibraryPage)
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-library-video"]').exists()).toBe(false)

    await wrapper.get('[data-testid="ugc-library-preview"]').trigger('click')

    const video = wrapper.get('[data-testid="ugc-library-video"]')
    expect(video.attributes('src')).toBe('http://localhost/video?signature=abc')
  })

  it('shows the error state when the request fails', async () => {
    mockListValidated.mockRejectedValue(new Error('boom'))
    const wrapper = mount(ProducerUgcLibraryPage)
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-library-error"]').exists()).toBe(true)
  })
})
