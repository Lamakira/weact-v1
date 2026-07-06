import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AxiosError, AxiosHeaders } from 'axios'
import { useValidateAttendance } from '../useValidateAttendance'
import { missionApi } from '../../services/missionApi'
import type {
  AttendanceFormResponse,
  ValidateAttendancePayload,
  ValidateAttendanceResponse,
} from '../../types/attendance'

vi.mock('../../services/missionApi', () => ({
  missionApi: {
    getAttendanceForm: vi.fn(),
    validateAttendance: vi.fn(),
  },
}))

const MISSION_UUID = 'mission-uuid-under-test'

function makeAttendanceForm(): AttendanceFormResponse {
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
        {
          id: 2,
          face: { id: 'face-2', display_name: 'Face B', profile_photo_url: null, profile_photo_thumbnail_url: null },
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
  // AxiosError needs a real config; AxiosHeaders + minimal config keep it valid.
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

const PAYLOAD: ValidateAttendancePayload = {
  entries: [
    { entry_id: 1, status: 'present' },
    { entry_id: 2, status: 'absent' },
  ],
}

describe('useValidateAttendance', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('fetchForm', () => {
    it('toggles isLoading and resolves with success + status:null on 200', async () => {
      vi.mocked(missionApi.getAttendanceForm).mockResolvedValueOnce(makeAttendanceForm())

      const { isLoading, fetchForm, data } = useValidateAttendance()
      const promise = fetchForm(MISSION_UUID)

      expect(isLoading.value).toBe(true)

      const result = await promise

      expect(isLoading.value).toBe(false)
      expect(result.success).toBe(true)
      expect(result.status).toBeNull()
      expect(result.data?.entries).toHaveLength(2)
      expect(data.value?.mission.id).toBe(MISSION_UUID)
      expect(missionApi.getAttendanceForm).toHaveBeenCalledTimes(1)
      expect(missionApi.getAttendanceForm).toHaveBeenCalledWith(MISSION_UUID)
    })

    it('captures status 403 and returns success:false with httpStatus.value === 403', async () => {
      vi.mocked(missionApi.getAttendanceForm).mockRejectedValueOnce(
        makeAxiosError(403, { error: { message: "Cette action n’est pas autorisée." } }),
      )

      const { fetchForm, httpStatus, error } = useValidateAttendance()
      const result = await fetchForm(MISSION_UUID)

      expect(result.success).toBe(false)
      expect(result.status).toBe(403)
      expect(httpStatus.value).toBe(403)
      expect(error.value).toBeTruthy()
    })

    it('captures status 404 with httpStatus.value === 404', async () => {
      vi.mocked(missionApi.getAttendanceForm).mockRejectedValueOnce(
        makeAxiosError(404, { error: { message: 'Mission introuvable.' } }),
      )

      const { fetchForm, httpStatus } = useValidateAttendance()
      const result = await fetchForm(MISSION_UUID)

      expect(result.success).toBe(false)
      expect(result.status).toBe(404)
      expect(httpStatus.value).toBe(404)
    })

    it('captures status 422 and fills fieldErrors', async () => {
      vi.mocked(missionApi.getAttendanceForm).mockRejectedValueOnce(
        makeAxiosError(422, {
          error: {
            message: 'Validation failed',
            details: { status: ['La validation des présences n’est pas possible.'] },
          },
        }),
      )

      const { fetchForm, httpStatus, fieldErrors } = useValidateAttendance()
      const result = await fetchForm(MISSION_UUID)

      expect(result.success).toBe(false)
      expect(result.status).toBe(422)
      expect(httpStatus.value).toBe(422)
      expect(fieldErrors.value).toEqual({
        status: ['La validation des présences n’est pas possible.'],
      })
    })

    it('resets error/fieldErrors/httpStatus/data at the start of every call (success after a prior 422)', async () => {
      vi.mocked(missionApi.getAttendanceForm)
        .mockRejectedValueOnce(
          makeAxiosError(422, {
            error: {
              message: 'Mission no longer eligible.',
              details: { status: ['Mission no longer eligible.'] },
            },
          }),
        )
        .mockResolvedValueOnce(makeAttendanceForm())

      const { fetchForm, error, fieldErrors, httpStatus, data } = useValidateAttendance()

      const failed = await fetchForm(MISSION_UUID)
      expect(failed.success).toBe(false)
      expect(error.value).toBeTruthy()
      expect(fieldErrors.value).not.toEqual({})
      expect(httpStatus.value).toBe(422)
      expect(data.value).toBeNull()

      const ok = await fetchForm(MISSION_UUID)
      expect(ok.success).toBe(true)
      expect(error.value).toBeNull()
      expect(fieldErrors.value).toEqual({})
      expect(httpStatus.value).toBeNull()
      expect(data.value).not.toBeNull()
    })
  })

  describe('submitAttendance', () => {
    it('returns success:true with response data and status:null on 200', async () => {
      vi.mocked(missionApi.validateAttendance).mockResolvedValueOnce(makeValidateResponse())

      const { submitAttendance, isSubmitting } = useValidateAttendance()
      const promise = submitAttendance(MISSION_UUID, PAYLOAD)

      expect(isSubmitting.value).toBe(true)

      const result = await promise

      expect(isSubmitting.value).toBe(false)
      expect(result.success).toBe(true)
      expect(result.status).toBeNull()
      expect(result.message).toBe('Présences validées avec succès.')
      expect(result.data?.mission.id).toBe(MISSION_UUID)
      expect(missionApi.validateAttendance).toHaveBeenCalledTimes(1)
      expect(missionApi.validateAttendance).toHaveBeenCalledWith(MISSION_UUID, PAYLOAD)
    })

    it('captures status 422 and fills fieldErrors', async () => {
      vi.mocked(missionApi.validateAttendance).mockRejectedValueOnce(
        makeAxiosError(422, {
          error: {
            message: 'Validation failed',
            details: { 'entries.0.entry_id': ['Cet identifiant est invalide.'] },
          },
        }),
      )

      const { submitAttendance, fieldErrors, httpStatus } = useValidateAttendance()
      const result = await submitAttendance(MISSION_UUID, PAYLOAD)

      expect(result.success).toBe(false)
      expect(result.status).toBe(422)
      expect(httpStatus.value).toBe(422)
      expect(fieldErrors.value).toEqual({
        'entries.0.entry_id': ['Cet identifiant est invalide.'],
      })
    })

    it('captures status 403', async () => {
      vi.mocked(missionApi.validateAttendance).mockRejectedValueOnce(
        makeAxiosError(403, { error: { message: 'Forbidden' } }),
      )

      const { submitAttendance, httpStatus } = useValidateAttendance()
      const result = await submitAttendance(MISSION_UUID, PAYLOAD)

      expect(result.success).toBe(false)
      expect(result.status).toBe(403)
      expect(httpStatus.value).toBe(403)
    })
  })

  describe('resetError', () => {
    it('clears error, fieldErrors, and httpStatus', async () => {
      vi.mocked(missionApi.getAttendanceForm).mockRejectedValueOnce(
        makeAxiosError(422, {
          error: { message: 'Validation failed', details: { status: ['ko'] } },
        }),
      )

      const { fetchForm, error, fieldErrors, httpStatus, resetError } = useValidateAttendance()
      await fetchForm(MISSION_UUID)

      expect(error.value).toBeTruthy()
      expect(fieldErrors.value).not.toEqual({})
      expect(httpStatus.value).toBe(422)

      resetError()

      expect(error.value).toBeNull()
      expect(fieldErrors.value).toEqual({})
      expect(httpStatus.value).toBeNull()
    })
  })
})
