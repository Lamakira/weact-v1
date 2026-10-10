import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { Clock } from 'lucide-vue-next'
import RTodoList from '../RTodoList.vue'
import RTodoItem from '../RTodoItem.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/', component: { template: '<div />' } }, { path: '/b', component: { template: '<div />' } }],
  })
}

describe('RTodoItem', () => {
  it('rend titre, meta, partie urgente en ambre et icône', () => {
    const wrapper = mount(RTodoItem, {
      props: { title: 'Répondre au booking', meta: 'Lookbook Wax', urgentMeta: 'expire dans 22 h', icon: Clock },
    })
    expect(wrapper.text()).toContain('Répondre au booking')
    expect(wrapper.find('[data-testid="r-todo-meta"]').text()).toBe('Lookbook Wax · expire dans 22 h')
    expect(wrapper.find('[data-testid="r-todo-urgent"]').classes()).toContain('text-urgent')
    expect(wrapper.find('svg').exists()).toBe(true)
  })

  it('le titre est un bouton atteignable au clavier qui émet select', async () => {
    const wrapper = mount(RTodoItem, { props: { title: 'Ajouter des photos' } })
    const title = wrapper.find('button')
    expect(title.attributes('type')).toBe('button')
    await title.trigger('click')
    expect(wrapper.emitted('select')).toHaveLength(1)
  })

  it('le bouton d’action est distinct et n’émet que action', async () => {
    const wrapper = mount(RTodoItem, { props: { title: 'T', actionLabel: 'Répondre', actionVariant: 'primary' } })
    const action = wrapper.find('[data-testid="r-todo-action"]')
    expect(action.text()).toBe('Répondre')
    expect(action.classes()).toContain('bg-weact-600')
    await action.trigger('click')
    expect(wrapper.emitted('action')).toHaveLength(1)
    expect(wrapper.emitted('select')).toBeUndefined()
  })

  it('devient un lien quand `to` est fourni', async () => {
    const router = makeRouter()
    await router.push('/')
    const wrapper = mount(RTodoItem, { props: { title: 'Aller', to: '/b' }, global: { plugins: [router] } })
    const link = wrapper.find('a')
    expect(link.attributes('href')).toBe('/b')
    expect(link.text()).toBe('Aller')
  })
})

describe('RTodoList', () => {
  it('affiche titre, compteur et items', () => {
    const wrapper = mount(RTodoList, {
      props: { count: 2 },
      slots: { default: '<li>a</li><li>b</li>' },
    })
    expect(wrapper.text()).toContain('À faire')
    expect(wrapper.find('[data-testid="r-todo-count"]').text()).toContain('2')
    expect(wrapper.findAll('li')).toHaveLength(2)
    expect(wrapper.find('[data-testid="r-todo-empty"]').exists()).toBe(false)
  })

  it('affiche l’état vide quand count vaut 0', () => {
    const wrapper = mount(RTodoList, { props: { count: 0 } })
    expect(wrapper.find('[data-testid="r-todo-empty"]').text()).toBe('Rien à faire pour le moment')
    expect(wrapper.find('ul').exists()).toBe(false)
  })

  it('affiche l’état vide sans count ni slot', () => {
    const wrapper = mount(RTodoList)
    expect(wrapper.find('[data-testid="r-todo-empty"]').exists()).toBe(true)
  })

  it('affiche le hint dans l’en-tête', () => {
    const wrapper = mount(RTodoList, { props: { count: 1, hint: 'Trié par urgence' }, slots: { default: '<li>a</li>' } })
    expect(wrapper.text()).toContain('Trié par urgence')
  })
})
