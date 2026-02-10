import adminApiClient, { getCsrfCookie } from './adminApiClient'

/**
 * Admin data from API
 */
export interface AdminData {
  id: number
  name: string
  email: string
  role: 'superadmin' | 'admin' | 'editor'
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
 * Single admin detail response
 */
export interface AdminDetailResponse {
  data: AdminData
  message: string
}

/**
 * Update admin form data
 */
export interface UpdateAdminForm {
  name?: string
  email?: string
  role?: string
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

  /**
   * Get a single admin by ID
   */
  async getAdmin(id: number): Promise<AdminDetailResponse> {
    const response = await adminApiClient.get<AdminDetailResponse>(`/admin/admins/${id}`)
    return response.data
  },

  /**
   * Update an admin's fields
   */
  async updateAdmin(id: number, data: UpdateAdminForm): Promise<AdminDetailResponse> {
    await getCsrfCookie()
    const response = await adminApiClient.put<AdminDetailResponse>(`/admin/admins/${id}`, data)
    return response.data
  },

  /**
   * Delete an admin account
   */
  async deleteAdmin(id: number): Promise<{ message: string }> {
    await getCsrfCookie()
    const response = await adminApiClient.delete<{ message: string }>(`/admin/admins/${id}`)
    return response.data
  },
}
