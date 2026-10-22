import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import { candidatureApi } from '../../services/candidatureApi'
import CandidatureCard from '../CandidatureCard.vue'
import { StatusPill } from '@/components/ugc'
import type { Shipment } from '@/components/ugc'
import type { FaceCandidature } from '../../types'

vi.mock('../../services/candidatureApi', () => ({
  candidatureApi: { rateProducer: vi.fn() },
}))

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
    avis_deadline_at: null,
    destinataire: { nom: 'Aïcha Bello', ville: 'Cotonou', pays: 'Bénin' },
    created_at: '2026-06-12T10:00:00+00:00',
    ...overrides,
  }
}

function makeCandidature(overrides: Partial<FaceCandidature> = {}): FaceCandidature {
  return {
    id: 'cand-uuid-1',
    status: 'confirmed',
    status_label: 'Confirmée',
    message_motivation: null,
    created_at: '2026-06-10T08:00:00+00:00',
    mission: { id: 'mission-uuid-1', titre: 'Unboxing sneakers', date_tournage: '2026-07-01', lieu: 'Cotonou', budget: 0 },
    producer: { id: 'prod-uuid-1', display_name: 'Shade Fit', type: 'agency', profile_photo_url: null, profile_photo_thumbnail_url: null },
    conversation_id: 'conv-uuid-1',
    ...overrides,
  }
}

function mountCard(candidature: FaceCandidature) {
  // La racine du composant est un RouterLink.
  return mount(CandidatureCard, {
    props: { candidature },
    global: { stubs: { RouterLink: RouterLinkStub, teleport: true } },
  })
}

