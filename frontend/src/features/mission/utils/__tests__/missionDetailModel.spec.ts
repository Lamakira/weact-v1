import { describe, expect, it } from 'vitest'
import type { Mission, MissionSummary, MissionSummaryStep } from '../../types'
import {
  buildNextBlock,
  buildPaymentCard,
  formatCountdown,
  getCurrentStepIndex,
  getMissionSteps,
  resolvePrimaryAction,
  type DetailContext,
  type PaymentUiState,
} from '../missionDetailModel'

function makeSummary(step: MissionSummaryStep, overrides: Partial<MissionSummary> = {}): MissionSummary {
  return {
    step,
    candidatures: { pending: 0, accepted: 0, confirmed: 0, in_progress: 0, completed: 0, rejected: 0, cancelled: 0, expired: 0 },
    selection: { faces_wanted: 3, faces_selected: 3, faces_confirmed: 2, freed_places: 0 },
    payment: { status: null, expires_at: null, paid_at: null, montant_total_producteur: null },
    attendance: null,
    ugc_deliverables: null,
    completion_blocker: null,
    faces_to_rate: 0,
    all_places_refunded: false,
    can_edit: false,
    ...overrides,
  }
}

function makeMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 'mission-uuid',
    titre: 'Spot TV',
    date_tournage: '2026-10-23T09:00:00+00:00',
    date_limite_candidature: '2026-10-19T12:00:00+00:00',
    budget: 110000,
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    lieu: 'Cotonou',
    status: 'published',
    type_compensation: null,
    commission_ugc: null,
    commission_paid_at: null,
    montant_remuneration: null,
    ...overrides,
  } as Mission
}

const NO_PAYMENT: PaymentUiState = { canResume: false, resumable: false, initializationFailed: false }

function ctx(
  mission: Mission,
  summary: MissionSummary,
  payment: PaymentUiState = NO_PAYMENT,
  emailVerified = true,
): DetailContext {
  return { mission, summary, emailVerified, payment }
}

describe('étapes', () => {
  it('liste les étapes standard et UGC', () => {
    expect(getMissionSteps(false).map((s) => s.label)).toEqual([
      'Candidatures',
      'Paiement',
      'Confirmations',
      'Tournage',
      'Terminée',
    ])
    expect(getMissionSteps(true).map((s) => s.label)).toEqual(['Commission', 'Candidatures', 'Tunnel', 'Terminée'])
  })

  it.each([
    ['recruiting', 0],
    ['payment', 1],
    ['confirmations', 2],
    ['ready', 3],
    ['attendance', 3],
    ['completed', 4],
    ['draft', -1],
  ] as const)('mission standard : summary.step %s -> index %i', (step, index) => {
    expect(getCurrentStepIndex(step, false)).toBe(index)
  })

  it.each([
    ['commission', 0],
    ['recruiting', 1],
    ['tunnel', 2],
    ['completed', 3],
    ['draft', -1],
  ] as const)('mission UGC : summary.step %s -> index %i', (step, index) => {
    expect(getCurrentStepIndex(step, true)).toBe(index)
  })
})

