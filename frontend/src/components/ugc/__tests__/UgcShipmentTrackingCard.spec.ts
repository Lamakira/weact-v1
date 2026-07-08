import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import UgcShipmentTrackingCard from '../UgcShipmentTrackingCard.vue'
import StatusPill from '../StatusPill.vue'
import type { Shipment } from '../ugc'

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

function mountCard(overrides: Partial<Shipment> = {}) {
  return mount(UgcShipmentTrackingCard, {
    props: { shipment: makeShipment(overrides) },
  })
}

describe('UgcShipmentTrackingCard', () => {
  it('renders carrier, tracking number and shipped date', () => {
    const wrapper = mountCard()

    expect(wrapper.text()).toContain('Gozem')
    expect(wrapper.text()).toContain('GZM-COT-882194')
    // Intl fr-FR dateStyle long : « 12 juin 2026 » (l'heure dépend du fuseau du runner).
    expect(wrapper.text()).toContain('12 juin 2026')
  })

  it('renders the StatusPill with the server label', () => {
    const wrapper = mountCard()

    const pill = wrapper.findComponent(StatusPill)
    expect(pill.exists()).toBe(true)
    expect(pill.props('kind')).toBe('shipped')
    // Label serveur autoritatif — jamais de label local.
    expect(pill.text()).toContain('Produit expédié')
  })

  it('renders the destinataire line omitting null parts', () => {
    const wrapper = mountCard({
      destinataire: { nom: 'Aïcha Bello', ville: null, pays: 'Bénin' },
    })

    expect(wrapper.text()).toContain('Aïcha Bello · Bénin')
    expect(wrapper.text()).not.toContain('Aïcha Bello · · Bénin')
  })

  it('renders the note when present', () => {
    const withNote = mountCard({ note_envoi: 'Appeler avant livraison' })
    expect(withNote.text()).toContain('Appeler avant livraison')

    const withoutNote = mountCard({ note_envoi: null })
    expect(withoutNote.text()).not.toContain('Appeler avant livraison')
  })

  it('shows the chrono reminder only while shipped', () => {
    const shipped = mountCard({ tunnel_status: 'shipped' })
    expect(shipped.find('[data-testid="chrono-reminder"]').exists()).toBe(true)

    const received = mountCard({ tunnel_status: 'received', tunnel_status_label: 'Produit reçu' })
    expect(received.find('[data-testid="chrono-reminder"]').exists()).toBe(false)
  })

  it('renders the reception photos gallery when the Face has attached proof', () => {
    const wrapper = mountCard({
      tunnel_status: 'received',
      tunnel_status_label: 'Produit reçu',
      reception_photos: [
        { id: 'p1', position: 1, photo_url: '/x/p1', grid_url: '/x/p1/grid', large_url: '/x/p1/large' },
        { id: 'p2', position: 2, photo_url: '/x/p2', grid_url: '/x/p2/grid', large_url: '/x/p2/large' },
      ],
    })

    expect(wrapper.find('[data-testid="product-photo-gallery"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Photos du produit reçu')
    expect(wrapper.findAll('[data-testid="product-photo-thumb"]')).toHaveLength(2)
  })

  it('renders no reception section for a pre-deploy shipment without photos', () => {
    // Tolérance zéro-photo (rétrocompat) : aucune section, aucun placeholder.
    const wrapper = mountCard({ tunnel_status: 'received', tunnel_status_label: 'Produit reçu' })
    expect(wrapper.find('[data-testid="product-photo-gallery"]').exists()).toBe(false)
  })
})
