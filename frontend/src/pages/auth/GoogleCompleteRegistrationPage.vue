<script setup lang="ts">
/**
 * Finalisation screen for a Google account that does not exist yet.
 *
 * Google returns neither a role, nor a date of birth, nor consent — so every
 * Google-created account passes through here. That is what keeps the 16+ legal
 * gate and the CGU acceptance in place on the Google path.
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { User, Calendar, Building } from 'lucide-vue-next'
import { FloatingField } from '@/components/ui/form'
import { useAuth } from '@/features/auth/composables/useAuth'
import {
  clearPendingGoogleRegistration,
  getPendingGoogleRegistration,
  type PendingGoogleRegistration,
} from '@/features/auth/googlePendingRegistration'
import type { CompleteGoogleRegistrationData, ProducerType } from '@/features/auth/types'

const router = useRouter()
const { completeGoogleRegistration, isLoading } = useAuth()

const pending = ref<PendingGoogleRegistration | null>(null)

const role = ref<'face' | 'producer'>('face')
const producerType = ref<ProducerType>('agency')

const nom = ref('')
const prenom = ref('')
const dateNaissance = ref('')
const nomOuRaisonSociale = ref('')
const acceptCgu = ref(false)

const apiError = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})

// The role is preselected from the button the user pressed. Only the /login entry
// point leaves it genuinely open.
const roleIsLocked = computed(() => pending.value?.intent !== 'login')

function fieldError(field: string): string | undefined {
  return fieldErrors.value[field]?.[0]
}

onMounted(() => {
  const stored = getPendingGoogleRegistration()

  if (stored === null) {
    router.replace({ name: 'login' })

    return
  }

  pending.value = stored
  prenom.value = stored.prenom
  nom.value = stored.nom
  role.value = stored.intent === 'producer' ? 'producer' : 'face'
})

async function handleSubmit(): Promise<void> {
  if (pending.value === null) return

  apiError.value = null
  fieldErrors.value = {}

  const payload: CompleteGoogleRegistrationData = {
    pending_token: pending.value.pending_token,
    role: role.value,
    accept_cgu: acceptCgu.value,
    ...(role.value === 'face'
      ? { nom: nom.value, prenom: prenom.value, date_naissance: dateNaissance.value }
      : producerType.value === 'agency'
        ? { type: producerType.value, agency_name: nomOuRaisonSociale.value }
        : { type: producerType.value, nom_complet: nomOuRaisonSociale.value }),
  }

  const result = await completeGoogleRegistration(payload)

  if (!result.success) {
    fieldErrors.value = result.errors ?? {}
    // Both branches submit one name input; surface its per-type error on it.
    const nameError = fieldErrors.value.agency_name ?? fieldErrors.value.nom_complet
    if (nameError) fieldErrors.value.nom_ou_raison_sociale = nameError

    if (Object.keys(fieldErrors.value).length === 0) {
      apiError.value = result.message ?? 'Une erreur est survenue'
    }

    return
  }

  const redirect = pending.value.redirect
  clearPendingGoogleRegistration()

  if (redirect !== null) {
    await router.replace(redirect)

    return
  }

  await router.replace(
    role.value === 'producer' ? { name: 'producer-dashboard' } : { name: 'face-upsell' }
  )
}
</script>

<template>
  <div
    class="min-h-screen flex flex-col items-center justify-center px-6 py-12 bg-gray-50"
    data-testid="google-complete-registration-page"
  >
    <div v-if="pending" class="max-w-md w-full bg-white rounded-2xl border border-gray-200 p-6">
      <h1 class="text-xl font-bold text-gray-900 mb-1">Presque terminé</h1>
      <p class="text-sm text-gray-500 mb-6">
        Connecté avec <span class="font-medium text-gray-700">{{ pending.email }}</span
        >. Encore quelques informations et votre compte est prêt.
      </p>

      <div
        v-if="apiError"
        class="rounded-lg bg-red-50 p-4 border border-red-200 mb-4"
        role="alert"
        data-testid="api-error"
      >
        <p class="text-sm text-red-700">{{ apiError }}</p>
      </div>

      <form class="space-y-6" data-testid="google-complete-form" @submit.prevent="handleSubmit">
        <!-- Role: preselected from the entry point, only open from /login -->
        <div v-if="!roleIsLocked" data-testid="role-selector">
          <p class="text-sm font-medium text-gray-700 mb-2">Vous êtes…</p>
          <div class="flex rounded-lg bg-gray-100 p-1">
            <button
              type="button"
              :class="[
                'flex-1 py-3 rounded-md text-sm font-medium transition-all',
                role === 'face'
                  ? 'bg-primary-500 text-white shadow-sm'
                  : 'text-gray-600 hover:text-gray-900',
              ]"
              data-testid="role-face-button"
              @click="role = 'face'"
            >
              Une Face
            </button>
            <button
              type="button"
              :class="[
                'flex-1 py-3 rounded-md text-sm font-medium transition-all',
                role === 'producer'
                  ? 'bg-primary-500 text-white shadow-sm'
                  : 'text-gray-600 hover:text-gray-900',
              ]"
              data-testid="role-producer-button"
              @click="role = 'producer'"
            >
              Un Producteur
            </button>
          </div>
        </div>

        <!-- Face branch -->
        <template v-if="role === 'face'">
          <div class="grid grid-cols-2 gap-4">
            <FloatingField
              id="nom"
              v-model="nom"
              label="Nom"
              :icon="User"
              :error="fieldError('nom')"
              required
              autocomplete="family-name"
              data-testid="nom-input"
            />
            <FloatingField
              id="prenom"
              v-model="prenom"
              label="Prénom"
              :icon="User"
              :error="fieldError('prenom')"
              required
              autocomplete="given-name"
              data-testid="prenom-input"
            />
          </div>

          <FloatingField
            id="date_naissance"
            v-model="dateNaissance"
            type="date"
            label="Date de naissance"
            :icon="Calendar"
            :error="fieldError('date_naissance')"
            required
            data-testid="date-naissance-input"
          />
        </template>

        <!-- Producer branch -->
        <template v-else>
          <div class="flex rounded-lg bg-gray-100 p-1" data-testid="type-selector">
            <button
              type="button"
              :class="[
                'flex-1 py-3 rounded-md text-sm font-medium transition-all',
                producerType === 'agency'
                  ? 'bg-primary-500 text-white shadow-sm'
                  : 'text-gray-600 hover:text-gray-900',
              ]"
              data-testid="type-agency-button"
              @click="producerType = 'agency'"
            >
              Agence
            </button>
            <button
              type="button"
              :class="[
                'flex-1 py-3 rounded-md text-sm font-medium transition-all',
                producerType === 'particulier'
                  ? 'bg-primary-500 text-white shadow-sm'
                  : 'text-gray-600 hover:text-gray-900',
              ]"
              data-testid="type-particulier-button"
              @click="producerType = 'particulier'"
            >
              Particulier
            </button>
          </div>

          <FloatingField
            id="nom_ou_raison_sociale"
            v-model="nomOuRaisonSociale"
            label="Nom ou raison sociale"
            :icon="Building"
            :error="fieldError('nom_ou_raison_sociale')"
            required
            autocomplete="organization"
            data-testid="nom-input"
          />
        </template>

        <!-- Consent is never skipped on the Google path -->
        <div class="space-y-1" data-testid="accept-cgu-field">
          <label class="flex items-start gap-2.5 cursor-pointer">
            <input
              v-model="acceptCgu"
              type="checkbox"
              class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500 cursor-pointer"
              data-testid="accept-cgu-checkbox"
            />
            <span class="text-xs text-gray-600 leading-relaxed">
              <template v-if="role === 'face'">J'ai 16 ans ou plus et j'accepte les </template>
              <template v-else>J'accepte les </template>
              <router-link
                to="/cgu"
                target="_blank"
                class="text-primary-500 hover:underline font-medium"
                >Conditions Générales d'Utilisation</router-link
              >
              et la
              <router-link
                to="/politique-confidentialite"
                target="_blank"
                class="text-primary-500 hover:underline font-medium"
                >Politique de Confidentialité</router-link
              >
              de WEACT.
            </span>
          </label>
          <p
            v-if="fieldError('accept_cgu')"
            class="text-xs text-red-500 ml-6"
            data-testid="accept-cgu-error"
          >
            {{ fieldError('accept_cgu') }}
          </p>
        </div>

        <button
          type="submit"
          :disabled="isLoading"
          class="w-full py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          data-testid="submit-button"
        >
          {{ isLoading ? 'Création en cours…' : 'Créer mon compte' }}
        </button>
      </form>
    </div>
  </div>
</template>
