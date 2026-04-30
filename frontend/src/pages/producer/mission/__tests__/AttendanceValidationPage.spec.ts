import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { AxiosError, AxiosHeaders } from 'axios'
import AttendanceValidationPage from '../AttendanceValidationPage.vue'
import { missionApi } from '@/features/mission/services/missionApi'
import type { AttendanceFormResponse, ValidateAttendanceResponse } from '@/features/mission/types/attendance'

const MISSION_UUID = 'mission-uuid-under-test'

const toastSuccessSpy = vi.fn()
const toastErrorSpy = vi.fn()
const routerPushSpy = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: MISSION_UUID } }),
  useRouter: () => ({ push: routerPushSpy }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: toastSuccessSpy,
    error: toastErrorSpy,
    warning: vi.fn(),
    info: vi.fn(),
    clear: vi.fn(),
    toast: {},
  }),
}))

vi.mock('@/components/ui/skeleton', () => ({
  Skeleton: defineComponent({
    name: 'SkeletonStub',
    setup() {
      return () => h('div', { 'data-testid': 'skeleton-stub' })
    },
  }),
}))

vi.mock('@/features/mission/services/missionApi', () => ({
  missionApi: {
    getAttendanceForm: vi.fn(),
    validateAttendance: vi.fn(),
  },
}))

vi.mock('@/features/mission/components/ValidateAttendanceDialog.vue', () => ({
  default: defineComponent({
    name: 'ValidateAttendanceDialogStub',
    props: {
      isOpen: { type: Boolean, required: true },
      missionTitle: { type: String, required: true },
      presentCount: { type: Number, required: true },
      absentCount: { type: Number, required: true },
      totalReleased: { type: Number, required: true },
      totalRefunded: { type: Number, required: true },
      isLoading: { type: Boolean, required: false, default: false },
    },
    emits: ['cancel', 'confirm'],
    setup(props, { emit }) {
      return () =>
        h('div', { 'data-testid': 'validate-attendance-dialog-stub', 'data-open': String(props.isOpen) }, [
          h('button', { 'data-testid': 'dialog-confirm', onClick: () => emit('confirm') }, 'confirm'),
          h('button', { 'data-testid': 'dialog-cancel', onClick: () => emit('cancel') }, 'cancel'),
        ])
    },
  }),
}))

