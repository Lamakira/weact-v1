import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import UgcFaceTrackingCard from '../UgcFaceTrackingCard.vue'
import StatusPill from '../StatusPill.vue'
import ChronoRing from '../ChronoRing.vue'
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
    destinataire: { nom: 'Aïcha Bello', ville: 'Cotonou', pays: 'Bénin' },
    created_at: '2026-06-12T10:00:00+00:00',
    ...overrides,
  }
}

const receivedShipment = () =>
  makeShipment({
    tunnel_status: 'received',
    tunnel_status_label: 'Produit reçu',
    recu_le: '2026-06-12T12:00:00+00:00',
    unboxing_deadline_at: '2026-06-19T12:00:00+00:00',
  })

function mountCard(shipment: Shipment, extraProps: { current?: number; isSubmitting?: boolean } = {}) {
  return mount(UgcFaceTrackingCard, {
    props: { shipment, current: extraProps.current ?? 4, isSubmitting: extraProps.isSubmitting },
  })
}

describe('UgcFaceTrackingCard', () => {
  it('renders the vertical timeline at the given step', () => {
    const wrapper = mountCard(makeShipment(), { current: 4 })
    const timeline = wrapper.find('[data-testid="ugc-timeline-v"]')
    expect(timeline.exists()).toBe(true)
    const active = timeline.findAll('[data-step-state="active"]')
    expect(active).toHaveLength(1)
    expect(active[0].text()).toContain('4')
  })

  it('renders the StatusPill with the server label', () => {
    const wrapper = mountCard(makeShipment())
    const pill = wrapper.findComponent(StatusPill)
    expect(pill.exists()).toBe(true)
    expect(pill.text()).toBe('Produit expédié')
  })

  it('renders carrier, tracking number and shipped date', () => {
    const wrapper = mountCard(makeShipment())
    expect(wrapper.text()).toContain('Gozem')
    expect(wrapper.text()).toContain('GZM-COT-882194')
    expect(wrapper.text()).toContain('12 juin 2026')
  })

  it('renders the note and the received date when present', () => {
    const wrapper = mountCard(
      { ...receivedShipment(), note_envoi: 'Colis remis au gardien' },
      { current: 5 },
    )
    expect(wrapper.text()).toContain('Colis remis au gardien')
    expect(wrapper.text()).toContain('Reçu le')
  })

  it('shows the confirm-receipt CTA while shipped', () => {
    const wrapper = mountCard(makeShipment())
    const btn = wrapper.find('[data-testid="confirm-receipt-btn"]')
    expect(btn.exists()).toBe(true)
    expect(btn.text()).toContain('Produit reçu')
    // Copy statique : « 7 jours » (UGC_UNBOXING_DAYS), jamais une date calculée.
    expect(wrapper.find('[data-testid="ugc-receipt-cta-zone"]').text()).toContain('7 jours')
    expect(wrapper.find('[data-testid="ugc-chrono-section"]').exists()).toBe(false)
  })

  it('emits confirm-receipt when the CTA is clicked', async () => {
    const wrapper = mountCard(makeShipment())
    await wrapper.find('[data-testid="confirm-receipt-btn"]').trigger('click')
    expect(wrapper.emitted('confirm-receipt')).toHaveLength(1)
  })

  it('disables the CTA while submitting', () => {
    const wrapper = mountCard(makeShipment(), { isSubmitting: true })
    const btn = wrapper.find('[data-testid="confirm-receipt-btn"]')
    expect(btn.attributes('disabled')).toBeDefined()
  })

  it('shows the chrono section with the ChronoRing once received', () => {
    const wrapper = mountCard(receivedShipment(), { current: 5 })
    expect(wrapper.find('[data-testid="ugc-chrono-section"]').exists()).toBe(true)
    expect(wrapper.findComponent(ChronoRing).exists()).toBe(true)
    expect(wrapper.find('[data-testid="confirm-receipt-btn"]').exists()).toBe(false)
  })

  it('renders the unboxing deadline and the suspension warning while the chrono runs', () => {
    const wrapper = mountCard(receivedShipment(), { current: 5 })
    const section = wrapper.find('[data-testid="ugc-chrono-section"]')
    // La deadline affichée vient de unboxing_deadline_at (serveur), jamais d'un calcul front.
    expect(section.text()).toContain('À envoyer avant le')
    expect(section.text()).toContain('19 juin 2026')
    expect(section.text()).toContain('automatiquement suspendu')
  })

  it('shows neither CTA nor chrono for unknown tunnel statuses', () => {
    const wrapper = mountCard(
      makeShipment({ tunnel_status: 'some_future_status', tunnel_status_label: 'État réservé' }),
    )
    expect(wrapper.find('[data-testid="confirm-receipt-btn"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="ugc-chrono-section"]').exists()).toBe(false)
    // Timeline + tracking restent rendus (déploiement backend seul ne casse rien, D-3.2.i).
    expect(wrapper.find('[data-testid="ugc-timeline-v"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('GZM-COT-882194')
  })
})
