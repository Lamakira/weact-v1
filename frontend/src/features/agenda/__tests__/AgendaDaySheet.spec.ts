import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AgendaDaySheet from '../components/AgendaDaySheet.vue'
import type { AgendaEvent } from '../types'

vi.mock('vue-router', () => ({
  RouterLink: {
    name: 'RouterLink',
    props: ['to'],
    template: '<a :data-route="JSON.stringify(to)" v-bind="$attrs"><slot /></a>',
  },
}))

const events: AgendaEvent[] = [
  {
    kind: 'booking',
    uuid: 'b-1',
    title: 'Clip Afro Vibes',
    status: 'accepted',
    state: 'engaged',
    start: '2026-11-18',
    end: '2026-11-20',
    counterpart: 'Studio Lumière',
  },
  {
    kind: 'mission',
    uuid: 'm-1',
    title: 'Pub boisson',
    status: 'confirmed',
    state: 'engaged',
    start: '2026-11-19',
    end: '2026-11-19',
    counterpart: 'Agence X',
  },
]

function mountSheet(props: Record<string, unknown> = {}) {
  return mount(AgendaDaySheet, {
    props: { day: '2026-11-19', today: '2026-11-08', horizonEnd: '2027-11-08', events, blocked: false, ...props },
    global: { stubs: { Teleport: { template: '<div><slot /></div>' } } },
  })
}

describe('AgendaDaySheet', () => {
  it('marque chaque événement d\'une pastille ronde, sans barre d\'accent', () => {
    const wrapper = mountSheet()
    const items = wrapper.findAll('[data-testid="agenda-day-events"] li')
    expect(items).toHaveLength(2)
    items.forEach((item) => expect(item.find('span.w-1').exists()).toBe(false))
    expect(items[0]!.get('span.size-2.rounded-full').classes()).toContain('bg-teal-600')
    expect(items[1]!.get('span.size-2.rounded-full').classes()).toContain('bg-violet-700')
  })

  it('émet block / unblock depuis le composant d\'action partagé', async () => {
    const free = mountSheet({ events: [] })
    await free.get('[data-testid="agenda-day-block"]').trigger('click')
    expect(free.emitted('block')).toEqual([['2026-11-19']])

    const blocked = mountSheet({ events: [], blocked: true })
    await blocked.get('[data-testid="agenda-day-unblock"]').trigger('click')
    expect(blocked.emitted('unblock')).toEqual([['2026-11-19']])
  })
})
