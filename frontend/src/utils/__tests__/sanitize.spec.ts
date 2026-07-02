// @vitest-environment jsdom
//
// DOMPurify relies on a spec-faithful DOM. happy-dom (the suite's default env) diverges
// from real browsers in a way that breaks DOMPurify >= 3.4 (it reports isSupported=true
// but does NOT strip scripts). jsdom mirrors real-browser behaviour (it's DOMPurify's own
// test env), so this file overrides the environment to exercise the real sanitiser path.
// Verified: in a real browser and under jsdom, DOMPurify 3.4.x strips script/iframe/onerror.
import { describe, it, expect } from 'vitest'
import { sanitizeHtml } from '../sanitize'

describe('sanitizeHtml', () => {
  it('returns empty string for empty input', () => {
    expect(sanitizeHtml('')).toBe('')
  })

  it('preserves safe HTML tags', () => {
    const input = '<p>Hello <strong>world</strong></p>'
    expect(sanitizeHtml(input)).toBe('<p>Hello <strong>world</strong></p>')
  })

  it('preserves links with href', () => {
    const input = '<a href="https://example.com">Link</a>'
    expect(sanitizeHtml(input)).toBe('<a href="https://example.com">Link</a>')
  })

  it('preserves images with src and alt', () => {
    const input = '<img src="https://example.com/img.jpg" alt="Photo">'
    expect(sanitizeHtml(input)).toContain('src="https://example.com/img.jpg"')
    expect(sanitizeHtml(input)).toContain('alt="Photo"')
  })

  it('preserves headings', () => {
    const input = '<h1>Title</h1><h2>Subtitle</h2>'
    expect(sanitizeHtml(input)).toBe('<h1>Title</h1><h2>Subtitle</h2>')
  })

  it('preserves lists', () => {
    const input = '<ul><li>Item 1</li><li>Item 2</li></ul>'
    expect(sanitizeHtml(input)).toBe('<ul><li>Item 1</li><li>Item 2</li></ul>')
  })

  it('strips script tags', () => {
    const input = '<p>Hello</p><script>alert("xss")</script>'
    expect(sanitizeHtml(input)).toBe('<p>Hello</p>')
  })

  it('strips event handlers', () => {
    const input = '<img src="x" onerror="alert(1)">'
    const result = sanitizeHtml(input)
    expect(result).not.toContain('onerror')
  })

  it('strips iframe tags', () => {
    const input = '<iframe src="https://evil.com"></iframe><p>Safe</p>'
    expect(sanitizeHtml(input)).toBe('<p>Safe</p>')
  })

  it('strips javascript: protocol in links', () => {
    const input = '<a href="javascript:alert(1)">Click</a>'
    const result = sanitizeHtml(input)
    expect(result).not.toContain('javascript:')
  })

  it('handles plain text without tags', () => {
    const input = 'Just plain text'
    expect(sanitizeHtml(input)).toBe('Just plain text')
  })
})