function makeAttendanceForm(overrides: Partial<AttendanceFormResponse['data']['entries'][number]> = {}): AttendanceFormResponse {
  return {
    data: {
      mission: {
        id: MISSION_UUID,
        titre: 'Tournage Spot TV',
        status: 'closed',
        status_label: 'Clôturée',
        date_tournage: '2026-05-01',
      },
      payment: { montant_total_producteur: 200000, nombre_faces_retenues: 2 },
      entries: [
        {
          id: 1,
          face: { id: 'face-1', display_name: 'Face A', profile_photo_url: null },
          montant_face_recoit: 90000,
          attendance_status: 'pending',
          attendance_status_label: 'En attente',
          escrow_status: 'locked',
          escrow_status_label: 'Verrouillé',
          released_at: null,
          refunded_at: null,
          notified_at: null,
          ...overrides,
        },
        {
          id: 2,
          face: { id: 'face-2', display_name: 'Face B', profile_photo_url: null },
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

function makeMixedAttendanceForm(): AttendanceFormResponse {
  return {
    data: {
      mission: {
        id: MISSION_UUID,
        titre: 'Tournage Mixed',
        status: 'pending_attendance_validation',
        status_label: 'En attente de validation des présences',
        date_tournage: '2026-05-01',
      },
      payment: { montant_total_producteur: 270000, nombre_faces_retenues: 3 },
      entries: [
        {
          id: 1,
          face: { id: 'face-1', display_name: 'Face A', profile_photo_url: null },
          montant_face_recoit: 90000,
          attendance_status: 'present',
          attendance_status_label: 'Présente',
          escrow_status: 'released',
          escrow_status_label: 'Versé',
          released_at: '2026-04-29T10:00:00Z',
          refunded_at: null,
          notified_at: null,
        },
        {
          id: 2,
          face: { id: 'face-2', display_name: 'Face B', profile_photo_url: null },
          montant_face_recoit: 90000,
          attendance_status: 'absent',
          attendance_status_label: 'Absente',
          escrow_status: 'locked',
          escrow_status_label: 'Verrouillé',
          released_at: null,
          refunded_at: null,
          notified_at: '2026-04-29T11:00:00Z',
        },
        {
          id: 3,
          face: { id: 'face-3', display_name: 'Face C', profile_photo_url: null },
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

function makeValidateResponse(): ValidateAttendanceResponse {
  return {
    data: {
      mission: {
        id: MISSION_UUID,
        titre: 'Tournage Spot TV',
        status: 'completed',
        status_label: 'Terminée',
        date_tournage: '2026-05-01',
      },
      entries: makeAttendanceForm().data.entries,
    },
    message: 'Présences validées avec succès.',
  }
}

function makeAxiosError(status: number, body: unknown = {}): AxiosError {
  const headers = new AxiosHeaders()
  return new AxiosError(
    `Request failed with status code ${status}`,
    String(status),
    { headers, url: '/x' } as never,
    null,
    {
      data: body,
      status,
      statusText: '',
      headers: {},
      config: { headers, url: '/x' } as never,
    },
  )
}

describe('AttendanceValidationPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('shows the skeleton while fetching the form and removes it after resolve', async () => {
    let resolveFn: (value: AttendanceFormResponse) => void = () => undefined
    vi.mocked(missionApi.getAttendanceForm).mockImplementationOnce(
      () => new Promise<AttendanceFormResponse>((resolve) => {
        resolveFn = resolve
      }),
    )

    const wrapper = mount(AttendanceValidationPage, { attachTo: document.body })
    await nextTick()

    expect(wrapper.find('[data-testid="skeleton-stub"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="attendance-entry-1"]').exists()).toBe(false)

    resolveFn(makeAttendanceForm())
    await flushPromises()

    expect(wrapper.find('[data-testid="skeleton-stub"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="attendance-entry-1"]').exists()).toBe(true)
  })

  it('renders the form with entries after fetch resolves', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValueOnce(makeAttendanceForm())

    const wrapper = mount(AttendanceValidationPage, { attachTo: document.body })
    await flushPromises()

    expect(wrapper.text()).toContain('Tournage Spot TV')
    expect(wrapper.find('[data-testid="attendance-entry-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="attendance-entry-2"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="attendance-submit-button"]').attributes('disabled')).toBeDefined()
  })

  it('clicks "Toutes présentes" sets all editable decisions to present', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValueOnce(makeAttendanceForm())

    const wrapper = mount(AttendanceValidationPage, { attachTo: document.body })
    await flushPromises()

    await wrapper.find('[data-testid="mark-all-present-button"]').trigger('click')

    const radio1 = wrapper.find<HTMLInputElement>('[data-testid="decision-1-present"]').element
    const radio2 = wrapper.find<HTMLInputElement>('[data-testid="decision-2-present"]').element
    expect(radio1.checked).toBe(true)
    expect(radio2.checked).toBe(true)

    expect(wrapper.find('[data-testid="attendance-submit-button"]').attributes('disabled')).toBeUndefined()
    expect(wrapper.text()).toMatch(/180\s000\s+XOF/)
  })

  it('"Toutes présentes" overrides previously-marked absent decisions for editable entries', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValueOnce(makeAttendanceForm())

    const wrapper = mount(AttendanceValidationPage, { attachTo: document.body })
    await flushPromises()

    await wrapper.find('[data-testid="decision-1-absent"]').setValue(true)
    expect(wrapper.find<HTMLInputElement>('[data-testid="decision-1-absent"]').element.checked).toBe(true)

    await wrapper.find('[data-testid="mark-all-present-button"]').trigger('click')

    expect(wrapper.find<HTMLInputElement>('[data-testid="decision-1-present"]').element.checked).toBe(true)
    expect(wrapper.find<HTMLInputElement>('[data-testid="decision-1-absent"]').element.checked).toBe(false)
  })

  it('keeps submit disabled until every editable entry is decided', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValueOnce(makeAttendanceForm())

    const wrapper = mount(AttendanceValidationPage, { attachTo: document.body })
    await flushPromises()

    await wrapper.find('[data-testid="decision-1-present"]').setValue(true)
    expect(wrapper.find('[data-testid="attendance-submit-button"]').attributes('disabled')).toBeDefined()

    await wrapper.find('[data-testid="decision-2-absent"]').setValue(true)
    expect(wrapper.find('[data-testid="attendance-submit-button"]').attributes('disabled')).toBeUndefined()
  })

  it('submits successfully and redirects to producer-missions with a success toast', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValueOnce(makeAttendanceForm())
    vi.mocked(missionApi.validateAttendance).mockResolvedValueOnce(makeValidateResponse())

    const wrapper = mount(AttendanceValidationPage, { attachTo: document.body })
    await flushPromises()

    await wrapper.find('[data-testid="decision-1-present"]').setValue(true)
    await wrapper.find('[data-testid="decision-2-absent"]').setValue(true)

    await wrapper.find('[data-testid="attendance-submit-button"]').trigger('click')
    await wrapper.find('[data-testid="dialog-confirm"]').trigger('click')
    await flushPromises()

    expect(missionApi.validateAttendance).toHaveBeenCalledWith(MISSION_UUID, {
      entries: [
        { entry_id: 1, status: 'present' },
        { entry_id: 2, status: 'absent' },
      ],
    })
    expect(toastSuccessSpy).toHaveBeenCalledTimes(1)
    expect(toastSuccessSpy.mock.calls[0]?.[0]).toMatch(/Présences validées avec succès/)
    expect(routerPushSpy).toHaveBeenCalledWith({ name: 'producer-missions' })
  })

  it('on submit 422 surfaces fieldErrors banner and stays on page', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValueOnce(makeAttendanceForm())
    vi.mocked(missionApi.validateAttendance).mockRejectedValueOnce(
      makeAxiosError(422, {
        error: {
          message: 'Validation failed',
          details: { 'entries.0.entry_id': ['Cet identifiant est invalide.'] },
        },
      }),
    )

    const wrapper = mount(AttendanceValidationPage, { attachTo: document.body })
    await flushPromises()

    await wrapper.find('[data-testid="decision-1-present"]').setValue(true)
    await wrapper.find('[data-testid="decision-2-absent"]').setValue(true)

    await wrapper.find('[data-testid="attendance-submit-button"]').trigger('click')
    await wrapper.find('[data-testid="dialog-confirm"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="attendance-field-errors"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="attendance-field-errors"]').text()).toContain('entries.0.entry_id')
    expect(toastErrorSpy).toHaveBeenCalledTimes(1)
    expect(routerPushSpy).not.toHaveBeenCalled()
  })

  it('on submit 403 redirects to producer-missions with an error toast', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValueOnce(makeAttendanceForm())
    vi.mocked(missionApi.validateAttendance).mockRejectedValueOnce(
      makeAxiosError(403, { error: { message: "Cette action n’est pas autorisée." } }),
    )

    const wrapper = mount(AttendanceValidationPage, { attachTo: document.body })
    await flushPromises()

    await wrapper.find('[data-testid="decision-1-present"]').setValue(true)
    await wrapper.find('[data-testid="decision-2-absent"]').setValue(true)

    await wrapper.find('[data-testid="attendance-submit-button"]').trigger('click')
    await wrapper.find('[data-testid="dialog-confirm"]').trigger('click')
    await flushPromises()

    expect(toastErrorSpy).toHaveBeenCalledTimes(1)
    expect(routerPushSpy).toHaveBeenCalledWith({ name: 'producer-missions' })
  })

  it('on initial fetch 403 toasts error and redirects', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockRejectedValueOnce(
      makeAxiosError(403, { error: { message: "Cette action n’est pas autorisée." } }),
    )

    mount(AttendanceValidationPage, { attachTo: document.body })
    await flushPromises()

    expect(toastErrorSpy).toHaveBeenCalledTimes(1)
    expect(routerPushSpy).toHaveBeenCalledWith({ name: 'producer-missions' })
  })

  it('on submit 422 with no fieldErrors falls back to the global error message toast', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValueOnce(makeAttendanceForm())
    vi.mocked(missionApi.validateAttendance).mockRejectedValueOnce(
      makeAxiosError(422, { error: { message: 'Mission no longer eligible.' } }),
    )

    const wrapper = mount(AttendanceValidationPage, { attachTo: document.body })
    await flushPromises()

    await wrapper.find('[data-testid="decision-1-present"]').setValue(true)
    await wrapper.find('[data-testid="decision-2-absent"]').setValue(true)

    await wrapper.find('[data-testid="attendance-submit-button"]').trigger('click')
    await wrapper.find('[data-testid="dialog-confirm"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="attendance-field-errors"]').exists()).toBe(false)
    expect(toastErrorSpy).toHaveBeenCalledTimes(1)
    expect(toastErrorSpy.mock.calls[0]?.[0]).toContain('Mission no longer eligible.')
    expect(routerPushSpy).not.toHaveBeenCalled()
  })

  it('hydrates pre-tranched present/absent entries as read-only and submits only pending decisions', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValueOnce(makeMixedAttendanceForm())
    vi.mocked(missionApi.validateAttendance).mockResolvedValueOnce(makeValidateResponse())

    const wrapper = mount(AttendanceValidationPage, { attachTo: document.body })
    await flushPromises()

    const r1Present = wrapper.find<HTMLInputElement>('[data-testid="decision-1-present"]').element
    expect(r1Present.checked).toBe(true)
    expect(r1Present.disabled).toBe(true)

    const r2Absent = wrapper.find<HTMLInputElement>('[data-testid="decision-2-absent"]').element
    expect(r2Absent.checked).toBe(true)
    expect(r2Absent.disabled).toBe(true)
    expect(wrapper.text()).toMatch(/Notifiée le.*fenêtre contestation 72h/)

    await wrapper.find('[data-testid="decision-3-present"]').setValue(true)
    await wrapper.find('[data-testid="attendance-submit-button"]').trigger('click')
    await wrapper.find('[data-testid="dialog-confirm"]').trigger('click')
    await flushPromises()

    expect(missionApi.validateAttendance).toHaveBeenCalledWith(MISSION_UUID, {
      entries: [{ entry_id: 3, status: 'present' }],
    })
  })

  it('hydrates disputed entries as absent read-only with audit-trail mention', async () => {
    const form = makeMixedAttendanceForm()
    form.data.entries[1]!.attendance_status = 'disputed'
    form.data.entries[1]!.attendance_status_label = 'Contestée'
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValueOnce(form)

    const wrapper = mount(AttendanceValidationPage, { attachTo: document.body })
    await flushPromises()

    const r2Absent = wrapper.find<HTMLInputElement>('[data-testid="decision-2-absent"]').element
    expect(r2Absent.checked).toBe(true)
    expect(r2Absent.disabled).toBe(true)
    expect(wrapper.text()).toMatch(/Notifiée le.*fenêtre contestation 72h/)
  })

  it('dialog and toast counts/amounts only reflect this-action entries (page recap stays cumul)', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockResolvedValueOnce(makeMixedAttendanceForm())
    vi.mocked(missionApi.validateAttendance).mockResolvedValueOnce(makeValidateResponse())

    const wrapper = mount(AttendanceValidationPage, { attachTo: document.body })
    await flushPromises()

    await wrapper.find('[data-testid="decision-3-present"]').setValue(true)
    await wrapper.find('[data-testid="attendance-submit-button"]').trigger('click')

    const dialogStub = wrapper.findComponent({ name: 'ValidateAttendanceDialogStub' })
    expect(dialogStub.props('presentCount')).toBe(1)
    expect(dialogStub.props('absentCount')).toBe(0)
    expect(dialogStub.props('totalReleased')).toBe(90000)
    expect(dialogStub.props('totalRefunded')).toBe(0)

    const recapText = wrapper.find('[data-testid="attendance-recap"]').text()
    expect(recapText).toContain('2 Face(s) présente(s)')
    expect(recapText).toContain('1 Face(s) absente(s)')

    await wrapper.find('[data-testid="dialog-confirm"]').trigger('click')
    await flushPromises()

    // When zero this-action absent, the toast omits the "remboursement(s) en cours"
    // sentence rather than printing "0 remboursement(s)" (cf. v2-P3 patch).
    expect(toastSuccessSpy.mock.calls[0]?.[0]).toContain('1 Face(s) créditée(s)')
    expect(toastSuccessSpy.mock.calls[0]?.[0]).not.toContain('remboursement(s) en cours')
  })

  it('on initial fetch 422 (status non éligible) shows banner and does NOT redirect or toast', async () => {
    vi.mocked(missionApi.getAttendanceForm).mockRejectedValueOnce(
      makeAxiosError(422, {
        error: {
          message: 'La validation des présences n’est pas possible pour cette mission.',
          details: { status: ['La validation des présences n’est pas possible pour cette mission.'] },
        },
      }),
    )

    const wrapper = mount(AttendanceValidationPage, { attachTo: document.body })
    await flushPromises()

    expect(routerPushSpy).not.toHaveBeenCalled()
    // Banner-only contract: 422 must surface via the inline error banner, never via toast.
    expect(toastErrorSpy).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Cette mission ne peut pas être validée pour le moment.')
  })
})
