<script setup lang="ts">
/**
 * AdminLayout
 * Wrapper layout for all admin dashboard pages.
 * Uses DashboardLayout with admin-specific sidebar items.
 * Child routes render via <router-view> in the content area.
 */
import { computed } from 'vue'
import { LayoutDashboard, FileText, ShieldCheck, Briefcase, UserCheck, Building, TrendingUp, Scale, MessageCircle, Ban, CreditCard } from 'lucide-vue-next'
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
  { label: 'Faces à contacter', icon: MessageCircle, to: '/admin/engagements' },
  { label: 'Litiges', icon: Scale, to: '/admin/attendance-disputes' },
  { label: 'Suspensions UGC', icon: Ban, to: '/admin/ugc/suspensions' },
  { label: 'Abonnements', icon: CreditCard, to: '/admin/subscriptions' },
  { label: 'Finances', icon: TrendingUp, to: '/admin/finance' },
]

const sidebarItems = computed(() =>
  allSidebarItems.filter((item) => {
    if (item.to === '/admin/admins') return adminAuthStore.isSuperAdmin
    if (adminAuthStore.isEditor) return item.to === '/admin/articles'
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
    <!-- Child routes render here. keep-alive caches the browse-and-return
         listings so back-nav restores them (the layout persists because App.vue
         keys the admin branch by matched[0].path). -->
    <router-view v-slot="{ Component }">
      <keep-alive :include="['AdminFacesListPage', 'AdminProducersListPage', 'AdminMissionsListPage', 'AdminListPage', 'AdminArticlesListPage']">
        <component :is="Component" />
      </keep-alive>
    </router-view>
  </DashboardLayout>
</template>
