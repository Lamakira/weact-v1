<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, onUnmounted, ref, watch } from 'vue'
import { isAxiosError } from 'axios'
import { RouterLink } from 'vue-router'
import { Check, FileText, Loader2, ShieldCheck, X } from 'lucide-vue-next'
import { useDismissOnDeactivate } from '@/composables/useDismissOnDeactivate'
import { useModalFocusTrap } from '@/composables/useModalFocusTrap'
import { formatApiError as getApiErrorMessage, getApiErrorCode, getApiErrorDetails } from '@/services/errorFormatter'
import { dureeLabel } from '../constants'
import { imageContractApi } from '../services/imageContractApi'
import type {
  ContractArticle,
  ContractSummary,
  GuardianRelation,
  ImageSignature,
  SignatureCodePayload,
  SignatureSubmitOutcome,
} from '../types'
import ImageContractCodeNotice from './ImageContractCodeNotice.vue'
import { SIGNATURE_NAME_INVALID } from '../utils/signatureErrors'

type Step = 'terms' | 'guardian' | 'code'

const CODE_LENGTH = 6
const DEFAULT_RESEND_DELAY = 60
const MIN_NAME_LENGTH = 3
const MIN_GUARDIAN_NAME_LENGTH = 3
const OWN_EMAIL_MESSAGE = "Indiquez l'e-mail de votre parent ou tuteur, pas le vôtre."

const GUARDIAN_RELATIONS: { value: GuardianRelation; label: string }[] = [
  { value: 'mere', label: 'Mère' },
  { value: 'pere', label: 'Père' },
  { value: 'tuteur', label: 'Tuteur' },
]

const ERROR_MESSAGES: Record<string, string> = {
  SIGNATURE_CODE_INVALID: "Ce code n'est pas le bon. Vérifiez-le et réessayez.",
  SIGNATURE_CODE_EXPIRED: 'Ce code a expiré. Demandez-en un nouveau.',
  SIGNATURE_CODE_TOO_MANY_ATTEMPTS: 'Trop de tentatives. Demandez un nouveau code.',
  SIGNATURE_CODE_TOO_SOON: 'Un code vient d\'être envoyé. Patientez un instant avant d\'en demander un autre.',
  SIGNATURE_CODE_SEND_FAILED: "Nous n'avons pas pu envoyer l'e-mail. Réessayez dans un instant.",
}

interface Props {
  isOpen: boolean
  role: 'producer' | 'face'
  summary: ContractSummary
  /** Nom prérempli dans « Votre nom complet ». */
  defaultName?: string
  /** Parcours signé : booking (défaut) ou mission (publication Producteur, confirmation Face). */
  flow?: 'booking' | 'mission'
  /** Booking à accepter (Face) : sert de motif au code de signature. */
  bookingId?: string
  /** Candidature à confirmer (Face, parcours mission) : sert de motif au code de signature. */
  candidatureId?: string
  /** Mission UGC produit seul (flow mission) : les droits ne démarrent qu'à la réception du produit. */
  productOnly?: boolean
  /** Face de moins de 18 ans : le code part à un parent ou tuteur. */
  requiresGuardian?: boolean
  /** E-mail du compte connecté : le parent ne peut pas signer avec la même adresse. */
  accountEmail?: string
  /** Soumission finale (création du booking ou acceptation) avec la signature saisie. */
  submit: (signature: ImageSignature) => Promise<SignatureSubmitOutcome>
}

const props = withDefaults(defineProps<Props>(), {
  defaultName: '',
  flow: 'booking',
  bookingId: undefined,
  candidatureId: undefined,
  productOnly: false,
  requiresGuardian: false,
  accountEmail: '',
})

const emit = defineEmits<{
  close: []
}>()

const isFace = computed(() => props.role === 'face')
const isMission = computed(() => props.flow === 'mission')

