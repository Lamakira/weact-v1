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
vi.mock('@/assets/images/logo-mark.svg', () => ({
  default: '/mock-logo-mark.svg',
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
      { path: '/face/conversations/:id', name: 'face-conversation', component: { template: '<div>Conversation</div>' } },
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

    it('shows the full logo and an icon-only collapse button next to it', () => {
      mockIsExpanded.value = true
      const wrapper = mountSidebar()
      const toggle = wrapper.find('[data-testid="sidebar-toggle"]')
      expect(wrapper.find('[data-testid="sidebar-logo"] img').attributes('src')).toBe('/mock-logo.png')
      expect(toggle.attributes('aria-label')).toBe('Réduire la barre latérale')
      expect(toggle.text()).toBe('')
      expect(wrapper.find('[data-testid="sidebar-header"]').element.contains(toggle.element)).toBe(true)
      expect(wrapper.find('[data-testid="sidebar-logo-mark"]').exists()).toBe(false)
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

    it('shows only the W mark instead of the full logo, and clicking it expands', async () => {
      mockIsExpanded.value = false
      const wrapper = mountSidebar()
      const mark = wrapper.find('[data-testid="sidebar-logo-mark"]')
      expect(mark.exists()).toBe(true)
      expect(mark.find('img').attributes('src')).toBe('/mock-logo-mark.svg')
      expect(mark.attributes('aria-label')).toBe('Agrandir la barre latérale')
      expect(wrapper.find('[data-testid="sidebar-logo"]').exists()).toBe(false)
      await mark.trigger('click')
      expect(mockToggle).toHaveBeenCalled()
    })

    it('uses the same icon colors as the expanded state', () => {
      const colorClasses = (expanded: boolean) => {
        mockIsExpanded.value = expanded
        const wrapper = mountSidebar()
        const inactive = wrapper.find('[data-testid="sidebar-item-messages"]').classes()
        const active = wrapper.find('[data-testid="sidebar-item-dashboard"]').classes()
        const pick = (cs: string[]) => cs.filter((c) => /^(text-|hover:text-|hover:bg-|bg-)/.test(c)).sort()
        return { inactive: pick(inactive).filter((c) => c !== 'font-medium'), active: pick(active) }
      }
      expect(colorClasses(false)).toEqual(colorClasses(true))
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
      // Actif = panneau blanc à anneau, icône teal 700 (direction Régie)
      expect(dashboardItem.classes()).toEqual(expect.arrayContaining(['bg-white', 'ring-1', 'ring-line']))
      expect(dashboardItem.classes()).toContain('[&_svg]:text-weact-700')
      expect(wrapper.find('[data-testid="sidebar-item-messages"]').classes()).not.toContain('bg-white')
    })

    it('garde Messages actif quand une conversation est ouverte (préfixe match)', async () => {
      await router.push('/face/conversations/abc-123')
      const wrapper = mountSidebar({
        items: defaultItems.map((i) => (i.label === 'Messages' ? { ...i, match: ['/face/conversations'] } : i)),
      })
      expect(wrapper.find('[data-testid="sidebar-item-messages"]').classes()).toContain('bg-white')
      expect(wrapper.find('[data-testid="sidebar-item-dashboard"]').classes()).not.toContain('bg-white')
    })

    it('utilise le fond sidebar et la typographie dense du tableau de bord', () => {
      const wrapper = mountSidebar()
      expect(wrapper.find('[data-testid="dashboard-sidebar"]').classes()).toContain('bg-sidebar')
      expect(wrapper.find('[data-testid="sidebar-item-messages"]').classes()).toContain('text-dash')
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
      expect(wrapper.find('[data-testid="sidebar-badge"]').classes()).toContain('bg-weact-600')
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
