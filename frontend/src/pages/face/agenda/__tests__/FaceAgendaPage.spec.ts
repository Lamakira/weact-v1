import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { AxiosError, type AxiosResponse } from 'axios'
import FaceAgendaPage from '../FaceAgendaPage.vue'
import { agendaApi } from '@/features/agenda/services/agendaApi'
import type { AgendaData } from '@/features/agenda/types'

const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }))
vi.mock('@/composables/useToast', () => ({ useToast: () => mockToast }))

vi.mock('vue-router', () => ({
  RouterLink: {
    name: 'RouterLink',
    props: ['to'],
    template: '<a :data-route="JSON.stringify(to)" v-bind="$attrs"><slot /></a>',
  },
}))

vi.mock('@/lib/dates', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/dates')>()),
  businessTodayIso: () => '2026-11-08',
}))

vi.mock('@/features/agenda/services/agendaApi', () => ({
  agendaApi: { getAgenda: vi.fn(), updateUnavailableDays: vi.fn(), getFaceAvailability: vi.fn() },
}))

const agenda: AgendaData = {
  from: '2026-11-01',
  to: '2026-11-30',
  today: '2026-11-08',
  unavailable_days: ['2026-11-12', '2026-11-13'],
  events: [
    {
      kind: 'booking',
      uuid: 'b-1',
      title: 'Clip Afro Vibes',
      status: 'accepted',
      state: 'engaged',
      start: '2026-11-18',
      end: '2026-11-20',
      counterpart: 'Studio Lumière',
    },
    {
      kind: 'mission',
      uuid: 'm-1',
      title: 'Pub boisson',
      status: 'confirmed',
      state: 'engaged',
      start: '2026-11-24',
      end: '2026-11-24',
      counterpart: 'Agence X',
    },
    {
      kind: 'booking',
      uuid: 'b-0',
      title: 'Shooting passé',
      status: 'completed',
      state: 'done',
      start: '2026-11-02',
      end: '2026-11-03',
      counterpart: 'Studio Passé',
    },
  ],
}

function unprocessable(message: string): AxiosError {
  const error = new AxiosError('Request failed', 'ERR_BAD_REQUEST')
  error.response = {
    status: 422,
    data: { message, errors: { add: [message] } },
  } as AxiosResponse
  return error
}

// Les pages montées sont démontées après chaque test : l'écoute clavier est sur window.
const mounted: ReturnType<typeof mount>[] = []

async function mountPage() {
  const wrapper = mount(FaceAgendaPage, {
    attachTo: document.body,
    global: { stubs: { Teleport: { template: '<div><slot /></div>' } } },
  })
  mounted.push(wrapper)
  await flushPromises()
  return wrapper
}

const day = (iso: string) => `[data-testid="day-${iso}"]`

const cell = (iso: string) => `[data-testid="cell-${iso}"]`

