<script setup lang="ts">
import { computed } from 'vue'
import {
  Users,
  Pencil,
  Trash2,
  XCircle,
  RefreshCw,
  CheckCircle2,
  ClipboardCheck,
  Wallet,
} from 'lucide-vue-next'
import type { Mission } from '../types'
import { getMissionActionState } from '../composables/missionActionState'

/**
 * Every action a producer has on a mission (candidatures, edit, pay commission,
 * close, reopen, validate attendance, complete, delete), as real buttons.
 * Used in the table row (icons) and in the compact mobile card (icons + labels).
 */
const props = withDefaults(
  defineProps<{
    mission: Mission
    emailVerified?: boolean
    showLabels?: boolean
  }>(),
  { emailVerified: true, showLabels: false },
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

const state = computed(() => getMissionActionState(props.mission, props.emailVerified))

const buttonBase =
  'inline-flex items-center justify-center gap-1.5 rounded-md border text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary'
const sizeClass = computed(() => (props.showLabels ? 'px-3 py-2' : 'h-8 w-8'))
</script>

<template>
  <div class="flex flex-wrap items-center gap-1.5" :class="showLabels ? 'gap-2' : 'justify-end'">
    <button
      type="button"
      data-testid="action-candidatures"
      :class="[buttonBase, sizeClass, 'border-border bg-card text-foreground hover:border-primary/40 hover:text-primary']"
      :aria-label="`Candidatures de la mission ${mission.titre}`"
      title="Candidatures"
      @click.stop="emit('viewCandidatures', mission.id)"
    >
      <Users class="h-4 w-4" aria-hidden="true" />
      <span v-if="showLabels">Candidatures</span>
    </button>

    <button
      v-if="state.canEdit"
      type="button"
      data-testid="action-edit"
      :class="[buttonBase, sizeClass, 'border-primary bg-primary text-primary-foreground hover:bg-primary/90']"
      :aria-label="`Modifier la mission ${mission.titre}`"
      title="Modifier"
      @click.stop="emit('edit', mission.id)"
    >
      <Pencil class="h-4 w-4" aria-hidden="true" />
      <span v-if="showLabels">Modifier</span>
    </button>

    <button
      v-if="state.canPayCommission"
      type="button"
      data-testid="pay-commission-button"
      :class="[buttonBase, sizeClass, 'border-weact bg-weact text-white hover:bg-weact/90']"
      :aria-label="`Régler la commission de la mission ${mission.titre}`"
      title="Régler la commission"
      @click.stop="emit('payCommission', mission.id)"
    >
      <Wallet class="h-4 w-4" aria-hidden="true" />
      <span v-if="showLabels">Régler la commission</span>
    </button>

    <button
      v-if="state.canClose"
      type="button"
      data-testid="action-close"
      :class="[buttonBase, sizeClass, 'border-orange-500 bg-orange-500 text-white hover:bg-orange-600']"
      :aria-label="`Clôturer les candidatures de la mission ${mission.titre}`"
      title="Clôturer les candidatures"
      @click.stop="emit('close', mission.id)"
    >
      <XCircle class="h-4 w-4" aria-hidden="true" />
      <span v-if="showLabels">Clôturer</span>
    </button>

    <button
      v-if="state.canReopen"
      type="button"
      data-testid="action-reopen"
      :class="[buttonBase, sizeClass, 'border-green-500 bg-green-500 text-white hover:bg-green-600']"
      :aria-label="`Réouvrir la mission ${mission.titre}`"
      title="Réouvrir la mission"
      @click.stop="emit('reopen', mission.id)"
    >
      <RefreshCw class="h-4 w-4" aria-hidden="true" />
      <span v-if="showLabels">Réouvrir</span>
    </button>

    <button
      v-if="state.canValidateAttendance"
      type="button"
      data-testid="action-attendance"
      :class="[buttonBase, sizeClass, 'border-amber-500 bg-amber-500 text-white hover:bg-amber-600']"
      :aria-label="`Valider les présences de la mission ${mission.titre}`"
      title="Valider les présences"
      @click.stop="emit('viewAttendance', mission.id)"
    >
      <ClipboardCheck class="h-4 w-4" aria-hidden="true" />
      <span v-if="showLabels">Valider les présences</span>
    </button>

    <button
      v-if="state.canComplete"
      type="button"
      data-testid="action-complete"
      :class="[buttonBase, sizeClass, 'border-blue-500 bg-blue-500 text-white hover:bg-blue-600']"
      :aria-label="`Marquer la mission ${mission.titre} comme terminée`"
      title="Marquer comme terminée"
      @click.stop="emit('complete', mission.id)"
    >
      <CheckCircle2 class="h-4 w-4" aria-hidden="true" />
      <span v-if="showLabels">Terminer</span>
    </button>

    <button
      v-if="state.canDelete"
      type="button"
      data-testid="action-delete"
      :class="[buttonBase, sizeClass, 'border-destructive/20 bg-destructive/10 text-destructive hover:bg-destructive hover:text-destructive-foreground']"
      :aria-label="`Supprimer la mission ${mission.titre}`"
      title="Supprimer la mission"
      @click.stop="emit('delete', mission.id)"
    >
      <Trash2 class="h-4 w-4" aria-hidden="true" />
      <span v-if="showLabels">Supprimer</span>
    </button>
  </div>
</template>
