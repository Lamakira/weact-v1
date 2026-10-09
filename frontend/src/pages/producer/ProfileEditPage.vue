<script setup lang="ts">
/**
 * ProfileEditPage
 * Producer profile settings (direction « Régie »): section tabs (?tab=), one
 * RSettingsSection per setting, a single sticky save bar for the fields saved
 * through the text endpoints (name, WhatsApp number, bio), and a leave guard.
 *
 * Photo / logo uploads, email change, password change and data privacy keep their
 * own immediate actions: they are not part of the dirty tracking.
 *
 * This component is rendered inside ProducerLayout via nested routing.
 */
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch, nextTick } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { ExternalLink, Lock, X } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useProducerProfilePhoto } from '@/features/producer/composables/useProducerProfilePhoto'
import { useProducerBio } from '@/features/producer/composables/useProducerBio'
import { useProducerBasicInfo } from '@/features/producer/composables/useProducerBasicInfo'
import { useToast } from '@/composables/useToast'
import ProducerProfilePhotoUpload from '@/features/producer/components/ProducerProfilePhotoUpload.vue'
import AgencyLogoUpload from '@/features/producer/components/AgencyLogoUpload.vue'
import EmailChangeForm from '@/features/auth/components/EmailChangeForm.vue'
import PasswordChangeForm from '@/features/auth/components/PasswordChangeForm.vue'
import DataPrivacySection from '@/components/account/DataPrivacySection.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import RSettingsSection from '@/components/regie/RSettingsSection.vue'
import RStickySaveBar from '@/components/regie/RStickySaveBar.vue'
import RStatusDot from '@/components/regie/RStatusDot.vue'
import type { ProducerBasicInfoFormData } from '@/features/producer/types'

const {
  profile,
  isLoading,
  isUploading,
  isDeleting,
  error,
  fetchProfile,
  uploadPhoto,
  deletePhoto,
} = useProducerProfilePhoto()

const {
  bio,
  isLoading: isBioLoading,
  isSaving: isBioSaving,
  error: bioError,
  fetchBio,
  saveBio,
} = useProducerBio()

const {
  basicInfo,
  isLoading: isBasicInfoLoading,
  isSaving: isBasicInfoSaving,
  error: basicInfoError,
  fetchBasicInfo,
  updateBasicInfo,
  clearError: clearBasicInfoError,
} = useProducerBasicInfo()

const MAX_BIO_LENGTH = 500

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

// Account type: the profile is the source for the upload block, the basic info for
// the name fields — they agree, the profile covers the window before basic info loads.
const isAgency = computed(() => (basicInfo.value?.type ?? profile.value?.type) === 'agency')

// ─── Tabs (state mirrored in ?tab=, same pattern as the Face profile) ───────────
type TabId = 'profil' | 'coordonnees' | 'securite' | 'donnees'

const TABS: { id: TabId; label: string }[] = [
  { id: 'profil', label: 'Profil public' },
  { id: 'coordonnees', label: 'Coordonnées' },
  { id: 'securite', label: 'Sécurité' },
  { id: 'donnees', label: 'Données personnelles' },
]

function isTabId(value: unknown): value is TabId {
  return typeof value === 'string' && TABS.some((t) => t.id === value)
}

function tabFromRoute(): TabId {
  // Deep-link from the WhatsApp banner (?focus=whatsapp) lands on Coordonnées.
  if (route.query.focus === 'whatsapp') return 'coordonnees'
  return isTabId(route.query.tab) ? route.query.tab : 'profil'
}

const activeTab = ref<TabId>(tabFromRoute())

// Security / email / privacy forms are only mounted once their tab was opened, so the
// browser's password manager does not detect them on arrival (UX-1 fix).
const visitedTabs = ref<TabId[]>([activeTab.value])
watch(activeTab, (tab) => {
  if (!visitedTabs.value.includes(tab)) visitedTabs.value.push(tab)
})

function selectTab(tab: TabId): void {
  activeTab.value = tab
  if (route.query.tab === tab) return
  void router.push({ query: { ...route.query, tab } })
}

watch(
  () => route.query.tab,
  (tab) => {
    if (isTabId(tab)) activeTab.value = tab
  }
)

