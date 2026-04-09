import { AxiosError } from 'axios'
import { publicApiClient } from './apiClient'
import type { PaginationMeta } from './publicFacesApi'

/**
 * Public article data (PublicArticleResource shape)
 * Only contains public-safe fields for unauthenticated visitors
 */
export interface PublicArticle {
  id: string
  title: string
  slug: string
  excerpt: string | null
  category: {
    value: string
    label: string
  }
  featured_image: string | null
  published_at: string
  created_at: string
  author_name: string | null
}

/**
 * Public article detail data (PublicArticleDetailResource shape)
 */
export interface PublicArticleDetail {
  id: string
  title: string
  slug: string
  content: string
  excerpt: string | null
  category: {
    value: string
    label: string
  }
  featured_image: string | null
  published_at: string
  created_at: string
  author_name: string | null
}

/**
 * API response for paginated articles list
 */
export interface PublicArticlesResponse {
  data: PublicArticle[]
  meta: PaginationMeta
  message: string
}

/**
 * API response for a single article detail
 */
export interface PublicArticleDetailResponse {
  data: PublicArticleDetail
  message: string
}

/**
 * Result type for article detail fetch with error handling
 */
export interface PublicArticleDetailResult {
  success: boolean
  article?: PublicArticleDetail
  error?: string
  notFound?: boolean
}

/**
 * Fetch paginated list of public articles
 */
export async function fetchPublicArticles(
  page: number = 1,
  perPage: number = 15,
  category?: string,
): Promise<PublicArticlesResponse> {
  const validPerPage = Math.min(Math.max(1, perPage), 30)

  const params: Record<string, string | number> = {
    page,
    per_page: validPerPage,
  }

  if (category) {
    params.category = category
  }

  const response = await publicApiClient.get<PublicArticlesResponse>('/public/articles', {
    params,
  })

  return response.data
}

/**
 * Fetch a single public article by slug
 */
export async function fetchPublicArticleDetail(
  slug: string,
): Promise<PublicArticleDetailResult> {
  try {
    const response = await publicApiClient.get<PublicArticleDetailResponse>(
      `/public/articles/${encodeURIComponent(slug)}`,
    )

    return {
      success: true,
      article: response.data.data,
    }
  } catch (err) {
    const axiosError = err as AxiosError

    if (axiosError.response?.status === 404) {
      return {
        success: false,
        notFound: true,
        error: 'Article non trouvé',
      }
    }

    return {
      success: false,
      error: 'Une erreur est survenue. Veuillez réessayer.',
    }
  }
}
