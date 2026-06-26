<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { missionSchema } from '../schemas/mission'
import { useMissionCreate } from '../composables/useMissionCreate'
import {
  MISSION_DURATION_PRESETS,
  formatCustomDuration,
  parseDurationToPreset,
} from '../constants/missionDuration'
import { BENIN_CITY_OPTIONS } from '@/shared/constants/beninCities'
import {
  MissionType,
  MissionGender,
  getMissionTypeOptions,
  getMissionGenderOptions,
  type Mission,
  type CreateMissionData,
} from '../types'
import { Button } from '@/components/ui/button'
import { FloatingField, FloatingTextarea, FloatingSelect } from '@/components/ui/form'
import UgcDotationFields from './UgcDotationFields.vue'
import { CommissionBreakdown, computeUgcCommission, type UgcCompensationType } from '@/components/ugc'
import {
  Film,
  Users,
  MapPin,
  Clock,
  Wallet,
  Calendar,
  UserCircle,
  AlertCircle,
  Info,
  Loader2,
  Send,
  Save,
  ShieldAlert,
  Mail,
  Type,
  AlignLeft,
} from 'lucide-vue-next'
import { authApi } from '@/features/auth/services/authApi'
import { useToast } from '@/composables/useToast'

const props = withDefaults(defineProps<{
  mode?: 'create' | 'edit'
  initialValues?: Partial<CreateMissionData>
  isSubmitting?: boolean
}>(), {
  mode: 'create',
  isSubmitting: false
})

const emit = defineEmits<{
  success: [mission: Mission]
  cancel: []
  submit: [data: CreateMissionData]
}>()

const { createMission, isSubmitting: isCreating, error, errorCode } = useMissionCreate()
const isProcessing = computed(() => props.isSubmitting || isCreating.value)
const toast = useToast()

// Email verification state
const isResendingVerification = ref(false)
const isEmailNotVerified = computed(() => errorCode.value === 'EMAIL_NOT_VERIFIED')

/**
 * Resend verification email
 */
async function handleResendVerification(): Promise<void> {
  isResendingVerification.value = true
  try {
    const result = await authApi.resendVerificationEmail()
    if (result.sent) {
      toast.success('Un email de vérification a été envoyé.')
    } else if (result.verified) {
      toast.success('Votre email est déjà vérifié. Vous pouvez réessayer.')
    }
  } catch (err) {
    if ((err as { response?: { status?: number } })?.response?.status === 429) {
      toast.warning('Veuillez patienter avant de renvoyer un email.')
    } else {
      toast.error("Impossible d'envoyer l'email. Veuillez réessayer.")
    }
  } finally {
    isResendingVerification.value = false
  }
}

const missionTypeOptions =
  props.mode === 'create'
    ? [...getMissionTypeOptions(), { value: 'ugc', label: 'UGC' }]
    : getMissionTypeOptions()
const genderOptions = getMissionGenderOptions()

// Duration preset state
const initialDuree = props.initialValues?.duree
  ? parseDurationToPreset(props.initialValues.duree)
  : { preset: MISSION_DURATION_PRESETS[0]!.value }
const selectedDureePreset = ref<string>(initialDuree.preset)
const customDureeJours = ref<number>(initialDuree.customDays ?? 6)
const customDureeError = ref<string>('')
const isLegacyDuree = ref(initialDuree.isLegacy === true)

const { handleSubmit, setFieldError } = useForm({
  validationSchema: toTypedSchema(missionSchema),
  initialValues: {
    titre: props.initialValues?.titre ?? '',
    description: props.initialValues?.description ?? '',
    date_tournage: props.initialValues?.date_tournage ?? '',
    profil_recherche: props.initialValues?.profil_recherche ?? '',
    budget: props.initialValues?.budget ?? undefined,
    date_limite_candidature: props.initialValues?.date_limite_candidature ?? '',
    nombre_faces_voulu: props.initialValues?.nombre_faces_voulu ?? 1,
    type_mission: props.initialValues?.type_mission ?? MissionType.PUBLICITE,
    type_mission_autre: props.initialValues?.type_mission_autre ?? '',
    genre_voulu: props.initialValues?.genre_voulu ?? MissionGender.TOUS,
    lieu: props.initialValues?.lieu ?? '',
    duree: props.initialValues?.duree ?? MISSION_DURATION_PRESETS[0]!.value,
    type_compensation: props.initialValues?.type_compensation ?? 'product',
    nom_produit: props.initialValues?.nom_produit ?? '',
    valeur_produit: props.initialValues?.valeur_produit ?? undefined,
    nombre_videos: props.initialValues?.nombre_videos ?? undefined,
    montant_remuneration: props.initialValues?.montant_remuneration ?? undefined,
  },
})

