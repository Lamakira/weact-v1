import { describe, it, expect } from 'vitest'
import {
  computeUgcCommission,
  UGC_COMMISSION_FLOOR,
  UGC_COMMISSION_RATE,
  UGC_PRODUCT_ONLY_VIDEO_COUNT,
  UGC_UNBOXING_DAYS,
  tunnelStatusToPillKind,
  ugcTunnelStep,
  ugcCandidatureTunnelStep,
  ugcShipmentSchema,
  type Shipment,
} from '../ugc'

function makeShipment(overrides: Partial<Shipment> = {}): Shipment {
  return {
    id: 'shipment-uuid-1',
    transporteur: 'Gozem',
    numero_suivi: 'GZM-COT-882194',
    note_envoi: null,
    tunnel_status: 'shipped',
    tunnel_status_label: 'Produit expédié',
    shipped_at: '2026-06-12T10:00:00+00:00',
    recu_le: null,
    unboxing_deadline_at: null,
    destinataire: { nom: 'Aïcha Bello', ville: 'Cotonou', pays: 'Bénin' },
    created_at: '2026-06-12T10:00:00+00:00',
    ...overrides,
  }
}

describe('computeUgcCommission', () => {
  // Same boundary values as the backend UgcCommissionServiceTest (story 1.1)
  it.each([
    [20000, 2500, 'floor (10% = 2000 < 2500)'],
    [25000, 2500, 'exact boundary (10% = 2500)'],
    [50000, 5000, 'percentage above the floor'],
    [25005, 2501, 'round half-up (2500.5 → 2501)'],
    [0, 2500, 'floor on empty/0 value'],
  ])('computeUgcCommission(%i) = %i (%s)', (productValue, expected) => {
    expect(computeUgcCommission(productValue)).toBe(expected)
  })

  it('falls back to the floor when given a falsy value', () => {
    expect(computeUgcCommission(NaN)).toBe(2500)
    // @ts-expect-error — guarding the `productValue || 0` runtime fallback
    expect(computeUgcCommission(undefined)).toBe(2500)
  })

  it('exposes constants that mirror backend/config/ugc.php', () => {
    expect(UGC_COMMISSION_RATE).toBe(0.1)
    expect(UGC_COMMISSION_FLOOR).toBe(2500)
    expect(UGC_PRODUCT_ONLY_VIDEO_COUNT).toBe(2)
    expect(UGC_UNBOXING_DAYS).toBe(7)
  })
})

describe('tunnelStatusToPillKind', () => {
  it('maps every known tunnel status to its pill kind', () => {
    expect(tunnelStatusToPillKind('shipped')).toBe('shipped')
    expect(tunnelStatusToPillKind('received')).toBe('received')
    expect(tunnelStatusToPillKind('unboxing_in_review')).toBe('received')
    expect(tunnelStatusToPillKind('avis_in_review')).toBe('received')
    expect(tunnelStatusToPillKind('completed')).toBe('completed')
    expect(tunnelStatusToPillKind('overdue')).toBe('overdue')
    expect(tunnelStatusToPillKind('suspended')).toBe('suspended')
  })

  it('maps avis_pending to received (4.4 — Unboxing validé, produit reçu mi-tunnel)', () => {
    expect(tunnelStatusToPillKind('avis_pending')).toBe('received')
  })

  it('falls back to pending for unknown statuses', () => {
    // Règle fan-out 3.1 : les cases réservés (épics 4-5) arrivent sans casser le front.
    expect(tunnelStatusToPillKind('some_future_status')).toBe('pending')
    expect(tunnelStatusToPillKind('')).toBe('pending')
  })
})

