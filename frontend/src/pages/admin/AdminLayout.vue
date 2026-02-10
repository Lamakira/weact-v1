<script setup lang="ts">
/**
 * AdminLayout
 * Wrapper layout for all admin dashboard pages.
 * Uses DashboardLayout with admin-specific sidebar items.
 * Child routes render via <router-view> in the content area.
 */
import { LayoutDashboard, FileText, Users } from 'lucide-vue-next'
import { useAdminAuth } from '@/features/admin/composables/useAdminAuth'
import { useAdminAuthStore } from '@/stores/adminAuth'
import { DashboardLayout, type SidebarItem } from '@/components/layout'

const adminAuthStore = useAdminAuthStore()
const { logout, isLoading } = useAdminAuth()

// Sidebar navigation items for admin dashboard
const sidebarItems: SidebarItem[] = [
  { label: 'Dashboard', icon: LayoutDashboard, to: '/admin/dashboard' },
  { label: 'Articles', icon: FileText, to: '/admin/articles' },
  { label: 'Utilisateurs', icon: Users, to: '/admin/users' },
]

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
    @logout="handleLogout"
  >
    <!-- Child routes render here -->
    <router-view />
  </DashboardLayout>
</template>
