import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ConversationListPane from '../components/ConversationListPane.vue'
import { makeListItem, makeSummary } from './fixtures'
import type { ConversationListItem } from '../types'

function mountPane(conversations: ConversationListItem[], extra: Record<string, unknown> = {}) {
  return mount(ConversationListPane, {
    props: {
      conversations,
      selectedId: null,
      isLoading: false,
      isRefreshing: false,
      error: null,
      emptyMessage: 'Rien ici',
      emptyActionLabel: 'Voir mes missions',
      ...extra,
    },
  })
}

const labels = (wrapper: ReturnType<typeof mountPane>) =>
  wrapper.findAll('[data-testid="r-pill"]').map((pill) => pill.text())

describe('ConversationListPane — filtres', () => {
  it('propose Toutes / Non lues et une pilule par type de contexte présent', () => {
    const wrapper = mountPane([
      makeListItem({ id: 'a', unread_count: 2 }),
      makeListItem({ id: 'b', context: makeSummary({ type: 'ugc', type_label: 'UGC' }) }),
    ])
    expect(labels(wrapper)).toEqual(['Toutes', 'Non lues1', 'Missions1', 'UGC1'])
  })

  it('n’affiche pas la pilule UGC sans conversation UGC', () => {
    const wrapper = mountPane([makeListItem({ id: 'a' })])
    expect(labels(wrapper)).toEqual(['Toutes', 'Non lues0', 'Missions1'])
  })

  it('filtre sur les non lues', async () => {
    const wrapper = mountPane([
      makeListItem({ id: 'a', unread_count: 2 }),
      makeListItem({ id: 'b' }),
    ])
    expect(wrapper.findAll('[data-testid="conversation-item"]')).toHaveLength(2)
    await wrapper.findAll('[data-testid="r-pill"]')[1]!.trigger('click')
    const rows = wrapper.findAll('[data-testid="conversation-item"]')
    expect(rows).toHaveLength(1)
    expect(rows[0]!.attributes('data-unread')).toBe('true')
  })

  it('filtre sur le type de contexte et gère le filtre vide', async () => {
    const wrapper = mountPane([
      makeListItem({ id: 'a' }),
      makeListItem({ id: 'b', context: makeSummary({ type: 'ugc', type_label: 'UGC' }) }),
    ])
    await wrapper.findAll('[data-testid="r-pill"]')[3]!.trigger('click')
    expect(wrapper.findAll('[data-testid="conversation-item"]')).toHaveLength(1)

    await wrapper.findAll('[data-testid="r-pill"]')[1]!.trigger('click')
    expect(wrapper.find('[data-testid="conversations-filter-empty"]').exists()).toBe(true)
  })

  it('affiche point d’état, type, titre, badge non lu et émet select', async () => {
    const item = makeListItem({ id: 'a', unread_count: 3 })
    const wrapper = mountPane([item])
    const row = wrapper.find('[data-testid="conversation-item"]')
    expect(row.text()).toContain('Mission · Lookbook Wax')
    expect(row.find('[data-testid="r-status-dot"]').exists()).toBe(true)
    expect(row.find('[data-testid="unread-badge"]').text()).toContain('3')
    await row.trigger('click')
    expect(wrapper.emitted('select')?.[0]).toEqual([item])
  })

  it('état vide avec action', async () => {
    const wrapper = mountPane([])
    expect(wrapper.find('[data-testid="conversations-empty"]').text()).toContain('Rien ici')
    await wrapper.find('[data-testid="conversations-empty"] button').trigger('click')
    expect(wrapper.emitted('empty-action')).toHaveLength(1)
  })

  it('sans contexte, retombe sur le titre de mission', () => {
    const wrapper = mountPane([makeListItem({ context: null })])
    expect(wrapper.find('[data-testid="conversation-item"]').text()).toContain('Lookbook Wax')
    expect(wrapper.find('[data-testid="r-status-dot"]').exists()).toBe(false)
  })
})
