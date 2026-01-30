import apiClient from '@/services/apiClient'
import type { DashboardStatsResponse, ChartStatsResponse, MissionsCountResponse } from '../types'

/**
 * Dashboard API service
 * Endpoints for Face users to get their dashboard statistics
 */
export const dashboardApi = {
  /**
   * Get Face dashboard statistics
   * Returns candidature counts grouped by status
   * @returns Dashboard stats with pending, accepted, in_progress, completed counts
   */
  async getStats(): Promise<DashboardStatsResponse> {
    const response = await apiClient.get<DashboardStatsResponse>('/face/dashboard/stats')
    return response.data
  },

  /**
   * Get Face dashboard chart statistics
   * Returns aggregated data for the last 6 months:
   * - candidatures_by_month: Candidatures grouped by month and status
   * - missions_completed_by_month: Completed missions grouped by month
   * @returns Chart stats for activity evolution charts
   */
  async getChartStats(): Promise<ChartStatsResponse> {
    const response = await apiClient.get<ChartStatsResponse>('/face/dashboard/chart-stats')
    return response.data
  },

  /**
   * Get available missions count
   * Returns count of published missions with open candidature deadlines
   * @returns Available missions count for quick access card
   */
  async getAvailableMissionsCount(): Promise<MissionsCountResponse> {
    const response = await apiClient.get<MissionsCountResponse>('/face/dashboard/available-missions-count')
    return response.data
  },
}
