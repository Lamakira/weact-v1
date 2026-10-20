import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { RouterLinkStub } from '@vue/test-utils'
import ActiveMissionsPanel from '../ActiveMissionsPanel.vue'
import type { ProducerActiveMission } from '../../../types'

function makeMission(overrides: Partial<ProducerActiveMission> = {}): ProducerActiveMission {
  return {
    id: 'm1',
    titre: 'Tournage',
    type_mission: 'publicite',
    status: 'closed',
    status_label: 'Clôturée',
    candidatures_count: 2,
    new_candidatures_count: 0,
    confirmed_count: 1,
    faces_wanted: 2,
    ...overrides,
  }
}

function mountPanel(items: ProducerActiveMission[]) {
  return mount(ActiveMissionsPanel, {
    props: { items, total: items.length },
    global: { stubs: { RouterLink: RouterLinkStub } },
  })
}

describe('ActiveMissionsPanel — libellés de statut', () => {
  it('affiche display_status_label du serveur', () => {
    const wrapper = mountPanel([
      makeMission({ display_status: 'paid_preparing', display_status_label: 'Payée, en préparation' }),
    ])
    expect(wrapper.get('[data-testid="r-status-dot"]').text()).toBe('Payée, en préparation')
  })

  it('affiche « Présences à valider » pour une mission en validation des présences', () => {
    const wrapper = mountPanel([
      makeMission({
        status: 'pending_attendance_validation',
        display_status: 'attendance_pending',
        display_status_label: 'Présences à valider',
      }),
    ])
    expect(wrapper.get('[data-testid="r-status-dot"]').text()).toBe('Présences à valider')
  })

  it('retombe sur Publiée / En cours sans display_status_label', () => {
    const wrapper = mountPanel([
      makeMission({ id: 'a', status: 'published', status_label: 'Publiée' }),
      makeMission({ id: 'b' }),
    ])
    const dots = wrapper.findAll('[data-testid="r-status-dot"]')
    expect(dots.map((d) => d.text())).toEqual(['Publiée', 'En cours'])
  })
})
