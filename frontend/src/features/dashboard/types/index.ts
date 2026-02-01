/**
 * Dashboard feature types
 */

/**
 * Dashboard statistics for Face
 * Candidature counts grouped by status
 */
export interface DashboardStats {
  pending: number
  accepted: number
  in_progress: number
  completed: number
}

/**
 * Monthly candidature statistics grouped by status
 * Used for candidatures_by_month chart data
 */
export interface MonthlyStats {
  month: string // Format: "YYYY-MM"
  pending: number
  accepted: number
  confirmed: number
  in_progress: number
  completed: number
  rejected: number
}

/**
 * Monthly count for completed missions
 * Used for missions_completed_by_month chart data
 */
export interface MonthlyCount {
  month: string // Format: "YYYY-MM"
  count: number
}

/**
 * Chart statistics data returned from the API
 * Contains last 6 months of aggregated data
 */
export interface ChartStats {
  candidatures_by_month: MonthlyStats[]
  missions_completed_by_month: MonthlyCount[]
}

/**
 * API response for chart stats
 */
export interface ChartStatsResponse {
  data: ChartStats
  message: string
}

/**
 * API response for dashboard stats
 */
export interface DashboardStatsResponse {
  data: DashboardStats
  message: string
}

/**
 * Available missions count data
 */
export interface MissionsCount {
  count: number
}

/**
 * API response for available missions count
 */
export interface MissionsCountResponse {
  data: MissionsCount
  message: string
}

/**
 * KPI card color type
 */
export type KpiColor = 'amber-500' | 'green-500' | 'blue-500' | 'primary'

/**
 * KPI card icon type
 */
export type KpiIcon = 'clock' | 'check' | 'play' | 'checkCircle'

/**
 * KPI card configuration
 */
export interface KpiConfig {
  key: keyof DashboardStats
  title: string
  color: KpiColor
  bgColor: string
  icon: KpiIcon
}

/**
 * KPI configurations for Face dashboard
 * Matches the French labels from FR51
 */
export const FACE_KPI_CONFIGS: KpiConfig[] = [
  {
    key: 'pending',
    title: 'En attente',
    color: 'amber-500',
    bgColor: 'amber-50',
    icon: 'clock',
  },
  {
    key: 'accepted',
    title: 'Acceptées',
    color: 'green-500',
    bgColor: 'green-50',
    icon: 'check',
  },
  {
    key: 'in_progress',
    title: 'En cours',
    color: 'blue-500',
    bgColor: 'blue-50',
    icon: 'play',
  },
  {
    key: 'completed',
    title: 'Terminées',
    color: 'primary',
    bgColor: 'primary/10',
    icon: 'checkCircle',
  },
]

/**
 * Producer Dashboard statistics
 * Mission counts grouped by status
 */
export interface ProducerDashboardStats {
  published: number
  in_progress: number
  closed: number
  completed: number
}

/**
 * API response for Producer dashboard stats
 */
export interface ProducerDashboardStatsResponse {
  data: ProducerDashboardStats
  message: string
}

/**
 * KPI card configuration for Producer dashboard
 */
export interface ProducerKpiConfig {
  key: keyof ProducerDashboardStats
  title: string
  color: KpiColor
  bgColor: string
  icon: KpiIcon
}

/**
 * KPI configurations for Producer dashboard
 * Matches the French labels from FR55
 */
export const PRODUCER_KPI_CONFIGS: ProducerKpiConfig[] = [
  {
    key: 'published',
    title: 'Publiées',
    color: 'primary',
    bgColor: 'primary/10',
    icon: 'check',
  },
  {
    key: 'in_progress',
    title: 'En cours',
    color: 'blue-500',
    bgColor: 'blue-50',
    icon: 'play',
  },
  {
    key: 'closed',
    title: 'Clôturées',
    color: 'amber-500',
    bgColor: 'amber-50',
    icon: 'clock',
  },
  {
    key: 'completed',
    title: 'Terminées',
    color: 'green-500',
    bgColor: 'green-50',
    icon: 'checkCircle',
  },
]