const step = ref<Step>('terms')
const name = ref('')
const accepted = ref(false)
const guardianName = ref('')
const guardianRelation = ref<GuardianRelation | ''>('')
const guardianEmail = ref('')
const sentTo = ref('')
const digits = ref<string[]>(Array(CODE_LENGTH).fill(''))
const errorCode = ref<string | null>(null)
const errorMessage = ref<string | null>(null)
const isSending = ref(false)
const isSubmitting = ref(false)
const resendIn = ref(0)

const reading = ref(false)
const articles = ref<ContractArticle[]>([])
const articlesLoading = ref(false)
const articlesError = ref<string | null>(null)

const dialogRef = ref<HTMLDivElement | null>(null)
const readingBodyRef = ref<HTMLDivElement | null>(null)
const backToSignRef = ref<HTMLButtonElement | null>(null)
const readFullRef = ref<HTMLButtonElement | null>(null)
const codeInputs = ref<(HTMLInputElement | null)[]>([])
const { prepareModalFocus, restoreFocus, trapModalFocus } = useModalFocusTrap()

let timer: ReturnType<typeof setInterval> | null = null

const code = computed(() => digits.value.join(''))
const nameValid = computed(() => name.value.trim().length >= MIN_NAME_LENGTH)
const canSend = computed(() => nameValid.value && accepted.value && !isSending.value)
const serverGuardianNameError = ref<string | null>(null)
const serverGuardianEmailError = ref<string | null>(null)

const guardianEmailIsOwn = computed(() => {
  const own = props.accountEmail.trim().toLowerCase()
  return own !== '' && guardianEmail.value.trim().toLowerCase() === own
})
const guardianEmailError = computed(() =>
  guardianEmailIsOwn.value ? OWN_EMAIL_MESSAGE : serverGuardianEmailError.value,
)

const guardianValid = computed(
  () =>
    guardianName.value.trim().length >= MIN_GUARDIAN_NAME_LENGTH
    && !guardianEmailIsOwn.value
    && guardianRelation.value !== ''
    && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(guardianEmail.value.trim()),
)
const canConfirm = computed(() => code.value.length === CODE_LENGTH && !isSubmitting.value)

const stepTitle = computed(() => {
  if (step.value === 'guardian') return 'Un parent doit signer pour vous'
  if (step.value === 'code') return 'Consultez votre e-mail'
  return "Votre contrat d'image"
})

const confirmLabel = computed(() => {
  if (isMission.value) return isFace.value ? 'Signer et confirmer' : 'Signer et publier'
  return isFace.value ? 'Signer et accepter le booking' : 'Signer et envoyer la demande'
})

const noCodeHint = computed(() =>
  isMission.value
    ? 'Sans ce code, votre participation ne peut pas être confirmée.'
    : 'Sans ce code, le booking ne peut pas être accepté.',
)

const displayedError = computed(() => {
  if (!errorCode.value && !errorMessage.value) return null
  return (errorCode.value && ERROR_MESSAGES[errorCode.value]) || errorMessage.value
})

function setError(code: string | null, message: string | null): void {
  errorCode.value = code
  errorMessage.value = message
}

function clearError(): void {
  setError(null, null)
}

