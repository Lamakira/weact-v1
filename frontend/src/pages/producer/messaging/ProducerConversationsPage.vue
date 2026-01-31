<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Loader2, AlertCircle, MessageSquare, RefreshCw } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useProducerConversationsList } from '@/features/messaging/composables/useProducerConversationsList'
import ConversationListItem from '@/features/messaging/components/ConversationListItem.vue'
import EmailVerificationRequired from '@/components/EmailVerificationRequired.vue'

/**
 * LOGIC & STATE MANAGEMENT
 */
const router = useRouter()
const authStore = useAuthStore()

const {
  conversations,
  isLoading,
  isRefreshing,
  error,
  hasConversations,
  loadConversations,
  refreshConversations,
} = useProducerConversationsList()

/**
 * Handle refresh button click
 */
async function handleRefresh(): Promise<void> {
  await refreshConversations()
}

/**
 * Handle retry button click
 */
async function handleRetry(): Promise<void> {
  await loadConversations()
}

/**
 * Navigate to missions list
 */
function goToMissions(): void {
  router.push({ name: 'producer-missions' })
}

/**
 * LIFECYCLE
 */
onMounted(() => {
  loadConversations()
})
</script>

<template>
  <div class="min-h-screen bg-background pb-8" data-testid="producer-conversations-page">
    <!-- Email verification required -->
    <EmailVerificationRequired
      v-if="!authStore.isEmailVerified"
      title="Messagerie non disponible"
      message="Vous devez vérifier votre adresse email pour accéder à vos messages."
    />

    <template v-else>
    <!-- Header Section -->
    <header class="border-b border-border bg-card">
      <div class="container mx-auto flex items-center justify-between px-4 py-6 sm:px-6">
        <div>
          <h1 class="text-2xl font-bold text-foreground sm:text-3xl">
            Messages
          </h1>
          <p class="mt-1 text-muted-foreground">
            Vos conversations avec les Faces
          </p>
        </div>

        <!-- Refresh Button -->
        <button
          v-if="hasConversations && !isLoading"
          type="button"
          :disabled="isRefreshing"
          class="flex h-10 w-10 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-primary/5 hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
          aria-label="Actualiser les conversations"
          @click="handleRefresh"
        >
          <RefreshCw :class="['h-5 w-5', isRefreshing && 'animate-spin']" />
        </button>
      </div>
    </header>

    <main class="container mx-auto mt-6 px-4 sm:px-6">
      <!-- Loading State -->
      <div
        v-if="isLoading"
        class="flex flex-col items-center justify-center py-24"
      >
        <Loader2 class="h-12 w-12 animate-spin text-primary" />
        <p class="mt-4 text-muted-foreground">Chargement de vos conversations...</p>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error && !hasConversations"
        class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-destructive/20 bg-destructive/5 py-24 text-center"
      >
        <div class="mb-4 rounded-full bg-destructive/10 p-4 text-destructive">
          <AlertCircle class="h-10 w-10" />
        </div>
        <h3 class="text-xl font-bold text-foreground">Une erreur est survenue</h3>
        <p class="mt-2 max-w-xs text-muted-foreground">
          {{ error }}
        </p>
        <button
          type="button"
          class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-6 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90"
          @click="handleRetry"
        >
          Réessayer
        </button>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="!hasConversations"
        class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-muted py-24 text-center"
      >
        <div class="mb-4 rounded-full bg-muted p-4">
          <MessageSquare class="h-10 w-10 text-muted-foreground" />
        </div>
        <h3 class="text-xl font-bold text-foreground">Aucune conversation</h3>
        <p class="mt-2 max-w-xs text-muted-foreground">
          Les discussions apparaîtront ici lorsque vous accepterez des candidatures.
        </p>
        <button
          type="button"
          class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-6 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90"
          @click="goToMissions"
        >
          Voir mes missions
        </button>
      </div>

      <!-- Conversations List -->
      <template v-else>
        <!-- Results count -->
        <p class="mb-4 text-sm text-muted-foreground">
          {{ conversations.length }} conversation{{ conversations.length > 1 ? 's' : '' }}
        </p>

        <!-- Conversations List -->
        <div class="space-y-3">
          <ConversationListItem
            v-for="conversation in conversations"
            :key="conversation.id"
            :conversation="conversation"
            route-name="producer-conversation"
          />
        </div>
      </template>
    </main>
    </template>
  </div>
</template>
