import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ActivityChart from '../ActivityChart.vue'
import type { MonthlyStats, MonthlyCount } from '../../types'

// Mock Chart.js components to avoid canvas rendering issues in tests
vi.mock('vue-chartjs', () => ({
  Bar: {
    name: 'Bar',
    template: '<canvas class="bar-chart-mock">Bar Chart</canvas>',
    props: ['data', 'options'],
  },
  Line: {
    name: 'Line',
    template: '<canvas class="line-chart-mock">Line Chart</canvas>',
    props: ['data', 'options'],
  },
}))

// Mock chart.js registration
vi.mock('chart.js', () => ({
  Chart: {
    register: vi.fn(),
  },
  Title: {},
  Tooltip: {},
  Legend: {},
  BarElement: {},
  CategoryScale: {},
  LinearScale: {},
  PointElement: {},
  LineElement: {},
  Filler: {},
}))

const mockCandidaturesByMonth: MonthlyStats[] = [
  {
    month: '2026-01',
    pending: 3,
    accepted: 2,
    confirmed: 0,
    in_progress: 1,
    completed: 4,
    rejected: 0,
  },
  {
    month: '2025-12',
    pending: 2,
    accepted: 1,
    confirmed: 0,
    in_progress: 2,
    completed: 3,
    rejected: 1,
  },
]

const mockMissionsCompletedByMonth: MonthlyCount[] = [
  { month: '2026-01', count: 4 },
  { month: '2025-12', count: 3 },
]

const emptyMonthlyStats: MonthlyStats[] = []
const emptyMissionsCompleted: MonthlyCount[] = []

