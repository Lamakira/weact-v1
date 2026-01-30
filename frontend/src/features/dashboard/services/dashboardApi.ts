import apiClient from '@/services/apiClient'
import type { DashboardStatsResponse } from '../types'

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
}