describe('action principale — mission standard', () => {
  it('recrutement avec des candidatures : « Choisir mes Faces »', () => {
    const summary = makeSummary('recruiting', {
      candidatures: { ...makeSummary('recruiting').candidatures, pending: 4 },
    })
    expect(resolvePrimaryAction(ctx(makeMission(), summary))).toEqual({
      key: 'select-faces',
      label: 'Choisir mes Faces',
      hint: 'Sélectionnez jusqu\'à 3 Faces',
    })
  })

  it('recrutement sans candidature, ou mission clôturée à la main : aucune action', () => {
    expect(resolvePrimaryAction(ctx(makeMission(), makeSummary('recruiting')))).toBeNull()
    const pending = { ...makeSummary('recruiting').candidatures, pending: 2 }
    expect(
      resolvePrimaryAction(ctx(makeMission({ status: 'closed' }), makeSummary('recruiting', { candidatures: pending }))),
    ).toBeNull()
  })

  it('paiement avec transaction vivante : « Reprendre le paiement » + montant', () => {
    const summary = makeSummary('payment', {
      payment: { status: 'pending', expires_at: null, paid_at: null, montant_total_producteur: 330000 },
    })
    const action = resolvePrimaryAction(
      ctx(makeMission({ status: 'pending_payment' }), summary, { ...NO_PAYMENT, canResume: true }),
    )
    expect(action?.key).toBe('resume-payment')
    expect(action?.label).toBe('Reprendre le paiement')
    expect(action?.hint).toContain('330')
  })

  it('paiement relançable (sans transaction vivante) : « Relancer le paiement »', () => {
    const action = resolvePrimaryAction(
      ctx(makeMission({ status: 'pending_payment' }), makeSummary('payment'), {
        canResume: true,
        resumable: true,
        initializationFailed: true,
      }),
    )
    expect(action?.label).toBe('Relancer le paiement')
  })

  it('paiement non repris (initialisation échouée, sélection consommée) : reconfirmer dans l’onglet Candidatures', () => {
    const action = resolvePrimaryAction(
      ctx(makeMission({ status: 'pending_payment' }), makeSummary('payment'), {
        canResume: false,
        resumable: false,
        initializationFailed: true,
      }),
    )
    expect(action?.key).toBe('reconfirm-selection')
  })

  it('paiement en cours d’évaluation : pas d’action inventée', () => {
    expect(resolvePrimaryAction(ctx(makeMission({ status: 'pending_payment' }), makeSummary('payment')))).toBeNull()
  })

  it('confirmations avec place libérée remplaçable : « Remplacer la place libérée »', () => {
    const summary = makeSummary('confirmations', {
      selection: { faces_wanted: 3, faces_selected: 3, faces_confirmed: 2, freed_places: 1 },
    })
    const mission = makeMission({ status: 'closed', can_replace_freed_place: true })
    expect(resolvePrimaryAction(ctx(mission, summary))?.label).toBe('Remplacer la place libérée')
    // Pluriel
    const two = makeSummary('confirmations', {
      selection: { faces_wanted: 3, faces_selected: 3, faces_confirmed: 1, freed_places: 2 },
    })
    expect(resolvePrimaryAction(ctx(mission, two))?.label).toBe('Remplacer les places libérées')
  })

  it('confirmations sans place libérée, ou non remplaçable : aucune action (le bloc explique qui agit)', () => {
    const mission = makeMission({ status: 'closed' })
    expect(resolvePrimaryAction(ctx(mission, makeSummary('confirmations')))).toBeNull()
    const freed = makeSummary('confirmations', {
      selection: { faces_wanted: 3, faces_selected: 3, faces_confirmed: 2, freed_places: 1 },
    })
    expect(resolvePrimaryAction(ctx({ ...mission, can_replace_freed_place: false }, freed))).toBeNull()
  })

  it('prêt : aucune action', () => {
    expect(resolvePrimaryAction(ctx(makeMission({ status: 'closed' }), makeSummary('ready')))).toBeNull()
  })

  it('jour du tournage : « Valider les présences » seulement s’il reste des présences à pointer', () => {
    const attendance = (pending: number) => ({
      opens_at: '2026-10-23T00:00:00+00:00',
      can_mark: true,
      pending,
      present: 0,
      absent: 0,
      disputed: 0,
    })
    const mission = makeMission({ status: 'closed' })
    expect(resolvePrimaryAction(ctx(mission, makeSummary('attendance', { attendance: attendance(3) })))).toMatchObject({
      key: 'validate-attendance',
      label: 'Valider les présences',
      hint: '3 Faces à pointer',
    })
    expect(resolvePrimaryAction(ctx(mission, makeSummary('attendance', { attendance: attendance(0) })))).toBeNull()
  })

  it('toutes les places remboursées : « Republier la mission » (standard)', () => {
    const action = resolvePrimaryAction(
      ctx(makeMission({ status: 'closed', has_paid_payment: true }), makeSummary('confirmations', { all_places_refunded: true })),
    )
    expect(action).toMatchObject({ key: 'republish', label: 'Republier la mission' })
  })

  it('places non remboursées : pas de republication', () => {
    const action = resolvePrimaryAction(
      ctx(makeMission({ status: 'closed', has_paid_payment: true }), makeSummary('confirmations', { all_places_refunded: false })),
    )
    expect(action?.key).not.toBe('republish')
  })

  it('terminée sans Face à noter : aucune action', () => {
    expect(resolvePrimaryAction(ctx(makeMission({ status: 'completed' }), makeSummary('completed')))).toBeNull()
  })

  it('terminée avec des Faces à noter : « Noter mes Faces », facultatif (standard et UGC)', () => {
    const action = resolvePrimaryAction(
      ctx(makeMission({ status: 'completed' }), makeSummary('completed', { faces_to_rate: 2 })),
    )
    expect(action).toMatchObject({ key: 'rate-faces', label: 'Noter mes Faces' })
    expect(action?.hint).toContain('2 Faces à noter')
    expect(action?.hint).toContain('facultatif')

    const ugc = resolvePrimaryAction(
      ctx(
        makeMission({ status: 'completed', type_mission: 'ugc' } as Partial<Mission>),
        makeSummary('completed', { faces_to_rate: 1 }),
      ),
    )
    expect(ugc?.key).toBe('rate-faces')
    expect(ugc?.hint).toContain('1 Face à noter')
  })
})

