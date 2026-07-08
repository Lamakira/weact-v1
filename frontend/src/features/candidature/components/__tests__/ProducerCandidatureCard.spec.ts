import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ProducerCandidatureCard from '../ProducerCandidatureCard.vue'
import WBadge from '@/components/ui/WBadge.vue'

const pendingCandidature = {
  id: 'cand-1',
  mission_id: 'mission-1',
  face_id: 'face-1',
  status: 'pending' as const,
  status_label: 'En attente',
  message_motivation: 'Test motivation',
  created_at: '2026-04-14T10:00:00Z',
  updated_at: '2026-04-14T10:00:00Z',
  conversation_id: null,
  face: {
    id: 'face-1',
    display_name: 'Alice Martin',
    profile_photo_url: null,
    profile_photo_thumbnail_url: null,
    city: 'Paris',
    category: 'acteur',
    tarif_horaire: null,
    tarif_journalier: 100000,
    has_elite_badge: false,
  },
}

const confirmedUgcCandidature = {
  ...pendingCandidature,
  id: 'candidature-uuid-1',
  status: 'confirmed' as const,
  status_label: 'Confirmée',
  message_motivation: null,
  conversation_id: 'conv-uuid-1',
  face: {
    ...pendingCandidature.face,
    id: 'face-uuid-1',
    display_name: 'Aïcha Bello',
    city: 'Cotonou',
    category: null,
    tarif_journalier: null,
  },
}

function makeShipment(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    id: 'shipment-uuid-1',
    transporteur: 'Gozem',
    numero_suivi: 'GZM-COT-882194',
    note_envoi: null,
    tunnel_status: 'shipped',
    tunnel_status_label: 'Produit expédié',
    shipped_at: '2026-06-12T10:00:00+00:00',
    recu_le: null,
    destinataire: { nom: 'Aïcha Bello', ville: 'Cotonou', pays: 'Bénin' },
    created_at: '2026-06-12T10:00:00+00:00',
    ...overrides,
  }
}

