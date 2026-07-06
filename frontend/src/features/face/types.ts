/**
 * Face profile feature types
 */

// Face profile data from API
export interface FaceProfile {
  id: string
  nom: string
  prenom: string
  username: string
  profile_photo_url: string | null
  thumbnail_url: string | null
  // 400px card variant — server falls back grid → medium → original
  profile_photo_grid_url: string | null
  average_rating: number | null
  ratings_count: number
  tarif_horaire: number | null
  tarif_journalier: number | null
}

// Face profile API response
export interface FaceProfileResponse {
  data: FaceProfile
  message?: string
}

// Profile photo upload result
export interface ProfilePhotoResult {
  success: boolean
  data?: FaceProfile
  errors?: Record<string, string[]>
  message?: string
}

// Album photo from API
export interface FacePhoto {
  id: string
  photo_url: string
  // Server-side fallback chains (legacy rows / variant job still pending):
  // grid_url → medium → original, large_url → medium → original
  grid_url: string | null
  large_url: string | null
  thumbnail_url: string
  position: number
}

// Album photos API response
export interface FacePhotosResponse {
  data: FacePhoto[]
  message?: string
}

// Single album photo API response
export interface FacePhotoResponse {
  data: FacePhoto
  message?: string
}

// Album photo operation result
export interface AlbumPhotoResult {
  success: boolean
  data?: FacePhoto
  errors?: Record<string, string[]>
  message?: string
}

// Presentation video info from API
export interface PresentationVideoInfo {
  presentation_video_url: string | null
  presentation_video_thumbnail_url: string | null
}

// Presentation video API response
export interface PresentationVideoResponse {
  data: PresentationVideoInfo
  message?: string
}

// Presentation video operation result
export interface PresentationVideoResult {
  success: boolean
  data?: PresentationVideoInfo
  errors?: Record<string, string[]>
  message?: string
}

// Video upload progress
export interface VideoUploadProgress {
  loaded: number
  total: number
  percentage: number
}

// Face videos (FP-2.2.1 typed face_videos API)

export type FaceVideoType = 'acting' | 'ugc'

export interface FaceVideo {
  id: string // uuid
  type: FaceVideoType
  video_url: string | null
  thumbnail_url: string | null
  position: number
}

export interface FaceVideosListResponse {
  data: FaceVideo[]
}

export interface FaceVideoUploadResponse {
  data: FaceVideo
  message?: string
}

export interface FaceVideoDeleteResponse {
  message: string
}

export interface FaceVideoUploadResult {
  success: boolean
  data?: FaceVideo
  errors?: Record<string, string[]>
  message?: string
}

export interface FaceVideoDeleteResult {
  success: boolean
  errors?: Record<string, string[]>
  message?: string
}

// Bio and location info from API
export interface BioLocationInfo {
  bio: string | null
  ville: string | null
  pays: string | null
  formatted_location: string | null
}

// Bio and location API response
export interface BioLocationResponse {
  data: BioLocationInfo
  message?: string
}

// Bio and location operation result
export interface BioLocationResult {
  success: boolean
  data?: BioLocationInfo
  errors?: Record<string, string[]>
  message?: string
}

// Physical characteristics info from API
export interface PhysicalCharacteristicsInfo {
  taille: number | null
  poids: number | null
}

// Physical characteristics API response
export interface PhysicalCharacteristicsResponse {
  data: PhysicalCharacteristicsInfo
  message?: string
}

// Physical characteristics operation result
export interface PhysicalCharacteristicsResult {
  success: boolean
  data?: PhysicalCharacteristicsInfo
  errors?: Record<string, string[]>
  message?: string
}

// Category enum values
export type FaceCategory = 'acteur' | 'influenceur' | 'createur' | 'mannequin' | 'figurant' | 'modele_photo' | 'egerie'

// Niche enum values
export type FaceNiche = 'beaute' | 'nourriture' | 'decouverte' | 'mode'

