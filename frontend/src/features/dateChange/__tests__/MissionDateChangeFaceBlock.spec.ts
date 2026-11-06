import { afterEach, describe, expect, it } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import MissionDateChangeFaceBlock from '../components/MissionDateChangeFaceBlock.vue'
import type { FaceMissionDateChange } from '../types'

const now = new Date('2026-11-06T08:00:00Z')

function makeDateChange(overrides: Partial<FaceMissionDateChange> = {}): FaceMissionDateChange {
  return {
    old_date: '2026-11-15',
    new_date: '2026-11-20',
    reason_label: 'Lieu indisponible',
    // 1 j 23 h après `now`
    respond_by: '2026-11-08T07:30:00Z',
    status: 'pending',
    conflict: false,
    can_respond: true,
    ...overrides,
  }
}

let wrapper: VueWrapper | null = null

function mountBlock(dateChange: FaceMissionDateChange, extra: Record<string, unknown> = {}) {
  wrapper = mount(MissionDateChangeFaceBlock, {
    props: { dateChange, amount: 22500, now, ...extra },
    global: { stubs: { teleport: true } },
  })
  return wrapper
}

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
})

describe('MissionDateChangeFaceBlock', () => {
  it('réponse attendue : comparaison, motif, décompte, boutons et mention du silence', () => {
    const w = mountBlock(makeDateChange())

    expect(w.get('[data-testid="date-change-face-title"]').text()).toBe('Le tournage change de date')
    const compare = w.get('[data-testid="date-change-compare"]').text()
    expect(compare).toContain('Dim. 15 novembre')
    expect(compare).toContain('Ven. 20 novembre')
    expect(w.text()).toContain('Motif indiqué par le Producteur : lieu indisponible.')
    expect(w.get('[data-testid="date-change-countdown"]').text()).toBe('Il vous reste 1 j 23 h')
    expect(w.get('[data-testid="date-change-confirm"]').text()).toBe('Je confirme le 20 novembre')
    expect(w.get('[data-testid="date-change-withdraw"]').text()).toBe('Je ne peux pas')
    expect(w.get('[data-testid="date-change-silence"]').text()).toBe(
      'Sans réponse avant le dimanche 8 novembre à 08:30, vous quittez la mission, sans pénalité.',
    )
    expect(w.attributes('data-state')).toBe('waiting')
  })

  it('« Je confirme » émet la confirmation', async () => {
    const w = mountBlock(makeDateChange())
    await w.get('[data-testid="date-change-confirm"]').trigger('click')
    expect(w.emitted('respond')).toEqual([['confirm']])
  })

  it('date déjà prise : titre dédié, bouton de confirmation désactivé, « Je ne peux pas » reste actif', async () => {
    const w = mountBlock(makeDateChange({ conflict: true }))

    expect(w.attributes('data-state')).toBe('conflict')
    expect(w.get('[data-testid="date-change-face-title"]').text()).toBe('Le 20 novembre est déjà pris dans votre agenda')
    expect(w.get('[data-testid="date-change-conflict"]').text()).toBe(
      'Vous ne pouvez pas confirmer cette date tant que cet engagement est actif.',
    )
    expect(w.get('[data-testid="date-change-confirm"]').attributes('disabled')).toBeDefined()
    expect(w.get('[data-testid="date-change-withdraw"]').attributes('disabled')).toBeUndefined()

    await w.get('[data-testid="date-change-confirm"]').trigger('click')
    expect(w.emitted('respond')).toBeUndefined()
  })

  it('confirmée : message de rendez-vous, plus de boutons', () => {
    const w = mountBlock(makeDateChange({ status: 'confirmed', can_respond: false }))

    expect(w.get('[data-testid="date-change-face-title"]').text()).toBe('Nouvelle date confirmée')
    expect(w.text()).toContain('Rendez-vous le vendredi 20 novembre. Vous recevrez un rappel la veille.')
    expect(w.find('[data-testid="date-change-actions"]').exists()).toBe(false)
  })

  it.each(['withdrawn', 'expired'] as const)('retirée (%s) : mission quittée sans pénalité', (status) => {
    const w = mountBlock(makeDateChange({ status, can_respond: false }))

    expect(w.get('[data-testid="date-change-face-title"]').text()).toBe('Vous avez quitté cette mission')
    expect(w.text()).toContain('Le Producteur en est informé. Aucune pénalité n’a été appliquée.')
    expect(w.find('[data-testid="date-change-actions"]').exists()).toBe(false)
  })

  it('délai écoulé sans réponse enregistrée : pas de boutons', () => {
    const w = mountBlock(makeDateChange({ can_respond: false }))
    expect(w.find('[data-testid="date-change-actions"]').exists()).toBe(false)
    expect(w.get('[data-testid="date-change-late"]').exists()).toBe(true)
  })

  it('« Je ne peux pas » ouvre une fenêtre de confirmation, sans retrait direct', async () => {
    const w = mountBlock(makeDateChange())

    await w.get('[data-testid="date-change-withdraw"]').trigger('click')

    expect(w.emitted('respond')).toBeUndefined()
    const dialog = w.get('[data-testid="date-change-withdraw-dialog"]')
    expect(dialog.text()).toContain('Quitter cette mission ?')
    expect(dialog.text()).toContain('Vous ne pouvez pas venir le vendredi 20 novembre.')
    expect(dialog.text()).toContain('Vous ne recevrez pas les')
    expect(dialog.text()).toContain('22')
    expect(dialog.text()).toContain('Votre note ne bouge pas : ce retrait ne compte pas comme une annulation.')
  })

  it('« Retour » referme la fenêtre sans rien émettre', async () => {
    const w = mountBlock(makeDateChange())
    await w.get('[data-testid="date-change-withdraw"]').trigger('click')
    await w.get('[data-testid="date-change-withdraw-back"]').trigger('click')

    expect(w.find('[data-testid="date-change-withdraw-dialog"]').exists()).toBe(false)
    expect(w.emitted('respond')).toBeUndefined()
  })

  it('« Quitter la mission » émet le retrait seulement après la fenêtre', async () => {
    const w = mountBlock(makeDateChange())
    await w.get('[data-testid="date-change-withdraw"]').trigger('click')
    await w.get('[data-testid="date-change-withdraw-confirm"]').trigger('click')

    expect(w.emitted('respond')).toEqual([['withdraw']])
    expect(w.find('[data-testid="date-change-withdraw-dialog"]').exists()).toBe(false)
  })
})
