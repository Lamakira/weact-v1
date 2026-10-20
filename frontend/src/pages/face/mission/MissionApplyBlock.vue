<script setup lang="ts">
/**
 * MissionApplyBlock
 *
 * Zone d'action « Postuler » et ses états AVANT candidature (email non vérifié / abonnement
 * requis / contexte genre indisponible / genre incompatible / mission fermée).
 *
 * Une fois la candidature posée, la page bascule sur FaceMissionHub (lot C5) : statut honnête,
 * action principale et « Et ensuite ? » y vivent désormais.
 *
 * Monté à DEUX emplacements selon le breakpoint sans dupliquer la logique :
 *  - desktop : dans la carte d'action de la sidebar sticky ;
 *  - mobile  : dans la barre d'action fixée en bas.
 *
 * Composant 100% présentationnel : la logique reste dans la page parente, les actions remontent via emits.
 */
import { AlertCircle, Loader2, Mail, ShieldAlert } from 'lucide-vue-next'
import type { Mission } from '@/features/mission/types'

defineProps<{
  mission: Mission
  canApply: boolean
  isSubscriptionGated: boolean
  isResendingVerification: boolean
  isGenderContextUnknown: boolean
  isRefreshingGenderContext: boolean
  genderContextMessage: string
  isGenderMismatch: boolean
  genderMismatchMessage: string
}>()

defineEmits<{
  apply: []
  'resend-verification': []
  'view-pricing': []
}>()
</script>

<template>
  <div>
    <!-- State 1: Email not verified -->
    <div
      v-if="!canApply && mission.is_accepting_candidatures"
      class="rounded-lg border border-amber-200 bg-amber-50 p-3 min-[376px]:p-4"
      data-testid="email-verification-apply-block"
    >
      <div class="flex flex-col gap-3">
        <div class="flex items-center gap-3">
          <div class="flex-shrink-0 w-9 h-9 min-[376px]:w-10 min-[376px]:h-10 rounded-full bg-amber-100 flex items-center justify-center">
            <ShieldAlert class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-amber-600" />
          </div>
          <div>
            <p class="text-xs min-[376px]:text-sm font-medium text-amber-800">Vérification email requise</p>
            <p class="text-[10px] min-[376px]:text-xs text-amber-700">Vous devez vérifier votre email pour postuler.</p>
          </div>
        </div>
        <button
          type="button"
          :disabled="isResendingVerification"
          class="w-full inline-flex items-center justify-center gap-2 px-3 min-[376px]:px-4 py-2 text-xs min-[376px]:text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          @click="$emit('resend-verification')"
        >
          <Mail v-if="!isResendingVerification" class="h-4 w-4" />
          <Loader2 v-else class="h-4 w-4 animate-spin" />
          {{ isResendingVerification ? 'Envoi...' : "Renvoyer l'email" }}
        </button>
      </div>
    </div>

    <!-- State 1b: Candidatures réservées aux Faces abonnées (missions standard, tier Free) -->
    <div
      v-else-if="mission.is_accepting_candidatures && isSubscriptionGated"
      class="rounded-[10px] bg-white p-3 ring-1 ring-[#E5E5E5] min-[376px]:p-4"
      data-testid="subscription-required-block"
    >
      <div class="flex flex-col gap-3">
        <p class="text-xs min-[376px]:text-sm text-[#525252]">
          Les candidatures sont réservées aux Faces abonnées. Les Producteurs peuvent toujours vous réserver directement.
        </p>
        <button
          type="button"
          class="w-full inline-flex items-center justify-center gap-2 rounded-[10px] bg-[#167686] px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#126676]"
          data-testid="view-pricing-button"
          @click="$emit('view-pricing')"
        >
          Voir les abonnements
        </button>
      </div>
    </div>

    <!-- State 2: Gender context unavailable -->
    <div
      v-else-if="mission.is_accepting_candidatures && isGenderContextUnknown"
      class="rounded-lg border border-amber-200 bg-amber-50 p-3 min-[376px]:p-4"
      data-testid="gender-context-block"
    >
      <div class="flex flex-col gap-3">
        <div class="flex items-center gap-3">
          <div class="flex-shrink-0 w-9 h-9 min-[376px]:w-10 min-[376px]:h-10 rounded-full bg-amber-100 flex items-center justify-center">
            <Loader2
              v-if="isRefreshingGenderContext"
              class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-amber-600 animate-spin"
            />
            <ShieldAlert
              v-else
              class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-amber-600"
            />
          </div>
          <div>
            <p class="text-xs min-[376px]:text-sm font-medium text-amber-800">Validation du profil requise</p>
            <p
              id="gender-context-message"
              class="text-[10px] min-[376px]:text-xs text-amber-700"
            >
              {{ genderContextMessage }}
            </p>
          </div>
        </div>
        <button
          type="button"
          disabled
          aria-describedby="gender-context-message"
          class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-white opacity-50 cursor-not-allowed"
          data-testid="apply-button-disabled"
        >
          Postuler à cette mission
        </button>
      </div>
    </div>

    <!-- State 3: Gender mismatch -->
    <div
      v-else-if="mission.is_accepting_candidatures && isGenderMismatch"
      class="rounded-lg border border-amber-200 bg-amber-50 p-3 min-[376px]:p-4"
      data-testid="gender-mismatch-block"
    >
      <div class="flex flex-col gap-3">
        <div class="flex items-center gap-3">
          <div class="flex-shrink-0 w-9 h-9 min-[376px]:w-10 min-[376px]:h-10 rounded-full bg-amber-100 flex items-center justify-center">
            <ShieldAlert class="h-4 w-4 min-[376px]:h-5 min-[376px]:w-5 text-amber-600" />
          </div>
          <div>
            <p class="text-xs min-[376px]:text-sm font-medium text-amber-800">Candidature non autorisée</p>
            <p
              id="gender-mismatch-message"
              class="text-[10px] min-[376px]:text-xs text-amber-700"
            >
              {{ genderMismatchMessage }}
            </p>
          </div>
        </div>
        <button
          type="button"
          disabled
          aria-describedby="gender-mismatch-message"
          class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-white opacity-50 cursor-not-allowed"
          data-testid="apply-button-disabled"
        >
          Postuler à cette mission
        </button>
      </div>
    </div>

    <!-- State 4: Can Apply (UGC compris — postule comme un standard, 8-3 D-8.3.e) -->
    <div v-else-if="mission.is_accepting_candidatures">
      <button
        type="button"
        class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#146c7a]"
        @click="$emit('apply')"
      >
        Postuler à cette mission
      </button>
      <p class="mt-2.5 text-center text-xs text-gray-400">
        Candidature gratuite · Réponse du producteur sous 72 h après la clôture des candidatures
      </p>
    </div>

    <!-- State 5: Mission Closed -->
    <div
      v-else-if="!mission.is_accepting_candidatures"
      class="flex items-center justify-center gap-2 rounded-lg border border-muted bg-muted/50 px-6 min-[376px]:px-8 py-3 text-xs min-[376px]:text-sm text-muted-foreground"
    >
      <AlertCircle class="h-5 w-5" />
      <span>Les candidatures sont fermées pour cette mission</span>
    </div>
  </div>
</template>
