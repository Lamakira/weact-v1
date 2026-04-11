import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { ref } from 'vue'
import NotificationBell from '../NotificationBell.vue'

// Create reactive refs for the store state
const mockUnreadCount = ref(0)

const mockFetchUnreadCount = vi.fn()
const mockFetchNotifications = vi.fn()
const mockMarkAsRead = vi.fn()
const mockMarkAllAsRead = vi.fn()

vi.mock('@/stores/notification', () => ({
  useNotificationStore: () => ({
    unreadCount: mockUnreadCount,
    fetchUnreadCount: mockFetchUnreadCount,
    fetchNotifications: mockFetchNotifications,
    markAsRead: mockMarkAsRead,
    markAllAsRead: mockMarkAllAsRead,
  }),
}))

// Mock storeToRefs to return reactive refs directly
vi.mock('pinia', async () => {
  const actual = await vi.importActual('pinia')
  return {
    ...actual,
    storeToRefs: () => ({
      unreadCount: mockUnreadCount,
    }),
  }
})

// Mock NotificationsDropdown to avoid deep rendering
vi.mock('../NotificationsDropdown.vue', () => ({
  default: {
    name: 'NotificationsDropdown',
    template: '<div data-testid="dropdown" />',
    emits: ['close'],
  },
}))

describe('NotificationBell', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    mockUnreadCount.value = 0
  })

  function mountBell() {
    return mount(NotificationBell, {
      global: {
        stubs: {
          Bell: { template: '<svg data-testid="bell-icon" />' },
        },
      },
    })
  }

  it('renders badge from store unreadCount', () => {
    mockUnreadCount.value = 5
    const wrapper = mountBell()

    expect(wrapper.find('.bg-red-500').exists()).toBe(true)
    expect(wrapper.find('.bg-red-500').text()).toBe('5')
  })

  it('does not render badge when unreadCount is 0', () => {
    mockUnreadCount.value = 0
    const wrapper = mountBell()

    expect(wrapper.find('.bg-red-500').exists()).toBe(false)
  })

  it('displays 9+ when unreadCount exceeds 9', () => {
    mockUnreadCount.value = 15
    const wrapper = mountBell()

    expect(wrapper.find('.bg-red-500').text()).toBe('9+')
  })

  it('does NOT start any polling interval', () => {
    vi.useFakeTimers()
    mountBell()

    vi.advanceTimersByTime(60000)

    expect(mockFetchUnreadCount).not.toHaveBeenCalled()
    vi.useRealTimers()
  })

  it('toggles dropdown on click', async () => {
    const wrapper = mountBell()

    expect(wrapper.findComponent({ name: 'NotificationsDropdown' }).exists()).toBe(false)

    await wrapper.find('button').trigger('click')

    expect(wrapper.findComponent({ name: 'NotificationsDropdown' }).exists()).toBe(true)
  })

  it('does not listen to notification-read or all-read events from dropdown', async () => {
    const wrapper = mountBell()
    await wrapper.find('button').trigger('click')

    const dropdown = wrapper.findComponent({ name: 'NotificationsDropdown' })
    const bellHtml = wrapper.html()
    expect(bellHtml).not.toContain('notification-read')
    expect(bellHtml).not.toContain('all-read')
    expect(dropdown.exists()).toBe(true)
  })
})
