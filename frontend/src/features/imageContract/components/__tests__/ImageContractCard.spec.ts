import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ImageContractCard from '../ImageContractCard.vue'
import type { ImageContract } from '../../types'

const { downloadPdfMock, toastErrorMock } = vi.hoisted(() => ({
  downloadPdfMock: vi.fn(),
  toastErrorMock: vi.fn(),
}))

vi.mock('../../services/imageContractApi', () => ({
  imageContractApi: { downloadPdf: downloadPdfMock },
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: toastErrorMock }),
}))

function makeContract(overrides: Partial<ImageContract> = {}): ImageContract {
  return {
    id: 'ic-1',
    status: 'signed',
    finalite: 'Publicité',
    supports: [{ value: 'social', label: 'Réseaux sociaux' }, { value: 'web', label: 'Site web' }],
    territoire: { value: 'benin', label: 'Bénin' },
    duree_annees: 3,
    producer_signed_at: '2026-11-01T10:00:00Z',
    producer_signed_name: 'Jean Producteur',
    face_signed_at: '2026-11-02T10:00:00Z',
    face_signed_name: 'Aïcha Bello',
    guardian_name: null,
    effective_at: null,
    pdf_ready: false,
    ...overrides,
  }
}

const mountCard = (contract: ImageContract, role: 'face' | 'producer' = 'face') =>
  mount(ImageContractCard, { props: { contract, bookingId: 'b-1', role } })

describe('ImageContractCard', () => {
  beforeEach(() => {
    downloadPdfMock.mockReset()
    toastErrorMock.mockReset()
  })

  it('affiche les signatures avec leurs dates et les conditions', () => {
    const wrapper = mountCard(makeContract())

    expect(wrapper.find('[data-testid="contract-producer-signature"]').text()).toContain('Jean Producteur')
    expect(wrapper.find('[data-testid="contract-producer-signature"]').text()).toContain('1 novembre 2026')
    expect(wrapper.find('[data-testid="contract-face-signature"]').text()).toContain('Aïcha Bello')
    expect(wrapper.find('[data-testid="contract-face-signature"]').text()).toContain('2 novembre 2026')
    expect(wrapper.find('[data-testid="contract-terms"]').text()).toContain('Réseaux sociaux, Site web')
    expect(wrapper.find('[data-testid="contract-terms"]').text()).toContain('3 ans')
  })

  it('awaiting_face : la signature de la Face est en attente', () => {
    const wrapper = mountCard(makeContract({ status: 'awaiting_face', face_signed_at: null, face_signed_name: null }), 'producer')

    expect(wrapper.find('[data-testid="contract-face-signature"]').text()).toContain('En attente de signature')
    expect(wrapper.find('[data-testid="contract-usage"]').text()).toBe('Pas encore autorisée')
  })

  it('signed : « Pas encore autorisée » avec la phrase de la Face', () => {
    const wrapper = mountCard(makeContract({ status: 'signed' }), 'face')

    expect(wrapper.find('[data-testid="contract-usage"]').text()).toBe('Pas encore autorisée')
    expect(wrapper.find('[data-testid="contract-usage-hint"]').text()).toBe(
      'Elle devient possible après votre paiement.',
    )
  })

  it('signed : phrase du Producteur', () => {
    const wrapper = mountCard(makeContract({ status: 'signed' }), 'producer')

    expect(wrapper.find('[data-testid="contract-usage-hint"]').text()).toBe(
      'Elle devient possible une fois la Face payée.',
    )
  })

  it('effective : « Autorisée depuis le {date} » sans phrase d\'attente', () => {
    const wrapper = mountCard(makeContract({ status: 'effective', effective_at: '2026-11-10T08:00:00Z' }))

    expect(wrapper.find('[data-testid="contract-usage"]').text()).toBe('Autorisée depuis le 10 novembre 2026')
    expect(wrapper.find('[data-testid="contract-usage-hint"]').exists()).toBe(false)
  })

  it('void : « Annulée »', () => {
    const wrapper = mountCard(makeContract({ status: 'void' }))

    expect(wrapper.find('[data-testid="contract-usage"]').text()).toBe('Annulée')
    expect(wrapper.find('[data-testid="contract-usage-hint"]').exists()).toBe(false)
  })

  it('affiche le parent ou tuteur quand il a signé', () => {
    const wrapper = mountCard(makeContract({ guardian_name: 'Marie Dossou' }))

    expect(wrapper.find('[data-testid="contract-guardian"]').text()).toBe('Marie Dossou')
  })

  it('n\'affiche le bouton PDF que lorsque le PDF est prêt', () => {
    expect(mountCard(makeContract({ pdf_ready: false })).find('[data-testid="contract-download"]').exists()).toBe(false)

    const ready = mountCard(makeContract({ pdf_ready: true }))
    expect(ready.find('[data-testid="contract-download"]').text()).toContain('Télécharger le contrat (PDF)')
  })

  it('télécharge le PDF du booking', async () => {
    downloadPdfMock.mockResolvedValue(new Blob(['%PDF']))
    const createUrl = vi.fn(() => 'blob:pdf')
    const revokeUrl = vi.fn()
    globalThis.URL.createObjectURL = createUrl
    globalThis.URL.revokeObjectURL = revokeUrl
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
    const wrapper = mountCard(makeContract({ pdf_ready: true }))

    await wrapper.find('[data-testid="contract-download"]').trigger('click')
    await flushPromises()

    expect(downloadPdfMock).toHaveBeenCalledWith('b-1')
    expect(click).toHaveBeenCalledTimes(1)
    expect(revokeUrl).toHaveBeenCalledWith('blob:pdf')
    click.mockRestore()
  })

  it('signale un échec de téléchargement', async () => {
    downloadPdfMock.mockRejectedValue(new Error('404'))
    const wrapper = mountCard(makeContract({ pdf_ready: true }))

    await wrapper.find('[data-testid="contract-download"]').trigger('click')
    await flushPromises()

    expect(toastErrorMock).toHaveBeenCalledWith(expect.stringContaining("n'a pas pu être téléchargé"))
  })
})
