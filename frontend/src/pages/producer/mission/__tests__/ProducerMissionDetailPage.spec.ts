import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import ProducerMissionDetailPage from '../ProducerMissionDetailPage.vue'
import { missionApi } from '@/features/mission/services/missionApi'
import { useAuthStore } from '@/stores/auth'
import type { Mission, MissionSummary, MissionSummaryStep } from '@/features/mission/types'

// reka-ui (menu « Plus ») mesure son popper avec ResizeObserver, absent de jsdom.
globalThis.ResizeObserver ??= class {
  observe() {}
  unobserve() {}
  disconnect() {}
} as unknown as typeof ResizeObserver

const routeState = vi.hoisted(() => ({
  query: {} as Record<string, unknown>,
  replace: vi.fn().mockResolvedValue(undefined),
  push: vi.fn().mockResolvedValue(undefined),
  focusFirstToRate: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: 'mission-uuid-under-test' }, query: routeState.query }),
  useRouter: () => ({ push: routeState.push, replace: routeState.replace }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ error: vi.fn(), success: vi.fn(), warning: vi.fn(), info: vi.fn(), clear: vi.fn(), toast: {} }),
}))

vi.mock('@/features/candidature/components', () => ({
  ProducerCandidaturesSection: defineComponent({
    name: 'ProducerCandidaturesSectionStub',
    props: { missionId: { type: String, required: true } },
    setup(_props, { expose }) {
      expose({ openCandidaturePayment: vi.fn(), focusFirstToRate: routeState.focusFirstToRate })
      return () => h('div', { 'data-testid': 'candidatures-section-stub' })
    },
  }),
}))

vi.mock('@/features/mission/components/AttendanceValidationPanel.vue', () => ({
  default: defineComponent({
    name: 'AttendanceValidationPanelStub',
    props: { missionUuid: { type: String, required: true } },
    setup: (props) => () => h('div', { 'data-testid': 'attendance-panel-stub', 'data-mission': props.missionUuid }),
  }),
}))

vi.mock('@/components/ugc/UgcDeliverableReviewInbox.vue', () => ({
  default: defineComponent({
    name: 'UgcDeliverableReviewInboxStub',
    props: { missionId: { type: String, default: '' }, compact: { type: Boolean, default: false } },
    setup: (props) => () =>
      h('div', {
        'data-testid': 'deliverables-inbox-stub',
        'data-mission': props.missionId,
        'data-compact': String(props.compact),
      }),
  }),
}))

vi.mock('@/features/mission/composables/useMissionPayment', () => ({
  useMissionPayment: () => ({ startPolling: vi.fn(), stopPolling: vi.fn() }),
}))

vi.mock('@/features/mission/services/missionApi', () => ({
  missionApi: {
    getMission: vi.fn(),
    getPaymentStatus: vi.fn(),
    getCommissionStatus: vi.fn(),
    confirmSelection: vi.fn(),
    cancelSelection: vi.fn(),
  },
}))

vi.mock('@/lib/redirectToCheckout', () => ({ redirectToCheckout: vi.fn() }))
vi.mock('@/features/candidature/services/candidatureApi', () => ({
  candidatureApi: { getCandidaturePaymentStatus: vi.fn() },
}))

function makeSummary(step: MissionSummaryStep, overrides: Partial<MissionSummary> = {}): MissionSummary {
  return {
    step,
    candidatures: { pending: 0, accepted: 0, confirmed: 0, in_progress: 0, completed: 0, rejected: 0, cancelled: 0, expired: 0 },
    selection: { faces_wanted: 3, faces_selected: 3, faces_confirmed: 2, freed_places: 0 },
    payment: { status: null, expires_at: null, paid_at: null, montant_total_producteur: null },
    attendance: null,
    ugc_deliverables: null,
    completion_blocker: null,
    faces_to_rate: 0,
    can_edit: false,
    ...overrides,
  }
}