describe('ugcTunnelStep', () => {
  it('derives steps 1/2/3 from booking status without shipment', () => {
    expect(ugcTunnelStep('pending')).toBe(1)
    expect(ugcTunnelStep('commission_paid')).toBe(2)
    expect(ugcTunnelStep('accepted', null)).toBe(3)
  })

  it('returns 4 when the shipment is shipped', () => {
    expect(ugcTunnelStep('accepted', makeShipment())).toBe(4)
  })

  it('maps received and in-review statuses to steps 5 and 6', () => {
    expect(ugcTunnelStep('accepted', makeShipment({ tunnel_status: 'received' }))).toBe(5)
    expect(ugcTunnelStep('accepted', makeShipment({ tunnel_status: 'unboxing_in_review' }))).toBe(5)
    expect(ugcTunnelStep('accepted', makeShipment({ tunnel_status: 'avis_in_review' }))).toBe(6)
    expect(ugcTunnelStep('completed', makeShipment({ tunnel_status: 'completed' }))).toBe(7)
  })

  it('maps avis_pending to step 6 (4.4 — Unboxing validé, étape Avis active)', () => {
    // D-4.4.i : avis_pending = Unboxing validé ⇒ étape Avis active (6), aligné avis_in_review.
    expect(ugcTunnelStep('accepted', makeShipment({ tunnel_status: 'avis_pending' }))).toBe(6)
  })

  it('falls back to 4 for unknown tunnel statuses (reserved cases)', () => {
    // Post-expédition, jamais < 4 : un déploiement backend seul ne fait pas reculer la timeline.
    expect(ugcTunnelStep('accepted', makeShipment({ tunnel_status: 'some_future_status' }))).toBe(4)
  })

  it('returns 0 for terminal booking statuses without shipment', () => {
    expect(ugcTunnelStep('refused')).toBe(0)
    expect(ugcTunnelStep('expired')).toBe(0)
    expect(ugcTunnelStep('cancelled_by_producer')).toBe(0)
  })
})

describe('ugcCandidatureTunnelStep', () => {
  it('derives steps 2/3/7 from candidature status without shipment', () => {
    // Commission mission payée AU PUBLISH : une candidature vivante est au moins à l'étape 2.
    expect(ugcCandidatureTunnelStep('pending')).toBe(2)
    expect(ugcCandidatureTunnelStep('accepted', null)).toBe(2)
    expect(ugcCandidatureTunnelStep('confirmed')).toBe(3)
    expect(ugcCandidatureTunnelStep('in_progress')).toBe(3)
    expect(ugcCandidatureTunnelStep('completed')).toBe(7)
  })

  it('returns 0 for dead or unknown candidature statuses', () => {
    expect(ugcCandidatureTunnelStep('rejected')).toBe(0)
    expect(ugcCandidatureTunnelStep('cancelled')).toBe(0)
    expect(ugcCandidatureTunnelStep('some_future_status')).toBe(0)
  })

  it('delegates to the shipment branch when a shipment exists', () => {
    // Même table de mapping que ugcTunnelStep (shipmentTunnelStep partagée).
    expect(ugcCandidatureTunnelStep('confirmed', makeShipment())).toBe(4)
    expect(ugcCandidatureTunnelStep('confirmed', makeShipment({ tunnel_status: 'received' }))).toBe(5)
    expect(ugcCandidatureTunnelStep('confirmed', makeShipment({ tunnel_status: 'some_future_status' }))).toBe(4)
  })
})

describe('ugcShipmentSchema', () => {
  it('accepts a valid payload and trims fields', () => {
    const result = ugcShipmentSchema.safeParse({
      transporteur: '  DHL  ',
      numero_suivi: ' TRK-001 ',
      note_envoi: '  Fragile  ',
    })
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.transporteur).toBe('DHL')
      expect(result.data.numero_suivi).toBe('TRK-001')
      expect(result.data.note_envoi).toBe('Fragile')
    }
  })

  it('rejects empty transporteur and numero_suivi', () => {
    const result = ugcShipmentSchema.safeParse({ transporteur: '', numero_suivi: '   ' })
    expect(result.success).toBe(false)
    if (!result.success) {
      const fields = result.error.issues.map((issue) => issue.path[0])
      expect(fields).toContain('transporteur')
      expect(fields).toContain('numero_suivi')
    }
  })

  it('rejects fields over their max length', () => {
    const result = ugcShipmentSchema.safeParse({
      transporteur: 'x'.repeat(101),
      numero_suivi: 'y'.repeat(101),
      note_envoi: 'z'.repeat(501),
    })
    expect(result.success).toBe(false)
    if (!result.success) {
      const fields = result.error.issues.map((issue) => issue.path[0])
      expect(fields).toContain('transporteur')
      expect(fields).toContain('numero_suivi')
      expect(fields).toContain('note_envoi')
    }
  })
})
