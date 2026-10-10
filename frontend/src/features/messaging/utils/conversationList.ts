import type { ConversationListItem, ConversationUpdatedBroadcast } from '../types'

/**
 * Applique un `.conversation.updated` à la liste (aperçu, heure, non-lus, ordre).
 *
 * L'ordre du serveur est « dernier message décroissant » : la conversation mise à jour
 * remonte en tête. `is_mine` est calculé ici à partir de l'expéditeur.
 *
 * @returns false si la conversation n'est pas dans la liste chargée (=> recharger la liste)
 */
export function applyConversationUpdate(
  conversations: ConversationListItem[],
  update: ConversationUpdatedBroadcast,
  currentUserId: number | null,
  unreadOverride?: number,
): boolean {
  const index = conversations.findIndex((conversation) => conversation.id === update.conversation_id)
  if (index === -1) return false

  const item = conversations[index]!
  item.latest_message = {
    content: update.latest_message.content,
    sender_name: update.latest_message.sender_name,
    is_mine: currentUserId !== null && update.latest_message.sender_id === currentUserId,
    created_at: update.latest_message.created_at,
  }
  item.unread_count = unreadOverride ?? update.unread_count
  item.updated_at = update.updated_at

  if (index > 0) {
    conversations.splice(index, 1)
    conversations.unshift(item)
  }
  return true
}
