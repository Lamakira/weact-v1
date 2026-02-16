<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { RouterLink, useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { Button } from '@/components/ui/button'
import { NotificationBell } from '@/features/notification/components'
import { Menu, X } from 'lucide-vue-next'
import logoNoir from '@/assets/images/logonoir.png'

const authStore = useAuthStore()
const { logout, isLoading } = useAuth()
const router = useRouter()
const route = useRoute()
const toast = useToast()

// Mobile menu state
const isMobileMenuOpen = ref(false)
const mobileMenuRef = ref<HTMLElement | null>(null)
const mobileMenuButtonRef = ref<HTMLElement | null>(null)

function toggleMobileMenu(): void {
  isMobileMenuOpen.value = !isMobileMenuOpen.value
}

function closeMobileMenu(): void {
  isMobileMenuOpen.value = false
}

// Close menu on route change
watch(() => route.path, () => {
  closeMobileMenu()
})

// Handle Escape key press to close menu
function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && isMobileMenuOpen.value) {
    closeMobileMenu()
    // Return focus to the menu button
    mobileMenuButtonRef.value?.focus()
  }
}

// Handle click outside to close menu
function handleClickOutside(event: MouseEvent): void {
  if (!isMobileMenuOpen.value) return

  const target = event.target as Node
  const isClickInsideMenu = mobileMenuRef.value?.contains(target)
  const isClickOnButton = mobileMenuButtonRef.value?.contains(target)

  if (!isClickInsideMenu && !isClickOnButton) {
    closeMobileMenu()
  }
}

// Focus first focusable element when menu opens
watch(isMobileMenuOpen, async (isOpen) => {
  if (isOpen) {
    await nextTick()
    const firstLink = mobileMenuRef.value?.querySelector('a, button') as HTMLElement | null
    firstLink?.focus()
  }
})

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.removeEventListener('click', handleClickOutside)
})

async function handleLogout(): Promise<void> {
  await logout()
  closeMobileMenu()
  toast.success('Vous avez été déconnecté')
  router.push({ name: 'home' })
}
</script>