function stopTimer(): void {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

function startCountdown(seconds: number): void {
  stopTimer()
  resendIn.value = Math.max(0, Math.floor(seconds))
  if (resendIn.value === 0) return
  timer = setInterval(() => {
    resendIn.value -= 1
    if (resendIn.value <= 0) {
      resendIn.value = 0
      stopTimer()
    }
  }, 1000)
}

function resetState(): void {
  stopTimer()
  step.value = 'terms'
  name.value = props.defaultName
  accepted.value = false
  guardianName.value = ''
  guardianRelation.value = ''
  guardianEmail.value = ''
  sentTo.value = ''
  digits.value = Array(CODE_LENGTH).fill('')
  resendIn.value = 0
  reading.value = false
  isSending.value = false
  isSubmitting.value = false
  clearError()
}

watch(
  () => props.isOpen,
  async (open) => {
    if (open) {
      resetState()
      await prepareModalFocus(dialogRef)
    } else {
      stopTimer()
      restoreFocus()
    }
  },
  { immediate: true },
)

function retryAfterOf(err: unknown): number {
  if (!isAxiosError(err)) return DEFAULT_RESEND_DELAY
  const data = err.response?.data as
    | { retry_after?: number; error?: { retry_after?: number; details?: { retry_after?: number } } }
    | undefined
  const value = data?.error?.retry_after ?? data?.error?.details?.retry_after ?? data?.retry_after
  return typeof value === 'number' ? value : DEFAULT_RESEND_DELAY
}

function focusCodeInput(index: number): void {
  void nextTick(() => codeInputs.value[index]?.focus())
}

async function sendCode(): Promise<void> {
  if (isSending.value) return
  clearError()
  serverGuardianNameError.value = null
  serverGuardianEmailError.value = null
  isSending.value = true

  const guardian = props.requiresGuardian && guardianRelation.value
    ? {
        guardian_name: guardianName.value.trim(),
        guardian_relation: guardianRelation.value,
        guardian_email: guardianEmail.value.trim(),
      }
    : {}

  let payload: SignatureCodePayload
  if (isMission.value) {
    payload = isFace.value
      ? { purpose: 'candidature_confirm', candidature_id: props.candidatureId ?? '', ...guardian }
      : { purpose: 'mission_publish' }
  } else {
    payload = isFace.value
      ? { purpose: 'booking_accept', booking_id: props.bookingId ?? '', ...guardian }
      : { purpose: 'booking_request' }
  }

  try {
    const response = await imageContractApi.requestCode(payload)
    sentTo.value = response.data.sent_to
    digits.value = Array(CODE_LENGTH).fill('')
    step.value = 'code'
    startCountdown(response.data.retry_after ?? DEFAULT_RESEND_DELAY)
    focusCodeInput(0)
  } catch (err) {
    const code = getApiErrorCode(err)
    const details = getApiErrorDetails(err)
    const nameDetail = details.guardian_name?.[0] ?? null
    const emailDetail = details.guardian_email?.[0] ?? null
    if (step.value === 'guardian' && (nameDetail || emailDetail)) {
      serverGuardianNameError.value = nameDetail
      serverGuardianEmailError.value = emailDetail
      return
    }
    setError(code, getApiErrorMessage(err))
    if (code === 'SIGNATURE_CODE_TOO_SOON') startCountdown(retryAfterOf(err))
  } finally {
    isSending.value = false
  }
}

function goNextFromTerms(): void {
  if (!canSend.value) return
  if (isFace.value && props.requiresGuardian) {
    clearError()
    step.value = 'guardian'
    return
  }
  void sendCode()
}

function backToTerms(): void {
  clearError()
  digits.value = Array(CODE_LENGTH).fill('')
  step.value = 'terms'
}

function fillDigits(raw: string, from = 0): void {
  const chars = raw.replace(/\D/g, '').slice(0, CODE_LENGTH - from).split('')
  if (chars.length === 0) return
  const next = [...digits.value]
  chars.forEach((char, offset) => {
    next[from + offset] = char
  })
  digits.value = next
  focusCodeInput(Math.min(from + chars.length, CODE_LENGTH - 1))
}

function onDigitInput(index: number, event: Event): void {
  const input = event.target as HTMLInputElement
  const raw = input.value.replace(/\D/g, '')
  if (raw.length === 0) {
    digits.value = digits.value.map((value, i) => (i === index ? '' : value))
    input.value = ''
    return
  }
  // Plusieurs chiffres d'un coup (saisie rapide, autofill) : on répartit sur les cases.
  fillDigits(raw, index)
  input.value = digits.value[index] ?? ''
}

function onDigitPaste(event: ClipboardEvent): void {
  event.preventDefault()
  fillDigits(event.clipboardData?.getData('text') ?? '', 0)
}

function onDigitKeydown(index: number, event: KeyboardEvent): void {
  if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
    event.preventDefault()
    digits.value = digits.value.map((value, i) => (i === index - 1 ? '' : value))
    focusCodeInput(index - 1)
  } else if (event.key === 'ArrowLeft' && index > 0) {
    focusCodeInput(index - 1)
  } else if (event.key === 'ArrowRight' && index < CODE_LENGTH - 1) {
    focusCodeInput(index + 1)
  } else if (event.key === 'Enter' && canConfirm.value) {
    event.preventDefault()
    void confirm()
  }
}

