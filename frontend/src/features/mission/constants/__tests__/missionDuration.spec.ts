import { describe, it, expect } from 'vitest'
import {
  MISSION_DURATION_PRESETS,
  formatCustomDuration,
  formatMissionDurationForDisplay,
  parseDurationToPreset,
} from '../missionDuration'

describe('MISSION_DURATION_PRESETS', () => {
  it('has 11 options (10 presets + custom)', () => {
    expect(MISSION_DURATION_PRESETS).toHaveLength(11)
  })

  it('starts with ½ journée (max 4h)', () => {
    expect(MISSION_DURATION_PRESETS[0].label).toBe('½ journée (max 4h)')
  })

  it('ends with custom option', () => {
    const last = MISSION_DURATION_PRESETS[MISSION_DURATION_PRESETS.length - 1]
    expect(last.value).toBe('custom')
    expect(last.label).toBe('Plus de 5 jours...')
  })

  it('preset values equal their labels (except custom)', () => {
    const presets = MISSION_DURATION_PRESETS.filter((p) => p.value !== 'custom')
    for (const preset of presets) {
      expect(preset.value).toBe(preset.label)
    }
  })
})

describe('formatCustomDuration', () => {
  it('formats 6 days correctly', () => {
    expect(formatCustomDuration(6)).toBe('6 jours (max 48h)')
  })

  it('formats 10 days correctly', () => {
    expect(formatCustomDuration(10)).toBe('10 jours (max 80h)')
  })
})

describe('formatMissionDurationForDisplay', () => {
  it('adds the max prefix to a legacy preset label', () => {
    expect(formatMissionDurationForDisplay('2 journées (16h)')).toBe('2 journées (max 16h)')
  })

  it('adds the max prefix to a legacy custom duration label', () => {
    expect(formatMissionDurationForDisplay('7 jours (56h)')).toBe('7 jours (max 56h)')
  })

  it('preserves the current format when max is already present', () => {
    expect(formatMissionDurationForDisplay('2 journées (max 16h)')).toBe('2 journées (max 16h)')
  })

  it('leaves values without hours unchanged', () => {
    expect(formatMissionDurationForDisplay('2 jours')).toBe('2 jours')
  })
})

describe('parseDurationToPreset', () => {
  it('recognizes a standard preset with max prefix', () => {
    expect(parseDurationToPreset('2 journées (max 16h)')).toEqual({ preset: '2 journées (max 16h)' })
  })

  it('recognizes the first preset with max prefix', () => {
    expect(parseDurationToPreset('½ journée (max 4h)')).toEqual({ preset: '½ journée (max 4h)' })
  })

  it('maps legacy preset (without max) to current preset', () => {
    expect(parseDurationToPreset('½ journée (4h)')).toEqual({ preset: '½ journée (max 4h)' })
    expect(parseDurationToPreset('2 journées (16h)')).toEqual({ preset: '2 journées (max 16h)' })
  })

  it('recognizes a custom duration pattern with max', () => {
    expect(parseDurationToPreset('7 jours (max 56h)')).toEqual({ preset: 'custom', customDays: 7 })
  })

  it('recognizes a legacy custom duration pattern without max', () => {
    expect(parseDurationToPreset('7 jours (56h)')).toEqual({ preset: 'custom', customDays: 7 })
  })

  it('falls back to custom with isLegacy flag for unrecognized legacy values', () => {
    expect(parseDurationToPreset('2 jours')).toEqual({ preset: 'custom', customDays: 6, isLegacy: true })
  })

  it('falls back to custom with isLegacy flag for arbitrary text', () => {
    expect(parseDurationToPreset('quelques heures')).toEqual({ preset: 'custom', customDays: 6, isLegacy: true })
  })
})
