<script setup lang="ts">
/**
 * PushToggle — « Notifications sur cet appareil » : active/désactive le web push
 * pour CET navigateur. La permission n'est demandée qu'au clic. Masqué quand le
 * navigateur n'a pas de push ou que le serveur n'a pas de clés VAPID.
 */
import { computed, onMounted } from 'vue'
import { useToast } from '@/composables/useToast'
import { useWebPush } from '../push/useWebPush'

const toast = useToast()
const { status, isBusy, refresh, enable, disable } = useWebPush()

const isVisible = computed(
  () => status.value !== 'checking' && status.value !== 'unsupported' && status.value !== 'unavailable',
)
const isOn = computed(() => status.value === 'enabled')
const isToggleDisabled = computed(
  () => isBusy.value || status.value === 'denied' ||
    status.value === 'ios-install-required' ||
    status.value === 'email-unverified',
)

async function handleToggle(): Promise<void> {
  if (isToggleDisabled.value) return

  if (isOn.value) {
    await disable()
    toast.info('Notifications désactivées sur cet appareil')
    return
  }

  const result = await enable()
  if (result === 'enabled') {
    toast.success('Notifications activées sur cet appareil')
  } else if (result === 'error') {
    toast.error("Impossible d'activer les notifications. Réessayez dans un instant.")
  }
}

onMounted(() => {
  void refresh()
})
</script>

<template>
  <div v-if="isVisible" class="px-4 py-3 border-t border-border" data-testid="push-toggle">
    <div class="flex items-center justify-between gap-3">
      <div class="min-w-0">
        <p class="text-sm font-medium text-foreground">Notifications sur cet appareil</p>
        <p class="text-xs text-muted-foreground mt-0.5">
          Une alerte même quand WeAct est fermé.
        </p>
      </div>
      <button
        type="button"
        role="switch"
        :aria-checked="isOn"
        aria-label="Notifications sur cet appareil"
        :disabled="isToggleDisabled"
        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        :class="isOn ? 'bg-primary' : 'bg-muted-foreground/30'"
        data-testid="push-toggle-switch"
        @click.stop="handleToggle"
      >
        <span
          class="inline-block size-5 rounded-full bg-white shadow transition-transform"
          :class="isOn ? 'translate-x-5' : 'translate-x-0.5'"
        />
      </button>
    </div>

    <p
      v-if="status === 'ios-install-required'"
      class="mt-2 text-xs text-muted-foreground"
      data-testid="push-toggle-ios-hint"
    >
      Sur iPhone, ajoutez d'abord WeAct à l'écran d'accueil (Partager → Sur l'écran d'accueil).
    </p>
    <p
      v-else-if="status === 'email-unverified'"
      class="mt-2 text-xs text-muted-foreground"
      data-testid="push-toggle-unverified-hint"
    >
      Vérifiez votre adresse e-mail pour activer les notifications.
    </p>
    <p
      v-else-if="status === 'denied'"
      class="mt-2 text-xs text-muted-foreground"
      data-testid="push-toggle-denied-hint"
    >
      Les notifications sont bloquées pour WeAct dans votre navigateur. Pour les réactiver, ouvrez
      les réglages du site (icône à gauche de l'adresse, ou Réglages du navigateur → Notifications),
      autorisez-les, puis revenez ici.
    </p>
  </div>
</template>