describe('CandidatureCard', () => {
  it('renders the compact tracking block when the candidature has a shipment', () => {
    const wrapper = mountCard(makeCandidature({ shipment: makeShipment() }))

    const block = wrapper.find('[data-testid="candidature-shipment-info"]')
    expect(block.exists()).toBe(true)
    expect(block.text()).toContain('Gozem · GZM-COT-882194')
    // Bloc purement informatif : pas de CTA « Produit reçu » en liste (D-3.4.h).
    expect(block.find('button').exists()).toBe(false)
  })

  it('omits the tracking block when there is no shipment', () => {
    const wrapper = mountCard(makeCandidature())

    expect(wrapper.find('[data-testid="candidature-shipment-info"]').exists()).toBe(false)
  })

  it('renders the pill with the server tunnel label', () => {
    const wrapper = mountCard(
      makeCandidature({
        shipment: makeShipment({ tunnel_status: 'received', tunnel_status_label: 'Produit reçu' }),
      }),
    )

    const pill = wrapper.findComponent(StatusPill)
    expect(pill.exists()).toBe(true)
    expect(pill.text()).toBe('Produit reçu')
  })

  it('keeps linking the card to the mission detail page', () => {
    const wrapper = mountCard(makeCandidature({ shipment: makeShipment() }))

    const link = wrapper.findComponent(RouterLinkStub)
    expect(link.props('to')).toEqual({ name: 'face-mission-detail', params: { id: 'mission-uuid-1' } })
  })

  describe('display status « Mission clôturée »', () => {
    it('shows the effective label in neutral grey and hides the cancel button', () => {
      const wrapper = mountCard(
        makeCandidature({
          status: 'pending',
          status_label: 'En attente',
          display_status: 'mission_closed',
          display_status_label: 'Mission clôturée',
        }),
      )

      expect(wrapper.text()).toContain('Mission clôturée')
      expect(wrapper.text()).not.toContain('En attente')
      expect(wrapper.find('[data-testid="candidature-cancel"]').exists()).toBe(false)
      expect(wrapper.find('span.bg-gray-100').exists()).toBe(true)
    })

    it('shows « Sélection en cours de paiement » in amber and keeps the cancel button', () => {
      const wrapper = mountCard(
        makeCandidature({
          status: 'pending',
          status_label: 'En attente',
          display_status: 'selection_payment_pending',
          display_status_label: 'Sélection en cours de paiement',
        }),
      )

      expect(wrapper.text()).toContain('Sélection en cours de paiement')
      expect(wrapper.text()).not.toContain('Mission clôturée')
      expect(wrapper.find('[data-testid="candidature-cancel"]').exists()).toBe(true)
      expect(wrapper.find('span.bg-yellow-100').exists()).toBe(true)
    })

    it('keeps « En attente » and the cancel button for a genuinely pending candidature', () => {
      const wrapper = mountCard(
        makeCandidature({
          status: 'pending',
          status_label: 'En attente',
          display_status: 'pending',
          display_status_label: 'En attente',
        }),
      )

      expect(wrapper.text()).toContain('En attente')
      expect(wrapper.find('[data-testid="candidature-cancel"]').exists()).toBe(true)
    })
  })

  describe('absence contest block', () => {
    it('renders the contest block when the candidature has an attendance_dispute', () => {
      const wrapper = mountCard(
        makeCandidature({
          attendance_dispute: { state: 'contestable', deadline: '2026-10-12T08:00:00+00:00', can_contest: true },
        }),
      )

      expect(wrapper.find('[data-testid="absence-contestable"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="absence-contest-btn"]').exists()).toBe(true)
    })

    it('omits the contest block without attendance_dispute', () => {
      const wrapper = mountCard(makeCandidature())

      expect(wrapper.find('[data-testid="absence-contestable"]').exists()).toBe(false)
    })
  })

  describe('reconfirmation deadline label (9-2)', () => {
    it('renders the deadline label for an accepted candidature with a reconfirm_deadline', () => {
      const wrapper = mountCard(
        makeCandidature({
          status: 'accepted',
          status_label: 'Acceptée',
          reconfirm_deadline: '2026-06-29T10:00:00+00:00',
        }),
      )

      const label = wrapper.find('[data-testid="reconfirm-deadline-label"]')
      expect(label.exists()).toBe(true)
      expect(label.text()).toContain('Reconfirmez avant le')
      // Assert the FORMATTED date is interpolated (not just the static prefix) :
      // l'année + le mois long fr-FR prouvent que formattedReconfirmDeadline a tourné
      // (l'ISO brut ne contient pas « juin »). Stables vis-à-vis du fuseau pour ce 29 juin.
      expect(label.text()).toContain('2026')
      expect(label.text()).toContain('juin')
    })

    it('omits the deadline label for an accepted candidature without a deadline (cash)', () => {
      const wrapper = mountCard(makeCandidature({ status: 'accepted', status_label: 'Acceptée' }))

      expect(wrapper.find('[data-testid="reconfirm-deadline-label"]').exists()).toBe(false)
    })

    it('omits the deadline label for a non-accepted candidature', () => {
      const wrapper = mountCard(
        makeCandidature({ status: 'confirmed', reconfirm_deadline: '2026-06-29T10:00:00+00:00' }),
      )

      expect(wrapper.find('[data-testid="reconfirm-deadline-label"]').exists()).toBe(false)
    })
  })

  describe('confirmation deadline label (lot B3)', () => {
    it('renders « À confirmer avant le … » for an accepted cash candidature with a confirmation_deadline', () => {
      const wrapper = mountCard(
        makeCandidature({
          status: 'accepted',
          status_label: 'Acceptée',
          confirmation_deadline: '2026-10-19T08:51:00+00:00',
        }),
      )

      const label = wrapper.find('[data-testid="confirmation-deadline-label"]')
      expect(label.exists()).toBe(true)
      expect(label.text()).toContain('À confirmer avant le')
      expect(label.text()).toContain('octobre')
      expect(label.text()).toMatch(/à \d{2}h\d{2}/)
    })

    it('omits the label without deadline, and for a non-accepted candidature', () => {
      expect(
        mountCard(makeCandidature({ status: 'accepted', status_label: 'Acceptée' }))
          .find('[data-testid="confirmation-deadline-label"]')
          .exists(),
      ).toBe(false)
      expect(
        mountCard(makeCandidature({ status: 'expired', status_label: 'Expirée', confirmation_deadline: '2026-10-19T08:51:00+00:00' }))
          .find('[data-testid="confirmation-deadline-label"]')
          .exists(),
      ).toBe(false)
    })
  })
})

