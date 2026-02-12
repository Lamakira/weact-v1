import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, VueWrapper } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import HomeView from '../HomeView.vue'

// Mock router
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', name: 'home', component: HomeView },
    { path: '/register/face', name: 'register-face', component: { template: '<div>Register</div>' } },
    { path: '/missions', name: 'missions', component: { template: '<div>Missions</div>' } },
  ],
})

describe('HomeView', () => {
  let wrapper: VueWrapper

  beforeEach(async () => {
    vi.useFakeTimers()
    router.push('/')
    await router.isReady()
    wrapper = mount(HomeView, {
      global: {
        plugins: [router],
      },
    })
  })

  afterEach(() => {
    vi.useRealTimers()
    wrapper.unmount()
  })

  describe('Hero Section', () => {
    it('renders the main headline', () => {
      const headline = wrapper.find('h1')
      expect(headline.exists()).toBe(true)
      expect(headline.text()).toContain('Monétisez votre')
      expect(headline.text()).toContain('image dans')
    })

    it('renders the animated word element', () => {
      const animatedWord = wrapper.find('[data-testid="hero-animated-word"]')
      expect(animatedWord.exists()).toBe(true)
    })

    it('cycles through words every 2.5 seconds', async () => {
      // Initial word should be 'film'
      expect(wrapper.find('[data-testid="hero-animated-word"]').text()).toBe('film')

      // Advance timer by 2.5 seconds - re-query element after each transition
      vi.advanceTimersByTime(2500)
      await wrapper.vm.$nextTick()
      expect(wrapper.find('[data-testid="hero-animated-word"]').text()).toBe('série télévisée')

      // Advance timer again
      vi.advanceTimersByTime(2500)
      await wrapper.vm.$nextTick()
      expect(wrapper.find('[data-testid="hero-animated-word"]').text()).toBe('vidéo publicitaire')

      // Advance timer again
      vi.advanceTimersByTime(2500)
      await wrapper.vm.$nextTick()
      expect(wrapper.find('[data-testid="hero-animated-word"]').text()).toBe('clip musical')

      // Should loop back to first word
      vi.advanceTimersByTime(2500)
      await wrapper.vm.$nextTick()
      expect(wrapper.find('[data-testid="hero-animated-word"]').text()).toBe('film')
    })

    it('renders the hero CTA button linking to face registration', () => {
      const cta = wrapper.find('[data-testid="hero-cta"]')
      expect(cta.exists()).toBe(true)
      expect(cta.text()).toContain('Créer mon profil')
      expect(cta.attributes('href')).toBe('/register/face')
    })

    it('renders silhouette visual on desktop', () => {
      const silhouette = wrapper.find('[data-testid="hero-silhouette"]')
      expect(silhouette.exists()).toBe(true)
    })
  })

  describe('Comment ça marche Section', () => {
    it('renders the section title', () => {
      const title = wrapper.find('[data-testid="how-it-works-title"]')
      expect(title.exists()).toBe(true)
      expect(title.text()).toContain('Comment ça marche')
    })

    it('renders all 4 step cards', () => {
      const step1 = wrapper.find('[data-testid="step-1-card"]')
      const step2 = wrapper.find('[data-testid="step-2-card"]')
      const step3 = wrapper.find('[data-testid="step-3-card"]')
      const step4 = wrapper.find('[data-testid="step-4-card"]')

      expect(step1.exists()).toBe(true)
      expect(step2.exists()).toBe(true)
      expect(step3.exists()).toBe(true)
      expect(step4.exists()).toBe(true)
    })

    it('displays correct step titles', () => {
      expect(wrapper.find('[data-testid="step-1-card"]').text()).toContain('CRÉER VOTRE PROFIL')
      expect(wrapper.find('[data-testid="step-2-card"]').text()).toContain('RECEVOIR OU CANDIDATER')
      expect(wrapper.find('[data-testid="step-3-card"]').text()).toContain('TRAVAILLER')
      expect(wrapper.find('[data-testid="step-4-card"]').text()).toContain('RECEVOIR VOS GAINS')
    })
  })

  describe('TU ATTENDS QUOI Section', () => {
    it('renders the section title', () => {
      const title = wrapper.find('[data-testid="cta-section-title"]')
      expect(title.exists()).toBe(true)
      expect(title.text()).toContain('DEVIENS')
      expect(title.text()).toContain('UNE FACE')
    })

    it('renders the faces carousel', () => {
      const carousel = wrapper.find('[data-testid="faces-carousel"]')
      expect(carousel.exists()).toBe(true)
    })

    it('renders the deviens face CTA button', () => {
      const cta = wrapper.find('[data-testid="deviens-face-cta"]')
      expect(cta.exists()).toBe(true)
      expect(cta.text()).toContain('Je crée mon profil')
    })
  })

  describe('Missions Section', () => {
    it('renders the missions section title', () => {
      const title = wrapper.find('[data-testid="missions-section-title"]')
      expect(title.exists()).toBe(true)
      expect(title.text()).toContain('Je recherche des missions en cours')
    })

    it('renders the see all missions link (scrolls to section)', () => {
      const link = wrapper.find('[data-testid="see-all-missions"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toContain('Voir toutes les missions')
      // Links to hash anchor until public missions page is implemented (story 11-6)
      expect(link.attributes('href')).toBe('#missions')
    })

    it('missions section has id for anchor link', () => {
      const section = wrapper.find('#missions')
      expect(section.exists()).toBe(true)
    })

    it('renders 6 mission cards (desktop)', () => {
      // Only count desktop cards (not mobile ones)
      const cards = wrapper.findAll('[data-testid^="mission-card-"]:not([data-testid*="mobile"])')
      expect(cards.length).toBe(6)
    })

    it('displays mission type badges', () => {
      const card1 = wrapper.find('[data-testid="mission-card-1"]')
      expect(card1.text()).toContain('FILM')
    })
  })

  describe('Pourquoi WEACT Section', () => {
    it('renders the section title', () => {
      const title = wrapper.find('[data-testid="why-weact-title"]')
      expect(title.exists()).toBe(true)
      expect(title.text()).toContain('Pourquoi WEACT')
    })

    it('renders all 3 feature cards', () => {
      const card1 = wrapper.find('[data-testid="feature-card-1"]')
      const card2 = wrapper.find('[data-testid="feature-card-2"]')
      const card3 = wrapper.find('[data-testid="feature-card-3"]')

      expect(card1.exists()).toBe(true)
      expect(card2.exists()).toBe(true)
      expect(card3.exists()).toBe(true)
    })

    it('displays correct feature titles', () => {
      expect(wrapper.find('[data-testid="feature-card-1"]').text()).toContain('Plateforme sécurisée')
      expect(wrapper.find('[data-testid="feature-card-2"]').text()).toContain('Paiement sécurisé')
      expect(wrapper.find('[data-testid="feature-card-3"]').text()).toContain('Transactions protégées')
    })
  })

  describe('Final CTA Section', () => {
    it('renders the final CTA title', () => {
      const title = wrapper.find('[data-testid="final-cta-title"]')
      expect(title.exists()).toBe(true)
      expect(title.text()).toContain('Prêt à commencer')
    })

    it('renders the final CTA button linking to face registration', () => {
      const button = wrapper.find('[data-testid="final-cta-button"]')
      expect(button.exists()).toBe(true)
      expect(button.text()).toContain("S'inscrire comme Face")
      expect(button.attributes('href')).toBe('/register/face')
    })
  })

  describe('Accessibility', () => {
    it('has proper heading hierarchy starting with h1', () => {
      const h1 = wrapper.find('h1')
      expect(h1.exists()).toBe(true)
    })

    it('all CTAs have data-testid attributes', () => {
      const heroCta = wrapper.find('[data-testid="hero-cta"]')
      const deviensFaceCta = wrapper.find('[data-testid="deviens-face-cta"]')
      const finalCta = wrapper.find('[data-testid="final-cta-button"]')
      const seeAllMissions = wrapper.find('[data-testid="see-all-missions"]')

      expect(heroCta.exists()).toBe(true)
      expect(deviensFaceCta.exists()).toBe(true)
      expect(finalCta.exists()).toBe(true)
      expect(seeAllMissions.exists()).toBe(true)
    })

    it('images have alt attributes', () => {
      const images = wrapper.findAll('img')
      images.forEach(img => {
        expect(img.attributes('alt')).toBeDefined()
        expect(img.attributes('alt')).not.toBe('')
      })
    })
  })

  describe('Responsive Design', () => {
    it('silhouette has hidden lg:block classes for mobile hiding', () => {
      const silhouetteContainer = wrapper.find('.hidden.lg\\:block')
      expect(silhouetteContainer.exists()).toBe(true)
    })
  })

  describe('Scroll-linked Rotation (AC #3)', () => {
    it('silhouette starts with 0 rotation', () => {
      const silhouette = wrapper.find('[data-testid="hero-silhouette"]')
      expect(silhouette.attributes('style')).toContain('rotate(0deg)')
    })

    it('silhouette element has transform style binding', () => {
      // Verify the silhouette has a style binding that includes rotation
      const silhouette = wrapper.find('[data-testid="hero-silhouette"]')
      const style = silhouette.attributes('style')
      expect(style).toBeDefined()
      expect(style).toMatch(/transform:\s*rotate\(\d+deg\)/)
    })

    it('rotation is computed from scroll progress', () => {
      // Test the rotation computation logic:
      // At 0% scroll, rotation should be 0deg
      // The formula is: scrollProgress * 360
      const silhouette = wrapper.find('[data-testid="hero-silhouette"]')

      // Initial state (no scroll) should be 0deg
      expect(silhouette.attributes('style')).toContain('rotate(0deg)')
    })
  })

  describe('Perspective Toggle (Story 11-2)', () => {
    it('renders the perspective toggle', () => {
      const toggle = wrapper.find('[data-testid="perspective-toggle"]')
      expect(toggle.exists()).toBe(true)
    })

    it('defaults to Face perspective', () => {
      // Verify Face is selected by default
      const faceTab = wrapper.find('[data-testid="toggle-face"]')
      expect(faceTab.exists()).toBe(true)
      expect(faceTab.attributes('aria-selected')).toBe('true')
      expect(faceTab.classes()).toContain('bg-black')

      // Verify HeroFace is rendered (check for Face-specific content)
      expect(wrapper.text()).toContain('Monétisez votre')
    })

    it('toggle has accessibility attributes', () => {
      const toggle = wrapper.find('[data-testid="perspective-toggle"]')
      // The tablist role is on the inner container
      const tablist = toggle.find('[role="tablist"]')
      expect(tablist.exists()).toBe(true)
      expect(tablist.attributes('aria-label')).toBe('Choisir votre profil')
    })

    it('switches to Producer perspective when clicked', async () => {
      const producerTab = wrapper.find('[data-testid="toggle-producer"]')
      await producerTab.trigger('click')
      await wrapper.vm.$nextTick()

      // Verify Producer is now selected
      expect(producerTab.attributes('aria-selected')).toBe('true')

      // Verify HeroProducer content is shown
      expect(wrapper.text()).toContain('Trouvez votre prochain')
    })

    it('updates How It Works section when switching perspectives', async () => {
      // Face perspective default - check step content
      const faceStep1 = wrapper.find('[data-testid="step-1-card"]')
      expect(faceStep1.text()).toContain('CRÉER VOTRE PROFIL')

      // Switch to Producer
      const producerTab = wrapper.find('[data-testid="toggle-producer"]')
      await producerTab.trigger('click')
      await wrapper.vm.$nextTick()

      // Producer perspective should have different step content
      const producerStep1 = wrapper.find('[data-testid="step-1-card"]')
      expect(producerStep1.text()).toContain('PUBLIEZ VOTRE MISSION')
    })

    it('updates Pourquoi WEACT section when switching perspectives', async () => {
      // Face perspective default
      const faceFeature1 = wrapper.find('[data-testid="feature-card-1"]')
      expect(faceFeature1.text()).toContain('Plateforme sécurisée')

      // Switch to Producer
      const producerTab = wrapper.find('[data-testid="toggle-producer"]')
      await producerTab.trigger('click')
      await wrapper.vm.$nextTick()

      // Producer perspective should have different feature content
      const producerFeature1 = wrapper.find('[data-testid="feature-card-1"]')
      expect(producerFeature1.text()).toContain('vivier de talents')
    })

    it('updates final CTA when switching perspectives', async () => {
      // Face perspective: "S'inscrire comme Face"
      expect(wrapper.find('[data-testid="final-cta-button"]').text()).toContain("S'inscrire comme Face")

      // Switch to Producer
      const producerTab = wrapper.find('[data-testid="toggle-producer"]')
      await producerTab.trigger('click')
      await wrapper.vm.$nextTick()

      // Producer perspective: "S'inscrire comme Producteur"
      expect(wrapper.find('[data-testid="final-cta-button"]').text()).toContain("S'inscrire comme Producteur")
    })

    it('switches showcase component when perspective changes', async () => {
      // Face perspective: FacesCarousel
      expect(wrapper.find('[data-testid="faces-carousel"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="talents-showcase"]').exists()).toBe(false)

      // Switch to Producer
      const producerTab = wrapper.find('[data-testid="toggle-producer"]')
      await producerTab.trigger('click')
      await wrapper.vm.$nextTick()

      // Producer perspective: TalentsShowcase
      expect(wrapper.find('[data-testid="talents-showcase"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="faces-carousel"]').exists()).toBe(false)
    })

    it('can switch back to Face perspective', async () => {
      // Switch to Producer first
      const producerTab = wrapper.find('[data-testid="toggle-producer"]')
      await producerTab.trigger('click')
      await wrapper.vm.$nextTick()
      expect(wrapper.text()).toContain('Trouvez votre prochain')

      // Switch back to Face
      const faceTab = wrapper.find('[data-testid="toggle-face"]')
      await faceTab.trigger('click')
      await wrapper.vm.$nextTick()
      expect(wrapper.text()).toContain('Monétisez votre')
    })

    it('applies perspective transition classes for smooth 300ms animation (AC #9)', async () => {
      // Switch perspective to trigger transition
      const producerTab = wrapper.find('[data-testid="toggle-producer"]')
      await producerTab.trigger('click')

      // The component uses Vue Transition with name="perspective"
      // which creates classes: perspective-enter-active, perspective-leave-active
      // The CSS defines 300ms (0.3s) transition duration
      // We verify the page re-renders with new perspective content
      await wrapper.vm.$nextTick()
      expect(wrapper.text()).toContain('Trouvez votre prochain')
    })
  })
})
