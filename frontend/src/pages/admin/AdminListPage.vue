<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { Plus, ShieldCheck, AlertCircle, Loader2, Pencil, Trash2 } from 'lucide-vue-next'
import { useAdmins } from '@/features/admin/composables/useAdmins'
import { useAdminAuthStore } from '@/stores/adminAuth'
import { getAdminRoleLabel, getAdminRoleColor } from '@/features/admin/utils/adminLabels'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const adminAuthStore = useAdminAuthStore()
const toast = useToast()
const { admins, pagination, isLoading, error, fetchAdmins, deleteAdmin } = useAdmins()

const currentAdminId = computed(() => adminAuthStore.admin?.id)

onMounted(() => {
  fetchAdmins()
})

const hasAdmins = computed(() => admins.value.length > 0)
const totalPages = computed(() => pagination.value?.last_page ?? 1)
const currentPage = computed(() => pagination.value?.current_page ?? 1)

function goToPage(page: number): void {
  fetchAdmins(page)
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

function goToEdit(adminId: string): void {
  router.push({ name: 'admin-admins-edit', params: { id: adminId } })
}

async function handleDelete(adminId: string, adminName: string): Promise<void> {
  const confirmed = window.confirm(
    `Êtes-vous sûr de vouloir supprimer le compte de ${adminName} ? Cette action est irréversible.`,
  )
  if (!confirmed) return

  const result = await deleteAdmin(adminId)
  if (result.success) {
    toast.success(result.message ?? 'Administrateur supprimé avec succès')
    fetchAdmins(currentPage.value)
  } else {
    toast.error(result.message ?? 'Erreur lors de la suppression')
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Administrateurs</h1>
        <p class="mt-1 text-sm text-gray-500">
          Gérez les comptes administrateurs de la plateforme
        </p>
      </div>
      <RouterLink
        to="/admin/admins/create"
        class="inline-flex items-center gap-2 self-start rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-700 transition-colors sm:self-auto"
        data-testid="create-admin-button"
      >
        <Plus class="h-4 w-4" />
        Créer un admin
      </RouterLink>
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="rounded-lg bg-red-50 border border-red-200 p-4 flex items-start gap-3"
      role="alert"
      data-testid="error-message"
    >
      <AlertCircle class="h-5 w-5 text-red-500 mt-0.5 shrink-0" />
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="flex items-center justify-center py-12"
      data-testid="loading-state"
    >
      <Loader2 class="h-8 w-8 text-primary-500 animate-spin" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="!hasAdmins && !error"
      class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center"
      data-testid="empty-state"
    >
      <ShieldCheck class="mx-auto h-12 w-12 text-gray-400" />
      <h3 class="mt-4 text-lg font-medium text-gray-900">Aucun administrateur</h3>
      <p class="mt-2 text-sm text-gray-500">
        Commencez par créer un compte administrateur.
      </p>
      <RouterLink
        to="/admin/admins/create"
        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary-500 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 transition-colors"
      >
        <Plus class="h-4 w-4" />
        Créer un admin
      </RouterLink>
    </div>

    <!-- Admin Table -->
    <div
      v-else-if="hasAdmins"
      class="overflow-x-auto rounded-xl border border-gray-200 bg-white"
      data-testid="admins-table"
    >
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Nom
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Email
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Rôle
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Date de création
            </th>
            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr
            v-for="admin in admins"
            :key="admin.id"
            class="hover:bg-gray-50 transition-colors"
          >
            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
              {{ admin.name }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ admin.email }}
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="getAdminRoleColor(admin.role)"
                data-testid="role-badge"
              >
                {{ getAdminRoleLabel(admin.role) }}
              </span>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ formatDate(admin.created_at) }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <button
                  @click="goToEdit(admin.id)"
                  class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                  data-testid="edit-button"
                >
                  <Pencil class="h-3.5 w-3.5" />
                  Modifier
                </button>
                <button
                  v-if="admin.id !== currentAdminId"
                  @click="handleDelete(admin.id, admin.name)"
                  class="inline-flex items-center gap-1.5 rounded-lg bg-red-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700 transition-colors"
                  data-testid="delete-button"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                  Supprimer
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div
        v-if="totalPages > 1"
        class="flex items-center justify-between border-t border-gray-200 bg-white px-6 py-3"
        data-testid="pagination"
      >
        <p class="text-sm text-gray-700">
          Page {{ currentPage }} sur {{ totalPages }}
          <span class="text-gray-500">({{ pagination?.total }} résultats)</span>
        </p>
        <div class="flex gap-2">
          <button
            :disabled="currentPage <= 1"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            data-testid="pagination-prev"
            @click="goToPage(currentPage - 1)"
          >
            Précédent
          </button>
          <button
            :disabled="currentPage >= totalPages"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            data-testid="pagination-next"
            @click="goToPage(currentPage + 1)"
          >
            Suivant
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
