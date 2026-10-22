<script setup lang="ts">
/**
 * ProducerLayout
 * Wrapper layout for all Producer dashboard pages.
 * Uses DashboardLayout with Producer-specific sidebar items.
 * Child routes render via <router-view> in the content area.
 */
import { onMounted, onBeforeUnmount, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { LayoutDashboard, FileText, MessageCircle, User, PlusCircle, Users, CalendarCheck, Wallet, BadgeCheck, FolderDown, House, Briefcase } from 'lucide-vue-next'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useAuthStore } from '@/stores/auth'
import { DashboardLayout, KeepAliveRouterView, type SidebarItem } from '@/components/layout'
import { resolveSidebarTitle } from '@/components/layout/resolveSidebarTitle'
import { useProducerProfilePhoto } from '@/features/producer/composables/useProducerProfilePhoto'
import { useActionCountsStore } from '@/stores/actionCounts'
import { useMessagesUnreadStore } from '@/stores/messagesUnread'
import AccountAlertBar from '@/components/AccountAlertBar.vue'

const route = useRoute()
const authStore = useAuthStore()
const { logout, isLoading } = useAuth()
const { profile, fetchProfile } = useProducerProfilePhoto()
const actionCountsStore = useActionCountsStore()
const messagesUnreadStore = useMessagesUnreadStore()

// Sidebar navigation items for Producer dashboard. Computed so the badges
// (missions / bookings / livrables à traiter, messages non lus) follow their counters.
const sidebarItems = computed<SidebarItem[]>(() => [
  { label: 'Tableau de bord', icon: LayoutDashboard, to: '/producer/dashboard' },
  { label: 'Mes missions', icon: FileText, to: '/producer/missions',
    badge: actionCountsStore.counts.missions },
  { label: 'Publier une mission', icon: PlusCircle, to: '/producer/missions/publish' },
  { label: 'Liste des Faces', icon: Users, to: '/producer/faces' },
  { label: 'Mes bookings', icon: CalendarCheck, to: '/producer/bookings',
    badge: actionCountsStore.counts.bookings },
  { label: 'Validation des livrables', icon: BadgeCheck, to: '/producer/ugc/validation',
    badge: actionCountsStore.counts.livrables },
  { label: 'Mes vidéos UGC', icon: FolderDown, to: '/producer/ugc/videos' },
  { label: 'Messages', icon: MessageCircle, to: '/producer/messages', match: ['/producer/conversations'],
    badge: messagesUnreadStore.count, badgeMax: 9, badgeKind: 'messages' },
  { label: 'Portefeuille', icon: Wallet, to: '/producer/wallet' },
  { label: 'Mon profil', icon: User, to: '/producer/profile' },
])

// Bottom tab bar (mobile): the 5 main destinations; the drawer keeps the rest.
// Le profil reste accessible par le menu de l'avatar (en-tête).
const mobileTabs = computed<SidebarItem[]>(() => [
  { label: 'Accueil', icon: House, to: '/producer/dashboard' },
  { label: 'Missions', icon: Briefcase, to: '/producer/missions',
    badge: actionCountsStore.counts.missions },
  { label: 'Bookings', icon: CalendarCheck, to: '/producer/bookings',
    badge: actionCountsStore.counts.bookings },
  { label: 'Messages', icon: MessageCircle, to: '/producer/messages', match: ['/producer/conversations'],
    badge: messagesUnreadStore.count, badgeMax: 9, badgeKind: 'messages' },
  { label: 'Livrables', icon: BadgeCheck, to: '/producer/ugc/validation',
    badge: actionCountsStore.counts.livrables },
])

// Header title = label of the sidebar item owning the current route.
const pageTitle = computed(() => resolveSidebarTitle(sidebarItems.value, route.path))

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

// Fetch profile on mount to get avatar; badges d'actions : chargement au boot, puis événements / focus / navigation
onMounted(async () => {
  actionCountsStore.start()
  // Badge « Messages » : 1 fetch au boot, puis événements / focus / repli (pas de fetch par navigation)
  messagesUnreadStore.start('producer')
  try {
    await fetchProfile()
  } catch {
    // Silently fail - avatar will show fallback
  }
})

onBeforeUnmount(() => {
  messagesUnreadStore.stop()
  actionCountsStore.stop()
})

// The layout now persists across child navigations (App.vue keys it by the
// layout's own route): refresh the action badges on each one (an action made by the
// user themself emits no event towards them), as the per-navigation remount used to.
watch(
  () => route.path,
  () => {
    void actionCountsStore.refresh()
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
    <!-- Barre d'alertes unique (e-mail à vérifier). Le rappel WhatsApp est une ligne
         de la to-do du tableau de bord. -->
    <template #alerts>
      <AccountAlertBar audience="producer" />
    </template>

    <!-- Child routes render here — meta.keepAlive-driven caching + page
         transitions live in the shared KeepAliveRouterView (see its header). -->
    <KeepAliveRouterView />
  </DashboardLayout>
</template>
