/**
 * Bug F4 — Reproduction test (RED until fixed)
 *
 * Le <main data-testid="dashboard-content"> de DashboardLayout est l'UNIQUE
 * scroller des dashboards (le wrapper est h-screen overflow-hidden : la
 * fenêtre ne scrolle jamais). Depuis que App.vue keye le layout par
 * matched[0]?.path, le layout PERSISTE à travers les navigations enfants,
 * et rien ne remet son scrollTop à zéro (le scrollBehavior du router ne
 * touche que window).
 *
 * Contrat du fix (implémenté séparément dans DashboardLayout) : à chaque
 * changement de route.fullPath, le layout
 *   1. sauvegarde le scrollTop courant du <main> pour la route QUITTÉE
 *      (clé = fullPath),
 *   2. après rendu, positionne le <main> : si la route d'ARRIVÉE a
 *      meta.keepAlive → restaure la position sauvegardée pour son fullPath
 *      (0 si aucune) ; sinon → scrollTop = 0.
 *   Affectation directe `el.scrollTop = …` (pas scrollTo — jsdom-compatible).
 */
import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h, inject, nextTick } from 'vue'
import DashboardLayout from '../DashboardLayout.vue'
import {
  restoreDashboardScrollKey,
  type RestoreDashboardScroll,
} from '../dashboardScrollRestoration'
import { LayoutDashboard, FileText } from 'lucide-vue-next'
import { useRoute } from 'vue-router'

// Mock the logo import
vi.mock('@/assets/images/logonoir.png', () => ({
  default: '/mock-logo.png',
}))

// Mock useSidebarState (même recette que DashboardLayout.spec.ts)
vi.mock('@/composables/useSidebarState', async () => {
  const { ref } = await import('vue')
  const isExpanded = ref(true)
  const isMobileOpen = ref(false)
  return {
    useSidebarState: () => ({
      isExpanded,
      isMobileOpen,
      closeMobile: () => {
        isMobileOpen.value = false
      },
      collapse: () => {
        isExpanded.value = false
      },
      openMobile: () => {},
      toggle: () => {},
    }),
  }
})

// Mock child components
vi.mock('../DashboardSidebar.vue', () => ({
  default: {
    name: 'DashboardSidebar',
    props: ['items', 'logoText'],
    template: '<aside data-testid="desktop-sidebar"><slot /></aside>',
  },
}))

vi.mock('../DashboardHeader.vue', () => ({
  default: {
    name: 'DashboardHeader',
    props: ['title', 'userEmail', 'userName', 'avatarUrl', 'isLoggingOut'],
    emits: ['logout'],
    template: '<header data-testid="dashboard-header-mock"><slot /></header>',
  },
}))

const sidebarItems = [
  { label: 'Liste', icon: LayoutDashboard, to: '/parent/list' },
  { label: 'Détail', icon: FileText, to: '/parent/detail' },
]

let capturedRestoreDashboardScroll: RestoreDashboardScroll | undefined

const AfterEnterTrigger = defineComponent({
  setup() {
    const restoreDashboardScroll = inject(restoreDashboardScrollKey)
    capturedRestoreDashboardScroll = restoreDashboardScroll
    const route = useRoute()
    return () => h('button', {
      'data-testid': 'after-enter-trigger',
      onClick: () => restoreDashboardScroll?.({
        fullPath: route.fullPath,
        keepAlive: Boolean(route.meta.keepAlive),
      }),
    })
  },
})

/** Hôte : rend DashboardLayout avec le <router-view/> enfant dans son slot */
const LayoutHost = defineComponent({
  components: { AfterEnterTrigger, DashboardLayout },
  data() {
    return { sidebarItems }
  },
  template: `
    <DashboardLayout :sidebar-items="sidebarItems" title="Test">
      <router-view />
      <AfterEnterTrigger />
    </DashboardLayout>
  `,
})

const ListPage = defineComponent({
  template: '<div data-testid="list-page">Liste</div>',
})

const DetailPage = defineComponent({
  template: '<div data-testid="detail-page">Détail</div>',
})

const RootApp = defineComponent({
  template: '<router-view />',
})

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/parent',
        component: LayoutHost,
        children: [
          {
            path: 'list',
            name: 'list',
            component: ListPage,
            meta: { keepAlive: true, preserveScrollOnQueryChange: true },
          },
          {
            path: 'detail',
            name: 'detail',
            component: DetailPage,
            // Calque la route face-profile : les onglets portent leur état
            // dans la query.
            meta: { preserveScrollOnQueryChange: true },
          },
          {
            // Liste paginée : la query porte ?page=, pas de l'état de vue —
            // pas d'opt-in, un changement de page doit repartir du haut.
            path: 'paginated',
            name: 'paginated',
            component: DetailPage,
          },
        ],
      },
    ],
  })
}

async function mountOnList(router: Router) {
  router.push('/parent/list')
  await router.isReady()
  const wrapper = mount(RootApp, {
    global: {
      plugins: [router],
      stubs: { Teleport: true },
    },
  })
  await flushPromises()
  const main = wrapper.find('[data-testid="dashboard-content"]')
    .element as HTMLElement
  return { wrapper, main }
}

