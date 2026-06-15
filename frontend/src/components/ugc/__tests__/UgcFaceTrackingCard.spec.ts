import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import UgcFaceTrackingCard from '../UgcFaceTrackingCard.vue'
import StatusPill from '../StatusPill.vue'
import ChronoRing from '../ChronoRing.vue'
import type { Deliverable, Shipment, UgcUploadProgress } from '../ugc'

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

const receivedShipment = () =>
  makeShipment({
    tunnel_status: 'received',
    tunnel_status_label: 'Produit reçu',
    recu_le: '2026-06-12T12:00:00+00:00',
    unboxing_deadline_at: '2026-06-19T12:00:00+00:00',
  })

function mountCard(
  shipment: Shipment,
  extra: {
    current?: number
    isSubmitting?: boolean
    isUploading?: boolean
    uploadProgress?: UgcUploadProgress | null
    deliverables?: Deliverable[]
  } = {},
) {
  return mount(UgcFaceTrackingCard, {
    props: {
      shipment,
      current: extra.current ?? 4,
      isSubmitting: extra.isSubmitting,
      isUploading: extra.isUploading,
      uploadProgress: extra.uploadProgress ?? null,
      deliverables: extra.deliverables ?? [],
    },
  })
}

// --- Fixtures phase Avis (4.6) ---
const avisPendingShipment = () =>
  makeShipment({
    tunnel_status: 'avis_pending',
    tunnel_status_label: 'Avis en attente',
    recu_le: '2026-06-12T12:00:00+00:00',
    unboxing_deadline_at: '2026-06-19T12:00:00+00:00',
    avis_deadline_at: '2026-06-26T12:00:00+00:00',
  })

const avisInReviewShipment = () =>
  makeShipment({
    tunnel_status: 'avis_in_review',
    tunnel_status_label: 'Avis en validation',
    recu_le: '2026-06-12T12:00:00+00:00',
    avis_deadline_at: '2026-06-26T12:00:00+00:00',
  })

// Unboxing validé par défaut (start du chrono Avis = validated_at, D-4.6.d).
function makeDeliverable(overrides: Partial<Deliverable> = {}): Deliverable {
  return {
    id: 'deliverable-uuid-1',
    kind: 'unboxing',
    kind_label: 'Unboxing',
    validation_status: 'validated',
    validation_status_label: 'Validée',
    review_note: null,
    validated_at: '2026-06-13T12:00:00+00:00',
    chrono_started_at: '2026-06-12T12:00:00+00:00',
    deadline_at: '2026-06-19T12:00:00+00:00',
    duree_seconds: 42,
    created_at: '2026-06-12T13:00:00+00:00',
    ...overrides,
  }
}

