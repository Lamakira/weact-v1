import axios from 'axios'

/**
 * Shared API client instance for public endpoints.
 * Used by all public API service modules to avoid duplication.
 */
export const publicApiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})
