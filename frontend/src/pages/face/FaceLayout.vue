<script setup lang="ts">
/**
 * FaceLayout
 * Wrapper layout for all Face dashboard pages.
 * Uses DashboardLayout with Face-specific sidebar items.
 * Child routes render via <router-view> in the content area.
 */
import { onMounted, onBeforeUnmount, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { LayoutDashboard, FileText, MessageCircle, User, Briefcase, CalendarCheck, Wallet, CreditCard, Tags, Video, House } from 'lucide-vue-next'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useAuthStore } from '@/stores/auth'
import { DashboardLayout, KeepAliveRouterView, type SidebarItem } from '@/components/layout'
import { resolveSidebarTitle } from '@/components/layout/resolveSidebarTitle'
import { useProfilePhoto } from '@/features/face/composables/useProfilePhoto'
import { useMessagesUnreadStore } from '@/stores/messagesUnread'
import { useActionCountsStore } from '@/stores/actionCounts'
import AccountAlertBar from '@/components/AccountAlertBar.vue'

const route = useRoute()
const authStore = useAuthStore()
const { logout, isLoading } = useAuth()
const { profile, fetchProfile } = useProfilePhoto()

const messagesUnreadStore = useMessagesUnreadStore()
const actionCountsStore = useActionCountsStore()

// Sidebar navigation items for Face dashboard. Computed so the « Messages » badge
// reactively follows the unread-conversations count (only that item).
const sidebarItems = computed<SidebarItem[]>(() => [
  { label: 'Tableau de bord', icon: LayoutDashboard, to: '/face/dashboard' },
  { label: 'Voir les missions', icon: Briefcase, to: '/face/missions' },
  { label: 'Missions UGC', icon: Video, to: '/face/ugc-missions' },
  { label: 'Mes candidatures', icon: FileText, to: '/face/candidatures',
    badge: actionCountsStore.counts.candidatures },
  { label: 'Mes bookings', icon: CalendarCheck, to: '/face/bookings',
    badge: actionCountsStore.counts.bookings },
  { label: 'Messages', icon: MessageCircle, to: '/face/messages', match: ['/face/conversations'],
    badge: messagesUnreadStore.count, badgeMax: 9, badgeKind: 'messages' },
  { label: 'Portefeuille', icon: Wallet, to: '/face/wallet' },
  { label: 'Facturation', icon: CreditCard, to: '/face/billing' },
  { label: 'Tarifs', icon: Tags, to: '/pricing' },
  { label: 'Mon profil', icon: User, to: '/face/profile' },
])

// Bottom tab bar (mobile): the 5 main destinations; the drawer keeps the rest.
// « Mes candidatures » reste joignable depuis Missions (lien en tête de page) : son badge
// (candidatures à reconfirmer) est porté par l'onglet Missions.
const mobileTabs = computed<SidebarItem[]>(() => [
  { label: 'Accueil', icon: House, to: '/face/dashboard' },
  { label: 'Missions', icon: Briefcase, to: '/face/missions', match: ['/face/ugc-missions'],
    badge: actionCountsStore.counts.candidatures },
  { label: 'Bookings', icon: CalendarCheck, to: '/face/bookings',
    badge: actionCountsStore.counts.bookings },
  { label: 'Messages', icon: MessageCircle, to: '/face/messages', match: ['/face/conversations'],
    badge: messagesUnreadStore.count, badgeMax: 9, badgeKind: 'messages' },
  { label: 'Profil', icon: User, to: '/face/profile' },
])

// Header title = label of the sidebar item owning the current route.
const pageTitle = computed(() => resolveSidebarTitle(sidebarItems.value, route.path))

// Computed user name from Face profile
const userName = computed(() => {
  if (profile.value) {
    return `${profile.value.prenom} ${profile.value.nom}`
  }
  return ''
})

// Fetch profile on mount to get avatar
onMounted(async () => {
  // Badge « Messages » : 1 fetch au boot, puis événements / focus / repli (pas de fetch par navigation)
  messagesUnreadStore.start('face')
  // Badges d'actions en attente : chargement au boot, puis événements / focus / navigation
  actionCountsStore.start()
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

watch(() => route.path, () => {
  // Une action faite par l'utilisateur lui-même n'émet aucun événement vers lui : on resynchronise
  // les badges à chaque navigation (un COUNT par rôle, requêtes en vol dédupliquées).
  void actionCountsStore.refresh()
})

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
    :avatar-url="profile?.profile_photo_url"
    :is-logging-out="isLoading"
    @logout="handleLogout"
  >
    <!-- Barre d'alertes unique (suspension UGC, paiement d'abonnement, e-mail à vérifier).
         Pas d'alerte de paiement sur les routes qui portent leur propre surface d'abonnement
         (Facturation) : la barre n'y monte pas la vérification automatique. -->
    <template #alerts>
      <AccountAlertBar
        audience="face"
        :hide-subscription="!!route.meta.ownSubscriptionSurface"
      />
    </template>

    <!-- Child routes render here — meta.keepAlive-driven caching + page
         transitions live in the shared KeepAliveRouterView (see its header). -->
    <KeepAliveRouterView />
  </DashboardLayout>
</template>
