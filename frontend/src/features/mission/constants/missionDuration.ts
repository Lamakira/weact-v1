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
  { value: '½ journée (max 4h)', label: '½ journée (max 4h)' },
  { value: '1 journée (max 8h)', label: '1 journée (max 8h)' },
  { value: '1,5 jour (max 12h)', label: '1,5 jour (max 12h)' },
  { value: '2 journées (max 16h)', label: '2 journées (max 16h)' },
  { value: '2,5 jours (max 20h)', label: '2,5 jours (max 20h)' },
  { value: '3 journées (max 24h)', label: '3 journées (max 24h)' },
  { value: '3,5 jours (max 28h)', label: '3,5 jours (max 28h)' },
  { value: '4 journées (max 32h)', label: '4 journées (max 32h)' },
  { value: '4,5 jours (max 36h)', label: '4,5 jours (max 36h)' },
  { value: '5 journées (max 40h)', label: '5 journées (max 40h)' },
  { value: 'custom', label: 'Plus de 5 jours...' },
]

/** Format a custom number of days into the standardized duration string. */
export function formatCustomDuration(days: number): string {
  return `${days} jours (max ${days * 8}h)`
}

/**
 * Normalize mission duration strings for display so legacy values also show
 * the explicit "max" prefix when an hours segment is present.
 */
export function formatMissionDurationForDisplay(duree: string | null | undefined): string {
  if (!duree) return ''
  return duree.replace(/\((?:max\s+)?(\d+h)\)/, '(max $1)')
}

/** All preset label values (excluding custom) for matching. */
const PRESET_VALUES = new Set(
  MISSION_DURATION_PRESETS
    .filter((p) => p.value !== 'custom')
    .map((p) => p.value),
)

/** Map legacy preset values (without "max") to current values. */
const LEGACY_PRESET_MAP = new Map<string, string>([
  ['½ journée (4h)', '½ journée (max 4h)'],
  ['1 journée (8h)', '1 journée (max 8h)'],
  ['1,5 jour (12h)', '1,5 jour (max 12h)'],
  ['2 journées (16h)', '2 journées (max 16h)'],
  ['2,5 jours (20h)', '2,5 jours (max 20h)'],
  ['3 journées (24h)', '3 journées (max 24h)'],
  ['3,5 jours (28h)', '3,5 jours (max 28h)'],
  ['4 journées (32h)', '4 journées (max 32h)'],
  ['4,5 jours (36h)', '4,5 jours (max 36h)'],
  ['5 journées (40h)', '5 journées (max 40h)'],
])

/** Custom duration pattern: "{N} jours (max {M}h)" or legacy "{N} jours ({M}h)" */
const CUSTOM_PATTERN = /^(\d+)\s+jours\s+\((?:max\s+)?\d+h\)$/

/**
 * Parse a stored duration string back to a preset selection.
 * Used for edit mode pre-population.
 */
export function parseDurationToPreset(duree: string): { preset: string; customDays?: number; isLegacy?: boolean } {
  if (PRESET_VALUES.has(duree)) {
    return { preset: duree }
  }

  const legacyMapped = LEGACY_PRESET_MAP.get(duree)
  if (legacyMapped) {
    return { preset: legacyMapped }
  }

  const match = duree.match(CUSTOM_PATTERN)
  if (match) {
    const days = parseInt(match[1]!, 10)
    if (days > 5) {
      return { preset: 'custom', customDays: days }
    }
  }

  // Legacy free-text value — preserve as-is, don't overwrite
  return { preset: 'custom', customDays: 6, isLegacy: true }
}