const { value: titre, errorMessage: titreError } = useField<string>('titre')
const { value: type_mission, errorMessage: typeMissionError } = useField<string>('type_mission')
const { value: type_mission_autre, errorMessage: typeMissionAutreError } = useField<string>('type_mission_autre')
const { value: description, errorMessage: descriptionError } = useField<string>('description')
const { value: profil_recherche, errorMessage: profilRechercheError } =
  useField<string>('profil_recherche')
const { value: budget, errorMessage: budgetError } = useField<number>('budget')
const { value: lieu, errorMessage: lieuError } = useField<string>('lieu')
const { value: duree, errorMessage: dureeError } = useField<string>('duree')
const { value: genre_voulu, errorMessage: genreVouluError } = useField<string>('genre_voulu')
const { value: nombre_faces_voulu, errorMessage: nombreFacesError } =
  useField<number>('nombre_faces_voulu')
const { value: date_limite_candidature, errorMessage: dateLimiteError } =
  useField<string>('date_limite_candidature')
const { value: date_tournage, errorMessage: dateTournageError } = useField<string>('date_tournage')

// UGC dotation fields (rendus uniquement quand type_mission === 'ugc')
const { value: type_compensation, errorMessage: typeCompensationError } =
  useField<UgcCompensationType>('type_compensation')
const { value: nom_produit, errorMessage: nomProduitError } = useField<string>('nom_produit')
const { value: valeur_produit, errorMessage: valeurProduitError } =
  useField<number | string | undefined>('valeur_produit')
const { value: nombre_videos, errorMessage: nombreVideosError } =
  useField<number | string | undefined>('nombre_videos')
const { value: montant_remuneration, errorMessage: montantRemunerationError } =
  useField<number | string | undefined>('montant_remuneration')

const isUgc = computed(() => type_mission.value === 'ugc')
const ugcCommission = computed(() => computeUgcCommission(Number(valeur_produit.value) || 0))
const submitLabel = computed(() => {
  if (props.mode === 'edit') return 'Enregistrer les modifications'
  return isUgc.value
    ? `Payer la commission · ${ugcCommission.value.toLocaleString('fr-FR')} FCFA`
    : 'Publier la mission'
})

// Sync duration preset / custom days → duree field
watch([selectedDureePreset, customDureeJours], () => {
  // Clear legacy flag on first user interaction — preserve original value until then
  if (isLegacyDuree.value) {
    isLegacyDuree.value = false
    return
  }
  if (selectedDureePreset.value === 'custom') {
    const days = Number(customDureeJours.value)
    if (!Number.isInteger(days) || days <= 5) {
      customDureeError.value = 'Entrez un nombre de jours entier supérieur à 5'
      return
    }
    customDureeError.value = ''
    duree.value = formatCustomDuration(days)
  } else {
    customDureeError.value = ''
    duree.value = selectedDureePreset.value
  }
})

const apiError = computed(() => error.value)

function formatCurrency(amount: number): string {
  return (
    new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'XOF',
      currencyDisplay: 'code',
    })
      .format(amount)
      .replace('XOF', '')
      .trim() + ' XOF'
  )
}

const pricingHint = computed(() => {
  const b = Number(budget.value) || 0
  const n = Number(nombre_faces_voulu.value) || 0
  if (b <= 0 || n <= 0) return null
  const sousTotal = b * n
  const commission = Math.round(sousTotal * 0.10)
  const total = sousTotal + commission
  return { total, n }
})

