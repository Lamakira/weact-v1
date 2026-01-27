/**
 * Mission feature types
 */

// Mission status enum
export const MissionStatus = {
  DRAFT: 'draft',
  PUBLISHED: 'published',
  CLOSED: 'closed',
  COMPLETED: 'completed',
} as const

export type MissionStatusType = (typeof MissionStatus)[keyof typeof MissionStatus]

// Mission status labels (French)
export const MissionStatusLabel: Record<MissionStatusType, string> = {
  [MissionStatus.DRAFT]: 'Brouillon',
  [MissionStatus.PUBLISHED]: 'Publiée',
  [MissionStatus.CLOSED]: 'Clôturée',
  [MissionStatus.COMPLETED]: 'Terminée',
}

// Mission type enum
export const MissionType = {
  PUBLICITE: 'publicite',
  FILM: 'film',
  COURT_METRAGE: 'court_metrage',
  CLIP_MUSICAL: 'clip_musical',
  AUTRE: 'autre',
} as const

export type MissionTypeType = (typeof MissionType)[keyof typeof MissionType]

// Mission type labels (French)
export const MissionTypeLabel: Record<MissionTypeType, string> = {
  [MissionType.PUBLICITE]: 'Publicité',
  [MissionType.FILM]: 'Film',
  [MissionType.COURT_METRAGE]: 'Court-métrage',
  [MissionType.CLIP_MUSICAL]: 'Clip musical',
  [MissionType.AUTRE]: 'Autre',
}

// Mission gender enum
export const MissionGender = {
  HOMME: 'homme',
  FEMME: 'femme',
  TOUS: 'tous',
} as const

export type MissionGenderType = (typeof MissionGender)[keyof typeof MissionGender]

// Mission gender labels (French)
export const MissionGenderLabel: Record<MissionGenderType, string> = {
  [MissionGender.HOMME]: 'Homme',
  [MissionGender.FEMME]: 'Femme',
  [MissionGender.TOUS]: 'Homme et Femme',
}

// Producer data embedded in Mission response
export interface MissionProducer {
  id: number
  type: 'agency' | 'particulier'
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

// Mission data from API
export interface Mission {
  id: number
  titre: string
  description: string
  date_tournage: string
  profil_recherche: string
  budget: number
  date_limite_candidature: string
  nombre_faces_voulu: number
  type_mission: MissionTypeType
  type_mission_label: string
  genre_voulu: MissionGenderType
  genre_voulu_label: string
  lieu: string
  duree: string
  status: MissionStatusType
  status_label: string
  is_accepting_candidatures: boolean
  candidatures_count?: number
  producer?: MissionProducer
  created_at: string
  updated_at: string
}

// Data for creating a new mission
export interface CreateMissionData {
  titre: string
  description: string
  date_tournage: string
  profil_recherche: string
  budget: number
  date_limite_candidature: string
  nombre_faces_voulu?: number
  type_mission: MissionTypeType
  genre_voulu: MissionGenderType
  lieu: string
  duree: string
}

// Candidature data (minimal version for mission detail response)
export interface MissionCandidature {
  id: number
  mission_id: number
  face_id: number
  status: string
  status_label: string
  message_motivation: string | null
  created_at: string
  updated_at: string
}

// Mission API response
export interface MissionResponse {
  data: Mission
  candidature?: MissionCandidature | null
  message?: string
}

// Mission create result
export interface MissionCreateResult {
  success: boolean
  data?: Mission
  errors?: Record<string, string[]>
  message?: string
}

// Data for updating a mission (same as create)
export type UpdateMissionData = CreateMissionData

// Mission update result
export interface MissionUpdateResult {
  success: boolean
  data?: Mission
  errors?: Record<string, string[]>
  message?: string
}

// Mission list API response
export interface MissionsListResponse {
  data: Mission[]
  message?: string
}

// Mission filters for Face browse
export interface MissionFilters {
  lieu?: string
  budget_min?: number
  budget_max?: number
  date_tournage?: string // Y-m-d format
  type_mission?: MissionTypeType
}

// Paginated missions response (for Face browse)
export interface PaginatedMissionsResponse {
  data: Mission[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  message?: string
}

// Mission type option for select inputs
export interface MissionTypeOption {
  value: MissionTypeType
  label: string
}

// Mission gender option for select inputs
export interface MissionGenderOption {
  value: MissionGenderType
  label: string
}

// Helper to get mission type options
export function getMissionTypeOptions(): MissionTypeOption[] {
  return Object.entries(MissionTypeLabel).map(([value, label]) => ({
    value: value as MissionTypeType,
    label,
  }))
}

// Helper to get mission gender options
export function getMissionGenderOptions(): MissionGenderOption[] {
  return Object.entries(MissionGenderLabel).map(([value, label]) => ({
    value: value as MissionGenderType,
    label,
  }))
}