function makeMission(overrides: Partial<Mission> = {}, summary: MissionSummary = makeSummary('recruiting')): Mission {
  return {
    id: 'mission-uuid-under-test',
    titre: 'Spot TV jus de bissap',
    description: 'Brief',
    date_tournage: '2026-10-23T09:00:00+00:00',
    profil_recherche: 'Face polyvalente',
    budget: 110000,
    date_limite_candidature: '2026-10-19T12:00:00+00:00',
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    genre_voulu: 'tous',
    genre_voulu_label: 'Homme et Femme',
    lieu: 'Cotonou, Haie Vive',
    duree: '1 jour',
    status: 'published',
    status_label: 'Publiée',
    display_status_label: 'Publiée',
    type_compensation: null,
    nom_produit: null,
    montant_remuneration: null,
    commission_ugc: null,
    commission_paid_at: null,
    is_accepting_candidatures: true,
    has_paid_payment: false,
    created_at: '2026-10-01T00:00:00Z',
    updated_at: '2026-10-01T00:00:00Z',
    summary,
    ...overrides,
  } as Mission
}

let wrapper: VueWrapper | null = null

async function mountPage(mission: Mission) {
  vi.mocked(missionApi.getMission).mockResolvedValue({ data: mission, message: 'ok' })
  wrapper = mount(ProducerMissionDetailPage, { attachTo: document.body })
  await flushPromises()
  await flushPromises()
  return wrapper
}

function primaryAction(w: VueWrapper) {
  return w.find('[data-testid="mission-primary-action"], [data-testid="mission-payment-resume-btn"]')
}

beforeEach(() => {
  setActivePinia(createPinia())
  useAuthStore().user = { email_verified: true } as never
  routeState.query = {}
  routeState.replace.mockClear()
  routeState.push.mockClear()
  vi.spyOn(console, 'error').mockImplementation(() => {})
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  document.body.innerHTML = ''
  vi.clearAllMocks()
  vi.restoreAllMocks()
})

describe('ProducerMissionDetailPage — en-tête et étapes', () => {
  it('affiche l’eyebrow, le titre, la pastille de statut véridique, le tournage, le lieu et le budget par Face', async () => {
    const w = await mountPage(makeMission({ display_status_label: 'Payée, en préparation' }))

    expect(w.get('[data-testid="mission-eyebrow"]').text()).toContain('Mission standard · 3 Faces')
    expect(w.get('h1').text()).toBe('Spot TV jus de bissap')
    expect(w.get('[data-testid="mission-status-pill"]').text()).toBe('Payée, en préparation')
    expect(w.text()).toContain('Cotonou, Haie Vive')
    expect(w.text()).toContain('par Face')
  })

  it('mission UGC : eyebrow UGC, étapes Commission / Candidatures / Tunnel / Terminée', async () => {
    const w = await mountPage(
      makeMission({ type_mission: 'ugc', type_compensation: 'product', nom_produit: 'Sneakers' } as Partial<Mission>, makeSummary('recruiting')),
    )

    expect(w.get('[data-testid="mission-eyebrow"]').text()).toContain('Mission UGC')
    const labels = w.get('[data-testid="mission-step-bar"]').findAll('li').map((li) => li.text())
    expect(labels).toEqual(['Commission', '2Candidatures', '3Tunnel', '4Terminée'])
  })

  it.each([
    ['recruiting', 0],
    ['payment', 1],
    ['confirmations', 2],
    ['ready', 3],
    ['attendance', 3],
    ['completed', 4],
  ] as const)('mission standard : l’étape %s marque l’index %i comme courant', async (step, index) => {
    const status = step === 'payment' ? 'pending_payment' : step === 'recruiting' ? 'published' : step === 'completed' ? 'completed' : 'closed'
    const w = await mountPage(makeMission({ status }, makeSummary(step)))

    const items = w.get('[data-testid="mission-step-bar"]').findAll('li')
    expect(items).toHaveLength(5)
    items.forEach((li, i) => {
      expect(li.attributes('data-state')).toBe(i < index ? 'done' : i === index ? 'now' : 'todo')
    })
    expect(items[index]!.attributes('aria-current')).toBe('step')
  })

  it('brouillon : pas de barre d’étapes, mais le bloc « Et ensuite ? » existe', async () => {
    const w = await mountPage(makeMission({ status: 'draft' }, makeSummary('draft')))

    expect(w.find('[data-testid="mission-step-bar"]').exists()).toBe(false)
    expect(w.get('[data-testid="mission-next-headline"]').text()).toBe('Brouillon')
  })
})

