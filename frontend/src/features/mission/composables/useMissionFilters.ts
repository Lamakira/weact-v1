import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { MissionFilters, MissionTypeType } from '../types'
import { MissionType } from '../types'

/**
 * Composable for managing mission filter state with URL synchronization
 */
export function useMissionFilters() {
  const route = useRoute()
  const router = useRouter()

  // Filter state
  const lieu = ref<string>('')
  const budgetMin = ref<number | undefined>(undefined)
  const budgetMax = ref<number | undefined>(undefined)
  const dateTournage = ref<string>('')
  const typeMission = ref<MissionTypeType | ''>('')

  // Guard flag to prevent re-initialization loops
  let isSyncingToUrl = false

  // Computed filters object for API calls
  const filters = computed<MissionFilters>(() => {
    const result: MissionFilters = {}
    if (lieu.value) result.lieu = lieu.value
    if (budgetMin.value !== undefined) result.budget_min = budgetMin.value
    if (budgetMax.value !== undefined) result.budget_max = budgetMax.value
    if (dateTournage.value) result.date_tournage = dateTournage.value
    if (typeMission.value) result.type_mission = typeMission.value as MissionTypeType
    return result
  })

  // Count of active filters
  const activeFilterCount = computed(() => {
    let count = 0
    if (lieu.value) count++
    if (budgetMin.value !== undefined) count++
    if (budgetMax.value !== undefined) count++
    if (dateTournage.value) count++
    if (typeMission.value) count++
    return count
  })

  // Check if any filters are active
  const hasActiveFilters = computed(() => activeFilterCount.value > 0)

  /**
   * Initialize filters from URL query params
   */
  function initFromUrl(): void {
    const query = route.query

    if (typeof query.lieu === 'string') {
      lieu.value = query.lieu
    }
    if (typeof query.budget_min === 'string') {
      const parsed = parseInt(query.budget_min, 10)
      if (!isNaN(parsed)) budgetMin.value = parsed
    }
    if (typeof query.budget_max === 'string') {
      const parsed = parseInt(query.budget_max, 10)
      if (!isNaN(parsed)) budgetMax.value = parsed
    }
    if (typeof query.date_tournage === 'string') {
      dateTournage.value = query.date_tournage
    }
    if (typeof query.type_mission === 'string') {
      const validTypes = Object.values(MissionType)
      if (validTypes.includes(query.type_mission as MissionTypeType)) {
        typeMission.value = query.type_mission as MissionTypeType
      }
    }
  }

  /**
   * Sync current filters to URL query params
   */
  function syncToUrl(): void {
    const query: Record<string, string> = {}

    if (lieu.value) query.lieu = lieu.value
    if (budgetMin.value !== undefined) query.budget_min = String(budgetMin.value)
    if (budgetMax.value !== undefined) query.budget_max = String(budgetMax.value)
    if (dateTournage.value) query.date_tournage = dateTournage.value
    if (typeMission.value) query.type_mission = typeMission.value

    // Only update if query has changed
    const currentQuery = { ...route.query }
    const hasChanged = JSON.stringify(query) !== JSON.stringify(currentQuery)

    if (hasChanged) {
      isSyncingToUrl = true
      router.replace({ query }).finally(() => {
        isSyncingToUrl = false
      })
    }
  }

  /**
   * Reset all filters
   */
  function resetFilters(): void {
    lieu.value = ''
    budgetMin.value = undefined
    budgetMax.value = undefined
    dateTournage.value = ''
    typeMission.value = ''
    syncToUrl()
  }

  /**
   * Apply filters (sync to URL)
   */
  function applyFilters(): void {
    syncToUrl()
  }

  /**
   * Resets every filter ref to its default, then re-ingests the CURRENT URL
   * query. Unlike initFromUrl (which only touches keys present in the query),
   * this makes the refs match the URL exactly — for a <keep-alive>-cached page
   * being re-activated, which must discard stale drafts and drop filters the
   * URL no longer carries. State-only: never writes to the router (resetFilters
   * does, so it cannot be used during a navigation).
   */
  function reinitFromUrl(): void {
    lieu.value = ''
    budgetMin.value = undefined
    budgetMax.value = undefined
    dateTournage.value = ''
    typeMission.value = ''
    initFromUrl()
  }

  // Watch for external URL changes (e.g., back/forward navigation)
  watch(
    () => route.query,
    () => {
      // Skip if we're the ones who triggered the URL change
      if (!isSyncingToUrl) {
        initFromUrl()
      }
    },
  )

  return {
    // Filter state
    lieu,
    budgetMin,
    budgetMax,
    dateTournage,
    typeMission,
    // Computed
    filters,
    activeFilterCount,
    hasActiveFilters,
    // Methods
    resetFilters,
    applyFilters,
    syncToUrl,
    initFromUrl,
    reinitFromUrl,
  }
}
