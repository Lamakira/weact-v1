<script setup lang="ts">
import { useRouter } from 'vue-router'
import BookingsListContent from '@/features/booking/components/BookingsListContent.vue'

// Explicit name (devtools). Caching is driven by the route's meta.keepAlive flag.
defineOptions({ name: 'FaceBookingsListPage' })

const router = useRouter()

function goToProfile(): void {
  router.push({ name: 'face-profile' })
}
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-slate-800">
        Mes bookings
      </h1>
      <p class="mt-1 text-slate-500">
        Suivez vos demandes de booking et leur évolution
      </p>
    </div>

    <BookingsListContent
      role="face"
      route-name="face-bookings"
      :empty-hint="`Vous n'avez pas encore reçu de demande de booking.\nComplétez votre profil pour attirer les producteurs !`"
      empty-cta-label="Compléter mon profil"
      @empty-cta="goToProfile"
    />
  </div>
</template>
