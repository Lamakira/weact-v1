import { isAxiosError, type AxiosError } from 'axios'

/**
 * Centralized API error message extraction (FIX-22.3).
 *
 * Single source of truth for reading error messages from Axios responses.
 * Designed for the standardized envelope { error: { message, code, details } }
 * delivered by the backend global exception handler (bootstrap/app.php, FIX-22.2).
 *
 * Preserves backward compatibility with:
 *  - legacy { message } at top-level (422 ValidationException shim, AC #9 FIX-22.2)
 *  - legacy { errors: { field: [msg, ...] } } at top-level (same shim)
 *
 * Usage:
 *   catch (err) {
 *     errorRef.value = formatApiError(err, 'Une erreur est survenue.')
 *   }
 */

type ErrorDetails = Record<string, string[]>

interface ApiErrorResponse {
  error?: {
    message?: string
    code?: string
    details?: unknown
  }
  errors?: unknown
  message?: string
}

export const GENERIC_VALIDATION_MESSAGE = 'Les données fournies ne sont pas valides'
const NETWORK_ERROR_MESSAGE = 'Impossible de se connecter au serveur. Vérifiez votre connexion.'
const GENERIC_ERROR_MESSAGE = 'Une erreur est survenue. Veuillez réessayer.'

function getAxiosError(error: unknown): AxiosError<ApiErrorResponse> | null {
  if (!isAxiosError(error)) {
    return null
  }

  return error as AxiosError<ApiErrorResponse>
}

function isNonEmptyString(value: unknown): value is string {
  return typeof value === 'string' && value.trim().length > 0
}

function isStringArrayRecord(value: unknown): value is ErrorDetails {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    return false
  }

  return Object.values(value).every(
    (entry) => Array.isArray(entry) && entry.every((item) => typeof item === 'string'),
  )
}

function getFirstDetailMessage(details: ErrorDetails): string | null {
  for (const messages of Object.values(details)) {
    const firstMessage = messages[0]
    if (isNonEmptyString(firstMessage)) {
      return firstMessage
    }
  }

  return null
}

function getStatusFallback(status: number, fallback?: string): string {
  switch (status) {
    case 401:
      return "Vous n'êtes pas autorisé à effectuer cette action."
    case 403:
      return 'Accès refusé.'
    case 404:
      return "La ressource demandée n'existe pas."
    case 419:
      return 'Votre session a expiré, veuillez rafraîchir la page.'
    case 422:
      return 'Les données fournies ne sont pas valides.'
    case 429:
      return 'Trop de tentatives. Veuillez réessayer dans quelques instants.'
    case 500:
    case 502:
    case 503:
      return 'Le serveur est temporairement indisponible. Veuillez réessayer.'
    default:
      return fallback ?? GENERIC_ERROR_MESSAGE
  }
}

export function getApiErrorDetails(error: unknown): ErrorDetails {
  const axiosError = getAxiosError(error)
  if (!axiosError) {
    return {}
  }

  const standardizedDetails = axiosError.response?.data?.error?.details
  if (isStringArrayRecord(standardizedDetails) && Object.keys(standardizedDetails).length > 0) {
    return standardizedDetails
  }

  const legacyDetails = axiosError.response?.data?.errors
  if (isStringArrayRecord(legacyDetails) && Object.keys(legacyDetails).length > 0) {
    return legacyDetails
  }

  return {}
}

export function getApiErrorCode(error: unknown): string | null {
  const axiosError = getAxiosError(error)
  const code = axiosError?.response?.data?.error?.code

  return isNonEmptyString(code) ? code : null
}

export function isNetworkError(error: unknown): boolean {
  const axiosError = getAxiosError(error)

  if (!axiosError) {
    return false
  }

  return (
    axiosError.response === undefined ||
    axiosError.code === 'ERR_NETWORK' ||
    axiosError.code === 'ECONNABORTED'
  )
}

export function formatApiError(error: unknown, fallback?: string): string {
  const axiosError = getAxiosError(error)

  if (axiosError) {
    const data = axiosError.response?.data
    const details = getApiErrorDetails(axiosError)
    const detailMessage = getFirstDetailMessage(details)
    const errorMessage = data?.error?.message

    if (
      detailMessage &&
      (!isNonEmptyString(errorMessage) || errorMessage === GENERIC_VALIDATION_MESSAGE)
    ) {
      return detailMessage
    }

    if (isNonEmptyString(errorMessage)) {
      return errorMessage
    }

    if (detailMessage) {
      return detailMessage
    }

    if (isNonEmptyString(data?.message)) {
      return data.message
    }

    if (isNetworkError(axiosError)) {
      return fallback ?? NETWORK_ERROR_MESSAGE
    }

    if (typeof axiosError.response?.status === 'number') {
      return getStatusFallback(axiosError.response.status, fallback)
    }
  }

  return fallback ?? GENERIC_ERROR_MESSAGE
}
