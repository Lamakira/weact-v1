<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Check, Loader2, Minus, Plus, UserPlus } from 'lucide-vue-next'
import { useDismissOnDeactivate } from '@/composables/useDismissOnDeactivate'
import { businessTodayIso } from '@/lib/dates'
import { useAddMissionPlaces } from '../composables/useAddMissionPlaces'
import type { Mission } from '../types'

const MIN_PLACES = 1
const MAX_PLACES = 20

interface Props {
  isOpen: boolean
  missionId: string
  missionTitle: string
  /** Nombre de places actuel (nombre_faces_voulu). */
  totalPlaces: number
  /** Places déjà prises (acceptées, confirmées, en cours, terminées). */
  takenPlaces: number
  /** Mission UGC hybride (rémunération en plus du produit) ; sinon produit seul. */
  isHybrid: boolean
  /** Date limite de candidature (AAAA-MM-JJ ou ISO). */
  deadline?: string | null
  /** `card` : raccourci depuis une carte de candidature (1 place, pas de compteur). */
  mode?: 'menu' | 'card'
  faceName?: string
}

const props = withDefaults(defineProps<Props>(), {
  deadline: null,
  mode: 'menu',
  faceName: '',
})

const emit = defineEmits<{
  cancel: []
  added: [places: number, mission?: Mission]
}>()

const { isAdding, error, addPlaces, resetError } = useAddMissionPlaces()

const count = ref(MIN_PLACES)
const isCardMode = computed(() => props.mode === 'card')
const effectivePlaces = computed(() => (isCardMode.value ? 1 : count.value))

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      count.value = MIN_PLACES
      resetError()
    }
  },
)

// Close when the host page is deactivated by <keep-alive> — the teleported
// overlay would otherwise stay in <body> on top of the next page.
useDismissOnDeactivate(() => props.isOpen, () => emit('cancel'))

function clamp(value: number): number {
  if (!Number.isFinite(value)) return MIN_PLACES
  return Math.min(MAX_PLACES, Math.max(MIN_PLACES, Math.trunc(value)))
}

function decrement(): void {
  count.value = clamp(count.value - 1)
}

function increment(): void {
  count.value = clamp(count.value + 1)
}

function onInput(event: Event): void {
  const input = event.target as HTMLInputElement
  count.value = clamp(Number(input.value))
  input.value = String(count.value)
}

const truncatedTitle = computed(() =>
  props.missionTitle.length > 50 ? props.missionTitle.substring(0, 50) + '...' : props.missionTitle,
)

function plural(n: number, singular: string, pluralForm: string): string {
  return n > 1 ? pluralForm : singular
}

// Date limite : comparée au jour métier (Bénin) ; passée si strictement avant aujourd'hui.
const deadlineDay = computed(() => (props.deadline ? props.deadline.slice(0, 10) : null))
const deadlinePassed = computed(() => (deadlineDay.value ? deadlineDay.value < businessTodayIso() : false))
const deadlineLabel = computed(() => {
  if (!deadlineDay.value) return null
  const [y, m, d] = deadlineDay.value.split('-').map(Number)
  if (!y || !m || !d) return null
  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long' }).format(new Date(y, m - 1, d))
})

const buttonLabel = computed(() => {
  if (isCardMode.value) return `Ajouter 1 place et accepter ${props.faceName}`
  return `Ajouter ${count.value} ${plural(count.value, 'place', 'places')}`
})

async function handleConfirm(): Promise<void> {
  if (isAdding.value) return
  const places = effectivePlaces.value
  const result = await addPlaces(props.missionId, places)
  if (result.success) emit('added', places, result.data)
}

function handleCancel(): void {
  if (!isAdding.value) emit('cancel')
}

function handleBackdropClick(event: MouseEvent): void {
  if (event.target === event.currentTarget) handleCancel()
}

function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.isOpen) handleCancel()
}

