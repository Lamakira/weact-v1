<script setup lang="ts">
/**
 * WBadge — Badge de tier WeAct
 *
 * Burst scalloped (8 ou 6 pointes selon taille) sur fond vert WeAct (#198496, la
 * couleur principale du site), avec le « W » du logo (le favicon) en blanc au centre.
 *
 * Le SVG conserve un viewBox fixe 24×24 : la pointe et le glyphe W sont en unités
 * viewBox, le navigateur scale proportionnellement via width/height. Le glyphe W est
 * le tracé vectoriel du favicon (`public/favicons/favicon-*.svg`), imbriqué via un
 * `<svg>` interne pour réutiliser son viewBox d'origine sans recalculer les chemins.
 *
 * Usage :
 *   <WBadge tier="elite" :size="22" title="Membre Élite" />
 *   <WBadge tier="pro" :size="14" />
 */

import { computed } from 'vue'

interface Props {
  /** 'elite' ou 'pro' — n'affecte que le libellé accessible (le fond reste le vert WeAct). Défaut : 'elite' */
  tier?: 'elite' | 'pro'
  /** Taille en pixels (largeur = hauteur). Défaut : 20 */
  size?: number
  /** Texte accessible (lecteur d'écran + tooltip natif). Défaut : "Membre Élite" / "Membre Pro" */
  title?: string
}

const props = withDefaults(defineProps<Props>(), {
  tier: 'elite',
  size: 20,
  title: undefined,
})

// =============================================================
// Generate a "burst" SVG path (scalloped circle)
// =============================================================
function burstPath(cx: number, cy: number, outerR: number, innerR: number, points: number): string {
  const step = (Math.PI * 2) / (points * 2)
  let d = ''
  for (let i = 0; i < points * 2; i++) {
    const angle = i * step - Math.PI / 2
    const r = i % 2 === 0 ? outerR : innerR
    const x = cx + r * Math.cos(angle)
    const y = cy + r * Math.sin(angle)
    d += i === 0 ? `M ${x.toFixed(2)} ${y.toFixed(2)}` : ` L ${x.toFixed(2)} ${y.toFixed(2)}`
  }
  return d + ' Z'
}

const BURST_8 = burstPath(12, 12, 11.5, 10, 8)
const BURST_6 = burstPath(12, 12, 11.5, 10.2, 6)

// Use simpler 6-point burst under 18px for legibility
const path = computed(() => (props.size < 18 ? BURST_6 : BURST_8))

// Fond = vert principal du site (WEACT primary, --color-weact).
const BG = '#198496'

// "W" du logo (favicon) — deux tracés dans le viewBox d'origine 220 220 640 640.
const LOGO_W_PATHS = [
  'M520.22,370.63c2.53-14.9-8.95-28.5-24.07-28.5h-212.94c-16.57,0-28.33,16.16-23.23,31.93l112.3,346.92c3.26,10.07,12.64,16.9,23.23,16.9h91.45c17.8,0,29.62-18.44,22.18-34.62l-30.03-65.29c-2.05-4.46-2.71-9.45-1.89-14.29l42.99-253.05Z',
  'M594.88,342.13h-19.31c-11.91,0-22.07,8.59-24.07,20.32l-26.44,155.58-17.95,105.64c-.82,4.85-.16,9.83,1.89,14.3l16.06,34.91,23.36,50.78c3.98,8.66,12.65,14.21,22.18,14.21h113.91c10.59,0,19.97-6.82,23.23-16.9l112.29-346.92c5.1-15.77-6.65-31.93-23.23-31.93h-201.91Z',
]

const ariaLabel = computed(
  () => props.title ?? (props.tier === 'elite' ? 'Membre Élite' : 'Membre Pro'),
)
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    :aria-label="ariaLabel"
    role="img"
    class="weact-badge"
  >
    <title>{{ ariaLabel }}</title>
    <path :d="path" :fill="BG" />
    <!-- "W" du logo (favicon), centré dans la pointe via le viewBox d'origine -->
    <svg x="4" y="4" width="16" height="16" viewBox="220 220 640 640">
      <path v-for="(d, i) in LOGO_W_PATHS" :key="i" :d="d" fill="#fff" />
    </svg>
  </svg>
</template>

<style scoped>
.weact-badge {
  display: inline-block;
  vertical-align: middle;
  flex-shrink: 0;
}
</style>