async function confirm(): Promise<void> {
  if (!canConfirm.value) return
  clearError()
  isSubmitting.value = true
  try {
    const outcome = await props.submit({ name: name.value.trim(), code: code.value })
    if (!outcome.ok) {
      setError(outcome.code ?? null, outcome.message ?? null)
      if (outcome.code === 'SIGNATURE_CODE_INVALID') {
        digits.value = Array(CODE_LENGTH).fill('')
        focusCodeInput(0)
      } else if (
        outcome.code === 'SIGNATURE_CODE_EXPIRED'
        || outcome.code === 'SIGNATURE_CODE_TOO_MANY_ATTEMPTS'
      ) {
        digits.value = Array(CODE_LENGTH).fill('')
      }
    }
  } finally {
    isSubmitting.value = false
  }
}

async function loadArticles(): Promise<void> {
  if (articles.value.length > 0 || articlesLoading.value) return
  articlesLoading.value = true
  articlesError.value = null
  try {
    articles.value = (await imageContractApi.getTemplate()).articles
  } catch {
    articlesError.value = "Le contrat n'a pas pu être chargé. Réessayez dans un instant."
  } finally {
    articlesLoading.value = false
  }
}

async function openReading(): Promise<void> {
  reading.value = true
  await nextTick()
  if (readingBodyRef.value) readingBodyRef.value.scrollTop = 0
  backToSignRef.value?.focus()
  await loadArticles()
}

async function closeReading(): Promise<void> {
  reading.value = false
  await nextTick()
  readFullRef.value?.focus()
}

function requestClose(): void {
  if (isSubmitting.value) return
  emit('close')
}

function handleKeydown(event: KeyboardEvent): void {
  if (!props.isOpen) return
  if (event.key === 'Escape') {
    if (reading.value) void closeReading()
    else requestClose()
    return
  }
  if (event.key === 'Tab') trapModalFocus(event, dialogRef.value)
}

onMounted(() => document.addEventListener('keydown', handleKeydown))
onUnmounted(() => document.removeEventListener('keydown', handleKeydown))
onBeforeUnmount(stopTimer)

// Dialogue téléporté : le fermer si la page hôte est désactivée par <keep-alive>.
useDismissOnDeactivate(() => props.isOpen, () => emit('close'))

