import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { House, Briefcase, MessageCircle, User } from 'lucide-vue-next'
import DashboardMobileTabs from '../DashboardMobileTabs.vue'

describe('DashboardMobileTabs', () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/face/dashboard', component: { template: '<div />' } },
      { path: '/face/missions', component: { template: '<div />' } },
      { path: '/face/missions/:id', component: { template: '<div />' } },
      { path: '/face/messages', component: { template: '<div />' } },
      { path: '/face/conversations/:id', component: { template: '<div />' } },
      { path: '/face/profile', component: { template: '<div />' } },
    ],
  })

  const tabs = [
    { label: 'Accueil', icon: House, to: '/face/dashboard' },
    { label: 'Missions', icon: Briefcase, to: '/face/missions' },
    { label: 'Messages', icon: MessageCircle, to: '/face/messages', badge: 3, match: ['/face/conversations'] },
    { label: 'Profil', icon: User, to: '/face/profile' },
  ]

  function mountTabs(props = {}) {
    return mount(DashboardMobileTabs, { props: { tabs, ...props }, global: { plugins: [router] } })
  }

  beforeEach(async () => {
    await router.push('/face/dashboard')
  })

  it('rend une navigation nommée avec les 4 destinations, masquée dès lg', () => {
    const wrapper = mountTabs()
    const nav = wrapper.find('[data-testid="mobile-tabbar"]')
    expect(nav.element.tagName).toBe('NAV')
    expect(nav.attributes('aria-label')).toBe('Navigation principale')
    expect(nav.classes()).toContain('lg:hidden')
    expect(wrapper.findAll('a').map((a) => a.text().replace(/\d+$/, ''))).toEqual(['Accueil', 'Missions', 'Messages', 'Profil'])
  })

  it('chaque cible fait au moins 44 px de haut', () => {
    const wrapper = mountTabs()
    wrapper.findAll('a').forEach((a) => expect(a.classes()).toContain('min-h-11'))
  })

  it('marque la destination active avec aria-current et la teinte teal 700', () => {
    const wrapper = mountTabs()
    const [home, missions] = wrapper.findAll('a')
    expect(home!.attributes('aria-current')).toBe('page')
    expect(home!.classes()).toContain('text-weact-700')
    expect(missions!.attributes('aria-current')).toBeUndefined()
    expect(missions!.classes()).not.toContain('text-weact-700')
  })

  it('reste actif sur une sous-route (détail de mission)', async () => {
    await router.push('/face/missions/42')
    const wrapper = mountTabs()
    expect(wrapper.find('[data-testid="mobile-tab-missions"]').attributes('aria-current')).toBe('page')
    expect(wrapper.find('[data-testid="mobile-tab-accueil"]').attributes('aria-current')).toBeUndefined()
  })

  it('reste sur Messages quand une conversation est ouverte (préfixe match)', async () => {
    await router.push('/face/conversations/abc-123')
    const wrapper = mountTabs()
    expect(wrapper.find('[data-testid="mobile-tab-messages"]').attributes('aria-current')).toBe('page')
  })

  it('affiche le compteur non lu et l’expose dans le nom accessible', () => {
    const wrapper = mountTabs()
    const messages = wrapper.find('[data-testid="mobile-tab-messages"]')
    expect(messages.find('[data-testid="mobile-tab-badge"]').text()).toBe('3')
    expect(messages.attributes('aria-label')).toBe('Messages, 3 non lus')
    expect(wrapper.find('[data-testid="mobile-tab-profil"]').attributes('aria-label')).toBeUndefined()
  })

  it('plafonne le compteur à « 9+ » (badgeMax) et le masque à 0', () => {
    const withMessages = (badge: number) =>
      tabs.map((t) => (t.label === 'Messages' ? { ...t, badge, badgeMax: 9 } : t))

    const over = mountTabs({ tabs: withMessages(14) })
    expect(over.find('[data-testid="mobile-tab-badge"]').text()).toBe('9+')

    const nine = mountTabs({ tabs: withMessages(9) })
    expect(nine.find('[data-testid="mobile-tab-badge"]').text()).toBe('9')

    const zero = mountTabs({ tabs: withMessages(0) })
    expect(zero.find('[data-testid="mobile-tab-badge"]').exists()).toBe(false)
    expect(zero.find('[data-testid="mobile-tab-messages"]').attributes('aria-label')).toBeUndefined()
  })

  it('réserve la zone de sécurité en bas', () => {
    const wrapper = mountTabs()
    expect(wrapper.find('[data-testid="mobile-tabbar"]').classes().join(' ')).toContain('safe-area-inset-bottom')
  })
})
