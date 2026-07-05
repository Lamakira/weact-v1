import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import type {
  AdminSubscriptionListItem,
  AdminSubscriptionStats,
} from '@/features/admin/services/adminFaceSubscriptionsApi'
import { formatAmount } from '@/features/admin/utils/subscriptionDisplay'
import AdminSubscriptionsListPage from '../AdminSubscriptionsListPage.vue'

const mockFetchSubscriptions = vi.fn()
const mockFetchStats = vi.fn()
const mockExtend = vi.fn()
const mockCancel = vi.fn()
const mockToastSuccess = vi.fn()
const mockToastError = vi.fn()

let subscriptionsRef = ref<AdminSubscriptionListItem[]>([])
let paginationRef = ref<{
  current_page: number
  last_page: number
  per_page: number
  total: number
} | null>(null)
let statsRef = ref<AdminSubscriptionStats | null>(null)
let isLoadingRef = ref(false)
let isStatsLoadingRef = ref(false)
let errorRef = ref<string | null>(null)
let statsErrorRef = ref<string | null>(null)

vi.mock('@/features/admin/composables/useAdminFaceSubscriptions', () => ({
  useAdminSubscriptionsList: () => ({
    subscriptions: subscriptionsRef,
    pagination: paginationRef,
    stats: statsRef,
    isLoading: isLoadingRef,
    isStatsLoading: isStatsLoadingRef,
    error: errorRef,
    statsError: statsErrorRef,
    fetchSubscriptions: mockFetchSubscriptions,
    fetchStats: mockFetchStats,
  }),
  useAdminFaceSubscriptions: () => ({
    extend: mockExtend,
    cancel: mockCancel,
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: mockToastSuccess,
    error: mockToastError,
    warning: vi.fn(),
    info: vi.fn(),
  }),
}))

function makeStats(overrides: Partial<AdminSubscriptionStats> = {}): AdminSubscriptionStats {
  return {
    active_by_plan: { starter: 2, pro: 5, elite: 1, total: 8 },
    revenue: { current_month: 150000, total: 1200000, currency: 'XOF' },
    expiring_within_30_days: 3,
    pending_payment_count: 4,
    failed_count: 2,
    ...overrides,
  }
}

function makeSubscription(
  overrides: Partial<AdminSubscriptionListItem> = {},
): AdminSubscriptionListItem {
  return {
    id: 'sub-uuid-1',
    plan: 'pro',
    plan_label: 'Pro',
    status: 'active',
    status_label: 'Active',
    starts_at: '2026-01-01T00:00:00+00:00',
    expires_at: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
    cancelled_at: null,
    paid_amount: 50000,
    paid_at: '2026-01-01T00:00:00+00:00',
    currency: 'XOF',
    created_at: '2026-01-01T00:00:00+00:00',
    updated_at: '2026-01-01T00:00:00+00:00',
    face: {
      id: 'face-uuid-1',
      nom: 'Doe',
      prenom: 'Jane',
      username: 'jane-doe',
    },
    ...overrides,
  }
}

async function mountPage(): Promise<VueWrapper> {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/admin/subscriptions',
        name: 'admin-subscriptions-list',
        component: AdminSubscriptionsListPage,
      },
      {
        path: '/admin/faces/:id',
        name: 'admin-face-detail',
        component: { template: '<div>Detail</div>' },
      },
    ],
  })

  await router.push('/admin/subscriptions')
  await router.isReady()

  const wrapper = mount(AdminSubscriptionsListPage, {
    global: {
      plugins: [router],
    },
    attachTo: document.body,
  })

  await flushPromises()
  return wrapper
}

