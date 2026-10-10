/**
 * Contrat keep-alive de la liste des bookings (Face), état piloté par l'URL.
 *
 * Historique (bug F7) : un flag `skipNextWatch` armé par un `router.replace` dupliqué
 * (re-clic sur l'onglet de statut déjà actif) restait orphelin sous keep-alive et
 * avalait le refresh au retour du détail. L'état de liste est désormais DANS l'URL
 * (plus aucun flag) : ce fichier garde le scénario comme garde-fou.
 *
 * Vrai router (memory history) + layout keep-alive reproduisant les layouts de l'app ;
 * seul `bookingApi.getBookings` est mocké — son compteur d'appels est la sonde du refresh.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'

const getBookings = vi.hoisted(() => vi.fn())
vi.mock('@/features/booking/services/bookingApi', () => ({
  bookingApi: { getBookings },
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: { id: 1, userable_type: 'Face' } }),
}))

import FaceBookingsListPage from '../FaceBookingsListPage.vue'
import BookingStatusFilter from '@/features/booking/components/BookingStatusFilter.vue'

const DetailPageStub = {
  name: 'FaceBookingDetailPageStub',
  template: '<div data-testid="detail-page" />',
}

const RootLayout = {
  name: 'RootLayout',
  template: `
    <router-view v-slot="{ Component }">
      <keep-alive>
        <component :is="Component" v-if="$route.meta.keepAlive" />
      </keep-alive>
      <component :is="Component" v-if="!$route.meta.keepAlive" :key="$route.path" />
    </router-view>
  `,
}

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/face/bookings', name: 'face-bookings', component: FaceBookingsListPage, meta: { keepAlive: true } },
      { path: '/face/bookings/:id', name: 'face-booking-detail', component: DetailPageStub },
    ],
  })
}

async function mountApp() {
  const router = makeRouter()
  await router.push('/face/bookings')
  await router.isReady()
  const wrapper = mount(RootLayout, { global: { plugins: [router] } })
  await flushPromises()
  return { wrapper, router }
}

describe('FaceBookingsListPage — keep-alive (état de liste dans l\'URL)', () => {
  beforeEach(() => {
    getBookings.mockReset()
    getBookings.mockResolvedValue({
      data: [],
      links: { first: null, last: null, prev: null, next: null },
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    })
  })

  it('refetch au retour du détail même après un re-clic redondant sur le filtre actif', async () => {
    const { wrapper, router } = await mountApp()
    expect(getBookings).toHaveBeenCalledTimes(1)

    // Re-clic sur « Tous » (déjà actif) : navigation dupliquée, aucun fetch.
    await wrapper.findComponent(BookingStatusFilter).findAll('button')[0]!.trigger('click')
    await flushPromises()
    expect(getBookings).toHaveBeenCalledTimes(1)

    await router.push('/face/bookings/123')
    await flushPromises()
    expect(getBookings).toHaveBeenCalledTimes(1)

    await router.push('/face/bookings')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('face-bookings')
    expect(getBookings).toHaveBeenCalledTimes(2)
    expect(getBookings).toHaveBeenLastCalledWith(1, '', { sort: null, direction: 'asc', perPage: 15 })
  })

  it('TÉMOIN : sans re-clic redondant, le retour du détail resynchronise la liste', async () => {
    const { router } = await mountApp()
    expect(getBookings).toHaveBeenCalledTimes(1)

    await router.push('/face/bookings/123')
    await flushPromises()
    expect(getBookings).toHaveBeenCalledTimes(1)

    await router.push('/face/bookings')
    await flushPromises()
    expect(getBookings).toHaveBeenCalledTimes(2)
  })

  it('restaure la vue (filtre + tri) depuis l\'URL au retour et refetch avec ces paramètres', async () => {
    const { router } = await mountApp()

    await router.push('/face/bookings?status=active&sort=created_at&direction=desc&page=2')
    await flushPromises()
    expect(getBookings).toHaveBeenLastCalledWith(2, 'active', { sort: 'created_at', direction: 'desc', perPage: 15 })

    await router.push('/face/bookings/123')
    await flushPromises()
    const calls = getBookings.mock.calls.length

    await router.push('/face/bookings?status=active&sort=created_at&direction=desc&page=2')
    await flushPromises()
    expect(getBookings.mock.calls.length).toBe(calls + 1)
    expect(getBookings).toHaveBeenLastCalledWith(2, 'active', { sort: 'created_at', direction: 'desc', perPage: 15 })
  })
})
