<script setup lang="ts">
/**
 * WhatsappAlertsToggle — « Recevoir mes alertes WeAct sur WhatsApp », sous le champ
 * numéro WhatsApp des profils Face et Producteur. Reflète le numéro ENREGISTRÉ
 * (`has_valid_number` du serveur), pas la saisie en cours. Masqué tant que la
 * fonctionnalité n'est pas disponible côté serveur.
 */
import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { whatsappAlertsApi, whatsappErrorMessage } from '../services/whatsappAlertsApi'

interface Props {
  /** Valeur actuellement saisie dans le champ numéro (peut différer de l'enregistré). */
  typedNumber?: string | null
  /** Valeur enregistrée du champ numéro (celle du profil chargé). */
  savedNumber?: string | null
}

const props = withDefaults(defineProps<Props>(), { typedNumber: null, savedNumber: null })

const authStore = useAuthStore()
const toast = useToast()
const isBusy = ref(false)

const alerts = computed(() => authStore.user?.whatsapp_alerts)
const isAvailable = computed(() => alerts.value?.available === true)
const hasValidNumber = computed(() => alerts.value?.has_valid_number === true)
const isOn = computed(() => alerts.value?.enabled === true)

const isNumberDirty = computed(
  () => (props.typedNumber ?? '').trim() !== (props.savedNumber ?? '').trim(),
)
const isDisabled = computed(() => isBusy.value || !hasValidNumber.value || isNumberDirty.value)

const hint = computed(() => {
  if (isNumberDirty.value) return 'Enregistrez votre numéro pour activer les alertes'
  if (!hasValidNumber.value) return "Ajoutez d'abord votre numéro WhatsApp"
  return null
})

const enabledOn = computed(() => {
  const at = alerts.value?.enabled_at
  if (!isOn.value || !at) return null
  const date = new Date(at)
  if (Number.isNaN(date.getTime())) return null
  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
})

async function handleToggle(): Promise<void> {
  if (isDisabled.value) return
  isBusy.value = true
  try {
    const next = !isOn.value
    await whatsappAlertsApi.setWhatsappAlerts(next)
    if (next) toast.success('Alertes WhatsApp activées')
    else toast.info('Alertes WhatsApp désactivées')
  } catch (err) {
    toast.error(
      whatsappErrorMessage(err, 'Impossible de modifier les alertes WhatsApp. Réessayez dans un instant.'),
    )
  } finally {
    isBusy.value = false
  }
}

// Un numéro enregistré qui change rend l'inscription caduque côté serveur :
// on recharge l'utilisateur pour que le bloc reflète le nouvel état.
watch(
  () => props.savedNumber,
  (next, prev) => {
    if (isAvailable.value && (next ?? '') !== (prev ?? '')) {
      void authStore.refreshUser()
    }
  },
)
</script>

<template>
  <div
    v-if="isAvailable"
    class="rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm"
    data-testid="whatsapp-alerts-toggle"
  >
    <div class="flex items-start gap-3">
      <button
        type="button"
        role="switch"
        :aria-checked="isOn"
        aria-label="Recevoir mes alertes WeAct sur WhatsApp"
        :disabled="isDisabled"
        class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        :class="isOn ? 'bg-teal-600' : 'bg-gray-200'"
        data-testid="whatsapp-alerts-switch"
        @click="handleToggle"
      >
        <span
          aria-hidden="true"
          class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"
          :class="isOn ? 'translate-x-5' : 'translate-x-1'"
        />
      </button>
      <div class="flex min-w-0 flex-col gap-0.5">
        <span class="text-sm font-medium text-gray-700">Recevoir mes alertes WeAct sur WhatsApp</span>
        <span class="text-xs text-gray-500">
          Demandes de booking, messages non lus et paiements. Pas de publicité. Vous pouvez arrêter à tout moment ici ou en répondant STOP.
        </span>
        <span
          v-if="hint"
          class="text-xs font-medium text-amber-700"
          data-testid="whatsapp-alerts-hint"
        >
          {{ hint }}
        </span>
        <span
          v-else-if="enabledOn"
          class="text-xs font-semibold text-emerald-700"
          data-testid="whatsapp-alerts-enabled-at"
        >
          Activé le {{ enabledOn }}
        </span>
        <span class="text-xs text-gray-500">Changer de numéro désactive les alertes.</span>
      </div>
    </div>
  </div>
</template>
