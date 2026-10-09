import { ref } from 'vue'
import { producerApi } from '@/features/producer/services/producerApi'
import { messagingApi } from '@/features/messaging/services/messagingApi'
import { useUgcValidationCountStore } from '@/stores/ugcValidationCount'
import { dashboardApi } from '../services/dashboardApi'
import type { DeliverableReviewItem } from '@/components/ugc/ugc'
import type { ConversationListItem } from '@/features/messaging/types'
import type { ProducerActiveMission } from '../types'

export const TO_VALIDATE_LIMIT = 5
export const UNREAD_MESSAGES_LIMIT = 3
export const ACTIVE_MISSIONS_LIMIT = 5

/**
 * Data of the three work modules of the Producer dashboard (to validate, unread
 * messages, active missions). Each module loads, fails and retries on its own so
 * one failing endpoint never blanks the page.
 *
 * Sources: the existing validation inbox (/producer/deliverables, oldest first),
 * the existing conversations list (first page, filtered on unread_count > 0) and
 * /producer/dashboard/active-missions.
 */
export function useProducerDashboardModules() {
  const ugcValidationCountStore = useUgcValidationCountStore()

  const toValidate = ref<DeliverableReviewItem[]>([])
  const toValidateTotal = ref(0)
  const toValidateLoading = ref(false)
  const toValidateError = ref<string | null>(null)

  const unreadConversations = ref<ConversationListItem[]>([])
  const unreadTotal = ref(0)
  const unreadLoading = ref(false)
  const unreadError = ref<string | null>(null)

  const activeMissions = ref<ProducerActiveMission[]>([])
  const activeMissionsTotal = ref(0)
  const activeMissionsLoading = ref(false)
  const activeMissionsError = ref<string | null>(null)

  async function fetchToValidate(): Promise<void> {
    toValidateLoading.value = true
    toValidateError.value = null
    try {
      const response = await producerApi.listDeliverablesToReview()
      toValidate.value = response.data.slice(0, TO_VALIDATE_LIMIT)
      toValidateTotal.value = response.data.length
      // Same source as the sidebar badge: keep it in sync without another call
      ugcValidationCountStore.setCount(response.data.length)
    } catch (error) {
      console.error('[ProducerDashboard] Failed to load deliverables to validate:', error)
      toValidateError.value = 'Impossible de charger les livrables.'
    } finally {
      toValidateLoading.value = false
    }
  }

  async function fetchUnreadMessages(): Promise<void> {
    unreadLoading.value = true
    unreadError.value = null
    try {
      const response = await messagingApi.getProducerConversations(1)
      const unread = response.data.filter((conversation) => conversation.unread_count > 0)
      unreadConversations.value = unread.slice(0, UNREAD_MESSAGES_LIMIT)
      unreadTotal.value = unread.length
    } catch (error) {
      console.error('[ProducerDashboard] Failed to load unread conversations:', error)
      unreadError.value = 'Impossible de charger les messages.'
    } finally {
      unreadLoading.value = false
    }
  }

  async function fetchActiveMissions(): Promise<void> {
    activeMissionsLoading.value = true
    activeMissionsError.value = null
    try {
      const response = await dashboardApi.getProducerActiveMissions(ACTIVE_MISSIONS_LIMIT)
      activeMissions.value = response.data
      activeMissionsTotal.value = response.meta.total
    } catch (error) {
      console.error('[ProducerDashboard] Failed to load active missions:', error)
      activeMissionsError.value = 'Impossible de charger les missions.'
    } finally {
      activeMissionsLoading.value = false
    }
  }

  async function fetchAll(): Promise<void> {
    await Promise.all([fetchToValidate(), fetchUnreadMessages(), fetchActiveMissions()])
  }

  return {
    toValidate,
    toValidateTotal,
    toValidateLoading,
    toValidateError,
    unreadConversations,
    unreadTotal,
    unreadLoading,
    unreadError,
    activeMissions,
    activeMissionsTotal,
    activeMissionsLoading,
    activeMissionsError,
    fetchToValidate,
    fetchUnreadMessages,
    fetchActiveMissions,
    fetchAll,
  }
}
