import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
// Feuille de style de vue-sonner (shadcn-vue). Importée APRÈS main.css : ses
// sélecteurs sont propres à la librairie ([data-sonner-toast]…), aucun conflit
// avec les utilities Tailwind, et l'ordre garantit que nos surcharges de
// variables (--normal-bg… posées par components/ui/sonner) gagnent.
import 'vue-sonner/style.css'

import App from './App.vue'
import router from './router'
import { setRouter, setPinia } from './services/apiClient'
import { setAdminRouter, setAdminPinia } from './features/admin/services/adminApiClient'

const app = createApp(App)
const pinia = createPinia()

// Les toasts n'ont plus de plugin : vue-sonner s'utilise via le composant
// <Toaster> monté dans App.vue (réglages globaux) et la fonction `toast`
// encapsulée dans le composable useToast.
app.use(pinia)
app.use(router)

// Initialize API clients with router and pinia instances
// This enables 401 interceptors to redirect and clear auth stores
setRouter(router)
setPinia(pinia)
setAdminRouter(router)
setAdminPinia(pinia)

app.mount('#app')