describe('action principale — mission UGC', () => {
  const ugc = (overrides: Partial<Mission> = {}) =>
    makeMission({ type_mission: 'ugc', type_compensation: 'product', commission_ugc: 2500, ...overrides } as Partial<Mission>)

  it('commission à régler : « Payer la commission »', () => {
    const action = resolvePrimaryAction(ctx(ugc({ status: 'pending_payment' }), makeSummary('commission')))
    expect(action).toMatchObject({ key: 'pay-commission', label: 'Payer la commission' })
    expect(action?.hint).toContain('2')
  })

  it('commission : pas d’action sans e-mail vérifié (même règle que la liste)', () => {
    expect(
      resolvePrimaryAction(ctx(ugc({ status: 'pending_payment' }), makeSummary('commission'), NO_PAYMENT, false)),
    ).toBeNull()
  })

  it('tunnel avec livrables à valider : « Voir les livrables »', () => {
    const summary = makeSummary('tunnel', { ugc_deliverables: { to_review: 2, validated: 0, retouche_requested: 0 } })
    expect(resolvePrimaryAction(ctx(ugc({ status: 'closed' }), summary))).toMatchObject({
      key: 'review-deliverables',
      label: 'Voir les livrables',
      hint: '2 à valider',
    })
  })

  it('tunnel sans livrable à valider, recrutement, terminée : aucune action', () => {
    const none = { to_review: 0, validated: 1, retouche_requested: 0 }
    expect(resolvePrimaryAction(ctx(ugc({ status: 'closed' }), makeSummary('tunnel', { ugc_deliverables: none })))).toBeNull()
    expect(resolvePrimaryAction(ctx(ugc(), makeSummary('recruiting')))).toBeNull()
    expect(resolvePrimaryAction(ctx(ugc({ status: 'completed' }), makeSummary('completed')))).toBeNull()
  })
})

