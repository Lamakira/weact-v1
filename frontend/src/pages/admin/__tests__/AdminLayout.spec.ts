import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { useAdminAuthStore, type AdminUser } from '@/stores/adminAuth'
import { DashboardLayout, type SidebarItem } from '@/components/layout'
import AdminLayout from '../AdminLayout.vue'

vi.mock('@/features/admin/composables/useAdminAuth', () => ({
  useAdminAuth: () => ({
    logout: vi.fn(),
    isLoading: { value: false },
  }),
}))

function makeAdmin(role: AdminUser['role']): AdminUser {
  return {
    id: 'admin-uuid-1',
    name: 'Admin Test',
    email: 'admin@weact.test',
    role,
  }
}

function mountLayout(role: AdminUser['role']) {
  const pinia = createTestingPinia({ createSpy: vi.fn, stubActions: false })
  const store = useAdminAuthStore(pinia)
  store.admin = makeAdmin(role)
  store.token = 'token-123'

  return mount(AdminLayout, {
    global: {
      plugins: [pinia],
      stubs: {
        DashboardLayout: true,
        RouterView: true,
      },
    },
  })
}

function sidebarLabels(wrapper: ReturnType<typeof mountLayout>): string[] {
  const layout = wrapper.findComponent(DashboardLayout)
  const items = layout.props('sidebarItems') as SidebarItem[]
  return items.map((item) => item.label)
}

describe('AdminLayout - entrée nav Abonnements', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('affiche l\'entrée Abonnements pour un admin', () => {
    const labels = sidebarLabels(mountLayout('admin'))
    expect(labels).toContain('Abonnements')
  })

  it('affiche l\'entrée Abonnements pour un superadmin', () => {
    const labels = sidebarLabels(mountLayout('superadmin'))
    expect(labels).toContain('Abonnements')
  })

  it('masque l\'entrée Abonnements pour un editor (articles uniquement)', () => {
    const labels = sidebarLabels(mountLayout('editor'))
    expect(labels).not.toContain('Abonnements')
    expect(labels).toEqual(['Articles'])
  })
})
