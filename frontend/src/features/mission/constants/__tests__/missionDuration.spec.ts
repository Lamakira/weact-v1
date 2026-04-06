import { describe, it, expect } from 'vitest'
import {
  MISSION_DURATION_PRESETS,
  formatCustomDuration,
  parseDurationToPreset,
} from '../missionDuration'

describe('MISSION_DURATION_PRESETS', () => {
  it('has 11 options (10 presets + custom)', () => {
    expect(MISSION_DURATION_PRESETS).toHaveLength(11)
  })

  it('starts with ½ journée (4h)', () => {
    expect(MISSION_DURATION_PRESETS[0].label).toBe('½ journée (4h)')
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
    expect(formatCustomDuration(6)).toBe('6 jours (48h)')
  })

  it('formats 10 days correctly', () => {
    expect(formatCustomDuration(10)).toBe('10 jours (80h)')
  })
})

describe('parseDurationToPreset', () => {
  it('recognizes a standard preset', () => {
    expect(parseDurationToPreset('2 journées (16h)')).toEqual({ preset: '2 journées (16h)' })
  })

  it('recognizes the first preset', () => {
    expect(parseDurationToPreset('½ journée (4h)')).toEqual({ preset: '½ journée (4h)' })
  })

  it('recognizes a custom duration pattern', () => {
    expect(parseDurationToPreset('7 jours (56h)')).toEqual({ preset: 'custom', customDays: 7 })
  })

  it('falls back to custom with isLegacy flag for unrecognized legacy values', () => {
    expect(parseDurationToPreset('2 jours')).toEqual({ preset: 'custom', customDays: 6, isLegacy: true })
  })

  it('falls back to custom with isLegacy flag for arbitrary text', () => {
    expect(parseDurationToPreset('quelques heures')).toEqual({ preset: 'custom', customDays: 6, isLegacy: true })
  })
})
