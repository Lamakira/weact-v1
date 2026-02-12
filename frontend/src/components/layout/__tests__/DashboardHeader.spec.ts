import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import DashboardHeader from '../DashboardHeader.vue'

// Mock useSidebarState
const mockOpenMobile = vi.fn()

vi.mock('@/composables/useSidebarState', () => ({
  useSidebarState: () => ({
    openMobile: mockOpenMobile,
  }),
}))

// Mock NotificationBell component
vi.mock('@/features/notification/components/NotificationBell.vue', () => ({
  default: {
    name: 'NotificationBell',
    template: '<div data-testid="header-notifications">Notifications</div>',
  },
}))

describe('DashboardHeader', () => {
  function mountHeader(props = {}) {
    return mount(DashboardHeader, {
      props: {
        title: 'Dashboard',
        userEmail: 'test@example.com',
        userName: 'John Doe',
        ...props,
      },
      global: {
        stubs: {
          NotificationBell: {
            template: '<div data-testid="header-notifications">Notifications</div>',
          },
        },
      },
    })
  }

  beforeEach(() => {
    mockOpenMobile.mockClear()
  })

  describe('Rendering', () => {
    it('renders header with testid', () => {
      const wrapper = mountHeader()
      expect(wrapper.find('[data-testid="dashboard-header"]').exists()).toBe(true)
    })

    it('displays title badge', () => {
      const wrapper = mountHeader({ title: 'Face Dashboard' })
      expect(wrapper.find('[data-testid="header-title"]').text()).toBe('Face Dashboard')
    })

    it('displays user email', () => {
      const wrapper = mountHeader({ userEmail: 'user@test.com' })
      expect(wrapper.find('[data-testid="header-user-email"]').text()).toBe('user@test.com')
    })

    it('displays hamburger menu button', () => {
      const wrapper = mountHeader()
      expect(wrapper.find('[data-testid="header-menu-button"]').exists()).toBe(true)
    })

    it('displays logout button', () => {
      const wrapper = mountHeader()
      expect(wrapper.find('[data-testid="header-logout-button"]').exists()).toBe(true)
    })

    it('displays avatar', () => {
      const wrapper = mountHeader()
      expect(wrapper.find('[data-testid="header-avatar"]').exists()).toBe(true)
    })

    it('displays notification bell', () => {
      const wrapper = mountHeader()
      expect(wrapper.find('[data-testid="header-notifications"]').exists()).toBe(true)
    })
  })

  describe('Avatar Display', () => {
    it('shows avatar image when avatarUrl is provided', () => {
      const wrapper = mountHeader({ avatarUrl: 'https://example.com/avatar.jpg' })
      const avatar = wrapper.find('[data-testid="header-avatar"]')
      expect(avatar.find('img').exists()).toBe(true)
      expect(avatar.find('img').attributes('src')).toBe('https://example.com/avatar.jpg')
    })

    it('shows initials when no avatarUrl', () => {
      const wrapper = mountHeader({ userName: 'John Doe', avatarUrl: null })
      const avatar = wrapper.find('[data-testid="header-avatar"]')
      expect(avatar.find('img').exists()).toBe(false)
      expect(avatar.text()).toBe('J')
    })

    it('uses email initial when no userName', () => {
      const wrapper = mountHeader({ userName: '', userEmail: 'test@example.com', avatarUrl: null })
      const avatar = wrapper.find('[data-testid="header-avatar"]')
      expect(avatar.text()).toBe('T')
    })
  })

  describe('Mobile Menu', () => {
    it('calls openMobile when hamburger button is clicked', async () => {
      const wrapper = mountHeader()
      await wrapper.find('[data-testid="header-menu-button"]').trigger('click')
      expect(mockOpenMobile).toHaveBeenCalled()
    })
  })

  describe('Logout', () => {
    it('emits logout event when logout button is clicked', async () => {
      const wrapper = mountHeader()
      await wrapper.find('[data-testid="header-logout-button"]').trigger('click')
      expect(wrapper.emitted('logout')).toBeTruthy()
    })

    it('shows loading state when isLoggingOut is true', () => {
      const wrapper = mountHeader({ isLoggingOut: true })
      expect(wrapper.find('[data-testid="header-logout-button"]').text()).toContain('Déconnexion...')
    })

    it('disables logout button when isLoggingOut is true', () => {
      const wrapper = mountHeader({ isLoggingOut: true })
      expect(wrapper.find('[data-testid="header-logout-button"]').attributes('disabled')).toBeDefined()
    })
  })
})
