import { afterEach, beforeEach } from 'vitest'
import { enableAutoUnmount } from '@vue/test-utils'
import { resetAllSharedCachedResources } from '@/lib/createSharedCachedResource'

// Démonte chaque composant monté à la fin de son test. Sans cela, les composants des tests
// précédents restent actifs : ils réagissent aux mêmes refs mockées et se redessinent à chaque
// test suivant (FaceBookingDetailPage passait de 60 ms à 3 s par test au fil du fichier), et
// leurs minuteries peuvent se déclencher après la fin du fichier.
// Les hooks afterEach s'exécutent en ordre inverse d'enregistrement : le vidage du DOM, enregistré
// d'abord, passe après le démontage. Une spec ne doit donc pas vider document.body dans son propre
// afterEach (il passerait avant le démontage, et Vue ne retrouverait plus ses nœuds).
afterEach(() => {
  document.body.replaceChildren()
})
enableAutoUnmount(afterEach)

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