describe('« Et ensuite ? »', () => {
  it('paiement : compte à rebours sur payment.expires_at et à vous d’agir', () => {
    const summary = makeSummary('payment', {
      payment: { status: 'pending', expires_at: '2026-10-20T16:05:00+00:00', paid_at: null, montant_total_producteur: 330000 },
    })
    const block = buildNextBlock(ctx(makeMission({ status: 'pending_payment' }), summary))
    expect(block.headline).toBe('Payez votre sélection')
    expect(block.text).toContain('Vos 3 Faces')
    expect(block.who).toBe('À vous d\'agir')
    expect(block.deadlineAt).toBe('2026-10-20T16:05:00+00:00')
    expect(block.deadlineLabel).toMatch(/^avant le .+ à \d{2}h\d{2}$/)
  })

  it('confirmations : nombre de Faces confirmées sur sélectionnées, les Faces doivent agir', () => {
    const block = buildNextBlock(ctx(makeMission({ status: 'closed' }), makeSummary('confirmations')))
    expect(block.headline).toBe('2 Faces sur 3 ont confirmé')
    expect(block.who).toBe('Les Faces doivent agir')
    expect(block.deadlineAt).toBeNull()
  })

  it('confirmations avec place libérée : date de remboursement et à vous d’agir', () => {
    const summary = makeSummary('confirmations', {
      selection: { faces_wanted: 3, faces_selected: 3, faces_confirmed: 2, freed_places: 1 },
    })
    const block = buildNextBlock(
      ctx(makeMission({ status: 'closed', freed_places_refund_at: '2026-10-23T08:00:00+00:00' }), summary),
    )
    expect(block.headline).toBe('Une place s\'est libérée')
    expect(block.text).toContain('crédité sur votre wallet')
    expect(block.who).toBe('À vous d\'agir')
    expect(block.deadlineAt).toBe('2026-10-23T08:00:00+00:00')
  })

  it('prêt : rendez-vous le jour du tournage', () => {
    const block = buildNextBlock(ctx(makeMission({ status: 'closed' }), makeSummary('ready')))
    expect(block.headline).toMatch(/^Rendez-vous le /)
    expect(block.text).toContain('Cotonou')
    expect(block.who).toBe('Prochaine étape : le tournage')
  })

  it('présences à pointer : à vous d’agir', () => {
    const summary = makeSummary('attendance', {
      attendance: { opens_at: null, can_mark: true, pending: 2, present: 0, absent: 0, disputed: 0 },
    })
    const block = buildNextBlock(ctx(makeMission({ status: 'closed' }), summary))
    expect(block.headline).toBe('Pointez les présences')
    expect(block.who).toBe('À vous d\'agir')
  })

  it('présences renseignées : affiche la raison de blocage de la clôture quand elle existe', () => {
    const summary = makeSummary('attendance', {
      attendance: { opens_at: null, can_mark: true, pending: 0, present: 2, absent: 1, disputed: 0 },
      completion_blocker: 'Une absence est encore dans sa fenêtre de contestation de 72 h.',
    })
    const block = buildNextBlock(ctx(makeMission({ status: 'pending_attendance_validation' }), summary))
    expect(block.text).toBe('Une absence est encore dans sa fenêtre de contestation de 72 h.')
    expect(block.who).toBe('En attente')
  })

  it('recrutement : candidatures reçues et date de fermeture', () => {
    const summary = makeSummary('recruiting', {
      candidatures: { ...makeSummary('recruiting').candidatures, pending: 5 },
    })
    const block = buildNextBlock(ctx(makeMission(), summary))
    expect(block.headline).toBe('Choisissez vos Faces')
    expect(block.text).toContain('5 candidatures reçues')
    expect(block.text).toContain('Les candidatures ferment le')
    expect(block.who).toBe('À vous d\'agir')
  })

  it('recrutement sans candidature : en attente', () => {
    const block = buildNextBlock(ctx(makeMission(), makeSummary('recruiting')))
    expect(block.headline).toBe('En attente de candidatures')
    expect(block.who).toBe('Les Faces peuvent postuler')
  })

  it('UGC : commission, livrables à valider, tunnel en cours', () => {
    const base = { type_mission: 'ugc', type_compensation: 'product', commission_ugc: 2500 } as Partial<Mission>
    expect(buildNextBlock(ctx(makeMission({ ...base, status: 'pending_payment' }), makeSummary('commission'))).headline).toBe(
      'Réglez la commission pour publier',
    )
    const review = makeSummary('tunnel', { ugc_deliverables: { to_review: 1, validated: 0, retouche_requested: 0 } })
    const reviewBlock = buildNextBlock(ctx(makeMission({ ...base, status: 'closed' }), review))
    expect(reviewBlock.headline).toBe('1 livrable à valider')
    expect(reviewBlock.who).toBe('À vous d\'agir')
    const running = makeSummary('tunnel', { ugc_deliverables: { to_review: 0, validated: 2, retouche_requested: 0 } })
    const runningBlock = buildNextBlock(ctx(makeMission({ ...base, status: 'closed' }), running))
    expect(runningBlock.headline).toBe('Le tunnel est en cours')
    expect(runningBlock.text).toContain('2 livrables validés')
    expect(runningBlock.who).toBe('Les Faces doivent agir')
  })

  it('terminée', () => {
    expect(buildNextBlock(ctx(makeMission({ status: 'completed' }), makeSummary('completed'))).headline).toBe('Mission terminée')
  })

  it('toutes les places remboursées : « Et ensuite ? » l\'annonce et suggère de republier', () => {
    const block = buildNextBlock(
      ctx(makeMission({ status: 'closed', has_paid_payment: true }), makeSummary('confirmations', { all_places_refunded: true })),
    )
    expect(block.headline).toBe('Toutes les places ont été remboursées')
    expect(block.text).toContain('Republiez la mission')
    expect(block.who).toBe('À vous d\'agir')
  })

  it('terminée avec des Faces à noter : la notation est présentée comme facultative', () => {
    const block = buildNextBlock(ctx(makeMission({ status: 'completed' }), makeSummary('completed', { faces_to_rate: 3 })))
    expect(block.headline).toBe('Mission terminée')
    expect(block.text).toContain('noter vos 3 Faces')
    expect(block.text).toContain('facultatif')
    expect(block.who).toBe('Rien d\'obligatoire')
  })
})