// Value+label pair for categories and niches
export interface ValueLabel {
  value: string
  label: string
}

// Category and niche info from API (multi-select)
export interface CategoryNicheInfo {
  categories: ValueLabel[]
  niches: ValueLabel[]
}

// Category and niche API response
export interface CategoryNicheResponse {
  data: CategoryNicheInfo
  message?: string
}

// Category and niche operation result
export interface CategoryNicheResult {
  success: boolean
  data?: CategoryNicheInfo
  errors?: Record<string, string[]>
  message?: string
}

// Category option for dropdown
export interface CategoryOption {
  value: FaceCategory
  label: string
}

// Niche option for dropdown
export interface NicheOption {
  value: FaceNiche
  label: string
}

// Experience from API
export interface Experience {
  id: string
  titre: string
  description: string | null
  date_debut: string // ISO date string (YYYY-MM-DD)
  date_fin: string | null // ISO date string or null for ongoing
  is_ongoing: boolean
  formatted_period: string // e.g., "15/01/2023 - 20/03/2024" or "15/01/2023 - Présent"
  created_at: string
  updated_at: string
}

// Experience form data for create/update
export interface ExperienceFormData {
  titre: string
  description?: string | null
  date_debut: string // YYYY-MM-DD format
  date_fin?: string | null // YYYY-MM-DD format or null for ongoing
}

// Single experience API response
export interface ExperienceResponse {
  data: Experience
  message?: string
}

// Experiences list API response
export interface ExperiencesListResponse {
  data: Experience[]
  message?: string
}

// Experience operation result
export interface ExperienceResult {
  success: boolean
  data?: Experience
  errors?: Record<string, string[]>
  message?: string
}

// Tarifs info from API
export interface TarifsInfo {
  tarif_horaire: number | null
  tarif_journalier: number | null
  formatted_tarif_horaire: string | null
  formatted_tarif_journalier: string | null
}

// Tarifs API response
export interface TarifsResponse {
  data: TarifsInfo
  message?: string
}

// Tarifs form data for update
export interface TarifsFormData {
  tarif_horaire: number | null
  tarif_journalier: number | null
}

// Tarifs operation result
export interface TarifsResult {
  success: boolean
  data?: TarifsInfo
  errors?: Record<string, string[]>
  message?: string
}

// Availability badge color type
export type AvailabilityBadgeColor = 'green' | 'grey'

// Availability info from API
export interface AvailabilityInfo {
  is_available: boolean
  availability_badge: string
  availability_badge_color: AvailabilityBadgeColor
}

// Availability API response
export interface AvailabilityResponse {
  data: AvailabilityInfo
  message?: string
}

// Availability form data for update
export interface AvailabilityFormData {
  is_available: boolean
}

// Availability operation result
export interface AvailabilityResult {
  success: boolean
  data?: AvailabilityInfo
  errors?: Record<string, string[]>
  message?: string
}

// Profile completion missing item
export interface ProfileCompletionMissingItem {
  key: string
  label: string
}

// Profile completion info from API
export interface ProfileCompletionInfo {
  profile_completion_percentage: number
  profile_completion_missing: ProfileCompletionMissingItem[]
  profile_completion_is_complete: boolean
}

// Profile completion API response
export interface ProfileCompletionResponse {
  data: ProfileCompletionInfo
  message?: string
}

// Langues info from API
export interface LanguesInfo {
  langues: string[] | null
}

// Langues API response
export interface LanguesResponse {
  data: LanguesInfo
  message?: string
}

// Langues operation result
export interface LanguesResult {
  success: boolean
  data?: LanguesInfo
  errors?: Record<string, string[]>
  message?: string
}

// Basic info (nom, prenom, username) from API
export interface BasicInfo {
  nom: string
  prenom: string
  username: string
}

// Basic info API response
export interface BasicInfoResponse {
  data: BasicInfo
  message?: string
}