onMounted(() => document.addEventListener('keydown', handleKeydown))
onUnmounted(() => document.removeEventListener('keydown', handleKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
        data-testid="add-places-dialog"
        @click="handleBackdropClick"
      >
        <div
          class="bg-background rounded-lg shadow-xl max-w-md w-full p-6 border border-border"
          role="dialog"
          aria-modal="true"
          aria-labelledby="add-places-dialog-title"
        >
          <div class="flex justify-center mb-4">
            <div class="w-12 h-12 rounded-full bg-weact-50 flex items-center justify-center">
              <UserPlus class="w-6 h-6 text-weact-600" aria-hidden="true" />
            </div>
          </div>

          <h2 id="add-places-dialog-title" class="text-lg font-semibold text-center text-foreground mb-2">
            {{ isCardMode ? 'Plus de place libre' : 'Ajouter des places' }}
          </h2>

          <p class="text-sm text-muted-foreground text-center mb-5" data-testid="add-places-subtitle">
            <template v-if="isCardMode">
              Toutes les places de cette mission sont prises. Ajoutez-en une pour retenir {{ faceName }}.
            </template>
            <template v-else>
              {{ truncatedTitle }} · {{ totalPlaces }} {{ plural(totalPlaces, 'place', 'places') }},
              {{ takenPlaces }} {{ plural(takenPlaces, 'prise', 'prises') }}.
            </template>
          </p>

          <div v-if="!isCardMode" class="mb-5" data-testid="add-places-counter">
            <label for="add-places-input" class="block text-sm font-medium text-foreground mb-2 text-center">
              Places à ajouter
            </label>
            <div class="flex items-center justify-center gap-3">
              <button
                type="button"
                class="h-10 w-10 rounded-lg border border-border flex items-center justify-center transition-colors hover:bg-secondary disabled:opacity-50 disabled:cursor-not-allowed"
                aria-label="Retirer une place à ajouter"
                data-testid="add-places-minus"
                :disabled="isAdding || count <= MIN_PLACES"
                @click="decrement"
              >
                <Minus class="w-4 h-4" aria-hidden="true" />
              </button>
              <input
                id="add-places-input"
                type="number"
                inputmode="numeric"
                :min="MIN_PLACES"
                :max="MAX_PLACES"
                :value="count"
                :disabled="isAdding"
                class="h-10 w-20 rounded-lg border border-border bg-background text-center text-base font-semibold text-foreground"
                data-testid="add-places-input"
                @input="onInput"
              />
              <button
                type="button"
                class="h-10 w-10 rounded-lg border border-border flex items-center justify-center transition-colors hover:bg-secondary disabled:opacity-50 disabled:cursor-not-allowed"
                aria-label="Ajouter une place de plus"
                data-testid="add-places-plus"
                :disabled="isAdding || count >= MAX_PLACES"
                @click="increment"
              >
                <Plus class="w-4 h-4" aria-hidden="true" />
              </button>
            </div>
            <p class="mt-2 text-xs text-muted-foreground text-center" data-testid="add-places-new-total">
              La mission passera à {{ totalPlaces + count }} {{ plural(totalPlaces + count, 'place', 'places') }}.
            </p>
          </div>

          <ul class="mb-5 space-y-2 rounded-lg border border-weact-100 bg-weact-50 p-3 text-sm text-foreground">
            <li class="flex gap-2" data-testid="add-places-line-cost">
              <Check class="mt-0.5 h-4 w-4 shrink-0 text-weact-600" aria-hidden="true" />
              <span v-if="isHybrid">
                Chaque Face retenue est réglée au moment où vous l'acceptez : sa rémunération + 10 % de frais de service.
              </span>
              <span v-else>Sans frais : la commission déjà réglée couvre ces places.</span>
            </li>
            <li class="flex gap-2">
              <Check class="mt-0.5 h-4 w-4 shrink-0 text-weact-600" aria-hidden="true" />
              <span>La mission est rouverte : vous pouvez accepter les candidatures en attente.</span>
            </li>
            <li class="flex gap-2" data-testid="add-places-line-deadline">
              <Check class="mt-0.5 h-4 w-4 shrink-0 text-weact-600" aria-hidden="true" />
              <span v-if="deadlinePassed">
                La date limite est passée : seules les candidatures en attente peuvent être acceptées.
              </span>
              <span v-else-if="deadlineLabel">
                D'autres Faces peuvent encore postuler jusqu'au {{ deadlineLabel }}.
              </span>
            </li>
          </ul>

          <p v-if="error" class="mb-4 text-sm text-destructive text-center" role="alert" data-testid="add-places-error">
            {{ error }}
          </p>

          <div class="flex gap-3">
            <button
              type="button"
              :disabled="isAdding"
              class="flex-1 px-4 py-2.5 rounded-lg border border-border bg-secondary text-secondary-foreground font-medium transition-colors hover:bg-secondary/80 disabled:opacity-50 disabled:cursor-not-allowed"
              data-testid="add-places-cancel"
              @click="handleCancel"
            >
              Annuler
            </button>
            <button
              type="button"
              :disabled="isAdding"
              class="flex-1 px-4 py-2.5 rounded-lg bg-primary text-primary-foreground font-medium transition-colors hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              data-testid="add-places-confirm"
              @click="handleConfirm"
            >
              <Loader2 v-if="isAdding" class="w-4 h-4 animate-spin" aria-hidden="true" />
              <span>{{ buttonLabel }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
