<script setup lang="ts">
/**
 * PushSoftPrompt — invite unique, fermable, affichée sur le tableau de bord après
 * connexion. « Activer » est le seul déclencheur de la demande de permission du
 * navigateur. Le refus (« Plus tard ») est mémorisé par utilisateur.
 */
import { computed, onMounted, ref } from 'vue'
import { BellRing } from 'lucide-vue-next'
import { buttonVariants } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useWebPush } from '../push/useWebPush'

interface Props {
  audience: 'face' | 'producer'
}

const props = defineProps<Props>()

const authStore = useAuthStore()
const toast = useToast()
const { status, isBusy, refresh, enable } = useWebPush()

const dismissed = ref(true)

const storageKey = computed(() => `weact.push.softPrompt.${authStore.user?.id ?? 'anonymous'}`)

const message = computed(() =>
  props.audience === 'face'
    ? 'Recevez une alerte quand un Producteur vous propose un booking'
    : 'Recevez une alerte quand une Face répond',
)

// 'disabled' = supporté, clés présentes, pas encore abonné, permission non refusée
const isVisible = computed(() => !dismissed.value && status.value === 'disabled')

function readDismissed(): boolean {
  try {
    return localStorage.getItem(storageKey.value) === '1'
  } catch {
    return false
  }
}

function remember(): void {
  dismissed.value = true
  try {
    localStorage.setItem(storageKey.value, '1')
  } catch {
    // Stockage indisponible (navigation privée…) : l'invite reviendra à la prochaine visite.
  }
}

async function handleEnable(): Promise<void> {
  const result = await enable()
  remember()
  if (result === 'enabled') {
    toast.success('Notifications activées sur cet appareil')
  } else if (result === 'error') {
    toast.error("Impossible d'activer les notifications. Réessayez depuis la cloche.")
  }
}

onMounted(async () => {
  dismissed.value = readDismissed()
  if (!dismissed.value) await refresh()
})
</script>

<template>
  <section
    v-if="isVisible"
    class="flex flex-col gap-3 rounded-panel bg-white px-4 py-3 ring-1 ring-line sm:flex-row sm:items-center"
    data-testid="push-soft-prompt"
  >
    <div class="flex min-w-0 flex-1 items-start gap-3">
      <BellRing :size="18" class="mt-0.5 shrink-0 text-weact-600" aria-hidden="true" />
      <p class="text-[13.5px] font-medium text-ink">{{ message }}</p>
    </div>
    <div class="flex shrink-0 items-center gap-2">
      <button
        type="button"
        :class="cn(buttonVariants({ variant: 'regie', size: 'regie' }))"
        :disabled="isBusy"
        data-testid="push-soft-prompt-enable"
        @click="handleEnable"
      >
        Activer
      </button>
      <button
        type="button"
        :class="cn(buttonVariants({ variant: 'regie-secondary', size: 'regie' }))"
        data-testid="push-soft-prompt-later"
        @click="remember"
      >
        Plus tard
      </button>
    </div>
  </section>
</template>
