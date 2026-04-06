type SelectOption = {
  value: string
  label: string
}

/**
 * Duration presets for mission publication form.
 * Same structure as booking duration presets but stores human-readable labels
 * instead of integer hours (mission.duree is a string field).
 */
export const MISSION_DURATION_PRESETS: SelectOption[] = [
  { value: '½ journée (4h)', label: '½ journée (4h)' },
  { value: '1 journée (8h)', label: '1 journée (8h)' },
  { value: '1,5 jour (12h)', label: '1,5 jour (12h)' },
  { value: '2 journées (16h)', label: '2 journées (16h)' },
  { value: '2,5 jours (20h)', label: '2,5 jours (20h)' },
  { value: '3 journées (24h)', label: '3 journées (24h)' },
  { value: '3,5 jours (28h)', label: '3,5 jours (28h)' },
  { value: '4 journées (32h)', label: '4 journées (32h)' },
  { value: '4,5 jours (36h)', label: '4,5 jours (36h)' },
  { value: '5 journées (40h)', label: '5 journées (40h)' },
  { value: 'custom', label: 'Plus de 5 jours...' },
]

/** Format a custom number of days into the standardized duration string. */
export function formatCustomDuration(days: number): string {
  return `${days} jours (${days * 8}h)`
}

/** All preset label values (excluding custom) for matching. */
const PRESET_VALUES = new Set(
  MISSION_DURATION_PRESETS
    .filter((p) => p.value !== 'custom')
    .map((p) => p.value),
)

/** Custom duration pattern: "{N} jours ({M}h)" */
const CUSTOM_PATTERN = /^(\d+)\s+jours\s+\(\d+h\)$/

/**
 * Parse a stored duration string back to a preset selection.
 * Used for edit mode pre-population.
 */
export function parseDurationToPreset(duree: string): { preset: string; customDays?: number; isLegacy?: boolean } {
  if (PRESET_VALUES.has(duree)) {
    return { preset: duree }
  }

  const match = duree.match(CUSTOM_PATTERN)
  if (match) {
    const days = parseInt(match[1], 10)
    if (days > 5) {
      return { preset: 'custom', customDays: days }
    }
  }

  // Legacy free-text value — preserve as-is, don't overwrite
  return { preset: 'custom', customDays: 6, isLegacy: true }
}
