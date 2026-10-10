import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import DataTable from '../DataTable.vue'
import type { DataTableColumn } from '../dataTableFeatures'

interface Row {
  id: string
  name: string
  amount: number
}

const rows: Row[] = [
  { id: 'a', name: 'Alpha', amount: 100 },
  { id: 'b', name: 'Bravo', amount: 200 },
]

const columns: DataTableColumn<Row>[] = [
  { id: 'name', accessorFn: (r) => r.name, header: 'Nom', enableSorting: false },
  { id: 'amount', accessorFn: (r) => r.amount, header: 'Montant', enableSorting: true },
  { id: 'actions', header: 'Actions', enableSorting: false },
]

const baseProps = {
  columns,
  data: rows,
  rowId: (r: Row) => r.id,
  sorting: [] as { id: string; desc: boolean }[],
  page: 1,
  pageSize: 15,
  total: 2,
  lastPage: 1,
}

function mountTable(props: Record<string, unknown> = {}, slots: Record<string, unknown> = {}) {
  return mount(DataTable as unknown as ReturnType<typeof defineComponent>, {
    props: { ...baseProps, ...props },
    slots: {
      'cell-name': ({ row }: { row: Row }) => h('span', { 'data-testid': `name-${row.id}` }, row.name),
      'cell-amount': ({ row }: { row: Row }) => `${row.amount} FCFA`,
      'cell-actions': ({ row }: { row: Row }) => h('a', { href: `/r/${row.id}` }, `Voir ${row.id}`),
      card: ({ row }: { row: Row }) => h('div', { 'data-testid': `card-${row.id}` }, row.name),
      empty: () => h('p', { 'data-testid': 'custom-empty' }, 'Rien ici'),
      ...slots,
    },
  })
}

