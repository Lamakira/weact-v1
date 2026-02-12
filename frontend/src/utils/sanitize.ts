import DOMPurify from 'dompurify'

/**
 * Sanitize HTML content using DOMPurify.
 * Allows safe tags for rich-text article content (headings, paragraphs, links, images, lists, etc.)
 * while stripping dangerous elements like scripts, iframes, and event handlers.
 */
export function sanitizeHtml(html: string): string {
  return DOMPurify.sanitize(html)
}
