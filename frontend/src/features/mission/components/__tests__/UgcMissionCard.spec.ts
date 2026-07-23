import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { MapPin } from 'lucide-vue-next'
import UgcMissionCard from '../UgcMissionCard.vue'
import type { ProductPhoto } from '@/components/ugc'
import type { Mission, UgcMissionTeaser } from '../../types'

// Mission UGC complète (branche éligible). La réponse réelle ne contient pas
// commission_ugc/commission_paid_at (masqués Face) — cast localisé as Mission.
function createEligibleMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 'uuid-1',
    titre: 'Test sneakers running · 2 vidéos',
    description: 'Brief complet',
    date_tournage: '2026-07-01',
    profil_recherche: 'Créatrices',
    budget: 0,
    date_limite_candidature: '2026-06-24T00:00:00Z',
    nombre_faces_voulu: 3,
    type_mission: 'autre',
    type_mission_label: 'UGC',
    type_mission_autre: null,
    type_compensation: 'hybrid',
    type_compensation_label: 'Produit + Argent',
    nom_produit: 'Sneakers Shade Fit',
    valeur_produit: 35000,
    nombre_videos: 2,
    montant_remuneration: 10000,
    genre_voulu: 'tous',
    genre_voulu_label: 'Homme et Femme',
    lieu: 'Cotonou',
    duree: 'Livrables vidéo',
    status: 'published',
    status_label: 'Publiée',
    is_accepting_candidatures: true,
    has_paid_payment: false,
    // display_name volontairement disjoint de nom_produit ('Sneakers Shade Fit')
    // pour que les assertions producteur soient falsifiables (review 2.2).
    producer: {
      id: 'producer-uuid-1',
      slug: 'maison-kewe',
      type: 'agency',
      agency_name: 'Maison Kéwé',
      first_name: null,
      last_name: null,
      display_name: 'Maison Kéwé',
      bio: null,
      profile_photo_url: null,
      thumbnail_url: null,
      agency_logo_url: null,
      agency_logo_thumbnail_url: null,
      average_rating: null,
      ratings_count: 0,
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    },
    created_at: '2026-06-09T00:00:00Z',
    updated_at: '2026-06-09T00:00:00Z',
    ...overrides,
  } as Mission
}

function createTeaser(overrides: Partial<UgcMissionTeaser> = {}): UgcMissionTeaser {
  return {
    id: 'uuid-1',
    titre: 'Test sneakers running · 2 vidéos',
    type_compensation: 'product',
    type_compensation_label: 'Produit seul',
    nom_produit: 'Sneakers Shade Fit',
    valeur_produit: 35000,
    nombre_videos: 2,
    lieu: 'Cotonou',
    date_limite_candidature: '2026-06-24T00:00:00Z',
    created_at: '2026-06-09T00:00:00Z',
    ...overrides,
  }
}

// Photo produit — miroir de ProductPhotoResource (mission = disque public,
// URLs directes ; grid_url retombe sur l'original avant génération des variantes).
function createPhoto(overrides: Partial<ProductPhoto> = {}): ProductPhoto {
  return {
    id: 'photo-uuid-1',
    position: 0,
    photo_url: 'https://cdn.test/original.jpg',
    grid_url: 'https://cdn.test/grid.webp',
    large_url: 'https://cdn.test/large.webp',
    ...overrides,
  }
}

// Normalise U+202F (espace fine insécable de toLocaleString fr-FR) — leçon 1.6
function normalizedText(wrapper: ReturnType<typeof mount>): string {
  return wrapper.text().replace(/\s/g, ' ')
}