describe('DataTable', () => {
  it('applies the column meta (sticky right + class) to header and cells', () => {
    const metaColumns: DataTableColumn<Row>[] = [
      { id: 'name', accessorFn: (r) => r.name, header: 'Nom', enableSorting: false },
      { id: 'amount', accessorFn: (r) => r.amount, header: 'Montant', enableSorting: false, meta: { class: 'hidden 2xl:table-cell' } },
      { id: 'actions', header: 'Actions', enableSorting: false, meta: { sticky: 'right' } },
    ]
    const wrapper = mountTable({ columns: metaColumns })
    const ths = wrapper.findAll('th')
    expect(ths[0]!.classes()).not.toContain('sticky')
    expect(ths[1]!.classes()).toContain('hidden')
    expect(ths[1]!.classes()).toContain('2xl:table-cell')
    expect(ths[2]!.classes()).toEqual(expect.arrayContaining(['sticky', 'right-0']))
    const tds = wrapper.findAll('tbody tr')[0]!.findAll('td')
    expect(tds[1]!.classes()).toContain('hidden')
    expect(tds[2]!.classes()).toEqual(expect.arrayContaining(['sticky', 'right-0', 'bg-card']))
    expect(tds[0]!.classes()).not.toContain('sticky')
  })

  it('renders headers and rows through the cell slots', () => {
    const wrapper = mountTable()
    expect(wrapper.findAll('th').map((th) => th.text())).toEqual(['Nom', 'Montant', 'Actions'])
    expect(wrapper.findAll('tbody tr')).toHaveLength(2)
    expect(wrapper.find('[data-testid="name-a"]').text()).toBe('Alpha')
    expect(wrapper.text()).toContain('200 FCFA')
  })

  it('renders a sort button with aria-sort only on sortable columns', () => {
    const wrapper = mountTable()
    const ths = wrapper.findAll('th')
    expect(ths[0]!.find('button').exists()).toBe(false)
    expect(ths[0]!.attributes('aria-sort')).toBeUndefined()
    expect(ths[1]!.find('button').exists()).toBe(true)
    expect(ths[1]!.attributes('aria-sort')).toBe('none')
  })

  it('reflects the sorting prop in aria-sort', () => {
    const asc = mountTable({ sorting: [{ id: 'amount', desc: false }] })
    expect(asc.findAll('th')[1]!.attributes('aria-sort')).toBe('ascending')
    const desc = mountTable({ sorting: [{ id: 'amount', desc: true }] })
    expect(desc.findAll('th')[1]!.attributes('aria-sort')).toBe('descending')
  })

  it('cycles ascending, descending, then removes the sort on header clicks', async () => {
    const wrapper = mountTable()
    await wrapper.findAll('th')[1]!.find('button').trigger('click')
    expect(wrapper.emitted('update:sorting')!.at(-1)).toEqual([[{ id: 'amount', desc: false }]])

    await wrapper.setProps({ sorting: [{ id: 'amount', desc: false }] })
    await wrapper.findAll('th')[1]!.find('button').trigger('click')
    expect(wrapper.emitted('update:sorting')!.at(-1)).toEqual([[{ id: 'amount', desc: true }]])

    await wrapper.setProps({ sorting: [{ id: 'amount', desc: true }] })
    await wrapper.findAll('th')[1]!.find('button').trigger('click')
    expect(wrapper.emitted('update:sorting')!.at(-1)).toEqual([[]])
  })

  it('shows pagination only when there is more than one page and emits page-change', async () => {
    expect(mountTable().find('nav[aria-label="Pagination"]').exists()).toBe(false)

    const wrapper = mountTable({ total: 40, lastPage: 3 })
    expect(wrapper.find('nav[aria-label="Pagination"]').exists()).toBe(true)
    await wrapper.find('[data-testid="pagination-page-2"]').trigger('click')
    expect(wrapper.emitted('page-change')).toEqual([[2]])
  })

  it('emits page-size-change from the page size select', async () => {
    const wrapper = mountTable({ total: 40, lastPage: 3 })
    const select = wrapper.find('select[data-testid="data-table-page-size"]')
    expect(select.exists()).toBe(true)
    await select.setValue('25')
    expect(wrapper.emitted('page-size-change')).toEqual([[25]])
  })

  it('shows skeleton rows while loading without data', () => {
    const wrapper = mountTable({ data: [], loading: true, total: 0 })
    expect(wrapper.find('[data-testid="data-table-skeleton"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="name-a"]').exists()).toBe(false)
  })

  it('keeps rows visible and marks the table busy when reloading with data', () => {
    const wrapper = mountTable({ loading: true })
    expect(wrapper.find('[data-testid="name-a"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="data-table-skeleton"]').exists()).toBe(false)
    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true)
  })

  it('shows the error with a retry button', async () => {
    const wrapper = mountTable({ error: 'Boom', data: [] })
    expect(wrapper.text()).toContain('Boom')
    await wrapper.find('[data-testid="data-table-retry"]').trigger('click')
    expect(wrapper.emitted('retry')).toHaveLength(1)
  })

  it('renders the empty slot when there is no row', () => {
    const wrapper = mountTable({ data: [], total: 0 })
    expect(wrapper.find('[data-testid="custom-empty"]').exists()).toBe(true)
    expect(wrapper.find('table').exists()).toBe(false)
  })

  it('renders the card slot instead of the table in cards view', () => {
    const wrapper = mountTable({ view: 'cards' })
    expect(wrapper.find('table').exists()).toBe(false)
    expect(wrapper.find('[data-testid="card-a"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="card-b"]').exists()).toBe(true)
  })

  it('keeps list semantics in cards view (li children of the ul)', () => {
    const wrapper = mountTable({ view: 'cards' })
    const items = wrapper.findAll('ul > li')
    expect(items).toHaveLength(2)
    expect(items[0]!.find('[data-testid="card-a"]').exists()).toBe(true)
  })

  it('accepts a per-row rowClickable predicate', () => {
    const wrapper = mountTable({ rowClickable: (r: Row) => r.id === 'a' })
    const rowsEl = wrapper.findAll('tbody tr')
    expect(rowsEl[0]!.classes()).toContain('cursor-pointer')
    expect(rowsEl[1]!.classes()).not.toContain('cursor-pointer')
  })

  it('emits row-click when a row is clicked but not when an inner link is', async () => {
    const wrapper = mountTable({ rowClickable: true })
    await wrapper.findAll('tbody tr')[0]!.trigger('click')
    expect(wrapper.emitted('row-click')).toEqual([[rows[0]]])
    await wrapper.findAll('tbody tr')[1]!.find('a').trigger('click')
    expect(wrapper.emitted('row-click')).toHaveLength(1)
  })
})
