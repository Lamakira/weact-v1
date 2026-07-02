import { describe, it, expect } from 'vitest'
import { bookingSchema } from '../booking'

const FACE_UUID = '550e8400-e29b-41d4-a716-446655440000'

const iso = (daysAhead: number): string => {
  const d = new Date()
  d.setDate(d.getDate() + daysAhead)
  return d.toISOString().slice(0, 10)
}

/** Valid base shared by every case (single future day, 4h ≤ 8h/day). */
const base = () => ({
  face_id: FACE_UUID,
  date_debut: iso(7),
  date_fin: iso(7),
  duree_heures: 4,
  lieu: 'Cotonou',
})

/** Returns the set of refine error paths for a failed parse. */
const errorPaths = (data: unknown): string[] => {
  const result = bookingSchema.safeParse(data)
  if (result.success) return []
  return result.error.issues.map((i) => i.path.join('.'))
}

describe('bookingSchema — cash (non-UGC) bookings ignore UGC rules', () => {
  it('accepts a cash booking with NO UGC fields at all', () => {
    const result = bookingSchema.safeParse({ ...base(), type_contenu: 'Publicité' })
    expect(result.success).toBe(true)
  })

  it('accepts a cash booking even when UGC fields are empty/blank', () => {
    const result = bookingSchema.safeParse({
      ...base(),
      type_contenu: 'Film',
      type_compensation: undefined,
      nom_produit: '',
      valeur_produit: '',
      nombre_videos: '',
      montant_remuneration: '',
    })
    expect(result.success).toBe(true)
  })

  it('accepts a cash booking even when hidden UGC text exceeds UGC limits', () => {
    const result = bookingSchema.safeParse({
      ...base(),
      type_contenu: 'Publicité',
      nom_produit: 'x'.repeat(256),
    })
    expect(result.success).toBe(true)
  })

  it('still requires the base fields for a cash booking', () => {
    const paths = errorPaths({ ...base(), type_contenu: '', lieu: '' })
    expect(paths).toContain('type_contenu')
    expect(paths).toContain('lieu')
  })

  it('requires the shoot date / fin / location for a cash booking', () => {
    const paths = errorPaths({
      face_id: FACE_UUID,
      duree_heures: 4,
      type_contenu: 'Publicité',
      date_debut: '',
      date_fin: '',
      lieu: '',
    })
    expect(paths).toContain('date_debut')
    expect(paths).toContain('date_fin')
    expect(paths).toContain('lieu')
  })
})

describe('bookingSchema — UGC product mode', () => {
  const ugcProduct = () => ({
    ...base(),
    type_contenu: 'UGC',
    type_compensation: 'product',
    nom_produit: 'Tenue Shade Fit M',
    valeur_produit: 45000,
  })

  it('accepts a valid product-only UGC booking (no video/remuneration needed)', () => {
    expect(bookingSchema.safeParse(ugcProduct()).success).toBe(true)
  })

  it('accepts a UGC booking with blank shoot date / location (a UGC dotation has no shoot)', () => {
    const result = bookingSchema.safeParse({
      face_id: FACE_UUID,
      date_debut: '',
      date_fin: '',
      duree_heures: 4, // form keeps its default; omitted from the UGC payload at submit time
      lieu: '',
      type_contenu: 'UGC',
      type_compensation: 'product',
      nom_produit: 'Tenue Shade Fit M',
      valeur_produit: 45000,
    })
    expect(result.success).toBe(true)
  })

  it('does NOT require nombre_videos / montant_remuneration in product mode', () => {
    const result = bookingSchema.safeParse({
      ...ugcProduct(),
      nombre_videos: undefined,
      montant_remuneration: undefined,
    })
    expect(result.success).toBe(true)
  })

  it('rejects an invalid type_compensation', () => {
    expect(errorPaths({ ...ugcProduct(), type_compensation: 'banana' })).toContain('type_compensation')
  })

  it('rejects an empty product name', () => {
    expect(errorPaths({ ...ugcProduct(), nom_produit: '' })).toContain('nom_produit')
  })

  it('rejects a whitespace-only product name', () => {
    expect(errorPaths({ ...ugcProduct(), nom_produit: '   ' })).toContain('nom_produit')
  })

  it('rejects a product name longer than 255 characters', () => {
    expect(errorPaths({ ...ugcProduct(), nom_produit: 'x'.repeat(256) })).toContain('nom_produit')
  })

  it('rejects a missing/empty product value', () => {
    expect(errorPaths({ ...ugcProduct(), valeur_produit: '' })).toContain('valeur_produit')
  })

  it('rejects a product value below 1', () => {
    expect(errorPaths({ ...ugcProduct(), valeur_produit: 0 })).toContain('valeur_produit')
  })

  it('rejects a non-integer product value', () => {
    expect(errorPaths({ ...ugcProduct(), valeur_produit: 1.5 })).toContain('valeur_produit')
  })
})

describe('bookingSchema — UGC hybrid mode', () => {
  const ugcHybrid = () => ({
    ...base(),
    type_contenu: 'UGC',
    type_compensation: 'hybrid',
    nom_produit: 'Tenue Shade Fit M',
    valeur_produit: 45000,
    nombre_videos: 3,
    montant_remuneration: 15000,
  })

  it('accepts a valid hybrid UGC booking', () => {
    expect(bookingSchema.safeParse(ugcHybrid()).success).toBe(true)
  })

  it('rejects a missing video count', () => {
    expect(errorPaths({ ...ugcHybrid(), nombre_videos: '' })).toContain('nombre_videos')
  })

  it('rejects a video count below 2', () => {
    expect(errorPaths({ ...ugcHybrid(), nombre_videos: 0 })).toContain('nombre_videos')
    expect(errorPaths({ ...ugcHybrid(), nombre_videos: 1 })).toContain('nombre_videos') // ← option B (ugc-4-0)
  })

  it('rejects a video count above 20', () => {
    expect(errorPaths({ ...ugcHybrid(), nombre_videos: 21 })).toContain('nombre_videos')
  })

  it('accepts the inclusive video-count bounds (2 and 20)', () => {
    expect(bookingSchema.safeParse({ ...ugcHybrid(), nombre_videos: 2 }).success).toBe(true)
    expect(bookingSchema.safeParse({ ...ugcHybrid(), nombre_videos: 20 }).success).toBe(true)
  })

  it('rejects a missing remuneration amount', () => {
    expect(errorPaths({ ...ugcHybrid(), montant_remuneration: '' })).toContain('montant_remuneration')
  })

  it('rejects a remuneration amount below 1', () => {
    expect(errorPaths({ ...ugcHybrid(), montant_remuneration: 0 })).toContain('montant_remuneration')
  })

  it('rejects a non-integer remuneration amount', () => {
    expect(errorPaths({ ...ugcHybrid(), montant_remuneration: 12.5 })).toContain('montant_remuneration')
  })
})
