<script setup lang="ts">
import { useRouter } from 'vue-router'
import BookingsListContent from '@/features/booking/components/BookingsListContent.vue'

// Explicit name (devtools). Caching is driven by the route's meta.keepAlive flag.
defineOptions({ name: 'ProducerBookingsListPage' })

const router = useRouter()

function goToFacesList(): void {
  router.push({ name: 'public-faces-list' })
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
        Gérez vos demandes de booking envoyées aux Faces
      </p>
    </div>

    <BookingsListContent
      role="producer"
      route-name="producer-bookings"
      :empty-hint="`Vous n'avez pas encore envoyé de demande de booking.\nParcourez les Faces pour trouver votre talent !`"
      empty-cta-label="Parcourir les Faces"
      @empty-cta="goToFacesList"
    />
  </div>
</template>
