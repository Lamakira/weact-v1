<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft } from 'lucide-vue-next'
import AttendanceValidationPanel from '@/features/mission/components/AttendanceValidationPanel.vue'

// Page dédiée conservée temporairement : la validation des présences vit désormais dans
// l'onglet « Présences » du détail mission (la route redirige déjà vers celui-ci).
const route = useRoute()
const router = useRouter()

const missionUuid = computed<string>(() => {
  const raw = route.params.id
  if (Array.isArray(raw)) return raw[0] ?? ''
  return raw ?? ''
})

function goToMissions(): void {
  router.push({ name: 'producer-missions' })
}
</script>

<template>
  <div class="container mx-auto px-4 py-6 max-w-3xl">
    <button
      type="button"
      class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground mb-4"
      @click="goToMissions"
    >
      <ArrowLeft :size="16" /> Retour aux missions
    </button>

    <AttendanceValidationPanel
      standalone
      :mission-uuid="missionUuid"
      @done="goToMissions"
      @failed="goToMissions"
      @back="goToMissions"
    />
  </div>
</template>
