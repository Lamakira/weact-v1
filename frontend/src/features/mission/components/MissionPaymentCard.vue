<script setup lang="ts">
import type { PaymentCardContent, Tone } from '../utils/missionDetailModel'

/** Carte « Paiement » de la page détail mission : montants et statut (en attente / séquestre / versé). */
defineProps<{
  card: PaymentCardContent
}>()

const TONE_CLASSES: Record<Tone, string> = {
  info: 'bg-primary/10 text-primary',
  warn: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
  ok: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
  mute: 'border border-border bg-muted text-muted-foreground',
}
</script>

<template>
  <section class="rounded-[10px] border border-border bg-card p-4" aria-label="Paiement" data-testid="mission-payment-card">
    <h3 class="text-[15px] font-bold text-foreground">Paiement</h3>
    <dl class="mt-2.5 grid gap-2 text-[13px]">
      <div v-for="row in card.rows" :key="row.label" class="flex justify-between gap-3">
        <dt class="text-muted-foreground">{{ row.label }}</dt>
        <dd class="text-right font-semibold text-foreground">{{ row.value }}</dd>
      </div>
      <div class="flex items-center justify-between gap-3 border-t border-border pt-2">
        <dt class="text-muted-foreground">Statut</dt>
        <dd>
          <span
            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold"
            :class="TONE_CLASSES[card.status.tone]"
            data-testid="mission-payment-status"
          >
            <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
            {{ card.status.label }}
          </span>
        </dd>
      </div>
    </dl>
  </section>
</template>
