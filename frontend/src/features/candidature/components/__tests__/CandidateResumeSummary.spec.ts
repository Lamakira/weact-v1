import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CandidateResumeSummary from '../CandidateResumeSummary.vue'
import type { CandidateFullProfile } from '../../types'

function createCandidate(overrides: Partial<CandidateFullProfile> = {}): CandidateFullProfile {
  return {
    id: 1,
    nom: 'Doe',
    prenom: 'John',
    username: 'johndoe',
    sexe: 'homme',
    sexe_label: 'Homme',
    age: 30,
    nationalite: 'Béninoise',
    langues: ['Français', 'Anglais'],
    profile_photo_url: null,
    thumbnail_url: null,
    presentation_video_url: null,
    presentation_video_thumbnail_url: null,
    acting_video_url: null,
    acting_video_thumbnail_url: null,
    bio: 'Acteur passionné avec 5 ans d\'expérience.',
    ville: 'Cotonou',
    pays: 'Bénin',
    formatted_location: 'Cotonou, Bénin',
    taille: 175,
    poids: 70,
    categories: [{ value: 'acteur', label: 'Acteur' }],
    niches: [{ value: 'cinema', label: 'Cinéma' }],
    tarif_horaire: 50000,
    tarif_journalier: 200000,
    formatted_tarif_horaire: '50 000 FCFA',
    formatted_tarif_journalier: '200 000 FCFA',
    is_available: true,
    availability_badge: 'Disponible',
    availability_badge_color: 'green',
    profile_completion_percentage: 100,
    profile_completion_is_complete: true,
    average_rating: 4.5,
    ratings_count: 10,
    experiences: [],
    photos: [],
    ...overrides,
  }
}

function mountComponent(candidate: CandidateFullProfile = createCandidate()) {
  return mount(CandidateResumeSummary, {
    props: { candidate },
  })
}

describe('CandidateResumeSummary', () => {
  it('renders the component with RÉSUMÉ title', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="candidate-resume-summary"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="resume-title"]').text()).toBe('Résumé')
  })

  it('displays all fields with values when candidate has full data', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="resume-sexe"]').text()).toContain('Homme')
    expect(wrapper.find('[data-testid="resume-age"]').text()).toContain('30 Ans')
    expect(wrapper.find('[data-testid="resume-taille"]').text()).toContain('175 cm')
    expect(wrapper.find('[data-testid="resume-nationalite"]').text()).toContain('Béninoise')
    expect(wrapper.find('[data-testid="resume-langues"]').text()).toContain('Français, Anglais')
    expect(wrapper.find('[data-testid="resume-pays"]').text()).toContain('Bénin')
    expect(wrapper.find('[data-testid="resume-talents"]').text()).toContain('Acteur')
  })

  it('displays "Non renseigné" for missing optional fields', () => {
    const wrapper = mountComponent(
      createCandidate({
        sexe_label: null,
        age: null,
        taille: null,
        nationalite: null,
        langues: null,
        pays: null,
        categories: [],
      }),
    )

    expect(wrapper.find('[data-testid="resume-sexe"]').text()).toContain('Non renseigné')
    expect(wrapper.find('[data-testid="resume-age"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="resume-taille"]').text()).toContain('Non renseigné')
    expect(wrapper.find('[data-testid="resume-nationalite"]').text()).toContain('Non renseigné')
    expect(wrapper.find('[data-testid="resume-langues"]').text()).toContain('Non renseigné')
    expect(wrapper.find('[data-testid="resume-pays"]').text()).toContain('Non renseigné')
    expect(wrapper.find('[data-testid="resume-talents"]').text()).toContain('Non renseigné')
  })

  it('displays bio when present', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="resume-bio-text"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="resume-bio-text"]').text()).toContain(
      'Acteur passionné avec 5 ans',
    )
    expect(wrapper.find('[data-testid="resume-bio-empty"]').exists()).toBe(false)
  })

  it('displays "Non renseigné" for missing bio', () => {
    const wrapper = mountComponent(createCandidate({ bio: null }))

    expect(wrapper.find('[data-testid="resume-bio-empty"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="resume-bio-empty"]').text()).toContain('Non renseigné')
    expect(wrapper.find('[data-testid="resume-bio-text"]').exists()).toBe(false)
  })

  it('displays multiple categories as comma-separated talents', () => {
    const wrapper = mountComponent(
      createCandidate({
        categories: [
          { value: 'acteur', label: 'Acteur' },
          { value: 'mannequin', label: 'Mannequin' },
        ],
      }),
    )

    expect(wrapper.find('[data-testid="resume-talents"]').text()).toContain('Acteur, Mannequin')
  })

  it('displays multiple languages as comma-separated', () => {
    const wrapper = mountComponent(
      createCandidate({
        langues: ['Français', 'Anglais', 'Fon'],
      }),
    )

    expect(wrapper.find('[data-testid="resume-langues"]').text()).toContain(
      'Français, Anglais, Fon',
    )
  })

  it('treats empty langues array as "Non renseigné"', () => {
    const wrapper = mountComponent(createCandidate({ langues: [] }))

    expect(wrapper.find('[data-testid="resume-langues"]').text()).toContain('Non renseigné')
  })
})
