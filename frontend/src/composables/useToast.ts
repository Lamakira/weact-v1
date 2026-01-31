import { useToast as useVueToastification } from 'vue-toastification'
import type { PluginOptions } from 'vue-toastification'

type ToastOptions = Partial<Pick<PluginOptions, 'timeout'>>

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
    success(message: string, options?: ToastOptions) {
      toast.success(message, options)
    },

    /**
     * Show an error toast
     */
    error(message: string, options?: ToastOptions) {
      toast.error(message, options)
    },

    /**
     * Show a warning toast
     */
    warning(message: string, options?: ToastOptions) {
      toast.warning(message, options)
    },

    /**
     * Show an info toast
     */
    info(message: string, options?: ToastOptions) {
      toast.info(message, options)
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