describe('FaceAgendaPage', () => {
  afterEach(() => {
    mounted.splice(0).forEach((w) => w.unmount())
  })

  beforeEach(() => {
    localStorage.clear()
    vi.mocked(agendaApi.getAgenda).mockReset().mockResolvedValue({ data: agenda })
    vi.mocked(agendaApi.updateUnavailableDays)
      .mockReset()
      .mockResolvedValue({ data: { unavailable_days: [] }, message: 'Agenda mis à jour' })
    mockToast.success.mockReset()
    mockToast.error.mockReset()
  })

  it('charge le mois affiché du 1er au dernier jour et affiche titre, légende et « À venir »', async () => {
    const wrapper = await mountPage()

    expect(agendaApi.getAgenda).toHaveBeenCalledWith('2026-11-01', '2026-11-30')
    expect(wrapper.get('[data-testid="agenda-month-title"]').text()).toBe('Novembre 2026')
    expect(wrapper.get('[data-testid="agenda-legend"]').text()).toContain('Demande en attente')
    expect(wrapper.get(day('2026-11-12')).get('span').classes()).toContain('line-through')
    expect(wrapper.get(day('2026-11-19')).get('span').classes()).toContain('bg-teal-600')

    const items = wrapper.findAll('[data-testid="agenda-upcoming-item"]')
    expect(items).toHaveLength(2)
    expect(items[0]!.text()).toContain('Clip Afro Vibes')
    expect(items[0]!.text()).toContain('18 au 20 nov · 3 jours')
    expect(items[0]!.attributes('data-route')).toBe(JSON.stringify({ name: 'face-booking-detail', params: { id: 'b-1' } }))
    expect(items[1]!.text()).toContain('24 nov · mission')
    expect(items[1]!.attributes('data-route')).toBe(JSON.stringify({ name: 'face-mission-detail', params: { id: 'm-1' } }))
  })

  it('recharge le mois suivant avec les chevrons', async () => {
    const wrapper = await mountPage()
    await wrapper.get('[data-testid="agenda-next"]').trigger('click')
    await flushPromises()

    expect(agendaApi.getAgenda).toHaveBeenLastCalledWith('2026-12-01', '2026-12-31')
    expect(wrapper.get('[data-testid="agenda-month-title"]').text()).toBe('Décembre 2026')
  })

  it('sélectionne des jours puis envoie PUT add, toast de succès, retour au mode normal et rechargement', async () => {
    const wrapper = await mountPage()
    await wrapper.get('[data-testid="agenda-block-days"]').trigger('click')

    expect(wrapper.text()).toContain('Touchez les jours où vous n\'êtes pas disponible.')
    expect(wrapper.text()).toContain('Les jours déjà engagés (booking, mission) ne peuvent pas être bloqués')
    expect(wrapper.get('[data-testid="agenda-selection-submit"]').attributes('disabled')).toBeDefined()

    await wrapper.get(day('2026-11-30')).trigger('click')
    await wrapper.get(day('2026-11-28')).trigger('click')
    await wrapper.get(day('2026-11-29')).trigger('click')
    expect(wrapper.get('[data-testid="agenda-selected-count"]').text()).toBe('3 jours sélectionnés')
    expect(wrapper.get('[data-testid="agenda-selected-list"]').text()).toBe('Sam 28, dim 29 et lun 30 nov')

    // Jour engagé et jour passé : ignorés.
    await wrapper.get(day('2026-11-19')).trigger('click')
    await wrapper.get(day('2026-11-03')).trigger('click')
    expect(wrapper.get('[data-testid="agenda-selected-count"]').text()).toBe('3 jours sélectionnés')

    await wrapper.get('[data-testid="agenda-selection-submit"]').trigger('click')
    await flushPromises()

    expect(agendaApi.updateUnavailableDays).toHaveBeenCalledWith({ add: ['2026-11-28', '2026-11-29', '2026-11-30'] })
    expect(mockToast.success).toHaveBeenCalledWith('Agenda mis à jour')
    expect(agendaApi.getAgenda).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[data-testid="agenda-selection-bar"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="agenda-block-days"]').exists()).toBe(true)
  })

  it('garde la sélection et affiche le premier message quand le serveur répond 422', async () => {
    vi.mocked(agendaApi.updateUnavailableDays).mockRejectedValueOnce(
      unprocessable('Le 18 novembre est déjà engagé (booking ou mission).'),
    )
    const wrapper = await mountPage()
    await wrapper.get('[data-testid="agenda-block-days"]').trigger('click')
    await wrapper.get(day('2026-11-28')).trigger('click')
    await wrapper.get('[data-testid="agenda-selection-submit"]').trigger('click')
    await flushPromises()

    expect(mockToast.error).toHaveBeenCalledWith('Le 18 novembre est déjà engagé (booking ou mission).')
    expect(mockToast.success).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="agenda-selection-bar"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="agenda-selected-count"]').text()).toBe('1 jour sélectionné')
    expect(agendaApi.getAgenda).toHaveBeenCalledTimes(1)
  })

  it('quitte le mode sélection avec la croix, sans appel', async () => {
    const wrapper = await mountPage()
    await wrapper.get('[data-testid="agenda-block-days"]').trigger('click')
    await wrapper.get(day('2026-11-28')).trigger('click')
    await wrapper.get('[data-testid="agenda-selection-exit"]').trigger('click')

    expect(wrapper.find('[data-testid="agenda-selection-bar"]').exists()).toBe(false)
    expect(agendaApi.updateUnavailableDays).not.toHaveBeenCalled()
  })

  it('ouvre le panneau d\'un jour bloqué avec « Rendre disponible » (PUT remove)', async () => {
    const wrapper = await mountPage()
    await wrapper.get(day('2026-11-12')).trigger('click')

    expect(wrapper.get('[data-testid="agenda-day-title"]').text()).toBe('Jeudi 12 novembre')
    expect(wrapper.find('[data-testid="agenda-day-block"]').exists()).toBe(false)

    await wrapper.get('[data-testid="agenda-day-unblock"]').trigger('click')
    await flushPromises()

    expect(agendaApi.updateUnavailableDays).toHaveBeenCalledWith({ remove: ['2026-11-12'] })
    expect(mockToast.success).toHaveBeenCalledWith('Agenda mis à jour')
    expect(wrapper.find('[data-testid="agenda-day-sheet"]').exists()).toBe(false)
  })

  it('propose « Marquer indisponible » sur un jour futur libre (PUT add)', async () => {
    const wrapper = await mountPage()
    await wrapper.get(day('2026-11-15')).trigger('click')
    await wrapper.get('[data-testid="agenda-day-block"]').trigger('click')
    await flushPromises()

    expect(agendaApi.updateUnavailableDays).toHaveBeenCalledWith({ add: ['2026-11-15'] })
  })

  it('liste le booking du jour avec son état, sa période, sa contrepartie et son lien', async () => {
    const wrapper = await mountPage()
    await wrapper.get(day('2026-11-19')).trigger('click')

    const sheet = wrapper.get('[data-testid="agenda-day-events"]')
    expect(sheet.text()).toContain('Clip Afro Vibes')
    expect(sheet.text()).toContain('booking accepté')
    expect(sheet.text()).toContain('18 au 20 nov · avec Studio Lumière')
    expect(sheet.get('[data-testid="agenda-event-link"]').text()).toBe('Voir le booking')
    // Jour engagé : aucune action de blocage.
    expect(wrapper.find('[data-testid="agenda-day-block"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="agenda-day-unblock"]').exists()).toBe(false)
  })

  it('n\'offre aucune action sur un jour passé', async () => {
    const wrapper = await mountPage()
    await wrapper.get(day('2026-11-03')).trigger('click')

    expect(wrapper.get('[data-testid="agenda-day-events"]').text()).toContain('booking terminé')
    expect(wrapper.find('[data-testid="agenda-day-block"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="agenda-day-unblock"]').exists()).toBe(false)

    await wrapper.get(day('2026-11-05')).trigger('click')
    expect(wrapper.find('[data-testid="agenda-day-block"]').exists()).toBe(false)
  })

  it('ferme le panneau avec Échap, le fond ou la croix', async () => {
    const wrapper = await mountPage()
    const sheet = '[data-testid="agenda-day-sheet"]'

    await wrapper.get(day('2026-11-15')).trigger('click')
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()
    expect(wrapper.find(sheet).exists()).toBe(false)

    await wrapper.get(day('2026-11-15')).trigger('click')
    await wrapper.get(sheet).trigger('click')
    await flushPromises()
    expect(wrapper.find(sheet).exists()).toBe(false)

    await wrapper.get(day('2026-11-15')).trigger('click')
    await wrapper.get('[data-testid="agenda-day-close"]').trigger('click')
    await flushPromises()
    expect(wrapper.find(sheet).exists()).toBe(false)
  })

  it('affiche une erreur de chargement avec « Réessayer »', async () => {
    vi.mocked(agendaApi.getAgenda).mockRejectedValueOnce(new Error('boom'))
    const wrapper = await mountPage()
    expect(wrapper.find('[data-testid="agenda-error"]').exists()).toBe(true)

    await wrapper.get('[data-testid="agenda-error"] button').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-testid="agenda-error"]').exists()).toBe(false)
  })

  describe('sélecteur de mois', () => {
    it('le titre du mois du téléphone ouvre le sélecteur ; un mois choisi charge ce mois et rend le focus', async () => {
      const wrapper = await mountPage()
      const button = wrapper.get('[data-testid="agenda-month-title-button"]')
      expect(button.attributes('aria-haspopup')).toBe('dialog')
      expect(button.attributes('aria-expanded')).toBe('false')
      expect(wrapper.get('[data-testid="agenda-month-title"]').text()).toBe('Novembre 2026')

      await button.trigger('click')
      expect(button.attributes('aria-expanded')).toBe('true')
      expect(wrapper.find('[data-testid="month-picker"]').exists()).toBe(true)

      await wrapper.get('[data-testid="month-option-2026-12-01"]').trigger('click')
      await flushPromises()

      expect(agendaApi.getAgenda).toHaveBeenLastCalledWith('2026-12-01', '2026-12-31')
      expect(wrapper.get('[data-testid="agenda-month-title"]').text()).toBe('Décembre 2026')
      expect(wrapper.find('[data-testid="month-picker"]').exists()).toBe(false)
      expect(document.activeElement).toBe(button.element)
    })

    it('le titre de la barre d\'outils ouvre le sélecteur ; Échap le ferme sans changer de mois', async () => {
      const wrapper = await mountPage()
      await wrapper.get('[data-testid="agenda-toolbar-title-button"]').trigger('click')
      expect(wrapper.find('[data-testid="month-picker"]').exists()).toBe(true)

      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
      await flushPromises()
      expect(wrapper.find('[data-testid="month-picker"]').exists()).toBe(false)
      expect(agendaApi.getAgenda).toHaveBeenCalledTimes(1)

      await wrapper.get('[data-testid="agenda-toolbar-title-button"]').trigger('click')
      await wrapper.get('[data-testid="month-option-2026-10-01"]').trigger('click')
      await flushPromises()
      expect(agendaApi.getAgenda).toHaveBeenLastCalledWith('2026-10-01', '2026-10-31')
      expect(wrapper.get('[data-testid="agenda-toolbar-title"]').text()).toBe('Octobre 2026')
    })

    it('en vue Semaine, le titre reste « Semaine du … » et le mois choisi mène à sa première semaine', async () => {
      const wrapper = await mountPage()
      await wrapper.get('[data-testid="agenda-view-week"]').trigger('click')
      await flushPromises()
      expect(wrapper.get('[data-testid="agenda-toolbar-title"]').text()).toBe('Semaine du 2 au 8 novembre')

      await wrapper.get('[data-testid="agenda-toolbar-title-button"]').trigger('click')
      await wrapper.get('[data-testid="month-option-2026-12-01"]').trigger('click')
      await flushPromises()

      // Le 1er décembre est un mardi : première semaine du lundi 30 novembre au dimanche 6 décembre.
      expect(wrapper.get('[data-testid="agenda-toolbar-title"]').text()).toBe('Semaine du 30 novembre au 6 décembre')
      expect(agendaApi.getAgenda).toHaveBeenLastCalledWith('2026-11-30', '2026-12-31')
    })
  })

  describe('blocage « du … au … »', () => {
    const emptyRange = { data: { ...agenda, unavailable_days: [], events: [] } }

    async function openSelection() {
      const wrapper = await mountPage()
      await wrapper.get('[data-testid="agenda-block-days"]').trigger('click')
      return wrapper
    }

    async function addRange(wrapper: Awaited<ReturnType<typeof mountPage>>, from: string, to: string) {
      await wrapper.get('[data-testid="agenda-range-from"]').setValue(from)
      await wrapper.get('[data-testid="agenda-range-to"]').setValue(to)
      await wrapper.get('[data-testid="agenda-range-add"]').trigger('click')
      await flushPromises()
    }

    const message = (wrapper: Awaited<ReturnType<typeof mountPage>>) =>
      wrapper.get('[data-testid="agenda-range-message"]').text()

    it('n\'affiche la ligne qu\'en mode sélection, bornée à aujourd\'hui et à l\'horizon', async () => {
      const wrapper = await mountPage()
      expect(wrapper.find('[data-testid="agenda-range"]').exists()).toBe(false)

      await wrapper.get('[data-testid="agenda-block-days"]').trigger('click')
      const from = wrapper.get('[data-testid="agenda-range-from"]')
      expect(from.attributes('min')).toBe('2026-11-08')
      expect(from.attributes('max')).toBe('2027-11-08')
      expect(wrapper.get('[data-testid="agenda-range-to"]').attributes('max')).toBe('2027-11-08')
      expect(wrapper.get('[data-testid="agenda-range-add"]').text()).toBe('Ajouter ces jours')
    })

    it('ajoute les jours touchables, ignore un jour engagé et un jour bloqué, sans toucher au mois affiché', async () => {
      const wrapper = await openSelection()
      // 12 et 13 bloqués, 18 au 20 engagés (booking) : du 11 au 20 = 10 jours, 5 ajoutés.
      await addRange(wrapper, '2026-11-11', '2026-11-20')

      expect(agendaApi.getAgenda).toHaveBeenLastCalledWith('2026-11-11', '2026-11-20')
      expect(message(wrapper)).toBe('5 jours ajoutés, 5 ignorés (déjà engagés ou déjà bloqués).')
      expect(wrapper.get('[data-testid="agenda-selected-count"]').text()).toBe('5 jours sélectionnés')
      expect(wrapper.get('[data-testid="agenda-selected-list"]').text()).toBe('Mer 11, sam 14, dim 15 et 2 autres')
      // Le mois affiché n'a pas été rechargé ni changé.
      expect(wrapper.get('[data-testid="agenda-month-title"]').text()).toBe('Novembre 2026')
      expect(agendaApi.getAgenda).toHaveBeenCalledTimes(2)
    })

    it('dit « 12 jours ajoutés. » sans ignorés, garde l\'union triée sans doublon', async () => {
      const wrapper = await openSelection()
      await wrapper.get(day('2026-11-28')).trigger('click')
      vi.mocked(agendaApi.getAgenda).mockResolvedValueOnce(emptyRange)
      await addRange(wrapper, '2026-11-26', '2026-11-30')

      // 28 déjà sélectionné : 4 nouveaux jours.
      expect(message(wrapper)).toBe('4 jours ajoutés.')
      expect(wrapper.get('[data-testid="agenda-selected-count"]').text()).toBe('5 jours sélectionnés')
    })

    it('dit « Aucun jour à bloquer sur cette plage. » quand rien n\'est touchable', async () => {
      const wrapper = await openSelection()
      await addRange(wrapper, '2026-11-12', '2026-11-13')
      expect(message(wrapper)).toBe('Aucun jour à bloquer sur cette plage.')
      expect(wrapper.get('[data-testid="agenda-selected-count"]').text()).toBe('0 jour sélectionné')
    })

    it('valide en ligne : champ vide, ordre inversé, plus de 62 jours, sans appel', async () => {
      const wrapper = await openSelection()

      await wrapper.get('[data-testid="agenda-range-from"]').setValue('2026-11-10')
      await wrapper.get('[data-testid="agenda-range-add"]').trigger('click')
      await flushPromises()
      expect(message(wrapper)).toBe('Choisissez une date de début et une date de fin.')

      await addRange(wrapper, '2026-11-20', '2026-11-10')
      expect(message(wrapper)).toBe('La date de fin doit être après la date de début.')

      await addRange(wrapper, '2026-11-09', '2027-01-20')
      expect(message(wrapper)).toBe('62 jours au plus à la fois.')

      expect(agendaApi.getAgenda).toHaveBeenCalledTimes(1)
      expect(wrapper.get('[data-testid="agenda-selected-count"]').text()).toBe('0 jour sélectionné')
    })

    it('accepte une plage de 62 jours exactement', async () => {
      const wrapper = await openSelection()
      vi.mocked(agendaApi.getAgenda).mockResolvedValueOnce(emptyRange)
      // Du 9 novembre au 9 janvier : 62 jours.
      await addRange(wrapper, '2026-11-09', '2027-01-09')
      expect(message(wrapper)).toBe('62 jours ajoutés.')
    })

    it('n\'ajoute rien et le dit quand l\'appel échoue', async () => {
      const wrapper = await openSelection()
      vi.mocked(agendaApi.getAgenda).mockRejectedValueOnce(new Error('boom'))
      await addRange(wrapper, '2026-11-09', '2026-11-11')

      expect(message(wrapper)).toBe('Impossible de vérifier ces dates. Réessayez.')
      expect(wrapper.get('[data-testid="agenda-selected-count"]').text()).toBe('0 jour sélectionné')
    })

    it('n\'ajoute rien quand le total dépasserait 62 jours', async () => {
      const wrapper = await openSelection()
      vi.mocked(agendaApi.getAgenda).mockResolvedValueOnce(emptyRange)
      await addRange(wrapper, '2026-11-09', '2027-01-06')
      expect(wrapper.get('[data-testid="agenda-selected-count"]').text()).toBe('59 jours sélectionnés')

      vi.mocked(agendaApi.getAgenda).mockResolvedValueOnce(emptyRange)
      await addRange(wrapper, '2027-01-07', '2027-01-12')
      expect(message(wrapper)).toBe("62 jours au plus par envoi : marquez d'abord ces jours indisponibles.")
      expect(wrapper.get('[data-testid="agenda-selected-count"]').text()).toBe('59 jours sélectionnés')
    })

    it('affiche le mois du premier jour ajouté et un résumé « Du … au … · N jours » dans la barre basse', async () => {
      const wrapper = await openSelection()
      vi.mocked(agendaApi.getAgenda).mockResolvedValueOnce(emptyRange)
      await addRange(wrapper, '2026-12-20', '2027-01-05')

      expect(message(wrapper)).toBe('17 jours ajoutés.')
      expect(agendaApi.getAgenda).toHaveBeenLastCalledWith('2026-12-01', '2026-12-31')
      expect(wrapper.get('[data-testid="agenda-month-title"]').text()).toBe('Décembre 2026')
      expect(wrapper.get('[data-testid="agenda-selected-list"]').text()).toBe('Du 20 décembre au 5 janvier · 17 jours')
    })

    it('garde la liste détaillée pour une petite sélection', async () => {
      const wrapper = await openSelection()
      await addRange(wrapper, '2026-11-09', '2026-11-11')
      expect(wrapper.get('[data-testid="agenda-selected-list"]').text()).toBe('Lun 9, mar 10 et mer 11 nov')
    })

    it('envoie PUT add avec les jours de la plage, puis revient au mode normal', async () => {
      const wrapper = await openSelection()
      await addRange(wrapper, '2026-11-09', '2026-11-11')
      await wrapper.get('[data-testid="agenda-selection-submit"]').trigger('click')
      await flushPromises()

      expect(agendaApi.updateUnavailableDays).toHaveBeenCalledWith({ add: ['2026-11-09', '2026-11-10', '2026-11-11'] })
      expect(mockToast.success).toHaveBeenCalledWith('Agenda mis à jour')
      expect(wrapper.find('[data-testid="agenda-selection-bar"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="agenda-range"]').exists()).toBe(false)
    })
  })

  describe('version téléphone : gains et choix Mois / Liste', () => {
    const MOBILE_KEY = 'weact:agenda:view-mobile'
    const DESKTOP_KEY = 'weact:agenda:view'
    const mobile = (wrapper: Awaited<ReturnType<typeof mountPage>>) => wrapper.get('[data-testid="agenda-mobile"]')

    it('montre la carte « Gains à venir » dans le bloc téléphone, entre le bouton et « À venir »', async () => {
      vi.mocked(agendaApi.getAgenda).mockResolvedValue({
        data: { ...agenda, upcoming_earnings: { amount: 75000, count: 2 } },
      })
      const wrapper = await mountPage()
      const phone = mobile(wrapper)

      const card = phone.get('[data-testid="agenda-mobile-earnings"]')
      expect(card.text()).toContain('Gains à venir')
      expect(card.get('[data-testid="earnings-count"]').text()).toBe('2 engagements en séquestre')
      expect(card.text()).toContain('Versés sur votre portefeuille après le tournage.')

      const html = phone.html()
      expect(html.indexOf('agenda-block-days')).toBeLessThan(html.indexOf('agenda-mobile-earnings'))
      expect(html.indexOf('agenda-mobile-earnings')).toBeLessThan(html.indexOf('agenda-upcoming"'))
    })

    it('affiche « Aucun gain en attente » sur téléphone sans gain', async () => {
      const wrapper = await mountPage()
      expect(mobile(wrapper).get('[data-testid="earnings-empty"]').text()).toBe('Aucun gain en attente')
    })

    it('par défaut Mois : grille et légende, sans liste', async () => {
      const wrapper = await mountPage()
      const phone = mobile(wrapper)
      expect(phone.get('[data-testid="agenda-mobile-view-month"]').attributes('aria-pressed')).toBe('true')
      expect(phone.find(day('2026-11-19')).exists()).toBe(true)
      expect(phone.find('[data-testid="agenda-legend"]').exists()).toBe(true)
      expect(phone.find('[data-testid="agenda-list-view"]').exists()).toBe(false)
    })

    it('la bascule Liste remplace la grille et la légende par la liste, et mémorise sous sa propre clé', async () => {
      const wrapper = await mountPage()
      await wrapper.get('[data-testid="agenda-mobile-view-list"]').trigger('click')
      await flushPromises()
      const phone = mobile(wrapper)

      expect(phone.find('[data-testid="agenda-list-view"]').exists()).toBe(true)
      expect(phone.find(day('2026-11-19')).exists()).toBe(false)
      expect(phone.find('[data-testid="agenda-legend"]').exists()).toBe(false)
      // Filtres par défaut : « Terminés » masqué, jours bloqués visibles.
      expect(phone.text()).toContain('Clip Afro Vibes')
      expect(phone.text()).not.toContain('Shooting passé')
      expect(phone.text()).toContain('Indisponible')
      // L'en-tête de mois, le bouton, les gains et « À venir » restent.
      expect(phone.find('[data-testid="agenda-month-title"]').exists()).toBe(true)
      expect(phone.find('[data-testid="agenda-block-days"]').exists()).toBe(true)
      expect(phone.find('[data-testid="agenda-mobile-earnings"]').exists()).toBe(true)
      expect(phone.find('[data-testid="agenda-upcoming"]').exists()).toBe(true)

      expect(localStorage.getItem(MOBILE_KEY)).toBe('list')
      expect(localStorage.getItem(DESKTOP_KEY)).toBeNull()

      const second = await mountPage()
      expect(mobile(second).find('[data-testid="agenda-list-view"]').exists()).toBe(true)
    })

    it('la navigation entre mois fonctionne en vue Liste', async () => {
      const wrapper = await mountPage()
      await wrapper.get('[data-testid="agenda-mobile-view-list"]').trigger('click')
      await wrapper.get('[data-testid="agenda-next"]').trigger('click')
      await flushPromises()

      expect(agendaApi.getAgenda).toHaveBeenLastCalledWith('2026-12-01', '2026-12-31')
      expect(wrapper.get('[data-testid="agenda-month-title"]').text()).toBe('Décembre 2026')
      expect(mobile(wrapper).find('[data-testid="agenda-list-view"]').exists()).toBe(true)
    })

    it('ne lit pas la vue de l\'ordinateur : Semaine côté ordinateur laisse le téléphone en Mois', async () => {
      localStorage.setItem(DESKTOP_KEY, 'week')
      const wrapper = await mountPage()
      expect(mobile(wrapper).get('[data-testid="agenda-mobile-view-month"]').attributes('aria-pressed')).toBe('true')
      expect(mobile(wrapper).find(day('2026-11-19')).exists()).toBe(true)
      expect(wrapper.find('[data-testid="week-view"]').exists()).toBe(true)
    })

    it('ignore une valeur mémorisée invalide pour le téléphone', async () => {
      localStorage.setItem(MOBILE_KEY, 'week')
      const wrapper = await mountPage()
      expect(mobile(wrapper).get('[data-testid="agenda-mobile-view-month"]').attributes('aria-pressed')).toBe('true')
    })

    it('la vue de l\'ordinateur reste inchangée après une bascule sur téléphone, et inversement', async () => {
      const wrapper = await mountPage()
      await wrapper.get('[data-testid="agenda-mobile-view-list"]').trigger('click')
      await flushPromises()
      expect(wrapper.find('[data-testid="month-grid-large"]').exists()).toBe(true)
      expect(wrapper.get('[data-testid="agenda-desktop"]').find('[data-testid="agenda-list-view"]').exists()).toBe(false)
      expect(localStorage.getItem(DESKTOP_KEY)).toBeNull()

      await wrapper.get('[data-testid="agenda-view-week"]').trigger('click')
      await flushPromises()
      expect(localStorage.getItem(DESKTOP_KEY)).toBe('week')
      expect(localStorage.getItem(MOBILE_KEY)).toBe('list')
      expect(mobile(wrapper).get('[data-testid="agenda-mobile-view-list"]').attributes('aria-pressed')).toBe('true')
    })

    it('la vue Liste du téléphone ne lit pas les filtres de l\'ordinateur', async () => {
      localStorage.setItem('weact:agenda:filters', JSON.stringify({ bookings: false, done: true }))
      const wrapper = await mountPage()
      await wrapper.get('[data-testid="agenda-mobile-view-list"]').trigger('click')
      await flushPromises()
      const phone = mobile(wrapper)
      expect(phone.text()).toContain('Clip Afro Vibes')
      expect(phone.text()).not.toContain('Shooting passé')
    })

    it('« Bloquer des jours » remet la grille, puis la vue Liste revient à la sortie', async () => {
      const wrapper = await mountPage()
      await wrapper.get('[data-testid="agenda-mobile-view-list"]').trigger('click')
      await wrapper.get('[data-testid="agenda-block-days"]').trigger('click')
      await flushPromises()

      let phone = mobile(wrapper)
      expect(phone.find(day('2026-11-28')).exists()).toBe(true)
      expect(phone.find('[data-testid="agenda-list-view"]').exists()).toBe(false)
      expect(phone.find('[data-testid="agenda-mobile-views"]').exists()).toBe(false)
      await wrapper.get(day('2026-11-28')).trigger('click')
      expect(wrapper.get('[data-testid="agenda-selected-count"]').text()).toBe('1 jour sélectionné')
      // Le choix mémorisé n'est pas écrasé par le forçage.
      expect(localStorage.getItem(MOBILE_KEY)).toBe('list')

      await wrapper.get('[data-testid="agenda-selection-exit"]').trigger('click')
      await flushPromises()
      phone = mobile(wrapper)
      expect(phone.find('[data-testid="agenda-list-view"]').exists()).toBe(true)
      expect(phone.find(day('2026-11-28')).exists()).toBe(false)
      expect(phone.get('[data-testid="agenda-mobile-view-list"]').attributes('aria-pressed')).toBe('true')
    })

    it('un appui sur une ligne de la liste ouvre le panneau du jour (premier jour d\'une plage bloquée)', async () => {
      const wrapper = await mountPage()
      await wrapper.get('[data-testid="agenda-mobile-view-list"]').trigger('click')
      await flushPromises()
      const rows = mobile(wrapper).findAll('[data-testid="list-row"]')

      const booking = rows.find((row) => row.text().includes('Clip Afro Vibes'))!
      await booking.trigger('click')
      expect(wrapper.get('[data-testid="agenda-day-title"]').text()).toBe('Mercredi 18 novembre')
      await wrapper.get('[data-testid="agenda-day-close"]').trigger('click')
      await flushPromises()

      const blocked = rows.find((row) => row.text().includes('Indisponible'))!
      await blocked.trigger('click')
      expect(wrapper.get('[data-testid="agenda-day-title"]').text()).toBe('Jeudi 12 novembre')
      expect(wrapper.find('[data-testid="agenda-day-unblock"]').exists()).toBe(true)
    })
  })

  describe('version ordinateur', () => {
    it('mémorise la bascule de vue et la restaure au montage suivant', async () => {
      const wrapper = await mountPage()
      expect(wrapper.find('[data-testid="month-grid-large"]').exists()).toBe(true)

      await wrapper.get('[data-testid="agenda-view-list"]').trigger('click')
      await flushPromises()
      expect(wrapper.find('[data-testid="agenda-list-view"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="month-grid-large"]').exists()).toBe(false)
      expect(localStorage.getItem('weact:agenda:view')).toBe('list')

      const second = await mountPage()
      expect(second.find('[data-testid="agenda-list-view"]').exists()).toBe(true)
    })

    it('affiche la semaine en vue Semaine et navigue de semaine en semaine', async () => {
      const wrapper = await mountPage()
      await wrapper.get('[data-testid="agenda-view-week"]').trigger('click')
      await flushPromises()

      // Aujourd'hui est le dimanche 8 novembre : semaine du lundi 2 au dimanche 8.
      expect(wrapper.get('[data-testid="agenda-toolbar-title"]').text()).toBe('Semaine du 2 au 8 novembre')
      expect(wrapper.find('[data-testid="week-view"]').exists()).toBe(true)

      await wrapper.get('[data-testid="agenda-toolbar-next"]').trigger('click')
      await flushPromises()
      expect(wrapper.get('[data-testid="agenda-toolbar-title"]').text()).toBe('Semaine du 9 au 15 novembre')
    })

    it('charge aussi le mois voisin pour une semaine à cheval sur deux mois', async () => {
      const wrapper = await mountPage()
      await wrapper.get('[data-testid="agenda-view-week"]').trigger('click')
      for (let i = 0; i < 4; i++) {
        await wrapper.get('[data-testid="agenda-toolbar-next"]').trigger('click')
        await flushPromises()
      }
      // Semaine du 30 novembre au 6 décembre : la plage chargée va jusqu'au 6 décembre.
      expect(wrapper.get('[data-testid="agenda-toolbar-title"]').text()).toBe('Semaine du 30 novembre au 6 décembre')
      expect(agendaApi.getAgenda).toHaveBeenLastCalledWith('2026-11-01', '2026-12-06')
    })

    it('navigue avec les flèches et revient avec T', async () => {
      const wrapper = await mountPage()
      window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))
      await flushPromises()
      expect(agendaApi.getAgenda).toHaveBeenLastCalledWith('2026-12-01', '2026-12-31')
      expect(wrapper.get('[data-testid="agenda-toolbar-title"]').text()).toBe('Décembre 2026')

      window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft' }))
      await flushPromises()
      window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft' }))
      await flushPromises()
      expect(wrapper.get('[data-testid="agenda-toolbar-title"]').text()).toBe('Octobre 2026')

      window.dispatchEvent(new KeyboardEvent('keydown', { key: 't' }))
      await flushPromises()
      expect(agendaApi.getAgenda).toHaveBeenLastCalledWith('2026-11-01', '2026-11-30')
      expect(wrapper.get('[data-testid="agenda-toolbar-title"]').text()).toBe('Novembre 2026')
    })

    it('ignore les raccourcis clavier dans un champ de saisie', async () => {
      await mountPage()
      const input = document.createElement('input')
      document.body.appendChild(input)
      const calls = vi.mocked(agendaApi.getAgenda).mock.calls.length

      input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }))
      input.dispatchEvent(new KeyboardEvent('keydown', { key: 't', bubbles: true }))
      await flushPromises()
      input.remove()

      expect(vi.mocked(agendaApi.getAgenda).mock.calls).toHaveLength(calls)
    })

    it('remplit la colonne de droite au clic sur un jour, sans ouvrir le panneau du bas', async () => {
      const wrapper = await mountPage()
      // Par défaut : aujourd'hui.
      expect(wrapper.get('[data-testid="detail-title"]').text()).toBe('Dimanche 8 novembre')

      await wrapper.get(cell('2026-11-19')).trigger('click')
      expect(wrapper.get('[data-testid="detail-title"]').text()).toBe('Jeudi 19 novembre')
      const events = wrapper.get('[data-testid="detail-events"]')
      expect(events.text()).toContain('Clip Afro Vibes')
      expect(events.text()).toContain('avec Studio Lumière')
      expect(events.get('[data-testid="detail-event-link"]').text()).toBe('Voir le booking')
      expect(wrapper.find('[data-testid="agenda-day-sheet"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="agenda-detail-block"]').exists()).toBe(false)
    })

    it('bloque un jour depuis la colonne de droite (PUT add) en gardant le jour affiché', async () => {
      const wrapper = await mountPage()
      await wrapper.get(cell('2026-11-15')).trigger('click')
      await wrapper.get('[data-testid="agenda-detail-block"]').trigger('click')
      await flushPromises()

      expect(agendaApi.updateUnavailableDays).toHaveBeenCalledWith({ add: ['2026-11-15'] })
      expect(wrapper.get('[data-testid="detail-title"]').text()).toBe('Dimanche 15 novembre')
    })

    it('glisser sur le calendrier ouvre la barre « N jours sélectionnés » puis envoie PUT add', async () => {
      const wrapper = await mountPage()
      await wrapper.get(cell('2026-11-28')).trigger('mousedown', { button: 0 })
      await wrapper.get(cell('2026-11-30')).trigger('mouseenter')
      await wrapper.get(cell('2026-11-30')).trigger('mouseup')

      expect(wrapper.get('[data-testid="agenda-selected-count"]').text()).toBe('3 jours sélectionnés')
      expect(wrapper.get('[data-testid="agenda-selected-list"]').text()).toBe('Sam 28, dim 29 et lun 30 nov')

      await wrapper.get('[data-testid="agenda-selection-submit"]').trigger('click')
      await flushPromises()
      expect(agendaApi.updateUnavailableDays).toHaveBeenCalledWith({ add: ['2026-11-28', '2026-11-29', '2026-11-30'] })
      expect(wrapper.find('[data-testid="agenda-selection-bar"]').exists()).toBe(false)
    })

    it('le bouton de la colonne gauche et Maj + clic entrent dans le mode sélection', async () => {
      const wrapper = await mountPage()
      await wrapper.get('[data-testid="agenda-block-days-lg"]').trigger('click')
      expect(wrapper.find('[data-testid="agenda-selection-bar"]').exists()).toBe(true)
      await wrapper.get('[data-testid="agenda-selection-exit"]').trigger('click')

      await wrapper.get(cell('2026-11-10')).trigger('click', { shiftKey: true })
      expect(wrapper.get('[data-testid="agenda-selected-count"]').text()).toBe('1 jour sélectionné')
      // Du 10 au 14 : les 12 et 13 sont bloqués, ils sont sautés.
      await wrapper.get(cell('2026-11-14')).trigger('click', { shiftKey: true })
      expect(wrapper.get('[data-testid="agenda-selected-count"]').text()).toBe('3 jours sélectionnés')
    })

    it('ne montre plus aucune barre d\'accent dans « À venir » du téléphone : une pastille ronde', async () => {
      const wrapper = await mountPage()
      const item = wrapper.get('[data-testid="agenda-upcoming-item"]')
      expect(item.find('span.w-1').exists()).toBe(false)
      const dot = item.get('span.size-2.rounded-full')
      expect(dot.classes()).toContain('bg-teal-600')
    })
  })
})
