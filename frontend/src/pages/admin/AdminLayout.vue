<script setup lang="ts">
/**
 * AdminLayout
 * Wrapper layout for all admin dashboard pages.
 * Uses DashboardLayout with admin-specific sidebar items.
 * Child routes render via <router-view> in the content area.
 */
import { computed } from 'vue'
import { LayoutDashboard, FileText, ShieldCheck, Briefcase, UserCheck, Building } from 'lucide-vue-next'
import { useAdminAuth } from '@/features/admin/composables/useAdminAuth'
import { useAdminAuthStore } from '@/stores/adminAuth'
import { DashboardLayout, type SidebarItem } from '@/components/layout'

const adminAuthStore = useAdminAuthStore()
const { logout, isLoading } = useAdminAuth()

// Sidebar navigation items for admin dashboard
const allSidebarItems: SidebarItem[] = [
  { label: 'Dashboard', icon: LayoutDashboard, to: '/admin/dashboard' },
  { label: 'Articles', icon: FileText, to: '/admin/articles' },
  { label: 'Faces', icon: UserCheck, to: '/admin/faces' },
  { label: 'Producteurs', icon: Building, to: '/admin/producers' },
  { label: 'Admins', icon: ShieldCheck, to: '/admin/admins' },
  { label: 'Missions', icon: Briefcase, to: '/admin/missions' },
]

const sidebarItems = computed(() =>
  allSidebarItems.filter((item) => {
    if (item.to === '/admin/admins') return adminAuthStore.isSuperAdmin
    return true
  }),
)

async function handleLogout(): Promise<void> {
  await logout()
}
</script>

<template>
  <DashboardLayout
    :sidebar-items="sidebarItems"
    title="Administration"
    :user-email="adminAuthStore.adminEmail"
    :user-name="adminAuthStore.adminName"
    :is-logging-out="isLoading"
    :show-notifications="false"
    @logout="handleLogout"
  >
    <!-- Child routes render here -->
    <router-view />
  </DashboardLayout>
</template>
