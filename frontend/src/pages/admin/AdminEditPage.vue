<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  AlertCircle,
  Loader2,
  Save,
  X,
  User,
  Mail,
  ShieldCheck,
  KeyRound,
} from 'lucide-vue-next'
import { useAdmins } from '@/features/admin/composables/useAdmins'
import { adminsApi } from '@/features/admin/services/adminsApi'
import { getApiErrorMessage } from '@/features/admin/services/adminAuthApi'
import { useAdminAuthStore } from '@/stores/adminAuth'
import { useToast } from '@/composables/useToast'
import { FloatingField, FloatingSelect } from '@/components/ui/form'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const adminAuthStore = useAdminAuthStore()
const { admin, isLoading, error, fetchAdmin, updateAdmin } = useAdmins()

const adminId = computed(() => route.params.id as string)
const isSelf = computed(() => admin.value?.id === adminAuthStore.admin?.id)

const editForm = ref({ name: '', email: '', role: '' })
const editErrors = ref<Record<string, string[]>>({})
const apiError = ref('')

const roleOptions = [
  { value: 'superadmin', label: 'Super Admin' },
  { value: 'admin', label: 'Admin' },
  { value: 'editor', label: 'Éditeur' },
]

onMounted(async () => {
  await fetchAdmin(adminId.value)
  if (admin.value) {
    editForm.value = {
      name: admin.value.name,
      email: admin.value.email,
      role: admin.value.role,
    }
  }
})

async function handleSave(): Promise<void> {
  editErrors.value = {}
  apiError.value = ''

  const result = await updateAdmin(adminId.value, editForm.value)
  if (result.success) {
    toast.success(result.message ?? 'Administrateur mis à jour avec succès')
    router.push({ name: 'admin-admins-list' })
  } else {
    if (result.errors) {
      editErrors.value = result.errors
    }
    apiError.value = result.message ?? 'Une erreur est survenue'
  }
}

const isSendingResetLink = ref(false)

async function handleSendResetLink(): Promise<void> {
  if (!admin.value) return
  isSendingResetLink.value = true

  try {
    const result = await adminsApi.sendPasswordResetLink(admin.value.id)
    toast.success(result.message ?? `Lien de réinitialisation envoyé à ${admin.value.email}`)
  } catch (error) {
    const message = getApiErrorMessage(error)
    toast.error(message ?? "Impossible d'envoyer le lien de réinitialisation")
  } finally {
    isSendingResetLink.value = false
  }
}

function goBack(): void {
  router.push({ name: 'admin-admins-list' })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Back button -->
    <button
      @click="goBack"
      class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
      data-testid="back-button"
    >
      <ArrowLeft class="h-4 w-4" />
      Retour à la liste
    </button>

    <!-- Loading State -->
    <div
      v-if="isLoading && !admin"
      class="flex items-center justify-center py-12"
      data-testid="loading-state"
    >
      <Loader2 class="h-8 w-8 text-primary-500 animate-spin" />
    </div>

    <!-- Error State (404 or network error) -->
    <div
      v-if="error && !admin"
      class="rounded-lg bg-red-50 border border-red-200 p-4 flex items-start gap-3"
      role="alert"
      data-testid="error-message"
    >
      <AlertCircle class="h-5 w-5 text-red-500 mt-0.5 shrink-0" />
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Edit Form -->
    <template v-if="admin">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
          <ShieldCheck class="h-6 w-6 text-primary-600" />
          Modifier l'administrateur
        </h1>
        <p class="mt-1 text-sm text-gray-500">
          Modifiez les informations de {{ admin.name }}
        </p>
      </div>

      <div class="max-w-lg">
        <div class="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
          <form @submit.prevent="handleSave" class="space-y-5" data-testid="edit-admin-form">
            <!-- API error -->
            <div
              v-if="apiError"
              class="rounded-lg bg-red-50 p-3 border border-red-200"
              role="alert"
              data-testid="api-error"
            >
              <p class="text-sm text-red-700">{{ apiError }}</p>
            </div>

            <!-- Name -->
            <FloatingField
              id="edit-admin-name"
              v-model="editForm.name"
              type="text"
              label="Nom"
              :icon="User"
              :error="editErrors.name?.[0]"
              required
              autocomplete="name"
              data-testid="name-input"
            />

            <!-- Email -->
            <FloatingField
              id="edit-admin-email"
              v-model="editForm.email"
              type="email"
              label="Email"
              :icon="Mail"
              :error="editErrors.email?.[0]"
              required
              autocomplete="email"
              data-testid="email-input"
            />

            <!-- Role -->
            <FloatingSelect
              id="edit-admin-role"
              v-model="editForm.role"
              label="Rôle"
              :icon="ShieldCheck"
              :options="roleOptions"
              :error="editErrors.role?.[0]"
              :disabled="isSelf"
              required
              data-testid="role-select"
            />

            <p v-if="isSelf" class="text-xs text-amber-600">
              Vous ne pouvez pas modifier votre propre rôle.
            </p>

            <!-- Actions -->
            <div class="flex items-center gap-3 pt-2">
              <button
                type="submit"
                :disabled="isLoading"
                class="flex-1 inline-flex items-center justify-center gap-2 py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                data-testid="save-button"
              >
                <Loader2 v-if="isLoading" class="h-4 w-4 animate-spin" />
                <Save v-else class="h-4 w-4" />
                {{ isLoading ? 'Enregistrement...' : 'Enregistrer' }}
              </button>
              <button
                type="button"
                @click="goBack"
                class="px-6 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors inline-flex items-center gap-2"
                data-testid="cancel-button"
              >
                <X class="h-4 w-4" />
                Annuler
              </button>
            </div>
          </form>
        </div>

        <!-- Send Password Reset Link (superadmin only, not for self) -->
        <div
          v-if="adminAuthStore.isSuperAdmin && !isSelf"
          class="mt-4 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"
          data-testid="reset-link-section"
        >
          <h3 class="text-sm font-medium text-gray-900 mb-2">Réinitialisation du mot de passe</h3>
          <p class="text-xs text-gray-500 mb-4">
            Envoyez un lien de réinitialisation de mot de passe à cet administrateur par email.
          </p>
          <button
            type="button"
            :disabled="isSendingResetLink"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            data-testid="send-reset-link-btn"
            @click="handleSendResetLink"
          >
            <Loader2 v-if="isSendingResetLink" class="h-4 w-4 animate-spin" />
            <KeyRound v-else class="h-4 w-4" />
            {{ isSendingResetLink ? 'Envoi en cours...' : 'Envoyer un lien de réinitialisation' }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
