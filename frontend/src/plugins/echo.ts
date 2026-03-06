import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { getXsrfTokenFromCookie } from '@/utils/csrf'

// @ts-expect-error — Echo requires window.Pusher (Reverb uses Pusher protocol)
window.Pusher = Pusher

const token = localStorage.getItem('auth_token')
const xsrfToken = getXsrfTokenFromCookie()

export const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
  wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
  forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
  enabledTransports: ['ws', 'wss'],
  authEndpoint: `${import.meta.env.VITE_BACKEND_URL}/broadcasting/auth`,
  withCredentials: true,
  auth: {
    headers: {
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
      Accept: 'application/json',
    },
  },
})
