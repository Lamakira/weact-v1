import { describe, expect, it } from 'vitest'
import { formatBookingDeadline } from '../formatBookingDeadline'

describe('formatBookingDeadline', () => {
  it('formats in the business timezone (UTC+1), like the backend notifications', () => {
    expect(formatBookingDeadline('2026-10-23T12:00:00+00:00')).toBe('23/10 à 13:00')
  })

  it('rolls over to the next day in business time', () => {
    expect(formatBookingDeadline('2026-10-21T23:30:00+00:00')).toBe('22/10 à 00:30')
  })
})
