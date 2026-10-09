<script setup lang="ts">
/**
 * ProducerLayout
 * Wrapper layout for all Producer dashboard pages.
 * Uses DashboardLayout with Producer-specific sidebar items.
 * Child routes render via <router-view> in the content area.
 */
import { onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { LayoutDashboard, FileText, MessageCircle, User, PlusCircle, Users, CalendarCheck, Wallet, BadgeCheck, FolderDown, House, Briefcase } from 'lucide-vue-next'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useAuthStore } from '@/stores/auth'
import { DashboardLayout, KeepAliveRouterView, type SidebarItem } from '@/components/layout'
import { resolveSidebarTitle } from '@/components/layout/resolveSidebarTitle'
import { useProducerProfilePhoto } from '@/features/producer/composables/useProducerProfilePhoto'
import { useProducerBasicInfo } from '@/features/producer/composables/useProducerBasicInfo'
import { useUgcValidationCountStore } from '@/stores/ugcValidationCount'
import EmailVerificationBanner from '@/components/EmailVerificationBanner.vue'
import WhatsappMissingBanner from '@/components/WhatsappMissingBanner.vue'

const route = useRoute()
const authStore = useAuthStore()
const { logout, isLoading } = useAuth()
const { profile, fetchProfile } = useProducerProfilePhoto()
// Basic-info is a shared cached resource: saving the number on the profile page
// updates it in place, so the banner disappears without a refetch.
const { basicInfo, fetchBasicInfo } = useProducerBasicInfo()

// "Loaded" is derived from the shared cache itself: a reset (logout) hides the banner.
const basicInfoLoaded = computed(() => basicInfo.value !== null)
// Present = dialable (at least one digit), same rule as the admin side
// (App\Support\Whatsapp::isDialable / has_whatsapp).
const hasWhatsapp = computed(() => /\d/.test(basicInfo.value?.whatsapp_number ?? ''))

async function loadBasicInfo(): Promise<void> {
  try {
    await fetchBasicInfo()
  } catch {
    // Silently fail - the banner stays hidden; retried on the next route change
  }
}
const ugcValidationCountStore = useUgcValidationCountStore()

// Sidebar navigation items for Producer dashboard. Computed so the « Validation
// des livrables » badge reactively follows the in_review count (only that item).
const sidebarItems = computed<SidebarItem[]>(() => [
  { label: 'Tableau de bord', icon: LayoutDashboard, to: '/producer/dashboard' },
  { label: 'Mes missions', icon: FileText, to: '/producer/missions' },
  { label: 'Publier une mission', icon: PlusCircle, to: '/producer/missions/publish' },
  { label: 'Liste des Faces', icon: Users, to: '/producer/faces' },
  { label: 'Mes bookings', icon: CalendarCheck, to: '/producer/bookings' },
  { label: 'Validation des livrables', icon: BadgeCheck, to: '/producer/ugc/validation',
    badge: ugcValidationCountStore.count },
  { label: 'Mes vidéos UGC', icon: FolderDown, to: '/producer/ugc/videos' },
  { label: 'Messages', icon: MessageCircle, to: '/producer/messages' },
  { label: 'Portefeuille', icon: Wallet, to: '/producer/wallet' },
  { label: 'Mon profil', icon: User, to: '/producer/profile' },
])

// Bottom tab bar (mobile): the 4 main destinations; the drawer keeps the rest
const mobileTabs: SidebarItem[] = [
  { label: 'Accueil', icon: House, to: '/producer/dashboard' },
  { label: 'Missions', icon: Briefcase, to: '/producer/missions' },
  { label: 'Messages', icon: MessageCircle, to: '/producer/messages' },
  { label: 'Profil', icon: User, to: '/producer/profile' },
]

// Header title = label of the sidebar item owning the current route.
const pageTitle = computed(() => resolveSidebarTitle(sidebarItems.value, route.path))

// The profile page has its own WhatsApp field: no reminder there.
const isProfilePage = computed(() => route.path === '/producer/profile')

// Computed user name from Producer profile
const userName = computed(() => {
  if (profile.value) {
    return profile.value.display_name
  }
  return ''
})

// Avatar URL: prefer profile photo, fall back to agency logo for agency-type producers
const avatarUrl = computed(() => {
  if (!profile.value) return null
  return profile.value.profile_photo_url ?? profile.value.agency_logo_url ?? null
})

// Fetch profile on mount to get avatar + the in_review validation count (badge)
onMounted(async () => {
  void ugcValidationCountStore.fetchCount()
  try {
    await fetchProfile()
  } catch {
    // Silently fail - avatar will show fallback
  }

  await loadBasicInfo()
})

// The layout now persists across child navigations (App.vue keys it by the
// layout's own route): refresh the sidebar "Validation des livrables" badge on
// each one, as the per-navigation remount used to do before keep-alive.
// (Mirrors the route.path watch FaceLayout already has for its banner.)
watch(
  () => route.path,
  () => {
    void ugcValidationCountStore.fetchCount()
    // Retry a failed basic-info load (mirrors FaceLayout) — only while not loaded.
    if (!basicInfoLoaded.value) void loadBasicInfo()
  },
)

async function handleLogout(): Promise<void> {
  await logout()
}
</script>

<template>
  <DashboardLayout
    :sidebar-items="sidebarItems"
    :mobile-tabs="mobileTabs"
    :title="pageTitle"
    :user-email="authStore.user?.email"
    :user-name="userName"
    :avatar-url="avatarUrl"
    :is-logging-out="isLoading"
    profile-route="/producer/profile"
    @logout="handleLogout"
  >
    <!-- Email verification banner (shown if email not verified) -->
    <EmailVerificationBanner
      v-if="!authStore.isEmailVerified"
      data-testid="email-verification-banner"
    />

    <!-- WhatsApp reminder (shown until the Producer sets their number; admin-only data) -->
    <WhatsappMissingBanner
      v-if="basicInfoLoaded && !hasWhatsapp && !isProfilePage"
      title="Renseignez votre numéro WhatsApp"
      message="Ajoutez votre numéro WhatsApp pour que l'équipe WeAct puisse vous joindre rapidement."
      cta-label="Renseigner mon WhatsApp"
      to="/producer/profile?focus=whatsapp"
    />

    <!-- Child routes render here — meta.keepAlive-driven caching + page
         transitions live in the shared KeepAliveRouterView (see its header). -->
    <KeepAliveRouterView />
  </DashboardLayout>
</template>
