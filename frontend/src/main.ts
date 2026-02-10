import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import Toast, { POSITION, type PluginOptions } from 'vue-toastification'
import 'vue-toastification/dist/index.css'

import App from './App.vue'
import router from './router'
import { setRouter, setPinia } from './services/apiClient'
import { setAdminRouter, setAdminPinia } from './features/admin/services/adminApiClient'

const app = createApp(App)
const pinia = createPinia()

// Toast configuration
const toastOptions: PluginOptions = {
  position: POSITION.TOP_RIGHT,
  timeout: 5000,
  closeOnClick: true,
  pauseOnFocusLoss: true,
  pauseOnHover: true,
  draggable: true,
  draggablePercent: 0.6,
  showCloseButtonOnHover: false,
  hideProgressBar: false,
  closeButton: 'button',
  icon: true,
  rtl: false,
}

app.use(pinia)
app.use(router)
app.use(Toast, toastOptions)

// Initialize API clients with router and pinia instances
// This enables 401 interceptors to redirect and clear auth stores
setRouter(router)
setPinia(pinia)
setAdminRouter(router)
setAdminPinia(pinia)

app.mount('#app')
