/**
 * Dashboard feature exports
 */

// Types
export * from './types'

// Components: ActivityChart / BookingActivityChart (chart.js) volontairement hors du barrel,
// importés en asynchrone par FaceDashboardPage pour rester dans un chunk séparé.

// Composables
export { useDashboardStats } from './composables/useDashboardStats'
export { useDashboardCharts } from './composables/useDashboardCharts'
export { useMissionsCount } from './composables/useMissionsCount'
export { useBookingStats } from './composables/useBookingStats'
export { useDashboardBookingCharts } from './composables/useDashboardBookingCharts'
