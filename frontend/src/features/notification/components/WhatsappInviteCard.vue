<script setup lang="ts">
/**
 * WhatsappInviteCard — invitation unique à activer les alertes WhatsApp, sur les
 * tableaux de bord Face et Producteur. Visible si la fonctionnalité est disponible,
 * le profil a un numéro valide, les alertes ne sont pas activées et l'invitation n'a
 * pas été écartée. « Plus tard » la masque définitivement (réglage du profil conservé).
 * Producteur sans numéro : pas de carte (la to-do « missing_whatsapp » le demande déjà).
 */
import { computed, ref } from 'vue'
import { MessageCircle } from 'lucide-vue-next'
import { buttonVariants } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { whatsappAlertsApi, whatsappErrorMessage } from '../services/whatsappAlertsApi'

interface Props {
  audience: 'face' | 'producer'
}

const props = defineProps<Props>()

const authStore = useAuthStore()
const toast = useToast()
const isBusy = ref(false)

const alerts = computed(() => authStore.user?.whatsapp_alerts)

const isVisible = computed(
  () =>
    alerts.value?.available === true &&
    alerts.value.has_valid_number === true &&
    alerts.value.enabled !== true &&
    alerts.value.invite_dismissed !== true,
)

const title = computed(() =>
  props.audience === 'face'
    ? 'Recevez vos demandes de booking sur WhatsApp'
    : 'Soyez prévenu sur WhatsApp quand une Face répond',
)

const body = computed(() => {
  const number = alerts.value?.number
  const at = number ? ` au ${number}` : ''
  if (props.audience === 'face') {
    return `Nous vous prévenons${at} quand un Producteur vous propose un booking, vous écrit ou paie. Pas de publicité.`
  }
  return `Nous vous prévenons${at} quand une Face répond, qu'une candidature arrive ou qu'un message vous attend. Pas de publicité.`
})

async function handleEnable(): Promise<void> {
  if (isBusy.value) return
  isBusy.value = true
  try {
    await whatsappAlertsApi.setWhatsappAlerts(true)
    toast.success('Alertes WhatsApp activées')
  } catch (err) {
    toast.error(
      whatsappErrorMessage(err, "Impossible d'activer les alertes WhatsApp. Réessayez dans un instant."),
    )
  } finally {
    isBusy.value = false
  }
}

async function handleLater(): Promise<void> {
  if (isBusy.value) return
  isBusy.value = true
  try {
    await whatsappAlertsApi.dismissWhatsappInvite()
  } catch {
    toast.error('Une erreur est survenue. Réessayez dans un instant.')
  } finally {
    isBusy.value = false
  }
}
</script>

<template>
  <section
    v-if="isVisible"
    class="flex flex-col gap-3 rounded-panel bg-weact-50 px-4 py-3 ring-1 ring-weact-500 sm:flex-row sm:items-center"
    data-testid="whatsapp-invite-card"
  >
    <div class="flex min-w-0 flex-1 items-start gap-3">
      <MessageCircle :size="18" class="mt-0.5 shrink-0 text-weact-600" aria-hidden="true" />
      <div class="flex min-w-0 flex-col gap-0.5">
        <p class="text-[14px] font-bold text-ink">{{ title }}</p>
        <p class="text-[13px] text-ink">{{ body }}</p>
      </div>
    </div>
    <div class="flex shrink-0 items-center gap-2">
      <button
        type="button"
        :class="cn(buttonVariants({ variant: 'regie', size: 'regie' }))"
        :disabled="isBusy"
        data-testid="whatsapp-invite-enable"
        @click="handleEnable"
      >
        Activer les alertes WhatsApp
      </button>
      <button
        type="button"
        :class="cn(buttonVariants({ variant: 'regie-secondary', size: 'regie' }))"
        :disabled="isBusy"
        data-testid="whatsapp-invite-later"
        @click="handleLater"
      >
        Plus tard
      </button>
    </div>
  </section>
</template>
