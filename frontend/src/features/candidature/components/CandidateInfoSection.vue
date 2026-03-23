<script setup lang="ts">
import { computed } from 'vue'
import { Ruler, Weight, Wallet } from 'lucide-vue-next'
import type { CandidateFullProfile } from '../types'

/**
 * Props
 */
const props = defineProps<{
  candidate: CandidateFullProfile
}>()

/**
 * Computed: Has physical characteristics
 */
const hasPhysicalInfo = computed(
  (): boolean => !!props.candidate.taille || !!props.candidate.poids,
)

/**
 * Computed: Has pricing info
 */
const hasPricing = computed(
  (): boolean =>
    !!props.candidate.tarif_horaire || !!props.candidate.tarif_journalier,
)

/**
 * Computed: Half-day rate = tarif_journalier / 2
 */
const tarifDemiJournee = computed((): number | null =>
  props.candidate.tarif_journalier ? Math.round(props.candidate.tarif_journalier / 2) : null,
)

function formatCurrency(amount: number): string {
  return (
    new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'XOF',
      currencyDisplay: 'code',
    })
      .format(amount)
      .replace('XOF', '')
      .trim() + ' XOF'
  )
}

/**
 * Format height in meters (e.g., 180 -> "1m80")
 */
function formatHeight(taille: number | null): string | null {
  if (!taille) return null
  const meters = Math.floor(taille / 100)
  const centimeters = taille % 100
  return `${meters}m${centimeters.toString().padStart(2, '0')}`
}

/**
 * Format weight in kg
 */
function formatWeight(poids: number | null): string | null {
  if (!poids) return null
  return `${poids} kg`
}
</script>

<template>
  <div class="rounded-2xl border border-border bg-card p-6">
    <h2 class="mb-4 text-lg font-semibold text-foreground">Informations</h2>

    <div class="space-y-4">
      <!-- Physical Characteristics -->
      <div v-if="hasPhysicalInfo" class="space-y-3">
        <h3 class="text-sm font-medium uppercase tracking-wider text-muted-foreground">
          Caractéristiques physiques
        </h3>
        <div class="flex flex-wrap gap-4">
          <div v-if="candidate.taille" class="flex items-center gap-2">
            <div
              class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10"
            >
              <Ruler class="h-4 w-4 text-primary" />
            </div>
            <div>
              <p class="text-xs text-muted-foreground">Taille</p>
              <p class="font-medium text-foreground">
                {{ formatHeight(candidate.taille) }}
              </p>
            </div>
          </div>
          <div v-if="candidate.poids" class="flex items-center gap-2">
            <div
              class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10"
            >
              <Weight class="h-4 w-4 text-primary" />
            </div>
            <div>
              <p class="text-xs text-muted-foreground">Poids</p>
              <p class="font-medium text-foreground">
                {{ formatWeight(candidate.poids) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Divider -->
      <div v-if="hasPhysicalInfo && hasPricing" class="border-t border-border" />

      <!-- Pricing -->
      <div v-if="hasPricing" class="space-y-3">
        <h3 class="text-sm font-medium uppercase tracking-wider text-muted-foreground">
          Tarifs
        </h3>
        <div class="flex flex-wrap gap-4">
          <div v-if="tarifDemiJournee" class="flex items-center gap-2">
            <div
              class="flex h-9 w-9 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30"
            >
              <Wallet class="h-4 w-4 text-green-600 dark:text-green-400" />
            </div>
            <div>
              <p class="text-xs text-muted-foreground">Tarif demi-journée</p>
              <p class="font-semibold text-foreground">
                {{ formatCurrency(tarifDemiJournee) }}
              </p>
            </div>
          </div>
          <div v-if="candidate.tarif_journalier" class="flex items-center gap-2">
            <div
              class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30"
            >
              <Wallet class="h-4 w-4 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              <p class="text-xs text-muted-foreground">Tarif journalier</p>
              <p class="font-semibold text-foreground">
                {{ candidate.formatted_tarif_journalier }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <p
        v-if="!hasPhysicalInfo && !hasPricing"
        class="italic text-muted-foreground/70"
      >
        Aucune information complémentaire renseignée
      </p>
    </div>
  </div>
</template>
