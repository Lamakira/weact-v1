<script setup lang="ts">
/**
 * FaceLayout
 * Wrapper layout for all Face dashboard pages.
 * Uses DashboardLayout with Face-specific sidebar items.
 * Child routes render via <router-view> in the content area.
 */
import { onMounted, ref, computed } from 'vue'
import { LayoutDashboard, FileText, MessageCircle, User } from 'lucide-vue-next'
import { useAuth } from '@/features/auth/composables/useAuth'
import { useAuthStore } from '@/stores/auth'
import { DashboardLayout, type SidebarItem } from '@/components/layout'
import { faceApi } from '@/features/face/services/faceApi'
import type { FaceProfile } from '@/features/face/types'

const authStore = useAuthStore()
const { logout, isLoading } = useAuth()

// Profile data for avatar
const profile = ref<FaceProfile | null>(null)

// Sidebar navigation items for Face dashboard
const sidebarItems: SidebarItem[] = [
  { label: 'Dashboard', icon: LayoutDashboard, to: '/face/dashboard' },
  { label: 'Mes candidatures', icon: FileText, to: '/face/candidatures' },
  { label: 'Messages', icon: MessageCircle, to: '/face/messages' },
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
    const response = await faceApi.getProfile()
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
    title="Face Dashboard"
    :user-email="authStore.user?.email"
    :user-name="userName"
    :avatar-url="profile?.profile_photo_url"
    :is-logging-out="isLoading"
    @logout="handleLogout"
  >
    <!-- Child routes render here -->
    <router-view />
  </DashboardLayout>
</template>
