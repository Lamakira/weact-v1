import { describe, it, expect } from 'vitest'
import { readFileSync } from 'fs'
import { resolve } from 'path'

describe('Toaster global', () => {
  const app = readFileSync(resolve(__dirname, '../App.vue'), 'utf-8')

  it('est monté une seule fois, en haut au centre', () => {
    expect(app.match(/^\s*<Toaster\s*$/gm)).toHaveLength(1)
    const block = app.slice(app.indexOf('\n  <Toaster'), app.indexOf('/>', app.indexOf('\n  <Toaster')))
    expect(block).toContain('position="top-center"')
    expect(block).toContain('rich-colors')
    expect(block).toContain(':duration="5000"')
  })
})
