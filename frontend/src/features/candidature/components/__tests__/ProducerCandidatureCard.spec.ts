import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ProducerCandidatureCard from '../ProducerCandidatureCard.vue'

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
    tarif_journalier: 100000,
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
})
