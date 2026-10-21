import { describe, it, expect } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import UnreadMessagesPanel from '../UnreadMessagesPanel.vue'
import { makeListItem } from '@/features/messaging/__tests__/fixtures'

describe('UnreadMessagesPanel — fils de booking', () => {
  it('liste un fil de booking non lu et ouvre le fil dans Messages', async () => {
    const stub = { template: '<div />' }
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/producer/messages', name: 'producer-messages', component: stub },
        { path: '/producer/conversations/:conversationId', name: 'producer-conversation', component: stub },
      ],
    })
    await router.push('/producer/messages')

    const wrapper = mount(UnreadMessagesPanel, {
      props: {
        items: [
          makeListItem({
            kind: 'booking',
            id: 'booking-uuid',
            candidature_id: null,
            mission_title: 'Booking · Clip musical · 05/12/2030',
            context: null,
            unread_count: 1,
          }),
        ],
        total: 1,
      },
      global: { plugins: [router] },
    })
    await flushPromises()

    const row = wrapper.find('[data-testid="unread-message-row"]')
    expect(row.text()).toContain('Booking · Clip musical · 05/12/2030')
    expect(row.find('a').attributes('href')).toBe('/producer/conversations/booking-uuid?kind=booking')
  })
})