describe('CandidatureCard — notation (lot C8)', () => {
  const completed = (overrides: Partial<FaceCandidature> = {}) =>
    makeCandidature({ status: 'completed', status_label: 'Terminée', can_rate: true, my_rating: null, ...overrides })

  it('propose « Noter le producteur » pour une candidature terminée non notée', () => {
    const wrapper = mountCard(completed())
    expect(wrapper.get('[data-testid="candidature-rate"]').text()).toBe('Noter')
  })

  it.each([
    ['déjà notée', completed({ can_rate: false, my_rating: { score: 5, comment: null, created_at: null } })],
    ['non terminée', makeCandidature({ status: 'confirmed', can_rate: false })],
    ['payload sans can_rate', makeCandidature({ status: 'completed', status_label: 'Terminée' })],
  ])('ne propose pas la notation : %s', (_label, candidature) => {
    expect(mountCard(candidature).find('[data-testid="candidature-rate"]').exists()).toBe(false)
  })

  it('ouvre le dialogue, envoie la note, émet « rated » et masque l’action', async () => {
    const rating = { score: 3, comment: null, created_at: '2026-10-20T10:00:00+00:00' }
    vi.mocked(candidatureApi.rateProducer).mockResolvedValueOnce(rating)
    const wrapper = mountCard(completed())

    await wrapper.get('[data-testid="candidature-rate"]').trigger('click')
    await wrapper.get('[data-testid="rating-star-3"]').trigger('click')
    await wrapper.get('[data-testid="rating-submit"]').trigger('click')
    await flushPromises()

    expect(candidatureApi.rateProducer).toHaveBeenCalledWith('cand-uuid-1', 3, undefined)
    expect(wrapper.emitted('rated')?.[0]).toEqual(['cand-uuid-1', rating])
    expect(wrapper.find('[data-testid="candidature-rate"]').exists()).toBe(false)
  })
})

describe('CandidatureCard — libellé / valeur et pied de carte (lot F4)', () => {
  const ugcMission = {
    id: 'mission-uuid-2',
    titre: 'Publicité pour Jolyderm',
    date_tournage: null,
    lieu: null,
    budget: 0,
    type_compensation: 'product',
    nom_produit: 'crème Jolyderm',
    nombre_videos: 2,
    montant_remuneration: null,
  }
  const factText = (wrapper: ReturnType<typeof mountCard>, key: string) =>
    wrapper.get(`[data-testid="candidature-fact-${key}"]`).text()

  it('carte UGC : Producteur, Type, Vous recevez, Prochaine étape, sans icône ni champ vide', () => {
    const wrapper = mountCard(makeCandidature({ mission: ugcMission, conversation_id: null }))

    expect(factText(wrapper, 'producer')).toBe('ProducteurShade Fit')
    expect(factText(wrapper, 'type')).toBe('TypeUGC · 2 vidéos')
    expect(factText(wrapper, 'receives')).toBe('Vous recevezProduit : crème Jolyderm')
    expect(factText(wrapper, 'next')).toBe('Prochaine étapeExpédition du produit')
    // Ni date, ni lieu, ni « 0 F CFA » pour une mission sans tournage ni argent.
    expect(wrapper.find('[data-testid="candidature-fact-shoot"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="candidature-fact-place"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('0 F CFA')
    expect(wrapper.find('svg').exists()).toBe(false)
  })

  it('carte standard : Producteur, Tournage, Lieu, Rémunération', () => {
    const wrapper = mountCard(makeCandidature({ mission: { id: 'm', titre: 'Spot', date_tournage: '2026-12-05', lieu: 'Abomey-Calavi', budget: 100000 } }))

    expect(wrapper.findAll('dt').map((dt) => dt.text())).toEqual(['Producteur', 'Tournage', 'Lieu', 'Rémunération'])
    expect(factText(wrapper, 'place')).toBe('LieuAbomey-Calavi')
  })

  it('pied de carte : date de candidature et une seule action principale', () => {
    const wrapper = mountCard(makeCandidature({ status: 'accepted', status_label: 'Acceptée', conversation_id: 'conv-1' }))

    expect(wrapper.text()).toContain('Postulé le 10/06/2026')
    expect(wrapper.text()).toContain('À confirmer par vous')
    expect(wrapper.find('[data-testid="candidature-confirm"]').text()).toBe('Confirmer')
    // Le chat reste joignable en second geste (icône), sans devenir une deuxième action texte.
    expect(wrapper.find('[data-testid="candidature-chat-secondary"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="candidature-chat"]').exists()).toBe(false)
  })

  it('candidature non retenue : « Non retenue » et « Voir d’autres missions »', () => {
    const wrapper = mountCard(makeCandidature({ status: 'rejected', status_label: 'Refusée', conversation_id: null }))

    expect(wrapper.text()).toContain('Non retenue')
    expect(wrapper.find('[data-testid="candidature-browse"]').text()).toBe('Voir d\'autres missions')
  })
})
