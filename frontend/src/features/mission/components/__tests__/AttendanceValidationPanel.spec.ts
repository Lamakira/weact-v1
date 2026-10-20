import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import AttendanceValidationPanel from '../AttendanceValidationPanel.vue'
import { missionApi } from '@/features/mission/services/missionApi'
import type { AttendanceFormResponse } from '@/features/mission/types/attendance'

const toastSuccessSpy = vi.fn()
const toastErrorSpy = vi.fn()

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: toastSuccessSpy, error: toastErrorSpy, warning: vi.fn(), info: vi.fn(), clear: vi.fn(), toast: {} }),
}))

vi.mock('@/features/mission/services/missionApi', () => ({
  missionApi: { getAttendanceForm: vi.fn(), validateAttendance: vi.fn() },
}))

vi.mock('@/features/mission/components/ValidateAttendanceDialog.vue', () => ({
  default: defineComponent({
    name: 'ValidateAttendanceDialogStub',
    props: { isOpen: { type: Boolean, required: true } },
    emits: ['cancel', 'confirm'],
    setup: (_props, { emit }) => () => h('button', { 'data-testid': 'dialog-confirm', onClick: () => emit('confirm') }, 'confirm'),
  }),
}))

function makeForm(overrides: Partial<AttendanceFormResponse['data']['mission']> = {}): AttendanceFormResponse {
  return {
    data: {
      mission: {
        id: 'mission-uuid',
        titre: 'Tournage Spot TV',
        status: 'closed',
        status_label: 'Clôturée',
        date_tournage: '2026-10-23',
        ...overrides,
      },
      payment: { montant_total_producteur: 200000, nombre_faces_retenues: 1 },
      entries: [
        {
          id: 1,
          face: { id: 'face-1', display_name: 'Face A', profile_photo_url: null, profile_photo_thumbnail_url: null },
          montant_face_recoit: 90000,
          attendance_status: 'pending',
          attendance_status_label: 'En attente',
          escrow_status: 'locked',
          escrow_status_label: 'Verrouillé',
          released_at: null,
          refunded_at: null,
          notified_at: null,
        },
      ],
    },
  }
}

let wrapper: VueWrapper | null = null

beforeEach(() => {
  vi.clearAllMocks()
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
})

describe('AttendanceValidationPanel — usage en onglet (non standalone)', () => {
  it('n’affiche ni le titre de la mission ni le bouton de retour ; charge le formulaire de la mission', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValue(makeForm())
    wrapper = mount(AttendanceValidationPanel, { props: { missionUuid: 'mission-uuid' } })
    await flushPromises()

    expect(missionApi.getAttendanceForm).toHaveBeenCalledWith('mission-uuid')
    expect(wrapper.find('h1').exists()).toBe(false)
    expect(wrapper.find('[data-testid="attendance-entry-1"]').exists()).toBe(true)
  })

  it('avant le jour du tournage, affiche la raison de blocage fournie par le backend', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValue(
      makeForm({
        can_mark_attendance: false,
        attendance_blocked_reason: 'Les présences se valident à partir du jeudi 23 octobre.',
      }),
    )
    wrapper = mount(AttendanceValidationPanel, { props: { missionUuid: 'mission-uuid' } })
    await flushPromises()

    expect(wrapper.get('[data-testid="attendance-blocked-reason"]').text()).toBe(
      'Les présences se valident à partir du jeudi 23 octobre.',
    )
    expect((wrapper.get('[data-testid="decision-1-present"]').element as HTMLInputElement).disabled).toBe(true)
  })

  it('après validation : toast, événement done et rechargement du formulaire (sans navigation)', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValue(makeForm())
    vi.mocked(missionApi.validateAttendance).mockResolvedValue({ data: {}, message: 'ok' } as never)
    wrapper = mount(AttendanceValidationPanel, { props: { missionUuid: 'mission-uuid' } })
    await flushPromises()

    await wrapper.get('[data-testid="decision-1-present"]').setValue(true)
    await wrapper.get('[data-testid="attendance-submit-button"]').trigger('click')
    await wrapper.get('[data-testid="dialog-confirm"]').trigger('click')
    await flushPromises()

    expect(toastSuccessSpy).toHaveBeenCalled()
    expect(wrapper.emitted('done')).toHaveLength(1)
    expect(missionApi.getAttendanceForm).toHaveBeenCalledTimes(2)
  })

  it('chargement impossible : toast et événement failed', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockRejectedValue(new Error('boom'))
    wrapper = mount(AttendanceValidationPanel, { props: { missionUuid: 'mission-uuid' } })
    await flushPromises()

    expect(toastErrorSpy).toHaveBeenCalled()
    expect(wrapper.emitted('failed')).toHaveLength(1)
  })
})

describe('AttendanceValidationPanel — standalone (ancienne page)', () => {
  it('affiche le titre de la mission', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValue(makeForm())
    wrapper = mount(AttendanceValidationPanel, { props: { missionUuid: 'mission-uuid', standalone: true } })
    await flushPromises()

    expect(wrapper.get('h1').text()).toBe('Tournage Spot TV')
  })
})