describe('ProducerCandidatureCard', () => {
  it('does not render the legacy "Accepter" button outside selection mode (FIX-20.3)', () => {
    const wrapper = mount(ProducerCandidatureCard, {
      props: {
        candidature: pendingCandidature,
        selectionMode: false,
      },
      global: {
        stubs: {
          RouterLink: true,
          Teleport: true,
        },
      },
    })

    const buttons = wrapper.findAll('button')
    const acceptButton = buttons.find((btn) => btn.text().includes('Accepter'))
    expect(acceptButton).toBeUndefined()
  })

  describe('FP-2.12 — Élite badge (WBadge V13 design refresh)', () => {
    const routerLinkStub = {
      template: '<a><slot /></a>',
      props: ['to'],
    }

    it('renders WBadge in elite tier at 14px next to display_name when face.has_elite_badge is true', () => {
      const wrapper = mount(ProducerCandidatureCard, {
        props: {
          candidature: {
            ...pendingCandidature,
            face: { ...pendingCandidature.face, has_elite_badge: true },
          },
        },
        global: { stubs: { RouterLink: routerLinkStub, Teleport: true } },
      })
      const badge = wrapper.findComponent(WBadge)
      expect(badge.exists()).toBe(true)
      expect(badge.props('tier')).toBe('elite')
      expect(badge.props('size')).toBe(14)
      expect(wrapper.text()).toContain('Alice Martin')
    })

    it('does not render the Élite badge when face.has_elite_badge is false', () => {
      const wrapper = mount(ProducerCandidatureCard, {
        props: { candidature: pendingCandidature },
        global: { stubs: { RouterLink: routerLinkStub, Teleport: true } },
      })
      expect(wrapper.findComponent(WBadge).exists()).toBe(false)
      expect(wrapper.text()).toContain('Alice Martin')
    })
  })

  describe('UGC shipment (story 3.2)', () => {
    function mountCard(candidature: Record<string, unknown>, isUgcMission?: boolean) {
      return mount(ProducerCandidatureCard, {
        props: {
          candidature: candidature as never,
          isUgcMission,
        },
        global: {
          stubs: {
            RouterLink: { template: '<a><slot /></a>', props: ['to'] },
            Teleport: true,
          },
        },
      })
    }

    it('shows the confirm-shipment button for a confirmed candidature on a UGC mission', async () => {
      const wrapper = mountCard(confirmedUgcCandidature, true)

      const button = wrapper.find('[data-testid="confirm-shipment-card-btn"]')
      expect(button.exists()).toBe(true)
      expect(button.text()).toContain("Confirmer l'envoi")

      await button.trigger('click')
      expect(wrapper.emitted('confirm-shipment')).toHaveLength(1)
    })

    it('hides the confirm-shipment button on non-UGC missions', () => {
      const wrapper = mountCard(confirmedUgcCandidature, false)

      expect(wrapper.find('[data-testid="confirm-shipment-card-btn"]').exists()).toBe(false)
    })

    it('hides the confirm-shipment button for pending candidatures', () => {
      const wrapper = mountCard(pendingCandidature, true)

      expect(wrapper.find('[data-testid="confirm-shipment-card-btn"]').exists()).toBe(false)
    })

    it('renders the compact tracking block instead of the button once shipped', () => {
      const wrapper = mountCard({ ...confirmedUgcCandidature, shipment: makeShipment() }, true)

      expect(wrapper.find('[data-testid="confirm-shipment-card-btn"]').exists()).toBe(false)

      const tracking = wrapper.find('[data-testid="candidature-shipment-info"]')
      expect(tracking.exists()).toBe(true)
      // Label serveur + transporteur · numéro de suivi.
      expect(tracking.text()).toContain('Produit expédié')
      expect(tracking.text()).toContain('Gozem · GZM-COT-882194')
    })
  })

  describe('UGC accept (8.3)', () => {
    function mountCard(isUgcMission?: boolean, ugcCompensationType?: string | null) {
      return mount(ProducerCandidatureCard, {
        props: {
          candidature: pendingCandidature,
          isUgcMission,
          ugcCompensationType,
        },
        global: {
          stubs: {
            RouterLink: { template: '<a><slot /></a>', props: ['to'] },
            Teleport: true,
          },
        },
      })
    }

    it('shows the "Accepter" button for a product-only UGC pending candidature and emits accept on click', async () => {
      const wrapper = mountCard(true, 'product')

      const acceptBtn = wrapper.find('[data-testid="accept-candidature-btn"]')
      expect(acceptBtn.exists()).toBe(true)

      await acceptBtn.trigger('click')
      expect(wrapper.emitted('accept')).toHaveLength(1)
      expect(wrapper.emitted('accept')?.[0]).toEqual(['cand-1'])
    })

    it('shows the "Accepter" button on a hybrid UGC mission and emits accept on click (8-5)', async () => {
      const wrapper = mountCard(true, 'hybrid')

      const acceptBtn = wrapper.find('[data-testid="accept-candidature-btn"]')
      expect(acceptBtn.exists()).toBe(true)

      await acceptBtn.trigger('click')
      expect(wrapper.emitted('accept')).toHaveLength(1)
      expect(wrapper.emitted('accept')?.[0]).toEqual(['cand-1'])
    })

    it('keeps the "Refuser" button available for a product-only UGC pending candidature', () => {
      const wrapper = mountCard(true, 'product')

      const rejectButton = wrapper.findAll('button').find((btn) => btn.text().includes('Refuser'))
      expect(rejectButton).toBeDefined()
    })
  })

  describe('UGC release (9-2)', () => {
    const acceptedUgcCandidature = {
      ...pendingCandidature,
      status: 'accepted' as const,
      status_label: 'Acceptée',
    }

    function mountCard(
      candidature: Record<string, unknown>,
      isUgcMission?: boolean,
      ugcCompensationType?: string | null,
    ) {
      return mount(ProducerCandidatureCard, {
        props: {
          candidature: candidature as never,
          isUgcMission,
          ugcCompensationType,
        },
        global: {
          stubs: {
            RouterLink: { template: '<a><slot /></a>', props: ['to'] },
            Teleport: true,
          },
        },
      })
    }

    it('renders the release button for an accepted candidature on a UGC mission', () => {
      const wrapper = mountCard(acceptedUgcCandidature, true)

      const button = wrapper.find('[data-testid="release-candidature-btn"]')
      expect(button.exists()).toBe(true)
      expect(button.text()).toContain('Libérer la place')
    })

    it('hides the release button for a pending candidature', () => {
      const wrapper = mountCard(pendingCandidature, true)

      expect(wrapper.find('[data-testid="release-candidature-btn"]').exists()).toBe(false)
    })

    it('hides the release button for a confirmed candidature', () => {
      const wrapper = mountCard(confirmedUgcCandidature, true)

      expect(wrapper.find('[data-testid="release-candidature-btn"]').exists()).toBe(false)
    })

    it('hides the release button on a non-UGC mission', () => {
      const wrapper = mountCard(acceptedUgcCandidature, false)

      expect(wrapper.find('[data-testid="release-candidature-btn"]').exists()).toBe(false)
    })

    it('opens the dialog and emits release with the id when confirmed', async () => {
      const wrapper = mountCard(acceptedUgcCandidature, true)

      await wrapper.find('[data-testid="release-candidature-btn"]').trigger('click')

      const confirmBtn = wrapper
        .findAll('button')
        .find((btn) => btn.text().includes('Confirmer la libération'))
      expect(confirmBtn).toBeDefined()

      await confirmBtn!.trigger('click')
      expect(wrapper.emitted('release')).toHaveLength(1)
      expect(wrapper.emitted('release')?.[0]).toEqual(['cand-1'])
    })

    it('does not emit release when the dialog is cancelled', async () => {
      const wrapper = mountCard(acceptedUgcCandidature, true)

      await wrapper.find('[data-testid="release-candidature-btn"]').trigger('click')

      const cancelBtn = wrapper
        .findAll('button')
        .find((btn) => btn.text().includes('Annuler'))
      expect(cancelBtn).toBeDefined()

      await cancelBtn!.trigger('click')
      expect(wrapper.emitted('release')).toBeUndefined()
    })

    it('promises the escrow refund only for a hybrid mission (honest copy, D-9.2.c)', async () => {
      const wrapper = mountCard(acceptedUgcCandidature, true, 'hybrid')

      await wrapper.find('[data-testid="release-candidature-btn"]').trigger('click')

      expect(wrapper.text()).toContain('vous remboursera le règlement séquestré')
    })

    it('omits the refund clause for a product-only mission (no escrow to refund)', async () => {
      const wrapper = mountCard(acceptedUgcCandidature, true, 'product')

      await wrapper.find('[data-testid="release-candidature-btn"]').trigger('click')

      expect(wrapper.text()).not.toContain('règlement séquestré')
      expect(wrapper.text()).toContain('annulera sa candidature et rouvrira la mission')
    })

    it('enters the loading state on confirm and clears it via the exposed resetReleasing()', async () => {
      const wrapper = mountCard(acceptedUgcCandidature, true)

      await wrapper.find('[data-testid="release-candidature-btn"]').trigger('click')
      const confirmBtn = wrapper
        .findAll('button')
        .find((btn) => btn.text().includes('Confirmer la libération'))
      await confirmBtn!.trigger('click')

      // confirmRelease : isReleasing=true → spinner « Libération... » + bouton désactivé
      // (surface de la garde anti-double-clic et du :disabled).
      const releaseBtn = wrapper.find('[data-testid="release-candidature-btn"]')
      expect(releaseBtn.text()).toContain('Libération...')
      expect(releaseBtn.attributes('disabled')).toBeDefined()

      // resetReleasing() (exposé, appelé par la section après la réponse) repasse au repos.
      const vm = wrapper.vm as unknown as { resetReleasing: () => void }
      vm.resetReleasing()
      await wrapper.vm.$nextTick()

      const releaseBtnAfter = wrapper.find('[data-testid="release-candidature-btn"]')
      expect(releaseBtnAfter.text()).toContain('Libérer la place')
      expect(releaseBtnAfter.attributes('disabled')).toBeUndefined()
    })
  })
})
