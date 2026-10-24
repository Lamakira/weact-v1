import { describe, expect, it } from 'vitest'
import { formatTypicalResponse } from '../utils/formatTypicalResponse'

describe('formatTypicalResponse', () => {
  it.each([
    [0, "moins d'1 h"],
    [59, "moins d'1 h"],
    [60, 'sous 1 h'],
    [165, 'sous 3 h'],
    [1380, 'sous 23 h'],
    [1381, 'sous 1 jour'],
    [1440, 'sous 1 jour'],
    [1441, "plus d'un jour"],
  ])('%i min -> %s', (minutes, expected) => {
    expect(formatTypicalResponse(minutes)).toBe(expected)
  })
})
