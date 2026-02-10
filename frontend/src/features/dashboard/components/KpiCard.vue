<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Clock, Check, Play, CheckCircle2, Users, UserCheck, Star, Shield, Pencil, FileText } from 'lucide-vue-next'
import { Skeleton } from '@/components/ui/skeleton'
import type { KpiColor, KpiIcon } from '../types'
import type { RouteLocationRaw } from 'vue-router'

interface KpiCardProps {
  title: string
  value: number
  icon: KpiIcon
  color: KpiColor
  isLoading?: boolean
  to?: RouteLocationRaw
}

const props = withDefaults(defineProps<KpiCardProps>(), {
  isLoading: false,
  to: undefined,
})

const formattedValue = computed(() => {
  return new Intl.NumberFormat('fr-FR').format(props.value)
})

const iconComponent = computed(() => {
  const icons = {
    clock: Clock,
    check: Check,
    play: Play,
    checkCircle: CheckCircle2,
    users: Users,
    userCheck: UserCheck,
    star: Star,
    shield: Shield,
    edit: Pencil,
    file: FileText,
  }
  return icons[props.icon] || Clock
})

const colorClasses = computed(() => {
  const mapping = {
    primary: {
      bg: 'bg-pink-50',
      text: 'text-pink-500',
    },
    'amber-500': {
      bg: 'bg-amber-50',
      text: 'text-amber-500',
    },
    'green-500': {
      bg: 'bg-green-50',
      text: 'text-green-500',
    },
    'blue-500': {
      bg: 'bg-blue-50',
      text: 'text-blue-500',
    },
    'purple-500': {
      bg: 'bg-violet-50',
      text: 'text-violet-500',
    },
    'gray-500': {
      bg: 'bg-gray-50',
      text: 'text-gray-500',
    },
  }
  return mapping[props.color] || mapping['primary']
})
</script>

<template>
  <component
    :is="to ? RouterLink : 'div'"
    :to="to || undefined"
    class="bg-white rounded-2xl p-4 shadow-sm transition-all duration-200"
    :class="to ? 'hover:shadow-md hover:ring-1 hover:ring-teal-200 cursor-pointer block' : ''"
    :aria-label="`${title}: ${formattedValue}`"
    data-testid="kpi-card"
  >
    <div v-if="isLoading" class="flex items-center gap-3" data-testid="kpi-card-skeleton">
      <Skeleton class="h-11 w-11 rounded-xl" />
      <div class="space-y-2">
        <Skeleton class="h-6 w-14" />
        <Skeleton class="h-4 w-20" />
      </div>
    </div>

    <div v-else class="flex items-center gap-3">
      <div
        class="flex h-11 w-11 items-center justify-center rounded-xl shrink-0"
        :class="[colorClasses.bg, colorClasses.text]"
      >
        <component :is="iconComponent" :size="20" stroke-width="2" />
      </div>

      <div class="flex flex-col min-w-0">
        <span
          class="text-xl font-bold text-gray-900 leading-none"
          data-testid="kpi-card-value"
        >
          {{ formattedValue }}
        </span>
        <span
          class="mt-1 text-sm text-gray-500 truncate"
          data-testid="kpi-card-title"
        >
          {{ title }}
        </span>
      </div>
    </div>
  </component>
</template>