describe('UgcMissionCard', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders the full eligible card: title, product, value, videos, location, producer', () => {
    const wrapper = mount(UgcMissionCard, {
      props: { item: createEligibleMission(), locked: false },
    })

    const text = normalizedText(wrapper)
    expect(text).toContain('Test sneakers running · 2 vidéos')
    expect(text).toContain('Sneakers Shade Fit')
    expect(text).toContain('35 000 FCFA')
    expect(text).toContain('de produit')
    expect(text).toContain('2 vidéos')
    expect(text).toContain('Cotonou')
    expect(text).toContain('Maison Kéwé')
    expect(wrapper.find('[data-testid="ugc-card-producer"]').exists()).toBe(true)
    expect(text).toContain('Produit + Argent')
  })

  it('hides the location pin when lieu is null (UGC dotation has no shoot location — ugc-8-1)', () => {
    // Depuis ugc-8-1, lieu est nulle sur 100% des missions UGC (migration + service hardcode null).
    // Le runtime renvoie donc null bien que le type teaser annonce string (mismatch suivi en defer F2).
    // Sans le v-if="item.lieu", la carte afficherait une épingle 📍 suivie d'un libellé vide.
    const wrapper = mount(UgcMissionCard, {
      props: { item: createEligibleMission({ lieu: null as unknown as string }), locked: false },
    })

    expect(wrapper.findComponent(MapPin).exists()).toBe(false)
    expect(normalizedText(wrapper)).not.toContain('Cotonou')
  })

  it('shows "+ X FCFA" when montant_remuneration is set', () => {
    const wrapper = mount(UgcMissionCard, {
      props: { item: createEligibleMission({ montant_remuneration: 10000 }), locked: false },
    })

    expect(normalizedText(wrapper)).toContain('+ 10 000 FCFA')
  })

  it('hides the cash line when montant_remuneration is null', () => {
    const wrapper = mount(UgcMissionCard, {
      props: { item: createEligibleMission({ montant_remuneration: null }), locked: false },
    })

    // Pas de ligne cash « + X FCFA » (le label « Produit + Argent » contient aussi un +)
    expect(normalizedText(wrapper)).not.toMatch(/\+ [\d ]+FCFA/)
  })

  it('shows lock overlay and aria-label when locked', () => {
    const wrapper = mount(UgcMissionCard, {
      props: { item: createTeaser(), locked: true },
    })

    expect(wrapper.find('[data-testid="ugc-card-lock-overlay"]').exists()).toBe(true)
    const card = wrapper.get('[data-testid="ugc-mission-card"]')
    expect(card.attributes('role')).toBe('button')
    expect(card.attributes('aria-label')).toBe('Mission verrouillée — abonnement requis')
  })

  it('does not render lock overlay when unlocked', () => {
    const wrapper = mount(UgcMissionCard, {
      props: { item: createEligibleMission(), locked: false },
    })

    expect(wrapper.find('[data-testid="ugc-card-lock-overlay"]').exists()).toBe(false)
  })

  it('emits click with the item id, locked or not (routing is the page concern)', async () => {
    const lockedWrapper = mount(UgcMissionCard, {
      props: { item: createTeaser({ id: 'teaser-id' }), locked: true },
    })
    await lockedWrapper.get('[data-testid="ugc-mission-card"]').trigger('click')
    expect(lockedWrapper.emitted('click')).toEqual([['teaser-id']])

    const openWrapper = mount(UgcMissionCard, {
      props: { item: createEligibleMission({ id: 'mission-id' }), locked: false },
    })
    await openWrapper.get('[data-testid="ugc-mission-card"]').trigger('click')
    expect(openWrapper.emitted('click')).toEqual([['mission-id']])
  })

  it('renders the teaser without eligible-only fields (no producer, no cash)', () => {
    const wrapper = mount(UgcMissionCard, {
      props: { item: createTeaser(), locked: true },
    })

    const text = normalizedText(wrapper)
    expect(text).toContain('Test sneakers running · 2 vidéos')
    expect(text).toContain('35 000 FCFA')
    expect(text).not.toMatch(/\+ [\d ]+FCFA/)
    expect(text).not.toContain('Maison Kéwé')
    // Pas de footer producteur en mode teaser
    expect(wrapper.find('[data-testid="ugc-card-producer"]').exists()).toBe(false)
  })

  it('renders "Candidatures ouvertes" vs "Délai dépassé" status pills', () => {
    // Teaser à deadline passée → overdue (heuristique display-only)
    const overdueTeaser = mount(UgcMissionCard, {
      props: {
        item: createTeaser({ date_limite_candidature: '2026-01-01T00:00:00Z' }),
        locked: true,
      },
    })
    expect(overdueTeaser.text()).toContain('Délai dépassé')

    // Teaser à deadline future → ouvert
    const openTeaser = mount(UgcMissionCard, {
      props: {
        item: createTeaser({ date_limite_candidature: '2099-01-01T00:00:00Z' }),
        locked: true,
      },
    })
    expect(openTeaser.text()).toContain('Candidatures ouvertes')

    // Éligible fermé par le serveur → overdue (is_accepting_candidatures autoritatif)
    const closedEligible = mount(UgcMissionCard, {
      props: {
        item: createEligibleMission({ is_accepting_candidatures: false }),
        locked: false,
      },
    })
    expect(closedEligible.text()).toContain('Délai dépassé')

    // Éligible ouvert par le serveur
    const openEligible = mount(UgcMissionCard, {
      props: {
        item: createEligibleMission({ is_accepting_candidatures: true }),
        locked: false,
      },
    })
    expect(openEligible.text()).toContain('Candidatures ouvertes')
  })

  it('is keyboard-activatable with Enter and Space even when unlocked', async () => {
    const wrapper = mount(UgcMissionCard, {
      props: { item: createEligibleMission({ id: 'mission-id' }), locked: false },
    })

    const card = wrapper.get('[data-testid="ugc-mission-card"]')
    expect(card.attributes('role')).toBe('button')
    expect(card.attributes('tabindex')).toBe('0')

    await card.trigger('keydown', { key: 'Enter' })
    await card.trigger('keydown', { key: ' ' })
    expect(wrapper.emitted('click')).toEqual([['mission-id'], ['mission-id']])
  })

  it('treats the deadline day itself as still open (day-inclusive, backend date semantics)', () => {
    vi.useFakeTimers()
    // 08:00Z le jour J : robuste aux fuseaux du runner (UTC-12 → UTC+14)
    vi.setSystemTime(new Date('2026-06-24T08:00:00Z'))

    const wrapper = mount(UgcMissionCard, {
      props: {
        item: createTeaser({ date_limite_candidature: '2026-06-24T00:00:00Z' }),
        locked: true,
      },
    })

    expect(wrapper.text()).toContain('Candidatures ouvertes')
  })

  // ─── Vitrine photo produit ──────────────────────────────────────────────

  it('renders the product cover from the first photo, preferring the grid variant', () => {
    const wrapper = mount(UgcMissionCard, {
      props: {
        item: createEligibleMission({
          product_photos: [
            createPhoto({ id: 'p1', position: 0, grid_url: 'https://cdn.test/first-grid.webp' }),
            createPhoto({ id: 'p2', position: 1, grid_url: 'https://cdn.test/second-grid.webp' }),
          ],
        }),
        locked: false,
      },
    })

    const cover = wrapper.get('[data-testid="ugc-card-cover"]')
    const img = cover.get('img')
    expect(img.attributes('src')).toBe('https://cdn.test/first-grid.webp')
    expect(img.attributes('loading')).toBe('lazy')
    expect(img.attributes('alt')).toBe('Photo du produit Sneakers Shade Fit')
    // Photo restante annoncée : la galerie complète vit sur le détail
    expect(wrapper.get('[data-testid="ugc-card-photo-count"]').text()).toBe('+1')
  })

  it('falls back to the original URL when the grid variant is not generated yet', () => {
    const wrapper = mount(UgcMissionCard, {
      props: {
        item: createEligibleMission({
          product_photos: [createPhoto({ grid_url: null, photo_url: 'https://cdn.test/raw.jpg' })],
        }),
        locked: false,
      },
    })

    expect(wrapper.get('[data-testid="ugc-card-cover"] img').attributes('src')).toBe(
      'https://cdn.test/raw.jpg',
    )
    // Une seule photo : pas de compteur
    expect(wrapper.find('[data-testid="ugc-card-photo-count"]').exists()).toBe(false)
  })

  it('renders no cover when the mission has no photo or no usable URL', () => {
    const withoutPhotos = mount(UgcMissionCard, {
      props: { item: createEligibleMission({ product_photos: [] }), locked: false },
    })
    expect(withoutPhotos.find('[data-testid="ugc-card-cover"]').exists()).toBe(false)
    // L'en-tête historique reste complet (badge UGC visible)
    expect(withoutPhotos.text()).toContain('UGC')

    // Clé absente (liste servie sans la relation chargée)
    const keyOmitted = mount(UgcMissionCard, {
      props: { item: createEligibleMission(), locked: false },
    })
    expect(keyOmitted.find('[data-testid="ugc-card-cover"]').exists()).toBe(false)

    // Row sans aucune URL exploitable → aucun <img> cassé
    const urlsNull = mount(UgcMissionCard, {
      props: {
        item: createEligibleMission({
          product_photos: [createPhoto({ grid_url: null, photo_url: null, large_url: null })],
        }),
        locked: false,
      },
    })
    expect(urlsNull.find('[data-testid="ugc-card-cover"]').exists()).toBe(false)
  })

  it('shows the cover unblurred on a locked teaser card, with the lock over it', () => {
    const wrapper = mount(UgcMissionCard, {
      props: {
        item: createTeaser({ product_photos: [createPhoto()] }),
        locked: true,
      },
    })

    const cover = wrapper.get('[data-testid="ugc-card-cover"]')
    expect(cover.get('img').attributes('src')).toBe('https://cdn.test/grid.webp')
    // La photo est l'argument d'upsell : nette, jamais floutée (décision PO)
    expect(cover.get('img').classes().join(' ')).not.toContain('blur')
    // Un seul cadenas, porté par le bandeau photo
    const overlays = wrapper.findAll('[data-testid="ugc-card-lock-overlay"]')
    expect(overlays).toHaveLength(1)
    expect(cover.find('[data-testid="ugc-card-lock-overlay"]').exists()).toBe(true)
  })

  it('treats an unparseable deadline as open (display-only heuristic)', () => {
    const wrapper = mount(UgcMissionCard, {
      props: {
        item: createTeaser({ date_limite_candidature: 'not-a-date' }),
        locked: true,
      },
    })

    expect(wrapper.text()).toContain('Candidatures ouvertes')
  })
})
