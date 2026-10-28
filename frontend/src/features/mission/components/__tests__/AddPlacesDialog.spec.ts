import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AddPlacesDialog from '../AddPlacesDialog.vue'

const mockAddPlaces = vi.fn()
vi.mock('../../services/missionApi', () => ({
  missionApi: { addPlaces: (...args: unknown[]) => mockAddPlaces(...args) },
}))

function renderDialog(props: Record<string, unknown> = {}) {
  return mount(AddPlacesDialog, {
    props: {
      isOpen: true,
      missionId: 'm-uuid',
      missionTitle: 'Unboxing Tenue Shade Fit',
      totalPlaces: 1,
      takenPlaces: 1,
      isHybrid: false,
      deadline: '2999-11-03',
      ...props,
    },
    attachTo: document.body,
  })
}

const text = (): string => (document.body.textContent ?? '').replace(/\s+/g, ' ')
const byId = (id: string): HTMLElement | null => document.body.querySelector(`[data-testid="${id}"]`)

describe('AddPlacesDialog', () => {
  beforeEach(() => {
    mockAddPlaces.mockReset()
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('mode menu : titre, sous-titre et total futur', () => {
    renderDialog({ totalPlaces: 3, takenPlaces: 2 })

    expect(text()).toContain('Ajouter des places')
    expect(text()).toContain('Unboxing Tenue Shade Fit · 3 places, 2 prises.')
    expect(text()).toContain('Places à ajouter')
    expect(text()).toContain('La mission passera à 4 places.')
  })

  it('produit seul : message sans frais', () => {
    renderDialog({ isHybrid: false })

    expect(text()).toContain('Sans frais : la commission déjà réglée couvre ces places.')
    expect(text()).not.toContain('10 % de frais de service')
  })

  it('hybride : chaque Face retenue est réglée à l’acceptation', () => {
    renderDialog({ isHybrid: true })

    expect(text()).toContain(
      'Chaque Face retenue est réglée au moment où vous l\'acceptez : sa rémunération + 10 % de frais de service.',
    )
    expect(text()).not.toContain('Sans frais')
  })

  it('date limite future : invite les Faces à postuler jusqu’au jour', () => {
    renderDialog({ deadline: '2999-11-03' })

    expect(text()).toContain('D\'autres Faces peuvent encore postuler jusqu\'au 3 novembre.')
    expect(text()).not.toContain('La date limite est passée')
  })

  it('date limite passée : seules les candidatures en attente peuvent être acceptées', () => {
    renderDialog({ deadline: '2020-01-01T12:00:00+00:00' })

    expect(text()).toContain('La date limite est passée : seules les candidatures en attente peuvent être acceptées.')
    expect(text()).not.toContain('peuvent encore postuler')
  })

  it('compteur borné entre 1 et 20, libellé du bouton au singulier puis au pluriel', async () => {
    renderDialog()
    const confirm = byId('add-places-confirm')!
    const minus = byId('add-places-minus') as HTMLButtonElement
    const plus = byId('add-places-plus') as HTMLButtonElement
    const input = byId('add-places-input') as HTMLInputElement

    expect(confirm.textContent).toContain('Ajouter 1 place')
    expect(confirm.textContent).not.toContain('places')
    expect(minus.disabled).toBe(true)

    plus.click()
    await flushPromises()
    expect(confirm.textContent).toContain('Ajouter 2 places')

    input.value = '99'
    input.dispatchEvent(new Event('input'))
    await flushPromises()
    expect(confirm.textContent).toContain('Ajouter 20 places')
    expect(plus.disabled).toBe(true)

    input.value = '0'
    input.dispatchEvent(new Event('input'))
    await flushPromises()
    expect(confirm.textContent).toContain('Ajouter 1 place')
    expect(minus.disabled).toBe(true)
  })

  it('mode carte : titre « Plus de place libre », sans compteur, bouton « Ajouter 1 place et accepter »', () => {
    renderDialog({ mode: 'card', faceName: 'Aïcha' })

    expect(text()).toContain('Plus de place libre')
    expect(text()).toContain('Toutes les places de cette mission sont prises. Ajoutez-en une pour retenir Aïcha.')
    expect(byId('add-places-counter')).toBeNull()
    expect(byId('add-places-confirm')!.textContent).toContain('Ajouter 1 place et accepter Aïcha')
  })

  it('appelle l’API avec le nombre choisi puis émet added avec la mission rafraîchie', async () => {
    const mission = { id: 'm-uuid', nombre_faces_voulu: 3 }
    mockAddPlaces.mockResolvedValue({ data: mission, message: '2 places ajoutées.' })
    const wrapper = renderDialog()

    ;(byId('add-places-plus') as HTMLButtonElement).click()
    await flushPromises()
    byId('add-places-confirm')!.click()
    await flushPromises()

    expect(mockAddPlaces).toHaveBeenCalledWith('m-uuid', 2)
    expect(wrapper.emitted('added')).toEqual([[2, mission]])
  })

  it('mode carte : appelle l’API avec 1 place', async () => {
    mockAddPlaces.mockResolvedValue({ data: {}, message: '1 place ajoutée.' })
    const wrapper = renderDialog({ mode: 'card', faceName: 'Aïcha' })

    byId('add-places-confirm')!.click()
    await flushPromises()

    expect(mockAddPlaces).toHaveBeenCalledWith('m-uuid', 1)
    expect(wrapper.emitted('added')?.[0]?.[0]).toBe(1)
  })

  it('affiche l’erreur de l’API dans la fenêtre et n’émet pas added', async () => {
    mockAddPlaces.mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: { message: 'Impossible d\'ajouter des places à cette mission.', errors: { mission: ['Impossible d\'ajouter des places à cette mission.'] } },
      },
    })
    const wrapper = renderDialog()

    byId('add-places-confirm')!.click()
    await flushPromises()

    expect(byId('add-places-error')!.textContent).toContain('Impossible d\'ajouter des places à cette mission.')
    expect(wrapper.emitted('added')).toBeUndefined()
  })

  it('« Annuler » émet cancel', async () => {
    const wrapper = renderDialog()

    byId('add-places-cancel')!.click()
    await flushPromises()

    expect(wrapper.emitted('cancel')).toHaveLength(1)
  })
})
