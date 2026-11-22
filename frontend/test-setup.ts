import { afterEach, beforeEach } from 'vitest'
import axios from 'axios'
import { enableAutoUnmount } from '@vue/test-utils'
import { resetAllSharedCachedResources } from '@/lib/createSharedCachedResource'

// Aucun test ne doit envoyer de vraie requête HTTP : une requête oubliée part vers
// 127.0.0.1:8000, échoue après la fin du test, et son message d'erreur arrive pendant la
// fermeture du worker (EnvironmentTeardownError, CI rouge au hasard). Les clients axios
// créés après ce fichier héritent de cet adaptateur : la requête est refusée tout de suite
// et le test qui l'a lancée échoue avec son adresse.
const realHttpCalls: string[] = []
axios.defaults.adapter = (config) => {
  realHttpCalls.push(`${(config.method ?? 'get').toUpperCase()} ${config.url ?? ''}`)
  return Promise.reject(new Error(`Requête HTTP réelle dans un test : ${config.url ?? ''}`))
}
afterEach(() => {
  if (realHttpCalls.length === 0) return
  const calls = realHttpCalls.splice(0).join(', ')
  throw new Error(`Requête HTTP réelle dans ce test (mockez le service) : ${calls}`)
})

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
