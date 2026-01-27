<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { missionSchema } from '../schemas/mission'
import { useMissionCreate } from '../composables/useMissionCreate'
import {
  MissionType,
  MissionTypeLabel,
  MissionGender,
  MissionGenderLabel,
  getMissionTypeOptions,
  getMissionGenderOptions,
  type Mission,
  type CreateMissionData,
} from '../types'
import { Button } from '@/components/ui/button'
import {
  Film,
  Users,
  MapPin,
  Clock,
  Wallet,
  Calendar,
  UserCircle,
  AlertCircle,
  Loader2,
  ChevronLeft,
  Send,
  Save,
} from 'lucide-vue-next'

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

const { createMission, isSubmitting: isCreating, error, validationErrors } = useMissionCreate()
const isProcessing = computed(() => props.isSubmitting || isCreating.value)

const missionTypeOptions = getMissionTypeOptions()
const genderOptions = getMissionGenderOptions()

const { handleSubmit, errors, setFieldError } = useForm({
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
    genre_voulu: props.initialValues?.genre_voulu ?? MissionGender.TOUS,
    lieu: props.initialValues?.lieu ?? '',
    duree: props.initialValues?.duree ?? '',
  },
})

const { value: titre, errorMessage: titreError } = useField<string>('titre')
const { value: type_mission, errorMessage: typeMissionError } = useField<string>('type_mission')
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

const apiError = computed(() => error.value)

const onSubmit = handleSubmit(async (values) => {
  const data: CreateMissionData = {
    titre: values.titre,
    description: values.description,
    date_tournage: values.date_tournage,
    profil_recherche: values.profil_recherche,
    budget: values.budget as number, // Validated by schema before submit
    date_limite_candidature: values.date_limite_candidature,
    nombre_faces_voulu: values.nombre_faces_voulu,
    type_mission: values.type_mission as CreateMissionData['type_mission'],
    genre_voulu: values.genre_voulu as CreateMissionData['genre_voulu'],
    lieu: values.lieu,
    duree: values.duree,
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
      'genre_voulu',
      'lieu',
      'duree',
    ] as const
    type ValidField = (typeof validFields)[number]

    Object.entries(result.errors).forEach(([field, messages]) => {
      if (messages && messages.length > 0 && validFields.includes(field as ValidField)) {
        setFieldError(field as ValidField, messages[0])
      }
    })
  }
})

const inputClasses = (hasError: boolean) => [
  'w-full px-4 py-2.5 rounded-lg border transition-all duration-200 outline-none text-gray-900 bg-white',
  hasError
    ? 'border-red-300 focus:border-red-500 focus:ring-4 focus:ring-red-200'
    : 'border-gray-300 focus:border-weact focus:ring-4 focus:ring-weact/20',
]

const labelClasses = 'block text-sm font-medium text-gray-700 mb-1.5 flex items-center gap-2'
const sectionClasses = 'bg-white rounded-xl border border-gray-100 p-6 shadow-sm mb-6'
</script>

