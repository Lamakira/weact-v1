import { onScopeDispose, ref, type Ref } from 'vue'

/**
 * Reactive `window.matchMedia`. Defaults to `false` when matchMedia is unavailable
 * (SSR, very old browsers), i.e. the mobile layout.
 */
export function useMediaQuery(query: string): Ref<boolean> {
  const matches = ref(false)
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return matches

  const mql = window.matchMedia(query)
  matches.value = mql.matches
  const onChange = (event: MediaQueryListEvent): void => {
    matches.value = event.matches
  }
  mql.addEventListener('change', onChange)
  onScopeDispose(() => mql.removeEventListener('change', onChange))

  return matches
}

/** Tailwind `md` breakpoint and up: tables are usable. */
export function useIsDesktop(): Ref<boolean> {
  return useMediaQuery('(min-width: 768px)')
}
