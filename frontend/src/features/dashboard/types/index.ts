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
export type KpiColor = 'amber-500' | 'green-500' | 'blue-500' | 'primary' | 'purple-500' | 'gray-500'

/**
 * KPI card icon type
 */
export type KpiIcon = 'clock' | 'check' | 'play' | 'checkCircle' | 'users' | 'userCheck' | 'star' | 'shield' | 'edit' | 'file'

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
 * Mission counts grouped by status + total candidatures received (FR56) + unique collaborators (FR57) + rating (FR58) + advanced stats (FR59)
 */
export interface ProducerDashboardStats {
  published: number
  in_progress: number
  closed: number
  completed: number
  total_candidatures: number
  unique_collaborators: number
  average_rating: number | null
  ratings_count: number
  // FR59 - Advanced stats
  acceptance_rate: number // 0-100 percentage
  average_response_time_hours: number | null // null if no decisions made
  completed_missions_count: number // AC #5 explicit field (alias for completed)
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
 * KPI configurations for Producer dashboard (missions stats)
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

/**
 * KPI configuration for Producer candidatures (FR56)
 */
export const PRODUCER_CANDIDATURES_KPI: ProducerKpiConfig = {
  key: 'total_candidatures',
  title: 'Candidatures reçues',
  color: 'purple-500',
  bgColor: 'violet-50',
  icon: 'users',
}

/**
 * KPI configuration for Producer unique collaborators (FR57)
 */
export const PRODUCER_COLLABORATORS_KPI: ProducerKpiConfig = {
  key: 'unique_collaborators',
  title: 'Collaborateurs',
  color: 'green-500',
  bgColor: 'green-50',
  icon: 'userCheck',
}

/**
 * KPI configuration for Producer rating (FR58)
 * Note: Rating card uses custom display (not KpiCard) for decimal formatting.
 * This config is exported for reference and potential future use.
 */
export const PRODUCER_RATING_KPI: ProducerKpiConfig = {
  key: 'ratings_count',
  title: 'Ma note',
  color: 'amber-500',
  bgColor: 'amber-50',
  icon: 'star',
}

/**
 * KPI configuration for Producer acceptance rate (FR59)
 * Note: Uses custom display for percentage formatting.
 */
export const PRODUCER_ACCEPTANCE_RATE_KPI: ProducerKpiConfig = {
  key: 'acceptance_rate',
  title: "Taux d'acceptation",
  color: 'green-500',
  bgColor: 'green-50',
  icon: 'check',
}

/**
 * KPI configuration for Producer average response time (FR59)
 * Note: Uses custom display for hour formatting.
 */
export const PRODUCER_RESPONSE_TIME_KPI: ProducerKpiConfig = {
  key: 'average_response_time_hours',
  title: 'Temps de réponse',
  color: 'blue-500',
  bgColor: 'blue-50',
  icon: 'clock',
}
