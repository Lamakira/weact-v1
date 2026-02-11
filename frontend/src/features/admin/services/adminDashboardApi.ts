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
 * Admin dashboard trends data
 */
export interface AdminDashboardTrends {
  registrations: {
    faces_7d: number
    faces_30d: number
    producers_7d: number
    producers_30d: number
  }
  missions: {
    published_7d: number
    published_30d: number
    completed_7d: number
    completed_30d: number
  }
  candidatures: {
    submitted_7d: number
    submitted_30d: number
    acceptance_rate_30d: number
  }
  health: {
    missions_without_candidatures_30d: number
    active_users_7d: number
  }
}

/**
 * Recent activity event
 */
export interface RecentActivityEvent {
  type: string
  title: string
  description: string
  timestamp: string
  link: string | null
}

/**
 * API response for admin dashboard stats
 */
export interface AdminDashboardStatsResponse {
  data: AdminDashboardStats
  message: string
}

export interface AdminDashboardTrendsResponse {
  data: AdminDashboardTrends
  message: string
}

export interface AdminDashboardRecentActivityResponse {
  data: RecentActivityEvent[]
  message: string
}

/**
 * Admin dashboard API service
 * Uses adminApiClient (NOT apiClient) for admin-specific auth
 */
export const adminDashboardApi = {
  async getStats(): Promise<AdminDashboardStatsResponse> {
    const response = await adminApiClient.get<AdminDashboardStatsResponse>('/admin/dashboard/stats')
    return response.data
  },

  async getTrends(): Promise<AdminDashboardTrendsResponse> {
    const response = await adminApiClient.get<AdminDashboardTrendsResponse>('/admin/dashboard/trends')
    return response.data
  },

  async getRecentActivity(): Promise<AdminDashboardRecentActivityResponse> {
    const response = await adminApiClient.get<AdminDashboardRecentActivityResponse>(
      '/admin/dashboard/recent-activity',
    )
    return response.data
  },
}
