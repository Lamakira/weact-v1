import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to, from, savedPosition) {
    // If user used browser back/forward, restore saved position
    if (savedPosition) {
      return savedPosition
    }
    // If navigating to a hash anchor, scroll to it
    if (to.hash) {
      return { el: to.hash, behavior: 'smooth' }
    }
    // Otherwise scroll to top
    return { top: 0 }
  },
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
      meta: {
        title: 'WEACT - Monétisez votre image au Bénin',
        description:
          'Rejoignez la première plateforme de casting au Bénin. Connectez-vous avec des producteurs et monétisez votre talent en jouant dans des films, séries, publicités et clips.',
        ogTitle: 'WEACT - La première plateforme de casting au Bénin',
        ogDescription:
          'Monétisez votre image en jouant dans des films, séries, publicités et clips. Rejoignez +500 talents sur WEACT.',
        ogImage: '/og-image.jpg', // TODO: Add actual OG image asset
        ogType: 'website',
        twitterCard: 'summary_large_image',
      },
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
    {
      path: '/verify-email/:id/:hash',
      name: 'verify-email',
      component: () => import('../pages/auth/VerifyEmailPage.vue'),
      // No auth required - user may click link when logged out
    },
    // Face routes (auth required + Face role) - nested under FaceLayout
    {
      path: '/face',
      component: () => import('../pages/face/FaceLayout.vue'),
      meta: { requiresAuth: true, role: 'Face' },
      children: [
        {
          path: '',
          redirect: { name: 'face-dashboard' },
        },
        {
          path: 'dashboard',
          name: 'face-dashboard',
          component: () => import('../pages/dashboard/FaceDashboardPage.vue'),
        },
        {
          path: 'profile',
          name: 'face-profile',
          component: () => import('../pages/face/ProfileEditPage.vue'),
        },
        {
          path: 'missions',
          name: 'face-missions',
          component: () => import('../pages/face/mission/FaceMissionsListPage.vue'),
        },
        {
          path: 'missions/:id',
          name: 'face-mission-detail',
          component: () => import('../pages/face/mission/FaceMissionDetailPage.vue'),
        },
        {
          path: 'candidatures',
          name: 'face-candidatures',
          component: () => import('../pages/face/candidature/FaceCandidaturesPage.vue'),
        },
        {
          path: 'messages',
          name: 'face-messages',
          component: () => import('../pages/face/messaging/FaceConversationsPage.vue'),
        },
        {
          path: 'conversations/:conversationId',
          name: 'face-conversation',
          component: () => import('../features/messaging/components/ConversationView.vue'),
        },
      ],
    },
    {
      path: '/dashboard/face',
      redirect: { name: 'face-dashboard' },
    },
    // Producer routes (auth required + Producer role) - nested under ProducerLayout
    {
      path: '/producer',
      component: () => import('../pages/producer/ProducerLayout.vue'),
      meta: { requiresAuth: true, role: 'Producer' },
      children: [
        {
          path: '',
          redirect: { name: 'producer-dashboard' },
        },
        {
          path: 'dashboard',
          name: 'producer-dashboard',
          component: () => import('../pages/dashboard/ProducerDashboardPage.vue'),
        },
        {
          path: 'profile',
          name: 'producer-profile',
          component: () => import('../pages/producer/ProfileEditPage.vue'),
        },
        {
          path: 'missions',
          name: 'producer-missions',
          component: () => import('../pages/producer/mission/MissionsListPage.vue'),
        },
        {
          path: 'missions/publish',
          name: 'publish-mission',
          component: () => import('../pages/producer/mission/PublishMissionPage.vue'),
        },
        {
          path: 'missions/:id/edit',
          name: 'edit-mission',
          component: () => import('../pages/producer/mission/EditMissionPage.vue'),
        },
        {
          path: 'missions/:id/candidatures',
          name: 'producer-mission-candidatures',
          component: () =>
            import('../pages/producer/candidature/ProducerMissionCandidaturesPage.vue'),
        },
        {
          path: 'candidates/:id',
          name: 'producer-candidate-profile',
          component: () =>
            import('../pages/producer/candidature/CandidateProfilePage.vue'),
        },
        {
          path: 'messages',
          name: 'producer-messages',
          component: () => import('../pages/producer/messaging/ProducerConversationsPage.vue'),
        },
        {
          path: 'conversations/:conversationId',
          name: 'producer-conversation',
          component: () => import('../features/messaging/components/ProducerConversationView.vue'),
        },
      ],
    },
    {
      path: '/dashboard/producer',
      redirect: { name: 'producer-dashboard' },
    },
    // Public routes (no auth required)
    {
      path: '/faces',
      name: 'public-faces-list',
      component: () => import('../views/PublicFacesView.vue'),
      meta: {
        title: 'Nos Talents - WEACT',
        description:
          'Découvrez notre vivier de talents béninois pour tous vos projets audiovisuels. Acteurs, mannequins, influenceurs et créateurs.',
      },
    },
    {
      path: '/missions',
      name: 'public-missions-list',
      component: () => import('../views/PublicMissionsView.vue'),
      meta: {
        title: 'Missions - WEACT',
        description:
          'Découvrez les opportunités de casting disponibles au Bénin. Publicités, films, courts-métrages et clips musicaux.',
      },
    },
    {
      path: '/faces/:id',
      name: 'public-face-profile',
      component: () => import('../views/PublicFaceProfileView.vue'),
      meta: {
        title: 'Profil | WEACT',
        description: 'Découvrez le profil de ce talent sur WEACT.',
      },
    },
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
