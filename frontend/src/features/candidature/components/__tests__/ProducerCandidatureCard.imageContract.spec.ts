import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ProducerCandidatureCard from '../ProducerCandidatureCard.vue'
import type { ImageContract, MissionImageTerms } from '@/features/imageContract/types'

const { downloadCandidaturePdfMock, toastErrorMock } = vi.hoisted(() => ({
  downloadCandidaturePdfMock: vi.fn(),
  toastErrorMock: vi.fn(),
}))

vi.mock('@/features/imageContract/services/imageContractApi', () => ({
  imageContractApi: { downloadCandidaturePdf: downloadCandidaturePdfMock },
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: toastErrorMock, info: vi.fn(), warning: vi.fn() }),
}))

const imageTerms: MissionImageTerms = {
  finalite: 'Publicité',
  supports: [{ value: 'social', label: 'Réseaux sociaux' }],
  territoire: { value: 'benin', label: 'Bénin' },
  duree_annees: 1,
  producer_signed_at: '2026-11-04T10:00:00Z',
  producer_signed_name: 'Jean Producteur',
}

function makeContract(overrides: Partial<ImageContract> = {}): ImageContract {
  return {
    id: 'ic-1',
    status: 'signed',
    finalite: 'Publicité',
    supports: [{ value: 'social', label: 'Réseaux sociaux' }],
    territoire: { value: 'benin', label: 'Bénin' },
    duree_annees: 1,
    producer_signed_at: '2026-11-04T10:00:00Z',
    producer_signed_name: 'Jean Producteur',
    face_signed_at: '2026-11-05T10:00:00Z',
    face_signed_name: 'Aïcha Bello',
    guardian_name: null,
    effective_at: null,
    pdf_ready: true,
    ...overrides,
  }
}

function makeCandidature(overrides: Record<string, unknown> = {}) {
  return {
    id: 'cand-9',
    status: 'accepted' as const,
    status_label: 'Acceptée',
    message_motivation: null,
    created_at: '2026-11-01T10:00:00Z',
    conversation_id: 'conv-1',
    confirmation_deadline: '2026-11-07T08:51:00',
    face: {
      id: 'face-1',
      display_name: 'Aïcha Bello',
      profile_photo_url: null,
      profile_photo_thumbnail_url: null,
      city: 'Cotonou',
      category: null,
      tarif_horaire: null,
      tarif_journalier: null,
      has_elite_badge: false,
    },
    ...overrides,
  }
}

function mountCard(candidature: Record<string, unknown>, imageTermsProp: MissionImageTerms | null | undefined = imageTerms) {
  return mount(ProducerCandidatureCard, {
    props: { candidature: candidature as never, imageTerms: imageTermsProp },
    global: { stubs: { RouterLink: { template: '<a><slot /></a>', props: ['to'] }, Teleport: true } },
  })
}

