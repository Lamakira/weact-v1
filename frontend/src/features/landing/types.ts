export interface LandingMissionProducer {
  id: string
  display_name: string
  profile_photo_thumbnail_url: string | null
  average_rating: number | null
  ratings_count: number
}

export interface LandingMission {
  id: string
  slug: string
  titre: string
  description: string
  date_tournage: string | null
  profil_recherche: string | null
  budget: number
  date_limite_candidature: string | null
  nombre_faces_voulu: number
  type_mission: string
  type_mission_label: string
  genre_voulu: string
  genre_voulu_label: string
  lieu: string
  duree: string | null
  status: string
  status_label: string
  created_at: string
  producer: LandingMissionProducer | null
}

export interface LandingFace {
  id: string
  username: string
  prenom: string
  nom: string
  ville: string | null
  categories: Array<{ value: string; label: string }>
  is_available: boolean
  profile_photo_url: string | null
  profile_photo_thumbnail_url: string | null
  average_rating: number | null
}

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface MissionsApiResponse {
  data: LandingMission[]
  meta: PaginationMeta
  message: string
}

export interface FacesApiResponse {
  data: LandingFace[]
  meta: PaginationMeta
  message: string
}