// Basic info form data for update
export interface BasicInfoFormData {
  nom?: string
  prenom?: string
  username?: string
}

// Basic info operation result
export interface BasicInfoResult {
  success: boolean
  data?: BasicInfo
  errors?: Record<string, string[]>
  message?: string
}

// Personal info (sexe, date_naissance, nationalite, pays) from API
export interface PersonalInfoInfo {
  sexe: string | null
  date_naissance: string | null
  nationalite: string | null
  pays: string | null
  show_age: boolean
  whatsapp_number: string | null
}

// Personal info API response
export interface PersonalInfoResponse {
  data: PersonalInfoInfo
  message?: string
}

// Personal info form data for update
export interface PersonalInfoFormData {
  sexe?: string | null
  date_naissance?: string | null
  nationalite?: string | null
  pays?: string | null
  show_age?: boolean
  whatsapp_number?: string | null
}

// Personal info operation result
export interface PersonalInfoResult {
  success: boolean
  data?: PersonalInfoInfo
  errors?: Record<string, string[]>
  message?: string
}

// Subscription — FP-2.7 tier-aware contract (GET /api/v1/face/subscription-status, FP-2.3)
export type FaceSubscriptionTier = 'free' | 'starter' | 'pro' | 'elite'
export type FaceSubscriptionPlan = 'starter' | 'pro' | 'elite' // purchasable tiers only
export type SubscriptionStatusValue =
  | 'free'
  | 'pending_payment'
  | 'active'
  | 'expired'
  | 'cancelled'
  | 'failed'

export interface TierCapabilities {
  max_album_photos: number
  max_presentation_videos: number
  max_acting_videos: number
  max_ugc_videos: number
  ugc_access: boolean
  commission_rate: number
  sort_priority: number
  has_elite_badge: boolean
}

export interface SubscriptionCurrent {
  tier: FaceSubscriptionTier
  plan: FaceSubscriptionPlan | null
  status: SubscriptionStatusValue
  starts_at: string | null
  expires_at: string | null
  cancelled_at: string | null
  capabilities: TierCapabilities
}

export interface SubscriptionOffer {
  tier: FaceSubscriptionTier
  price: number
  currency: string
  capabilities: TierCapabilities
}

export interface SubscriptionCta {
  upgrade_available: boolean
  downgrade_available: boolean
  renew_available: boolean
}

export interface SubscriptionStatusData {
  current: SubscriptionCurrent
  offers: SubscriptionOffer[]
  cta: SubscriptionCta
}

export interface SubscriptionStatusResponse {
  data: SubscriptionStatusData
}

// Payment initiation (FP-2.5) — POST /api/v1/face/subscription/initiate-payment
export interface SubscriptionInitiatePaymentResponse {
  data: {
    subscription_id: string
    status: SubscriptionStatusValue
    plan: FaceSubscriptionPlan
    checkout_url: string
    amount: number
    currency: string
    forfeited_days: number
  }
  message?: string
}

// Payment verification (FP-2.5) — POST /api/v1/face/subscription/verify-payment
export interface SubscriptionVerifyPaymentResponse {
  data: {
    subscription_id: string | null
    status: SubscriptionStatusValue
  }
}

// Payment cancellation (FP-2.8.1 backend, FP-2.15.1 frontend) — POST /api/v1/face/subscription/cancel-pending
export interface SubscriptionCancelPendingResponse {
  data: {
    subscription_id: string
    status: SubscriptionStatusValue
  }
  message?: string
}

// Payment resume (FP-2.15.2) — POST /api/v1/face/subscription/resume-payment
export interface SubscriptionResumePaymentResponse {
  data: {
    subscription_id: string
    status: SubscriptionStatusValue
    checkout_url: string | null
    amount: number | null
    currency: string | null
  }
  message?: string
}

// UI-only ephemeral state for the payment flow
export type SubscriptionPaymentState = 'idle' | 'waiting' | 'confirmed' | 'failed'
