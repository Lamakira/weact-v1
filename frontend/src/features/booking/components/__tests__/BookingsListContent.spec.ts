import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import type { Booking } from '../../types'

const userRef = vi.hoisted(() => ({ value: { id: 7, userable_type: 'Producer' } as { id: number; userable_type: string } }))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: userRef.value }),
}))

const getBookings = vi.hoisted(() => vi.fn())
vi.mock('../../services/bookingApi', () => ({
  bookingApi: { getBookings },
}))

import BookingsListContent from '../BookingsListContent.vue'

function makeBooking(id: string, overrides: Partial<Booking> = {}): Booking {
  return {
    id,
    realtime_channel_key: 1,
    face_id: 2,
    producer_id: 3,
    status: 'pending',
    status_label: 'En attente',
    date_debut: '2026-04-10T08:00:00Z',
    date_fin: '2026-04-11T12:00:00Z',
    duree_heures: 4,
    type_contenu: 'Publicité',
    lieu: null,
    message: null,
    tarif_base: 50000,
    montant_total_producteur: 55000,
    montant_face_recoit: 45000,
    face: {
      id: 2,
      email: 'f@example.com',
      userable_type: 'Face',
      userable_id: 20,
      userable: {
        id: 20, nom: 'Doe', prenom: 'Jane', username: 'jane',
        profile_photo_url: null, thumbnail_url: null, average_rating: null, ratings_count: 0,
      },
    },
    producer: {
      id: 3,
      email: 'p@example.com',
      userable_type: 'Producer',
      userable_id: 30,
      userable: {
        id: 30, display_name: 'Studio Cotonou',
        profile_photo_url: null, thumbnail_url: null, average_rating: null, ratings_count: 0,
      },
    },
    created_at: '2026-04-01T08:00:00Z',
    ...overrides,
  } as Booking
}

function listResponse(bookings: Booking[], meta: Partial<{ current_page: number; last_page: number; per_page: number; total: number }> = {}) {
  return {
    data: bookings,
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, last_page: 1, per_page: 15, total: bookings.length, ...meta },
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

function makeRouter(): Router {
  const stub = { template: '<div />' }
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/producer/bookings', name: 'producer-bookings', component: stub },
      { path: '/producer/bookings/:id', name: 'producer-booking-detail', component: stub },
      { path: '/face/bookings', name: 'face-bookings', component: stub },
      { path: '/face/bookings/:id', name: 'face-booking-detail', component: stub },
    ],
  })
}

async function mountContent(role: 'face' | 'producer', url = `/${role}/bookings`) {
  const router = makeRouter()
  await router.push(url)
  await router.isReady()
  const wrapper = mount(BookingsListContent, {
    props: { role, routeName: `${role}-bookings`, emptyHint: 'Rien\nÀ faire', emptyCtaLabel: 'Aller' },
    global: { plugins: [router] },
  })
  await flushPromises()
  return { wrapper, router }
}