// ─── Form state and dirty tracking ──────────────────────────────────────────────
interface FormValues {
  agency_name: string
  first_name: string
  last_name: string
  whatsapp_number: string
  bio: string
}

const form = reactive<FormValues>({
  agency_name: '',
  first_name: '',
  last_name: '',
  whatsapp_number: '',
  bio: '',
})

const baseline = computed<FormValues>(() => {
  const info = basicInfo.value
  return {
    agency_name: info?.type === 'agency' ? (info.agency_name ?? '') : '',
    first_name: info?.type === 'particulier' ? (info.first_name ?? '') : '',
    last_name: info?.type === 'particulier' ? (info.last_name ?? '') : '',
    whatsapp_number: info?.whatsapp_number ?? '',
    bio: bio.value ?? '',
  }
})

const FIELD_KEYS = Object.keys(form) as (keyof FormValues)[]

// A field follows the loaded value as long as the user has not touched it
// (compared to the PREVIOUS baseline), so a late fetch never clobbers an edit.
watch(
  baseline,
  (next, previous) => {
    for (const key of FIELD_KEYS) {
      if (form[key].trim() === (previous?.[key] ?? '').trim()) form[key] = next[key]
    }
  },
  { immediate: true }
)

const basicDirty = computed(() => {
  const base = baseline.value
  const nameChanged = isAgency.value
    ? form.agency_name !== base.agency_name
    : form.first_name !== base.first_name || form.last_name !== base.last_name
  return nameChanged || form.whatsapp_number.trim() !== base.whatsapp_number.trim()
})
const bioDirty = computed(() => form.bio.trim() !== baseline.value.bio.trim())
const isDirty = computed(() => basicDirty.value || bioDirty.value)
const isSaving = computed(() => isBasicInfoSaving.value || isBioSaving.value)

const bioCount = computed(() => form.bio.length)
const isBioOverLimit = computed(() => bioCount.value > MAX_BIO_LENGTH)
const isBioNearLimit = computed(
  () => bioCount.value > MAX_BIO_LENGTH - 50 && bioCount.value <= MAX_BIO_LENGTH
)

const nameError = ref<string | null>(null)
watch(
  () => [form.agency_name, form.first_name],
  () => {
    nameError.value = null
  }
)

function resetForm(): void {
  Object.assign(form, baseline.value)
  nameError.value = null
  clearBasicInfoError()
  bioError.value = null
}

function validate(): boolean {
  nameError.value = null

  if (basicDirty.value) {
    if (isAgency.value && form.agency_name.trim() === '') {
      nameError.value = "Le nom de l'agence est requis."
    } else if (!isAgency.value && form.first_name.trim() === '') {
      nameError.value = 'Le prénom est requis.'
    }
  }

  return nameError.value === null && !isBioOverLimit.value
}

async function handleSave(): Promise<void> {
  if (isSaving.value || !isDirty.value || !validate()) return

  clearBasicInfoError()
  const saved: string[] = []

  if (basicDirty.value) {
    // Empty → null so the backend clears the number (same as the Face field).
    const whatsapp_number = form.whatsapp_number.trim() || null
    const data: ProducerBasicInfoFormData = isAgency.value
      ? { agency_name: form.agency_name, whatsapp_number }
      : { first_name: form.first_name, last_name: form.last_name, whatsapp_number }

    const result = await updateBasicInfo(data)
    if (result.success) saved.push(result.message || 'Informations mises à jour avec succès')
  }

  if (bioDirty.value) {
    const result = await saveBio(form.bio.trim() || null)
    if (result.success) saved.push(result.message || 'Bio mise à jour avec succès')
  }

  if (saved.length === 1) toast.success(saved[0]!)
  else if (saved.length > 1) toast.success('Profil mis à jour avec succès')
}

// ─── Leave guard ────────────────────────────────────────────────────────────────
const leaveModalOpen = ref(false)
let resolveLeave: ((allowed: boolean) => void) | null = null

onBeforeRouteLeave(() => {
  if (!isDirty.value) return true

  leaveModalOpen.value = true
  return new Promise<boolean>((resolve) => {
    resolveLeave = resolve
  })
})

