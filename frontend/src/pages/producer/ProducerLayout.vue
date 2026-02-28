<script setup lang="ts">
/**
 * ProducerLayout
 * Wrapper layout for all Producer dashboard pages.
 * Uses DashboardLayout with Producer-specific sidebar items.
 * Child routes render via <router-view> in the content area.
 */
import { onMounted, ref, computed } from 'vue'
import { LayoutDashboard, FileText, MessageCircle, User, PlusCircle, Users, CalendarCheck } from 'lucide-vue-next'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useAuthStore } from '@/stores/auth'
import { DashboardLayout, type SidebarItem } from '@/components/layout'
import { producerApi } from '@/features/producer/services/producerApi'
import type { Producer } from '@/features/producer/types'
import EmailVerificationBanner from '@/components/EmailVerificationBanner.vue'

const authStore = useAuthStore()
const { logout, isLoading } = useAuth()

// Profile data for avatar
const profile = ref<Producer | null>(null)

// Sidebar navigation items for Producer dashboard
const sidebarItems: SidebarItem[] = [
  { label: 'Dashboard', icon: LayoutDashboard, to: '/producer/dashboard' },
  { label: 'Mes missions', icon: FileText, to: '/producer/missions' },
  { label: 'Publier une mission', icon: PlusCircle, to: '/producer/missions/publish' },
  { label: 'Liste des faces', icon: Users, to: '/producer/faces' },
  { label: 'Mes bookings', icon: CalendarCheck, to: '/producer/bookings' },
  { label: 'Messages', icon: MessageCircle, to: '/producer/messages' },
  { label: 'Mon profil', icon: User, to: '/producer/profile' },
]

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

// Fetch profile on mount to get avatar
onMounted(async () => {
  try {
    const response = await producerApi.getProfile()
    profile.value = response.data
  } catch {
    // Silently fail - avatar will show fallback
  }
})

async function handleLogout(): Promise<void> {
  await logout()
}
</script>

<template>
  <DashboardLayout
    :sidebar-items="sidebarItems"
    title="Producer Dashboard"
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

    <!-- Child routes render here -->
    <router-view />
  </DashboardLayout>
</template>
