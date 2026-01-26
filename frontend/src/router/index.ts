import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/about',
      name: 'about',
      component: () => import('../views/AboutView.vue'),
    },
    // Auth routes (guest only)
    {
      path: '/register/face',
      name: 'register-face',
      component: () => import('../pages/auth/RegisterFacePage.vue'),
      meta: { guest: true },
    },
    {
      path: '/register/producer',
      name: 'register-producer',
      component: () => import('../pages/auth/RegisterProducerPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../pages/auth/LoginPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('../pages/auth/ForgotPasswordPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/reset-password/:token',
      name: 'reset-password',
      component: () => import('../pages/auth/ResetPasswordPage.vue'),
      meta: { guest: true },
    },
    // Face routes (auth required + Face role)
    {
      path: '/face/dashboard',
      name: 'face-dashboard',
      component: () => import('../pages/dashboard/FaceDashboardPage.vue'),
      meta: { requiresAuth: true, role: 'Face' },
    },
    {
      path: '/face/profile',
      name: 'face-profile',
      component: () => import('../pages/face/ProfileEditPage.vue'),
      meta: { requiresAuth: true, role: 'Face' },
    },
    {
      path: '/face/missions',
      name: 'face-missions',
      component: () => import('../pages/face/mission/FaceMissionsListPage.vue'),
      meta: { requiresAuth: true, role: 'Face' },
    },
    {
      path: '/face/missions/:id',
      name: 'face-mission-detail',
      component: () => import('../pages/face/mission/FaceMissionDetailPage.vue'),
      meta: { requiresAuth: true, role: 'Face' },
    },
    {
      path: '/dashboard/face',
      redirect: { name: 'face-dashboard' },
    },
    // Producer routes (auth required + Producer role)
    {
      path: '/producer/dashboard',
      name: 'producer-dashboard',
      component: () => import('../pages/dashboard/ProducerDashboardPage.vue'),
      meta: { requiresAuth: true, role: 'Producer' },
    },
    {
      path: '/producer/profile',
      name: 'producer-profile',
      component: () => import('../pages/producer/ProfileEditPage.vue'),
      meta: { requiresAuth: true, role: 'Producer' },
    },
    {
      path: '/producer/missions',
      name: 'producer-missions',
      component: () => import('../pages/producer/mission/MissionsListPage.vue'),
      meta: { requiresAuth: true, role: 'Producer' },
    },
    {
      path: '/producer/missions/publish',
      name: 'publish-mission',
      component: () => import('../pages/producer/mission/PublishMissionPage.vue'),
      meta: { requiresAuth: true, role: 'Producer' },
    },
    {
      path: '/producer/missions/:id/edit',
      name: 'edit-mission',
      component: () => import('../pages/producer/mission/EditMissionPage.vue'),
      meta: { requiresAuth: true, role: 'Producer' },
    },
    {
      path: '/dashboard/producer',
      redirect: { name: 'producer-dashboard' },
    },
    // Public routes (no auth required)
    {
      path: '/producers/:id',
      name: 'public-producer-profile',
      component: () => import('../pages/public/ProducerProfilePage.vue'),
    },
  ],
})

// Navigation guards
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()
  const isAuthenticated = authStore.isAuthenticated
  const userType = authStore.user?.userable_type

  // Guest routes - redirect to dashboard if already logged in
  if (to.meta.guest && isAuthenticated) {
    if (userType === 'Face') {
      return next({ name: 'face-dashboard' })
    } else if (userType === 'Producer') {
      return next({ name: 'producer-dashboard' })
    }
    return next({ name: 'home' })
  }

  // Protected routes - redirect to login if not authenticated
  if (to.meta.requiresAuth && !isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  // Role-based routes - check if user has correct role
  if (to.meta.role && userType !== to.meta.role) {
    // Redirect to appropriate dashboard based on role
    if (userType === 'Face') {
      return next({ name: 'face-dashboard' })
    } else if (userType === 'Producer') {
      return next({ name: 'producer-dashboard' })
    }
    return next({ name: 'home' })
  }

  next()
})

export default router