function answerLeave(allowed: boolean): void {
  leaveModalOpen.value = false
  resolveLeave?.(allowed)
  resolveLeave = null
}

// Closing / reloading the tab with unsaved changes.
function handleBeforeUnload(event: BeforeUnloadEvent): void {
  if (!isDirty.value) return
  event.preventDefault()
  event.returnValue = ''
}

// ─── WhatsApp banner deep-link (?focus=whatsapp) ────────────────────────────────
// Once the field is focused, drop `focus` from the URL so a refresh or a second
// click on the banner CTA triggers the focus again.
function clearFocusQuery(): void {
  const { focus: _focus, ...rest } = route.query
  void router.replace({ path: route.path, query: rest })
}

async function focusWhatsappField(): Promise<void> {
  activeTab.value = 'coordonnees'
  await nextTick()
  const input = document.getElementById('whatsapp_number')
  input?.scrollIntoView?.({ block: 'center' })
  input?.focus()
  clearFocusQuery()
}

// The page can already be displayed when the banner CTA is clicked (same route.path
// ⇒ same component, no remount): react to the query turning on.
watch(
  () => route.query.focus,
  (focus) => {
    if (focus === 'whatsapp' && !isBasicInfoLoading.value) void focusWhatsappField()
  }
)

// ─── Completion nudge ───────────────────────────────────────────────────────────
// Producers deliberately have NO completion percentage: only two items are real
// (visual identity, bio), and a ring built on two booleans is noise. A single
// dismissible line does the same job for none of the machinery.
//
// The dismissal is keyed per user: the browser may be shared between accounts.
// Storage can be unavailable (private mode, quota): it then degrades to
// "dismissed for this page view only".
const nudgeStorageKey = computed<string | null>(() =>
  authStore.user?.id != null ? `producer_profile_nudge_dismissed:${authStore.user.id}` : null
)

function readNudgeDismissed(): boolean {
  if (nudgeStorageKey.value === null) return false

  try {
    return localStorage.getItem(nudgeStorageKey.value) === '1'
  } catch {
    return false
  }
}

const nudgeDismissed = ref(readNudgeDismissed())

const hasVisualIdentity = computed(() =>
  isAgency.value ? !!profile.value?.agency_logo_url : !!profile.value?.profile_photo_url
)

const showProfileNudge = computed(
  () =>
    !nudgeDismissed.value &&
    !isLoading.value &&
    !isBioLoading.value &&
    (!hasVisualIdentity.value || !bio.value)
)

const nudgeMessage = computed<string>(() => {
  const missing: string[] = []

  if (!hasVisualIdentity.value) {
    missing.push(isAgency.value ? 'ajoutez le logo de votre agence' : 'ajoutez une photo de profil')
  }
  if (!bio.value) missing.push('présentez votre activité en quelques lignes')

  return `Complétez votre profil — ${missing.join(' et ')} — les Faces répondent bien plus souvent aux Producteurs identifiables.`
})

function dismissNudge(): void {
  nudgeDismissed.value = true

  if (nudgeStorageKey.value === null) return

  try {
    localStorage.setItem(nudgeStorageKey.value, '1')
  } catch {
    // Not persisted: the nudge reappears on the next visit, which is harmless.
  }
}

// ─── Public page link ───────────────────────────────────────────────────────────
const publicSlug = computed<string | null>(() => {
  const userable = authStore.user?.userable
  return userable && 'slug' in userable && userable.slug ? userable.slug : null
})

// ─── Lifecycle ──────────────────────────────────────────────────────────────────
onMounted(async () => {
  window.addEventListener('beforeunload', handleBeforeUnload)
  await Promise.all([fetchProfile(), fetchBio(), fetchBasicInfo()])

  if (route.query.focus === 'whatsapp') await focusWhatsappField()
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', handleBeforeUnload)
})

// ─── Photo upload handlers ──────────────────────────────────────────────────────
async function handleUpload(file: File): Promise<void> {
  const result = await uploadPhoto(file)

  if (result.success && result.message) {
    toast.success(result.message)
  }
}

