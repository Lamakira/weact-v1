/**
 * Messaging feature types
 */
import type { CandidatureStatusType } from '@/features/candidature/types'

// Message data from API
export interface Message {
  id: number
  content: string
  sender_id: number
  sender_type: string
  sender_name: string
  is_own_message: boolean
  read_at: string | null
  created_at: string
}

// Other participant in conversation (Face or Producer)
export interface OtherParticipant {
  id: string
  name: string
  photo_url: string | null
  // 150px avatar variant — server falls back to the original while the
  // variant job is pending, so it is only null when photo_url is null too
  profile_photo_thumbnail_url: string | null
  type: 'face' | 'producer'
}

// Étapes Demandé -> Accepté -> Payé -> Réalisé (dérivées du statut de candidature côté serveur)
export type ContextStepKey = 'requested' | 'accepted' | 'paid' | 'done'

export interface ContextStep {
  key: ContextStepKey
  label: string
  state: 'done' | 'current' | 'todo'
}

// Version compacte du contexte (liste des conversations)
export interface ConversationContextSummary {
  type: 'mission' | 'ugc'
  type_label: string
  title: string
  candidature_status: CandidatureStatusType
  // null quand la candidature est close (refusée / annulée)
  step: { key: ContextStepKey; label: string } | null
  date_tournage: string | null
}

// Montants visibles par la Face : ce qu'elle reçoit (net), jamais les frais Producteur
export interface FaceContextAmounts {
  face_receives: number | null
  product_value: number | null
}

// Montants visibles par le Producteur : cachet + frais de service + total
export interface ProducerContextAmounts {
  cachet: number | null
  service_fee: number | null
  total: number | null
  product_value: number | null
}

// Contexte complet (détail de conversation)
export interface ConversationContext extends ConversationContextSummary {
  candidature_status_label: string
  closed: boolean
  steps: ContextStep[]
  lieu: string | null
  mission_id: string
  candidature_id: string
  amounts: FaceContextAmounts | ProducerContextAmounts
}

// Conversation data from API
export interface Conversation {
  id: string
  candidature_id: string
  mission_title: string
  other_participant: OtherParticipant
  context: ConversationContext | null
  messages: Message[]
  unread_count: number
}

// API response for single conversation
export interface ConversationResponse {
  data: Conversation
}

// API response for single message
export interface MessageResponse {
  data: Message
  message?: string
}

// Data for sending a message
export interface SendMessageData {
  content: string
}

// API error response structure
export interface ApiErrorResponse {
  error?: {
    code: string
    message: string
  }
  errors?: Record<string, string[]>
  message?: string
}

// Latest message preview in conversation list
export interface LatestMessagePreview {
  content: string
  sender_name: string
  is_mine: boolean
  created_at: string
}

// Conversation list item (lightweight, for list view)
export interface ConversationListItem {
  id: string
  candidature_id: string
  mission_title: string
  other_participant: OtherParticipant
  context: ConversationContextSummary | null
  latest_message: LatestMessagePreview | null
  unread_count: number
  updated_at: string
}

// Pagination metadata
export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

// API response for conversations list
export interface ConversationsListResponse {
  data: ConversationListItem[]
  meta: PaginationMeta
}
