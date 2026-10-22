<script setup lang="ts">
/**
 * ProducerTodoPanel — file « À faire » du dashboard Producteur (direction Régie).
 * Données : GET /producer/dashboard/todo (jusqu'à 8 items triés par urgence côté serveur).
 * Chaque ligne : trait de couleur à gauche, titre, meta, un seul bouton ; les items à
 * partie urgente (échéance < 48 h) ont un bouton primaire.
 */
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import RTodoList from '@/components/regie/RTodoList.vue'
import RTodoItem from '@/components/regie/RTodoItem.vue'
import RPanel from '@/components/regie/RPanel.vue'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import type { ProducerTodoItem } from '../../types'

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

// Trait de couleur de la ligne : attention dès qu'une échéance est sous 48 h (`urgent_meta`),
// danger si elle est dépassée (le serveur envoie alors « échéance dépassée », « délai de revue
// dépassé » ou « expire bientôt » à la place du compte à rebours).
function accent(item: ProducerTodoItem): 'none' | 'attention' | 'danger' {
  if (!item.urgent_meta) return 'none'
  return /dépass|expire bientôt/i.test(item.urgent_meta) ? 'danger' : 'attention'
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
        :accent="accent(item)"
        :action-label="item.action_label"
        :action-variant="item.urgent_meta ? 'primary' : 'secondary'"
        :to="item.url"
        @action="open(item)"
      />
    </RTodoList>
  </div>
</template>
