import axios from 'axios'

/**
 * Shared API client instance for public endpoints.
 * Used by all public API service modules to avoid duplication.
 */
export const publicApiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})
