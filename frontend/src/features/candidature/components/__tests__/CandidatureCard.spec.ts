import { describe, expect, it } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import CandidatureCard from '../CandidatureCard.vue'
import { StatusPill } from '@/components/ugc'
import type { Shipment } from '@/components/ugc'
import type { FaceCandidature } from '../../types'

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
    global: { stubs: { RouterLink: RouterLinkStub } },
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
      expect(wrapper.text()).not.toContain('Annuler ma candidature')
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
      expect(wrapper.text()).toContain('Annuler ma candidature')
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
      expect(wrapper.text()).toContain('Annuler ma candidature')
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