describe('BookingsListContent', () => {
  beforeEach(() => {
    localStorage.clear()
    getBookings.mockReset()
    getBookings.mockResolvedValue(listResponse([makeBooking('b1'), makeBooking('b2')], { total: 40, last_page: 3 }))
    userRef.value = { id: 7, userable_type: 'Producer' }
    mockMatchMedia(true)
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('renders a table on md and up with the Producer columns', async () => {
    const { wrapper } = await mountContent('producer')
    expect(wrapper.find('table').exists()).toBe(true)
    expect(wrapper.findAll('th').map((th) => th.text())).toEqual([
      'Face', 'Contenu', 'Tournage', 'Durée', 'Montant', 'Statut', 'Créé le', 'Actions',
    ])
    expect(wrapper.findAll('tbody tr')).toHaveLength(2)
    expect(wrapper.text()).toContain('Jane Doe')
    expect(wrapper.text()).toContain('En attente')
  })

  it('renders the status column as a status dot for both roles', async () => {
    const producer = await mountContent('producer')
    const dot = producer.wrapper.find('tbody tr td:nth-child(6) [data-testid="r-status-dot"]')
    expect(dot.exists()).toBe(true)
    expect(dot.attributes('data-tone')).toBe('pending')
    expect(dot.text()).toBe('En attente')
    producer.wrapper.unmount()

    userRef.value = { id: 8, userable_type: 'Face' }
    const face = await mountContent('face')
    expect(face.wrapper.find('tbody tr td:nth-child(6) [data-testid="r-status-dot"]').text()).toBe('En attente')
  })

  it('renders the Face columns with the received amount', async () => {
    userRef.value = { id: 8, userable_type: 'Face' }
    const { wrapper } = await mountContent('face')
    expect(wrapper.findAll('th').map((th) => th.text())).toEqual([
      'Producteur', 'Contenu', 'Tournage', 'Durée', 'Montant reçu', 'Statut', 'Créé le', 'Actions',
    ])
    expect(wrapper.text()).toContain('Studio Cotonou')
    // 45 000 XOF (face) and not 55 000 (producer total)
    expect(wrapper.text().replace(/\s/g, '')).toContain('45000')
    expect(wrapper.text().replace(/\s/g, '')).not.toContain('55000')
  })

  it('renders cards below md and hides the view toggle', async () => {
    mockMatchMedia(false)
    const { wrapper } = await mountContent('producer')
    expect(wrapper.find('table').exists()).toBe(false)
    expect(wrapper.findAll('a[href*="/producer/bookings/"]')).toHaveLength(2)
    expect(wrapper.find('[data-testid="view-cards"]').exists()).toBe(false)
  })

  it('toggles to cards on md+ and persists the choice per user', async () => {
    const { wrapper } = await mountContent('producer')
    await wrapper.find('[data-testid="view-cards"]').trigger('click')
    expect(wrapper.find('table').exists()).toBe(false)
    expect(localStorage.getItem('weact:bookings-view:7')).toBe('cards')

    const again = await mountContent('producer')
    expect(again.wrapper.find('table').exists()).toBe(false)
  })

  it('survives a blocked localStorage', async () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('blocked')
    })
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('blocked')
    })
    const { wrapper } = await mountContent('producer')
    expect(wrapper.find('table').exists()).toBe(true)
    await wrapper.find('[data-testid="view-cards"]').trigger('click')
    expect(wrapper.find('table').exists()).toBe(false)
  })

  it('loads from the URL state (page, sort, status, per_page)', async () => {
    await mountContent('producer', '/producer/bookings?status=active&sort=montant&direction=desc&page=2&per_page=25')
    expect(getBookings).toHaveBeenCalledWith(2, 'active', { sort: 'montant', direction: 'desc', perPage: 25 })
  })

  it('ignores invalid URL values instead of sending them to the API', async () => {
    await mountContent('producer', '/producer/bookings?sort=password&status=nope&page=abc&per_page=9')
    expect(getBookings).toHaveBeenCalledWith(1, '', { sort: null, direction: 'asc', perPage: 15 })
  })

  it('reflects the sort in aria-sort and cycles it through the URL and the API', async () => {
    const { wrapper, router } = await mountContent('producer')
    const montantHeader = () => wrapper.findAll('th').find((th) => th.text().startsWith('Montant'))!

    expect(montantHeader().attributes('aria-sort')).toBe('none')
    await montantHeader().find('button').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toMatchObject({ sort: 'montant', direction: 'asc' })
    expect(getBookings).toHaveBeenLastCalledWith(1, '', { sort: 'montant', direction: 'asc', perPage: 15 })
    expect(montantHeader().attributes('aria-sort')).toBe('ascending')

    await montantHeader().find('button').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toMatchObject({ sort: 'montant', direction: 'desc' })
    expect(montantHeader().attributes('aria-sort')).toBe('descending')

    await montantHeader().find('button').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.sort).toBeUndefined()
    expect(getBookings).toHaveBeenLastCalledWith(1, '', { sort: null, direction: 'asc', perPage: 15 })
  })

  it('does not offer sorting on non sortable columns', async () => {
    const { wrapper } = await mountContent('producer')
    const sortable = wrapper.findAll('th').filter((th) => th.find('button').exists()).map((th) => th.text())
    expect(sortable).toEqual(['Tournage', 'Montant', 'Statut', 'Créé le'])
  })

  it('changes page through the URL and goes back to page 1 when sorting or filtering', async () => {
    const { wrapper, router } = await mountContent('producer', '/producer/bookings?page=2')
    expect(getBookings).toHaveBeenLastCalledWith(2, '', { sort: null, direction: 'asc', perPage: 15 })

    await wrapper.find('[data-testid="pagination-page-3"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.page).toBe('3')
    expect(getBookings).toHaveBeenLastCalledWith(3, '', { sort: null, direction: 'asc', perPage: 15 })

    await wrapper.findAll('th').find((th) => th.text() === 'Statut')!.find('button').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.page).toBeUndefined()
    expect(getBookings).toHaveBeenLastCalledWith(1, '', { sort: 'status', direction: 'asc', perPage: 15 })

    await router.replace({ query: { page: '2', sort: 'status', direction: 'asc' } })
    await flushPromises()
    const completedTab = wrapper.findAll('button').find((b) => b.text() === 'Terminés')!
    await completedTab.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.page).toBeUndefined()
    expect(router.currentRoute.value.query).toMatchObject({ status: 'completed', sort: 'status' })
    expect(getBookings).toHaveBeenLastCalledWith(1, 'completed', { sort: 'status', direction: 'asc', perPage: 15 })
  })

  it('redirects to the last page when the requested page is past the end', async () => {
    getBookings.mockResolvedValueOnce(listResponse([], { current_page: 5, last_page: 3, total: 40 }))
    const { wrapper, router } = await mountContent('producer', '/producer/bookings?page=5')
    await flushPromises()

    expect(router.currentRoute.value.query.page).toBe('3')
    expect(getBookings).toHaveBeenLastCalledWith(3, '', { sort: null, direction: 'asc', perPage: 15 })
    // Never flashes the « no data » state for a list that does have data.
    expect(wrapper.text()).not.toContain('Pas encore de booking')
  })

  it('changes the page size and resets to page 1', async () => {
    const { wrapper, router } = await mountContent('producer', '/producer/bookings?page=2')
    await wrapper.find('select[data-testid="data-table-page-size"]').setValue('50')
    await flushPromises()
    expect(router.currentRoute.value.query).toMatchObject({ per_page: '50' })
    expect(router.currentRoute.value.query.page).toBeUndefined()
    expect(getBookings).toHaveBeenLastCalledWith(1, '', { sort: null, direction: 'asc', perPage: 50 })
  })

  it('navigates to the detail from the Voir link and from a row click', async () => {
    const { wrapper, router } = await mountContent('producer')
    const link = wrapper.find('tbody a[href="/producer/bookings/b1"]')
    expect(link.exists()).toBe(true)
    expect(link.text()).toContain('Voir')

    await wrapper.findAll('tbody tr')[1]!.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/producer/bookings/b2')
  })

  it('shows the empty state with its CTA and emits empty-cta', async () => {
    getBookings.mockResolvedValue(listResponse([]))
    const { wrapper } = await mountContent('producer')
    expect(wrapper.text()).toContain('Pas encore de booking')
    await wrapper.findAll('button').find((b) => b.text() === 'Aller')!.trigger('click')
    expect(wrapper.emitted('empty-cta')).toHaveLength(1)
  })

  it('names the active filter in the empty state and drops the CTA', async () => {
    getBookings.mockResolvedValue(listResponse([]))
    const { wrapper } = await mountContent('producer', '/producer/bookings?status=cancelled')
    expect(wrapper.text()).toContain('Aucun booking avec le statut "Annulés" trouvé.')
    expect(wrapper.text()).not.toContain('Aller')
  })

  it('shows the error with a retry that refetches', async () => {
    getBookings.mockRejectedValueOnce(new Error('boom'))
    const { wrapper } = await mountContent('producer')
    expect(wrapper.find('[data-testid="data-table-retry"]').exists()).toBe(true)

    getBookings.mockResolvedValue(listResponse([makeBooking('b1')]))
    await wrapper.find('[data-testid="data-table-retry"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('table').exists()).toBe(true)
  })
})
