import { beforeEach } from 'vitest'
import { resetAllSharedCachedResources } from '@/lib/createSharedCachedResource'

// Reka UI (Select, Combobox) s'appuie sur des API que happy-dom n'implémente pas.
const elementProto = Element.prototype as unknown as Record<string, unknown>
if (!elementProto.hasPointerCapture) elementProto.hasPointerCapture = () => false
if (!elementProto.setPointerCapture) elementProto.setPointerCapture = () => {}
if (!elementProto.releasePointerCapture) elementProto.releasePointerCapture = () => {}
if (!elementProto.scrollIntoView) elementProto.scrollIntoView = () => {}
if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = class {
    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
  } as unknown as typeof ResizeObserver
}

beforeEach(() => {
  resetAllSharedCachedResources()
})