describe('ProducerMissionDetailPage — action principale par étape', () => {
  it('recrutement avec candidatures : « Choisir mes Faces » ouvre l’onglet Candidatures', async () => {
    const summary = makeSummary('recruiting', {
      candidatures: { ...makeSummary('recruiting').candidatures, pending: 4 },
    })
    const w = await mountPage(makeMission({}, summary))

    const button = primaryAction(w)
    expect(button.text()).toContain('Choisir mes Faces')
    await button.trigger('click')

    expect(routeState.replace).toHaveBeenCalledWith({ query: { tab: 'candidatures' } })
  })

  it('recrutement sans candidature : aucune action principale', async () => {
    const w = await mountPage(makeMission({}, makeSummary('recruiting')))

    expect(primaryAction(w).exists()).toBe(false)
  })

  it('paiement avec transaction vivante : « Reprendre le paiement » + « Modifier la sélection » en secondaire', async () => {
    vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
      data: { has_payment: true, is_trackable: true, status: 'pending', mission_status: 'pending_payment' },
    })
    const w = await mountPage(makeMission({ status: 'pending_payment' }, makeSummary('payment')))

    expect(w.get('[data-testid="mission-payment-resume-btn"]').text()).toContain('Reprendre le paiement')
    expect(w.find('[data-testid="mission-payment-cancel-selection-btn"]').exists()).toBe(true)
  })

  it('confirmations avec place libérée : « Remplacer la place libérée » ; sans place libérée : aucun bouton', async () => {
    const freed = makeSummary('confirmations', {
      selection: { faces_wanted: 3, faces_selected: 3, faces_confirmed: 2, freed_places: 1 },
    })
    const w = await mountPage(makeMission({ status: 'closed', can_replace_freed_place: true, freed_places_count: 1 }, freed))
    expect(primaryAction(w).text()).toContain('Remplacer la place libérée')
    w.unmount()
    wrapper = null

    const w2 = await mountPage(makeMission({ status: 'closed' }, makeSummary('confirmations')))
    expect(primaryAction(w2).exists()).toBe(false)
    // « Et ensuite ? » dit qui doit agir.
    expect(w2.get('[data-testid="mission-next-who"]').text()).toBe('Les Faces doivent agir')
  })

  it('prêt : aucune action principale', async () => {
    const w = await mountPage(makeMission({ status: 'closed' }, makeSummary('ready')))

    expect(primaryAction(w).exists()).toBe(false)
  })

  it('jour du tournage : « Valider les présences » bascule sur l’onglet Présences', async () => {
    const summary = makeSummary('attendance', {
      attendance: { opens_at: '2026-10-23T00:00:00+00:00', can_mark: true, pending: 3, present: 0, absent: 0, disputed: 0 },
    })
    const w = await mountPage(makeMission({ status: 'closed', has_paid_payment: true }, summary))

    const button = primaryAction(w)
    expect(button.text()).toContain('Valider les présences')
    await button.trigger('click')

    expect(routeState.replace).toHaveBeenCalledWith({ query: { tab: 'presences' } })
  })

  it('terminée : aucune action principale', async () => {
    const w = await mountPage(makeMission({ status: 'completed' }, makeSummary('completed')))

    expect(primaryAction(w).exists()).toBe(false)
  })

  it('UGC commission : « Payer la commission » ouvre le tunnel de paiement', async () => {
    const w = await mountPage(
      makeMission(
        { status: 'pending_payment', type_mission: 'ugc', type_compensation: 'product', commission_ugc: 2500 } as Partial<Mission>,
        makeSummary('commission'),
      ),
    )

    const button = primaryAction(w)
    expect(button.text()).toContain('Payer la commission')
    await button.trigger('click')
    await flushPromises()

    expect(document.body.textContent).toContain('Paiement de la commission')
  })

  it('mission terminée avec des Faces à noter : « Noter mes Faces » met le premier « Noter » en avant, facultatif', async () => {
    routeState.focusFirstToRate.mockClear()
    const w = await mountPage(
      makeMission({ status: 'completed', has_paid_payment: true }, makeSummary('completed', { faces_to_rate: 2 })),
    )

    const button = primaryAction(w)
    expect(button.text()).toContain('Noter mes Faces')
    expect(button.text()).toContain('facultatif')
    expect(w.text()).toContain('facultatif')

    await button.trigger('click')
    await flushPromises()
    expect(routeState.focusFirstToRate).toHaveBeenCalledTimes(1)
  })

  it('mission terminée dont toutes les Faces sont notées : plus d\'action principale', async () => {
    const w = await mountPage(
      makeMission({ status: 'completed', has_paid_payment: true }, makeSummary('completed', { faces_to_rate: 0 })),
    )

    expect(primaryAction(w).exists()).toBe(false)
  })

  it('UGC publiée depuis le formulaire (?pay={id}) : ouvre le tunnel de commission et consomme le paramètre', async () => {
    routeState.query = { pay: 'mission-uuid-under-test' }
    await mountPage(
      makeMission(
        { status: 'pending_payment', type_mission: 'ugc', type_compensation: 'product', commission_ugc: 2500 } as Partial<Mission>,
        makeSummary('commission'),
      ),
    )

    expect(document.body.textContent).toContain('Paiement de la commission')
    expect(routeState.replace).toHaveBeenCalledWith({ query: {} })
  })

  it('?pay sur une mission déjà payée : ne rouvre pas le tunnel mais consomme le paramètre', async () => {
    routeState.query = { pay: 'mission-uuid-under-test' }
    await mountPage(
      makeMission(
        { status: 'published', type_mission: 'ugc', type_compensation: 'product', commission_ugc: 2500 } as Partial<Mission>,
        makeSummary('recruiting'),
      ),
    )

    expect(document.body.textContent).not.toContain('Paiement de la commission')
    expect(routeState.replace).toHaveBeenCalledWith({ query: {} })
  })

  it('UGC tunnel : « Voir les livrables » seulement s’il y a des livrables à valider', async () => {
    const ugc = { status: 'closed', type_mission: 'ugc', type_compensation: 'product' } as Partial<Mission>
    const w = await mountPage(
      makeMission(ugc, makeSummary('tunnel', { ugc_deliverables: { to_review: 2, validated: 0, retouche_requested: 0 } })),
    )
    const button = primaryAction(w)
    expect(button.text()).toContain('Voir les livrables')
    await button.trigger('click')
    expect(routeState.replace).toHaveBeenCalledWith({ query: { tab: 'livrables' } })
    w.unmount()
    wrapper = null

    const w2 = await mountPage(
      makeMission(ugc, makeSummary('tunnel', { ugc_deliverables: { to_review: 0, validated: 1, retouche_requested: 0 } })),
    )
    expect(primaryAction(w2).exists()).toBe(false)
  })

  it('barre d’action mobile : titre du bloc « Et ensuite ? » et même action principale', async () => {
    const summary = makeSummary('attendance', {
      attendance: { opens_at: null, can_mark: true, pending: 2, present: 0, absent: 0, disputed: 0 },
    })
    const w = await mountPage(makeMission({ status: 'closed' }, summary))

    const bar = w.get('[data-testid="mission-action-bar"]')
    expect(bar.text()).toContain('Pointez les présences')
    expect(bar.get('[data-testid="mission-action-bar-primary"]').text()).toContain('Valider les présences')
  })
})

