import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ProducerCandidatureCard from '../ProducerCandidatureCard.vue'
import WBadge from '@/components/ui/WBadge.vue'

const pendingCandidature = {
  id: 'cand-1',
  mission_id: 'mission-1',
  face_id: 'face-1',
  status: 'pending' as const,
  status_label: 'En attente',
  message_motivation: 'Test motivation',
  created_at: '2026-04-14T10:00:00Z',
  updated_at: '2026-04-14T10:00:00Z',
  conversation_id: null,
  face: {
    id: 'face-1',
    display_name: 'Alice Martin',
    profile_photo_url: null,
    city: 'Paris',
    category: 'acteur',
    tarif_horaire: null,
    tarif_journalier: 100000,
    has_elite_badge: false,
  },
}

describe('ProducerCandidatureCard', () => {
  it('does not render the legacy "Accepter" button outside selection mode (FIX-20.3)', () => {
    const wrapper = mount(ProducerCandidatureCard, {
      props: {
        candidature: pendingCandidature,
        selectionMode: false,
      },
      global: {
        stubs: {
          RouterLink: true,
          Teleport: true,
        },
      },
    })

    const buttons = wrapper.findAll('button')
    const acceptButton = buttons.find((btn) => btn.text().includes('Accepter'))
    expect(acceptButton).toBeUndefined()
  })

  describe('FP-2.12 — Élite badge (WBadge V13 design refresh)', () => {
    const routerLinkStub = {
      template: '<a><slot /></a>',
      props: ['to'],
    }

    it('renders WBadge in elite tier at 14px next to display_name when face.has_elite_badge is true', () => {
      const wrapper = mount(ProducerCandidatureCard, {
        props: {
          candidature: {
            ...pendingCandidature,
            face: { ...pendingCandidature.face, has_elite_badge: true },
          },
        },
        global: { stubs: { RouterLink: routerLinkStub, Teleport: true } },
      })
      const badge = wrapper.findComponent(WBadge)
      expect(badge.exists()).toBe(true)
      expect(badge.props('tier')).toBe('elite')
      expect(badge.props('size')).toBe(14)
      expect(wrapper.text()).toContain('Alice Martin')
    })

    it('does not render the Élite badge when face.has_elite_badge is false', () => {
      const wrapper = mount(ProducerCandidatureCard, {
        props: { candidature: pendingCandidature },
        global: { stubs: { RouterLink: routerLinkStub, Teleport: true } },
      })
      expect(wrapper.findComponent(WBadge).exists()).toBe(false)
      expect(wrapper.text()).toContain('Alice Martin')
    })
  })
})
