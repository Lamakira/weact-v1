/**
 * Get XSRF token from cookies
 * Required for cross-origin requests where Axios automatic XSRF handling doesn't work
 */
export function getXsrfTokenFromCookie(): string | null {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  if (match && match[1]) {
    return decodeURIComponent(match[1])
  }
  return null
}