const protectedItems = computed(() => [
  'Pas d\'usage politique, religieux, pornographique ou diffamatoire.',
  'Pas de génération ni de modification de votre visage, de votre corps ou de votre voix par intelligence artificielle.',
  'Pas d\'usage pour un autre produit ou un autre projet que celui indiqué.',
  isMission.value && props.productOnly
    ? 'Pas d\'exploitation de votre image avant la réception du produit.'
    : 'Pas d\'exploitation de votre image avant votre paiement.',
])
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="ph-no-capture fixed inset-0 z-[60] bg-black/60 sm:flex sm:items-center sm:justify-center sm:p-4"
      data-testid="image-contract-dialog"
    >
      <div
        ref="dialogRef"
        :class="reading ? 'overflow-hidden' : 'overflow-y-auto'"
        class="flex h-full w-full flex-col bg-white shadow-2xl focus:outline-none sm:h-auto sm:max-h-[92vh] sm:max-w-4xl sm:rounded-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="image-contract-title"
        tabindex="-1"
      >
        <header class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-gray-100 bg-white px-5 py-4">
          <h2 id="image-contract-title" class="text-lg font-semibold text-gray-900" data-testid="image-contract-title">
            {{ stepTitle }}
          </h2>
          <button
            type="button"
            class="rounded-full p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
            aria-label="Fermer"
            :disabled="isSubmitting"
            data-testid="image-contract-close"
            @click="requestClose"
          >
            <X :size="20" />
          </button>
        </header>

        <div v-show="!reading" class="grid flex-1 gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_340px]">
          <!-- Colonne gauche : contenu du contrat -->
          <div :class="['space-y-5', step === 'terms' ? '' : 'hidden lg:block']" data-testid="image-contract-content">
            <dl class="divide-y divide-gray-100 rounded-xl border border-gray-200 text-sm" data-testid="image-contract-summary">
              <div class="flex justify-between gap-4 px-4 py-2.5">
                <dt class="text-gray-500">Projet</dt>
                <dd class="text-right font-medium text-gray-900" data-testid="summary-projet">{{ summary.projet }}</dd>
              </div>
              <div class="flex justify-between gap-4 px-4 py-2.5">
                <dt class="text-gray-500">Finalité</dt>
                <dd class="text-right font-medium text-gray-900" data-testid="summary-finalite">{{ summary.finalite }}</dd>
              </div>
              <div class="flex justify-between gap-4 px-4 py-2.5">
                <dt class="text-gray-500">Supports</dt>
                <dd class="text-right font-medium text-gray-900" data-testid="summary-supports">{{ summary.supports.join(', ') }}</dd>
              </div>
              <div class="flex justify-between gap-4 px-4 py-2.5">
                <dt class="text-gray-500">Territoire</dt>
                <dd class="text-right font-medium text-gray-900" data-testid="summary-territoire">{{ summary.territoire }}</dd>
              </div>
              <div class="flex justify-between gap-4 px-4 py-2.5">
                <dt class="text-gray-500">Durée</dt>
                <dd class="text-right font-medium text-gray-900" data-testid="summary-duree">{{ dureeLabel(summary.dureeAnnees) }}</dd>
              </div>
              <div class="flex justify-between gap-4 px-4 py-2.5">
                <dt class="text-gray-500">Contrepartie</dt>
                <dd class="text-right font-medium text-gray-900" data-testid="summary-contrepartie">{{ summary.contrepartie }}</dd>
              </div>
            </dl>

            <div>
              <button
                ref="readFullRef"
                type="button"
                class="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border-2 border-weact-500 px-4 py-2 text-sm font-semibold text-weact-700 no-underline transition-colors hover:bg-weact-50"
                data-testid="image-contract-read-full"
                @click="openReading"
              >
                <FileText :size="18" aria-hidden="true" />
                Lire le contrat complet
              </button>
            </div>

            <div v-if="isFace" class="rounded-xl bg-gray-50 p-4" data-testid="image-contract-protected">
              <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-800">
                <ShieldCheck :size="16" class="text-weact-600" aria-hidden="true" />
                Ce qui reste protégé
              </p>
              <ul class="space-y-1.5 text-sm text-gray-600">
                <li v-for="item in protectedItems" :key="item" class="flex gap-2">
                  <Check :size="14" class="mt-0.5 shrink-0 text-weact-600" aria-hidden="true" />
                  <span>{{ item }}</span>
                </li>
              </ul>
            </div>
          </div>

          <!-- Colonne droite : encadré orange + actions -->
          <div class="space-y-4" data-testid="image-contract-actions">
            <p
              v-if="displayedError"
              class="text-sm text-red-600"
              role="alert"
              data-testid="image-contract-error"
            >
              <template v-if="errorCode === 'BIRTH_DATE_REQUIRED'">
                Renseignez votre date de naissance dans
                <RouterLink to="/face/profile" class="underline">votre profil</RouterLink>
                avant de signer.
              </template>
              <template v-else>{{ displayedError }}</template>
            </p>
            <button
              v-if="errorCode === SIGNATURE_NAME_INVALID && step === 'code'"
              type="button"
              class="text-sm font-medium text-weact-600 underline underline-offset-2 hover:text-weact-700"
              data-testid="image-contract-edit-name"
              @click="backToTerms"
            >
              Modifier mon nom
            </button>

            <!-- Étape 1 -->
            <template v-if="step === 'terms'">
              <div>
                <label for="image-contract-name" class="mb-1 block text-sm font-medium text-gray-700">Votre nom complet</label>
                <input
                  id="image-contract-name"
                  v-model="name"
                  type="text"
                  autocomplete="name"
                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:border-weact-500 focus:outline-none focus:ring-1 focus:ring-weact-500"
                  data-testid="image-contract-name"
                />
                <p v-if="name && !nameValid" class="mt-1 text-xs text-red-600" data-testid="image-contract-name-error">
                  Indiquez votre nom complet (3 caractères minimum).
                </p>
              </div>

              <label class="flex cursor-pointer items-start gap-2.5 text-sm text-gray-700">
                <input
                  v-model="accepted"
                  type="checkbox"
                  class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300"
                  data-testid="image-contract-accept"
                />
                <span>J'ai lu le contrat et j'accepte de le signer.</span>
              </label>

              <ImageContractCodeNotice />


              <button
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-weact-500 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-weact-600 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canSend"
                data-testid="image-contract-send"
                @click="goNextFromTerms"
              >
                <Loader2 v-if="isSending" :size="16" class="animate-spin" aria-hidden="true" />
                {{ isFace && requiresGuardian ? 'Continuer' : 'Recevoir mon code par e-mail' }}
              </button>
            </template>

            <!-- Étape mineure (Face) : avant le code -->
            <template v-else-if="step === 'guardian'">
              <p class="text-sm text-gray-600" data-testid="image-contract-guardian-text">
                Vous avez moins de 18 ans : votre parent ou tuteur doit signer cette autorisation avec vous.
              </p>

              <div>
                <label for="guardian-name" class="mb-1 block text-sm font-medium text-gray-700">Nom du parent ou tuteur</label>
                <input
                  id="guardian-name"
                  v-model="guardianName"
                  type="text"
                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:border-weact-500 focus:outline-none focus:ring-1 focus:ring-weact-500"
                  data-testid="guardian-name"
                  @input="serverGuardianNameError = null"
                />
                <p v-if="serverGuardianNameError" class="mt-1 text-xs text-red-600" data-testid="guardian-name-error">
                  {{ serverGuardianNameError }}
                </p>
              </div>

              <fieldset>
                <legend class="mb-1 text-sm font-medium text-gray-700">Lien avec vous</legend>
                <div class="grid grid-cols-3 gap-2">
                  <button
                    v-for="relation in GUARDIAN_RELATIONS"
                    :key="relation.value"
                    type="button"
                    :class="[
                      'rounded-lg border px-2 py-2 text-sm font-medium transition-colors',
                      guardianRelation === relation.value
                        ? 'border-weact-500 bg-weact-50 text-weact-700'
                        : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300',
                    ]"
                    :aria-pressed="guardianRelation === relation.value"
                    :data-testid="`guardian-relation-${relation.value}`"
                    @click="guardianRelation = relation.value"
                  >
                    {{ relation.label }}
                  </button>
                </div>
              </fieldset>

              <div>
                <label for="guardian-email" class="mb-1 block text-sm font-medium text-gray-700">E-mail du parent ou tuteur</label>
                <input
                  id="guardian-email"
                  v-model="guardianEmail"
                  type="email"
                  autocomplete="off"
                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:border-weact-500 focus:outline-none focus:ring-1 focus:ring-weact-500"
                  data-testid="guardian-email"
                  @input="serverGuardianEmailError = null"
                />
                <p v-if="guardianEmailError" class="mt-1 text-xs text-red-600" data-testid="guardian-email-error">
                  {{ guardianEmailError }}
                </p>
              </div>

              <ImageContractCodeNotice
                title="Le code part à votre parent ou tuteur"
                :text="`Il reçoit le contrat et le code par e-mail. ${noCodeHint}`"
              />


              <button
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-weact-500 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-weact-600 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!guardianValid || isSending"
                data-testid="guardian-send"
                @click="sendCode"
              >
                <Loader2 v-if="isSending" :size="16" class="animate-spin" aria-hidden="true" />
                Envoyer le code à mon parent
              </button>
              <button
                type="button"
                class="w-full text-sm text-gray-500 underline underline-offset-2 hover:text-gray-700"
                data-testid="guardian-back"
                @click="backToTerms"
              >
                Retour
              </button>
            </template>

            <!-- Étape 2 : saisie du code -->
            <template v-else>
              <p class="text-sm text-gray-700" data-testid="image-contract-sent-to">
                Nous avons envoyé un code à 6 chiffres à <strong>{{ sentTo }}</strong>.
              </p>

              <div class="flex justify-between gap-2" data-testid="image-contract-code-inputs">
                <input
                  v-for="(_, index) in digits"
                  :key="index"
                  :ref="(el) => { codeInputs[index] = el as HTMLInputElement | null }"
                  :value="digits[index]"
                  type="text"
                  inputmode="numeric"
                  autocomplete="one-time-code"
                  maxlength="6"
                  :aria-label="`Chiffre ${index + 1} du code`"
                  class="h-12 w-11 rounded-lg border border-gray-300 text-center text-xl font-semibold text-gray-900 focus:border-weact-500 focus:outline-none focus:ring-2 focus:ring-weact-500/30"
                  :data-testid="`code-digit-${index}`"
                  @input="onDigitInput(index, $event)"
                  @paste="onDigitPaste"
                  @keydown="onDigitKeydown(index, $event)"
                />
              </div>

              <p class="text-xs text-gray-500" data-testid="image-contract-expiry">Ce code expire dans 10 minutes.</p>


              <button
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-weact-500 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-weact-600 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canConfirm"
                data-testid="image-contract-confirm"
                @click="confirm"
              >
                <Loader2 v-if="isSubmitting" :size="16" class="animate-spin" aria-hidden="true" />
                {{ confirmLabel }}
              </button>

              <p class="text-xs text-gray-500" data-testid="image-contract-spam-hint">
                Vous ne le trouvez pas ? Regardez dans vos spams, ou renvoyez le code.
              </p>
              <button
                type="button"
                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="resendIn > 0 || isSending || isSubmitting"
                data-testid="image-contract-resend"
                @click="sendCode"
              >
                {{ resendIn > 0 ? `Renvoyer le code (${resendIn} s)` : 'Renvoyer le code' }}
              </button>

              <p v-if="isFace" class="text-xs font-medium text-gray-600" data-testid="image-contract-no-code-hint">
                {{ noCodeHint }}
              </p>
            </template>
          </div>
        </div>

        <!-- Vue de lecture : tout le contrat, en-tête et pied fixes -->
        <template v-if="reading">
          <div ref="readingBodyRef" class="min-h-0 flex-1 overflow-y-auto p-5" data-testid="image-contract-articles">
            <p v-if="articlesLoading" class="text-sm text-gray-500">Chargement du contrat...</p>
            <p v-else-if="articlesError" class="text-sm text-red-600" role="alert">{{ articlesError }}</p>
            <div class="gap-x-8 lg:columns-2" data-testid="image-contract-articles-columns">
              <article
                v-for="(article, index) in articles"
                :key="article.title"
                class="mb-5 break-inside-avoid text-sm text-gray-700"
              >
                <h3 class="font-semibold text-gray-900">Article {{ index + 1 }}. {{ article.title }}</h3>
                <p class="mt-0.5">{{ article.body }}</p>
                <ul v-if="article.bullets?.length" class="mt-1 list-disc space-y-0.5 pl-5">
                  <li v-for="bullet in article.bullets" :key="bullet">{{ bullet }}</li>
                </ul>
              </article>
            </div>
          </div>
          <footer class="flex shrink-0 items-center justify-between gap-3 border-t border-gray-100 bg-white px-5 py-3">
            <span class="text-sm text-gray-500" data-testid="image-contract-articles-count">
              <template v-if="articles.length > 0">{{ articles.length }} {{ articles.length > 1 ? 'articles' : 'article' }}</template>
            </span>
            <button
              ref="backToSignRef"
              type="button"
              class="rounded-lg bg-weact-500 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-weact-600"
              data-testid="image-contract-back-to-sign"
              @click="closeReading"
            >
              Retour à la signature
            </button>
          </footer>
        </template>
      </div>
    </div>
  </Teleport>
</template>
