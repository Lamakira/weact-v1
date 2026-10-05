<script setup lang="ts">
/**
 * Landing page of the Google round-trip.
 *
 * Google → backend callback → here, carrying either a one-shot `code` or an
 * `error`. The code is stripped from the URL before anything else so it never
 * lingers in the history entry, then traded for the Sanctum token.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Loader2 } from 'lucide-vue-next'
import { useAuth } from '@/features/auth/composables/useAuth'
import { setPendingGoogleRegistration } from '@/features/auth/googlePendingRegistration'
import { takeGoogleOAuthNonce } from '@/features/auth/googleOAuthNonce'
import { setGoogleReauthTicket, takePendingReauthPurpose } from '@/features/auth/googleReauth'
import { safeRedirect } from '@/lib/safeRedirect'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const { exchangeGoogleCode } = useAuth()
const authStore = useAuthStore()

const error = ref<string | null>(null)
const errorCode = ref<string | null>(null)

const NOT_LINKED_CODE = 'GOOGLE_ACCOUNT_NOT_LINKED'
const NOT_LINKED_REAUTH_MESSAGE =
  "Ce compte Google n'est pas celui associé à votre compte WEACT."

// A bounce for an already-authenticated user can only be a reauth attempt.
const isReauthMismatch = computed(
  () => errorCode.value === NOT_LINKED_CODE && authStore.isAuthenticated
)

const profileRoute = computed(() =>
  authStore.isProducer ? { name: 'producer-profile' } : { name: 'face-profile' }
)

const GENERIC_ERROR = 'La connexion avec Google a échoué.'

// Own keys only: `?error=constructor` must not resolve to Object.prototype members.
// (Object.prototype.hasOwnProperty.call: the project lib predates Object.hasOwn.)
function messageFor(code: string): string {
  return Object.prototype.hasOwnProperty.call(ERROR_MESSAGES, code)
    ? (ERROR_MESSAGES[code] ?? GENERIC_ERROR)
    : GENERIC_ERROR
}

function roleDashboard(): { name: string } {
  return authStore.isProducer ? { name: 'producer-dashboard' } : { name: 'face-dashboard' }
}

// Errors the backend can bounce back on the callback, mapped to something a
// human can act on.
const ERROR_MESSAGES: Record<string, string> = {
  GOOGLE_EMAIL_UNVERIFIED:
    "Votre adresse Google n'est pas vérifiée. Vérifiez-la chez Google, puis réessayez.",
  ACCOUNT_DEACTIVATED: 'Ce compte a été désactivé.',
  registration_disabled:
    'Les inscriptions sont temporairement suspendues. Veuillez réessayer ultérieurement.',
  OAUTH_STATE_INVALID: 'Lien de connexion expiré ou invalide. Reprenez la connexion avec Google.',
  GOOGLE_HANDSHAKE_FAILED: 'La connexion avec Google a échoué. Veuillez réessayer.',
  GOOGLE_OAUTH_DISABLED: 'La connexion avec Google est indisponible.',
  GOOGLE_ACCOUNT_NOT_LINKED:
    "Ce compte Google n'est associé à aucun compte WEACT. Connectez-vous d'abord.",
  GOOGLE_ACCOUNT_CONFLICT: 'Cette adresse est déjà associée à un autre compte Google.',
}

onMounted(async () => {
  const code = typeof route.query.code === 'string' ? route.query.code : null
  const bouncedError = typeof route.query.error === 'string' ? route.query.error : null
  // One-shot like the code: consumed whatever the outcome.
  const nonce = takeGoogleOAuthNonce()

  // Strip the one-shot code from the URL before doing anything with it: the
  // history entry must not keep it.
  await router.replace({ name: 'google-callback' })

  if (bouncedError !== null) {
    errorCode.value = bouncedError
    error.value = messageFor(bouncedError)
    if (isReauthMismatch.value) error.value = NOT_LINKED_REAUTH_MESSAGE

    return
  }

  if (code === null) {
    error.value = 'Lien de connexion invalide. Reprenez la connexion avec Google.'

    return
  }

  // Browser binding: a code that did not start in this tab cannot be exchanged.
  if (nonce === null) {
    error.value = messageFor('OAUTH_STATE_INVALID')

    return
  }

  const outcome = await exchangeGoogleCode(code, nonce)

  if (!outcome.success || !outcome.result) {
    error.value = outcome.message ?? 'La connexion avec Google a échoué.'

    return
  }

  const result = outcome.result

  // Re-authentication before an irreversible action: no session is opened, the
  // ticket goes back to the screen that asked for it.
  if (result.reauth_token) {
    // The ticket is stamped with the purpose the button recorded; without one,
    // nobody asked for it and it is dropped.
    const purpose = takePendingReauthPurpose()

    if (purpose !== null) setGoogleReauthTicket(result.reauth_token, purpose)

    await router.replace(safeRedirect(result.redirect) ?? roleDashboard())

    return
  }

  if (result.needs_completion) {
    // Nothing exists server-side yet: hand the finalisation screen what it needs.
    setPendingGoogleRegistration({
      pending_token: result.pending_token ?? '',
      email: result.email ?? '',
      prenom: result.prenom ?? '',
      nom: result.nom ?? '',
      intent: result.intent ?? 'login',
      redirect: safeRedirect(result.redirect),
    })

    await router.replace({ name: 'google-complete-registration' })

    return
  }

  const redirect = safeRedirect(result.redirect)

  if (redirect !== null) {
    await router.replace(redirect)

    return
  }

  await router.replace(
    result.user?.userable_type === 'Producer'
      ? { name: 'producer-dashboard' }
      : { name: 'face-dashboard' }
  )
})
</script>

<template>
  <div
    class="min-h-screen flex flex-col items-center justify-center px-6 bg-gray-50"
    data-testid="google-callback-page"
  >
    <div v-if="error === null" class="flex flex-col items-center gap-3 text-gray-600">
      <Loader2 class="h-8 w-8 animate-spin text-[#198496]" />
      <p class="text-sm">Connexion en cours…</p>
    </div>

    <div v-else class="max-w-md w-full text-center" data-testid="google-callback-error">
      <div class="rounded-xl border border-red-200 bg-red-50 p-4 mb-4">
        <p class="text-sm text-red-700">{{ error }}</p>
      </div>
      <router-link
        v-if="isReauthMismatch"
        :to="profileRoute"
        class="text-sm font-semibold text-[#198496] hover:underline"
        data-testid="google-callback-back-to-profile"
      >
        Retour à mon profil
      </router-link>
      <router-link
        v-else
        :to="{ name: 'login' }"
        class="text-sm font-semibold text-[#198496] hover:underline"
        data-testid="google-callback-back-to-login"
      >
        Retour à la connexion
      </router-link>
    </div>
  </div>
</template>
