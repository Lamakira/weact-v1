import { describe, it, expect } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import { Check, Crown, Minus } from 'lucide-vue-next'
import PricingView from '../PricingView.vue'

const mountPricing = () =>
  mount(PricingView, {
    global: {
      stubs: {
        RouterLink: RouterLinkStub,
      },
    },
  })

describe('PricingView (public /pricing — FP-2.13)', () => {
  // ---------------------------------------------------------------
  // Hero
  // ---------------------------------------------------------------
  describe('hero section', () => {
    it('renders the H1 copy', () => {
      const wrapper = mountPricing()
      const h1 = wrapper.find('h1')
      expect(h1.exists()).toBe(true)
      expect(h1.text()).toBe('Plus tu montes, plus tu décroches.')
    })
  })

  // ---------------------------------------------------------------
  // Pricing Cards
  // ---------------------------------------------------------------
  describe('pricing cards', () => {
    it('renders the four tier cards in DOM order Découverte → Starter → Pro → Élite', () => {
      const wrapper = mountPricing()
      const cards = wrapper.findAll('[data-testid^="tier-card-"]')
      expect(cards.length).toBe(4)
      const tierNames = cards.map((c) => c.find('h3').text())
      expect(tierNames).toEqual(['Découverte', 'Starter', 'Pro', 'Élite'])
    })

    it('shows the "Populaire" badge inside the Pro card only (not on the three other cards)', () => {
      const wrapper = mountPricing()
      expect(wrapper.get('[data-testid="tier-card-pro"]').text()).toContain('Populaire')
      expect(wrapper.get('[data-testid="tier-card-decouverte"]').text()).not.toContain('Populaire')
      expect(wrapper.get('[data-testid="tier-card-starter"]').text()).not.toContain('Populaire')
      // "Populaire" must not appear inside the Élite card wrapper either.
      expect(wrapper.get('[data-testid="tier-card-elite"]').text()).not.toContain('Populaire')
    })

    it('shows a Crown icon on the Élite card only (no Crown on the three other cards)', () => {
      const wrapper = mountPricing()
      expect(wrapper.get('[data-testid="tier-card-decouverte"]').findAllComponents(Crown).length).toBe(0)
      expect(wrapper.get('[data-testid="tier-card-starter"]').findAllComponents(Crown).length).toBe(0)
      expect(wrapper.get('[data-testid="tier-card-pro"]').findAllComponents(Crown).length).toBe(0)
      expect(wrapper.get('[data-testid="tier-card-elite"]').findAllComponents(Crown).length).toBe(1)
    })

    it('applies the dark variant (bg-[#0F1419]) on the Élite card wrapper, transparent on the others', () => {
      const wrapper = mountPricing()
      const elite = wrapper.get('[data-testid="tier-card-elite"]')
      expect(elite.classes()).toContain('bg-[#0F1419]')
      expect(elite.classes()).toContain('text-white')
      // Sibling cards rely on the page background — no bg-* utility on the wrapper.
      for (const key of ['decouverte', 'starter', 'pro'] as const) {
        const card = wrapper.get(`[data-testid="tier-card-${key}"]`)
        expect(card.classes().some((c) => c.startsWith('bg-'))).toBe(false)
      }
    })

    it('lists "4 photos dans la galerie" inside the Pro card features (AC #6 — content correction)', () => {
      const wrapper = mountPricing()
      const proCard = wrapper.get('[data-testid="tier-card-pro"]')
      expect(proCard.find('h3').text()).toBe('Pro')
      expect(proCard.text()).toContain('4 photos dans la galerie')
      // Make sure the legacy "2 photos" wording is not present in the Pro card
      expect(proCard.text()).not.toContain('2 photos dans la galerie')
    })
  })

  // ---------------------------------------------------------------
  // Comparison Table
  // ---------------------------------------------------------------
  describe('comparison table', () => {
    it('renders the "Photos dans la galerie" row with the Pro cell showing "4" (AC #6 + #7)', () => {
      const wrapper = mountPricing()
      const rows = wrapper.findAll('table tbody tr')
      const photosRow = rows.find((tr) => tr.find('td')?.text() === 'Photos dans la galerie')
      expect(photosRow).toBeTruthy()
      const cells = photosRow!.findAll('td')
      // Cell layout: [name, decouverte, starter, pro, elite]
      expect(cells[1].text()).toBe('1')
      expect(cells[2].text()).toBe('2')
      expect(cells[3].text()).toBe('4')
      expect(cells[4].text()).toBe('6')
    })

    it('renders Check icons for true booleans and Minus icons for false (AC #7)', () => {
      const wrapper = mountPricing()
      const rows = wrapper.findAll('table tbody tr')

      // "Photo de profil" row: all four tiers true → 4 Check icons, 0 Minus icons in that row
      const photoRow = rows.find((tr) => tr.find('td')?.text() === 'Photo de profil')
      expect(photoRow).toBeTruthy()
      expect(photoRow!.findAllComponents(Check).length).toBe(4)
      expect(photoRow!.findAllComponents(Minus).length).toBe(0)

      // "Vidéo modèle UGC" row: only Élite true → 1 Check, 3 Minus
      const ugcRow = rows.find((tr) => tr.find('td')?.text() === 'Vidéo modèle UGC')
      expect(ugcRow).toBeTruthy()
      expect(ugcRow!.findAllComponents(Check).length).toBe(1)
      expect(ugcRow!.findAllComponents(Minus).length).toBe(3)
    })
  })

  // ---------------------------------------------------------------
  // FAQ accordion
  // ---------------------------------------------------------------
  describe('FAQ accordion', () => {
    it('opens the first FAQ item by default and keeps the four others closed (aria-expanded)', () => {
      const wrapper = mountPricing()
      const toggles = wrapper.findAll('[data-testid^="faq-toggle-"]')
      expect(toggles.length).toBe(5)
      expect(toggles[0].attributes('aria-expanded')).toBe('true')
      expect(toggles[1].attributes('aria-expanded')).toBe('false')
      expect(toggles[2].attributes('aria-expanded')).toBe('false')
      expect(toggles[3].attributes('aria-expanded')).toBe('false')
      expect(toggles[4].attributes('aria-expanded')).toBe('false')
    })

    it('exposes aria-controls on every FAQ toggle and an id on every FAQ panel (A11y)', () => {
      const wrapper = mountPricing()
      const toggles = wrapper.findAll('[data-testid^="faq-toggle-"]')
      const panels = wrapper.findAll('[data-testid^="faq-panel-"], [id^="faq-panel-"]')
      expect(toggles.length).toBe(5)
      // Each toggle's aria-controls must point to an existing panel id.
      for (const t of toggles) {
        const controlled = t.attributes('aria-controls')
        expect(controlled).toMatch(/^faq-panel-\d+$/)
        expect(panels.some((p) => p.attributes('id') === controlled)).toBe(true)
      }
    })

    it('marks closed FAQ panels as aria-hidden + inert (and the open one as not hidden)', () => {
      const wrapper = mountPricing()
      const panels = wrapper.findAll('[id^="faq-panel-"]')
      expect(panels.length).toBe(5)
      // FAQ #0 starts open
      expect(panels[0].attributes('aria-hidden')).toBe('false')
      expect(panels[0].attributes('inert')).toBeUndefined()
      // Others are closed → aria-hidden="true" and inert is present
      for (let i = 1; i < panels.length; i++) {
        expect(panels[i].attributes('aria-hidden')).toBe('true')
        expect(panels[i].attributes('inert')).toBeDefined()
      }
    })

    it('toggles a closed FAQ open on click, then closes it on a second click (aria-expanded flips)', async () => {
      const wrapper = mountPricing()
      const toggle = wrapper.get('[data-testid="faq-toggle-1"]')
      // Second FAQ starts closed
      expect(toggle.attributes('aria-expanded')).toBe('false')

      await toggle.trigger('click')
      expect(toggle.attributes('aria-expanded')).toBe('true')

      await toggle.trigger('click')
      expect(toggle.attributes('aria-expanded')).toBe('false')
    })

    it('closes any previously open FAQ when a different one is opened (exclusive accordion)', async () => {
      const wrapper = mountPricing()
      const t0 = wrapper.get('[data-testid="faq-toggle-0"]')
      const t1 = wrapper.get('[data-testid="faq-toggle-1"]')
      const t2 = wrapper.get('[data-testid="faq-toggle-2"]')
      // FAQ #0 starts open by default
      expect(t0.attributes('aria-expanded')).toBe('true')

      // Click FAQ #1 — FAQ #0 must auto-close
      await t1.trigger('click')
      expect(t0.attributes('aria-expanded')).toBe('false')
      expect(t1.attributes('aria-expanded')).toBe('true')

      // Click FAQ #2 — FAQ #1 must auto-close
      await t2.trigger('click')
      expect(t1.attributes('aria-expanded')).toBe('false')
      expect(t2.attributes('aria-expanded')).toBe('true')
    })

    it('FAQ #1 answer uses the no-pro-rata wording (AC #10 — Product Decision #3)', () => {
      const wrapper = mountPricing()
      const text = wrapper.text()
      expect(text).toContain('facturé au prix annuel plein')
      expect(text).toContain('jours restants')
      // Defensive — make sure the deprecated "calculé au prorata" copy is gone
      expect(text).not.toContain('au prorata')
      expect(text).not.toContain('calculé au pro')
    })

    it('FAQ #4 answer mentions the 90-day retention window (AC #11 — Product Decision #11)', () => {
      const wrapper = mountPricing()
      expect(wrapper.text()).toContain('90 jours')
    })
  })

  // ---------------------------------------------------------------
  // CTA routes
  // ---------------------------------------------------------------
  describe('CTA routes', () => {
    const tierExpected: Record<string, string> = {
      decouverte: '/register/face',
      starter: '/register/face?plan=starter',
      pro: '/register/face?plan=pro',
      elite: '/register/face?plan=elite',
    }

    it('routes each tier card primary CTA to /register/face with the right ?plan= query (AC #12)', () => {
      const wrapper = mountPricing()
      for (const [key, expected] of Object.entries(tierExpected)) {
        const link = wrapper.findComponent(`[data-testid="cta-tier-${key}"]` as never)
        expect(link.exists()).toBe(true)
        expect(link.props('to')).toBe(expected)
      }
    })

    it('routes the comparison-table per-tier "Choisir" buttons to the same targets per tier (AC #12)', () => {
      const wrapper = mountPricing()
      for (const [key, expected] of Object.entries(tierExpected)) {
        const link = wrapper.findComponent(`[data-testid="cta-comparison-${key}"]` as never)
        expect(link.exists()).toBe(true)
        expect(link.props('to')).toBe(expected)
      }
    })

    it('routes the two footer CTAs to /register/face and /contact (AC #12)', () => {
      const wrapper = mountPricing()
      const register = wrapper.findComponent('[data-testid="cta-footer-register"]' as never)
      const contact = wrapper.findComponent('[data-testid="cta-footer-contact"]' as never)
      expect(register.exists()).toBe(true)
      expect(contact.exists()).toBe(true)
      expect(register.props('to')).toBe('/register/face')
      expect(contact.props('to')).toBe('/contact')
    })
  })

  // ---------------------------------------------------------------
  // Responsive layout
  // ---------------------------------------------------------------
  describe('responsive layout', () => {
    it('keeps the lg:grid-cols-4 class on the pricing grid container (AC #13)', () => {
      const wrapper = mountPricing()
      const grid = wrapper.get('[data-testid="pricing-grid"]')
      expect(grid.classes()).toContain('lg:grid-cols-4')
      expect(grid.classes()).toContain('grid-cols-1')
      expect(grid.classes()).toContain('sm:grid-cols-2')
    })
  })

  // ---------------------------------------------------------------
  // Tier prices — NBSP (no narrow-viewport line wrap, FR typography)
  // ---------------------------------------------------------------
  describe('tier prices', () => {
    it('renders amount prices with non-breaking space between thousands', () => {
      const wrapper = mountPricing()
      // U+00A0 NBSP between the thousand and hundred segments
      expect(wrapper.get('[data-testid="tier-card-starter"]').text()).toContain('12 000')
      expect(wrapper.get('[data-testid="tier-card-pro"]').text()).toContain('25 000')
      expect(wrapper.get('[data-testid="tier-card-elite"]').text()).toContain('40 000')
    })
  })
})