describe('DashboardLayout — scroll reset/restore du <main> (bug F4)', () => {
  it('navigation liste → détail : le détail arrive à scrollTop = 0 (aujourd\'hui il hérite l\'offset de la liste)', async () => {
    const router = makeRouter()
    const { main } = await mountOnList(router)

    // La liste est scrollée
    main.scrollTop = 500
    expect(main.scrollTop).toBe(500) // garde : jsdom persiste bien la valeur

    // Ouverture du détail depuis la liste scrollée
    await router.push('/parent/detail')
    await flushPromises()
    await nextTick()

    // CONTRAT : route d'arrivée sans meta.keepAlive → scrollTop = 0.
    // BUG ACTUEL : le <main> persiste et garde 500 (offset hérité).
    expect(main.scrollTop).toBe(0)
  })

  it('retour vers une route meta.keepAlive : la position sauvegardée est restaurée (aujourd\'hui on garde le scroll du détail)', async () => {
    const router = makeRouter()
    const { main } = await mountOnList(router)

    // La liste est scrollée à 500
    main.scrollTop = 500

    // On ouvre le détail (le fix mettra le <main> à 0)…
    await router.push('/parent/detail')
    await flushPromises()
    await nextTick()

    // …puis l'utilisateur scrolle DANS le détail
    main.scrollTop = 42

    // Retour vers la liste (meta.keepAlive: true)
    await router.push('/parent/list')
    await flushPromises()
    await nextTick()

    // CONTRAT : restauration de la position sauvegardée pour /parent/list = 500.
    // BUG ACTUEL : rien n'est sauvegardé/restauré → le <main> reste à 42.
    expect(main.scrollTop).toBe(500)
  })

  it('reapplies the saved offset after enter when the browser clamped the early restoration', async () => {
    const router = makeRouter()
    const { wrapper, main } = await mountOnList(router)

    main.scrollTop = 500
    await router.push('/parent/detail')
    await flushPromises()
    main.scrollTop = 42

    await router.push('/parent/list')
    await flushPromises()
    await nextTick()
    expect(main.scrollTop).toBe(500)

    // Reproduce the browser's clamp while an out-in transition temporarily
    // has no scrollable destination content, then signal transition entry.
    main.scrollTop = 0
    await wrapper.find('[data-testid="after-enter-trigger"]').trigger('click')

    expect(main.scrollTop).toBe(500)
  })

  it('navigation query-only sur la même page (onglets Profil) : la position de lecture est conservée', async () => {
    const router = makeRouter()
    const { main } = await mountOnList(router)

    await router.push('/parent/detail')
    await flushPromises()
    await nextTick()

    // L'utilisateur a scrollé jusqu'aux onglets, très bas sur la mise en page
    // empilée du mobile.
    main.scrollTop = 640

    // Clic sur un onglet : la page synchronise son état dans l'URL
    // (?tab=…) — même route, même composant (l'outlet keye par route.path),
    // donc ce n'est PAS un changement de page.
    await router.push('/parent/detail?tab=physique')
    await flushPromises()
    await nextTick()

    // CONTRAT : la position de lecture ne bouge pas.
    // BUG : le watch sur route.fullPath remet le <main> à 0 et renvoie
    // l'utilisateur tout en haut, l'obligeant à re-scroller jusqu'aux onglets.
    expect(main.scrollTop).toBe(640)
  })

  it('navigation query-only sur une page keep-alive : la position est également conservée', async () => {
    const router = makeRouter()
    const { main } = await mountOnList(router)

    main.scrollTop = 300
    await router.push('/parent/list?filtre=actives')
    await flushPromises()
    await nextTick()

    expect(main.scrollTop).toBe(300)
  })

  it('sans opt-in (liste paginée) : un changement de query repart bien du haut', async () => {
    const router = makeRouter()
    const { main } = await mountOnList(router)

    await router.push('/parent/paginated')
    await flushPromises()
    await nextTick()

    // L'utilisateur est en bas de la liste, sur les boutons de pagination
    main.scrollTop = 800

    await router.push('/parent/paginated?page=2')
    await flushPromises()
    await nextTick()

    // Page suivante = nouveau contenu : on repart du haut de la liste.
    expect(main.scrollTop).toBe(0)
  })

  it('ignores a stale transition callback after a newer navigation wins', async () => {
    const router = makeRouter()
    const { main } = await mountOnList(router)

    main.scrollTop = 500
    await router.push('/parent/detail')
    await flushPromises()

    main.scrollTop = 42
    await router.push('/parent/list')
    await flushPromises()

    // The real outlet captures its destination before enter. Calling the
    // provider with an older destination must not alter the current page.
    main.scrollTop = 321
    capturedRestoreDashboardScroll?.({ fullPath: '/parent/detail', keepAlive: false })

    expect(main.scrollTop).toBe(321)
  })
})
