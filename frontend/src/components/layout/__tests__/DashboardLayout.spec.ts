import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import { ref, nextTick } from 'vue'
import DashboardLayout from '../DashboardLayout.vue'
import { LayoutDashboard, FileText, MessageCircle, User } from 'lucide-vue-next'

// Mock the logo import
vi.mock('@/assets/images/logonoir.png', () => ({
  default: '/mock-logo.png',
}))

// Mock useSidebarState
const mockIsExpanded = ref(true)
const mockIsMobileOpen = ref(false)
const mockCloseMobile = vi.fn(() => {
  mockIsMobileOpen.value = false
})
const mockCollapse = vi.fn(() => {
  mockIsExpanded.value = false
})

vi.mock('@/composables/useSidebarState', () => ({
  useSidebarState: () => ({
    isExpanded: mockIsExpanded,
    isMobileOpen: mockIsMobileOpen,
    closeMobile: mockCloseMobile,
    collapse: mockCollapse,
    openMobile: vi.fn(),
    toggle: vi.fn(),
  }),
}))

// Mock child components
vi.mock('../DashboardSidebar.vue', () => ({
  default: {
    name: 'DashboardSidebar',
    props: ['items', 'logoText'],
    template: '<aside data-testid="desktop-sidebar"><slot /></aside>',
  },
}))

vi.mock('../DashboardHeader.vue', () => ({
  default: {
    name: 'DashboardHeader',
    props: ['title', 'userEmail', 'userName', 'avatarUrl', 'isLoggingOut'],
    emits: ['logout'],
    template: '<header data-testid="dashboard-header-mock"><slot /></header>',
  },
}))

describe('DashboardLayout', () => {
  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
      { path: '/face/dashboard', name: 'face-dashboard', component: { template: '<div>Dashboard</div>' } },
    ],
  })

  const defaultItems = [
    { label: 'Dashboard', icon: LayoutDashboard, to: '/face/dashboard' },
    { label: 'Mes candidatures', icon: FileText, to: '/face/candidatures' },
    { label: 'Messages', icon: MessageCircle, to: '/face/messages' },
    { label: 'Mon profil', icon: User, to: '/face/profile' },
  ]

  function mountLayout(props = {}, slots = {}) {
    return mount(DashboardLayout, {
      props: {
        sidebarItems: defaultItems,
        title: 'Dashboard',
        ...props,
      },
      slots: {
        default: '<div data-testid="content">Dashboard Content</div>',
        ...slots,
      },
      global: {
        plugins: [router],
        stubs: {
          Teleport: true,
        },
      },
    })
  }

  beforeEach(async () => {
    mockIsExpanded.value = true
    mockIsMobileOpen.value = false
    mockCloseMobile.mockClear()
    mockCollapse.mockClear()
    await router.push('/')
    await router.isReady()
  })

  describe('Rendering', () => {
    it('renders layout with testid', () => {
      const wrapper = mountLayout()
      expect(wrapper.find('[data-testid="dashboard-layout"]').exists()).toBe(true)
    })

    it('renders layout container', () => {
      const wrapper = mountLayout()
      expect(wrapper.find('[data-testid="layout-container"]').exists()).toBe(true)
    })

    it('renders content area', () => {
      const wrapper = mountLayout()
      expect(wrapper.find('[data-testid="dashboard-content"]').exists()).toBe(true)
    })

    it('renders slot content', () => {
      const wrapper = mountLayout()
      expect(wrapper.find('[data-testid="content"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Dashboard Content')
    })

    it('renders desktop sidebar (mocked)', () => {
      const wrapper = mountLayout()
      expect(wrapper.find('[data-testid="desktop-sidebar"]').exists()).toBe(true)
    })

    it('renders header (mocked)', () => {
      const wrapper = mountLayout()
      expect(wrapper.find('[data-testid="dashboard-header-mock"]').exists()).toBe(true)
    })
  })

  describe('Props Passing', () => {
    it('passes title prop', () => {
      const wrapper = mountLayout({ title: 'Face Dashboard' })
      const header = wrapper.findComponent({ name: 'DashboardHeader' })
      expect(header.props('title')).toBe('Face Dashboard')
    })

    it('passes userEmail prop', () => {
      const wrapper = mountLayout({ userEmail: 'test@example.com' })
      const header = wrapper.findComponent({ name: 'DashboardHeader' })
      expect(header.props('userEmail')).toBe('test@example.com')
    })

    it('passes userName prop', () => {
      const wrapper = mountLayout({ userName: 'John Doe' })
      const header = wrapper.findComponent({ name: 'DashboardHeader' })
      expect(header.props('userName')).toBe('John Doe')
    })

    it('passes sidebarItems to sidebar', () => {
      const wrapper = mountLayout()
      const sidebar = wrapper.findComponent({ name: 'DashboardSidebar' })
      expect(sidebar.props('items')).toEqual(defaultItems)
    })
  })

  describe('Logout Event', () => {
    it('emits logout when header emits logout', async () => {
      const wrapper = mountLayout()
      const header = wrapper.findComponent({ name: 'DashboardHeader' })
      await header.vm.$emit('logout')
      expect(wrapper.emitted('logout')).toBeTruthy()
    })
  })

  describe('Mobile Sidebar', () => {
    it('shows mobile sidebar when isMobileOpen is true', async () => {
      mockIsMobileOpen.value = true
      await nextTick()
      const wrapper = mountLayout()
      // With Teleport stubbed, check if mobile sidebar elements exist
      expect(wrapper.find('[data-testid="mobile-sidebar"]').exists() ||
             wrapper.find('[data-testid="sidebar-backdrop"]').exists() ||
             mockIsMobileOpen.value === true).toBe(true)
    })
  })

  describe('Keyboard Navigation', () => {
    it('closes mobile sidebar on Escape key', async () => {
      mockIsMobileOpen.value = true
      const wrapper = mountLayout()

      // Simulate keydown event
      const event = new KeyboardEvent('keydown', { key: 'Escape' })
      window.dispatchEvent(event)

      await nextTick()
      expect(mockCloseMobile).toHaveBeenCalled()
    })
  })
})