<template>
  <form @submit.prevent="onSubmit" class="max-w-4xl mx-auto pb-24" data-testid="mission-form">
    <fieldset :disabled="isSubmitting" class="contents">
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">
          {{ props.mode === 'edit' ? 'Modifier la Mission' : 'Nouvelle Mission' }}
        </h1>
        <p class="text-gray-500 mt-1">
          {{ props.mode === 'edit' ? 'Mettez à jour les détails de votre mission.' : 'Publiez votre casting et trouvez les meilleurs profils.' }}
        </p>
      </div>
      <Button
        variant="ghost"
        type="button"
        @click="emit('cancel')"
        class="text-gray-500 hover:text-gray-700"
        data-testid="cancel-button-top"
      >
        <ChevronLeft class="w-4 h-4 mr-2" />
        Retour
      </Button>
    </div>

    <!-- API Errors -->
    <div
      v-if="apiError"
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
          <label for="titre" :class="labelClasses">
            Titre de la mission <span class="text-red-500">*</span>
          </label>
          <input
            id="titre"
            v-model="titre"
            type="text"
            placeholder="Ex: Acteur principal pour spot TV"
            :class="inputClasses(!!titreError)"
            data-testid="titre-input"
          />
          <p v-if="titreError" class="mt-1.5 text-xs text-red-600 font-medium" data-testid="titre-error">
            {{ titreError }}
          </p>
        </div>

        <!-- Type de mission -->
        <div>
          <label for="type_mission" :class="labelClasses">
            Type de mission <span class="text-red-500">*</span>
          </label>
          <select
            id="type_mission"
            v-model="type_mission"
            :class="inputClasses(!!typeMissionError)"
            data-testid="type-mission-select"
          >
            <option v-for="option in missionTypeOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
          <p
            v-if="typeMissionError"
            class="mt-1.5 text-xs text-red-600 font-medium"
            data-testid="type-mission-error"
          >
            {{ typeMissionError }}
          </p>
        </div>

        <!-- Budget -->
        <div>
          <label for="budget" :class="labelClasses">
            Budget estimé (XOF) <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
              <Wallet class="w-4 h-4 text-gray-400" />
            </div>
            <input
              id="budget"
              v-model.number="budget"
              type="number"
              min="1"
              placeholder="0"
              :class="[...inputClasses(!!budgetError), 'pl-10']"
              data-testid="budget-input"
            />
          </div>
          <p
            v-if="budgetError"
            class="mt-1.5 text-xs text-red-600 font-medium"
            data-testid="budget-error"
          >
            {{ budgetError }}
          </p>
        </div>

        <!-- Description -->
        <div class="md:col-span-2">
          <label for="description" :class="labelClasses">
            Description détaillée <span class="text-red-500">*</span>
          </label>
          <textarea
            id="description"
            v-model="description"
            rows="4"
            placeholder="Décrivez le projet, le contexte et les attentes..."
            :class="inputClasses(!!descriptionError)"
            data-testid="description-input"
          ></textarea>
          <p
            v-if="descriptionError"
            class="mt-1.5 text-xs text-red-600 font-medium"
            data-testid="description-error"
          >
            {{ descriptionError }}
          </p>
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
        <div>
          <label for="genre_voulu" :class="labelClasses">
            Genre recherché <span class="text-red-500">*</span>
          </label>
          <select
            id="genre_voulu"
            v-model="genre_voulu"
            :class="inputClasses(!!genreVouluError)"
            data-testid="genre-voulu-select"
          >
            <option v-for="option in genderOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
          <p
            v-if="genreVouluError"
            class="mt-1.5 text-xs text-red-600 font-medium"
            data-testid="genre-voulu-error"
          >
            {{ genreVouluError }}
          </p>
        </div>

        <!-- Nombre de profils -->
        <div>
          <label for="nombre_faces_voulu" :class="labelClasses"> Nombre de profils </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
              <UserCircle class="w-4 h-4 text-gray-400" />
            </div>
            <input
              id="nombre_faces_voulu"
              v-model.number="nombre_faces_voulu"
              type="number"
              min="1"
              :class="[...inputClasses(!!nombreFacesError), 'pl-10']"
              data-testid="nombre-faces-input"
            />
          </div>
          <p
            v-if="nombreFacesError"
            class="mt-1.5 text-xs text-red-600 font-medium"
            data-testid="nombre-faces-error"
          >
            {{ nombreFacesError }}
          </p>
        </div>

        <!-- Profil recherché -->
        <div class="md:col-span-2">
          <label for="profil_recherche" :class="labelClasses">
            Profil recherché <span class="text-red-500">*</span>
          </label>
          <textarea
            id="profil_recherche"
            v-model="profil_recherche"
            rows="3"
            placeholder="Âge, compétences, particularités physiques..."
            :class="inputClasses(!!profilRechercheError)"
            data-testid="profil-recherche-input"
          ></textarea>
          <p
            v-if="profilRechercheError"
            class="mt-1.5 text-xs text-red-600 font-medium"
            data-testid="profil-recherche-error"
          >
            {{ profilRechercheError }}
          </p>
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
        <!-- Lieu -->
        <div>
          <label for="lieu" :class="labelClasses">
            Lieu du tournage <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
              <MapPin class="w-4 h-4 text-gray-400" />
            </div>
            <input
              id="lieu"
              v-model="lieu"
              type="text"
              placeholder="Ex: Abidjan, Plateau"
              :class="[...inputClasses(!!lieuError), 'pl-10']"
              data-testid="lieu-input"
            />
          </div>
          <p v-if="lieuError" class="mt-1.5 text-xs text-red-600 font-medium" data-testid="lieu-error">
            {{ lieuError }}
          </p>
        </div>

        <!-- Durée -->
        <div>
          <label for="duree" :class="labelClasses">
            Durée estimée <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
              <Clock class="w-4 h-4 text-gray-400" />
            </div>
            <input
              id="duree"
              v-model="duree"
              type="text"
              placeholder="Ex: 2 jours, 4 heures..."
              :class="[...inputClasses(!!dureeError), 'pl-10']"
              data-testid="duree-input"
            />
          </div>
          <p v-if="dureeError" class="mt-1.5 text-xs text-red-600 font-medium" data-testid="duree-error">
            {{ dureeError }}
          </p>
        </div>

        <!-- Date de tournage -->
        <div>
          <label for="date_tournage" :class="labelClasses">
            Date de tournage <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
              <Calendar class="w-4 h-4 text-gray-400" />
            </div>
            <input
              id="date_tournage"
              v-model="date_tournage"
              type="date"
              :class="[...inputClasses(!!dateTournageError), 'pl-10']"
              data-testid="date-tournage-input"
            />
          </div>
          <p
            v-if="dateTournageError"
            class="mt-1.5 text-xs text-red-600 font-medium"
            data-testid="date-tournage-error"
          >
            {{ dateTournageError }}
          </p>
        </div>

        <!-- Date limite de candidature -->
        <div>
          <label for="date_limite_candidature" :class="labelClasses">
            Clôture des candidatures <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
              <Calendar class="w-4 h-4 text-gray-400" />
            </div>
            <input
              id="date_limite_candidature"
              v-model="date_limite_candidature"
              type="date"
              :class="[...inputClasses(!!dateLimiteError), 'pl-10']"
              data-testid="date-limite-input"
            />
          </div>
          <p
            v-if="dateLimiteError"
            class="mt-1.5 text-xs text-red-600 font-medium"
            data-testid="date-limite-error"
          >
            {{ dateLimiteError }}
          </p>
        </div>
      </div>
    </div>

    </fieldset>

    <!-- Actions -->
    <div
      class="fixed bottom-0 left-0 right-0 p-4 bg-white/80 backdrop-blur-lg border-t border-gray-100 flex justify-center z-50"
    >
      <div class="max-w-4xl w-full flex justify-end gap-3">
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
            {{ props.mode === 'edit' ? 'Enregistrer les modifications' : 'Publier la mission' }}
          </template>
        </Button>
      </div>
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

.fixed {
  box-shadow: 0 -4px 20px -5px rgba(0, 0, 0, 0.05);
}
</style>
