import { describe, it, expect } from 'vitest'
import { createMissionSchema, missionSchema } from '../mission'

/**
 * Schéma conditionnel UGC (story 1.4 AC6) :
 *  - `budget` reste obligatoire pour les types standard, NON requis pour l'UGC (dérivé serveur — D-1.4.d).
 *  - Les champs UGC (`type_compensation`, `nom_produit`, `valeur_produit`, et en hybride
 *    `nombre_videos`/`montant_remuneration`) ne sont validés QUE quand `type_mission === 'ugc'`.
 *  - Le `max:255` de `nom_produit` est un refine gardé UGC-only (pas une contrainte inconditionnelle).
 */

// Dates futures cohérentes (tournage > limite > aujourd'hui) — calculées pour ne pas pourrir avec le temps.
const futureDate = (daysAhead: number): string => {
  const d = new Date()
  d.setDate(d.getDate() + daysAhead)
  return d.toISOString().slice(0, 10)
}

const baseStandard = {
  titre: 'Pub été',
  description: 'Desc valide',
  date_tournage: futureDate(60),
  profil_recherche: 'Profil',
  date_limite_candidature: futureDate(30),
  nombre_faces_voulu: 2,
  type_mission: 'publicite',
  genre_voulu: 'tous',
  lieu: 'Cotonou',
  duree: '1 jour',
}

const baseUgc = {
  ...baseStandard,
  type_mission: 'ugc',
  budget: undefined,
  type_compensation: 'product',
  nom_produit: 'Sérum',
  valeur_produit: 25000,
}

/** True if a Zod issue exists on the given top-level field path. */
const hasIssueOn = (result: ReturnType<typeof missionSchema.safeParse>, field: string): boolean =>
  !result.success && result.error.issues.some((i) => i.path[0] === field)

describe('missionSchema — budget conditionnel (standard vs UGC)', () => {
  it('1. standard sans budget → échec sur "budget" (obligatoire)', () => {
    const result = missionSchema.safeParse({ ...baseStandard })
    expect(result.success).toBe(false)
    expect(hasIssueOn(result, 'budget')).toBe(true)
  })

  it('2. standard avec budget=50000 → succès', () => {
    const result = missionSchema.safeParse({ ...baseStandard, budget: 50000 })
    expect(result.success).toBe(true)
  })

  it('3. UGC sans budget → succès (budget non requis)', () => {
    const result = missionSchema.safeParse({ ...baseUgc })
    expect(result.success).toBe(true)
  })

  it('4. UGC avec budget vidé résiduel → succès (champ masqué ignoré)', () => {
    const result = missionSchema.safeParse({ ...baseUgc, budget: '' })
    expect(result.success).toBe(true)
  })
})

describe('missionSchema — refines UGC (gardés par type_mission === "ugc")', () => {
  it('5. UGC sans type_compensation → échec sur "type_compensation"', () => {
    const result = missionSchema.safeParse({ ...baseUgc, type_compensation: undefined })
    expect(result.success).toBe(false)
    expect(hasIssueOn(result, 'type_compensation')).toBe(true)
  })

  it('6. UGC sans nom_produit → échec sur "nom_produit"', () => {
    const result = missionSchema.safeParse({ ...baseUgc, nom_produit: '' })
    expect(result.success).toBe(false)
    expect(hasIssueOn(result, 'nom_produit')).toBe(true)
  })

  it('7. UGC valeur_produit=0 → échec ; valeur_produit=25000 → succès', () => {
    const zero = missionSchema.safeParse({ ...baseUgc, valeur_produit: 0 })
    expect(zero.success).toBe(false)
    expect(hasIssueOn(zero, 'valeur_produit')).toBe(true)

    const ok = missionSchema.safeParse({ ...baseUgc, valeur_produit: 25000 })
    expect(ok.success).toBe(true)
  })

  it('8. UGC hybride sans montant_remuneration → échec sur "montant_remuneration"', () => {
    const result = missionSchema.safeParse({
      ...baseUgc,
      type_compensation: 'hybrid',
      nombre_videos: 3,
      montant_remuneration: undefined,
    })
    expect(result.success).toBe(false)
    expect(hasIssueOn(result, 'montant_remuneration')).toBe(true)
  })

  it('9. UGC hybride : nombre_videos manquant → échec ; =21 → échec ; =3 → succès', () => {
    const missing = missionSchema.safeParse({
      ...baseUgc,
      type_compensation: 'hybrid',
      montant_remuneration: 15000,
    })
    expect(missing.success).toBe(false)
    expect(hasIssueOn(missing, 'nombre_videos')).toBe(true)

    const tooMany = missionSchema.safeParse({
      ...baseUgc,
      type_compensation: 'hybrid',
      nombre_videos: 21,
      montant_remuneration: 15000,
    })
    expect(tooMany.success).toBe(false)
    expect(hasIssueOn(tooMany, 'nombre_videos')).toBe(true)

    const belowFloor = missionSchema.safeParse({
      ...baseUgc,
      type_compensation: 'hybrid',
      nombre_videos: 1,
      montant_remuneration: 15000,
    })
    expect(belowFloor.success).toBe(false)
    expect(hasIssueOn(belowFloor, 'nombre_videos')).toBe(true)

    const floor = missionSchema.safeParse({
      ...baseUgc,
      type_compensation: 'hybrid',
      nombre_videos: 2,
      montant_remuneration: 15000,
    })
    expect(floor.success).toBe(true)

    const ok = missionSchema.safeParse({
      ...baseUgc,
      type_compensation: 'hybrid',
      nombre_videos: 3,
      montant_remuneration: 15000,
    })
    expect(ok.success).toBe(true)
  })

  it('10. nom_produit > 255 : ignoré pour standard (max gardé UGC), bloque pour UGC', () => {
    const longName = 'x'.repeat(300)

    // Standard avec un nom_produit résiduel trop long (champ masqué) → NE bloque PAS.
    const standard = missionSchema.safeParse({ ...baseStandard, budget: 50000, nom_produit: longName })
    expect(standard.success).toBe(true)

    // UGC avec nom_produit trop long → échec sur "nom_produit".
    const ugc = missionSchema.safeParse({ ...baseUgc, nom_produit: longName })
    expect(ugc.success).toBe(false)
    expect(hasIssueOn(ugc, 'nom_produit')).toBe(true)
  })

  it('11. nom_produit trimé à 255 caractères utiles → succès', () => {
    const validTrimmedName = `  ${'x'.repeat(255)}  `

    const result = missionSchema.safeParse({ ...baseUgc, nom_produit: validTrimmedName })

    expect(result.success).toBe(true)
  })
})

