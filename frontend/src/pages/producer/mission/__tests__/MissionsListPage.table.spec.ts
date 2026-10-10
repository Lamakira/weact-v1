import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import type { Mission } from '@/features/mission/types'
import DeleteMissionDialog from '@/features/mission/components/DeleteMissionDialog.vue'

const emailVerified = vi.hoisted(() => ({ value: true }))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { id: 1, userable_type: 'Producer' },
    get isEmailVerified() {
      return emailVerified.value
    },
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn(), clear: vi.fn(), toast: {} }),
}))

const getMissionsPage = vi.hoisted(() => vi.fn())
vi.mock('@/features/mission/services/missionApi', () => ({
  missionApi: { getMissionsPage, getMission: vi.fn(), getCommissionStatus: vi.fn(), getPaymentStatus: vi.fn() },
}))

import MissionsListPage from '../MissionsListPage.vue'

// reka-ui measures its popper with ResizeObserver, absent from jsdom.
globalThis.ResizeObserver ??= class {
  observe() {}
  unobserve() {}
  disconnect() {}
} as unknown as typeof ResizeObserver

/** Opens the « Plus d'actions » menu of a row/card (portaled to document.body). */
async function openRowMenu(scope: { find: (s: string) => { trigger: (e: string, o?: object) => Promise<void> } }) {
  await scope.find('[data-testid="actions-menu-trigger"]').trigger('keydown', { key: 'Enter' })
  await flushPromises()
}
function menuItem(testId: string): HTMLElement {
  return document.body.querySelector(`[role="menuitem"][data-testid="${testId}"]`) as HTMLElement
}
function menuLabels(): string[] {
  return Array.from(document.body.querySelectorAll('[role="menuitem"]')).map((el) => el.textContent!.trim())
}

function makeMission(id: string, overrides: Partial<Mission> = {}): Mission {
  return {
    id,
    titre: `Mission ${id}`,
    description: 'fixture',
    date_tournage: '2026-08-01T00:00:00+00:00',
    profil_recherche: 'Face',
    budget: 100000,
    date_limite_candidature: '2026-07-25T00:00:00+00:00',
    nombre_faces_voulu: 2,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    type_mission_autre: null,
    type_compensation: null,
    type_compensation_label: null,
    nom_produit: null,
    valeur_produit: null,
    nombre_videos: null,
    montant_remuneration: null,
    commission_ugc: null,
    commission_paid_at: null,
    genre_voulu: 'tous',
    genre_voulu_label: 'Homme et Femme',
    lieu: 'Cotonou',
    duree: '1 jour',
    status: 'published',
    status_label: 'Publiée',
    is_accepting_candidatures: true,
    has_paid_payment: false,
    candidatures_count: 3,
    created_at: '2026-07-01T00:00:00+00:00',
    updated_at: '2026-07-01T00:00:00+00:00',
    ...overrides,
  }
}

const published = makeMission('pub')
const closedPaid = makeMission('clo', { status: 'closed', status_label: 'Clôturée', has_paid_payment: true })
const ugcPending = makeMission('ugc', {
  status: 'pending_payment',
  status_label: 'En attente de paiement',
  commission_ugc: 2500,
  date_tournage: null as unknown as string,
})

function listResponse(missions: Mission[], meta: Partial<{ current_page: number; last_page: number; per_page: number; total: number }> = {}) {
  return {
    data: missions,
    message: 'ok',
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, last_page: 1, per_page: 15, total: missions.length, ...meta },
  }
}

function mockMatchMedia(desktop: boolean) {
  Object.defineProperty(window, 'matchMedia', {
    configurable: true,
    writable: true,
    value: (query: string) => ({
      matches: desktop,
      media: query,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    }),
  })
}

const Stub = defineComponent({ setup: () => () => h('div') })

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/producer/missions', name: 'producer-missions', component: Stub },
      { path: '/producer/missions/publish', name: 'publish-mission', component: Stub },
      { path: '/producer/missions/:id/edit', name: 'edit-mission', component: Stub },
      { path: '/producer/missions/:id/candidatures', name: 'producer-mission-candidatures', component: Stub },
      { path: '/producer/missions/:id/attendance', name: 'producer-mission-attendance', component: Stub },
    ],
  })
}

