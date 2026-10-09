<script setup lang="ts">
/**
 * FaceTodoPanel — file « À faire » du dashboard Face (direction Régie).
 * Données : GET /face/dashboard/todo (jusqu'à 8 items triés par urgence côté serveur).
 * Les items à partie urgente (échéance < 48 h) ont un bouton primaire et une icône ambre.
 */
import { computed, type Component } from 'vue'
import { useRouter } from 'vue-router'
import {
  CalendarClock,
  Package,
  ShieldAlert,
  CheckCircle2,
  Video,
  Hourglass,
  UserCog,
  ListChecks,
} from 'lucide-vue-next'
import RTodoList from '@/components/regie/RTodoList.vue'
import RTodoItem from '@/components/regie/RTodoItem.vue'
import RPanel from '@/components/regie/RPanel.vue'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import type { FaceTodoItem, FaceTodoType } from '../types'

interface Props {
  items: FaceTodoItem[]
  isLoading?: boolean
  error?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  error: null,
})

defineEmits<{
  retry: []
}>()

const router = useRouter()

const ICONS: Record<FaceTodoType, Component> = {
  booking_proposal: CalendarClock,
  ugc_proposal: Package,
  no_show_contest: ShieldAlert,
  confirm_prestation: CheckCircle2,
  ugc_deliverable: Video,
  pending_candidatures: Hourglass,
  profile_completion: UserCog,
}

const showList = computed(() => !props.error && (!props.isLoading || props.items.length > 0))

function open(item: FaceTodoItem): void {
  void router.push(item.url)
}
</script>

<template>
  <div data-testid="face-todo-panel">
    <RPanel v-if="isLoading && items.length === 0 && !error" title="À faire">
      <div class="space-y-3 p-4 sm:p-5" data-testid="face-todo-skeleton">
        <Skeleton class="h-10 w-full" />
        <Skeleton class="h-10 w-full" />
      </div>
    </RPanel>

    <RPanel v-else-if="error" title="À faire">
      <div class="p-4 sm:p-5" data-testid="face-todo-error">
        <p class="text-dash text-ink-3">{{ error }}</p>
        <Button
          type="button"
          variant="regie-secondary"
          size="regie"
          class="mt-3"
          data-testid="face-todo-retry"
          @click="$emit('retry')"
        >
          Réessayer
        </Button>
      </div>
    </RPanel>

    <RTodoList
      v-else-if="showList"
      title="À faire"
      :count="items.length"
      hint="Trié par urgence"
      empty-text="Rien à faire pour le moment. Vos prochaines échéances apparaîtront ici."
    >
      <RTodoItem
        v-for="item in items"
        :key="`${item.type}:${item.url}`"
        :title="item.title"
        :meta="item.meta ?? undefined"
        :urgent-meta="item.urgent_meta ?? undefined"
        :icon="ICONS[item.type] ?? ListChecks"
        :action-label="item.action_label"
        :action-variant="item.urgent_meta ? 'primary' : 'secondary'"
        :to="item.url"
        @action="open(item)"
      />
    </RTodoList>
  </div>
</template>