describe('createMissionSchema — clôture inchangée en édition', () => {
  const pastDate = futureDate(-5)
  const base = { ...baseStandard, budget: 50000, date_limite_candidature: pastDate, date_tournage: futureDate(20) }

  it('création : une clôture passée reste refusée', () => {
    const result = missionSchema.safeParse(base)
    expect(result.success).toBe(false)
    expect(hasIssueOn(result, 'date_limite_candidature')).toBe(true)
  })

  it('édition : la clôture initiale, même passée, est acceptée', () => {
    const result = createMissionSchema({ unchangedDeadline: pastDate }).safeParse(base)
    expect(result.success).toBe(true)
  })

  it('édition : la valeur initiale au format ISO complet est reconnue comme inchangée', () => {
    const result = createMissionSchema({ unchangedDeadline: `${pastDate}T00:00:00+00:00` }).safeParse(base)
    expect(result.success).toBe(true)
  })

  it('édition : une clôture modifiée vers une autre date passée garde la règle', () => {
    const result = createMissionSchema({ unchangedDeadline: pastDate }).safeParse({
      ...base,
      date_limite_candidature: futureDate(-2),
    })
    expect(result.success).toBe(false)
    expect(hasIssueOn(result, 'date_limite_candidature')).toBe(true)
  })

  it('édition : une clôture modifiée vers une date future est acceptée', () => {
    const result = createMissionSchema({ unchangedDeadline: pastDate }).safeParse({
      ...base,
      date_limite_candidature: futureDate(10),
    })
    expect(result.success).toBe(true)
  })

  it('édition : le tournage doit rester postérieur à la clôture inchangée', () => {
    const result = createMissionSchema({ unchangedDeadline: pastDate }).safeParse({
      ...base,
      date_tournage: pastDate,
    })
    expect(result.success).toBe(false)
    expect(hasIssueOn(result, 'date_tournage')).toBe(true)
  })

  it('édition sans valeur initiale : la règle de création s’applique', () => {
    const result = createMissionSchema({ unchangedDeadline: null }).safeParse(base)
    expect(result.success).toBe(false)
  })

  it('édition, clôture passée conservée : tournage aujourd’hui ou passé refusé', () => {
    const schema = createMissionSchema({ unchangedDeadline: pastDate })
    const today = schema.safeParse({ ...base, date_tournage: futureDate(0) })
    expect(today.success).toBe(false)
    expect(hasIssueOn(today, 'date_tournage')).toBe(true)
    const past = schema.safeParse({ ...base, date_tournage: futureDate(-2) })
    expect(hasIssueOn(past, 'date_tournage')).toBe(true)
  })

  it('édition, clôture passée conservée : tournage demain accepté', () => {
    const result = createMissionSchema({ unchangedDeadline: pastDate }).safeParse({ ...base, date_tournage: futureDate(1) })
    expect(result.success).toBe(true)
  })
})