<template>
  <header class="bg-white border-b border-gray-200 relative" data-testid="app-header">
    <div class="max-w-7xl mx-auto px-4 py-4">
      <div class="flex items-center justify-between">
        <!-- Logo -->
        <RouterLink
          to="/"
          class="flex-shrink-0"
          data-testid="header-logo"
        >
          <img :src="logoNoir" alt="WEACT" class="h-8" />
        </RouterLink>

        <!-- Center Navigation (Desktop) -->
        <nav
          class="hidden lg:flex items-center gap-8"
          role="navigation"
          aria-label="Navigation principale"
          data-testid="header-desktop-nav"
        >
          <RouterLink
            to="/faces"
            class="text-sm text-gray-700 hover:text-[#198496] transition-colors"
            data-testid="nav-faces"
          >
            Trouver des faces
          </RouterLink>
          <RouterLink
            to="/missions"
            class="text-sm text-gray-700 hover:text-[#198496] transition-colors"
            data-testid="nav-missions"
          >
            Missions
          </RouterLink>
          <RouterLink
            to="/ressources"
            class="text-sm text-gray-700 hover:text-[#198496] transition-colors"
            data-testid="nav-ressources"
          >
            Ressources
          </RouterLink>

          <!-- Face-specific links -->
          <RouterLink
            v-if="authStore.isAuthenticated && authStore.isFace"
            to="/face/candidatures"
            class="text-sm text-gray-700 hover:text-[#198496] transition-colors"
            data-testid="nav-candidatures"
          >
            Mes candidatures
          </RouterLink>

          <!-- Producer-specific links -->
          <RouterLink
            v-if="authStore.isAuthenticated && authStore.isProducer"
            to="/producer/missions"
            class="text-sm text-gray-700 hover:text-[#198496] transition-colors"
            data-testid="nav-producer-missions"
          >
            Mes missions
          </RouterLink>
        </nav>

        <!-- Right Actions (Desktop) -->
        <div class="hidden lg:flex items-center gap-3" data-testid="header-desktop-actions">
          <!-- Guest Navigation -->
          <template v-if="!authStore.isAuthenticated">
            <Button
              as-child
              class="bg-black hover:bg-gray-800 text-white text-sm font-medium px-5 py-2 rounded-md"
              data-testid="cta-poster-mission"
            >
              <RouterLink to="/register/producer">Poster une mission</RouterLink>
            </Button>
            <Button
              variant="outline"
              as-child
              class="text-[#198496] border-[#198496] hover:bg-[#198496]/5 text-sm font-medium px-5 py-2 rounded-md"
              data-testid="cta-devenir-face"
            >
              <RouterLink to="/register/face">Devenir une face</RouterLink>
            </Button>
            <RouterLink
              to="/login"
              class="text-sm text-gray-700 hover:text-[#198496] transition-colors ml-2"
              data-testid="link-login"
            >
              Se connecter
            </RouterLink>
          </template>

          <!-- Authenticated Navigation -->
          <template v-else>
            <!-- Notification Bell -->
            <NotificationBell data-testid="header-notifications" />

            <RouterLink
              :to="authStore.isFace ? '/face/dashboard' : '/producer/dashboard'"
              class="text-sm text-gray-700 hover:text-[#198496] transition-colors"
              data-testid="link-dashboard"
            >
              Dashboard
            </RouterLink>
            <Button
              @click="handleLogout"
              :disabled="isLoading"
              variant="outline"
              size="sm"
              data-testid="btn-logout"
              aria-label="Se déconnecter"
            >
              Déconnexion
            </Button>
          </template>
        </div>

        <!-- Mobile Menu Button -->
        <button
          ref="mobileMenuButtonRef"
          @click.stop="toggleMobileMenu"
          class="lg:hidden p-2 text-gray-700 hover:text-[#198496] transition-colors rounded-lg hover:bg-gray-100"
          :aria-expanded="isMobileMenuOpen"
          aria-controls="mobile-menu"
          aria-label="Ouvrir le menu de navigation"
          data-testid="header-mobile-menu-button"
        >
          <Menu v-if="!isMobileMenuOpen" class="w-6 h-6" />
          <X v-else class="w-6 h-6" />
        </button>
      </div>
    </div>

    <!-- Mobile Navigation Menu -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 -translate-y-4"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-4"
    >
      <div
        v-if="isMobileMenuOpen"
        ref="mobileMenuRef"
        id="mobile-menu"
        class="absolute top-full left-0 w-full bg-white border-b border-gray-200 shadow-lg lg:hidden z-[100]"
        role="navigation"
        aria-label="Menu de navigation mobile"
        data-testid="header-mobile-menu"
      >
        <div class="px-4 py-6 flex flex-col space-y-6">
          <!-- Navigation Links -->
          <nav class="flex flex-col space-y-4" data-testid="mobile-nav-links">
            <RouterLink
              to="/faces"
              class="text-sm text-gray-700 hover:text-[#198496] transition-colors"
              @click="closeMobileMenu"
            >
              Trouver des faces
            </RouterLink>
            <RouterLink
              to="/missions"
              class="text-sm text-gray-700 hover:text-[#198496] transition-colors"
              @click="closeMobileMenu"
            >
              Missions
            </RouterLink>
            <RouterLink
              to="/ressources"
              class="text-sm text-gray-700 hover:text-[#198496] transition-colors"
              @click="closeMobileMenu"
            >
              Ressources
            </RouterLink>

          </nav>

          <!-- Divider -->
          <div class="h-[1px] w-full bg-gray-100"></div>

          <!-- CTAs -->
          <div class="flex flex-col space-y-3" data-testid="mobile-ctas">
            <!-- Guest CTAs -->
            <template v-if="!authStore.isAuthenticated">
              <RouterLink
                to="/register/producer"
                class="w-full text-center py-3 text-sm font-medium bg-black text-white rounded-md hover:bg-gray-800 transition-colors"
                @click="closeMobileMenu"
              >
                Poster une mission
              </RouterLink>
              <RouterLink
                to="/register/face"
                class="w-full text-center py-3 text-sm font-medium text-[#198496] border border-[#198496] rounded-md hover:bg-[#198496]/5 transition-colors"
                @click="closeMobileMenu"
              >
                Devenir une face
              </RouterLink>
              <RouterLink
                to="/login"
                class="w-full text-center py-3 text-sm text-gray-700 hover:text-[#198496] transition-colors"
                @click="closeMobileMenu"
              >
                Se connecter
              </RouterLink>
            </template>

            <!-- Authenticated CTAs -->
            <template v-else>
              <RouterLink
                :to="authStore.isFace ? '/face/dashboard' : '/producer/dashboard'"
                class="w-full text-center py-3 text-sm font-medium bg-[#198496] text-white rounded-md hover:bg-[#146c7a] transition-colors"
                @click="closeMobileMenu"
              >
                Dashboard
              </RouterLink>
              <button
                @click="handleLogout"
                :disabled="isLoading"
                class="w-full text-center py-3 text-sm text-gray-700 hover:text-[#198496] transition-colors disabled:opacity-50"
                aria-label="Se déconnecter"
              >
                {{ isLoading ? 'Déconnexion...' : 'Déconnexion' }}
              </button>
            </template>
          </div>
        </div>
      </div>
    </Transition>
  </header>
</template>
