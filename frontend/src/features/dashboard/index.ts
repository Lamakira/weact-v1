/**
 * Dashboard feature exports
 */

// Types
export * from './types'

// Components
export { default as KpiCard } from './components/KpiCard.vue'
export { default as WalletCard } from './components/WalletCard.vue'
export { default as ActivityChart } from './components/ActivityChart.vue'
export { default as MissionsQuickAccessCard } from './components/MissionsQuickAccessCard.vue'
export { default as MessagesCard } from './components/MessagesCard.vue'

// Composables
export { useDashboardStats } from './composables/useDashboardStats'
export { useDashboardCharts } from './composables/useDashboardCharts'
export { useMissionsCount } from './composables/useMissionsCount'

// Services
export { dashboardApi } from './services/dashboardApi'
