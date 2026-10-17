import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
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

// ConfirmModal est téléportée : on la remplace par un stub rendu sur place.
const ConfirmModalStub = {
  name: 'ConfirmModal',
  props: ['isOpen', 'title', 'message', 'confirmText', 'cancelText', 'variant', 'confirmDisabled'],
  emits: ['confirm', 'cancel'],
  template: `<div v-if="isOpen" data-testid="modal">
    <p data-testid="modal-title">{{ title }}</p>
    <button data-testid="modal-confirm" :disabled="confirmDisabled" @click="$emit('confirm')">{{ confirmText }}</button>
    <button data-testid="modal-cancel" @click="$emit('cancel')">{{ cancelText }}</button>
  </div>`,
}

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
    global: { stubs: { ConfirmModal: ConfirmModalStub } },
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

  it('opens the confirmation dialog without calling the API', async () => {
    const wrapper = mountBlock()

    await wrapper.find('[data-testid="absence-contest-btn"]').trigger('click')

    expect(wrapper.find('[data-testid="modal-title"]').text()).toBe('Contester cette absence ?')
    expect(candidatureApi.contestMissionAbsence).not.toHaveBeenCalled()
  })

  it('calls the endpoint on confirm, then shows the sent state and emits contested', async () => {
    vi.mocked(candidatureApi.contestMissionAbsence).mockResolvedValue({ message: 'ok' })
    const wrapper = mountBlock()

    await wrapper.find('[data-testid="absence-contest-btn"]').trigger('click')
    await wrapper.find('[data-testid="modal-confirm"]').trigger('click')
    await flushPromises()

    expect(candidatureApi.contestMissionAbsence).toHaveBeenCalledWith('mission-uuid-1')
    expect(wrapper.find('[data-testid="absence-contested"]').text())
      .toContain('Contestation envoyée — un administrateur va trancher')
    expect(wrapper.find('[data-testid="absence-contest-btn"]').exists()).toBe(false)
    expect(wrapper.emitted('contested')).toHaveLength(1)
    expect(toastMock.success).toHaveBeenCalled()
  })

  it('keeps the contestable state and toasts an error when the API fails', async () => {
    vi.mocked(candidatureApi.contestMissionAbsence).mockRejectedValue(new Error('boom'))
    const wrapper = mountBlock()

    await wrapper.find('[data-testid="absence-contest-btn"]').trigger('click')
    await wrapper.find('[data-testid="modal-confirm"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="absence-contestable"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="absence-contested"]').exists()).toBe(false)
    expect(wrapper.emitted('contested')).toBeUndefined()
    expect(toastMock.error).toHaveBeenCalled()
  })

  it('renders the sent state directly when already contested, without a button', () => {
    const wrapper = mountBlock({ state: 'contested', can_contest: false })

    expect(wrapper.find('[data-testid="absence-contested"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="absence-contest-btn"]').exists()).toBe(false)
  })
})
