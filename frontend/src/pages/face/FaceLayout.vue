<script setup lang="ts">
/**
 * FaceLayout
 * Wrapper layout for all Face dashboard pages.
 * Uses DashboardLayout with Face-specific sidebar items.
 * Child routes render via <router-view> in the content area.
 */
import { onMounted, ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { LayoutDashboard, FileText, MessageCircle, User, Briefcase, CalendarCheck, Wallet, CreditCard, Video } from 'lucide-vue-next'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useAuthStore } from '@/stores/auth'
import { DashboardLayout, type SidebarItem } from '@/components/layout'
import { useProfilePhoto } from '@/features/face/composables/useProfilePhoto'
import { usePersonalInfo } from '@/features/face/composables/usePersonalInfo'
import EmailVerificationBanner from '@/components/EmailVerificationBanner.vue'
import TarifsMissingBanner from '@/components/TarifsMissingBanner.vue'
import WhatsappMissingBanner from '@/components/WhatsappMissingBanner.vue'
import PendingSubscriptionPaymentBanner from '@/components/PendingSubscriptionPaymentBanner.vue'
import UgcSuspensionBanner from '@/components/UgcSuspensionBanner.vue'

const route = useRoute()
const authStore = useAuthStore()
const { logout, isLoading } = useAuth()
const { profile, fetchProfile } = useProfilePhoto()
const { personalInfo, fetchPersonalInfo } = usePersonalInfo()

const hasTarifs = computed(() => !!profile.value?.tarif_journalier)
const personalInfoLoaded = ref(false)
const hasWhatsapp = computed(() => !!personalInfo.value?.whatsapp_number)

// Sidebar navigation items for Face dashboard
const sidebarItems: SidebarItem[] = [
  { label: 'Dashboard', icon: LayoutDashboard, to: '/face/dashboard' },
  { label: 'Voir les missions', icon: Briefcase, to: '/face/missions' },
  { label: 'Missions UGC', icon: Video, to: '/face/ugc-missions' },
  { label: 'Mes candidatures', icon: FileText, to: '/face/candidatures' },
  { label: 'Mes bookings', icon: CalendarCheck, to: '/face/bookings' },
  { label: 'Messages', icon: MessageCircle, to: '/face/messages' },
  { label: 'Portefeuille', icon: Wallet, to: '/face/wallet' },
  { label: 'Facturation', icon: CreditCard, to: '/face/billing' },
  { label: 'Tarifs', icon: CreditCard, to: '/pricing' },
  { label: 'Mon profil', icon: User, to: '/face/profile' },
]

// Computed user name from Face profile
const userName = computed(() => {
  if (profile.value) {
    return `${profile.value.prenom} ${profile.value.nom}`
  }
  return ''
})

// Fetch profile on mount to get avatar
onMounted(async () => {
  try {
    await fetchProfile()
  } catch {
    // Silently fail - avatar will show fallback
  }

  await fetchWhatsappStatus()
})

async function fetchWhatsappStatus(): Promise<void> {
  try {
    await fetchPersonalInfo()
    personalInfoLoaded.value = true
  } catch {
    // Silently fail - banner will show until data can be loaded
    personalInfoLoaded.value = true
  }
}

// Re-check WhatsApp status when navigating between child routes
// (e.g., user fills WhatsApp on /face/profile then navigates back)
watch(() => route.path, () => {
  fetchWhatsappStatus()
})

async function handleLogout(): Promise<void> {
  await logout()
}
</script>

<template>
  <DashboardLayout
    :sidebar-items="sidebarItems"
    title="Face Dashboard"
    :user-email="authStore.user?.email"
    :user-name="userName"
    :avatar-url="profile?.profile_photo_url"
    :is-logging-out="isLoading"
    @logout="handleLogout"
  >
    <!-- UGC soft-suspension banner — shown on every Face page while a suspension is
         active (écran 10A, story 5.2). Self-fetches its own state; placed first so a
         suspension visually outranks the subscription nudges below. -->
    <UgcSuspensionBanner />

    <!-- Pending subscription payment nudge — shown on every Face page (except the
         routes that own their subscription surface, e.g. the Facturation tab which
         carries the full resume controls) so a Face with an abandoned/unconfirmed
         payment is always guided to resume it. -->
    <PendingSubscriptionPaymentBanner v-if="!route.meta.ownSubscriptionSurface" />

    <!-- Email verification banner (shown if email not verified) -->
    <EmailVerificationBanner
      v-if="!authStore.isEmailVerified"
      data-testid="email-verification-banner"
    />

    <!-- Tarifs missing banner (shown until Face sets their tarifs) -->
    <TarifsMissingBanner
      v-if="profile !== null && !hasTarifs"
      data-testid="tarifs-missing-banner"
    />

    <!-- WhatsApp missing banner (shown until Face sets their WhatsApp number) -->
    <WhatsappMissingBanner
      v-if="personalInfoLoaded && !hasWhatsapp"
    />

    <!-- Child routes render here -->
    <router-view />
  </DashboardLayout>
</template>
