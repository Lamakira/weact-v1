import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createRouter, createWebHistory } from 'vue-router'
import AppHeader from '../AppHeader.vue'
import { useAuthStore } from '@/stores/auth'

// Mock the useAuth composable
vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    logout: vi.fn().mockResolvedValue(undefined),
    isLoading: false,
  }),
}))

// Mock the useToast composable
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
  }),
}))

// Mock the logo import
vi.mock('@/assets/images/logonoir.svg', () => ({
  default: '/mock-logo.svg',
}))

describe('AppHeader', () => {
  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
      { path: '/login', name: 'login', component: { template: '<div>Login</div>' } },
      { path: '/register/face', name: 'register-face', component: { template: '<div>Register Face</div>' } },
      { path: '/register/producer', name: 'register-producer', component: { template: '<div>Register Producer</div>' } },
      { path: '/face/dashboard', name: 'face-dashboard', component: { template: '<div>Face Dashboard</div>' } },
      { path: '/producer/dashboard', name: 'producer-dashboard', component: { template: '<div>Producer Dashboard</div>' } },
      { path: '/faces', name: 'faces', component: { template: '<div>Faces</div>' } },
      { path: '/missions', name: 'missions', component: { template: '<div>Missions</div>' } },
      { path: '/ressources', name: 'ressources', component: { template: '<div>Ressources</div>' } },
      { path: '/face/candidatures', name: 'face-candidatures', component: { template: '<div>Face Candidatures</div>' } },
      { path: '/producer/missions', name: 'producer-missions', component: { template: '<div>Producer Missions</div>' } },
    ],
  })

  function mountHeader(initialState = {}) {
    return mount(AppHeader, {
      global: {
        plugins: [
          router,
          createTestingPinia({
            createSpy: vi.fn,
            initialState: {
              auth: {
                user: null,
                token: null,
                isLoading: false,
                ...initialState,
              },
            },
          }),
        ],
        stubs: {
          Button: {
            template: '<button><slot /></button>',
            props: ['variant', 'size', 'disabled', 'asChild'],
          },
        },
      },
    })
  }

  beforeEach(async () => {
    await router.push('/')
    await router.isReady()
  })

  describe('Guest Navigation', () => {
    it('displays login link when not authenticated', () => {
      const wrapper = mountHeader()

      expect(wrapper.text()).toContain('Se connecter')
    })

    it('displays register buttons when not authenticated', () => {
      const wrapper = mountHeader()

      expect(wrapper.text()).toContain('Poster une mission')
      expect(wrapper.text()).toContain('Devenir une face')
    })

    it('displays public navigation links', () => {
      const wrapper = mountHeader()

      expect(wrapper.text()).toContain('Trouver des faces')
      expect(wrapper.text()).toContain('Missions')
      expect(wrapper.text()).toContain('Ressources')
    })

    it('does not display Dashboard link when not authenticated', () => {
      const wrapper = mountHeader()

      expect(wrapper.text()).not.toContain('Dashboard')
    })

    it('does not display Déconnexion button when not authenticated', () => {
      const wrapper = mountHeader()

      expect(wrapper.text()).not.toContain('Déconnexion')
    })
  })

  describe('Face Authenticated Navigation', () => {
    const faceUser = {
      user: {
        id: 1,
        email: 'face@test.com',
        userable_type: 'Face',
        userable_id: 1,
      },
      token: 'test-token',
    }

    it('displays Dashboard link for Face user', () => {
      const wrapper = mountHeader(faceUser)

      expect(wrapper.text()).toContain('Dashboard')
    })

    it('displays Déconnexion button for authenticated user', () => {
      const wrapper = mountHeader(faceUser)

      expect(wrapper.text()).toContain('Déconnexion')
    })

    it('does not display login/register buttons when authenticated', () => {
      const wrapper = mountHeader(faceUser)

      expect(wrapper.text()).not.toContain('Se connecter')
      expect(wrapper.text()).not.toContain('Poster une mission')
      expect(wrapper.text()).not.toContain('Devenir une face')
    })

    it('displays Face-specific Mes candidatures link', async () => {
      const wrapper = mountHeader(faceUser)
      const authStore = useAuthStore()

      // Force the computed values to update
      authStore.user = faceUser.user
      authStore.token = faceUser.token
      await flushPromises()

      expect(wrapper.text()).toContain('Mes candidatures')
    })

    it('does not display Producer-specific links for Face user', async () => {
      const wrapper = mountHeader(faceUser)
      const authStore = useAuthStore()

      authStore.user = faceUser.user
      authStore.token = faceUser.token
      await flushPromises()

      expect(wrapper.text()).not.toContain('Mes missions')
    })

    it('links Dashboard to Face dashboard', () => {
      const wrapper = mountHeader(faceUser)
      const authStore = useAuthStore()
      authStore.user = faceUser.user
      authStore.token = faceUser.token

      const dashboardLink = wrapper.find('a[href="/face/dashboard"]')
      expect(dashboardLink.exists()).toBe(true)
    })
  })

  describe('Producer Authenticated Navigation', () => {
    const producerUser = {
      user: {
        id: 2,
        email: 'producer@test.com',
        userable_type: 'Producer',
        userable_id: 1,
      },
      token: 'test-token',
    }

    it('displays Dashboard link for Producer user', () => {
      const wrapper = mountHeader(producerUser)

      expect(wrapper.text()).toContain('Dashboard')
    })

    it('displays Producer-specific Mes missions link', async () => {
      const wrapper = mountHeader(producerUser)
      const authStore = useAuthStore()

      authStore.user = producerUser.user
      authStore.token = producerUser.token
      await flushPromises()

      expect(wrapper.text()).toContain('Mes missions')
    })

    it('does not display Face-specific links for Producer user', async () => {
      const wrapper = mountHeader(producerUser)
      const authStore = useAuthStore()

      authStore.user = producerUser.user
      authStore.token = producerUser.token
      await flushPromises()

      expect(wrapper.text()).not.toContain('Mes candidatures')
    })

    it('links Dashboard to Producer dashboard', () => {
      const wrapper = mountHeader(producerUser)
      const authStore = useAuthStore()
      authStore.user = producerUser.user
      authStore.token = producerUser.token

      const dashboardLink = wrapper.find('a[href="/producer/dashboard"]')
      expect(dashboardLink.exists()).toBe(true)
    })
  })

  describe('Logo', () => {
    it('displays the WEACT logo', () => {
      const wrapper = mountHeader()

      const logo = wrapper.find('img[alt="WEACT"]')
      expect(logo.exists()).toBe(true)
    })

    it('logo links to home page', () => {
      const wrapper = mountHeader()

      const logoLink = wrapper.find('a[href="/"]')
      expect(logoLink.exists()).toBe(true)
    })
  })
})
