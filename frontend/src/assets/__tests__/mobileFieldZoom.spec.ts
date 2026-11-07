/**
 * iOS Safari zooms the page in as soon as a text field under 16 px gets focus,
 * and never zooms back out. Most fields in the app are styled `text-sm` (14 px)
 * or smaller, so on an iPhone the page "zooms by itself" when someone taps into
 * a form, the chat composer or a filter.
 *
 * The fix is one unlayered rule in main.css that raises every text-entry field
 * to 16 px on phones and touch screens. Unlayered on purpose: unlayered CSS beats
 * every Tailwind utility whatever its specificity, so `text-sm` on a field cannot
 * win against it. These tests pin that contract, and forbid the tempting wrong
 * fix (locking the viewport scale), which also kills voluntary pinch-zoom.
 */
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, it, expect } from 'vitest'

const here = dirname(fileURLToPath(import.meta.url))
const mainCss = readFileSync(join(here, '../main.css'), 'utf-8')
const indexHtml = readFileSync(join(here, '../../../index.html'), 'utf-8')

interface CssBlock {
  prelude: string
  body: string
}

/** Top-level blocks of a stylesheet (depth 0 only), comments stripped. */
function topLevelBlocks(css: string): CssBlock[] {
  const source = css.replace(/\/\*[\s\S]*?\*\//g, '')
  const blocks: CssBlock[] = []
  let depth = 0
  let preludeStart = 0
  let bodyStart = 0
  for (let i = 0; i < source.length; i++) {
    const char = source[i]
    if (char === ';' && depth === 0) {
      preludeStart = i + 1
    } else if (char === '{') {
      if (depth === 0) bodyStart = i + 1
      depth++
    } else if (char === '}') {
      depth--
      if (depth === 0) {
        blocks.push({
          prelude: source.slice(preludeStart, bodyStart - 1).trim(),
          body: source.slice(bodyStart, i),
        })
        preludeStart = i + 1
      }
    }
  }
  return blocks
}

/** Selector list of the rule, inside `body`, that sets font-size to 16px. */
function fieldSelectorIn(body: string): string | null {
  const rule = topLevelBlocks(body).find((block) =>
    /(^|;|\s)font-size\s*:\s*16px\s*(;|$)/.test(block.body.trim()),
  )
  return rule ? rule.prelude : null
}

function mobileFieldRule(): { media: string; selector: string } | null {
  for (const block of topLevelBlocks(mainCss)) {
    if (!block.prelude.startsWith('@media')) continue
    const selector = fieldSelectorIn(block.body)
    if (selector) return { media: block.prelude, selector }
  }
  return null
}

function matchesFieldRule(markup: string): boolean {
  const rule = mobileFieldRule()
  if (!rule) return false
  const host = document.createElement('div')
  host.innerHTML = markup
  const el = host.firstElementChild as Element
  return el.matches(rule.selector)
}

describe('mobile text fields never trigger the iOS focus zoom', () => {
  it('sets text fields to 16px in an unlayered media rule of main.css', () => {
    // Top level, not inside @layer: that is what lets it beat text-sm & co.
    expect(mobileFieldRule(), 'unlayered @media rule with font-size: 16px').not.toBeNull()
  })

  it('applies on phones in portrait and landscape, and on touch screens', () => {
    const media = mobileFieldRule()?.media ?? ''
    // Phone portrait (390 px) and below Tailwind's md breakpoint.
    expect(media).toMatch(/max-width:\s*767\.98px/)
    // Phone landscape is wider than md (844 px on an iPhone 14): catch it by input type.
    expect(media).toMatch(/\(hover:\s*none\)\s*and\s*\(pointer:\s*coarse\)/)
    // A desktop with a mouse must not be affected.
    expect(media).not.toMatch(/min-width|pointer:\s*fine/)
  })

  it.each([
    '<input>',
    '<input type="text">',
    '<input type="email">',
    '<input type="password">',
    '<input type="search">',
    '<input type="tel">',
    '<input type="url">',
    '<input type="number">',
    '<input type="date">',
    '<input type="time">',
    '<input type="datetime-local">',
    '<input type="month">',
    '<textarea></textarea>',
    '<select></select>',
    '<div contenteditable="true"></div>',
    '<div contenteditable=""></div>',
  ])('covers the text-entry field %s', (markup) => {
    expect(matchesFieldRule(markup)).toBe(true)
  })

  it.each([
    '<input type="checkbox">',
    '<input type="radio">',
    '<input type="range">',
    '<input type="file">',
    '<input type="color">',
    '<input type="hidden">',
    '<input type="submit">',
    '<input type="button">',
    '<input type="reset">',
    '<input type="image">',
    '<div contenteditable="false"></div>',
    '<button></button>',
  ])('leaves the non-text control %s alone', (markup) => {
    expect(matchesFieldRule(markup)).toBe(false)
  })

  it('stops a quick double tap on a control from zooming, without touching pinch-zoom', () => {
    const base = topLevelBlocks(mainCss).find((block) => block.prelude === '@layer base')
    const rule = topLevelBlocks(base?.body ?? '').find((block) =>
      /touch-action\s*:\s*manipulation/.test(block.body),
    )
    expect(rule, '@layer base rule with touch-action: manipulation').toBeDefined()
    const selectors = (rule?.prelude ?? '').split(',').map((s) => s.trim())
    expect(selectors).toEqual(expect.arrayContaining(['a', 'button', 'input', 'select', 'textarea']))
    // Never the whole page: double-tap on text stays an accessibility zoom.
    expect(selectors).not.toEqual(expect.arrayContaining(['html']))
    expect(selectors).not.toEqual(expect.arrayContaining(['body']))
  })

  it('keeps voluntary pinch-zoom: the viewport never locks the scale', () => {
    const viewport = indexHtml.match(/<meta\s+name="viewport"\s+content="([^"]*)"/)?.[1]
    expect(viewport).toBeDefined()
    expect(viewport).toContain('width=device-width')
    expect(viewport).not.toMatch(/maximum-scale|user-scalable/)
  })
})
