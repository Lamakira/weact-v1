import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { AxiosError, type AxiosResponse } from 'axios'
import MissionAbsenceContestBlock from '../MissionAbsenceContestBlock.vue'
import { candidatureApi } from '../../services/candidatureApi'
import type { AttendanceDispute } from '../../types'

vi.mock('../../services/candidatureApi', () => ({
  candidatureApi: {
    contestMissionAbsence: vi.fn(),
  },
}))

const toastMock = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }))
vi.mock('@/composables/useToast', () => ({ useToast: () => toastMock }))

function mountBlock(dispute: Partial<AttendanceDispute> = {}) {
  return mount(MissionAbsenceContestBlock, {
    props: {
      missionId: 'mission-uuid-1',
      dispute: {
        state: 'contestable',
        deadline: '2026-10-12T08:00:00+00:00',
        can_contest: true,
        ...dispute,
      },
    },
    global: { stubs: { teleport: true } },
  })
}

describe('MissionAbsenceContestBlock', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows the absence state with the formatted deadline and a Contester button', () => {
    const wrapper = mountBlock()

    const block = wrapper.find('[data-testid="absence-contestable"]')
    expect(block.exists()).toBe(true)
    expect(block.text()).toContain('Le Producteur vous a déclarée absente')
    expect(block.text()).toContain('vous avez jusqu\'au')
    // La date formatée est interpolée (mois long fr-FR, pas l'ISO brut).
    expect(block.text()).toContain('octobre')
    expect(block.text()).toContain('2026')
    expect(wrapper.find('[data-testid="absence-contest-btn"]').text()).toContain('Contester')
  })

  const VALID_MESSAGE = 'J\'étais sur place à 14h, le Producteur était absent.'

  async function openDialog(wrapper: ReturnType<typeof mountBlock>) {
    await wrapper.find('[data-testid="absence-contest-btn"]').trigger('click')
  }

  it('opens the dialog with a message textarea and a 0/1000 counter, without calling the API', async () => {
    const wrapper = mountBlock()

    await openDialog(wrapper)

    expect(wrapper.find('[data-testid="absence-contest-dialog"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Expliquez ce qui s\'est passé')
    expect(wrapper.find('[data-testid="absence-contest-counter"]').text()).toBe('0/1000')
    expect(candidatureApi.contestMissionAbsence).not.toHaveBeenCalled()
  })

  it('keeps submit disabled until the message has at least 10 characters and updates the counter', async () => {
    const wrapper = mountBlock()
    await openDialog(wrapper)
    // Le stub de Teleport re-rend son contenu : on ré-interroge le DOM à chaque étape.
    const submit = () => wrapper.find('[data-testid="absence-contest-submit"]')
    const textarea = () => wrapper.find('[data-testid="absence-contest-message"]')

    expect(submit().attributes('disabled')).toBeDefined()

    await textarea().setValue('court')
    expect(wrapper.find('[data-testid="absence-contest-counter"]').text()).toBe('5/1000')
    expect(submit().attributes('disabled')).toBeDefined()

    await textarea().setValue('   court    ') // 5 caractères utiles seulement
    expect(submit().attributes('disabled')).toBeDefined()

    await textarea().setValue(VALID_MESSAGE)
    expect(wrapper.find('[data-testid="absence-contest-counter"]').text()).toBe(`${VALID_MESSAGE.length}/1000`)
    expect(submit().attributes('disabled')).toBeUndefined()
  })

  it('sends the trimmed message, then shows the sent state and emits contested', async () => {
    vi.mocked(candidatureApi.contestMissionAbsence).mockResolvedValue({ message: 'ok' })
    const wrapper = mountBlock()

    await openDialog(wrapper)
    await wrapper.find('[data-testid="absence-contest-message"]').setValue(`  ${VALID_MESSAGE}  `)
    await wrapper.find('[data-testid="absence-contest-submit"]').trigger('click')
    await flushPromises()

    expect(candidatureApi.contestMissionAbsence).toHaveBeenCalledWith('mission-uuid-1', VALID_MESSAGE)
    expect(wrapper.find('[data-testid="absence-contested"]').text())
      .toContain('Contestation envoyée — un administrateur va trancher')
    expect(wrapper.find('[data-testid="absence-contest-btn"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="absence-contest-dialog"]').exists()).toBe(false)
    expect(wrapper.emitted('contested')).toHaveLength(1)
    expect(toastMock.success).toHaveBeenCalled()
  })

  it('keeps the dialog open and displays the server validation error when the API fails', async () => {
    vi.mocked(candidatureApi.contestMissionAbsence).mockRejectedValue(
      new AxiosError('Request failed', AxiosError.ERR_BAD_REQUEST, undefined, undefined, {
        status: 422,
        data: {
          error: {
            code: 'VALIDATION_ERROR',
            message: 'Les données fournies ne sont pas valides',
            details: { attendance: ['Le délai de contestation (72h) est dépassé.'] },
          },
        },
        statusText: '',
        headers: {},
        config: {} as never,
      } as AxiosResponse),
    )
    const wrapper = mountBlock()

    await openDialog(wrapper)
    await wrapper.find('[data-testid="absence-contest-message"]').setValue(VALID_MESSAGE)
    await wrapper.find('[data-testid="absence-contest-submit"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="absence-contest-dialog"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="absence-contest-error"]').text())
      .toBe('Le délai de contestation (72h) est dépassé.')
    expect(wrapper.find('[data-testid="absence-contest-message"]').element).toHaveProperty('value', VALID_MESSAGE)
    expect(wrapper.find('[data-testid="absence-contested"]').exists()).toBe(false)
    expect(wrapper.emitted('contested')).toBeUndefined()
  })

  it('closes the dialog on Retour without calling the API', async () => {
    const wrapper = mountBlock()

    await openDialog(wrapper)
    await wrapper.find('[data-testid="absence-contest-cancel"]').trigger('click')

    expect(wrapper.find('[data-testid="absence-contest-dialog"]').exists()).toBe(false)
    expect(candidatureApi.contestMissionAbsence).not.toHaveBeenCalled()
  })

  it('renders the sent state directly when already contested, without a button', () => {
    const wrapper = mountBlock({ state: 'contested', can_contest: false })

    expect(wrapper.find('[data-testid="absence-contested"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="absence-contest-btn"]').exists()).toBe(false)
  })
})