const inReviewShipment = () =>
  makeShipment({
    tunnel_status: 'unboxing_in_review',
    tunnel_status_label: 'Unboxing en validation',
    recu_le: '2026-06-12T12:00:00+00:00',
    unboxing_deadline_at: '2026-06-19T12:00:00+00:00',
  })

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

  it('renders the upload dropzone in the chrono section while received', () => {
    const wrapper = mountCard(receivedShipment(), { current: 5 })
    const section = wrapper.find('[data-testid="ugc-chrono-section"]')
    expect(section.exists()).toBe(true)
    expect(section.find('[data-testid="ugc-upload-dropzone"]').exists()).toBe(true)
    expect(section.find('[data-testid="ugc-upload-input"]').exists()).toBe(true)
    // Copy 8A réelle : « Uploade » (le placeholder « Prépare » de 3.4 est levé).
    expect(section.text()).toContain('Uploade ta vidéo Unboxing')
    expect(section.text()).toContain('MP4, MOV ou AVI · max 200 Mo')
  })

  it('emits upload when a video file is selected', async () => {
    const wrapper = mountCard(receivedShipment(), { current: 5 })
    const file = new File(['x'], 'unboxing.mp4', { type: 'video/mp4' })
    const input = wrapper.find('[data-testid="ugc-upload-input"]')
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')
    expect(wrapper.emitted('upload')?.[0]).toEqual([file])
  })

  it('emits upload when a video file is dropped', async () => {
    const wrapper = mountCard(receivedShipment(), { current: 5 })
    const file = new File(['x'], 'unboxing.mp4', { type: 'video/mp4' })
    await wrapper
      .find('[data-testid="ugc-upload-dropzone"]')
      .trigger('drop', { dataTransfer: { files: [file] } })
    expect(wrapper.emitted('upload')?.[0]).toEqual([file])
  })

  it('does not emit upload while an upload is in progress', async () => {
    const wrapper = mountCard(receivedShipment(), { current: 5, isUploading: true })
    const file = new File(['x'], 'unboxing.mp4', { type: 'video/mp4' })
    const input = wrapper.find('[data-testid="ugc-upload-input"]')
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')
    await wrapper
      .find('[data-testid="ugc-upload-dropzone"]')
      .trigger('drop', { dataTransfer: { files: [file] } })
    expect(wrapper.emitted('upload')).toBeUndefined()
  })

  it('shows the upload progress overlay while uploading', () => {
    const wrapper = mountCard(receivedShipment(), {
      current: 5,
      isUploading: true,
      uploadProgress: { loaded: 42, total: 100, percentage: 42 },
    })
    const overlay = wrapper.find('[data-testid="ugc-upload-progress"]')
    expect(overlay.exists()).toBe(true)
    expect(overlay.text()).toContain('42%')
  })

  it('shows the awaiting-validation block once the deliverable is in review', () => {
    const wrapper = mountCard(inReviewShipment(), { current: 5 })
    const section = wrapper.find('[data-testid="ugc-deliverable-review-section"]')
    expect(section.exists()).toBe(true)
    expect(section.text()).toContain('Vidéo Unboxing déposée')
    expect(section.text()).toContain('En attente de validation du Producteur')
    // Ni dropzone, ni ChronoRing : le chrono Unboxing est honoré (D-4.2.e).
    expect(wrapper.find('[data-testid="ugc-chrono-section"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="ugc-upload-dropzone"]').exists()).toBe(false)
    expect(wrapper.findComponent(ChronoRing).exists()).toBe(false)
  })

  it('shows neither CTA nor chrono for unknown tunnel statuses', () => {
    const wrapper = mountCard(
      makeShipment({ tunnel_status: 'some_future_status', tunnel_status_label: 'État réservé' }),
    )
    expect(wrapper.find('[data-testid="confirm-receipt-btn"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="ugc-chrono-section"]').exists()).toBe(false)
    // Le bloc review est aussi absent : seuls received / unboxing_in_review l'activent (D-4.2.h).
    expect(wrapper.find('[data-testid="ugc-deliverable-review-section"]').exists()).toBe(false)
    // Timeline + tracking restent rendus (déploiement backend seul ne casse rien, D-3.2.i).
    expect(wrapper.find('[data-testid="ugc-timeline-v"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('GZM-COT-882194')
  })

  // ============================ Phase Avis & bandeau de refus (4.6) ============================

  it('shows the Avis chrono section and dropzone while avis_pending', () => {
    const wrapper = mountCard(avisPendingShipment(), {
      current: 6,
      deliverables: [makeDeliverable()], // Unboxing validé → start chrono Avis
    })
    const section = wrapper.find('[data-testid="ugc-chrono-section"]')
    expect(section.exists()).toBe(true)
    expect(wrapper.findComponent(ChronoRing).exists()).toBe(true)
    expect(section.find('[data-testid="ugc-upload-dropzone"]').exists()).toBe(true)
    // Copy spécifique Avis (la phase Unboxing dit « Unboxing »).
    expect(section.text()).toContain('Uploade ta vidéo Avis')
    expect(section.text()).not.toContain('Uploade ta vidéo Unboxing')
    // La deadline rendue vient de avis_deadline_at (26 juin), JAMAIS d'unboxing_deadline_at
    // (19 juin) : verrouille la source de `activeDeadlineAt` en phase Avis (AC2 / D-4.6.d).
    expect(section.text()).toContain('À envoyer avant le')
    expect(section.text()).toContain('26 juin 2026')
    expect(section.text()).not.toContain('19 juin')
    // Pas de bandeau de refus au premier dépôt Avis (AC7).
    expect(wrapper.find('[data-testid="ugc-rejection-banner"]').exists()).toBe(false)
  })

  it('emits upload when an Avis video is selected', async () => {
    const wrapper = mountCard(avisPendingShipment(), { current: 6, deliverables: [makeDeliverable()] })
    const file = new File(['x'], 'avis.mp4', { type: 'video/mp4' })
    const input = wrapper.find('[data-testid="ugc-upload-input"]')
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')
    expect(wrapper.emitted('upload')?.[0]).toEqual([file])
  })

  it('shows the awaiting-validation block once the Avis is in review', () => {
    const wrapper = mountCard(avisInReviewShipment(), { current: 6 })
    const section = wrapper.find('[data-testid="ugc-deliverable-review-section"]')
    expect(section.exists()).toBe(true)
    expect(section.text()).toContain('Vidéo Avis déposée')
    expect(section.text()).toContain('En attente de validation du Producteur')
    // Ni dropzone, ni ChronoRing : le chrono Avis est honoré (calque D-4.2.e).
    expect(wrapper.find('[data-testid="ugc-chrono-section"]').exists()).toBe(false)
    expect(wrapper.findComponent(ChronoRing).exists()).toBe(false)
  })

  it('shows the rejection banner with the producer note on an Avis re-upload', () => {
    const wrapper = mountCard(avisPendingShipment(), {
      current: 6,
      deliverables: [
        makeDeliverable(), // Unboxing validé (start du chrono Avis)
        makeDeliverable({
          id: 'deliverable-uuid-2',
          kind: 'avis',
          kind_label: 'Avis',
          validation_status: 'rejected',
          validation_status_label: 'Refusée',
          review_note: 'Le cadrage est flou, refais la vidéo en lumière naturelle.',
          validated_at: null,
        }),
      ],
    })
    const banner = wrapper.find('[data-testid="ugc-rejection-banner"]')
    expect(banner.exists()).toBe(true)
    expect(banner.text()).toContain('Refusée')
    expect(banner.text()).toContain('Le cadrage est flou, refais la vidéo en lumière naturelle.')
    // La dropzone reste affichée pour re-déposer sous le même chrono (D-4.6.f).
    expect(wrapper.find('[data-testid="ugc-upload-dropzone"]').exists()).toBe(true)
  })

  it('shows the rejection banner for a retouche-requested Unboxing re-upload in received', () => {
    const wrapper = mountCard(receivedShipment(), {
      current: 5,
      deliverables: [
        makeDeliverable({
          validation_status: 'retouche_requested',
          validation_status_label: 'Retouche demandée',
          review_note: 'Ajoute un plan rapproché du logo.',
          validated_at: null,
        }),
      ],
    })
    const banner = wrapper.find('[data-testid="ugc-rejection-banner"]')
    expect(banner.exists()).toBe(true)
    expect(banner.text()).toContain('Retouche demandée')
    expect(banner.text()).toContain('Ajoute un plan rapproché du logo.')
  })

  it('does not show the rejection banner on a first Unboxing upload (received, no refused deliverable)', () => {
    const wrapper = mountCard(receivedShipment(), { current: 5, deliverables: [] })
    expect(wrapper.find('[data-testid="ugc-chrono-section"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="ugc-rejection-banner"]').exists()).toBe(false)
  })
})
