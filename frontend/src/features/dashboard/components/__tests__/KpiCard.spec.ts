import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import KpiCard from '../KpiCard.vue'

describe('KpiCard', () => {
  const defaultProps = {
    title: 'En attente',
    value: 5,
    icon: 'clock' as const,
    color: 'amber-500' as const,
  }

  describe('rendering', () => {
    it('renders the card container', () => {
      const wrapper = mount(KpiCard, { props: defaultProps })

      expect(wrapper.find('[data-testid="kpi-card"]').exists()).toBe(true)
    })

    it('renders the title', () => {
      const wrapper = mount(KpiCard, { props: defaultProps })

      expect(wrapper.find('[data-testid="kpi-card-title"]').text()).toBe('En attente')
    })

    it('renders the value', () => {
      const wrapper = mount(KpiCard, { props: defaultProps })

      expect(wrapper.find('[data-testid="kpi-card-value"]').text()).toBe('5')
    })

    it('renders different titles correctly', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, title: 'Terminées' },
      })

      expect(wrapper.find('[data-testid="kpi-card-title"]').text()).toBe('Terminées')
    })
  })

  describe('value formatting', () => {
    it('formats value with French locale (thousands separator)', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, value: 1234 },
      })

      // French locale uses non-breaking space as thousands separator
      const valueText = wrapper.find('[data-testid="kpi-card-value"]').text()
      expect(valueText).toMatch(/1[\s\u00A0]234/)
    })

    it('displays zero correctly', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, value: 0 },
      })

      expect(wrapper.find('[data-testid="kpi-card-value"]').text()).toBe('0')
    })

    it('displays large numbers correctly', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, value: 999999 },
      })

      const valueText = wrapper.find('[data-testid="kpi-card-value"]').text()
      // Should have thousands separators
      expect(valueText).toMatch(/999[\s\u00A0]999/)
    })
  })

  describe('loading state', () => {
    it('shows skeleton when isLoading is true', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, isLoading: true },
      })

      expect(wrapper.find('[data-testid="kpi-card-skeleton"]').exists()).toBe(true)
    })

    it('hides value when loading', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, isLoading: true },
      })

      expect(wrapper.find('[data-testid="kpi-card-value"]').exists()).toBe(false)
    })

    it('hides title when loading', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, isLoading: true },
      })

      expect(wrapper.find('[data-testid="kpi-card-title"]').exists()).toBe(false)
    })

    it('shows value when not loading', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, isLoading: false },
      })

      expect(wrapper.find('[data-testid="kpi-card-value"]').exists()).toBe(true)
    })
  })

  describe('color variants', () => {
    it('applies amber-500 color classes to icon container', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, color: 'amber-500' },
      })

      const iconContainer = wrapper.find('.rounded-xl')
      expect(iconContainer.classes()).toContain('bg-amber-50')
      expect(iconContainer.classes()).toContain('text-amber-500')
    })

    it('applies green-500 color classes to icon container', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, color: 'green-500' },
      })

      const iconContainer = wrapper.find('.rounded-xl')
      expect(iconContainer.classes()).toContain('bg-green-50')
      expect(iconContainer.classes()).toContain('text-green-500')
    })

    it('applies blue-500 color classes to icon container', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, color: 'blue-500' },
      })

      const iconContainer = wrapper.find('.rounded-xl')
      expect(iconContainer.classes()).toContain('bg-blue-50')
      expect(iconContainer.classes()).toContain('text-blue-500')
    })

    it('applies primary color classes to icon container', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, color: 'primary' },
      })

      const iconContainer = wrapper.find('.rounded-xl')
      expect(iconContainer.classes()).toContain('bg-pink-50')
      expect(iconContainer.classes()).toContain('text-pink-500')
    })
  })

  describe('icon variants', () => {
    it('renders an icon for clock variant', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, icon: 'clock' },
      })

      // An SVG icon should be rendered
      expect(wrapper.find('svg').exists()).toBe(true)
    })

    it('renders an icon for check variant', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, icon: 'check' },
      })

      expect(wrapper.find('svg').exists()).toBe(true)
    })

    it('renders an icon for play variant', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, icon: 'play' },
      })

      expect(wrapper.find('svg').exists()).toBe(true)
    })

    it('renders an icon for checkCircle variant', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, icon: 'checkCircle' },
      })

      expect(wrapper.find('svg').exists()).toBe(true)
    })
  })

  describe('accessibility', () => {
    it('has aria-label with title and value', () => {
      const wrapper = mount(KpiCard, { props: defaultProps })

      const card = wrapper.find('[data-testid="kpi-card"]')
      expect(card.attributes('aria-label')).toBe('En attente: 5')
    })

    it('updates aria-label when value changes', async () => {
      const wrapper = mount(KpiCard, { props: defaultProps })

      await wrapper.setProps({ value: 10 })

      const card = wrapper.find('[data-testid="kpi-card"]')
      expect(card.attributes('aria-label')).toBe('En attente: 10')
    })
  })

  describe('styling', () => {
    it('has rounded card styling', () => {
      const wrapper = mount(KpiCard, { props: defaultProps })

      const card = wrapper.find('[data-testid="kpi-card"]')
      expect(card.classes()).toContain('rounded-2xl')
    })

    it('has hover effect class when linked', () => {
      const wrapper = mount(KpiCard, {
        props: { ...defaultProps, to: '/some-route' },
        global: { stubs: { RouterLink: { template: '<a><slot /></a>', props: ['to'] } } },
      })

      const card = wrapper.find('[data-testid="kpi-card"]')
      expect(card.classes()).toContain('hover:shadow-md')
    })

    it('does not have hover effect class when not linked', () => {
      const wrapper = mount(KpiCard, { props: defaultProps })

      const card = wrapper.find('[data-testid="kpi-card"]')
      expect(card.classes()).not.toContain('hover:shadow-md')
    })

    it('has white background', () => {
      const wrapper = mount(KpiCard, { props: defaultProps })

      const card = wrapper.find('[data-testid="kpi-card"]')
      expect(card.classes()).toContain('bg-white')
    })
  })
})
