<script setup lang="ts">
/**
 * ProducerTodoPanel — file « À faire » du dashboard Producteur (direction Régie).
 * Données : GET /producer/dashboard/todo (jusqu'à 8 items triés par urgence côté serveur).
 * Les items à partie urgente (échéance < 48 h) ont un bouton primaire.
 */
import { computed, type Component } from 'vue'
import { useRouter } from 'vue-router'
import {
  Users,
  CreditCard,
  Wallet,
  ClipboardCheck,
  Video,
  PackageCheck,
  CheckCircle2,
  Star,
  ListChecks,
} from 'lucide-vue-next'
import RTodoList from '@/components/regie/RTodoList.vue'
import RTodoItem from '@/components/regie/RTodoItem.vue'
import RPanel from '@/components/regie/RPanel.vue'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import type { ProducerTodoItem, ProducerTodoType } from '../../types'

interface Props {
  items: ProducerTodoItem[]
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

const ICONS: Record<ProducerTodoType, Component> = {
  candidatures_to_process: Users,
  selection_payment: CreditCard,
  ugc_mission_commission: Wallet,
  booking_payment: CreditCard,
  ugc_booking_commission: Wallet,
  attendance_validation: ClipboardCheck,
  ugc_deliverable_review: Video,
  ugc_shipment: PackageCheck,
  booking_completion: CheckCircle2,
  rate: Star,
}

const showList = computed(() => !props.error && (!props.isLoading || props.items.length > 0))

function open(item: ProducerTodoItem): void {
  void router.push(item.url)
}
</script>

<template>
  <div data-testid="producer-todo-panel">
    <RPanel v-if="isLoading && items.length === 0 && !error" title="À faire">
      <div class="space-y-3 p-4 sm:p-5" data-testid="producer-todo-skeleton">
        <Skeleton class="h-10 w-full" />
        <Skeleton class="h-10 w-full" />
      </div>
    </RPanel>

    <RPanel v-else-if="error" title="À faire">
      <div class="p-4 sm:p-5" data-testid="producer-todo-error">
        <p class="text-dash text-ink-3">{{ error }}</p>
        <Button
          type="button"
          variant="regie-secondary"
          size="regie"
          class="mt-3"
          data-testid="producer-todo-retry"
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
      empty-text="Rien à traiter pour le moment"
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
