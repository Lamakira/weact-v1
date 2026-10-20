import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import RateFaceDialog from '../RateFaceDialog.vue'
import { candidatureApi } from '../../services/candidatureApi'

vi.mock('../../services/candidatureApi', () => ({
  candidatureApi: { rateCandidature: vi.fn() },
}))

function mountDialog(candidatureId: string | null = 'cand-1') {
  return mount(RateFaceDialog, {
    props: { candidatureId, faceName: 'Aïcha Bello' },
    global: { stubs: { Teleport: true } },
  })
}

describe('RateFaceDialog (lot C8)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('reste fermé sans candidature ciblée', () => {
    expect(mountDialog(null).find('[data-testid="rate-face-dialog"]').exists()).toBe(false)
  })

  it('nomme la Face, rappelle que c\'est facultatif et bloque l\'envoi sans étoile', () => {
    const wrapper = mountDialog()

    expect(wrapper.text()).toContain('Noter Aïcha Bello')
    expect(wrapper.text()).toContain('facultatif')
    expect(wrapper.get('[data-testid="rate-face-submit"]').attributes('disabled')).toBeDefined()
  })

  it('envoie la note choisie avec le commentaire et émet rated', async () => {
    vi.mocked(candidatureApi.rateCandidature).mockResolvedValue({
      data: { score: 4, comment: 'Ponctuelle', created_at: '2026-10-20T10:00:00+00:00' },
      message: 'ok',
    })
    const wrapper = mountDialog()

    await wrapper.get('[data-testid="rate-star-4"]').trigger('click')
    await wrapper.get('textarea').setValue('  Ponctuelle  ')
    await wrapper.get('[data-testid="rate-face-submit"]').trigger('click')
    await flushPromises()

    expect(candidatureApi.rateCandidature).toHaveBeenCalledWith('cand-1', 4, 'Ponctuelle')
    expect(wrapper.emitted('rated')?.[0]).toEqual([
      'cand-1',
      { score: 4, comment: 'Ponctuelle', created_at: '2026-10-20T10:00:00+00:00' },
    ])
  })

  it('le commentaire est optionnel', async () => {
    vi.mocked(candidatureApi.rateCandidature).mockResolvedValue({
      data: { score: 5, comment: null, created_at: null },
      message: 'ok',
    })
    const wrapper = mountDialog()

    await wrapper.get('[data-testid="rate-star-5"]').trigger('click')
    await wrapper.get('[data-testid="rate-face-submit"]').trigger('click')
    await flushPromises()

    expect(candidatureApi.rateCandidature).toHaveBeenCalledWith('cand-1', 5, undefined)
  })

  it('affiche l\'erreur serveur (déjà notée) sans fermer le dialogue', async () => {
    vi.mocked(candidatureApi.rateCandidature).mockRejectedValue({
      isAxiosError: true,
      response: { status: 422, data: { error: { code: 'ALREADY_RATED', message: 'Vous avez déjà évalué ce talent pour cette mission' } } },
    })
    const wrapper = mountDialog()

    await wrapper.get('[data-testid="rate-star-3"]').trigger('click')
    await wrapper.get('[data-testid="rate-face-submit"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="rate-face-error"]').text()).not.toBe('')
    expect(wrapper.emitted('rated')).toBeUndefined()
    expect(wrapper.emitted('close')).toBeUndefined()
  })

  it('« Plus tard » émet close', async () => {
    const wrapper = mountDialog()
    const later = wrapper.findAll('button').find((b) => b.text() === 'Plus tard')!
    await later.trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})
