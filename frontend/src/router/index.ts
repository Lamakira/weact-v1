import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '@/stores/auth'
import { useAdminAuthStore } from '@/stores/adminAuth'
import { useNavigationProgress } from '@/composables/useNavigationProgress'

const navigationProgress = useNavigationProgress()

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to, _from, savedPosition) {
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
      meta: { title: 'À propos - WEACT' },
    },
    {
      path: '/mentions-legales',
      name: 'mentions-legales',
      component: () => import('../views/MentionsLegalesView.vue'),
      meta: { title: 'Mentions légales - WEACT' },
    },
    {
      path: '/cgu',
      name: 'cgu',
      component: () => import('../views/CguView.vue'),
      meta: { title: 'Conditions Générales d\'Utilisation - WEACT' },
    },
    {
      path: '/politique-confidentialite',
      name: 'politique-confidentialite',
      component: () => import('../views/PolitiqueConfidentialiteView.vue'),
      meta: { title: 'Politique de Confidentialité - WEACT' },
    },
    {
      path: '/legal',
      redirect: '/mentions-legales',
    },
    {
      path: '/contact',
      name: 'contact',
      component: () => import('../views/ContactView.vue'),
      meta: { title: 'Contact - WEACT' },
    },
    {
      path: '/faq',
      name: 'faq',
      component: () => import('../views/FaqView.vue'),
      meta: { title: 'FAQ - WEACT' },
    },
    {
      path: '/guide',
      name: 'guide',
      component: () => import('../views/GuideView.vue'),
      meta: { title: 'Guide de démarrage - WEACT' },
    },
    {
      path: '/support',
      name: 'support',
      component: () => import('../views/SupportView.vue'),
      meta: { title: 'Support - WEACT' },
    },
    {
      path: '/pricing',
      name: 'pricing',
      component: () => import('../views/PricingView.vue'),
      // ownSubscriptionSurface: the page runs its own useSubscriptionPayment
      // polling/controls — the shared payment banners must not mount here.
      meta: { title: 'Tarifs - WEACT', ownSubscriptionSurface: true },
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
    {
      path: '/email-change/confirm/:id/:hash',
      name: 'email-change-confirm',
      component: () => import('../pages/auth/EmailChangeConfirmPage.vue'),
      // No auth required - works via signed URL
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
          path: 'ugc-missions',
          name: 'face-ugc-missions',
          component: () => import('../pages/face/mission/FaceUgcMissionsListPage.vue'),
          meta: { title: 'Missions UGC - WEACT' },
        },
        {
          path: 'ugc/suspension',
          name: 'face-ugc-suspension',
          component: () => import('../pages/face/FaceUgcSuspensionPage.vue'),
          meta: { title: 'Compte suspendu - WEACT' },
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
        {
          path: 'bookings',
          name: 'face-bookings',
          component: () => import('../pages/face/booking/FaceBookingsListPage.vue'),
        },
        {
          path: 'bookings/:id',
          name: 'face-booking-detail',
          component: () => import('../pages/face/booking/FaceBookingDetailPage.vue'),
        },
        {
          path: 'wallet',
          name: 'face-wallet',
          component: () => import('../pages/face/wallet/FaceWalletPage.vue'),
          meta: { title: 'Mon portefeuille - WEACT' },
        },
        {
          path: 'billing',
          name: 'face-billing',
          component: () => import('../pages/face/billing/FaceBillingPage.vue'),
          // ownSubscriptionSurface: the tab carries the full resume/cancel
          // controls — the shared payment banners must not mount here.
          meta: { title: 'Facturation & Abonnement - WEACT', ownSubscriptionSurface: true },
        },
      ],
    },
    // Post-registration upsell page (FP-3.5) — standalone full-page (NOT under
    // FaceLayout). Reached only from RegisterFacePage.handleSuccess default;
    // requiresAuth + role:'Face' is satisfied by the just-stored post-register auth.
    {
      path: '/bienvenue',
      name: 'face-upsell',
      component: () => import('../pages/auth/FaceUpsellPage.vue'),
      meta: { requiresAuth: true, role: 'Face', title: 'Bienvenue sur WEACT' },
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
          path: 'missions/:id',
          redirect: (to) => ({ name: 'edit-mission', params: { id: to.params.id } }),
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
          path: 'missions/:id/attendance',
          name: 'producer-mission-attendance',
          component: () =>
            import('../pages/producer/mission/AttendanceValidationPage.vue'),
        },
        {
          path: 'candidates/:id',
          name: 'producer-candidate-profile',
          component: () =>
            import('../pages/producer/candidature/CandidateProfilePage.vue'),
        },
        {
          path: 'faces',
          name: 'producer-faces-list',
          component: () => import('../pages/producer/ProducerFacesListPage.vue'),
        },
        {
          // Frontier-local Face profile: renders the SAME PublicFaceProfileView but
          // under ProducerLayout, so browsing /producer/faces → a profile → back
          // stays within /producer/* (matched[0] = /producer). That keeps the layout
          // instance alive, letting <keep-alive> cache the faces list (grid + scroll
          // restored, no rotation reshuffle). The public /faces/:username route stays
          // for everyone; this one inherits the parent's requiresAuth + role:Producer.
          path: 'faces/:username',
          name: 'producer-face-profile',
          component: () => import('../views/PublicFaceProfileView.vue'),
          meta: { title: 'Profil | WEACT' },
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
        {
          path: 'wallet',
          name: 'producer-wallet',
          component: () => import('../pages/producer/wallet/ProducerWalletPage.vue'),
          meta: { title: 'Mon portefeuille - WEACT' },
        },
        {
          path: 'bookings',
          name: 'producer-bookings',
          component: () => import('../pages/producer/booking/ProducerBookingsListPage.vue'),
        },
        {
          path: 'bookings/:id',
          name: 'producer-booking-detail',
          component: () => import('../pages/face/booking/FaceBookingDetailPage.vue'),
        },
        {
          path: 'ugc/validation',
          name: 'producer-ugc-validation',
          component: () => import('../pages/producer/ugc/ProducerUgcValidationPage.vue'),
          meta: { title: 'Validation des livrables - WEACT' },
        },
        {
          path: 'ugc/videos',
          name: 'producer-ugc-library',
          component: () => import('../pages/producer/ugc/ProducerUgcLibraryPage.vue'),
          meta: { title: 'Mes vidéos UGC - WEACT' },
        },
      ],
    },
    {
      path: '/dashboard/producer',
      redirect: { name: 'producer-dashboard' },
    },
    // Admin login route (guest-only for admins)
    {
      path: '/admin/login',
      name: 'admin-login',
      component: () => import('../pages/admin/AdminLoginPage.vue'),
      meta: { adminGuest: true, title: 'Administration - WEACT' },
    },
    {
      path: '/admin/forgot-password',
      name: 'admin-forgot-password',
      component: () => import('../pages/admin/AdminForgotPasswordPage.vue'),
      meta: { adminGuest: true, title: 'Mot de passe oublié - WEACT' },
    },
    {
      path: '/admin/reset-password/:token',
      name: 'admin-reset-password',
      component: () => import('../pages/admin/AdminResetPasswordPage.vue'),
      meta: { adminGuest: true, title: 'Réinitialiser le mot de passe - WEACT' },
    },
    // Admin routes (admin auth required) - nested under AdminLayout
    {
      path: '/admin',
      component: () => import('../pages/admin/AdminLayout.vue'),
      meta: { requiresAdminAuth: true },
      children: [
        {
          path: '',
          redirect: { name: 'admin-dashboard' },
        },
        {
          path: 'dashboard',
          name: 'admin-dashboard',
          component: () => import('../pages/admin/AdminDashboardPage.vue'),
          meta: { title: 'Dashboard - WEACT' },
        },
        {
          path: 'admins',
          name: 'admin-admins-list',
          component: () => import('../pages/admin/AdminListPage.vue'),
          meta: { title: 'Administrateurs - WEACT' },
        },
        {
          path: 'admins/create',
          name: 'admin-admins-create',
          component: () => import('../pages/admin/AdminCreatePage.vue'),
          meta: { title: 'Créer un admin - WEACT' },
        },
        {
          path: 'admins/:id/edit',
          name: 'admin-admins-edit',
          component: () => import('../pages/admin/AdminEditPage.vue'),
          meta: { title: 'Modifier un admin - WEACT' },
        },
        {
          path: 'faces',
          name: 'admin-faces-list',
          component: () => import('../pages/admin/AdminFacesListPage.vue'),
          meta: { title: 'Gestion des Faces - WEACT' },
        },
        {
          path: 'faces/:id',
          name: 'admin-face-detail',
          component: () => import('../pages/admin/AdminFaceDetailPage.vue'),
          meta: { title: 'Détail Face - WEACT' },
        },
        {
          path: 'producers',
          name: 'admin-producers-list',
          component: () => import('../pages/admin/AdminProducersListPage.vue'),
          meta: { title: 'Gestion des Producteurs - WEACT' },
        },
        {
          path: 'producers/:id',
          name: 'admin-producer-detail',
          component: () => import('../pages/admin/AdminProducerDetailPage.vue'),
          meta: { title: 'Détail Producteur - WEACT' },
        },
        {
          path: 'missions',
          name: 'admin-missions-list',
          component: () => import('../pages/admin/AdminMissionsListPage.vue'),
          meta: { title: 'Gestion des Missions - WEACT' },
        },
        {
          path: 'missions/:id',
          name: 'admin-mission-detail',
          component: () => import('../pages/admin/AdminMissionDetailPage.vue'),
          meta: { title: 'Détail Mission - WEACT' },
        },
        {
          path: 'engagements',
          name: 'admin-engagements',
          component: () => import('../pages/admin/AdminFaceContactsPage.vue'),
          meta: { title: 'Faces à contacter - WEACT' },
        },
        {
          path: 'finance',
          name: 'admin-finance',
          component: () => import('../pages/admin/AdminFinancePage.vue'),
          meta: { title: 'Vue financière - WEACT' },
        },
        {
          path: 'attendance-disputes',
          name: 'admin-attendance-disputes',
          component: () => import('../pages/admin/AdminAttendanceDisputesPage.vue'),
          meta: { title: 'Litiges présence - WEACT' },
        },
        {
          path: 'ugc/suspensions',
          name: 'admin-ugc-suspensions',
          component: () => import('../pages/admin/AdminUgcSuspensionsPage.vue'),
          meta: { title: 'Suspensions UGC - WEACT' },
        },
        {
          path: 'subscriptions',
          name: 'admin-subscriptions-list',
          component: () => import('../pages/admin/AdminSubscriptionsListPage.vue'),
          meta: { title: 'Abonnements - WEACT' },
        },
        {
          path: 'articles',
          name: 'admin-articles-list',
          component: () => import('../pages/admin/AdminArticlesListPage.vue'),
          meta: { title: 'Gestion des Articles - WEACT' },
        },
        {
          path: 'articles/create',
          name: 'admin-articles-create',
          component: () => import('../pages/admin/AdminArticleCreatePage.vue'),
          meta: { title: 'Créer un article - WEACT' },
        },
        {
          path: 'articles/:id/edit',
          name: 'admin-articles-edit',
          component: () => import('../pages/admin/AdminArticleEditPage.vue'),
          meta: { title: 'Modifier un article - WEACT' },
        },
      ],
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
      path: '/missions/:slug',
      name: 'public-mission-detail',
      component: () => import('../views/PublicMissionDetailView.vue'),
      meta: {
        title: 'Mission | WEACT',
        description: 'Découvrez les détails de cette mission sur WEACT.',
      },
    },
    {
      path: '/faces/:username',
      name: 'public-face-profile',
      component: () => import('../views/PublicFaceProfileView.vue'),
      meta: {
        title: 'Profil | WEACT',
        description: 'Découvrez le profil de ce talent sur WEACT.',
      },
    },
    {
      path: '/producers/:slug',
      name: 'public-producer-profile',
      component: () => import('../pages/public/ProducerProfilePage.vue'),
    },
    {
      path: '/ressources',
      name: 'ressources-list',
      component: () => import('../views/RessourcesView.vue'),
      meta: {
        title: 'Ressources - WEACT',
        description:
          'Conseils, guides et actualités pour les talents et producteurs du Bénin.',
      },
    },
    {
      path: '/ressources/:slug',
      name: 'ressources-detail',
      component: () => import('../views/ArticleDetailView.vue'),
      meta: {
        title: 'Article | WEACT',
        description: 'Découvrez cet article sur WEACT.',
      },
    },
  ],
})

