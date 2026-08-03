<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { authApi, getApiErrorMessage } from '../services/authApi'
import type { GoogleIntent } from '../types'

const props = withDefaults(
  defineProps<{
    intent: GoogleIntent
    /** Signup surfaces disable the button until the CGU checkbox is ticked. */
    disabled?: boolean
    label?: string
  }>(),
  { disabled: false, label: 'Continuer avec Google' }
)

const route = useRoute()

const isLoading = ref(false)
const error = ref<string | null>(null)

async function handleClick(): Promise<void> {
  if (props.disabled || isLoading.value) return

  isLoading.value = true
  error.value = null

  try {
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null
    // The backend hands back a URL rather than redirecting: a 302 here would be
    // followed by this XHR and die on Google's origin (no CORS header).
    const url = await authApi.getGoogleRedirectUrl(props.intent, redirect)

    window.location.href = url
  } catch (err) {
    error.value = getApiErrorMessage(err)
    isLoading.value = false
  }
}
</script>

<template>
  <div data-testid="google-sign-in">
    <button
      type="button"
      :disabled="disabled || isLoading"
      class="w-full flex items-center justify-center gap-3 py-3 px-4 border border-gray-300 rounded-lg bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      data-testid="google-sign-in-button"
      @click="handleClick"
    >
      <!-- Google "G", inlined: no external asset, no request. -->
      <svg class="h-5 w-5 shrink-0" viewBox="0 0 18 18" aria-hidden="true">
        <path
          fill="#4285F4"
          d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"
        />
        <path
          fill="#34A853"
          d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z"
        />
        <path
          fill="#FBBC05"
          d="M3.97 10.72a5.4 5.4 0 0 1 0-3.44V4.95H.96a9 9 0 0 0 0 8.1l3.01-2.33z"
        />
        <path
          fill="#EA4335"
          d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.9 11.43 0 9 0A9 9 0 0 0 .96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"
        />
      </svg>
      <span v-if="isLoading">Redirection…</span>
      <span v-else>{{ label }}</span>
    </button>

    <p v-if="error" class="mt-2 text-xs text-red-500" data-testid="google-sign-in-error">
      {{ error }}
    </p>
  </div>
</template>
