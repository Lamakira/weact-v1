import { useToast as useVueToastification } from 'vue-toastification'

/**
 * Composable for showing toast notifications
 * Wraps vue-toastification with WEACT-specific defaults
 */
export function useToast() {
  const toast = useVueToastification()

  return {
    /**
     * Show a success toast
     */
    success(message: string) {
      toast.success(message)
    },

    /**
     * Show an error toast
     */
    error(message: string) {
      toast.error(message)
    },

    /**
     * Show a warning toast
     */
    warning(message: string) {
      toast.warning(message)
    },

    /**
     * Show an info toast
     */
    info(message: string) {
      toast.info(message)
    },

    /**
     * Clear all toasts
     */
    clear() {
      toast.clear()
    },

    /**
     * Access the underlying toast instance for advanced usage
     */
    toast,
  }
}
