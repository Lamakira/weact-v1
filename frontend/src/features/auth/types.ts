/**
 * Auth feature types
 */

// Login form data
export interface LoginForm {
  email: string
  password: string
}

// Registration form data - Face
export interface FaceRegistrationForm {
  nom: string
  prenom: string
  email: string
  date_naissance: string
  password: string
  accept_cgu: boolean
}

// Producer type
export type ProducerType = 'agency' | 'particulier'

// Registration form data - Producer (base)
export interface ProducerRegistrationFormBase {
  type: ProducerType
  email: string
  password: string
  accept_cgu: boolean
}

// Registration form data - Producer Agency
export interface AgencyRegistrationForm extends ProducerRegistrationFormBase {
  type: 'agency'
  agency_name: string
}

// Registration form data - Producer Particulier
// Collected as one field; the backend splits it into first_name/last_name.
export interface ParticulierRegistrationForm extends ProducerRegistrationFormBase {
  type: 'particulier'
  nom_complet: string
}

// Union type for Producer registration
export type ProducerRegistrationForm = AgencyRegistrationForm | ParticulierRegistrationForm

// Face data from API
export interface Face {
  id: string
  nom: string
  prenom: string
  username: string
  sexe: string | null
  sexe_label?: string | null
  created_at: string
  updated_at: string
}

// Producer data from API
export interface Producer {
  id: string
  type: ProducerType
  agency_name: string | null
  first_name: string | null
  last_name: string | null
  display_name: string
  created_at: string
  updated_at: string
}

// User data from API
export interface User {
  id: number
  email: string
  userable_type: 'Face' | 'Producer'
  userable_id: number
  userable?: Face | Producer | null
  email_verified: boolean
  email_verified_at: string | null
  // Optional: sessions restored from localStorage predate this field. Read it
  // through the auth store's `hasPassword`, which defaults it to true.
  has_password?: boolean
  created_at: string
  updated_at: string
}

// Which button started the Google flow. Drives what the finalisation screen asks
// for, and is ignored entirely when the account already exists (userable_type is
// never mutated).
export type GoogleIntent = 'face' | 'producer' | 'login'

// Result of trading the one-shot callback code.
export interface GoogleExchangeResult {
  needs_completion: boolean
  redirect: string | null
  // Present when needs_completion is false
  user?: User
  token?: string
  // Present when needs_completion is true
  pending_token?: string
  email?: string
  prenom?: string
  nom?: string
  intent?: GoogleIntent
}

export interface CompleteGoogleRegistrationData {
  pending_token: string
  role: 'face' | 'producer'
  accept_cgu: boolean
  // Face branch
  nom?: string
  prenom?: string
  date_naissance?: string
  // Producer branch
  type?: ProducerType
  agency_name?: string
  nom_complet?: string
}

// Auth response from API
export interface AuthResponse {
  data: {
    user: User
    token: string
  }
  message: string
}

// API error response
export interface ApiError {
  error: {
    code: string
    message: string
    details: Record<string, string[]>
  }
}

// Password reset data
export interface ResetPasswordData {
  token: string
  email: string
  password: string
  password_confirmation: string
}
