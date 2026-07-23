import { toast } from 'vue-sonner'

/**
 * Options acceptées par le wrapper — volontairement étroit. Le reste de l'API
 * sonner (action, description, promise…) reste accessible via `toast` ci-dessous
 * pour les cas avancés, sans que chaque call-site ait à connaître la librairie.
 */
type ToastOptions = {
  /** Durée d'affichage en millisecondes. */
  duration?: number
}

/**
 * Composable for showing toast notifications.
 *
 * Wraps vue-sonner (shadcn-vue) with WEACT-specific defaults. Les 43 call-sites
 * passent tous par ici : c'est le SEUL endroit du code applicatif qui connaît la
 * librairie de toasts — la migration depuis vue-toastification n'a touché que ce
 * fichier, main.ts et App.vue. Les réglages globaux (position, durée par défaut,
 * couleurs) vivent sur le <Toaster> monté dans App.vue.
 */
export function useToast() {
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
      toast.dismiss()
    },

    /**
     * Access the underlying toast instance for advanced usage
     */
    toast,
  }
}
