import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MissionCard from '../MissionCard.vue'
import type { Mission, MissionStatusType } from '../../types'

function createMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 'mission-uuid-1',
    titre: 'Tournage Spot TV',
    description: 'Test mission description',
    date_tournage: '2026-05-01',
    profil_recherche: 'Face polyvalent',
    budget: 200000,
    date_limite_candidature: '2026-04-25',
    nombre_faces_voulu: 2,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    type_mission_autre: null,
    genre_voulu: 'tous',
    genre_voulu_label: 'Homme et Femme',
    lieu: 'Cotonou',
    duree: '1 jour',
    status: 'closed',
    status_label: 'Clôturée',
    is_accepting_candidatures: false,
    has_paid_payment: true,
    candidatures_count: 0,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('MissionCard — Valider les présences CTA (FIX-26.7)', () => {
  it('shows the button when status is closed and email is verified', () => {
    const wrapper = mount(MissionCard, {
      props: { mission: createMission({ status: 'closed' }), emailVerified: true },
    })
    expect(wrapper.text()).toContain('Valider les présences')
  })

  it('shows the button when status is pending_attendance_validation', () => {
    const wrapper = mount(MissionCard, {
      props: {
        mission: createMission({
          status: 'pending_attendance_validation',
          status_label: 'En attente de validation des présences',
        }),
        emailVerified: true,
      },
    })
    expect(wrapper.text()).toContain('Valider les présences')
  })

  it('does NOT show the button on draft / published / pending_payment / completed', () => {
    const cases: MissionStatusType[] = ['draft', 'published', 'pending_payment', 'completed']
    for (const status of cases) {
      const wrapper = mount(MissionCard, {
        props: { mission: createMission({ status, has_paid_payment: false }), emailVerified: true },
      })
      expect(wrapper.text()).not.toContain('Valider les présences')
    }
  })

  it('does NOT show the button on completed / pending_payment even when has_paid_payment is true (status whitelist enforced)', () => {
    // Defends against a regression where the status check would be silently dropped while
    // the has_paid_payment guard alone happened to bar the cases above.
    const cases: MissionStatusType[] = ['pending_payment', 'completed']
    for (const status of cases) {
      const wrapper = mount(MissionCard, {
        props: { mission: createMission({ status, has_paid_payment: true }), emailVerified: true },
      })
      expect(wrapper.text()).not.toContain('Valider les présences')
    }
  })

  it('does NOT show the button when email is not verified', () => {
    const wrapper = mount(MissionCard, {
      props: { mission: createMission({ status: 'closed' }), emailVerified: false },
    })
    expect(wrapper.text()).not.toContain('Valider les présences')
  })

  it('does NOT show the button on closed status when has_paid_payment is false', () => {
    const wrapper = mount(MissionCard, {
      props: {
        mission: createMission({ status: 'closed', has_paid_payment: false }),
        emailVerified: true,
      },
    })
    expect(wrapper.text()).not.toContain('Valider les présences')
  })

  it('renders the CTA as an enabled <button type="button"> with the visible label', () => {
    const wrapper = mount(MissionCard, {
      props: { mission: createMission({ status: 'closed' }), emailVerified: true },
    })

    const button = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Valider les présences'))
    expect(button).toBeTruthy()
    expect(button!.element.tagName).toBe('BUTTON')
    expect(button!.attributes('type')).toBe('button')
    expect(button!.attributes('disabled')).toBeUndefined()
  })

  it('emits viewAttendance with mission.id on click', async () => {
    const wrapper = mount(MissionCard, {
      props: { mission: createMission({ status: 'closed' }), emailVerified: true },
    })

    const button = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Valider les présences'))
    expect(button).toBeTruthy()

    await button!.trigger('click')

    expect(wrapper.emitted('viewAttendance')).toEqual([['mission-uuid-1']])
  })
})
