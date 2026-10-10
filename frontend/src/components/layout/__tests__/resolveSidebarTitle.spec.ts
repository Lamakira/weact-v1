import { describe, it, expect } from 'vitest'
import { resolveSidebarTitle } from '../resolveSidebarTitle'

const items = [
  { label: 'Missions', to: '/x/missions' },
  { label: 'Publier', to: '/x/missions/publish' },
]

describe('resolveSidebarTitle', () => {
  it('returns the label of an exact match', () => {
    expect(resolveSidebarTitle(items, '/x/missions')).toBe('Missions')
  })
  it('prefers the longest matching prefix', () => {
    expect(resolveSidebarTitle(items, '/x/missions/publish')).toBe('Publier')
  })
  it('inherits the section for sub-routes', () => {
    expect(resolveSidebarTitle(items, '/x/missions/abc/edit')).toBe('Missions')
  })
  it('does not match a path sharing only a string prefix', () => {
    expect(resolveSidebarTitle(items, '/x/missionsfoo')).toBe('Tableau de bord')
  })
  it('falls back when nothing matches', () => {
    expect(resolveSidebarTitle(items, '/other', 'Autre')).toBe('Autre')
  })
  it('uses the extra `match` prefixes of an item (open conversation ⇒ Messages)', () => {
    const withMessages = [...items, { label: 'Messages', to: '/x/messages', match: ['/x/conversations'] }]
    expect(resolveSidebarTitle(withMessages, '/x/conversations/abc-123')).toBe('Messages')
    expect(resolveSidebarTitle(withMessages, '/x/messages')).toBe('Messages')
  })
})
