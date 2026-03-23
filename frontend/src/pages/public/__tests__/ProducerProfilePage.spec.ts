import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { ref, computed } from 'vue'
import ProducerProfilePage from '../ProducerProfilePage.vue'
import type { PublicProducer } from '@/features/public/types'
import type { ReviewsListResponse } from '@/features/rating/types'

// Mock publicApi for reviews
const mockGetProducerReviews = vi.fn()
vi.mock('@/features/public/services/publicApi', () => ({
  publicApi: {
    getProducerReviews: (...args: unknown[]) => mockGetProducerReviews(...args),
  },
}))

const emptyReviewsResponse: ReviewsListResponse = {
  data: [],
  meta: {
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
  },
}

const mockParticulierProducer: PublicProducer = {
  id: 1,
  type: 'particulier',
  display_name: 'Jean Dupont',
  agency_name: null,
  bio: 'Test bio content',
  profile_photo_url: 'https://example.com/photo.jpg',
  thumbnail_url: 'https://example.com/thumb.jpg',
  agency_logo_url: null,
  agency_logo_thumbnail_url: null,
  missions_count: 0,
  average_rating: null,
  ratings_count: 0,
  member_since: 'janvier 2026',
}

const mockAgencyProducer: PublicProducer = {
  id: 2,
  type: 'agency',
  display_name: 'Test Agency',
  agency_name: 'Test Agency',
  bio: 'We are a creative agency',
  profile_photo_url: 'https://example.com/agency-photo.jpg',
  thumbnail_url: 'https://example.com/agency-thumb.jpg',
  agency_logo_url: 'https://example.com/logo.png',
  agency_logo_thumbnail_url: 'https://example.com/logo-thumb.png',
  missions_count: 0,
  average_rating: 4.5,
  ratings_count: 10,
  member_since: 'décembre 2025',
}

// Create reactive refs for the mock composable
const producerRef = ref<PublicProducer | null>(null)
const isLoadingRef = ref(false)
const errorRef = ref<string | null>(null)
const notFoundRef = ref(false)
const isAgencyComputed = computed(() => producerRef.value?.type === 'agency')
const mockFetchProducer = vi.fn()
const mockClearProducer = vi.fn()

vi.mock('@/features/public/composables/usePublicProducer', () => ({
  usePublicProducer: () => ({
    producer: producerRef,
    isLoading: isLoadingRef,
    error: errorRef,
    notFound: notFoundRef,
    isAgency: isAgencyComputed,
    fetchProducer: mockFetchProducer,
    clearProducer: mockClearProducer,
  }),
}))

