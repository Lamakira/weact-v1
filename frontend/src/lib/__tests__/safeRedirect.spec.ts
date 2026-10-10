import { describe, it, expect } from 'vitest'
import { safeRedirect } from '../safeRedirect'

describe('safeRedirect', () => {
  it.each(['/pricing?plan=pro', '/face/profile', '/'])('accepts the same-origin path %s', (value) => {
    expect(safeRedirect(value)).toBe(value)
  })

  it.each([
    '//evil.com',
    '/\\evil.com',
    '/\\\\evil.com',
    '/foo\\bar',
    'https://x',
    'javascript:alert(1)',
    'pricing',
    '',
  ])('rejects %s', (value) => {
    expect(safeRedirect(value)).toBeNull()
  })

  it.each([null, undefined, 42, ['/a'], {}])('rejects the non-string %s', (value) => {
    expect(safeRedirect(value)).toBeNull()
  })
})
