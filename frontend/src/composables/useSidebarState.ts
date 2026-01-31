import { ref, computed } from 'vue'

const isExpanded = ref(true)
const isMobileOpen = ref(false)

/**
 * Composable for managing dashboard sidebar state.
 * State is shared across all components using this composable.
 */
export function useSidebarState() {
  const toggle = () => {
    isExpanded.value = !isExpanded.value
  }

  const collapse = () => {
    isExpanded.value = false
  }

  const expand = () => {
    isExpanded.value = true
  }

  const openMobile = () => {
    isMobileOpen.value = true
  }

  const closeMobile = () => {
    isMobileOpen.value = false
  }

  const toggleMobile = () => {
    isMobileOpen.value = !isMobileOpen.value
  }

  /** Sidebar width class based on expanded state */
  const sidebarWidth = computed(() => (isExpanded.value ? 'w-64' : 'w-20'))

  return {
    isExpanded,
    isMobileOpen,
    sidebarWidth,
    toggle,
    collapse,
    expand,
    openMobile,
    closeMobile,
    toggleMobile,
  }
}
