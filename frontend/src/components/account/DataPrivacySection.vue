<script setup lang="ts">
/**
 * DataPrivacySection - User data rights (Art. 437-443 Code du Numerique)
 * Provides: data export (portability) and account deletion (right to be forgotten)
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { Download, Trash2, AlertTriangle, Loader2, Eye, X } from 'lucide-vue-next'
import apiClient, { getCsrfCookie } from '@/services/apiClient'
import { formatApiError } from '@/services/errorFormatter'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

interface DeleteAccountResponse {
  message?: string
  warning?: string | null
}

const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

// Export state
const isExporting = ref(false)

// Delete state
const showDeleteDialog = ref(false)
const deletePassword = ref('')
const deleteError = ref<string | null>(null)
const isDeleting = ref(false)

async function handleExport() {
  isExporting.value = true
  try {
    const response = await apiClient.get('/user/data-export')
    const jsonStr = JSON.stringify(response.data.data, null, 2)
    const blob = new Blob([jsonStr], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `weact-mes-donnees-${new Date().toISOString().slice(0, 10)}.json`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
    toast.success('Vos données ont été exportées avec succès.')
  } catch {
    toast.error('Erreur lors de l\'export. Veuillez réessayer.')
  } finally {
    isExporting.value = false
  }
}

function openDeleteDialog() {
  showDeleteDialog.value = true
  deletePassword.value = ''
  deleteError.value = null
}

function closeDeleteDialog() {
  showDeleteDialog.value = false
  deletePassword.value = ''
  deleteError.value = null
}

async function handleDelete() {
  if (!deletePassword.value) {
    deleteError.value = 'Veuillez entrer votre mot de passe.'
    return
  }

  isDeleting.value = true
  deleteError.value = null

  try {
    await getCsrfCookie()
    const response = await apiClient.delete<DeleteAccountResponse>('/user/account', {
      data: { password: deletePassword.value },
    })

    authStore.clearAuth()
    router.push({ name: 'home' })
    toast.success(response.data.message || 'Votre compte a été supprimé. Vos données ont été anonymisées.')
    if (response.data.warning) {
      toast.warning(response.data.warning, { duration: 7000 })
    }
  } catch (err) {
    deleteError.value = formatApiError(err, 'Erreur lors de la suppression. Vérifiez votre mot de passe.')
  } finally {
    isDeleting.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center gap-2 mb-2">
      <Eye :size="18" class="text-teal-600" />
      <h2 class="text-base font-medium text-gray-900">Mes données personnelles</h2>
    </div>
    <p class="text-xs text-gray-500 leading-relaxed">
      Conformément aux articles 437 à 443 du Code du Numérique du Bénin, vous disposez d'un droit d'accès,
      de portabilité et de suppression de vos données personnelles.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <!-- Export button -->
      <button
        @click="handleExport"
        :disabled="isExporting"
        class="flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-[#198496] bg-[#198496]/5 border border-[#198496]/15 rounded-lg hover:bg-[#198496]/10 transition-colors disabled:opacity-50 cursor-pointer"
      >
        <Loader2 v-if="isExporting" :size="16" class="animate-spin" />
        <Download v-else :size="16" />
        Télécharger mes données
      </button>

      <!-- Delete button -->
      <button
        @click="openDeleteDialog"
        class="flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-red-600 bg-red-50 border border-red-100 rounded-lg hover:bg-red-100 transition-colors cursor-pointer"
      >
        <Trash2 :size="16" />
        Supprimer mon compte
      </button>
    </div>
  </div>

  <!-- Delete confirmation dialog -->
  <Teleport to="body">
    <div
      v-if="showDeleteDialog"
      class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
    >
      <div class="absolute inset-0 bg-black/50" @click="closeDeleteDialog" />

      <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full">
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-100">
          <div class="flex items-center gap-2 text-red-600">
            <AlertTriangle :size="18" />
            <h3 class="text-sm font-semibold">Supprimer mon compte</h3>
          </div>
          <button @click="closeDeleteDialog" class="p-1 text-gray-400 hover:text-gray-600 cursor-pointer">
            <X :size="18" />
          </button>
        </div>

        <!-- Body -->
        <div class="p-4 space-y-4">
          <div class="p-3 bg-red-50 rounded-md border border-red-100">
            <p class="text-xs text-red-700 leading-relaxed font-medium">
              Cette action est irréversible. Votre compte sera désactivé et vos données personnelles seront anonymisées :
            </p>
            <ul class="mt-2 text-xs text-red-600 space-y-1 list-disc pl-4">
              <li>Votre profil ne sera plus visible publiquement</li>
              <li>Votre nom, email et informations personnelles seront anonymisés</li>
              <li>Vos photos et vidéos seront supprimées</li>
              <li>Vous ne pourrez plus vous connecter</li>
              <li>L'historique des transactions sera conservé (obligation légale de 10 ans)</li>
            </ul>
          </div>

          <div>
            <label for="delete-password" class="block text-xs font-medium text-gray-700 mb-1">
              Confirmez avec votre mot de passe
            </label>
            <input
              id="delete-password"
              v-model="deletePassword"
              type="password"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-red-300 focus:border-red-400 outline-none"
              placeholder="Votre mot de passe actuel"
              @keyup.enter="handleDelete"
            />
            <p v-if="deleteError" class="mt-1 text-xs text-red-500">{{ deleteError }}</p>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-2 p-4 border-t border-gray-100">
          <button
            @click="closeDeleteDialog"
            class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200 transition-colors cursor-pointer"
          >
            Annuler
          </button>
          <button
            @click="handleDelete"
            :disabled="isDeleting || !deletePassword"
            class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 transition-colors disabled:opacity-50 cursor-pointer"
          >
            <span v-if="isDeleting" class="flex items-center gap-1">
              <Loader2 :size="14" class="animate-spin" />
              Suppression...
            </span>
            <span v-else>Supprimer définitivement</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
