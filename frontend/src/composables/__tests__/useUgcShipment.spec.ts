import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useUgcShipment } from '../useUgcShipment'
import { bookingApi } from '@/features/booking/services/bookingApi'
import { candidatureApi } from '@/features/candidature/services/candidatureApi'
import { faceApi } from '@/features/face/services/faceApi'
import type { ConfirmShipmentPayload, Shipment, ShipmentResponse } from '@/components/ugc'

vi.mock('@/features/booking/services/bookingApi', () => ({
  bookingApi: { confirmShipment: vi.fn() },
}))
vi.mock('@/features/candidature/services/candidatureApi', () => ({
  candidatureApi: { confirmShipment: vi.fn() },
}))
vi.mock('@/features/face/services/faceApi', () => ({
  faceApi: { confirmShipmentReceipt: vi.fn() },
}))

const payload: ConfirmShipmentPayload = {
  transporteur: 'Gozem',
  numero_suivi: 'GZM-COT-882194',
}

function makeShipmentResponse(overrides: Partial<Shipment> = {}): ShipmentResponse {
  return {
    data: { id: 'shipment-uuid-1', tunnel_status: 'shipped', ...overrides } as unknown as Shipment,
    message: 'Expédition confirmée',
  }
}

/** Erreur axios-like portant l'envelope FIX-22.2 { error: { code, message } }. */
function makeEnvelopeError(code: string, message: string): unknown {
  return {
    isAxiosError: true,
    response: { status: 422, data: { error: { code, message } } },
  }
}

describe('useUgcShipment', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('confirms a booking shipment through bookingApi', async () => {
    vi.mocked(bookingApi.confirmShipment).mockResolvedValue(makeShipmentResponse())

    const { confirmShipment, error, errorCode } = useUgcShipment()
    const shipment = await confirmShipment('booking', 'b1', payload)

    expect(bookingApi.confirmShipment).toHaveBeenCalledWith('b1', payload)
    expect(candidatureApi.confirmShipment).not.toHaveBeenCalled()
    expect(shipment).toMatchObject({ id: 'shipment-uuid-1', tunnel_status: 'shipped' })
    expect(error.value).toBeNull()
    expect(errorCode.value).toBeNull()
  })

  it('confirms a candidature shipment through candidatureApi', async () => {
    vi.mocked(candidatureApi.confirmShipment).mockResolvedValue(makeShipmentResponse())

    const { confirmShipment } = useUgcShipment()
    const shipment = await confirmShipment('candidature', 'c1', payload)

    expect(candidatureApi.confirmShipment).toHaveBeenCalledWith('c1', payload)
    expect(bookingApi.confirmShipment).not.toHaveBeenCalled()
    expect(shipment).not.toBeNull()
  })

  it('tracks isSubmitting during the request', async () => {
    let resolveRequest!: (value: ShipmentResponse) => void
    vi.mocked(bookingApi.confirmShipment).mockReturnValue(
      new Promise((resolve) => {
        resolveRequest = resolve
      }),
    )

    const { confirmShipment, isSubmitting } = useUgcShipment()
    expect(isSubmitting.value).toBe(false)

    const pending = confirmShipment('booking', 'b1', payload)
    expect(isSubmitting.value).toBe(true)

    resolveRequest(makeShipmentResponse())
    await pending
    expect(isSubmitting.value).toBe(false)
  })

  it('captures the error message on failure', async () => {
    vi.mocked(bookingApi.confirmShipment).mockRejectedValue(
      makeEnvelopeError('INVALID_STATUS', 'Ce deal ne peut pas être expédié dans son état actuel.'),
    )

    const { confirmShipment, error } = useUgcShipment()
    const shipment = await confirmShipment('booking', 'b1', payload)

    expect(shipment).toBeNull()
    expect(error.value).toBe('Ce deal ne peut pas être expédié dans son état actuel.')
  })

  it('captures the envelope error code', async () => {
    vi.mocked(bookingApi.confirmShipment).mockRejectedValue(
      makeEnvelopeError('ALREADY_SHIPPED', "L'expédition de ce deal a déjà été confirmée."),
    )

    const { confirmShipment, errorCode } = useUgcShipment()
    await confirmShipment('booking', 'b1', payload)

    expect(errorCode.value).toBe('ALREADY_SHIPPED')
  })

  it('confirms a receipt through faceApi', async () => {
    vi.mocked(faceApi.confirmShipmentReceipt).mockResolvedValue(
      makeShipmentResponse({ tunnel_status: 'received' }),
    )

    const { confirmReceipt, error, errorCode } = useUgcShipment()
    const shipment = await confirmReceipt('shipment-uuid-1')

    expect(faceApi.confirmShipmentReceipt).toHaveBeenCalledWith('shipment-uuid-1')
    expect(shipment).toMatchObject({ id: 'shipment-uuid-1', tunnel_status: 'received' })
    expect(error.value).toBeNull()
    expect(errorCode.value).toBeNull()
  })

  it('tracks isSubmitting during receipt confirmation', async () => {
    let resolveRequest!: (value: ShipmentResponse) => void
    vi.mocked(faceApi.confirmShipmentReceipt).mockReturnValue(
      new Promise((resolve) => {
        resolveRequest = resolve
      }),
    )

    const { confirmReceipt, isSubmitting } = useUgcShipment()
    expect(isSubmitting.value).toBe(false)

    const pending = confirmReceipt('shipment-uuid-1')
    expect(isSubmitting.value).toBe(true)

    resolveRequest(makeShipmentResponse())
    await pending
    expect(isSubmitting.value).toBe(false)
  })

  it('captures the envelope error code on receipt failure', async () => {
    vi.mocked(faceApi.confirmShipmentReceipt).mockRejectedValue(
      makeEnvelopeError('ALREADY_RECEIVED', 'La réception de ce produit a déjà été confirmée.'),
    )

    const { confirmReceipt, error, errorCode } = useUgcShipment()
    const shipment = await confirmReceipt('shipment-uuid-1')

    expect(shipment).toBeNull()
    expect(error.value).toBe('La réception de ce produit a déjà été confirmée.')
    expect(errorCode.value).toBe('ALREADY_RECEIVED')
  })

  it('clearError resets error state', async () => {
    vi.mocked(bookingApi.confirmShipment).mockRejectedValue(
      makeEnvelopeError('ALREADY_SHIPPED', "L'expédition de ce deal a déjà été confirmée."),
    )

    const { confirmShipment, clearError, error, errorCode } = useUgcShipment()
    await confirmShipment('booking', 'b1', payload)
    expect(error.value).not.toBeNull()
    expect(errorCode.value).not.toBeNull()

    clearError()
    expect(error.value).toBeNull()
    expect(errorCode.value).toBeNull()
  })
})
