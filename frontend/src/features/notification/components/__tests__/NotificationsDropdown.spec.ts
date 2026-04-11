import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { ref } from 'vue'
import NotificationsDropdown from '../NotificationsDropdown.vue'

// Mock toast
const mockToastError = vi.fn()
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ error: mockToastError, success: vi.fn(), info: vi.fn() }),
}))

// Mock router
const mockPush = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush }),
}))

// Mock store with reactive refs
const mockItems = ref<Array<{ id: string; type: string; data: { message: string; url?: string }; read_at: string | null; created_at: string }>>([])
const mockIsLoading = ref(false)

const mockFetchNotifications = vi.fn()
const mockMarkAsRead = vi.fn()
const mockMarkAllAsRead = vi.fn()

vi.mock('@/stores/notification', () => ({
  useNotificationStore: () => ({
    items: mockItems,
    isLoading: mockIsLoading,
    fetchNotifications: mockFetchNotifications,
    markAsRead: mockMarkAsRead,
    markAllAsRead: mockMarkAllAsRead,
  }),
}))

// Mock storeToRefs to return the reactive refs directly
vi.mock('pinia', async () => {
  const actual = await vi.importActual('pinia')
  return {
    ...actual,
    storeToRefs: () => ({
      items: mockItems,
      isLoading: mockIsLoading,
    }),
  }
})

function createNotification(overrides: Partial<typeof mockItems.value[0]> = {}) {
  return {
    id: 'uuid-1',
    type: 'candidature_accepted',
    data: { message: 'Test notification' },
    read_at: null,
    created_at: '2026-04-11T10:00:00.000000Z',
    ...overrides,
  }
}

describe('NotificationsDropdown', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    mockItems.value = []
    mockIsLoading.value = false
    mockFetchNotifications.mockResolvedValue(true)
    mockMarkAsRead.mockResolvedValue(true)
    mockMarkAllAsRead.mockResolvedValue(true)
  })

  function mountDropdown() {
    return mount(NotificationsDropdown, {
      global: {
        stubs: {
          CheckCircle: { template: '<svg />' },
          XCircle: { template: '<svg />' },
          Bell: { template: '<svg />' },
          Loader2: { template: '<svg class="animate-spin" />' },
        },
      },
    })
  }

  it('calls store.fetchNotifications() on mount', async () => {
    mountDropdown()
    await flushPromises()

    expect(mockFetchNotifications).toHaveBeenCalledOnce()
  })

  it('shows error toast when fetchNotifications returns false', async () => {
    mockFetchNotifications.mockResolvedValue(false)

    mountDropdown()
    await flushPromises()

    expect(mockToastError).toHaveBeenCalledWith('Impossible de charger les notifications')
  })

  it('calls store.markAsRead(id) when clicking an unread notification', async () => {
    const notification = createNotification({ id: 'notif-1', read_at: null })
    mockItems.value = [notification]

    const wrapper = mountDropdown()
    await flushPromises()

    await wrapper.find('li').trigger('click')
    await flushPromises()

    expect(mockMarkAsRead).toHaveBeenCalledWith('notif-1')
  })

  it('shows error toast when markAsRead returns false', async () => {
    mockMarkAsRead.mockResolvedValue(false)
    const notification = createNotification({ id: 'notif-1', read_at: null })
    mockItems.value = [notification]

    const wrapper = mountDropdown()
    await flushPromises()

    await wrapper.find('li').trigger('click')
    await flushPromises()

    expect(mockToastError).toHaveBeenCalledWith('Impossible de marquer comme lu')
  })

  it('calls store.markAllAsRead() when clicking "Tout marquer comme lu"', async () => {
    mockItems.value = [createNotification({ id: 'notif-1', read_at: null })]

    const wrapper = mountDropdown()
    await flushPromises()

    const markAllBtn = wrapper.findAll('button').find((b) => b.text().includes('Tout marquer comme lu'))
    expect(markAllBtn).toBeDefined()

    await markAllBtn!.trigger('click')
    await flushPromises()

    expect(mockMarkAllAsRead).toHaveBeenCalledOnce()
  })

  it('shows error toast when markAllAsRead returns false', async () => {
    mockMarkAllAsRead.mockResolvedValue(false)
    mockItems.value = [createNotification({ id: 'notif-1', read_at: null })]

    const wrapper = mountDropdown()
    await flushPromises()

    const markAllBtn = wrapper.findAll('button').find((b) => b.text().includes('Tout marquer comme lu'))
    await markAllBtn!.trigger('click')
    await flushPromises()

    expect(mockToastError).toHaveBeenCalledWith('Impossible de marquer tout comme lu')
  })

  it('emits close but NOT notification-read or all-read', async () => {
    mockItems.value = [createNotification({ id: 'notif-1', read_at: null })]

    const wrapper = mountDropdown()
    await flushPromises()

    // Click footer to trigger close
    const footer = wrapper.find('.bg-muted\\/30')
    await footer.trigger('click')

    expect(wrapper.emitted('close')).toBeTruthy()
    expect(wrapper.emitted('notification-read')).toBeUndefined()
    expect(wrapper.emitted('all-read')).toBeUndefined()
  })

  it('emits close when clicking a notification (for dropdown toggle)', async () => {
    const notification = createNotification({ id: 'notif-1', data: { message: 'Test', url: '/some-url' } })
    mockItems.value = [notification]

    const wrapper = mountDropdown()
    await flushPromises()

    await wrapper.find('li').trigger('click')
    await flushPromises()

    expect(wrapper.emitted('close')).toBeTruthy()
  })
})