describe('compte à rebours', () => {
  const now = new Date('2026-10-20T10:00:00+00:00')

  it('formate jours / heures / minutes', () => {
    expect(formatCountdown('2026-10-22T02:00:00+00:00', now)).toBe('Reste 1 j 16 h')
    expect(formatCountdown('2026-10-20T14:22:00+00:00', now)).toBe('Reste 4 h 22')
    expect(formatCountdown('2026-10-20T10:12:00+00:00', now)).toBe('Reste 12 min')
  })

  it('échéance dépassée ou absente', () => {
    expect(formatCountdown('2026-10-20T09:00:00+00:00', now)).toBe('Échéance atteinte')
    expect(formatCountdown(null, now)).toBeNull()
    expect(formatCountdown('pas une date', now)).toBeNull()
  })
})

describe('carte Paiement', () => {
  it('standard : en attente / en séquestre / versé', () => {
    const pending = buildPaymentCard(
      makeMission(),
      makeSummary('payment', { payment: { status: 'pending', expires_at: null, paid_at: null, montant_total_producteur: 330000 } }),
    )
    expect(pending.status).toEqual({ label: 'En attente de paiement', tone: 'warn' })
    expect(pending.rows.map((r) => r.label)).toContain('Total à payer')

    const paid = { status: 'paid', expires_at: null, paid_at: '2026-10-19T10:00:00+00:00', montant_total_producteur: 330000 }
    expect(buildPaymentCard(makeMission(), makeSummary('confirmations', { payment: paid })).status.label).toBe('En séquestre')
    const done = buildPaymentCard(makeMission(), makeSummary('completed', { payment: paid }))
    expect(done.status.label).toBe('Versé aux Faces')
    expect(done.rows.map((r) => r.label)).toContain('Total payé')
  })

  it('standard sans paiement : après la sélection', () => {
    const card = buildPaymentCard(makeMission(), makeSummary('recruiting'))
    expect(card.status.label).toBe('Après votre sélection')
    expect(card.rows.find((r) => r.label === 'Faces sélectionnées')?.value).toBe('3 / 3')
  })

  it('UGC : commission à régler / payée', () => {
    const base = { type_mission: 'ugc', type_compensation: 'product', commission_ugc: 2500 } as Partial<Mission>
    expect(buildPaymentCard(makeMission({ ...base, status: 'pending_payment' }), makeSummary('commission')).status.label).toBe(
      'Commission à régler',
    )
    expect(
      buildPaymentCard(makeMission({ ...base, commission_paid_at: '2026-10-19T10:00:00+00:00' }), makeSummary('recruiting')).status.label,
    ).toBe('Commission payée')
  })
})
