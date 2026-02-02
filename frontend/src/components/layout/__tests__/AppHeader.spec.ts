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
vi.mock('@/assets/images/logonoir.png', () => ({
  default: '/mock-logo.png',
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
          NotificationBell: {
            template: '<div data-testid="notification-bell-stub">NotificationBell</div>',
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

  describe('Mobile Menu', () => {
    it('displays mobile menu button', () => {
      const wrapper = mountHeader()

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')
      expect(mobileMenuButton.exists()).toBe(true)
    })

    it('mobile menu button has correct accessibility attributes', () => {
      const wrapper = mountHeader()

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')
      expect(mobileMenuButton.attributes('aria-label')).toBe('Ouvrir le menu de navigation')
      expect(mobileMenuButton.attributes('aria-controls')).toBe('mobile-menu')
      expect(mobileMenuButton.attributes('aria-expanded')).toBe('false')
    })

    it('mobile menu is hidden by default', () => {
      const wrapper = mountHeader()

      const mobileMenu = wrapper.find('[data-testid="header-mobile-menu"]')
      expect(mobileMenu.exists()).toBe(false)
    })

    it('clicking mobile menu button opens the menu', async () => {
      const wrapper = mountHeader()

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')
      await mobileMenuButton.trigger('click')

      const mobileMenu = wrapper.find('[data-testid="header-mobile-menu"]')
      expect(mobileMenu.exists()).toBe(true)
    })

    it('updates aria-expanded when menu is opened', async () => {
      const wrapper = mountHeader()

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')
      await mobileMenuButton.trigger('click')

      expect(mobileMenuButton.attributes('aria-expanded')).toBe('true')
    })

    it('clicking mobile menu button again closes the menu', async () => {
      const wrapper = mountHeader()

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')
      // Open menu
      await mobileMenuButton.trigger('click')
      expect(wrapper.find('[data-testid="header-mobile-menu"]').exists()).toBe(true)

      // Close menu
      await mobileMenuButton.trigger('click')
      expect(wrapper.find('[data-testid="header-mobile-menu"]').exists()).toBe(false)
    })

    it('mobile menu contains navigation links for guest', async () => {
      const wrapper = mountHeader()

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')
      await mobileMenuButton.trigger('click')

      const mobileMenu = wrapper.find('[data-testid="header-mobile-menu"]')
      expect(mobileMenu.text()).toContain('Trouver des faces')
      expect(mobileMenu.text()).toContain('Missions')
      expect(mobileMenu.text()).toContain('Ressources')
    })

    it('mobile menu contains CTAs for guest', async () => {
      const wrapper = mountHeader()

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')
      await mobileMenuButton.trigger('click')

      const mobileMenu = wrapper.find('[data-testid="header-mobile-menu"]')
      expect(mobileMenu.text()).toContain('Poster une mission')
      expect(mobileMenu.text()).toContain('Devenir une face')
      expect(mobileMenu.text()).toContain('Se connecter')
    })

    it('mobile menu contains Dashboard for authenticated user', async () => {
      const faceUser = {
        user: {
          id: 1,
          email: 'face@test.com',
          userable_type: 'Face',
          userable_id: 1,
        },
        token: 'test-token',
      }
      const wrapper = mountHeader(faceUser)

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')
      await mobileMenuButton.trigger('click')

      const mobileMenu = wrapper.find('[data-testid="header-mobile-menu"]')
      expect(mobileMenu.text()).toContain('Dashboard')
      expect(mobileMenu.text()).toContain('Déconnexion')
    })

    it('mobile menu closes on route change', async () => {
      const wrapper = mountHeader()

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')
      await mobileMenuButton.trigger('click')
      expect(wrapper.find('[data-testid="header-mobile-menu"]').exists()).toBe(true)

      // Navigate to a different route
      await router.push('/missions')
      await flushPromises()

      expect(wrapper.find('[data-testid="header-mobile-menu"]').exists()).toBe(false)
    })

    it('mobile menu closes on Escape key press', async () => {
      const wrapper = mountHeader()

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')
      await mobileMenuButton.trigger('click')
      expect(wrapper.find('[data-testid="header-mobile-menu"]').exists()).toBe(true)

      // Press Escape key
      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
      await flushPromises()

      expect(wrapper.find('[data-testid="header-mobile-menu"]').exists()).toBe(false)
    })

    // Regression test: Bug fix for menu not opening on first click
    // Issue: Click event bubbled to document, triggering handleClickOutside
    // Fix: Added @click.stop to prevent event propagation
    it('mobile menu opens on first click without being immediately closed', async () => {
      const wrapper = mountHeader()

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')

      // Menu should be closed initially
      expect(wrapper.find('[data-testid="header-mobile-menu"]').exists()).toBe(false)

      // Single click should open the menu
      await mobileMenuButton.trigger('click')
      await flushPromises()

      // Menu should be open after ONE click (regression: was requiring multiple clicks)
      expect(wrapper.find('[data-testid="header-mobile-menu"]').exists()).toBe(true)
    })

    it('mobile logout button has aria-label', async () => {
      const faceUser = {
        user: {
          id: 1,
          email: 'face@test.com',
          userable_type: 'Face',
          userable_id: 1,
        },
        token: 'test-token',
      }
      const wrapper = mountHeader(faceUser)

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')
      await mobileMenuButton.trigger('click')

      const mobileMenu = wrapper.find('[data-testid="header-mobile-menu"]')
      const logoutButton = mobileMenu.find('button[aria-label="Se déconnecter"]')
      expect(logoutButton.exists()).toBe(true)
    })

    // =====================================================================
    // REGRESSION TESTS: Remove redundant elements from mobile menu
    // These elements are already present in the dashboard header/menu,
    // so showing them again in the public header's mobile menu is redundant.
    // These tests should FAIL until the fix is implemented.
    // =====================================================================

    // Bug #1: NotificationBell should NOT appear in mobile menu for authenticated users
    // Reason: The NotificationBell is already displayed in the dashboard header,
    // so showing it again in the public header's mobile menu creates redundancy.
    it('mobile menu should NOT show NotificationBell for authenticated users (redundant - already in dashboard header)', async () => {
      const faceUser = {
        user: {
          id: 1,
          email: 'face@test.com',
          userable_type: 'Face',
          userable_id: 1,
        },
        token: 'test-token',
      }
      const wrapper = mountHeader(faceUser)

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')
      await mobileMenuButton.trigger('click')

      const mobileNotifications = wrapper.find('[data-testid="mobile-notifications"]')
      // Should NOT exist - it's redundant with dashboard header
      expect(mobileNotifications.exists()).toBe(false)
    })

    // Bug #2: "Mes candidatures" link should NOT appear in mobile menu for Face users
    // Reason: This link is already available in the Face dashboard sidebar menu,
    // so showing it in the public header's mobile menu is redundant.
    it('mobile menu should NOT show "Mes candidatures" link for Face users (redundant - already in dashboard menu)', async () => {
      const faceUser = {
        user: {
          id: 1,
          email: 'face@test.com',
          userable_type: 'Face',
          userable_id: 1,
        },
        token: 'test-token',
      }
      const wrapper = mountHeader(faceUser)

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')
      await mobileMenuButton.trigger('click')

      const mobileMenu = wrapper.find('[data-testid="header-mobile-menu"]')
      // Should NOT contain "Mes candidatures" - it's redundant with dashboard menu
      expect(mobileMenu.text()).not.toContain('Mes candidatures')
    })

    // Bug #3: "Mes missions" link should NOT appear in mobile menu for Producer users
    // Reason: This link is already available in the Producer dashboard sidebar menu,
    // so showing it in the public header's mobile menu is redundant.
    it('mobile menu should NOT show "Mes missions" link for Producer users (redundant - already in dashboard menu)', async () => {
      const producerUser = {
        user: {
          id: 2,
          email: 'producer@test.com',
          userable_type: 'Producer',
          userable_id: 1,
        },
        token: 'test-token',
      }
      const wrapper = mountHeader(producerUser)

      const mobileMenuButton = wrapper.find('[data-testid="header-mobile-menu-button"]')
      await mobileMenuButton.trigger('click')

      const mobileMenu = wrapper.find('[data-testid="header-mobile-menu"]')
      // Should NOT contain "Mes missions" - it's redundant with dashboard menu
      expect(mobileMenu.text()).not.toContain('Mes missions')
    })
  })

  describe('NotificationBell', () => {
    it('displays NotificationBell for authenticated Face user', () => {
      const faceUser = {
        user: {
          id: 1,
          email: 'face@test.com',
          userable_type: 'Face',
          userable_id: 1,
        },
        token: 'test-token',
      }
      const wrapper = mountHeader(faceUser)

      const notificationBell = wrapper.find('[data-testid="header-notifications"]')
      expect(notificationBell.exists()).toBe(true)
    })

    it('displays NotificationBell for authenticated Producer user', () => {
      const producerUser = {
        user: {
          id: 2,
          email: 'producer@test.com',
          userable_type: 'Producer',
          userable_id: 1,
        },
        token: 'test-token',
      }
      const wrapper = mountHeader(producerUser)

      const notificationBell = wrapper.find('[data-testid="header-notifications"]')
      expect(notificationBell.exists()).toBe(true)
    })

    it('does not display NotificationBell for guest user', () => {
      const wrapper = mountHeader()

      const notificationBell = wrapper.find('[data-testid="header-notifications"]')
      expect(notificationBell.exists()).toBe(false)
    })
  })
})