// Navigation guards
router.beforeEach((to, _from, next) => {
  // Démarre la barre de chargement dès le début de toute navigation
  // (les redirections des guards ci-dessous relancent beforeEach : start() est idempotent).
  navigationProgress.start()

  const authStore = useAuthStore()
  const isAuthenticated = authStore.isAuthenticated
  const userType = authStore.user?.userable_type

  // === Admin auth guards (separate from user auth) ===

  // Admin guest routes - redirect to admin dashboard if already admin-authenticated
  if (to.meta.adminGuest) {
    const adminAuthStore = useAdminAuthStore()
    if (adminAuthStore.isAuthenticated) {
      const defaultRoute = adminAuthStore.isEditor ? 'admin-articles-list' : 'admin-dashboard'
      return next({ name: defaultRoute })
    }
    return next()
  }

  // Admin protected routes - redirect to admin login if not admin-authenticated
  if (to.meta.requiresAdminAuth) {
    const adminAuthStore = useAdminAuthStore()
    if (!adminAuthStore.isAuthenticated) {
      return next({ name: 'admin-login', query: { redirect: to.fullPath } })
    }

    // Editor role restriction: can only access article routes
    if (adminAuthStore.isEditor) {
      const allowedEditorRoutes = [
        'admin-articles-list',
        'admin-articles-create',
        'admin-articles-edit',
      ]
      if (!allowedEditorRoutes.includes(to.name as string)) {
        return next({ name: 'admin-articles-list' })
      }
    }

    return next()
  }

  // === User auth guards ===

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

router.afterEach((to) => {
  // Termine la barre : afterEach se déclenche même sur navigation annulée/redirigée,
  // donc la barre ne reste jamais bloquée.
  navigationProgress.done()

  const title = to.meta.title as string | undefined
  document.title = title || 'WEACT'
})

// Échec de chargement d'un chunk lazy → on termine aussi la barre pour éviter qu'elle reste figée.
router.onError(() => {
  navigationProgress.done()
})

export default router
