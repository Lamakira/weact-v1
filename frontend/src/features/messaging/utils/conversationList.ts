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

  // Événements dans le désordre (plusieurs workers) : on ignore une mise à jour plus
  // ancienne que le dernier message déjà affiché (la conversation reste « connue »).
  const shownAt = item.latest_message ? Date.parse(item.latest_message.created_at) : Number.NaN
  const incomingAt = Date.parse(update.latest_message.created_at)
  if (!Number.isNaN(shownAt) && !Number.isNaN(incomingAt) && incomingAt < shownAt) return true

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

/** Aperçu de la liste : même troncature que le serveur (50 caractères + « ... »). */
export function previewContent(content: string): string {
  return content.length > 50 ? `${content.slice(0, 50)}...` : content
}