const overlayStub = defineComponent({
  name: 'UgcPaymentOverlay',
  props: { modelValue: { type: Boolean, required: true }, ownerId: { type: String, default: '' } },
  setup: (props) => () => h('div', { 'data-testid': 'ugc-overlay-stub', 'data-open': String(props.modelValue) }),
})

async function mountPage(url = '/producer/missions') {
  const router = makeRouter()
  await router.push(url)
  await router.isReady()
  const wrapper = mount(MissionsListPage, {
    attachTo: document.body,
    global: { plugins: [router], stubs: { UgcPaymentOverlay: overlayStub } },
  })
  await flushPromises()
  return { wrapper, router }
}

const defaultParams = { page: 1, perPage: 15, status: '', sort: null, direction: 'asc' }

describe('MissionsListPage — table (md and up)', () => {
  beforeEach(() => {
    getMissionsPage.mockReset()
    getMissionsPage.mockResolvedValue(listResponse([published, closedPaid, ugcPending], { total: 40, last_page: 3 }))
    emailVerified.value = true
    mockMatchMedia(true)
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('renders a table with the expected columns and row data', async () => {
    const { wrapper } = await mountPage()
    expect(wrapper.find('table').exists()).toBe(true)
    expect(wrapper.findAll('th').map((th) => th.text())).toEqual([
      'Mission', 'Statut', 'Limite candidature', 'Tournage', 'Candidatures', 'Budget', 'Créée le', 'Actions',
    ])
    const rows = wrapper.findAll('tbody tr')
    expect(rows).toHaveLength(3)
    expect(rows[0]!.text()).toContain('Mission pub')
    expect(rows[0]!.text()).toContain('Publicité')
    expect(rows[0]!.text()).toContain('Publiée')
    expect(rows[2]!.text()).toContain('UGC')
    expect(rows[2]!.text()).toContain('En attente de paiement')
  })

  it('only offers sorting on status, the two dates, candidatures and creation date', async () => {
    const { wrapper } = await mountPage()
    const sortable = wrapper.findAll('th').filter((th) => th.find('button').exists()).map((th) => th.text())
    expect(sortable).toEqual(['Statut', 'Limite candidature', 'Tournage', 'Candidatures', 'Créée le'])
  })

  it('loads the first page opting in to pagination with the default params', async () => {
    await mountPage()
    expect(getMissionsPage).toHaveBeenCalledTimes(1)
    expect(getMissionsPage).toHaveBeenCalledWith(defaultParams)
  })

  it('restores status / sort / page / page size from the URL', async () => {
    await mountPage('/producer/missions?status=closed&sort=candidatures_count&direction=desc&page=2&per_page=25')
    expect(getMissionsPage).toHaveBeenCalledWith({
      page: 2, perPage: 25, status: 'closed', sort: 'candidatures_count', direction: 'desc',
    })
  })

  it('drops invalid URL values instead of sending them (422)', async () => {
    await mountPage('/producer/missions?sort=budget&status=nope&page=0&per_page=3')
    expect(getMissionsPage).toHaveBeenCalledWith(defaultParams)
  })

  it('cycles the sort of a column through aria-sort, the URL and the API', async () => {
    const { wrapper, router } = await mountPage()
    const header = () => wrapper.findAll('th').find((th) => th.text() === 'Candidatures')!

    expect(header().attributes('aria-sort')).toBe('none')
    await header().find('button').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toMatchObject({ sort: 'candidatures_count', direction: 'asc' })
    expect(getMissionsPage).toHaveBeenLastCalledWith({ ...defaultParams, sort: 'candidatures_count', direction: 'asc' })
    expect(header().attributes('aria-sort')).toBe('ascending')

    await header().find('button').trigger('click')
    await flushPromises()
    expect(getMissionsPage).toHaveBeenLastCalledWith({ ...defaultParams, sort: 'candidatures_count', direction: 'desc' })
    expect(header().attributes('aria-sort')).toBe('descending')

    await header().find('button').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.sort).toBeUndefined()
    expect(getMissionsPage).toHaveBeenLastCalledWith(defaultParams)
  })

  it('goes back to page 1 when the status filter changes and keeps the sort', async () => {
    const { wrapper, router } = await mountPage('/producer/missions?page=3&sort=status&direction=asc')
    const closedTab = wrapper.findAll('button').find((b) => b.text() === 'Clôturée')!
    await closedTab.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.page).toBeUndefined()
    expect(router.currentRoute.value.query).toMatchObject({ status: 'closed', sort: 'status' })
    expect(getMissionsPage).toHaveBeenLastCalledWith({ page: 1, perPage: 15, status: 'closed', sort: 'status', direction: 'asc' })
  })

  it('paginates through the URL', async () => {
    const { wrapper, router } = await mountPage()
    await wrapper.find('[data-testid="pagination-page-2"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.page).toBe('2')
    expect(getMissionsPage).toHaveBeenLastCalledWith({ ...defaultParams, page: 2 })
  })

  it('redirects to the last page when the requested page is past the end', async () => {
    getMissionsPage.mockResolvedValueOnce(listResponse([], { current_page: 5, last_page: 3, total: 40 }))
    const { wrapper, router } = await mountPage('/producer/missions?page=5')
    await flushPromises()

    expect(router.currentRoute.value.query.page).toBe('3')
    expect(getMissionsPage).toHaveBeenLastCalledWith({ ...defaultParams, page: 3 })
    expect(wrapper.text()).not.toContain("Vous n'avez pas encore de missions")
  })

  it('only marks editable rows as clickable', async () => {
    const { wrapper } = await mountPage()
    const rows = wrapper.findAll('tbody tr')
    expect(rows[0]!.classes()).toContain('cursor-pointer')
    expect(rows[1]!.classes()).not.toContain('cursor-pointer')
    expect(rows[2]!.classes()).not.toContain('cursor-pointer')
  })

  it('shows the total only once (table footer)', async () => {
    const { wrapper } = await mountPage()
    expect(wrapper.text().match(/40 mission/g) ?? []).toHaveLength(0)
    expect(wrapper.text().match(/40 résultat/g)).toHaveLength(1)
  })

  it('does not refetch when only a foreign query key (?pay) changes', async () => {
    const { router } = await mountPage()
    expect(getMissionsPage).toHaveBeenCalledTimes(1)
    await router.replace({ query: { ref: 'x' } })
    await flushPromises()
    expect(getMissionsPage).toHaveBeenCalledTimes(1)
  })

  it('offers one labelled primary action per row and the others in the menu', async () => {
    const { wrapper } = await mountPage()
    const rows = wrapper.findAll('tbody tr')
    const primaryOf = (i: number) => rows[i]!.find('[data-primary-action]').text()

    // published standard mission: candidatures primary; edit, close, delete in the menu
    expect(primaryOf(0)).toBe('Candidatures · 3')
    await openRowMenu(rows[0]!)
    expect(menuLabels()).toEqual(['Modifier', 'Clôturer', 'Supprimer'])
    document.body.querySelector('[role="menu"]')!.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await flushPromises()

    // closed + paid payment: attendance primary; candidatures + complete (no reopen: payment exists)
    expect(primaryOf(1)).toBe('Valider les présences')
    await openRowMenu(rows[1]!)
    expect(menuLabels()).toEqual(['Candidatures · 3', 'Terminer'])
  })

  it('makes the commission the primary action of an UGC mission awaiting payment', async () => {
    const { wrapper } = await mountPage()
    const row = wrapper.findAll('tbody tr')[2]!
    expect(row.find('[data-primary-action]').text()).toBe('Régler la commission')
    await openRowMenu(row)
    expect(menuLabels()).toEqual(['Candidatures · 3'])
  })

  it('wires the actions: navigation, dialogs and the commission tunnel', async () => {
    const { wrapper, router } = await mountPage()
    const rows = wrapper.findAll('tbody tr')

    await rows[1]!.find('[data-testid="action-attendance"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/producer/missions/clo/attendance')

    await router.push('/producer/missions')
    await flushPromises()
    await openRowMenu(wrapper.findAll('tbody tr')[0]!)
    menuItem('action-delete').click()
    await flushPromises()
    expect(wrapper.findComponent(DeleteMissionDialog).props('isOpen')).toBe(true)

    await wrapper.findAll('tbody tr')[2]!.find('[data-testid="pay-commission-button"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-testid="ugc-overlay-stub"]').attributes('data-open')).toBe('true')
  })

  it('renders status dots (not coloured pills) and a plain candidatures number', async () => {
    const { wrapper } = await mountPage()
    const rows = wrapper.findAll('tbody tr')
    const dot = rows[0]!.find('td:nth-child(2) [data-testid="r-status-dot"]')
    expect(dot.exists()).toBe(true)
    expect(dot.text()).toBe('Publiée')
    expect(dot.attributes('data-tone')).toBe('success')
    expect(rows[2]!.find('td:nth-child(2) [data-testid="r-status-dot"]').attributes('data-tone')).toBe('pending')
    // candidatures column: plain number, no button, no icon
    const cell = rows[0]!.find('td:nth-child(5)')
    expect(cell.text()).toBe('3 / 2')
    expect(cell.find('span').attributes('aria-label')).toBe('3 candidatures, 2 Faces voulues')
    expect(cell.html()).not.toMatch(/tabular-nums|font-mono/)
    expect(cell.find('button').exists()).toBe(false)
    expect(cell.find('svg').exists()).toBe(false)
  })

  it('pins the Actions column to the right and hides « Créée le » below 2xl', async () => {
    const { wrapper } = await mountPage()
    const ths = wrapper.findAll('th')
    const actions = ths[ths.length - 1]!
    expect(actions.text()).toBe('Actions')
    expect(actions.classes()).toEqual(expect.arrayContaining(['sticky', 'right-0']))
    const created = ths[ths.length - 2]!
    expect(created.text()).toContain('Créée le')
    expect(created.classes()).toEqual(expect.arrayContaining(['hidden', '2xl:table-cell']))
  })

  it('opens the edit form from an editable row click only', async () => {
    const { wrapper, router } = await mountPage()
    await wrapper.findAll('tbody tr')[0]!.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/producer/missions/pub/edit')

    await router.push('/producer/missions')
    await flushPromises()
    // closed row: not editable -> a row click does nothing
    await wrapper.findAll('tbody tr')[1]!.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/producer/missions')
  })

  it('only keeps candidatures and delete when the email is not verified', async () => {
    emailVerified.value = false
    const { wrapper } = await mountPage()
    const row = wrapper.findAll('tbody tr')[0]!
    expect(row.find('[data-primary-action]').text()).toBe('Candidatures · 3')
    await openRowMenu(row)
    expect(menuLabels()).toEqual(['Supprimer'])
    expect(wrapper.text()).not.toContain('Publier une mission')
  })

  it('shows the first-mission empty state with the publish CTA', async () => {
    getMissionsPage.mockResolvedValue(listResponse([]))
    const { wrapper, router } = await mountPage()
    expect(wrapper.text()).toContain("Vous n'avez pas encore de missions")
    await wrapper.findAll('button').find((b) => b.text().includes('Publier ma première mission'))!.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/producer/missions/publish')
  })

  it('shows the filtered empty state and lets the user clear the filter', async () => {
    getMissionsPage.mockResolvedValue(listResponse([]))
    const { wrapper, router } = await mountPage('/producer/missions?status=completed')
    expect(wrapper.text()).toContain('Aucune mission trouvée')
    await wrapper.findAll('button').find((b) => b.text() === 'Voir toutes les missions')!.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.status).toBeUndefined()
    expect(getMissionsPage).toHaveBeenLastCalledWith(defaultParams)
  })

  it('shows the error with a retry that refetches', async () => {
    getMissionsPage.mockRejectedValueOnce(new Error('Réseau'))
    const { wrapper } = await mountPage()
    expect(wrapper.text()).toContain('Impossible de charger vos missions pour le moment.')
    getMissionsPage.mockResolvedValue(listResponse([published]))
    await wrapper.find('[data-testid="data-table-retry"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('table').exists()).toBe(true)
  })
})

describe('MissionsListPage — compact cards (below md)', () => {
  beforeEach(() => {
    getMissionsPage.mockReset()
    getMissionsPage.mockResolvedValue(listResponse([published, closedPaid, ugcPending]))
    emailVerified.value = true
    mockMatchMedia(false)
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('renders one compact card per mission instead of a table, with labelled actions', async () => {
    const { wrapper } = await mountPage()
    expect(wrapper.find('table').exists()).toBe(false)
    expect(wrapper.find('[data-testid="mission-card-pub"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="mission-card-clo"]').text()).toContain('Valider les présences')
    expect(wrapper.find('[data-testid="mission-card-ugc"]').text()).toContain('Régler la commission')
    expect(wrapper.find('[data-testid="mission-card-ugc"]').text()).toContain('UGC')
  })

  it('keeps actions working from the cards', async () => {
    const { wrapper, router } = await mountPage()
    await openRowMenu(wrapper.find('[data-testid="mission-card-pub"]'))
    menuItem('action-edit').click()
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/producer/missions/pub/edit')
  })
})
