/**
 * Auth feature types
 */

// Registration form data - Face
export interface FaceRegistrationForm {
  nom: string
  prenom: string
  username: string
  email: string
  password: string
  password_confirmation: string
}

// Producer type
export type ProducerType = 'agency' | 'particulier'

// Registration form data - Producer (base)
export interface ProducerRegistrationFormBase {
  type: ProducerType
  email: string
  password: string
  password_confirmation: string
}

// Registration form data - Producer Agency
export interface AgencyRegistrationForm extends ProducerRegistrationFormBase {
  type: 'agency'
  agency_name: string
}

// Registration form data - Producer Particulier
export interface ParticulierRegistrationForm extends ProducerRegistrationFormBase {
  type: 'particulier'
  first_name: string
  last_name: string
}

// Union type for Producer registration
export type ProducerRegistrationForm = AgencyRegistrationForm | ParticulierRegistrationForm

// Face data from API
export interface Face {
  id: number
  nom: string
  prenom: string
  username: string
  created_at: string
  updated_at: string
}

// Producer data from API
export interface Producer {
  id: number
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
  userable: Face | Producer | null
  email_verified_at: string | null
  created_at: string
  updated_at: string
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

// Auth state
export interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  isLoading: boolean
}
