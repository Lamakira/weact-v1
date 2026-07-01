<script setup lang="ts">
/**
 * MissionApplyBlock
 *
 * Zone d'action « Postuler » + tous ses états (déjà postulé / suivi tracking /
 * email non vérifié / contexte genre indisponible / genre incompatible /
 * reconfirmation UGC / mission fermée).
 *
 * Extrait de FaceMissionDetailPage pour être monté à DEUX emplacements selon le
 * breakpoint sans dupliquer la logique :
 *  - desktop : dans la carte d'action de la sidebar sticky ;
 *  - mobile  : dans la barre d'action fixée en bas (CTA) ou inline (post-candidature).
 *
 * Composant 100% présentationnel : aucune donnée propre. Toute la logique reste
 * dans la page parente (props named-identiques aux locales de la page → markup
 * des états conservé à l'identique), les actions remontent via emits.
 */
import {
  AlertCircle,
  CheckCircle,
  Loader2,
  Mail,
  ShieldAlert,
  XCircle,
} from 'lucide-vue-next'
import { UgcFaceTrackingCard } from '@/components/ugc'
import type { Shipment, UgcUploadProgress } from '@/components/ugc'
import type { Mission, MissionCandidature } from '@/features/mission/types'

defineProps<{
  mission: Mission
  candidature: MissionCandidature | null
  hasApplied: boolean
  showFaceTrackingCard: boolean
  candidatureShipment: Shipment | null
  ugcTrackingStep: number
  isSubmittingReceipt: boolean
  isUploadingDeliverable: boolean
  uploadProgress: UgcUploadProgress | null
  ugcEngaged: boolean
  canCancelCandidature: boolean
  canReconfirmUgc: boolean
  isReconfirming: boolean
  isCancelling: boolean
  canApply: boolean
  isResendingVerification: boolean
  isGenderContextUnknown: boolean
  isRefreshingGenderContext: boolean
  genderContextMessage: string
  isGenderMismatch: boolean
  genderMismatchMessage: string
}>()

defineEmits<{
  apply: []
  cancel: []
  reconfirm: []
  'resend-verification': []
  'confirm-receipt': []
  upload: [file: File]
}>()
</script>

<template>
  <div>
    <!-- State 1: Already Applied (UGC engaged: dedicated banner, no CTA, no cancel) -->
    <div v-if="hasApplied" class="space-y-3">
      <!-- Carte de suivi Face (3.4, D-3.4.f) — remplace le bandeau dès que le produit est expédié -->
      <UgcFaceTrackingCard
        v-if="showFaceTrackingCard && candidatureShipment"
        :shipment="candidatureShipment"
        :current="ugcTrackingStep"
        :is-submitting="isSubmittingReceipt"
        :is-uploading="isUploadingDeliverable"
        :upload-progress="uploadProgress"
        :deliverables="candidature?.deliverables ?? []"
        @confirm-receipt="$emit('confirm-receipt')"
        @upload="(file: File) => $emit('upload', file)"
      />
      <div
        v-else-if="ugcEngaged"
        class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-6 min-[376px]:px-8 py-4 text-green-700"
        data-testid="ugc-engaged-banner"
      >
        <CheckCircle class="h-5 w-5 shrink-0 mt-0.5" aria-hidden="true" />
        <p class="text-xs min-[376px]:text-sm font-medium">
          Mission acceptée — le producteur va expédier votre produit. Les chronos démarreront à la réception.
        </p>
      </div>
      <div
        v-else
        class="flex items-center justify-center gap-2 rounded-lg border px-6 min-[376px]:px-8 py-3"
        :class="canCancelCandidature
          ? 'border-green-200 bg-green-50 text-green-700'
          : candidature?.status === 'cancelled'
            ? 'border-gray-200 bg-gray-50 text-gray-600'
            : 'border-green-200 bg-green-50 text-green-700'"
      >
        <CheckCircle v-if="candidature?.status !== 'cancelled'" class="h-5 w-5" />
        <XCircle v-else class="h-5 w-5" />
        <span class="text-xs min-[376px]:text-sm font-medium">
          {{ candidature?.status === 'cancelled' ? 'Candidature annulée' : 'Candidature envoyée' }}
        </span>
        <span class="text-xs opacity-75">({{ candidature?.status_label }})</span>
      </div>
      <button
        v-if="canReconfirmUgc"
        type="button"
        data-testid="ugc-reconfirm-btn"
        class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="isReconfirming"
        @click="$emit('reconfirm')"
      >
        <Loader2 v-if="isReconfirming" class="h-4 w-4 animate-spin" />
        <CheckCircle v-else class="h-4 w-4" />
        {{ isReconfirming ? 'Confirmation...' : 'Reconfirmer ma participation' }}
      </button>
      <button
        v-if="canCancelCandidature"
        type="button"
        class="w-full inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-6 min-[376px]:px-8 py-2.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="isCancelling"
        @click="$emit('cancel')"
      >
        <Loader2 v-if="isCancelling" class="h-4 w-4 animate-spin" />
        <XCircle v-else class="h-4 w-4" />
        {{ isCancelling ? 'Annulation...' : 'Annuler ma candidature' }}
      </button>
    </div>

    <!-- State 2: Email not verified -->
    <div
      v-else-if="!canApply && mission.is_accepting_candidatures"
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

    <!-- State 3: Gender context unavailable -->
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

    <!-- State 4: Gender mismatch -->
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

    <!-- State 5: Can Apply (UGC compris — postule comme un standard, 8-3 D-8.3.e) -->
    <div v-else-if="mission.is_accepting_candidatures">
      <button
        type="button"
        class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#146c7a]"
        @click="$emit('apply')"
      >
        Postuler à cette mission
      </button>
      <p class="mt-2.5 text-center text-xs text-gray-400">
        Candidature gratuite · réponse du producteur sous 72h
      </p>
    </div>

    <!-- State 6: Mission Closed -->
    <div
      v-else-if="!mission.is_accepting_candidatures"
      class="flex items-center justify-center gap-2 rounded-lg border border-muted bg-muted/50 px-6 min-[376px]:px-8 py-3 text-xs min-[376px]:text-sm text-muted-foreground"
    >
      <AlertCircle class="h-5 w-5" />
      <span>Les candidatures sont fermées pour cette mission</span>
    </div>
  </div>
</template>
