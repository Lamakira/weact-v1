import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import FaceMissionHub from '../FaceMissionHub.vue'
import type { Mission, MissionCandidature } from '@/features/mission/types'

function makeMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 'mission-uuid-1',
    titre: 'Spot TV bissap',
    description: 'Une scène de marché à Dantokpa.',
    date_tournage: '2026-10-23',
    profil_recherche: 'Femme, 20-35 ans',
    budget: 100000,
    genre_voulu: 'femme',
    genre_voulu_label: 'Femme',
    lieu: 'Cotonou',
    duree: '1 jour',
    type_mission: 'publicite',
    nom_produit: null,
    montant_remuneration: null,
    producer: {
      id: 'p1',
      slug: 'tropical-benin',
      type: 'agency',
      agency_name: 'Tropical Bénin',
      profile_photo_url: null,
      thumbnail_url: null,
      average_rating: null,
      ratings_count: 0,
    },
    ...overrides,
  } as unknown as Mission
}

function makeCandidature(overrides: Partial<MissionCandidature> = {}): MissionCandidature {
  return {
    id: 'cand-1',
    mission_id: 'mission-uuid-1',
    face_id: 'f1',
    status: 'pending',
    status_label: 'En attente',
    message_motivation: null,
    created_at: '2026-10-01T08:00:00+00:00',
    updated_at: '2026-10-01T08:00:00+00:00',
    ...overrides,
  }
}

function mountHub(candidature: MissionCandidature, extra: { isUgc?: boolean; mission?: Mission } = {}) {
  return mount(FaceMissionHub, {
    props: {
      mission: extra.mission ?? makeMission(),
      candidature,
      isUgc: extra.isUgc ?? false,
      producerName: 'Tropical Bénin',
      busy: false,
      showFaceTrackingCard: false,
      candidatureShipment: null,
      ugcTrackingStep: 0,
      isSubmittingReceipt: false,
      isUploadingDeliverable: false,
      uploadProgress: null,
    },
    global: {
      stubs: {
        RouterLink: { template: '<a data-testid="rl" :data-to="JSON.stringify(to)"><slot /></a>', props: ['to'] },
        RatingDisplay: true,
        UgcFaceTrackingCard: true,
        MissionAbsenceContestBlock: true,
      },
    },
  })
}

afterEach(() => {
  vi.useRealTimers()
})

describe('FaceMissionHub — états honnêtes', () => {
  it('candidature refusée : « Non retenue » grise, jamais le pictogramme vert « envoyée »', () => {
    const wrapper = mountHub(makeCandidature({ status: 'rejected', status_label: 'Refusée' }))
    const pill = wrapper.get('[data-testid="hub-pill"]')

    expect(pill.text()).toBe('Non retenue')
    expect(pill.attributes('data-tone')).toBe('mute')
    expect(pill.classes().join(' ')).not.toMatch(/emerald|green/)
    expect(wrapper.text()).not.toContain('Candidature envoyée')
    expect(wrapper.get('[data-testid="hub-action-desktop"]').text()).toBe('Voir d’autres missions')
  })

  it('expirée : affiche le libellé du backend', () => {
    const wrapper = mountHub(
      makeCandidature({ status: 'expired', display_status: 'expired', display_status_label: 'Sans réponse du producteur' }),
    )
    expect(wrapper.get('[data-testid="hub-pill"]').text()).toBe('Sans réponse du producteur')
    expect(wrapper.get('[data-testid="hub-next-headline"]').text()).toBe('Le producteur n’a pas répondu')
  })

  it('sélection en cours de paiement : ambre + retrait possible', () => {
    const wrapper = mountHub(makeCandidature({ display_status: 'selection_payment_pending' }))
    expect(wrapper.get('[data-testid="hub-pill"]').attributes('data-tone')).toBe('warn')
    expect(wrapper.get('[data-testid="hub-next-headline"]').text()).toBe('Le producteur finalise sa sélection')
    expect(wrapper.get('[data-testid="hub-action-desktop"]').text()).toBe('Retirer ma candidature')
  })

  it('confirmée : verte, « Rendez-vous le … »', () => {
    const wrapper = mountHub(makeCandidature({ status: 'confirmed', conversation_id: 'conv-1' }))
    expect(wrapper.get('[data-testid="hub-pill"]').attributes('data-tone')).toBe('ok')
    expect(wrapper.get('[data-testid="hub-next-headline"]').text()).toMatch(/^Rendez-vous le /)
  })

  it('terminée : payée', () => {
    const wrapper = mountHub(makeCandidature({ status: 'completed', conversation_id: 'conv-1' }))
    expect(wrapper.get('[data-testid="hub-pill"]').text()).toBe('Payée')
  })
})

