import { describe, it, expect } from 'vitest'
import { formatAge, formatMessageTime } from '../formatAge'

const NOW = new Date('2026-10-09T12:00:00')

describe('formatAge', () => {
  it('renders minutes under an hour', () => {
    expect(formatAge('2026-10-09T11:40:00', NOW)).toBe('20 min')
    expect(formatAge('2026-10-09T11:59:40', NOW)).toBe('1 min')
  })

  it('renders hours under a day', () => {
    expect(formatAge('2026-10-09T10:00:00', NOW)).toBe('2 h')
  })

  it('renders « hier » between 24 and 48 hours', () => {
    expect(formatAge('2026-10-08T10:00:00', NOW)).toBe('hier')
  })

  it('renders days from 48 hours', () => {
    expect(formatAge('2026-10-06T12:00:00', NOW)).toBe('3 j')
  })

  it('never goes negative for a timestamp slightly in the future', () => {
    expect(formatAge('2026-10-09T12:00:30', NOW)).toBe('1 min')
  })
})

describe('formatMessageTime', () => {
  it('renders the hour for today', () => {
    expect(formatMessageTime('2026-10-09T10:42:00', NOW)).toBe('10:42')
  })

  it('renders « hier » for yesterday', () => {
    expect(formatMessageTime('2026-10-08T23:10:00', NOW)).toBe('hier')
  })

  it('renders day/month for older messages', () => {
    expect(formatMessageTime('2026-10-02T09:00:00', NOW)).toBe('02/10')
  })
})
