/**
 * Mission feature types
 */

import type { Deliverable, ProductPhoto, Shipment } from '@/components/ugc'

// Mission status enum
export const MissionStatus = {
  DRAFT: 'draft',
  PUBLISHED: 'published',
  PENDING_PAYMENT: 'pending_payment',
  CLOSED: 'closed',
  PENDING_ATTENDANCE_VALIDATION: 'pending_attendance_validation',
  COMPLETED: 'completed',
} as const

export type MissionStatusType = (typeof MissionStatus)[keyof typeof MissionStatus]

// Mission status labels (French)
export const MissionStatusLabel: Record<MissionStatusType, string> = {
  [MissionStatus.DRAFT]: 'Brouillon',
  [MissionStatus.PUBLISHED]: 'Publiée',
  [MissionStatus.PENDING_PAYMENT]: 'En attente de paiement',
  [MissionStatus.CLOSED]: 'Clôturée',
  [MissionStatus.PENDING_ATTENDANCE_VALIDATION]: 'En attente de validation des présences',
  [MissionStatus.COMPLETED]: 'Terminée',
}

// Mission type enum
export const MissionType = {
  PUBLICITE: 'publicite',
  FILM: 'film',
  COURT_METRAGE: 'court_metrage',
  CLIP_MUSICAL: 'clip_musical',
  SHOOTING_PHOTO: 'shooting_photo',
  AUTRE: 'autre',
} as const

export type MissionTypeType = (typeof MissionType)[keyof typeof MissionType]

// Mission type labels (French)
export const MissionTypeLabel: Record<MissionTypeType, string> = {
  [MissionType.PUBLICITE]: 'Publicité',
  [MissionType.FILM]: 'Film',
  [MissionType.COURT_METRAGE]: 'Court-métrage',
  [MissionType.CLIP_MUSICAL]: 'Clip musical',
  [MissionType.SHOOTING_PHOTO]: 'Shooting photo',
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
const MissionGenderLabel: Record<MissionGenderType, string> = {
  [MissionGender.HOMME]: 'Homme',
  [MissionGender.FEMME]: 'Femme',
  [MissionGender.TOUS]: 'Homme et Femme',
}

// Producer data embedded in Mission response
export interface MissionProducer {
  id: string
  slug: string | null
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
  average_rating: number | null
  ratings_count: number
  created_at: string
  updated_at: string
}

// Mission data from API
export interface Mission {
  id: string
  titre: string
  description: string
  date_tournage: string
  profil_recherche: string
  budget: number
  date_limite_candidature: string
  nombre_faces_voulu: number
  type_mission: MissionTypeType
  type_mission_label: string
  type_mission_autre: string | null
  // UGC fields (null pour les missions non-UGC) — mirrors MissionResource (story 1.3)
  type_compensation: string | null
  type_compensation_label: string | null
  nom_produit: string | null
  valeur_produit: number | null
  nombre_videos: number | null
  montant_remuneration: number | null
  commission_ugc: number | null
  commission_paid_at: string | null
  genre_voulu: MissionGenderType
  genre_voulu_label: string
  lieu: string
  duree: string
  status: MissionStatusType
  status_label: string
  is_accepting_candidatures: boolean
  has_paid_payment: boolean
  candidatures_count?: number
  // Photos produit UGC (spec photos produit, `whenLoaded`) — clé OMISE sur les
  // listes, chargée sur les détails. URLs publiques directes (disque public).
  product_photos?: ProductPhoto[]
  producer?: MissionProducer
  created_at: string
  updated_at: string
}

// Data for creating a new mission
export interface CreateMissionData {
  titre: string
  description: string
  date_tournage?: string // optionnel : champ de tournage masqué pour l'UGC (D-8.1.d) ; requis pour les types standard
  profil_recherche: string
  budget?: number // optionnel : dérivé serveur pour l'UGC (D-1.4.d) ; requis pour les types standard
  date_limite_candidature: string
  nombre_faces_voulu?: number
  type_mission: MissionTypeType | 'ugc' // 'ugc' n'est PAS ajouté à l'enum/labels partagés (D-1.4.b)
  type_mission_autre?: string
  genre_voulu: MissionGenderType
  lieu?: string // optionnel : champ de tournage masqué pour l'UGC (D-8.1.d) ; requis pour les types standard
  duree?: string // optionnel : champ de tournage masqué pour l'UGC (D-8.1.d) ; requis pour les types standard
  // UGC dotation (envoyés uniquement quand type_mission === 'ugc')
  type_compensation?: 'product' | 'hybrid'
  nom_produit?: string
  valeur_produit?: number
  nombre_videos?: number
  montant_remuneration?: number
  // Photos produit (0-2, facultatives, création seulement) — leur présence
  // bascule l'envoi en FormData
  product_photos?: File[]
}

// Candidature data (minimal version for mission detail response)
export interface MissionCandidature {
  id: string
  mission_id: string
  face_id: string
  status: string
  status_label: string
  message_motivation: string | null
  created_at: string
  updated_at: string
  /** Expédition UGC (whenLoaded — clé OMISE hors deal UGC expédié, 3.3 AC8). */
  shipment?: Shipment
  /** Livrables vidéo UGC (whenLoaded, 4.6) — review_note du bandeau de refus + start chrono Avis (D-4.6.b). */
  deliverables?: Deliverable[]
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
  errorCode?: string | null
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

// ─── Découverte UGC (story 2.2) ───────────────────────────────────────────
// Miroir strict de UgcMissionTeaserResource (backend 2.1, D-2.1.c — 10 clés).
export interface UgcMissionTeaser {
  id: string
  titre: string
  type_compensation: 'product' | 'hybrid' | null
  type_compensation_label: string | null
  nom_produit: string | null
  valeur_produit: number | null
  nombre_videos: number | null
  lieu: string
  date_limite_candidature: string | null
  created_at: string | null
}

export interface UgcPaywallMeta {
  code: string
  message: string
  pricing_url: string
}

// Un item de découverte : MissionResource complet (Face éligible) ou teaser (free).
export type UgcDiscoveryItem = Mission | UgcMissionTeaser

export interface PaginatedUgcMissionsResponse {
  data: UgcDiscoveryItem[]
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
    can_access_ugc: boolean
    paywall?: UgcPaywallMeta
  }
}

// Mission type option for select inputs
interface MissionTypeOption {
  value: MissionTypeType
  label: string
}

// Mission gender option for select inputs
interface MissionGenderOption {
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

/**
 * Discriminant UGC côté Face (D-2.3.b) : `type_compensation` est requis à la
 * création d'une mission UGC et structurellement null sur les standard.
 * Ne JAMAIS discriminer par `type_mission === 'ugc'` (hors MissionTypeType,
 * D-1.4.b) ni par `commission_ugc` (masqué pour les requesters Face, 2.1).
 */
export function isUgcMission(mission: Mission): boolean {
  return mission.type_compensation != null
}
