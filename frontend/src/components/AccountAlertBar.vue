<script setup lang="ts">
/**
 * Barre d'alertes unique du compte (Face et Producer), placée sous l'en-tête du
 * tableau de bord : une seule ligne fine qui affiche les alertes actives une à la
 * fois, dans l'ordre d'urgence fixe suspension UGC, paiement d'abonnement, e-mail à
 * vérifier (voir accountAlerts.ts). Remplace l'empilement de bandeaux.
 *
 * - Défilement automatique toutes les 6 s, SAUF si une alerte bloquante (suspension)
 *   est présente, au survol ou au focus clavier dans la barre, et sous
 *   `prefers-reduced-motion`.
 * - Annonce lecteur d'écran (aria-live) uniquement après un changement manuel.
 * - Chaque source garde son texte et son action d'origine.
 */
import { computed, ref, watch, watchEffect, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, Info, Ban } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useUgcSuspension } from '@/composables/useUgcSuspension'
import { useMediaQuery } from '@/composables/useMediaQuery'
import { useResendVerificationEmail } from '@/composables/useResendVerificationEmail'
import SubscriptionAlertSource from '@/components/SubscriptionAlertSource.vue'
import { sortAlerts, type AccountAlert } from './accountAlerts'

const props = withDefaults(
  defineProps<{
    /** Les sources suspension UGC et abonnement ne concernent que les Faces */
    audience?: 'face' | 'producer'
    /** Route qui porte sa propre surface d'abonnement : pas d'alerte de paiement */
    hideSubscription?: boolean
  }>(),
  { audience: 'face', hideSubscription: false },
)

const AUTO_ADVANCE_MS = 6000

const router = useRouter()
const authStore = useAuthStore()
const isFace = props.audience === 'face'

// Suspension UGC (Face uniquement) : auto-récupérée, silencieuse tant que non résolue.
const suspension = isFace ? useUgcSuspension() : null
onMounted(() => { void suspension?.fetchStatus() })

const { isLoading: resending, cooldownSeconds, canResend, handleResend } = useResendVerificationEmail()

// Alerte d'abonnement, remontée par la source sans rendu.
const subscriptionAlert = ref<AccountAlert | null>(null)
const showSubscriptionSource = computed(() => isFace && !props.hideSubscription)

const emailLabel = computed(() => {
  if (cooldownSeconds.value > 0) return `Renvoyer (${cooldownSeconds.value}s)`
  if (resending.value) return 'Envoi en cours...'
  return 'Renvoyer l\'email'
})

const alerts = computed<AccountAlert[]>(() => {
  const list: AccountAlert[] = []
  if (suspension?.isSuspended.value) {
    list.push({
      id: 'ugc-suspension',
      tone: 'danger',
      title: 'Compte suspendu.',
      message: 'Vous avez dépassé une deadline UGC. Vos missions sont en pause et votre accès UGC est bloqué.',
      blocking: true,
      actionLabel: 'Voir les détails',
      onAction: () => { void router.push({ name: 'face-ugc-suspension' }) },
    })
  }
  if (showSubscriptionSource.value && subscriptionAlert.value) list.push(subscriptionAlert.value)
  if (!authStore.isEmailVerified) {
    list.push({
      id: 'email-verification',
      tone: 'info',
      title: 'Vérifiez votre adresse email.',
      message: 'Pour accéder à toutes les fonctionnalités de la plateforme, veuillez cliquer sur le lien envoyé lors de votre inscription.',
      actionLabel: emailLabel.value,
      actionDisabled: !canResend.value,
      onAction: () => { void handleResend() },
    })
  }
  return sortAlerts(list)
})

// L'alerte courante est suivie par identifiant (si une autre disparaît, on ne saute pas).
// Tant que personne n'a choisi ni fait défiler, `null` = la plus urgente : une alerte qui
// arrive après coup (suspension, paiement chargés en asynchrone) prend sa place d'emblée.
const currentId = ref<string | null>(null)
const currentIndex = computed(() => {
  const found = alerts.value.findIndex((a) => a.id === currentId.value)
  return found === -1 ? 0 : found
})
const current = computed(() => alerts.value[currentIndex.value] ?? null)
// Une alerte bloquante qui apparaît reprend la main sur le défilement.
watch(
  () => alerts.value.some((a) => a.blocking),
  (blocking) => { if (blocking) currentId.value = null },
)

// Annonce vocale seulement après un choix manuel (jamais lors du défilement automatique).
const announce = ref(false)
function select(index: number, manual: boolean): void {
  const target = alerts.value[index]
  if (!target) return
  announce.value = manual
  currentId.value = target.id
}

const prefersReducedMotion = useMediaQuery('(prefers-reduced-motion: reduce)')
const paused = ref(false)
const hasBlocking = computed(() => alerts.value.some((a) => a.blocking))
const autoAdvance = computed(
  () => alerts.value.length > 1 && !hasBlocking.value && !prefersReducedMotion.value,
)
const running = computed(() => autoAdvance.value && !paused.value)

watchEffect((onCleanup) => {
  if (!running.value) return
  // Relit l'alerte courante pour relancer le minuteur à chaque changement.
  void currentId.value
  const timer = setTimeout(() => {
    select((currentIndex.value + 1) % alerts.value.length, false)
  }, AUTO_ADVANCE_MS)
  onCleanup(() => clearTimeout(timer))
})

