/**
 * Accepts only a same-origin absolute path. Rejects non-strings (e.g. the
 * string[] of a duplicated ?redirect=), protocol-relative URLs (`//evil.com`),
 * absolute URLs and anything with a backslash (`/\evil.com`, which browsers
 * normalise to `//evil.com`).
 */
export function safeRedirect(value: unknown): string | null {
  if (typeof value !== 'string') return null
  if (!value.startsWith('/') || value.startsWith('//')) return null
  if (value.includes('\\')) return null

  return value
}