describe('FaceMissionHub — confirmation de participation', () => {
  const accepted = () =>
    makeCandidature({
      status: 'accepted',
      status_label: 'Acceptée',
      confirmation_deadline: new Date(Date.now() + 40 * 3600_000).toISOString(),
      montant_face_recoit: 85000,
      conversation_id: 'conv-1',
    })

  it('expose l’action « Confirmer ma participation » avec échéance, et émet l’action', async () => {
    const wrapper = mountHub(accepted())
    const btn = wrapper.get('[data-testid="hub-action-desktop"]')

    expect(btn.text()).toContain('Confirmer ma participation')
    expect(btn.text()).toContain('Avant le')

    await btn.trigger('click')
    expect(wrapper.emitted('action')).toEqual([['confirm']])
  })

  it('affiche le compte à rebours, le « Vous recevrez » et l’intitulé dans la barre mobile', () => {
    const wrapper = mountHub(accepted())

    expect(wrapper.get('[data-testid="hub-countdown"]').text()).toMatch(/Reste 1 j 1\d h|Reste 1 j 2\d h|Reste 1 j \d+ h/)
    expect(wrapper.get('[data-testid="hub-money"]').text()).toContain('Vous recevrez 85 000 FCFA')
    const bar = wrapper.get('[data-testid="hub-actionbar"]')
    expect(bar.text()).toContain('Vous êtes retenue')
    expect(bar.get('[data-testid="hub-action-mobile"]').text()).toBe('Confirmer ma participation')
  })

  it('désactive l’action principale pendant une requête', () => {
    const wrapper = mountHub(accepted())
    return wrapper.setProps({ busy: true }).then(() => {
      expect(wrapper.get('[data-testid="hub-action-desktop"]').attributes('disabled')).toBeDefined()
      expect(wrapper.get('[data-testid="hub-action-mobile"]').attributes('disabled')).toBeDefined()
    })
  })

  it('met à jour le compte à rebours chaque minute', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-20T10:00:00Z'))
    const wrapper = mountHub(
      makeCandidature({ status: 'accepted', confirmation_deadline: '2026-10-20T13:40:00Z' }),
    )
    expect(wrapper.get('[data-testid="hub-countdown"]').text()).toBe('Reste 3 h 40')

    await vi.advanceTimersByTimeAsync(60_000)
    expect(wrapper.get('[data-testid="hub-countdown"]').text()).toBe('Reste 3 h 39')
  })
})

describe('FaceMissionHub — chat', () => {
  it.each(['accepted', 'confirmed', 'in_progress', 'completed'])(
    'ouvre le chat (route face-conversation) quand la candidature est %s',
    (status) => {
      const wrapper = mountHub(makeCandidature({ status, conversation_id: 'conv-42' }))
      const link = wrapper.get('[data-testid="hub-chat-link"]')

      expect(link.text()).toContain('Discuter avec le producteur')
      expect(JSON.parse(link.attributes('data-to') as string)).toEqual({
        name: 'face-conversation',
        params: { conversationId: 'conv-42' },
      })
      expect(wrapper.find('[data-testid="hub-chat-locked"]').exists()).toBe(false)
    },
  )

  it.each(['pending', 'rejected', 'expired', 'cancelled'])('garde le chat verrouillé quand la candidature est %s', (status) => {
    const wrapper = mountHub(makeCandidature({ status }))
    expect(wrapper.find('[data-testid="hub-chat-link"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="hub-chat-locked"]').text()).toContain('s’ouvre quand votre place est payée et confirmée')
  })
})

describe('FaceMissionHub — barre d’étapes et mouvement', () => {
  it('rend 5 étapes, coche les étapes passées et marque l’étape courante', () => {
    const wrapper = mountHub(makeCandidature({ status: 'accepted' }))
    const items = wrapper.findAll('[data-testid="hub-steps"] li')

    expect(items).toHaveLength(5)
    expect(items.map((li) => li.attributes('data-step-state'))).toEqual(['done', 'done', 'now', 'todo', 'todo'])
    expect(items[2]!.attributes('aria-current')).toBe('step')
  })

  it('remplit la barre via transform scaleX (propriété animée = transform seulement)', async () => {
    const wrapper = mountHub(makeCandidature({ status: 'accepted' }))
    expect(wrapper.get('[data-testid="hub-fill"]').attributes('style')).toContain('scaleX(0.5)')

    await wrapper.setProps({ candidature: makeCandidature({ status: 'confirmed', conversation_id: 'c' }) })
    expect(wrapper.get('[data-testid="hub-fill"]').attributes('style')).toContain('scaleX(0.75)')
  })

  it('utilise les étapes UGC pour une mission UGC', () => {
    const wrapper = mountHub(makeCandidature({ status: 'pending' }), {
      isUgc: true,
      mission: makeMission({ nom_produit: 'Sneakers', montant_remuneration: 20000 }),
    })
    expect(wrapper.get('[data-testid="hub-steps"]').text()).toContain('Livrables')
    expect(wrapper.get('[data-testid="hub-eyebrow"]').text()).toBe('Tropical Bénin · Mission UGC')
  })

  it('l’en-tête indique « Mission standard » avec date et lieu', () => {
    const wrapper = mountHub(makeCandidature())
    expect(wrapper.get('[data-testid="hub-eyebrow"]').text()).toBe('Tropical Bénin · Mission standard')
    expect(wrapper.get('[data-testid="hub-date"]').text()).toContain('Tournage')
    expect(wrapper.text()).toContain('Cotonou')
  })
})

describe('FaceMissionHub — blocs UGC existants', () => {
  it('monte le bloc de contestation d’absence quand attendance_dispute est présent', () => {
    const wrapper = mountHub(
      makeCandidature({
        status: 'confirmed',
        attendance_dispute: { state: 'contestable', deadline: '2026-10-12T08:00:00+00:00', can_contest: true },
      }),
    )
    expect(wrapper.findComponent({ name: 'MissionAbsenceContestBlock' }).exists()).toBe(true)
  })

  it('ne monte pas le bloc de contestation sans attendance_dispute', () => {
    const wrapper = mountHub(makeCandidature({ status: 'confirmed' }))
    expect(wrapper.findComponent({ name: 'MissionAbsenceContestBlock' }).exists()).toBe(false)
  })
})
