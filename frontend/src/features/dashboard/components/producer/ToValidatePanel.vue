<script setup lang="ts">
/**
 * ToValidatePanel — module « Livrables à valider » du dashboard Producteur.
 * Les 5 premiers livrables UGC en attente de validation (le plus ancien d'abord,
 * ordre de l'inbox), avec l'âge depuis le dépôt. Passe en ambre une fois le délai
 * de validation (review_due_at) dépassé.
 */
import { RouterLink } from 'vue-router'
import { BadgeCheck } from 'lucide-vue-next'
import RPanel from '@/components/regie/RPanel.vue'
import { Skeleton } from '@/components/ui/skeleton'
import { buttonVariants } from '@/components/ui/button'
import { formatAge } from '@/lib/formatAge'
import type { DeliverableReviewItem } from '@/components/ugc/ugc'

interface Props {
  items: DeliverableReviewItem[]
  /** Nombre total de livrables à valider (peut dépasser `items`) */
  total: number
  isLoading?: boolean
  error?: string | null
  now?: Date
}

withDefaults(defineProps<Props>(), {
  isLoading: false,
  error: null,
  now: () => new Date(),
})

defineEmits<{ retry: [] }>()

function isOverdue(item: DeliverableReviewItem, now: Date): boolean {
  return item.review_due_at !== null && new Date(item.review_due_at).getTime() < now.getTime()
}

function age(item: DeliverableReviewItem, now: Date): string {
  return item.submitted_at ? formatAge(item.submitted_at, now) : '—'
}
</script>

<template>
  <RPanel title="Livrables à valider" data-testid="module-to-validate">
    <template #actions>
      <span
        v-if="!isLoading && !error && total > 0"
        class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-urgent-soft px-1.5 text-[11.5px] font-semibold text-urgent"
        data-testid="to-validate-count"
      >{{ total }}</span>
      <RouterLink
        :to="{ name: 'producer-ugc-validation' }"
        class="text-[12.5px] font-medium text-ink-2 hover:text-ink hover:underline"
        data-testid="to-validate-see-all"
      >Tout voir</RouterLink>
    </template>

    <div v-if="isLoading" class="space-y-3 p-4" data-testid="to-validate-loading">
      <Skeleton class="h-10 w-full" />
      <Skeleton class="h-10 w-full" />
    </div>

    <p v-else-if="error" class="p-4 text-dash text-ink-2" role="alert" data-testid="to-validate-error">
      {{ error }}
      <button type="button" class="font-semibold text-weact-700 hover:underline" @click="$emit('retry')">
        Réessayer
      </button>
    </p>

    <div
      v-else-if="items.length === 0"
      class="flex flex-col items-center gap-2 px-4 py-8 text-center"
      data-testid="to-validate-empty"
    >
      <BadgeCheck class="size-5 text-ink-3" aria-hidden="true" />
      <p class="text-dash text-ink-2">Aucun livrable en attente de validation.</p>
    </div>

    <ul v-else class="divide-y divide-line" data-testid="to-validate-list">
      <li
        v-for="item in items"
        :key="item.id"
        class="flex items-center gap-3 px-4 py-3 sm:px-5"
        data-testid="to-validate-row"
      >
        <div class="min-w-0 flex-1">
          <p class="truncate text-dash font-semibold text-ink">
            {{ item.kind_label }}
            <span v-if="item.product_name" class="font-normal text-ink-2">· {{ item.product_name }}</span>
          </p>
          <p class="truncate text-[12px] text-ink-3">
            {{ item.face_name ?? 'Face' }} ·
            <span
              :class="isOverdue(item, now) ? 'font-semibold text-urgent' : ''"
              :data-overdue="isOverdue(item, now) ? 'true' : undefined"
              data-testid="to-validate-age"
            >{{ age(item, now) }}</span>
          </p>
        </div>
        <RouterLink
          :to="{ name: 'producer-ugc-validation' }"
          :class="buttonVariants({ variant: 'regie-secondary', size: 'regie' })"
          :aria-label="`Examiner ${item.kind_label}${item.face_name ? ' de ' + item.face_name : ''}`"
        >Examiner</RouterLink>
      </li>
    </ul>
  </RPanel>
</template>