const onSubmit = handleSubmit(async (values) => {
  // Base payload WITHOUT budget ni champs de tournage (ajoutés conditionnellement ci-dessous —
  // budget dérivé serveur + date/lieu/durée masqués pour l'UGC, D-1.4.d / D-8.1.e)
  const data: CreateMissionData = {
    titre: values.titre,
    description: values.description,
    profil_recherche: values.profil_recherche,
    date_limite_candidature: values.date_limite_candidature,
    nombre_faces_voulu: values.nombre_faces_voulu,
    type_mission: values.type_mission as CreateMissionData['type_mission'],
    type_mission_autre: values.type_mission_autre || undefined,
    genre_voulu: values.genre_voulu as CreateMissionData['genre_voulu'],
  }

  if (values.type_mission === 'ugc') {
    data.type_compensation = values.type_compensation as 'product' | 'hybrid'
    data.nom_produit = values.nom_produit?.trim()
    data.valeur_produit = Number(values.valeur_produit)
    if (values.type_compensation === 'hybrid') {
      data.nombre_videos = Number(values.nombre_videos)
      data.montant_remuneration = Number(values.montant_remuneration)
    }
  } else {
    data.budget = values.budget as number // Validated by schema before submit
    data.date_tournage = values.date_tournage
    data.lieu = values.lieu
    data.duree = values.duree
  }

  // In edit mode, emit the data to parent for handling
  if (props.mode === 'edit') {
    emit('submit', data)
    return
  }

  // In create mode, handle the API call directly
  const result = await createMission(data)

  if (result.success && result.data) {
    emit('success', result.data)
  } else if (result.errors) {
    const validFields = [
      'titre',
      'description',
      'date_tournage',
      'profil_recherche',
      'budget',
      'date_limite_candidature',
      'nombre_faces_voulu',
      'type_mission',
      'type_mission_autre',
      'genre_voulu',
      'lieu',
      'duree',
      'type_compensation',
      'nom_produit',
      'valeur_produit',
      'nombre_videos',
      'montant_remuneration',
    ] as const
    type ValidField = (typeof validFields)[number]

    Object.entries(result.errors).forEach(([field, messages]) => {
      if (messages && messages.length > 0 && validFields.includes(field as ValidField)) {
        setFieldError(field as ValidField, messages[0])
      }
    })
  }
})

const sectionClasses = 'bg-white rounded-2xl border border-gray-100 p-6 mb-6'
</script>

