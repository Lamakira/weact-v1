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
 * API response for dashboard stats
 */
export interface DashboardStatsResponse {
  data: DashboardStats
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