async function handleDelete(): Promise<void> {
  const result = await deletePhoto()

  if (result.success && result.message) {
    toast.success(result.message)
  }
}

const inputClass =
  'h-9 w-full rounded-control bg-white px-3 text-[13.5px] text-ink ring-1 ring-line placeholder:text-ink-3 focus:outline-none focus:ring-2 focus:ring-weact-600'
</script>

<template>
  <div>
    <!-- Loading state -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <svg
        class="animate-spin h-8 w-8 text-primary"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle
          class="opacity-25"
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          stroke-width="4"
        ></circle>
        <path
          class="opacity-75"
          fill="currentColor"
          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
        ></path>
      </svg>
    </div>

    <div v-else>
      <header>
        <h1 class="text-[22px] font-semibold tracking-[-0.03em] text-ink">Mon profil</h1>
        <p class="mt-1 text-[13px] text-ink-3">
          Gérez votre fiche publique, vos coordonnées et la sécurité du compte.
        </p>
      </header>

      <!-- Non-blocking completion nudge (no percentage, see script comment) -->
      <div
        v-if="showProfileNudge"
        class="mt-4 flex items-start gap-3 rounded-control bg-white px-3 py-2 ring-1 ring-line"
        data-testid="producer-profile-nudge"
      >
        <RStatusDot tone="pending" label="À compléter" hide-label class="mt-1.5" />
        <p class="grow text-[13px] text-ink-2">
          {{ nudgeMessage }}
        </p>
        <button
          type="button"
          class="shrink-0 rounded p-1 text-ink-3 hover:bg-sidebar"
          aria-label="Masquer ce rappel"
          data-testid="producer-profile-nudge-dismiss"
          @click="dismissNudge"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <!-- Section tabs (scroll horizontally on mobile) -->
      <nav
        role="tablist"
        aria-label="Sections du profil"
        class="mt-5 flex gap-5 overflow-x-auto whitespace-nowrap border-b border-line"
        data-testid="profile-tabs"
      >
        <button
          v-for="tab in TABS"
          :key="tab.id"
          type="button"
          role="tab"
          :id="`profile-tab-${tab.id}`"
          :aria-selected="activeTab === tab.id"
          :aria-controls="`profile-panel-${tab.id}`"
          :data-testid="`profile-tab-${tab.id}`"
          class="-mb-px shrink-0 border-b-2 pb-2.5 text-[13px] transition-colors"
          :class="
            activeTab === tab.id
              ? 'border-ink font-semibold text-ink'
              : 'border-transparent text-ink-3 hover:text-ink'
          "
          @click="selectTab(tab.id)"
        >
          {{ tab.label }}
        </button>
      </nav>

      <!-- Save errors (basic info endpoint) -->
      <div
        v-if="basicInfoError"
        class="mt-4 flex items-center gap-2 rounded-control bg-red-50 px-3 py-2 ring-1 ring-red-200"
        role="alert"
        data-testid="error-message"
      >
        <span class="text-[13px] font-medium text-red-700">{{ basicInfoError }}</span>
      </div>

      <!-- Profil public -->
      <div
        v-show="activeTab === 'profil'"
        id="profile-panel-profil"
        role="tabpanel"
        aria-labelledby="profile-tab-profil"
        data-testid="profile-panel-profil"
      >
        <RSettingsSection
          :title="isAgency ? 'Logo de l\'agence' : 'Photo de profil'"
          :description="
            isAgency
              ? 'Affiché sur vos missions et dans la messagerie.'
              : 'Affichée sur vos missions et dans la messagerie.'
          "
        >
          <!-- Agency logo -->
          <AgencyLogoUpload v-if="isAgency" data-testid="agency-logo-section" />

          <!-- Profile photo (particulier) -->
          <ProducerProfilePhotoUpload
            v-else
            :profile="profile"
            :is-uploading="isUploading"
            :is-deleting="isDeleting"
            :error="error"
            @upload="handleUpload"
            @delete="handleDelete"
          />
        </RSettingsSection>

        <RSettingsSection
          :title="isAgency ? 'Informations de l\'agence' : 'Votre nom'"
          :description="
            isAgency
              ? 'Le nom sous lequel votre agence apparaît auprès des Faces.'
              : 'Le nom sous lequel vous apparaissez auprès des Faces.'
          "
        >
          <div v-if="isBasicInfoLoading" class="flex justify-center py-4" data-testid="loading-state">
            <svg class="animate-spin h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              ></path>
            </svg>
          </div>
          <div v-else class="max-w-[520px] space-y-3" data-testid="basic-info-section">
            <div v-if="isAgency" class="space-y-1.5">
              <label for="agency_name" class="text-[12.5px] font-semibold text-ink">Nom de l'agence</label>
              <input
                id="agency_name"
                v-model="form.agency_name"
                type="text"
                required
                placeholder="Production ABC"
                :class="inputClass"
                data-testid="agency-name-input"
              />
            </div>
            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div class="space-y-1.5">
                <label for="first_name" class="text-[12.5px] font-semibold text-ink">Prénom</label>
                <input
                  id="first_name"
                  v-model="form.first_name"
                  type="text"
                  required
                  placeholder="Marie"
                  :class="inputClass"
                  data-testid="first-name-input"
                />
              </div>
              <div class="space-y-1.5">
                <label for="last_name" class="text-[12.5px] font-semibold text-ink">Nom (facultatif)</label>
                <input
                  id="last_name"
                  v-model="form.last_name"
                  type="text"
                  placeholder="Martin"
                  :class="inputClass"
                  data-testid="last-name-input"
                />
              </div>
            </div>
            <p v-if="nameError" class="text-[12.5px] text-red-600" role="alert" data-testid="name-error">
              {{ nameError }}
            </p>
          </div>
        </RSettingsSection>

        <RSettingsSection
          title="Bio"
          description="Quelques lignes sur votre activité. Les Faces répondent plus souvent aux Producteurs identifiables."
        >
          <div v-if="isBioLoading" class="flex justify-center py-4">
            <svg class="animate-spin h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              ></path>
            </svg>
          </div>
          <div v-else class="max-w-[520px]" data-testid="bio-editor">
            <div
              v-if="bioError"
              class="mb-3 rounded-control bg-red-50 px-3 py-2 text-[13px] text-red-700 ring-1 ring-red-200"
              data-testid="bio-error"
            >
              {{ bioError }}
            </div>
            <label for="producer_bio" class="sr-only">Bio</label>
            <textarea
              id="producer_bio"
              v-model="form.bio"
              :maxlength="MAX_BIO_LENGTH + 50"
              rows="5"
              class="w-full resize-none rounded-control px-3 py-2.5 text-[13.5px] leading-[1.55] text-ink ring-1 focus:outline-none focus:ring-2"
              :class="{
                'ring-line focus:ring-weact-600': !isBioOverLimit && !isBioNearLimit,
                'ring-amber-400 focus:ring-amber-500': isBioNearLimit,
                'ring-red-500 focus:ring-red-500': isBioOverLimit,
              }"
              placeholder="Ex : Agence de casting spécialisée dans la publicité depuis 10 ans…"
              data-testid="bio-textarea"
            ></textarea>
            <div class="mt-1 flex items-center justify-between">
              <p
                v-if="isBioOverLimit"
                class="text-[12.5px] text-red-600"
                data-testid="bio-over-limit-warning"
              >
                La bio dépasse la limite de {{ MAX_BIO_LENGTH }} caractères
              </p>
              <span v-else></span>
              <span
                class="text-[11.5px]"
                :class="isBioOverLimit ? 'text-red-600' : isBioNearLimit ? 'text-amber-700' : 'text-ink-3'"
                data-testid="bio-char-count"
              >
                {{ bioCount }}/{{ MAX_BIO_LENGTH }}
              </span>
            </div>
          </div>
        </RSettingsSection>

        <RSettingsSection
          v-if="publicSlug"
          title="Fiche publique"
          description="La page que les Faces voient de votre profil."
        >
          <RouterLink
            :to="{ name: 'public-producer-profile', params: { slug: publicSlug } }"
            target="_blank"
            class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-weact-700 hover:underline"
            data-testid="public-profile-link"
          >
            Voir ma fiche publique
            <ExternalLink class="h-3.5 w-3.5" aria-hidden="true" />
          </RouterLink>
        </RSettingsSection>
      </div>

      <!-- Coordonnées -->
      <div
        v-show="activeTab === 'coordonnees'"
        id="profile-panel-coordonnees"
        role="tabpanel"
        aria-labelledby="profile-tab-coordonnees"
        data-testid="profile-panel-coordonnees"
      >
        <RSettingsSection
          title="Numéro WhatsApp"
          description="Utilisé par l'équipe WeAct pour vous joindre rapidement."
        >
          <div class="max-w-[300px] space-y-1.5">
            <label for="whatsapp_number" class="text-[12.5px] font-semibold text-ink">
              Numéro WhatsApp
            </label>
            <input
              id="whatsapp_number"
              v-model="form.whatsapp_number"
              type="tel"
              maxlength="30"
              placeholder="+229 01 00 00 00 00"
              :class="inputClass"
              data-testid="whatsapp-number-input"
            />
            <p class="text-[12px] text-ink-3">
              Visible uniquement par l'équipe WeAct, jamais par les Faces.
            </p>
          </div>
        </RSettingsSection>

        <RSettingsSection
          title="Compte"
          description="Votre adresse de connexion. Le type de compte est fixé à l'inscription."
        >
          <div class="max-w-[520px] space-y-4">
            <div class="space-y-1.5">
              <span class="text-[12.5px] font-semibold text-ink">Email</span>
              <p class="text-[13.5px] text-ink" data-testid="account-email">
                {{ authStore.user?.email ?? '-' }}
              </p>
            </div>

            <div class="space-y-1.5">
              <span class="text-[12.5px] font-semibold text-ink">Type de compte</span>
              <div
                class="flex h-9 items-center gap-2 rounded-control bg-sidebar px-3 text-[13.5px] text-ink-3 ring-1 ring-line"
                data-testid="account-type"
              >
                <Lock class="h-3.5 w-3.5" aria-hidden="true" />
                {{ isAgency ? 'Agence' : 'Particulier' }}
              </div>
            </div>

            <div v-if="visitedTabs.includes('coordonnees')" class="border-t border-line pt-4">
              <EmailChangeForm />
            </div>
          </div>
        </RSettingsSection>
      </div>

      <!-- Sécurité (mounted on first opening only) -->
      <div
        v-show="activeTab === 'securite'"
        id="profile-panel-securite"
        role="tabpanel"
        aria-labelledby="profile-tab-securite"
        data-testid="profile-panel-securite"
      >
        <RSettingsSection
          v-if="visitedTabs.includes('securite')"
          title="Mot de passe"
          description="Changez votre mot de passe, ou définissez-en un si votre compte a été créé avec Google."
        >
          <div class="max-w-[520px]">
            <PasswordChangeForm />
          </div>
        </RSettingsSection>
      </div>

      <!-- Données personnelles (mounted on first opening only) -->
      <div
        v-show="activeTab === 'donnees'"
        id="profile-panel-donnees"
        role="tabpanel"
        aria-labelledby="profile-tab-donnees"
        data-testid="profile-panel-donnees"
      >
        <RSettingsSection
          v-if="visitedTabs.includes('donnees')"
          title="Données personnelles"
          description="Articles 437 à 443 du Code du numérique du Bénin."
        >
          <div
            class="max-w-[520px] rounded-panel bg-red-50/40 p-4 ring-1 ring-red-200"
            data-testid="danger-zone"
          >
            <DataPrivacySection />
          </div>
        </RSettingsSection>
      </div>

      <RStickySaveBar
        :dirty="isDirty"
        :saving="isSaving"
        @cancel="resetForm"
        @save="handleSave"
      />
    </div>

    <ConfirmModal
      :is-open="leaveModalOpen"
      title="Quitter sans enregistrer ?"
      message="Vos modifications non enregistrées seront perdues."
      confirm-text="Quitter sans enregistrer"
      cancel-text="Rester sur la page"
      variant="warning"
      @confirm="answerLeave(true)"
      @cancel="answerLeave(false)"
    />
  </div>
</template>

<style scoped>
.text-primary {
  color: var(--color-weact, #198496);
}
</style>