describe('ProducerCandidatureCard : ligne du contrat d\'image', () => {
  beforeEach(() => {
    downloadCandidaturePdfMock.mockReset()
    toastErrorMock.mockReset()
  })

  it('retenue sans contrat, mission à conditions : « Signature attendue » orange avec l\'échéance', () => {
    const wrapper = mountCard(makeCandidature())

    const line = wrapper.get('[data-testid="candidature-contract-line"]')
    expect(line.text()).toContain("Contrat d'image")
    expect(line.text()).not.toContain('Aïcha Bello')
    const state = line.get('[data-testid="contract-line-state"]')
    expect(state.text()).toContain('Signature attendue')
    expect(state.attributes('data-tone')).toBe('amber')
    expect(state.classes()).toContain('text-amber-700')
    expect(line.get('[data-testid="contract-line-deadline"]').text()).toContain('Avant le')
    expect(line.get('[data-testid="contract-line-deadline"]').text()).toContain('08h51')
    expect(line.find('[data-testid="contract-line-pdf"]').exists()).toBe(false)
    // L'échéance n'est pas répétée par la phrase historique.
    expect(wrapper.find('[data-testid="confirmation-deadline-label"]').exists()).toBe(false)
  })

  it('UGC : l\'échéance de reconfirmation sert de délai', () => {
    const wrapper = mountCard(makeCandidature({ confirmation_deadline: null, reconfirm_deadline: '2026-11-08T10:30:00' }))

    expect(wrapper.get('[data-testid="contract-line-deadline"]').text()).toContain('10h30')
  })

  it('sans échéance : pas de délai affiché', () => {
    const wrapper = mountCard(makeCandidature({ confirmation_deadline: null }))

    expect(wrapper.find('[data-testid="contract-line-deadline"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="contract-line-state"]').text()).toContain('Signature attendue')
  })

  it('contrat signé : « Contrat signé » vert avec le bouton PDF sur la même ligne', () => {
    const wrapper = mountCard(makeCandidature({ status: 'confirmed', status_label: 'Confirmée', image_contract: makeContract() }))

    const line = wrapper.get('[data-testid="candidature-contract-line"]')
    const state = line.get('[data-testid="contract-line-state"]')
    expect(state.text()).toContain('Contrat signé')
    expect(state.attributes('data-tone')).toBe('green')
    expect(line.find('[data-testid="contract-line-deadline"]').exists()).toBe(false)
    // Nom + état à gauche, PDF à droite : un seul conteneur flex pour les trois.
    expect(line.classes()).toEqual(expect.arrayContaining(['flex', 'items-center', 'justify-between']))
    expect(line.element.children).toHaveLength(2)
    expect(line.get('[data-testid="contract-line-pdf"]').exists()).toBe(true)
  })

  it('contrat signé dont le PDF n\'est pas prêt : pas de bouton PDF', () => {
    const wrapper = mountCard(makeCandidature({ status: 'confirmed', image_contract: makeContract({ pdf_ready: false }) }))

    expect(wrapper.get('[data-testid="contract-line-state"]').text()).toContain('Contrat signé')
    expect(wrapper.find('[data-testid="contract-line-pdf"]').exists()).toBe(false)
  })

  it('contrat effectif : « Image utilisable » vert avec PDF', () => {
    const wrapper = mountCard(
      makeCandidature({ status: 'in_progress', image_contract: makeContract({ status: 'effective', effective_at: '2026-11-10T08:00:00Z' }) }),
    )

    const state = wrapper.get('[data-testid="contract-line-state"]')
    expect(state.text()).toContain('Image utilisable')
    expect(state.attributes('data-tone')).toBe('green')
    expect(wrapper.find('[data-testid="contract-line-pdf"]').exists()).toBe(true)
  })

  it('contrat annulé : « Contrat annulé » gris, sans bouton', () => {
    const wrapper = mountCard(makeCandidature({ status: 'cancelled', image_contract: makeContract({ status: 'void' }) }))

    const state = wrapper.get('[data-testid="contract-line-state"]')
    expect(state.text()).toContain('Contrat annulé')
    expect(state.attributes('data-tone')).toBe('gray')
    expect(wrapper.find('[data-testid="contract-line-pdf"]').exists()).toBe(false)
  })

  it.each([
    ['mission sans conditions', makeCandidature(), null],
    ['candidature en attente', makeCandidature({ status: 'pending', status_label: 'En attente' }), imageTerms],
    ['candidature confirmée sans contrat', makeCandidature({ status: 'confirmed' }), imageTerms],
  ])('aucune ligne : %s', (_name, candidature, terms) => {
    const wrapper = mountCard(candidature, terms)

    expect(wrapper.find('[data-testid="candidature-contract-line"]').exists()).toBe(false)
  })

  it('mission sans conditions : la phrase d\'échéance historique est conservée', () => {
    const wrapper = mountCard(makeCandidature(), null)

    expect(wrapper.find('[data-testid="confirmation-deadline-label"]').exists()).toBe(true)
  })

  it('télécharge le PDF de la candidature', async () => {
    downloadCandidaturePdfMock.mockResolvedValue(new Blob(['%PDF']))
    globalThis.URL.createObjectURL = vi.fn(() => 'blob:pdf')
    globalThis.URL.revokeObjectURL = vi.fn()
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
    const wrapper = mountCard(makeCandidature({ status: 'confirmed', image_contract: makeContract() }))

    await wrapper.get('[data-testid="contract-line-pdf"]').trigger('click')
    await flushPromises()

    expect(downloadCandidaturePdfMock).toHaveBeenCalledWith('cand-9')
    expect(click).toHaveBeenCalledTimes(1)
    click.mockRestore()
  })

  it('signale un échec de téléchargement', async () => {
    downloadCandidaturePdfMock.mockRejectedValue(new Error('404'))
    const wrapper = mountCard(makeCandidature({ status: 'confirmed', image_contract: makeContract() }))

    await wrapper.get('[data-testid="contract-line-pdf"]').trigger('click')
    await flushPromises()

    expect(toastErrorMock).toHaveBeenCalledWith(expect.stringContaining("n'a pas pu être téléchargé"))
  })
})