describe('ProducerMissionDetailPage — onglets', () => {
  it('mission standard : Candidatures + Présences, pas de Livrables ; Candidatures actif par défaut', async () => {
    const w = await mountPage(makeMission())

    expect(w.find('[data-testid="mission-tab-candidatures"]').attributes('aria-selected')).toBe('true')
    expect(w.find('[data-testid="mission-tab-presences"]').exists()).toBe(true)
    expect(w.find('[data-testid="mission-tab-livrables"]').exists()).toBe(false)
    expect(w.find('[data-testid="candidatures-section-stub"]').exists()).toBe(true)
  })

  it('mission UGC : Candidatures + Livrables, pas de Présences', async () => {
    const w = await mountPage(makeMission({ type_mission: 'ugc', type_compensation: 'product' } as Partial<Mission>))

    expect(w.find('[data-testid="mission-tab-livrables"]').exists()).toBe(true)
    expect(w.find('[data-testid="mission-tab-presences"]').exists()).toBe(false)
  })

  it('cliquer un onglet écrit ?tab= dans l’URL en conservant les autres paramètres', async () => {
    routeState.query = { foo: 'bar' }
    const w = await mountPage(makeMission())

    await w.get('[data-testid="mission-tab-presences"]').trigger('click')

    expect(routeState.replace).toHaveBeenCalledWith({ query: { foo: 'bar', tab: 'presences' } })
  })

  it('?tab=presences ouvre l’onglet Présences : message avant le paiement (pas de formulaire)', async () => {
    routeState.query = { tab: 'presences' }
    const w = await mountPage(makeMission())

    expect(w.get('[data-testid="mission-tab-presences"]').attributes('aria-selected')).toBe('true')
    expect(w.find('[data-testid="mission-presences-empty"]').exists()).toBe(true)
    expect(w.find('[data-testid="attendance-panel-stub"]').exists()).toBe(false)
  })

  it('?tab=presences avec un paiement versé : le formulaire de présences est monté pour cette mission', async () => {
    routeState.query = { tab: 'presences' }
    const summary = makeSummary('attendance', {
      attendance: { opens_at: null, can_mark: true, pending: 1, present: 0, absent: 0, disputed: 0 },
    })
    const w = await mountPage(makeMission({ status: 'closed', has_paid_payment: true }, summary))

    expect(w.get('[data-testid="attendance-panel-stub"]').attributes('data-mission')).toBe('mission-uuid-under-test')
  })

  it('?tab=livrables sur une mission UGC : l’inbox est restreinte à la mission et compacte', async () => {
    routeState.query = { tab: 'livrables' }
    const w = await mountPage(
      makeMission({ status: 'closed', type_mission: 'ugc', type_compensation: 'product' } as Partial<Mission>, makeSummary('tunnel', {
        ugc_deliverables: { to_review: 1, validated: 0, retouche_requested: 0 },
      })),
    )

    const inbox = w.get('[data-testid="deliverables-inbox-stub"]')
    expect(inbox.attributes('data-mission')).toBe('mission-uuid-under-test')
    expect(inbox.attributes('data-compact')).toBe('true')
    expect(w.get('[data-testid="mission-tab-livrables"]').text()).toContain('1')
  })

  it('un onglet inconnu ou indisponible retombe sur Candidatures', async () => {
    routeState.query = { tab: 'livrables' } // mission standard : pas de livrables
    const w = await mountPage(makeMission())

    expect(w.get('[data-testid="mission-tab-candidatures"]').attributes('aria-selected')).toBe('true')
  })

  it('les panneaux visités restent montés quand on change d’onglet (la sélection de Faces n’est pas perdue)', async () => {
    const w = await mountPage(makeMission())
    expect(w.find('[data-testid="candidatures-section-stub"]').exists()).toBe(true)

    routeState.query = { tab: 'presences' }
    await w.get('[data-testid="mission-tab-presences"]').trigger('click')
    // La page lit route.query à chaque rendu : on force le rendu après le changement d’URL simulé.
    await w.vm.$forceUpdate()
    await flushPromises()

    expect(w.find('[data-testid="candidatures-section-stub"]').exists()).toBe(true)
  })
})

