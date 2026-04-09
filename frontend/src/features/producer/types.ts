/**
 * Producer profile feature types
 */

// Producer type enum
export type ProducerType = 'agency' | 'particulier'

// Producer profile data from API
export interface Producer {
  id: string
  type: ProducerType
  agency_name: string | null
  first_name: string | null
  last_name: string | null
  display_name: string
  bio: string | null
  profile_photo_url: string | null
  thumbnail_url: string | null
  agency_logo_url: string | null
  agency_logo_thumbnail_url: string | null
  created_at: string
  updated_at: string
}

// Producer profile API response
export interface ProducerProfileResponse {
  data: Producer
  message?: string
}

// Profile photo upload result
export interface ProducerProfilePhotoResult {
  success: boolean
  data?: Producer
  errors?: Record<string, string[]>
  message?: string
}

// Bio data from API
export interface ProducerBioData {
  bio: string | null
}

// Bio API response
export interface ProducerBioResponse {
  data: ProducerBioData
  message?: string
}

// Bio update result
export interface ProducerBioResult {
  success: boolean
  data?: ProducerBioData
  errors?: Record<string, string[]>
  message?: string
}

// Agency logo data from API
export interface AgencyLogoData {
  agency_logo_url: string | null
  agency_logo_thumbnail_url: string | null
}

// Agency logo API response
export interface AgencyLogoResponse {
  data: AgencyLogoData
  message?: string
}

// Agency logo result
export interface AgencyLogoResult {
  success: boolean
  data?: AgencyLogoData | Producer
  errors?: Record<string, string[]>
  message?: string
}

// Producer basic info from API (type-dependent)
export interface ProducerBasicInfoAgency {
  type: 'agency'
  agency_name: string
}

export interface ProducerBasicInfoParticulier {
  type: 'particulier'
  first_name: string
  last_name: string
}

export type ProducerBasicInfo = ProducerBasicInfoAgency | ProducerBasicInfoParticulier

// Producer basic info API response
export interface ProducerBasicInfoResponse {
  data: ProducerBasicInfo
  message?: string
}

// Producer basic info form data (type-dependent)
export interface ProducerBasicInfoAgencyFormData {
  agency_name: string
}

export interface ProducerBasicInfoParticulierFormData {
  first_name?: string
  last_name?: string
}

export type ProducerBasicInfoFormData =
  | ProducerBasicInfoAgencyFormData
  | ProducerBasicInfoParticulierFormData

// Producer basic info operation result
export interface ProducerBasicInfoResult {
  success: boolean
  data?: ProducerBasicInfo
  errors?: Record<string, string[]>
  message?: string
}
