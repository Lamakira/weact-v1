<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { Button } from '@/components/ui/button'
import logoNoir from '@/assets/images/logonoir.svg'

const authStore = useAuthStore()
const { logout, isLoading } = useAuth()
const router = useRouter()
const toast = useToast()

async function handleLogout(): Promise<void> {
  await logout()
  toast.success('Vous avez été déconnecté')
  router.push({ name: 'home' })
}
</script>

<template>
  <header class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 py-4">
      <div class="flex items-center justify-between">
        <!-- Logo -->
        <RouterLink to="/" class="flex-shrink-0">
          <img :src="logoNoir" alt="WEACT" class="h-8" />
        </RouterLink>

        <!-- Center Navigation -->
        <nav class="hidden md:flex items-center gap-8">
          <RouterLink
            to="/faces"
            class="text-gray-700 hover:text-primary transition-colors"
          >
            Trouver des faces
          </RouterLink>
          <RouterLink
            to="/missions"
            class="text-gray-700 hover:text-primary transition-colors"
          >
            Missions
          </RouterLink>
          <RouterLink
            to="/ressources"
            class="text-gray-700 hover:text-primary transition-colors"
          >
            Ressources
          </RouterLink>

          <!-- Face-specific links -->
          <RouterLink
            v-if="authStore.isAuthenticated && authStore.isFace"
            to="/face/candidatures"
            class="text-gray-700 hover:text-primary transition-colors"
          >
            Mes candidatures
          </RouterLink>

          <!-- Producer-specific links -->
          <RouterLink
            v-if="authStore.isAuthenticated && authStore.isProducer"
            to="/producer/missions"
            class="text-gray-700 hover:text-primary transition-colors"
          >
            Mes missions
          </RouterLink>
        </nav>

        <!-- Right Actions -->
        <div class="flex items-center gap-4">
          <!-- Guest Navigation -->
          <template v-if="!authStore.isAuthenticated">
            <Button as-child>
              <RouterLink to="/register/producer">Poster une mission</RouterLink>
            </Button>
            <Button variant="outline" as-child>
              <RouterLink to="/register/face">Devenir une face</RouterLink>
            </Button>
            <RouterLink
              to="/login"
              class="text-gray-700 hover:text-primary transition-colors"
            >
              Se connecter
            </RouterLink>
          </template>

          <!-- Authenticated Navigation -->
          <template v-else>
            <RouterLink
              :to="authStore.isFace ? '/face/dashboard' : '/producer/dashboard'"
              class="text-gray-700 hover:text-primary transition-colors"
            >
              Dashboard
            </RouterLink>
            <Button
              @click="handleLogout"
              :disabled="isLoading"
              variant="outline"
              size="sm"
            >
              Déconnexion
            </Button>
          </template>
        </div>
      </div>
    </div>
  </header>
</template>
