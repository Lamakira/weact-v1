import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import { ref } from 'vue'
import DashboardSidebar from '../DashboardSidebar.vue'
import { LayoutDashboard, FileText, MessageCircle, User } from 'lucide-vue-next'

// Mock the logo import
vi.mock('@/assets/images/logonoir.png', () => ({
  default: '/mock-logo.png',
}))

// Mock useSidebarState
const mockIsExpanded = ref(true)
const mockToggle = vi.fn(() => {
  mockIsExpanded.value = !mockIsExpanded.value
})

vi.mock('@/composables/useSidebarState', () => ({
  useSidebarState: () => ({
    isExpanded: mockIsExpanded,
    toggle: mockToggle,
  }),
}))

describe('DashboardSidebar', () => {
  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
      { path: '/face/dashboard', name: 'face-dashboard', component: { template: '<div>Dashboard</div>' } },
      { path: '/face/candidatures', name: 'face-candidatures', component: { template: '<div>Candidatures</div>' } },
      { path: '/face/messages', name: 'face-messages', component: { template: '<div>Messages</div>' } },
      { path: '/face/profile', name: 'face-profile', component: { template: '<div>Profile</div>' } },
    ],
  })

  const defaultItems = [
    { label: 'Dashboard', icon: LayoutDashboard, to: '/face/dashboard' },
    { label: 'Mes candidatures', icon: FileText, to: '/face/candidatures' },
    { label: 'Messages', icon: MessageCircle, to: '/face/messages' },
    { label: 'Mon profil', icon: User, to: '/face/profile' },
  ]

  function mountSidebar(props = {}) {
    return mount(DashboardSidebar, {
      props: {
        items: defaultItems,
        ...props,
      },
      global: {
        plugins: [router],
      },
    })
  }

  beforeEach(async () => {
    mockIsExpanded.value = true
    mockToggle.mockClear()
    await router.push('/face/dashboard')
    await router.isReady()
  })

  describe('Rendering', () => {
    it('renders sidebar with testid', () => {
      const wrapper = mountSidebar()
      expect(wrapper.find('[data-testid="dashboard-sidebar"]').exists()).toBe(true)
    })

    it('displays logo', () => {
      const wrapper = mountSidebar()
      expect(wrapper.find('[data-testid="sidebar-logo"]').exists()).toBe(true)
    })

    it('renders all navigation items', () => {
      const wrapper = mountSidebar()
      expect(wrapper.find('[data-testid="sidebar-item-dashboard"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="sidebar-item-mes-candidatures"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="sidebar-item-messages"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="sidebar-item-mon-profil"]').exists()).toBe(true)
    })

    it('renders back to site link', () => {
      const wrapper = mountSidebar()
      expect(wrapper.find('[data-testid="sidebar-back-to-site"]').exists()).toBe(true)
    })

    it('renders toggle button', () => {
      const wrapper = mountSidebar()
      expect(wrapper.find('[data-testid="sidebar-toggle"]').exists()).toBe(true)
    })
  })

  describe('Expanded State', () => {
    it('shows labels when expanded', () => {
      mockIsExpanded.value = true
      const wrapper = mountSidebar()
      expect(wrapper.text()).toContain('Dashboard')
      expect(wrapper.text()).toContain('Mes candidatures')
    })

    it('applies expanded width class', () => {
      mockIsExpanded.value = true
      const wrapper = mountSidebar()
      expect(wrapper.find('[data-testid="dashboard-sidebar"]').classes()).toContain('w-64')
    })

    it('shows "Réduire" text on toggle button', () => {
      mockIsExpanded.value = true
      const wrapper = mountSidebar()
      expect(wrapper.find('[data-testid="sidebar-toggle"]').text()).toContain('Réduire')
    })
  })

  describe('Collapsed State', () => {
    it('hides labels when collapsed', () => {
      mockIsExpanded.value = false
      const wrapper = mountSidebar()
      // Labels should not be visible (using v-if)
      const dashboardItem = wrapper.find('[data-testid="sidebar-item-dashboard"]')
      expect(dashboardItem.find('span').exists()).toBe(false)
    })

    it('applies collapsed width class', () => {
      mockIsExpanded.value = false
      const wrapper = mountSidebar()
      expect(wrapper.find('[data-testid="dashboard-sidebar"]').classes()).toContain('w-20')
    })
  })

  describe('Toggle Functionality', () => {
    it('calls toggle when toggle button is clicked', async () => {
      const wrapper = mountSidebar()
      await wrapper.find('[data-testid="sidebar-toggle"]').trigger('click')
      expect(mockToggle).toHaveBeenCalled()
    })
  })

  describe('Active Route Highlighting', () => {
    it('highlights active route', async () => {
      await router.push('/face/dashboard')
      const wrapper = mountSidebar()
      const dashboardItem = wrapper.find('[data-testid="sidebar-item-dashboard"]')
      expect(dashboardItem.classes().some(c => c.includes('primary'))).toBe(true)
    })
  })

  describe('Badge Display', () => {
    it('shows badge when item has badge value', () => {
      const itemsWithBadge = [
        { label: 'Messages', icon: MessageCircle, to: '/face/messages', badge: 5 },
      ]
      const wrapper = mountSidebar({ items: itemsWithBadge })
      expect(wrapper.find('[data-testid="sidebar-badge"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="sidebar-badge"]').text()).toBe('5')
    })

    it('hides badge when value is 0', () => {
      const itemsWithZeroBadge = [
        { label: 'Messages', icon: MessageCircle, to: '/face/messages', badge: 0 },
      ]
      const wrapper = mountSidebar({ items: itemsWithZeroBadge })
      expect(wrapper.find('[data-testid="sidebar-badge"]').exists()).toBe(false)
    })
  })
})
