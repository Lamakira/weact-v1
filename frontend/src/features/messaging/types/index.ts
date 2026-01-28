/**
 * Messaging feature types
 */

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
  id: number
  name: string
  photo_url: string | null
  type: 'face' | 'producer'
}

// Conversation data from API
export interface Conversation {
  id: number
  candidature_id: number
  mission_title: string
  other_participant: OtherParticipant
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
  id: number
  candidature_id: number
  mission_title: string
  other_participant: OtherParticipant
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
