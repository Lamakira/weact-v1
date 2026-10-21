import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import MissionMarks from '../MissionMarks.vue'
import AvailableMissionCard from '../AvailableMissionCard.vue'
import UgcMissionCard from '../UgcMissionCard.vue'
import type { Mission, UgcMissionTeaser } from '../../types'

vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }) }))

describe('MissionMarks (lot D8)', () => {
  it('ne rend rien sans marque', () => {
    const wrapper = mount(MissionMarks, { props: { myCandidature: null, eligibility: 'ok' } })
    expect(wrapper.find('[data-testid="mission-marks"]').exists()).toBe(false)
  })

  it('affiche « Déjà candidaté · {statut} »', () => {
    const wrapper = mount(MissionMarks, {
      props: { myCandidature: 'pending', myCandidatureLabel: 'En attente', eligibility: 'ok' },
    })
    expect(wrapper.get('[data-testid="mark-applied"]').text()).toBe('Déjà candidaté · En attente')
  })

  it('affiche « Non compatible avec votre profil » pour un genre incompatible', () => {
    const wrapper = mount(MissionMarks, { props: { eligibility: 'gender_mismatch' } })
    expect(wrapper.get('[data-testid="mark-incompatible"]').text()).toBe(
      'Non compatible avec votre profil',
    )
    expect(wrapper.find('[data-testid="mark-subscription"]').exists()).toBe(false)
  })

  it.each(['subscription_required', 'ugc_subscription_required'] as const)(
    'affiche « Abonnement requis » pour %s (jamais « Non compatible »)',
    (eligibility) => {
      const wrapper = mount(MissionMarks, { props: { eligibility } })
      expect(wrapper.get('[data-testid="mark-subscription"]').text()).toBe('Abonnement requis')
      expect(wrapper.find('[data-testid="mark-incompatible"]').exists()).toBe(false)
    },
  )
})

function baseMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 'm1',
    titre: 'Mission',
    description: 'Brief',
    date_tournage: '2026-07-01',
    budget: 1000,
    date_limite_candidature: '2026-06-24T00:00:00Z',
    nombre_faces_voulu: 1,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    lieu: 'Cotonou',
    nom_produit: 'Produit',
    valeur_produit: 1000,
    nombre_videos: 1,
    status: 'published',
    is_accepting_candidatures: true,
    producer: null,
    ...overrides,
  } as unknown as Mission
}

describe('marques sur les cartes', () => {
  it('AvailableMissionCard affiche les marques de la mission', () => {
    const wrapper = mount(AvailableMissionCard, {
      props: {
        mission: baseMission({
          my_candidature: 'pending',
          my_candidature_label: 'En attente',
          eligibility: 'gender_mismatch',
        }),
      },
      global: { stubs: { RatingDisplay: true } },
    })
    expect(wrapper.text()).toContain('Déjà candidaté · En attente')
    expect(wrapper.text()).toContain('Non compatible avec votre profil')
  })

  it('UgcMissionCard éligible affiche la marque de la mission', () => {
    const wrapper = mount(UgcMissionCard, {
      props: { item: baseMission({ eligibility: 'gender_mismatch' }), locked: false },
    })
    expect(wrapper.text()).toContain('Non compatible avec votre profil')
  })

  it('UgcMissionCard verrouillée affiche « Abonnement requis »', () => {
    const teaser = {
      id: 't1',
      titre: 'Teaser',
      date_limite_candidature: '2999-01-01T00:00:00Z',
    } as unknown as UgcMissionTeaser
    const wrapper = mount(UgcMissionCard, { props: { item: teaser, locked: true } })
    expect(wrapper.text()).toContain('Abonnement requis')
  })
})
