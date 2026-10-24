<script setup lang="ts">
/**
 * PushMomentCard — carte proposant à la Face d'activer les notifications à un moment clé
 * (discussion ouverte, candidature envoyée). Calquée sur PushSoftPrompt : « Activer » est le
 * seul déclencheur de la permission du navigateur ; « Plus tard » masque la carte pour ce
 * booking / cette mission / cette candidature seulement (elle revient au prochain moment clé).
 * Elle disparaît dès que les notifications sont actives sur l'appareil.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { BellRing } from 'lucide-vue-next'
import { buttonVariants } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useWebPush } from '../push/useWebPush'

interface Props {
  producerName: string | null
  scope: 'booking' | 'mission' | 'candidature'
  scopeId: string
}

const props = defineProps<Props>()

const emit = defineEmits<{ (e: 'visibility', visible: boolean): void }>()

const authStore = useAuthStore()
const toast = useToast()
const { status, isBusy, refresh, enable } = useWebPush()

const dismissed = ref(true)

const storageKey = computed(
  () => `weact.push.moment.${props.scope}.${props.scopeId}.${authStore.user?.id ?? 'anonymous'}`,
)

const name = computed(() => props.producerName?.trim() || null)

const title = computed(() => {
  if (props.scope === 'candidature') {
    return name.value ? `Suivez la réponse de ${name.value}` : 'Suivez la réponse du Producteur'
  }
  return name.value
    ? `Ne ratez pas les messages de ${name.value}`
    : 'Ne ratez pas les messages du Producteur'
})

const text = computed(() =>
  props.scope === 'candidature'
    ? "Activez les notifications sur ce téléphone pour voir sa réponse dès qu'elle arrive."
    : "Activez les notifications sur ce téléphone pour voir ses messages dès qu'ils arrivent.",
)

const isIosInstall = computed(() => status.value === 'ios-install-required')

// 'disabled' = activable ; 'ios-install-required' = explication. Refus navigateur : rien.
const isVisible = computed(
  () => !dismissed.value && (status.value === 'disabled' || isIosInstall.value),
)

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
    // Stockage indisponible (navigation privée…) : la carte reviendra à la prochaine visite.
  }
}

async function handleEnable(): Promise<void> {
  const result = await enable()
  if (result === 'enabled') {
    toast.success('Notifications activées sur cet appareil')
  } else if (result === 'error') {
    toast.error("Impossible d'activer les notifications. Réessayez depuis la cloche.")
  }
}

// Signale la visibilité au parent (ex. la modale de candidature ne se ferme pas seule).
watch(isVisible, (visible) => emit('visibility', visible), { immediate: true })

watch(storageKey, () => {
  dismissed.value = readDismissed()
})

onMounted(async () => {
  dismissed.value = readDismissed()
  if (!dismissed.value && status.value === 'checking') await refresh()
})
</script>

<template>
  <section
    v-if="isVisible"
    class="flex flex-col gap-3 rounded-panel bg-weact-50 px-4 py-3 ring-1 ring-weact-600"
    data-testid="push-moment-card"
  >
    <div class="flex min-w-0 items-start gap-3">
      <BellRing :size="18" class="mt-0.5 shrink-0 text-weact-600" aria-hidden="true" />
      <div class="grid gap-0.5">
        <p class="text-[14px] font-bold text-ink" data-testid="push-moment-title">{{ title }}</p>
        <p class="text-[13px] text-ink">{{ text }}</p>
        <p v-if="isIosInstall" class="text-[13px] text-ink-2" data-testid="push-moment-ios">
          Sur iPhone : ajoutez d'abord WeAct à l'écran d'accueil (Partager, puis « Sur l'écran
          d'accueil »), puis activez les notifications.
        </p>
      </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <button
        v-if="!isIosInstall"
        type="button"
        :class="cn(buttonVariants({ variant: 'regie', size: 'regie' }))"
        :disabled="isBusy"
        data-testid="push-moment-enable"
        @click="handleEnable"
      >
        Activer les notifications
      </button>
      <button
        type="button"
        :class="cn(buttonVariants({ variant: 'regie-secondary', size: 'regie' }))"
        data-testid="push-moment-later"
        @click="remember"
      >
        Plus tard
      </button>
    </div>
  </section>
</template>
