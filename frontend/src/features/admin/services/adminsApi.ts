import adminApiClient, { getCsrfCookie } from './adminApiClient'

/**
 * Admin data from API
 */
export interface AdminData {
  id: number
  name: string
  email: string
  created_at: string
}

/**
 * Paginated admin list response
 */
export interface AdminListResponse {
  data: AdminData[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

/**
 * Create admin form data
 */
export interface CreateAdminForm {
  name: string
  email: string
  password: string
  password_confirmation: string
}

/**
 * Create admin success response
 */
export interface CreateAdminResponse {
  data: AdminData
  message: string
}

/**
 * Admin management API service
 */
export const adminsApi = {
  /**
   * Get paginated list of admins
   */
  async getAdmins(page: number = 1): Promise<AdminListResponse> {
    const response = await adminApiClient.get<AdminListResponse>('/admin/admins', {
      params: { page },
    })
    return response.data
  },

  /**
   * Create a new admin account
   */
  async createAdmin(data: CreateAdminForm): Promise<CreateAdminResponse> {
    await getCsrfCookie()
    const response = await adminApiClient.post<CreateAdminResponse>('/admin/admins', data)
    return response.data
  },
}
