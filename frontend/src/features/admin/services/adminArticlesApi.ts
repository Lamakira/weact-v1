import adminApiClient from './adminApiClient'

/**
 * Article category enum value + label
 */
export interface ArticleCategoryField {
  value: string
  label: string
}

/**
 * Article status enum value + label
 */
export interface ArticleStatusField {
  value: string
  label: string
}

/**
 * Article data from admin API (ArticleResource shape)
 */
export interface AdminArticleData {
  id: string
  title: string
  slug: string
  content: string
  excerpt: string | null
  category: ArticleCategoryField
  status: ArticleStatusField
  featured_image: string | null
  published_at: string | null
  created_at: string
  updated_at: string
  admin: {
    id: string
    name: string
  } | null
}

/**
 * Paginated article list response
 */
export interface AdminArticleListResponse {
  data: AdminArticleData[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  message: string
}

/**
 * Single article response
 */
export interface AdminArticleDetailResponse {
  data: AdminArticleData
  message: string
}

/**
 * Query params for listing articles
 */
export interface AdminArticleListParams {
  page?: number
  search?: string
  category?: string
  status?: string
}

/**
 * Create article form data
 */
export interface CreateArticleForm {
  title: string
  content: string
  excerpt?: string
  category: string
  status?: string
  featured_image?: File
}

/**
 * Update article form data
 */
export interface UpdateArticleForm {
  title?: string
  content?: string
  excerpt?: string
  category?: string
  status?: string
  featured_image?: File
}

/**
 * Admin article management API service
 */
export const adminArticlesApi = {
  /**
   * Get paginated list of articles with optional search/filters
   */
  async getArticles(params?: AdminArticleListParams): Promise<AdminArticleListResponse> {
    const response = await adminApiClient.get<AdminArticleListResponse>('/admin/articles', {
      params,
    })
    return response.data
  },

  /**
   * Get a single article by ID
   */
  async getArticle(id: string): Promise<AdminArticleDetailResponse> {
    const response = await adminApiClient.get<AdminArticleDetailResponse>(`/admin/articles/${id}`)
    return response.data
  },

  /**
   * Create a new article (supports file upload for featured_image)
   */
  async createArticle(data: CreateArticleForm): Promise<AdminArticleDetailResponse> {
    const formData = new FormData()
    formData.append('title', data.title)
    formData.append('content', data.content)
    formData.append('category', data.category)
    if (data.excerpt) formData.append('excerpt', data.excerpt)
    if (data.status) formData.append('status', data.status)
    if (data.featured_image) formData.append('featured_image', data.featured_image)

    const response = await adminApiClient.post<AdminArticleDetailResponse>(
      '/admin/articles',
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    )
    return response.data
  },

  /**
   * Update an existing article (supports file upload for featured_image)
   */
  async updateArticle(id: string, data: UpdateArticleForm): Promise<AdminArticleDetailResponse> {
    const formData = new FormData()
    if (data.title) formData.append('title', data.title)
    if (data.content) formData.append('content', data.content)
    if (data.excerpt !== undefined) formData.append('excerpt', data.excerpt ?? '')
    if (data.category) formData.append('category', data.category)
    if (data.status) formData.append('status', data.status)
    if (data.featured_image) formData.append('featured_image', data.featured_image)
    // Laravel PUT with FormData needs _method override
    formData.append('_method', 'PUT')

    const response = await adminApiClient.post<AdminArticleDetailResponse>(
      `/admin/articles/${id}`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    )
    return response.data
  },

  /**
   * Delete an article
   */
  async deleteArticle(id: string): Promise<{ message: string }> {
    const response = await adminApiClient.delete<{ message: string }>(`/admin/articles/${id}`)
    return response.data
  },

  /**
   * Update an article's status (draft ↔ published)
   */
  async updateArticleStatus(
    id: string,
    status: string,
  ): Promise<AdminArticleDetailResponse> {
    const response = await adminApiClient.patch<AdminArticleDetailResponse>(
      `/admin/articles/${id}/status`,
      { status },
    )
    return response.data
  },

  /**
   * Update an article's category
   */
  async updateArticleCategory(
    id: string,
    category: string,
  ): Promise<AdminArticleDetailResponse> {
    const response = await adminApiClient.patch<AdminArticleDetailResponse>(
      `/admin/articles/${id}/category`,
      { category },
    )
    return response.data
  },
}