describe('ProducerProfilePage', () => {
  let router: ReturnType<typeof createRouter>

  beforeEach(() => {
    vi.clearAllMocks()
    // Reset mock composable state
    producerRef.value = null
    isLoadingRef.value = false
    errorRef.value = null
    notFoundRef.value = false
    mockFetchProducer.mockResolvedValue({ success: true })
    mockGetProducerReviews.mockResolvedValue(emptyReviewsResponse)

    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        {
          path: '/producers/:id',
          name: 'public-producer-profile',
          component: ProducerProfilePage,
        },
      ],
    })
  })

  async function mountComponent(producerId: number = 1) {
    await router.push(`/producers/${producerId}`)
    await router.isReady()

    return mount(ProducerProfilePage, {
      global: {
        plugins: [router],
      },
    })
  }

  describe('Loading State', () => {
    it('displays loading state while fetching', async () => {
      isLoadingRef.value = true

      const wrapper = await mountComponent(1)
      await flushPromises()

      expect(wrapper.find('[data-testid="loading-state"]').exists()).toBe(true)
    })

    it('calls fetchProducer on mount with the correct ID', async () => {
      await mountComponent(123)
      await flushPromises()

      expect(mockFetchProducer).toHaveBeenCalledWith(123)
    })
  })

  describe('Not Found State', () => {
    it('displays not found state when producer does not exist', async () => {
      notFoundRef.value = true

      const wrapper = await mountComponent(99999)
      await flushPromises()

      expect(wrapper.find('[data-testid="not-found-state"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Producteur introuvable')
    })

    it('shows appropriate message for not found', async () => {
      notFoundRef.value = true

      const wrapper = await mountComponent(99999)
      await flushPromises()

      expect(wrapper.text()).toContain("n'existe pas ou a été supprimé")
    })
  })

  describe('Error State', () => {
    it('displays error state when fetch fails', async () => {
      errorRef.value = 'Une erreur est survenue. Veuillez réessayer.'

      const wrapper = await mountComponent(1)
      await flushPromises()

      expect(wrapper.find('[data-testid="error-state"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Une erreur est survenue')
    })

    it('has a retry button', async () => {
      errorRef.value = 'Une erreur est survenue.'

      const wrapper = await mountComponent(1)
      await flushPromises()

      const retryButton = wrapper.find('[data-testid="retry-button"]')
      expect(retryButton.exists()).toBe(true)
      expect(retryButton.text()).toContain('Réessayer')
    })

    it('calls fetchProducer again when retry button is clicked', async () => {
      errorRef.value = 'Une erreur est survenue.'

      const wrapper = await mountComponent(1)
      await flushPromises()

      mockFetchProducer.mockClear()

      await wrapper.find('[data-testid="retry-button"]').trigger('click')
      await flushPromises()

      expect(mockFetchProducer).toHaveBeenCalledWith(1)
    })
  })

  describe('Particulier Producer Profile', () => {
    beforeEach(() => {
      producerRef.value = mockParticulierProducer
    })

    it('displays the producer profile', async () => {
      const wrapper = await mountComponent(1)
      await flushPromises()

      expect(wrapper.find('[data-testid="producer-profile"]').exists()).toBe(true)
    })

    it('displays the display name', async () => {
      const wrapper = await mountComponent(1)
      await flushPromises()

      expect(wrapper.find('[data-testid="display-name"]').text()).toBe('Jean Dupont')
    })

    it('displays the bio content', async () => {
      const wrapper = await mountComponent(1)
      await flushPromises()

      expect(wrapper.find('[data-testid="bio-content"]').text()).toBe('Test bio content')
    })

    it('shows Particulier badge', async () => {
      const wrapper = await mountComponent(1)
      await flushPromises()

      const badge = wrapper.find('[data-testid="producer-type-badge"]')
      expect(badge.text()).toBe('Particulier')
    })

    it('displays member since date', async () => {
      const wrapper = await mountComponent(1)
      await flushPromises()

      expect(wrapper.find('[data-testid="member-since"]').text()).toContain('janvier 2026')
    })

    it('displays missions count', async () => {
      const wrapper = await mountComponent(1)
      await flushPromises()

      expect(wrapper.find('[data-testid="missions-count"]').text()).toBe('0')
    })

    it('shows no rating message when average_rating is null', async () => {
      const wrapper = await mountComponent(1)
      await flushPromises()

      expect(wrapper.find('[data-testid="no-rating"]').exists()).toBe(true)
    })

    it('does not display agency logo section for particulier', async () => {
      const wrapper = await mountComponent(1)
      await flushPromises()

      expect(wrapper.find('[data-testid="agency-logo-section"]').exists()).toBe(false)
    })

    it('displays profile photo when available', async () => {
      const wrapper = await mountComponent(1)
      await flushPromises()

      const photo = wrapper.find('[data-testid="profile-photo"]')
      expect(photo.exists()).toBe(true)
      expect(photo.attributes('src')).toBe('https://example.com/thumb.jpg')
    })
  })

  describe('Agency Producer Profile', () => {
    beforeEach(() => {
      producerRef.value = mockAgencyProducer
    })

    it('shows Agence badge', async () => {
      const wrapper = await mountComponent(2)
      await flushPromises()

      const badge = wrapper.find('[data-testid="producer-type-badge"]')
      expect(badge.text()).toBe('Agence')
    })

    it('displays agency logo as main image when available', async () => {
      const wrapper = await mountComponent(2)
      await flushPromises()

      const logo = wrapper.find('[data-testid="agency-logo"]')
      expect(logo.exists()).toBe(true)
      expect(logo.attributes('src')).toBe('https://example.com/logo.png')
    })

    it('displays rating when available', async () => {
      const wrapper = await mountComponent(2)
      await flushPromises()

      const rating = wrapper.find('[data-testid="rating-display"]')
      expect(rating.exists()).toBe(true)
      expect(rating.text()).toContain('4.5')
    })
  })

  describe('No Bio State', () => {
    it('displays placeholder when bio is null', async () => {
      producerRef.value = {
        ...mockParticulierProducer,
        bio: null,
      }

      const wrapper = await mountComponent(1)
      await flushPromises()

      expect(wrapper.find('[data-testid="no-bio"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Aucune description disponible')
    })
  })

  describe('No Profile Photo State', () => {
    it('displays placeholder when profile photo is null', async () => {
      producerRef.value = {
        ...mockParticulierProducer,
        profile_photo_url: null,
        thumbnail_url: null,
      }

      const wrapper = await mountComponent(1)
      await flushPromises()

      expect(wrapper.find('[data-testid="profile-placeholder"]').exists()).toBe(true)
    })
  })

  describe('Navigation', () => {
    it('has a back button', async () => {
      producerRef.value = mockParticulierProducer

      const wrapper = await mountComponent(1)
      await flushPromises()

      const backButton = wrapper.find('[data-testid="back-button"]')
      expect(backButton.exists()).toBe(true)
      expect(backButton.text()).toContain('Retour')
    })
  })

  describe('No Ratings State', () => {
    it('shows no ratings message when producer has no ratings', async () => {
      producerRef.value = mockParticulierProducer

      const wrapper = await mountComponent(1)
      await flushPromises()

      // The ReviewsList component shows the empty state
      expect(wrapper.find('[data-testid="reviews-empty"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Aucune note pour le moment')
    })
  })

  describe('Route Parameter Changes', () => {
    it('fetches new producer when route param changes', async () => {
      producerRef.value = mockParticulierProducer

      await mountComponent(1)
      await flushPromises()

      expect(mockFetchProducer).toHaveBeenCalledWith(1)

      // Navigate to a different producer
      mockFetchProducer.mockClear()
      await router.push('/producers/2')
      await flushPromises()

      expect(mockFetchProducer).toHaveBeenCalledWith(2)
    })

    it('handles invalid (NaN) producer ID gracefully', async () => {
      await router.push('/producers/invalid')
      await router.isReady()

      mount(ProducerProfilePage, {
        global: {
          plugins: [router],
        },
      })
      await flushPromises()

      // Should not call fetchProducer with NaN
      expect(mockFetchProducer).not.toHaveBeenCalled()
    })
  })

  describe('Image Error Handling', () => {
    it('shows placeholder when profile photo fails to load', async () => {
      producerRef.value = {
        ...mockParticulierProducer,
        profile_photo_url: 'https://example.com/broken.jpg',
        thumbnail_url: 'https://example.com/broken-thumb.jpg',
      }

      const wrapper = await mountComponent(1)
      await flushPromises()

      // Initially shows the image
      const photo = wrapper.find('[data-testid="profile-photo"]')
      expect(photo.exists()).toBe(true)

      // Trigger error event
      await photo.trigger('error')
      await flushPromises()

      // Should now show placeholder
      expect(wrapper.find('[data-testid="profile-placeholder"]').exists()).toBe(true)
    })
  })
})