describe('ActivityChart', () => {
  describe('rendering with data', () => {
    it('renders the chart section', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: mockCandidaturesByMonth,
          missionsCompletedByMonth: mockMissionsCompletedByMonth,
          isLoading: false,
          error: null,
        },
      })

      expect(wrapper.find('[data-testid="activity-chart"]').exists()).toBe(true)
    })

    it('renders section title "Mon évolution"', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: mockCandidaturesByMonth,
          missionsCompletedByMonth: mockMissionsCompletedByMonth,
          isLoading: false,
          error: null,
        },
      })

      expect(wrapper.text()).toContain('Mon évolution')
    })

    it('renders the candidatures chart when data is available', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: mockCandidaturesByMonth,
          missionsCompletedByMonth: mockMissionsCompletedByMonth,
          isLoading: false,
          error: null,
        },
      })

      expect(wrapper.find('[data-testid="candidatures-chart"]').exists()).toBe(true)
      expect(wrapper.find('.bar-chart-mock').exists()).toBe(true)
    })

    it('renders the missions completed chart when data is available', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: mockCandidaturesByMonth,
          missionsCompletedByMonth: mockMissionsCompletedByMonth,
          isLoading: false,
          error: null,
        },
      })

      expect(wrapper.find('[data-testid="missions-chart"]').exists()).toBe(true)
      expect(wrapper.find('.line-chart-mock').exists()).toBe(true)
    })

    it('displays chart titles', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: mockCandidaturesByMonth,
          missionsCompletedByMonth: mockMissionsCompletedByMonth,
          isLoading: false,
          error: null,
        },
      })

      expect(wrapper.text()).toContain('Candidatures par mois')
      expect(wrapper.text()).toContain('Missions terminées')
    })
  })

  describe('loading state', () => {
    it('shows loading skeleton for candidatures chart', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: emptyMonthlyStats,
          missionsCompletedByMonth: emptyMissionsCompleted,
          isLoading: true,
          error: null,
        },
      })

      expect(wrapper.find('[data-testid="candidatures-chart-loading"]').exists()).toBe(true)
    })

    it('shows loading skeleton for missions chart', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: emptyMonthlyStats,
          missionsCompletedByMonth: emptyMissionsCompleted,
          isLoading: true,
          error: null,
        },
      })

      expect(wrapper.find('[data-testid="missions-chart-loading"]').exists()).toBe(true)
    })

    it('does not render charts when loading', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: mockCandidaturesByMonth,
          missionsCompletedByMonth: mockMissionsCompletedByMonth,
          isLoading: true,
          error: null,
        },
      })

      expect(wrapper.find('.bar-chart-mock').exists()).toBe(false)
      expect(wrapper.find('.line-chart-mock').exists()).toBe(false)
    })
  })

  describe('empty state', () => {
    it('shows empty state for candidatures chart when no data', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: emptyMonthlyStats,
          missionsCompletedByMonth: emptyMissionsCompleted,
          isLoading: false,
          error: null,
        },
      })

      expect(wrapper.find('[data-testid="candidatures-chart-empty"]').exists()).toBe(true)
    })

    it('shows empty state for missions chart when no data', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: emptyMonthlyStats,
          missionsCompletedByMonth: emptyMissionsCompleted,
          isLoading: false,
          error: null,
        },
      })

      expect(wrapper.find('[data-testid="missions-chart-empty"]').exists()).toBe(true)
    })

    it('displays helpful message in empty state', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: emptyMonthlyStats,
          missionsCompletedByMonth: emptyMissionsCompleted,
          isLoading: false,
          error: null,
        },
      })

      expect(wrapper.text()).toContain('Pas encore de données')
      expect(wrapper.text()).toContain('Commencez à postuler aux missions')
    })
  })

  describe('error state', () => {
    it('shows error state for candidatures chart', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: emptyMonthlyStats,
          missionsCompletedByMonth: emptyMissionsCompleted,
          isLoading: false,
          error: 'Une erreur est survenue',
        },
      })

      expect(wrapper.find('[data-testid="candidatures-chart-error"]').exists()).toBe(true)
    })

    it('shows error state for missions chart', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: emptyMonthlyStats,
          missionsCompletedByMonth: emptyMissionsCompleted,
          isLoading: false,
          error: 'Une erreur est survenue',
        },
      })

      expect(wrapper.find('[data-testid="missions-chart-error"]').exists()).toBe(true)
    })

    it('displays the error message', () => {
      const errorMessage = 'Impossible de charger les données'
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: emptyMonthlyStats,
          missionsCompletedByMonth: emptyMissionsCompleted,
          isLoading: false,
          error: errorMessage,
        },
      })

      expect(wrapper.text()).toContain(errorMessage)
    })

    it('shows retry button in error state', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: emptyMonthlyStats,
          missionsCompletedByMonth: emptyMissionsCompleted,
          isLoading: false,
          error: 'Une erreur est survenue',
        },
      })

      expect(wrapper.find('[data-testid="candidatures-chart-retry"]').exists()).toBe(true)
    })

    it('emits retry event when retry button is clicked', async () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: emptyMonthlyStats,
          missionsCompletedByMonth: emptyMissionsCompleted,
          isLoading: false,
          error: 'Une erreur est survenue',
        },
      })

      await wrapper.find('[data-testid="candidatures-chart-retry"]').trigger('click')

      expect(wrapper.emitted('retry')).toHaveLength(1)
    })
  })

  describe('responsive layout', () => {
    it('has grid layout with lg:grid-cols-2 for desktop', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: mockCandidaturesByMonth,
          missionsCompletedByMonth: mockMissionsCompletedByMonth,
          isLoading: false,
          error: null,
        },
      })

      const grid = wrapper.find('[data-testid="activity-chart"] > div')
      expect(grid.classes()).toContain('lg:grid-cols-2')
      expect(grid.classes()).toContain('grid-cols-1')
    })
  })

  describe('styling', () => {
    it('has white background on chart cards', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: mockCandidaturesByMonth,
          missionsCompletedByMonth: mockMissionsCompletedByMonth,
          isLoading: false,
          error: null,
        },
      })

      const chartCard = wrapper.find('[data-testid="candidatures-chart"]')
      expect(chartCard.classes()).toContain('bg-white')
    })

    it('has rounded corners on chart cards', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: mockCandidaturesByMonth,
          missionsCompletedByMonth: mockMissionsCompletedByMonth,
          isLoading: false,
          error: null,
        },
      })

      const chartCard = wrapper.find('[data-testid="candidatures-chart"]')
      expect(chartCard.classes()).toContain('rounded-2xl')
    })

    it('has shadow-sm on chart cards', () => {
      const wrapper = mount(ActivityChart, {
        props: {
          candidaturesByMonth: mockCandidaturesByMonth,
          missionsCompletedByMonth: mockMissionsCompletedByMonth,
          isLoading: false,
          error: null,
        },
      })

      const chartCard = wrapper.find('[data-testid="candidatures-chart"]')
      expect(chartCard.classes()).toContain('shadow-sm')
    })
  })
})
