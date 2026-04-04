<script setup lang="ts">
/**
 * ReportButton - Signalement de contenu illicite (Art. 498 Code du Numérique)
 * Affiche un bouton discret + modal de signalement.
 */
import type { AxiosError } from 'axios'
import { ref } from 'vue'
import { Flag, X, Loader2, CheckCircle } from 'lucide-vue-next'
import apiClient, { getCsrfCookie } from '@/services/apiClient'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

interface ApiErrorResponse {
  message?: string
  error?: {
    message?: string
  }
}

const props = defineProps<{
  reportableType: 'face' | 'mission'
  reportableId: number
}>()

const authStore = useAuthStore()
const router = useRouter()

const showModal = ref(false)
const reason = ref('')
const description = ref('')
const isSubmitting = ref(false)
const submitted = ref(false)
const error = ref<string | null>(null)

const reasons = [
  { value: 'contenu_inapproprie', label: 'Contenu inapproprié' },
  { value: 'usurpation_identite', label: 'Usurpation d\'identité' },
  { value: 'harcelement', label: 'Harcèlement' },
  { value: 'fraude', label: 'Fraude ou arnaque' },
  { value: 'autre', label: 'Autre' },
]

function openModal() {
  if (!authStore.isAuthenticated) {
    router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
    return
  }
  showModal.value = true
  reason.value = ''
  description.value = ''
  error.value = null
  submitted.value = false
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  if (!reason.value) {
    error.value = 'Veuillez sélectionner un motif.'
    return
  }

  isSubmitting.value = true
  error.value = null

  try {
    await getCsrfCookie()
    await apiClient.post('/reports', {
      reportable_type: props.reportableType,
      reportable_id: props.reportableId,
      reason: reason.value,
      description: description.value || null,
    })
    submitted.value = true
  } catch (err) {
    const apiError = err as AxiosError<ApiErrorResponse>
    const msg = apiError.response?.data?.error?.message ?? apiError.response?.data?.message
    error.value = msg || 'Une erreur est survenue. Veuillez réessayer.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <!-- Trigger button -->
  <button
    @click="openModal"
    class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-red-500 transition-colors cursor-pointer"
    title="Signaler ce contenu"
    data-testid="report-button"
  >
    <Flag :size="14" />
    <span>Signaler</span>
  </button>

  <!-- Modal -->
  <Teleport to="body">
    <div
      v-if="showModal"
      class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
    >
      <div class="absolute inset-0 bg-black/50" @click="closeModal" />

      <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full">
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-100">
          <div class="flex items-center gap-2">
            <Flag :size="16" class="text-red-500" />
            <h3 class="text-sm font-semibold text-gray-900">Signaler un contenu</h3>
          </div>
          <button @click="closeModal" class="p-1 text-gray-400 hover:text-gray-600 cursor-pointer">
            <X :size="18" />
          </button>
        </div>

        <!-- Success state -->
        <div v-if="submitted" class="p-6 text-center space-y-3">
          <div class="mx-auto w-12 h-12 rounded-full bg-green-50 flex items-center justify-center">
            <CheckCircle :size="24" class="text-green-500" />
          </div>
          <p class="text-sm font-medium text-gray-900">Signalement envoyé</p>
          <p class="text-xs text-gray-500">
            Merci pour votre signalement. Nous l'examinerons dans les meilleurs délais
            conformément à l'article 498 du Code du Numérique.
          </p>
          <button
            @click="closeModal"
            class="mt-2 px-4 py-1.5 text-xs font-medium text-white bg-[#198496] rounded hover:bg-[#146c7a] transition-colors cursor-pointer"
          >
            Fermer
          </button>
        </div>

        <!-- Form -->
        <template v-else>
          <div class="p-4 space-y-4">
            <p class="text-xs text-gray-500 leading-relaxed">
              Conformément à l'article 498 du Code du Numérique du Bénin, vous pouvez signaler
              tout contenu que vous estimez illicite. Nous traiterons votre signalement dans les meilleurs délais.
            </p>

            <!-- Reason select -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">
                Motif du signalement <span class="text-red-500">*</span>
              </label>
              <select
                v-model="reason"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-[#198496]/30 focus:border-[#198496] outline-none bg-white cursor-pointer"
                data-testid="report-reason-select"
              >
                <option value="" disabled>Sélectionnez un motif</option>
                <option v-for="r in reasons" :key="r.value" :value="r.value">
                  {{ r.label }}
                </option>
              </select>
            </div>

            <!-- Description -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">
                Description (facultatif)
              </label>
              <textarea
                v-model="description"
                rows="3"
                maxlength="1000"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-[#198496]/30 focus:border-[#198496] outline-none resize-none"
                placeholder="Décrivez le problème en détail..."
                data-testid="report-description"
              />
              <p class="text-right text-[10px] text-gray-400 mt-0.5">{{ description.length }}/1000</p>
            </div>

            <p v-if="error" class="text-xs text-red-500">{{ error }}</p>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2 p-4 border-t border-gray-100">
            <button
              @click="closeModal"
              class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200 transition-colors cursor-pointer"
            >
              Annuler
            </button>
            <button
              @click="handleSubmit"
              :disabled="isSubmitting || !reason"
              class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 transition-colors disabled:opacity-50 cursor-pointer"
            >
              <span v-if="isSubmitting" class="flex items-center gap-1">
                <Loader2 :size="14" class="animate-spin" />
                Envoi...
              </span>
              <span v-else>Envoyer le signalement</span>
            </button>
          </div>
        </template>
      </div>
    </div>
  </Teleport>
</template>