describe('AdminSubscriptionsListPage', () => {
  beforeEach(() => {
    subscriptionsRef = ref<AdminSubscriptionListItem[]>([])
    paginationRef = ref(null)
    statsRef = ref<AdminSubscriptionStats | null>(null)
    isLoadingRef = ref(false)
    isStatsLoadingRef = ref(false)
    errorRef = ref<string | null>(null)
    statsErrorRef = ref<string | null>(null)
    vi.clearAllMocks()
    document.body.innerHTML = ''
  })

  it('charge la liste et les stats au montage', async () => {
    await mountPage()

    expect(mockFetchSubscriptions).toHaveBeenCalledWith({ page: 1 })
    expect(mockFetchStats).toHaveBeenCalledTimes(1)
  })

  it('affiche les cartes KPIs à partir des stats', async () => {
    // Valeurs distinctes non-substrings l'une de l'autre : intervertir les
    // bindings current_month ↔ total doit faire échouer le test.
    statsRef.value = makeStats({
      revenue: { current_month: 175000, total: 9430000, currency: 'XOF' },
    })
    const wrapper = await mountPage()

    const active = wrapper.find('[data-testid="kpi-active"]')
    expect(active.text()).toContain('8')
    expect(active.text()).toContain('Starter 2')
    expect(active.text()).toContain('Pro 5')
    expect(active.text()).toContain('Élite 1')

    // Montants formatés via le même formatter que la page (séparateurs
    // fr-FR = espaces fines insécables) — comparaison sur la chaîne complète.
    const expectedMonth = formatAmount(175000, 'XOF')
    const expectedTotal = formatAmount(9430000, 'XOF')
    expect(expectedTotal).not.toBe(expectedMonth)

    const monthText = wrapper.find('[data-testid="kpi-revenue-month"]').text()
    const totalText = wrapper.find('[data-testid="kpi-revenue-total"]').text()
    expect(monthText).toContain(expectedMonth)
    expect(monthText).not.toContain(expectedTotal)
    expect(totalText).toContain(expectedTotal)
    expect(totalText).not.toContain(expectedMonth)

    expect(wrapper.find('[data-testid="kpi-expiring"]').text()).toContain('3')
    expect(wrapper.find('[data-testid="kpi-pending"]').text()).toContain('4')
    expect(wrapper.find('[data-testid="kpi-failed"]').text()).toContain('2')
  })

  it('transmet les filtres palier et statut dans les params', async () => {
    const wrapper = await mountPage()
    mockFetchSubscriptions.mockClear()

    await wrapper.find('[data-testid="plan-filter"]').setValue('pro')
    expect(mockFetchSubscriptions).toHaveBeenCalledWith({ page: 1, plan: 'pro' })

    mockFetchSubscriptions.mockClear()
    await wrapper.find('[data-testid="status-filter"]').setValue('active')
    expect(mockFetchSubscriptions).toHaveBeenCalledWith({
      page: 1,
      plan: 'pro',
      status: 'active',
    })
  })

  it('debounce la recherche à 300 ms puis envoie le param search', async () => {
    const wrapper = await mountPage()
    mockFetchSubscriptions.mockClear()

    vi.useFakeTimers()
    try {
      await wrapper.find('[data-testid="search-input"]').setValue('jane')

      vi.advanceTimersByTime(299)
      expect(mockFetchSubscriptions).not.toHaveBeenCalled()

      vi.advanceTimersByTime(1)
      expect(mockFetchSubscriptions).toHaveBeenCalledWith({ page: 1, search: 'jane' })
    } finally {
      vi.useRealTimers()
    }
  })

  it('désamorce le timer de recherche armé quand un filtre force un fetch immédiat (pas de rappel fantôme page 1)', async () => {
    const wrapper = await mountPage()
    mockFetchSubscriptions.mockClear()

    vi.useFakeTimers()
    try {
      // Arme le debounce de recherche sans le laisser expirer.
      await wrapper.find('[data-testid="search-input"]').setValue('jane')
      vi.advanceTimersByTime(100)
      expect(mockFetchSubscriptions).not.toHaveBeenCalled()

      // Le changement de filtre déclenche un fetch immédiat…
      await wrapper.find('[data-testid="plan-filter"]').setValue('pro')
      expect(mockFetchSubscriptions).toHaveBeenCalledTimes(1)
      expect(mockFetchSubscriptions).toHaveBeenCalledWith({ page: 1, search: 'jane', plan: 'pro' })

      // …et le timer résiduel ne doit PAS refire loadSubscriptions(1) après coup.
      vi.advanceTimersByTime(400)
      expect(mockFetchSubscriptions).toHaveBeenCalledTimes(1)
    } finally {
      vi.useRealTimers()
    }
  })

  it('trim le terme de recherche et omet le param search si vide après trim', async () => {
    const wrapper = await mountPage()
    mockFetchSubscriptions.mockClear()

    vi.useFakeTimers()
    try {
      await wrapper.find('[data-testid="search-input"]').setValue('  jane  ')
      vi.advanceTimersByTime(300)
      expect(mockFetchSubscriptions).toHaveBeenCalledWith({ page: 1, search: 'jane' })

      mockFetchSubscriptions.mockClear()
      await wrapper.find('[data-testid="search-input"]').setValue('   ')
      vi.advanceTimersByTime(300)
      expect(mockFetchSubscriptions).toHaveBeenCalledWith({ page: 1 })
    } finally {
      vi.useRealTimers()
    }
  })

  it('affiche une ligne par abonnement avec lien vers la fiche Face', async () => {
    subscriptionsRef.value = [makeSubscription()]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 15, total: 1 }

    const wrapper = await mountPage()

    const rows = wrapper.findAll('[data-testid="subscription-row"]')
    expect(rows).toHaveLength(1)

    const link = wrapper.find('[data-testid="face-link"]')
    expect(link.text()).toContain('Jane Doe')
    expect(link.text()).toContain('@jane-doe')
    expect(link.attributes('href')).toBe('/admin/faces/face-uuid-1')
    expect(wrapper.find('[data-testid="status-badge"]').text()).toBe('Active')
  })

  it('affiche l\'état vide sans abonnement', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('[data-testid="empty-state"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="subscriptions-table"]').exists()).toBe(false)
  })

  it('affiche l\'état de chargement', async () => {
    isLoadingRef.value = true
    const wrapper = await mountPage()
    expect(wrapper.find('[data-testid="loading-state"]').exists()).toBe(true)
  })

  it('modale Prolonger : succès → payload correct, toast, liste et stats rafraîchies', async () => {
    mockExtend.mockResolvedValue({ success: true, message: 'Abonnement étendu' })
    subscriptionsRef.value = [makeSubscription({ id: 'sub-active' })]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 15, total: 1 }

    const wrapper = await mountPage()
    mockFetchSubscriptions.mockClear()
    mockFetchStats.mockClear()

    await wrapper.find('[data-testid="extend-button"]').trigger('click')
    await flushPromises()

    const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="extend-notes"]')!
    const days = document.querySelector<HTMLInputElement>(
      '[data-testid="extend-additional-days"]',
    )!
    notes.value = 'Prolongation geste commercial'
    notes.dispatchEvent(new Event('input'))
    days.value = '30'
    days.dispatchEvent(new Event('input'))
    await flushPromises()

    document.querySelector<HTMLButtonElement>('[data-testid="extend-submit"]')!.click()
    await flushPromises()

    expect(mockExtend).toHaveBeenCalledWith('sub-active', {
      notes: 'Prolongation geste commercial',
      additional_days: 30,
    })
    expect(mockToastSuccess).toHaveBeenCalledWith('Abonnement étendu')
    expect(mockFetchSubscriptions).toHaveBeenCalledTimes(1)
    expect(mockFetchStats).toHaveBeenCalledTimes(1)
    expect(document.querySelector('[data-testid="subscriptions-extend-modal"]')).toBeNull()
  })

  it('modale Prolonger : conflit 409 → message affiché, modale ouverte', async () => {
    mockExtend.mockResolvedValue({
      success: false,
      message: 'Cet abonnement ne peut pas être prolongé.',
      code: 'SUBSCRIPTION_NOT_EXTENDABLE',
    })
    subscriptionsRef.value = [makeSubscription({ id: 'sub-active' })]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 15, total: 1 }

    const wrapper = await mountPage()

    await wrapper.find('[data-testid="extend-button"]').trigger('click')
    await flushPromises()

    const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="extend-notes"]')!
    const days = document.querySelector<HTMLInputElement>(
      '[data-testid="extend-additional-days"]',
    )!
    notes.value = 'Prolongation impossible'
    notes.dispatchEvent(new Event('input'))
    days.value = '30'
    days.dispatchEvent(new Event('input'))
    await flushPromises()

    document.querySelector<HTMLButtonElement>('[data-testid="extend-submit"]')!.click()
    await flushPromises()

    const errorEl = document.querySelector('[data-testid="subscriptions-extend-error"]')
    expect(errorEl).not.toBeNull()
    expect(errorEl!.textContent).toContain('ne peut pas être prolongé')
    expect(document.querySelector('[data-testid="subscriptions-extend-modal"]')).not.toBeNull()
    expect(mockToastSuccess).not.toHaveBeenCalled()
  })

  it('modale Annuler : succès → payload correct, toast, liste et stats rafraîchies', async () => {
    mockCancel.mockResolvedValue({ success: true, message: 'Abonnement annulé' })
    subscriptionsRef.value = [makeSubscription({ id: 'sub-active' })]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 15, total: 1 }

    const wrapper = await mountPage()
    mockFetchSubscriptions.mockClear()
    mockFetchStats.mockClear()

    await wrapper.find('[data-testid="cancel-button"]').trigger('click')
    await flushPromises()

    const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="cancel-notes"]')!
    notes.value = 'Annulation à la demande du client.'
    notes.dispatchEvent(new Event('input'))
    await flushPromises()

    document.querySelector<HTMLButtonElement>('[data-testid="cancel-submit"]')!.click()
    await flushPromises()

    expect(mockCancel).toHaveBeenCalledWith('sub-active', {
      notes: 'Annulation à la demande du client.',
    })
    expect(mockToastSuccess).toHaveBeenCalledWith('Abonnement annulé')
    expect(mockFetchSubscriptions).toHaveBeenCalledTimes(1)
    expect(mockFetchStats).toHaveBeenCalledTimes(1)
    expect(document.querySelector('[data-testid="subscriptions-cancel-modal"]')).toBeNull()
  })

  it('modale Annuler : conflit 409 → message affiché, modale ouverte', async () => {
    mockCancel.mockResolvedValue({
      success: false,
      message: 'Cet abonnement ne peut pas être annulé.',
      code: 'SUBSCRIPTION_NOT_CANCELLABLE',
    })
    subscriptionsRef.value = [makeSubscription({ id: 'sub-active' })]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 15, total: 1 }

    const wrapper = await mountPage()

    await wrapper.find('[data-testid="cancel-button"]').trigger('click')
    await flushPromises()

    const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="cancel-notes"]')!
    notes.value = 'Annulation impossible'
    notes.dispatchEvent(new Event('input'))
    await flushPromises()

    document.querySelector<HTMLButtonElement>('[data-testid="cancel-submit"]')!.click()
    await flushPromises()

    const errorEl = document.querySelector('[data-testid="subscriptions-cancel-error"]')
    expect(errorEl).not.toBeNull()
    expect(errorEl!.textContent).toContain('ne peut pas être annulé')
    expect(document.querySelector('[data-testid="subscriptions-cancel-modal"]')).not.toBeNull()
  })

  it('masque Prolonger sur une ligne non-active et Annuler sur une ligne expirée', async () => {
    subscriptionsRef.value = [
      makeSubscription({
        id: 'sub-expired',
        status: 'expired',
        status_label: 'Expirée',
        expires_at: '2025-01-01T00:00:00+00:00',
      }),
      makeSubscription({
        id: 'sub-pending',
        status: 'pending_payment',
        status_label: 'En attente de paiement',
        expires_at: null,
      }),
    ]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 15, total: 2 }

    const wrapper = await mountPage()

    // expired : ni Prolonger ni Annuler ; pending : Annuler seulement
    expect(wrapper.findAll('[data-testid="extend-button"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-testid="cancel-button"]')).toHaveLength(1)
  })

  it('clamp de page : une annulation qui vide la page courante refetch la dernière page réelle', async () => {
    mockCancel.mockResolvedValue({ success: true, message: 'Abonnement annulé' })
    // État initial : page 2 sur 2, une seule ligne (la dernière de la page).
    subscriptionsRef.value = [makeSubscription({ id: 'sub-last-active' })]
    paginationRef.value = { current_page: 2, last_page: 2, per_page: 15, total: 16 }

    const wrapper = await mountPage()
    mockFetchSubscriptions.mockClear()
    mockFetchStats.mockClear()

    // 1er refetch post-mutation (page 2) : la page est désormais vide et le
    // backend clampe last_page à 1 → la page doit relancer un fetch en page 1.
    mockFetchSubscriptions
      .mockImplementationOnce(async () => {
        subscriptionsRef.value = []
        paginationRef.value = { current_page: 2, last_page: 1, per_page: 15, total: 15 }
      })
      .mockImplementationOnce(async () => {
        subscriptionsRef.value = [makeSubscription({ id: 'sub-page-1' })]
        paginationRef.value = { current_page: 1, last_page: 1, per_page: 15, total: 15 }
      })

    await wrapper.find('[data-testid="cancel-button"]').trigger('click')
    await flushPromises()

    const notes = document.querySelector<HTMLTextAreaElement>('[data-testid="cancel-notes"]')!
    notes.value = 'Annulation de la dernière ligne de la page.'
    notes.dispatchEvent(new Event('input'))
    await flushPromises()

    document.querySelector<HTMLButtonElement>('[data-testid="cancel-submit"]')!.click()
    await flushPromises()

    expect(mockCancel).toHaveBeenCalledWith('sub-last-active', {
      notes: 'Annulation de la dernière ligne de la page.',
    })
    // Refetch de la page courante (2) PUIS clamp sur la dernière page réelle (1).
    expect(mockFetchSubscriptions).toHaveBeenCalledTimes(2)
    expect(mockFetchSubscriptions).toHaveBeenNthCalledWith(1, { page: 2 })
    expect(mockFetchSubscriptions).toHaveBeenNthCalledWith(2, { page: 1 })
    expect(mockFetchStats).toHaveBeenCalledTimes(1)
  })
})
