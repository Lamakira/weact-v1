import { afterEach, describe, expect, it } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import MissionRowActions from '../MissionRowActions.vue'
import type { Mission } from '../../types'

// reka-ui measures its popper with ResizeObserver, absent from jsdom.
globalThis.ResizeObserver ??= class {
  observe() {}
  unobserve() {}
  disconnect() {}
} as unknown as typeof ResizeObserver

function makeMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 'm1',
    titre: 'Ma mission',
    status: 'published',
    commission_ugc: null,
    has_paid_payment: false,
    candidatures_count: 3,
    ...overrides,
  } as Mission
}

let wrapper: VueWrapper | null = null

function mountActions(mission: Mission, emailVerified = true) {
  wrapper = mount(MissionRowActions, {
    props: { mission, emailVerified },
    attachTo: document.body,
  })
  return wrapper
}

async function openMenu(w: VueWrapper) {
  await w.find('[data-testid="actions-menu-trigger"]').trigger('keydown', { key: 'Enter' })
  await flushPromises()
}

function menuLabels(): string[] {
  return Array.from(document.body.querySelectorAll('[role="menuitem"]')).map((el) => el.textContent!.trim())
}

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  document.body.innerHTML = ''
})

const ugcPendingPayment = { status: 'pending_payment', commission_ugc: 2500 } as const

describe('MissionRowActions — action principale', () => {
  it.each([
    ['published', { status: 'published' }, 'Candidatures · 3', 'action-candidatures'],
    ['closed (non payée)', { status: 'closed' }, 'Terminer', 'action-complete'],
    ['pending_payment UGC', ugcPendingPayment, 'Régler la commission', 'pay-commission-button'],
    ['pending_attendance_validation payée', { status: 'pending_attendance_validation', has_paid_payment: true }, 'Valider les présences', 'action-attendance'],
    ['closed payée', { status: 'closed', has_paid_payment: true }, 'Valider les présences', 'action-attendance'],
    ['completed', { status: 'completed' }, 'Candidatures · 3', 'action-candidatures'],
  ] as const)('%s -> bouton principal « %s »', (_name, overrides, label, testId) => {
    const w = mountActions(makeMission(overrides))
    const primary = w.find('[data-primary-action]')
    expect(primary.text()).toBe(label)
    expect(primary.attributes('data-testid')).toBe(testId)
  })

  it('affiche « Candidatures » sans compteur quand il est absent', () => {
    const w = mountActions(makeMission({ candidatures_count: undefined }))
    expect(w.find('[data-primary-action]').text()).toBe('Candidatures')
  })

  it('utilise le bouton teal pour la commission et le secondaire pour Terminer', () => {
    const pay = mountActions(makeMission(ugcPendingPayment))
    expect(pay.find('[data-primary-action]').classes()).toContain('bg-weact-600')
    pay.unmount()
    const done = mountActions(makeMission({ status: 'closed' }))
    expect(done.find('[data-primary-action]').classes()).not.toContain('bg-weact-600')
  })

  it('émet l’évènement de l’action principale sans le propager à la ligne', async () => {
    let rowClicks = 0
    const w = mountActions(makeMission(ugcPendingPayment))
    w.element.parentElement!.addEventListener('click', () => rowClicks++)
    await w.find('[data-primary-action]').trigger('click')
    expect(w.emitted('payCommission')).toEqual([['m1']])
    expect(rowClicks).toBe(0)
  })
})

describe('MissionRowActions — menu « Plus d’actions »', () => {
  it.each([
    ['published', { status: 'published' }, ['Modifier', 'Clôturer', 'Supprimer']],
    ['draft', { status: 'draft' }, ['Modifier', 'Supprimer']],
    ['closed (non payée)', { status: 'closed' }, ['Candidatures · 3', 'Réouvrir']],
    ['closed payée', { status: 'closed', has_paid_payment: true }, ['Candidatures · 3', 'Terminer']],
    ['pending_attendance_validation', { status: 'pending_attendance_validation', has_paid_payment: true }, ['Candidatures · 3']],
    ['pending_payment UGC', ugcPendingPayment, ['Candidatures · 3']],
    ['completed', { status: 'completed' }, null],
  ] as const)('%s', async (_name, overrides, expected) => {
    const w = mountActions(makeMission(overrides))
    const trigger = w.find('[data-testid="actions-menu-trigger"]')
    if (expected === null) {
      expect(trigger.exists()).toBe(false)
      return
    }
    expect(trigger.attributes('aria-label')).toBe("Plus d'actions")
    await openMenu(w)
    expect(menuLabels()).toEqual(expected)
  })

  it('met Supprimer en dernier, en destructif et séparé', async () => {
    const w = mountActions(makeMission({ status: 'published' }))
    await openMenu(w)
    const items = Array.from(document.body.querySelectorAll('[role="menuitem"]'))
    const last = items[items.length - 1]!
    expect(last.textContent!.trim()).toBe('Supprimer')
    expect(last.getAttribute('data-variant')).toBe('destructive')
    expect(document.body.querySelector('[role="separator"]')).not.toBeNull()
  })

  it('sans email vérifié : Candidatures en principal, seul Supprimer dans le menu', async () => {
    const w = mountActions(makeMission({ status: 'published' }), false)
    expect(w.find('[data-primary-action]').text()).toBe('Candidatures · 3')
    await openMenu(w)
    expect(menuLabels()).toEqual(['Supprimer'])
    expect(document.body.querySelector('[role="separator"]')).toBeNull()
  })

  it('émet l’évènement de chaque entrée de menu', async () => {
    const cases: Array<[string, string, string]> = [
      ['action-edit', 'edit', 'm1'],
      ['action-close', 'close', 'm1'],
      ['action-delete', 'delete', 'm1'],
    ]
    for (const [testId, event, id] of cases) {
      const w = mountActions(makeMission({ status: 'published' }))
      await openMenu(w)
      ;(document.body.querySelector(`[data-testid="${testId}"]`) as HTMLElement).click()
      await flushPromises()
      expect(w.emitted(event)).toEqual([[id]])
      w.unmount()
      wrapper = null
      document.body.innerHTML = ''
    }
  })

  it('émet réouvrir, terminer et candidatures depuis le menu', async () => {
    const reopen = mountActions(makeMission({ status: 'closed' }))
    await openMenu(reopen)
    ;(document.body.querySelector('[data-testid="action-reopen"]') as HTMLElement).click()
    await flushPromises()
    expect(reopen.emitted('reopen')).toEqual([['m1']])
    expect(reopen.emitted('viewCandidatures')).toBeUndefined()
    reopen.unmount()
    wrapper = null
    document.body.innerHTML = ''

    const paid = mountActions(makeMission({ status: 'closed', has_paid_payment: true }))
    await openMenu(paid)
    ;(document.body.querySelector('[data-testid="action-complete"]') as HTMLElement).click()
    await flushPromises()
    expect(paid.emitted('complete')).toEqual([['m1']])
  })
})
