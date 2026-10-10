import type {
  Conversation,
  ConversationContext,
  ConversationContextSummary,
  ConversationListItem,
  Message,
} from '../types'

export function makeMessage(overrides: Partial<Message> = {}): Message {
  return {
    id: 1,
    content: 'Bonjour',
    sender_id: 2,
    sender_type: 'App\\Models\\User',
    sender_name: 'Afiavi Hounkpatin',
    is_own_message: false,
    read_at: null,
    created_at: '2030-10-09T10:00:00+00:00',
    ...overrides,
  }
}

export function makeSummary(
  overrides: Partial<ConversationContextSummary> = {},
): ConversationContextSummary {
  return {
    type: 'mission',
    type_label: 'Mission',
    title: 'Lookbook Wax',
    candidature_status: 'confirmed',
    step: { key: 'paid', label: 'Payé' },
    date_tournage: '2030-10-14',
    ...overrides,
  }
}

export function makeContext(
  role: 'face' | 'producer',
  overrides: Partial<ConversationContext> = {},
): ConversationContext {
  return {
    ...makeSummary(),
    candidature_status_label: 'Confirmée',
    closed: false,
    steps: [
      { key: 'requested', label: 'Demandé', state: 'done' },
      { key: 'accepted', label: 'Accepté', state: 'done' },
      { key: 'paid', label: 'Payé', state: 'current' },
      { key: 'done', label: 'Réalisé', state: 'todo' },
    ],
    lieu: 'Cotonou',
    mission_id: 'mission-uuid',
    candidature_id: 'cand-uuid',
    amounts:
      role === 'face'
        ? { face_receives: 85000, product_value: null }
        : { cachet: 100000, service_fee: 10000, total: 110000, product_value: null },
    ...overrides,
  }
}

export function makeListItem(overrides: Partial<ConversationListItem> = {}): ConversationListItem {
  return {
    id: 'conv-1',
    candidature_id: 'cand-uuid',
    mission_title: 'Lookbook Wax',
    other_participant: {
      id: 'p-1',
      name: 'Afiavi Hounkpatin',
      photo_url: null,
      profile_photo_thumbnail_url: null,
      type: 'face',
    },
    context: makeSummary(),
    latest_message: {
      content: 'Je serai là à 8 h 30',
      sender_name: 'Afiavi Hounkpatin',
      is_mine: false,
      created_at: '2030-10-09T10:42:00+00:00',
    },
    unread_count: 0,
    updated_at: '2030-10-09T10:42:00+00:00',
    ...overrides,
  }
}

export function makeConversation(
  role: 'face' | 'producer',
  overrides: Partial<Conversation> = {},
): Conversation {
  return {
    id: 'conv-1',
    candidature_id: 'cand-uuid',
    mission_title: 'Lookbook Wax',
    other_participant: makeListItem().other_participant,
    context: makeContext(role),
    messages: [makeMessage()],
    unread_count: 0,
    ...overrides,
  }
}
