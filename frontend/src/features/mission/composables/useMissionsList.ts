import { ref } from 'vue'
import { missionApi } from '../services/missionApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import type { Mission, MissionListParams } from '../types'

/**
 * Composable for the producer's missions list.
 *
 * Server-driven: sort, status filter and pagination are applied by the API
 * (`GET /producer/missions?page=&per_page=&sort=&direction=&status=`); this
 * composable only holds the current page and its pagination meta.
 */
export function useMissionsList() {
  const missions = ref<Mission[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const message = ref<string | null>(null)
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const hasLoaded = ref(false)

  let lastParams: MissionListParams | null = null
  let requestId = 0
  let inFlight: { key: string; promise: Promise<void> } | null = null

  /**
   * Fetch one page. Identical concurrent calls (e.g. keep-alive reactivation +
   * URL watcher firing together) share a single request; a stale response never
   * overwrites a newer one.
   */
  function fetchMissions(params: MissionListParams, options: { force?: boolean } = {}): Promise<void> {
    const key = JSON.stringify(params)
    if (!options.force && inFlight && inFlight.key === key) return inFlight.promise

    lastParams = params
    const id = ++requestId
    isLoading.value = true
    error.value = null

    const promise = (async () => {
      try {
        const response = await missionApi.getMissionsPage(params)
        if (id !== requestId) return
        missions.value = response.data
        message.value = response.message ?? null
        currentPage.value = response.meta.current_page
        lastPage.value = response.meta.last_page
        total.value = response.meta.total
        hasLoaded.value = true
      } catch (err: unknown) {
        if (id !== requestId) return
        error.value = getApiErrorMessage(err)
        missions.value = []
      } finally {
        if (id === requestId) {
          isLoading.value = false
          inFlight = null
        }
      }
    })()

    inFlight = { key, promise }
    return promise
  }

  /**
   * Re-run the last request (after a mutation). Always a NEW request: a mutation must
   * never be answered by a response that was already in flight before it.
   */
  async function refreshMissions(): Promise<void> {
    if (lastParams) await fetchMissions(lastParams, { force: true })
  }

  /**
   * Remove a mission from the local list by ID
   * Used for optimistic updates after successful deletion
   */
  function removeMissionFromList(missionId: string): void {
    missions.value = missions.value.filter((m) => m.id !== missionId)
  }

  function reset(): void {
    missions.value = []
    isLoading.value = false
    error.value = null
    message.value = null
    currentPage.value = 1
    lastPage.value = 1
    total.value = 0
    hasLoaded.value = false
    lastParams = null
    inFlight = null
    requestId++
  }

  return {
    // State
    missions,
    isLoading,
    error,
    message,
    currentPage,
    lastPage,
    total,
    hasLoaded,

    // Actions
    fetchMissions,
    refreshMissions,
    removeMissionFromList,
    reset,
  }
}
