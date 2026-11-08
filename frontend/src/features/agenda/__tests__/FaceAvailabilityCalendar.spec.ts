import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import FaceAvailabilityCalendar from '../components/FaceAvailabilityCalendar.vue'
import { agendaApi } from '../services/agendaApi'

vi.mock('@/lib/dates', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/dates')>()),
  businessTodayIso: () => '2026-11-08',
}))

vi.mock('../services/agendaApi', () => ({
  agendaApi: { getAgenda: vi.fn(), updateUnavailableDays: vi.fn(), getFaceAvailability: vi.fn() },
}))

const cell = (wrapper: ReturnType<typeof mount>, iso: string) => wrapper.get(`[data-testid="day-${iso}"]`)

describe('FaceAvailabilityCalendar', () => {
  beforeEach(() => {
    vi.mocked(agendaApi.getFaceAvailability)
      .mockReset()
      .mockResolvedValue({
        data: { from: '2026-11-01', to: '2026-11-30', today: '2026-11-08', unavailable_days: ['2026-11-12', '2026-11-18'] },
      })
  })

  it('charge la disponibilité de la Face (uuid) pour le mois affiché et montre deux états seulement', async () => {
    const wrapper = mount(FaceAvailabilityCalendar, { props: { faceId: 'face-uuid-1' } })
    await flushPromises()

    expect(agendaApi.getFaceAvailability).toHaveBeenCalledWith('face-uuid-1', '2026-11-01', '2026-11-30')
    expect(wrapper.text()).toContain('Disponibilités')
    expect(wrapper.text()).toContain('Les jours indisponibles ne peuvent pas être réservés.')
    expect(wrapper.get('[data-testid="availability-legend"]').text()).toBe('Libre Indisponible')

    expect(wrapper.findAll('button[aria-pressed]')).toHaveLength(0)
    expect(cell(wrapper, '2026-11-12').get('span').classes()).toEqual(expect.arrayContaining(['bg-slate-200', 'text-slate-500']))
    expect(cell(wrapper, '2026-11-12').get('span').classes()).not.toContain('line-through')
    expect(cell(wrapper, '2026-11-20').get('span').classes()).toContain('border-slate-200')
    expect(cell(wrapper, '2026-11-08').get('span').classes()).toContain('ring-slate-900')
    expect(cell(wrapper, '2026-11-03').get('span').classes()).toContain('text-slate-300')
    expect(cell(wrapper, '2026-11-12').attributes('aria-label')).toBe('Jeudi 12 novembre, indisponible')
  })

  it('navigue entre les mois et recharge', async () => {
    const wrapper = mount(FaceAvailabilityCalendar, { props: { faceId: 'face-uuid-1' } })
    await flushPromises()
    await wrapper.get('[data-testid="availability-next"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="availability-month-title"]').text()).toBe('Décembre 2026')
    expect(agendaApi.getFaceAvailability).toHaveBeenLastCalledWith('face-uuid-1', '2026-12-01', '2026-12-31')
  })

  it('signale un échec de chargement sans rien bloquer', async () => {
    vi.mocked(agendaApi.getFaceAvailability).mockRejectedValueOnce(new Error('boom'))
    const wrapper = mount(FaceAvailabilityCalendar, { props: { faceId: 'face-uuid-1' } })
    await flushPromises()

    expect(wrapper.find('[data-testid="availability-error"]').exists()).toBe(true)
  })
})
