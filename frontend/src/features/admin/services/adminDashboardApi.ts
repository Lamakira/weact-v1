import adminApiClient from './adminApiClient'

/**
 * Admin dashboard stats data
 */
export interface AdminDashboardStats {
  users: {
    faces: number
    producers: number
    admins: number
  }
  missions: {
    published: number
    closed: number
    completed: number
    draft: number
  }
  articles: {
    published: number
    draft: number
  }
  candidatures: {
    total: number
    completed: number
  }
}

/**
 * API response for admin dashboard stats
 */
export interface AdminDashboardStatsResponse {
  data: AdminDashboardStats
  message: string
}

/**
 * Admin dashboard API service
 * Uses adminApiClient (NOT apiClient) for admin-specific auth
 */
export const adminDashboardApi = {
  /**
   * Get global platform KPIs for admin dashboard
   * @returns Dashboard stats with users, missions, articles, candidatures counts
   */
  async getStats(): Promise<AdminDashboardStatsResponse> {
    const response = await adminApiClient.get<AdminDashboardStatsResponse>('/admin/dashboard/stats')
    return response.data
  },
}
