<script setup lang="ts">
import { computed, type Component } from 'vue'
import {
  Users,
  Pencil,
  Trash2,
  XCircle,
  RefreshCw,
  CheckCircle2,
  ClipboardCheck,
  MoreHorizontal,
} from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { Mission } from '../types'
import { getMissionActionState } from '../composables/missionActionState'

/**
 * Actions d'une mission (ligne de tableau et carte mobile) : UNE action principale
 * en bouton libellé + un menu « ⋯ » pour toutes les autres. Les règles d'autorisation
 * restent dans `getMissionActionState` ; ici, uniquement la présentation.
 *
 * Action principale (par priorité) : Régler la commission > Valider les présences >
 * Terminer > Candidatures.
 */
const props = withDefaults(
  defineProps<{
    mission: Mission
    emailVerified?: boolean
  }>(),
  { emailVerified: true },
)

const emit = defineEmits<{
  edit: [id: string]
  delete: [id: string]
  close: [id: string]
  reopen: [id: string]
  complete: [id: string]
  viewCandidatures: [id: string]
  viewAttendance: [id: string]
  payCommission: [id: string]
}>()

type ActionKey =
  | 'candidatures'
  | 'edit'
  | 'payCommission'
  | 'close'
  | 'reopen'
  | 'attendance'
  | 'complete'
  | 'delete'

interface ActionDef {
  key: ActionKey
  label: string
  ariaLabel: string
  testId: string
  icon: Component
}

const state = computed(() => getMissionActionState(props.mission, props.emailVerified))

const candidaturesLabel = computed(() =>
  props.mission.candidatures_count !== undefined
    ? `Candidatures · ${props.mission.candidatures_count}`
    : 'Candidatures',
)

const definitions = computed<Record<ActionKey, ActionDef>>(() => ({
  candidatures: {
    key: 'candidatures',
    label: candidaturesLabel.value,
    ariaLabel: `Candidatures de la mission ${props.mission.titre}`,
    testId: 'action-candidatures',
    icon: Users,
  },
  edit: {
    key: 'edit',
    label: 'Modifier',
    ariaLabel: `Modifier la mission ${props.mission.titre}`,
    testId: 'action-edit',
    icon: Pencil,
  },
  payCommission: {
    key: 'payCommission',
    label: 'Régler la commission',
    ariaLabel: `Régler la commission de la mission ${props.mission.titre}`,
    testId: 'pay-commission-button',
    icon: CheckCircle2,
  },
  close: {
    key: 'close',
    label: 'Clôturer',
    ariaLabel: `Clôturer les candidatures de la mission ${props.mission.titre}`,
    testId: 'action-close',
    icon: XCircle,
  },
  reopen: {
    key: 'reopen',
    label: 'Réouvrir',
    ariaLabel: `Réouvrir la mission ${props.mission.titre}`,
    testId: 'action-reopen',
    icon: RefreshCw,
  },
  attendance: {
    key: 'attendance',
    label: 'Valider les présences',
    ariaLabel: `Valider les présences de la mission ${props.mission.titre}`,
    testId: 'action-attendance',
    icon: ClipboardCheck,
  },
  complete: {
    key: 'complete',
    label: 'Terminer',
    ariaLabel: `Marquer la mission ${props.mission.titre} comme terminée`,
    testId: 'action-complete',
    icon: CheckCircle2,
  },
  delete: {
    key: 'delete',
    label: 'Supprimer',
    ariaLabel: `Supprimer la mission ${props.mission.titre}`,
    testId: 'action-delete',
    icon: Trash2,
  },
}))

const primaryKey = computed<ActionKey>(() => {
  if (state.value.canPayCommission) return 'payCommission'
  if (state.value.canValidateAttendance) return 'attendance'
  if (state.value.canComplete) return 'complete'
  return 'candidatures'
})

const primary = computed(() => definitions.value[primaryKey.value])

/** Primary teal for the money/attendance steps, secondary for the rest. */
const primaryVariant = computed(() =>
  primaryKey.value === 'payCommission' || primaryKey.value === 'attendance'
    ? 'regie'
    : 'regie-secondary',
)

/** Every other allowed action, in menu order (Supprimer handled separately, last). */
const menuItems = computed<ActionDef[]>(() => {
  const s = state.value
  const keys: ActionKey[] = ['candidatures']
  if (s.canEdit) keys.push('edit')
  if (s.canClose) keys.push('close')
  if (s.canReopen) keys.push('reopen')
  if (s.canValidateAttendance) keys.push('attendance')
  if (s.canComplete) keys.push('complete')
  return keys.filter((k) => k !== primaryKey.value).map((k) => definitions.value[k])
})

const showDelete = computed(() => state.value.canDelete)
const hasMenu = computed(() => menuItems.value.length > 0 || showDelete.value)

function trigger(key: ActionKey): void {
  const id = props.mission.id
  switch (key) {
    case 'candidatures':
      return emit('viewCandidatures', id)
    case 'edit':
      return emit('edit', id)
    case 'payCommission':
      return emit('payCommission', id)
    case 'close':
      return emit('close', id)
    case 'reopen':
      return emit('reopen', id)
    case 'attendance':
      return emit('viewAttendance', id)
    case 'complete':
      return emit('complete', id)
    case 'delete':
      return emit('delete', id)
  }
}
</script>

<template>
  <div class="flex items-center justify-end gap-2 whitespace-nowrap">
    <Button
      type="button"
      :variant="primaryVariant"
      size="regie"
      :data-testid="primary.testId"
      data-primary-action
      :aria-label="primary.ariaLabel"
      @click.stop="trigger(primary.key)"
    >
      {{ primary.label }}
    </Button>

    <DropdownMenu v-if="hasMenu">
      <DropdownMenuTrigger as-child>
        <Button
          type="button"
          variant="regie-secondary"
          size="regie"
          class="w-10 shrink-0 px-0 md:w-8"
          data-testid="actions-menu-trigger"
          aria-label="Plus d'actions"
          @click.stop
        >
          <MoreHorizontal class="size-4" aria-hidden="true" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" class="min-w-48" data-testid="actions-menu">
        <DropdownMenuItem
          v-for="item in menuItems"
          :key="item.key"
          :data-testid="item.testId"
          :aria-label="item.ariaLabel"
          class="min-h-10 md:min-h-8"
          @select="trigger(item.key)"
        >
          <component :is="item.icon" aria-hidden="true" />
          {{ item.label }}
        </DropdownMenuItem>

        <template v-if="showDelete">
          <DropdownMenuSeparator v-if="menuItems.length > 0" />
          <DropdownMenuItem
            variant="destructive"
            data-testid="action-delete"
            :aria-label="definitions.delete.ariaLabel"
            class="min-h-10 md:min-h-8"
            @select="trigger('delete')"
          >
            <Trash2 aria-hidden="true" />
            Supprimer
          </DropdownMenuItem>
        </template>
      </DropdownMenuContent>
    </DropdownMenu>
  </div>
</template>