function onFocusOut(event: FocusEvent): void {
  const next = event.relatedTarget as Node | null
  if (!next || !(event.currentTarget as HTMLElement).contains(next)) paused.value = false
}

const toneClasses = {
  danger: 'bg-red-50 text-red-900',
  warn: 'bg-amber-50 text-amber-900',
  info: 'bg-weact-50 text-weact-900',
} as const
const iconClasses = {
  danger: 'bg-red-600',
  warn: 'bg-amber-600',
  info: 'bg-weact-600',
} as const
</script>

<template>
  <SubscriptionAlertSource
    v-if="showSubscriptionSource"
    @change="subscriptionAlert = $event"
  />

  <section
    v-if="current"
    role="region"
    aria-label="Alertes du compte"
    class="account-alert-bar relative grid min-h-11 grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-3 overflow-hidden border-b border-line px-4 py-2 text-[13px] md:px-6"
    :class="toneClasses[current.tone]"
    data-testid="account-alert-bar"
    :data-tone="current.tone"
    @mouseenter="paused = true"
    @mouseleave="paused = false"
    @focusin="paused = true"
    @focusout="onFocusOut"
  >
    <span
      class="flex h-[22px] w-[22px] items-center justify-center rounded-full text-white transition-colors"
      :class="iconClasses[current.tone]"
      aria-hidden="true"
    >
      <Ban v-if="current.id === 'ugc-suspension'" class="h-3.5 w-3.5" />
      <Info v-else-if="current.tone === 'info'" class="h-3.5 w-3.5" />
      <AlertTriangle v-else class="h-3.5 w-3.5" />
    </span>

    <!-- Région live stable : le contenu keyé change, la région reste -->
    <div class="min-w-0" :aria-live="announce ? 'polite' : 'off'" aria-atomic="true">
      <div
        :key="current.id"
        class="alert-swap flex min-w-0 flex-col gap-1 md:flex-row md:items-center md:gap-4"
        data-testid="account-alert-content"
      >
        <p class="min-w-0 leading-snug max-md:line-clamp-2">
          <strong class="font-bold">{{ current.title }}</strong>
          {{ current.message }}
        </p>
        <div class="flex shrink-0 items-center gap-3">
          <button
            type="button"
            class="rounded-sm font-bold underline underline-offset-[3px] transition-opacity hover:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:no-underline disabled:opacity-60"
            :disabled="current.actionDisabled"
            data-testid="account-alert-action"
            @click="current.onAction()"
          >
            {{ current.actionLabel }}
          </button>
          <button
            v-if="current.secondaryLabel"
            type="button"
            class="rounded-sm font-medium opacity-80 underline-offset-[3px] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600 focus-visible:ring-offset-2"
            data-testid="account-alert-secondary"
            @click="current.onSecondary?.()"
          >
            {{ current.secondaryLabel }}
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="alerts.length > 1"
      class="flex items-center"
      role="group"
      aria-label="Alertes"
      data-testid="account-alert-dots"
    >
      <button
        v-for="(item, index) in alerts"
        :key="item.id"
        type="button"
        class="alert-dot-hit group grid h-6 w-5 place-items-center focus-visible:outline-none"
        :aria-label="`Alerte ${index + 1} sur ${alerts.length}`"
        :aria-current="index === currentIndex ? 'true' : undefined"
        data-testid="account-alert-dot"
        @click="select(index, true)"
      >
        <span
          class="alert-dot block h-2 rounded-full bg-current group-focus-visible:ring-2 group-focus-visible:ring-weact-600 group-focus-visible:ring-offset-1"
          :class="index === currentIndex ? 'w-[18px] opacity-80' : 'w-2 opacity-25'"
        />
      </button>
      <span class="ml-1 text-[11px] tabular-nums opacity-70" data-testid="account-alert-count">
        {{ currentIndex + 1 }}/{{ alerts.length }}
      </span>
    </div>

    <!-- Trait de progression du défilement automatique (discret) -->
    <span
      v-if="running"
      :key="current.id"
      class="alert-progress absolute bottom-0 left-0 h-0.5 w-full bg-current"
      aria-hidden="true"
      data-testid="account-alert-progress"
    />
  </section>
</template>

<style scoped>
/* Mouvement : ease-out, < 300 ms, uniquement transform / opacity / filter. */
.alert-swap {
  animation: alert-in 240ms cubic-bezier(0.23, 1, 0.32, 1);
}
@keyframes alert-in {
  from { opacity: 0; transform: translateY(6px); filter: blur(2px); }
  to { opacity: 1; transform: none; filter: none; }
}

.alert-dot {
  transition: opacity 160ms cubic-bezier(0.23, 1, 0.32, 1);
}

.alert-progress {
  opacity: 0.25;
  transform-origin: left;
  transform: scaleX(0);
  animation: alert-progress 6000ms linear forwards;
}
@keyframes alert-progress {
  to { transform: scaleX(1); }
}

@media (prefers-reduced-motion: reduce) {
  .alert-swap { animation: none; }
  .alert-dot { transition: none; }
  .alert-progress { display: none; }
}
</style>