describe('ProducerMissionDetailPage — menu « Plus »', () => {
  async function openMenu(w: VueWrapper) {
    await w.get('[data-testid="mission-more-menu-trigger"]').trigger('keydown', { key: 'Enter' })
    await flushPromises()
  }

  function menuLabels(): string[] {
    return Array.from(document.body.querySelectorAll('[role="menuitem"]')).map((el) => el.textContent!.trim())
  }

  it('mission publiée modifiable : Modifier, Clôturer, Supprimer', async () => {
    const w = await mountPage(makeMission({}, makeSummary('recruiting', { can_edit: true })))
    await openMenu(w)

    const labels = menuLabels()
    expect(labels).toContain('Modifier')
    expect(labels).toContain('Clôturer')
    expect(labels).toContain('Supprimer')
    expect(labels).not.toContain('Rouvrir')
    expect(labels).not.toContain('Terminer')
  })

  it('mission clôturée non payée : Rouvrir', async () => {
    const w = await mountPage(makeMission({ status: 'closed' }, makeSummary('recruiting')))
    await openMenu(w)

    expect(menuLabels()).toContain('Rouvrir')
  })

  it('Terminer disponible quand le serveur l’autorise', async () => {
    const w = await mountPage(makeMission({ status: 'closed', can_complete: true, has_paid_payment: true }, makeSummary('ready')))
    await openMenu(w)

    expect(menuLabels()).toContain('Terminer')
  })

  it('Terminer bloqué : entrée désactivée avec la raison du serveur', async () => {
    const blocker = 'Toutes les faces sélectionnées doivent confirmer leur participation avant de terminer la mission'
    const w = await mountPage(
      makeMission({ status: 'closed', can_complete: false, has_paid_payment: true }, makeSummary('confirmations', { completion_blocker: blocker })),
    )
    await openMenu(w)

    const blocked = document.body.querySelector('[data-testid="mission-menu-complete-blocked"]')
    expect(blocked).not.toBeNull()
    expect(blocked!.getAttribute('data-disabled')).not.toBeNull()
    expect(blocked!.textContent).toContain(blocker)
  })

  it('« Modifier » mène à la page d’édition', async () => {
    const w = await mountPage(makeMission({}, makeSummary('recruiting', { can_edit: true })))
    await openMenu(w)

    ;(document.body.querySelector('[data-testid="mission-menu-edit"]') as HTMLElement).click()
    await flushPromises()

    expect(routeState.push).toHaveBeenCalledWith({ name: 'edit-mission', params: { id: 'mission-uuid-under-test' } })
  })
})

describe('ProducerMissionDetailPage — « Et ensuite ? » et paiement', () => {
  it('affiche le bloc de la phase, le compte à rebours de paiement et la carte Paiement', async () => {
    vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
      data: { has_payment: true, is_trackable: true, status: 'pending', mission_status: 'pending_payment' },
    })
    const expiresAt = new Date(Date.now() + 14 * 3_600_000 + 30 * 60_000).toISOString()
    const summary = makeSummary('payment', {
      payment: { status: 'pending', expires_at: expiresAt, paid_at: null, montant_total_producteur: 330000 },
    })
    const w = await mountPage(makeMission({ status: 'pending_payment' }, summary))

    expect(w.get('[data-testid="mission-next-headline"]').text()).toBe('Payez votre sélection')
    expect(w.get('[data-testid="mission-next-countdown"]').text()).toMatch(/Reste 14 h/)
    expect(w.get('[data-testid="mission-payment-status"]').text()).toBe('En attente de paiement')
  })
})
