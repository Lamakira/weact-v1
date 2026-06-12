<script setup lang="ts">
import { Check } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    /** Étape active 1-6 (7 = tout terminé, 0 = tout futur/neutre). */
    current: number
    overdue?: boolean
    variant?: 'horizontal' | 'vertical'
  }>(),
  { overdue: false, variant: 'horizontal' },
)

// Étapes du tunnel UGC — reprises STRICTEMENT de shared.jsx:392-399.
const STEPS = [
  { id: 1, short: 'Paiement', desc: 'Commission WeAct payée' },
  { id: 2, short: 'Acceptation', desc: 'La Face accepte le deal' },
  { id: 3, short: 'Expédition', desc: 'Produit envoyé + tracking' },
  { id: 4, short: 'Réception', desc: 'Produit reçu — chrono démarre' },
  { id: 5, short: 'Unboxing', desc: 'Vidéo 1 sous 7 jours' },
  { id: 6, short: 'Avis', desc: 'Vidéo 2 sous 14 jours' },
] as const
</script>

<template>
  <!-- Variante horizontale (écran 4A) — shared.jsx:435-466 -->
  <div v-if="variant === 'horizontal'" class="flex w-full items-center" data-testid="ugc-timeline-h">
    <template v-for="(step, i) in STEPS" :key="step.id">
      <div class="flex w-[84px] shrink-0 flex-col items-center gap-1.5">
        <div
          class="flex h-7 w-7 items-center justify-center rounded-full border-2 text-[10px] font-bold"
          :class="
            step.id < current
              ? 'border-[#198496] bg-[#198496] text-white'
              : step.id === current
                ? overdue
                  ? 'border-red-500 bg-white text-red-600'
                  : 'border-[#198496] bg-white text-[#198496]'
                : 'border-gray-200 bg-white text-gray-400'
          "
          :data-step-state="step.id < current ? 'done' : step.id === current ? 'active' : 'future'"
        >
          <Check v-if="step.id < current" class="h-3.5 w-3.5" :stroke-width="3" />
          <template v-else>{{ step.id }}</template>
        </div>
        <div
          class="text-center text-[10px] font-semibold"
          :class="
            step.id === current
              ? overdue ? 'text-red-600' : 'text-[#198496]'
              : step.id < current ? 'text-gray-900' : 'text-gray-400'
          "
        >
          {{ step.short }}
        </div>
      </div>
      <div
        v-if="i < STEPS.length - 1"
        class="h-0.5 flex-1 -mt-5"
        :class="step.id < current ? 'bg-[#198496]' : 'bg-gray-200'"
      />
    </template>
  </div>

  <!-- Variante verticale (consommée par 3.4) — shared.jsx:401-433 -->
  <ol v-else class="space-y-4" data-testid="ugc-timeline-v">
    <li v-for="step in STEPS" :key="step.id" class="flex gap-3">
      <div class="flex flex-col items-center">
        <div
          class="flex h-6 w-6 items-center justify-center rounded-full border-2 text-[10px] font-bold"
          :class="
            step.id < current
              ? 'border-[#198496] bg-[#198496] text-white'
              : step.id === current
                ? overdue
                  ? 'border-red-500 bg-white text-red-600'
                  : 'border-[#198496] bg-white text-[#198496]'
                : 'border-gray-200 bg-white text-gray-400'
          "
          :data-step-state="step.id < current ? 'done' : step.id === current ? 'active' : 'future'"
        >
          <Check v-if="step.id < current" class="h-3 w-3" :stroke-width="3" />
          <template v-else>{{ step.id }}</template>
        </div>
        <div
          v-if="step.id < STEPS.length"
          class="mb-1 mt-1 min-h-[26px] w-0.5 flex-1"
          :class="step.id < current ? 'bg-[#198496]' : 'bg-gray-200'"
        />
      </div>
      <div class="flex-1 pb-1">
        <div
          class="text-xs font-semibold"
          :class="
            step.id === current
              ? overdue ? 'text-red-600' : 'text-gray-900'
              : step.id < current ? 'text-gray-900' : 'text-gray-400'
          "
        >
          {{ step.short }}
        </div>
        <div
          class="mt-0.5 text-[11px]"
          :class="step.id <= current ? 'text-gray-500' : 'text-gray-400'"
        >
          {{ step.desc }}
        </div>
      </div>
    </li>
  </ol>
</template>
