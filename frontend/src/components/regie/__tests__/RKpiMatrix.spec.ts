import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import RKpiMatrix, { type RKpiColumn, type RKpiRow } from '../RKpiMatrix.vue'
import RMiniBars from '../RMiniBars.vue'

const columns: RKpiColumn[] = [
  { key: 'pending', label: 'En attente', tone: 'pending' },
  { key: 'done', label: 'Terminées', tone: 'done' },
]
const rows: RKpiRow[] = [
  {
    key: 'candidatures',
    label: 'Candidatures',
    to: '/c',
    cells: { pending: { value: 3, sub: '2 > 5 j', subTone: 'urgent' }, done: { value: 14, sub: '+3 mois', subTone: 'positive' } },
    trend: [2, 4, 3, 6, 8, 10],
  },
  { key: 'bookings', label: 'Bookings', cells: { pending: { value: 1 }, done: { value: 8 } }, trend: [1, 2, 3] },
]

async function mountMatrix() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/', component: { template: '<div />' } }, { path: '/c', component: { template: '<div />' } }],
  })
  await router.push('/')
  return mount(RKpiMatrix, { props: { columns, rows }, global: { plugins: [router] } })
}

describe('RKpiMatrix', () => {
  it('rend un tableau avec en-têtes de colonnes à points d’état et de lignes', async () => {
    const wrapper = await mountMatrix()
    const table = wrapper.find('[data-testid="r-kpi-table"]')
    expect(table.findAll('thead th[scope="col"]').length).toBe(4)
    expect(table.find('thead').text()).toContain('En attente')
    expect(table.find('[data-row="candidatures"] th[scope="row"]').text()).toContain('Candidatures')
    expect(table.find('[data-row="candidatures"] [data-cell="pending"]').text()).toContain('3')
    expect(table.find('[data-row="candidatures"] [data-cell="pending"]').text()).toContain('2 > 5 j')
    expect(table.find('[data-row="candidatures"] [data-cell="done"] .text-positive').text()).toBe('+3 mois')
    expect(table.find('[data-row="candidatures"] a').attributes('href')).toBe('/c')
    expect(table.find('[data-row="bookings"] a').exists()).toBe(false)
  })

  it('affiche une mini-barre par ligne avec trend', async () => {
    const wrapper = await mountMatrix()
    expect(wrapper.find('[data-testid="r-kpi-table"]').findAllComponents(RMiniBars)).toHaveLength(2)
  })

  it('mobile : liste de la première ligne puis de la ligne choisie au segment', async () => {
    const wrapper = await mountMatrix()
    const list = wrapper.find('[data-testid="r-kpi-list"]')
    expect(list.classes()).toContain('md:hidden')
    expect(list.find('[data-cell="done"]').text()).toContain('14')
    await list.findAll('[role="radio"]')[1]!.trigger('click')
    expect(list.find('[data-cell="done"]').text()).toContain('8')
    expect(list.find('[data-cell="done"]').text()).not.toContain('14')
  })

  it('le tableau est masqué en dessous de md', async () => {
    const wrapper = await mountMatrix()
    expect(wrapper.find('[data-testid="r-kpi-table"]').classes()).toEqual(expect.arrayContaining(['hidden', 'md:table']))
  })
})

describe('RMiniBars', () => {
  it('mois en cours en teal, historique en gris, hauteurs proportionnelles', () => {
    const wrapper = mount(RMiniBars, { props: { values: [2, 5, 10], label: 'Par mois' } })
    const rects = wrapper.findAll('rect')
    expect(rects).toHaveLength(3)
    expect(rects[2]!.classes()).toContain('fill-weact-600')
    expect(rects[0]!.classes()).toContain('fill-state-neutral')
    expect(rects[1]!.classes()).toContain('fill-state-neutral')
    expect(Number(rects[2]!.attributes('height'))).toBeGreaterThan(Number(rects[0]!.attributes('height')))
    expect(wrapper.attributes('role')).toBe('img')
    expect(wrapper.attributes('aria-label')).toBe('Par mois')
  })

  it('garde une hauteur minimale pour les zéros', () => {
    const wrapper = mount(RMiniBars, { props: { values: [0, 0], label: 'x' } })
    expect(Number(wrapper.findAll('rect')[0]!.attributes('height'))).toBeGreaterThanOrEqual(2)
  })
})