<template>
  <form @submit.prevent="onSubmit" class="pb-24" data-testid="mission-form">
    <fieldset :disabled="isSubmitting" class="contents">

    <!-- Email Not Verified Error -->
    <div
      v-if="isEmailNotVerified"
      class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4"
      data-testid="email-not-verified-alert"
    >
      <div class="flex items-start gap-3">
        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
          <ShieldAlert class="h-5 w-5 text-amber-600" />
        </div>
        <div class="flex-1">
          <h4 class="text-sm font-semibold text-amber-800">Email non vérifié</h4>
          <p class="mt-1 text-sm text-amber-700">
            Vous devez vérifier votre adresse email avant de pouvoir publier une mission.
          </p>
          <button
            type="button"
            class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="isResendingVerification"
            @click="handleResendVerification"
            data-testid="resend-verification-button"
          >
            <Mail v-if="!isResendingVerification" class="h-4 w-4" />
            <Loader2 v-else class="h-4 w-4 animate-spin" />
            {{ isResendingVerification ? 'Envoi en cours...' : "Renvoyer l'email de vérification" }}
          </button>
        </div>
      </div>
    </div>

    <!-- Generic API Errors -->
    <div
      v-else-if="apiError"
      class="mb-6 p-4 bg-red-50 border border-red-100 rounded-lg flex items-start gap-3 text-red-700"
      role="alert"
      data-testid="api-error"
    >
      <AlertCircle class="w-5 h-5 mt-0.5 shrink-0" />
      <p class="text-sm font-medium">{{ apiError }}</p>
    </div>

    <!-- Section 1: Informations Générales -->
    <div :class="sectionClasses">
      <div
        class="flex items-center gap-2 mb-6 text-weact font-semibold uppercase tracking-wider text-xs"
      >
        <div class="w-8 h-8 rounded-lg bg-weact/10 flex items-center justify-center">
          <Film class="w-4 h-4" />
        </div>
        Informations Générales
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Titre -->
        <div class="md:col-span-2">
          <FloatingField
            id="titre"
            v-model="titre"
            label="Titre de la mission"
            :icon="Type"
            :error="titreError"
            required
            data-testid="titre-input"
          />
        </div>

        <!-- Type de mission -->
        <FloatingSelect
          id="type_mission"
          v-model="type_mission"
          label="Type de mission"
          :icon="Film"
          :error="typeMissionError"
          :options="missionTypeOptions"
          required
          data-testid="type-mission-select"
        />

        <!-- Précision type de mission (conditionnel) -->
        <FloatingField
          v-if="type_mission === MissionType.AUTRE"
          id="type_mission_autre"
          v-model="type_mission_autre"
          label="Précisez le type de mission"
          :icon="Type"
          :error="typeMissionAutreError"
          required
          data-testid="type-mission-autre-input"
        />

        <!-- Rémunération + Nombre de profils (rémunération masquée pour l'UGC — D-1.4.d) -->
        <FloatingField
          v-if="!isUgc"
          id="budget"
          v-model="budget"
          type="number"
          label="Rémunération par Face (XOF)"
          :icon="Wallet"
          :error="budgetError"
          required
          min="1"
          data-testid="budget-input"
        />

        <FloatingField
          id="nombre_faces_voulu"
          v-model="nombre_faces_voulu"
          type="number"
          label="Nombre de profils voulus"
          :icon="UserCircle"
          :error="nombreFacesError"
          min="1"
          data-testid="nombre-faces-input"
        />

        <!-- Hint tarifaire réactif (masqué pour l'UGC) -->
        <div
          v-if="pricingHint && !isUgc"
          data-testid="pricing-hint"
          class="md:col-span-2 flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm"
        >
          <Wallet class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
          <div class="space-y-0.5 text-muted-foreground">
            <p>Chaque Face sélectionnée recevra <span class="font-semibold text-foreground">{{ formatCurrency(Number(budget)) }}</span>.</p>
            <p>
              Total à payer, frais de service inclus :
              <span class="font-semibold text-foreground">{{ formatCurrency(pricingHint.total) }}</span>
              <span class="text-xs"> pour {{ pricingHint.n }} profil{{ pricingHint.n > 1 ? 's' : '' }}</span>
            </p>
          </div>
        </div>

        <!-- Description -->
        <div class="md:col-span-2">
          <FloatingTextarea
            id="description"
            v-model="description"
            label="Description détaillée"
            :icon="AlignLeft"
            :error="descriptionError"
            required
            :rows="4"
            data-testid="description-input"
          />
        </div>

        <!-- Bloc Dotation UGC (création + type UGC) -->
        <div v-if="isUgc" class="md:col-span-2 space-y-4">
          <UgcDotationFields
            v-model:compensation-type="type_compensation"
            v-model:nom-produit="nom_produit"
            v-model:valeur-produit="valeur_produit"
            v-model:nombre-videos="nombre_videos"
            v-model:montant-remuneration="montant_remuneration"
            :compensation-type-error="typeCompensationError"
            :nom-produit-error="nomProduitError"
            :valeur-produit-error="valeurProduitError"
            :nombre-videos-error="nombreVideosError"
            :montant-remuneration-error="montantRemunerationError"
          />
          <CommissionBreakdown
            :product-value="Number(valeur_produit) || 0"
            :pay-amount="type_compensation === 'hybrid' ? (Number(montant_remuneration) || 0) : 0"
          />
        </div>
      </div>
    </div>

    <!-- Section 2: Casting & Profils -->
    <div :class="sectionClasses">
      <div
        class="flex items-center gap-2 mb-6 text-weact font-semibold uppercase tracking-wider text-xs"
      >
        <div class="w-8 h-8 rounded-lg bg-weact/10 flex items-center justify-center">
          <Users class="w-4 h-4" />
        </div>
        Casting & Profils
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Genre recherché -->
        <FloatingSelect
          id="genre_voulu"
          v-model="genre_voulu"
          label="Genre recherché"
          :icon="Users"
          :error="genreVouluError"
          :options="genderOptions"
          required
          data-testid="genre-voulu-select"
        />

        <!-- Profil recherché -->
        <div class="md:col-span-2">
          <FloatingTextarea
            id="profil_recherche"
            v-model="profil_recherche"
            label="Profil recherché"
            :icon="UserCircle"
            :error="profilRechercheError"
            required
            :rows="3"
            data-testid="profil-recherche-input"
          />
        </div>
      </div>
    </div>

    <!-- Section 3: Dates & Lieu -->
    <div :class="sectionClasses">
      <div
        class="flex items-center gap-2 mb-6 text-weact font-semibold uppercase tracking-wider text-xs"
      >
        <div class="w-8 h-8 rounded-lg bg-weact/10 flex items-center justify-center">
          <MapPin class="w-4 h-4" />
        </div>
        Dates & Lieu
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Lieu (masqué pour l'UGC : dotation sans tournage — D-8.1.e) -->
        <FloatingSelect
          v-if="!isUgc"
          id="lieu"
          v-model="lieu"
          label="Lieu du tournage"
          :icon="MapPin"
          :error="lieuError"
          :options="BENIN_CITY_OPTIONS"
          required
          data-testid="lieu-select"
        />

        <!-- Durée (masquée pour l'UGC : dotation sans tournage — D-8.1.e) -->
        <div v-if="!isUgc">
          <FloatingSelect
            id="duree_preset"
            v-model="selectedDureePreset"
            label="Durée du tournage"
            :icon="Clock"
            :error="dureeError"
            :options="MISSION_DURATION_PRESETS"
            required
            data-testid="duree-select"
          />
          <template v-if="selectedDureePreset === 'custom'">
            <FloatingField
              id="duree_custom_jours"
              v-model="customDureeJours"
              type="number"
              label="Nombre de jours"
              :icon="Clock"
              :error="customDureeError"
              required
              min="6"
              class="mt-3"
              data-testid="duree-custom-days"
            />
            <div class="flex items-start gap-2 text-xs text-gray-500 mt-1">
              <Info :size="14" class="shrink-0 mt-0.5 text-blue-400" />
              <span>Au-delà de 5 jours, seuls des jours entiers sont possibles.</span>
            </div>
          </template>
        </div>

        <!-- Date de tournage (masquée pour l'UGC : dotation sans tournage — D-8.1.e) -->
        <FloatingField
          v-if="!isUgc"
          id="date_tournage"
          v-model="date_tournage"
          type="date"
          label="Date de tournage"
          :icon="Calendar"
          :error="dateTournageError"
          required
          data-testid="date-tournage-input"
        />

        <!-- Date limite de candidature -->
        <FloatingField
          id="date_limite_candidature"
          v-model="date_limite_candidature"
          type="date"
          label="Clôture des candidatures"
          :icon="Calendar"
          :error="dateLimiteError"
          required
          data-testid="date-limite-input"
        />
      </div>
    </div>

    </fieldset>

    <!-- Actions -->
    <div class="flex justify-end gap-3 mt-8">
      <Button
        type="button"
        variant="outline"
        @click="emit('cancel')"
        :disabled="isProcessing"
        class="min-w-[120px]"
        data-testid="cancel-button"
      >
        Annuler
      </Button>
      <Button
        type="submit"
        :disabled="isProcessing"
        class="min-w-[180px] bg-weact hover:bg-weact/90 text-white shadow-lg shadow-weact/20"
        data-testid="submit-button"
      >
        <template v-if="isProcessing">
          <Loader2 class="w-4 h-4 mr-2 animate-spin" />
          {{ props.mode === 'edit' ? 'Enregistrement...' : 'Publication...' }}
        </template>
        <template v-else>
          <component :is="props.mode === 'edit' ? Save : Send" class="w-4 h-4 mr-2" />
          {{ submitLabel }}
        </template>
      </Button>
    </div>
  </form>
</template>

<style scoped>
input,
select,
textarea {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
input[type='number'] {
  -moz-appearance: textfield;
}
</style>
