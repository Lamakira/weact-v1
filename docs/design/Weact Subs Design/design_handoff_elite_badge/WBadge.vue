<script setup lang="ts">
/**
 * WBadge — Badge de tier WeAct
 *
 * Burst scalloped (8 ou 6 pointes selon taille) avec monogramme W blanc.
 * - tier="elite" → fond noir #0F1419
 * - tier="pro"   → fond teal #198496
 *
 * Le SVG conserve un viewBox fixe 24×24 : `fontSize` et `path` sont en unités
 * viewBox, le navigateur scale proportionnellement via width/height.
 *
 * Usage :
 *   <WBadge tier="elite" :size="22" title="Membre Élite" />
 *   <WBadge tier="pro" :size="14" />
 */

import { computed } from 'vue'

interface Props {
  /** 'elite' (noir) ou 'pro' (teal). Défaut : 'elite' */
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
const fill = computed(() => (props.tier === 'elite' ? '#0F1419' : '#198496'))
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
    <path :d="path" :fill="fill" />
    <text
      x="12"
      y="16"
      text-anchor="middle"
      font-size="11"
      font-weight="800"
      fill="#fff"
      font-family="Inter, system-ui, sans-serif"
      style="letter-spacing: -0.04em"
    >W</text>
  </svg>
</template>

<style scoped>
.weact-badge {
  display: inline-block;
  vertical-align: middle;
  flex-shrink: 0;
}
</style>
