import { flushPromises, type DOMWrapper, type VueWrapper } from '@vue/test-utils'
import { SimpleSelect } from '@/components/ui/select'
import { SearchableSelect } from '@/components/ui/combobox'

/**
 * Helpers pour piloter les listes shadcn-vue (Reka UI) dans les specs.
 * Les listes s'ouvrent dans un portail sur document.body : on les interroge là.
 */
type Trigger = DOMWrapper<Element>

export function getOptions(): HTMLElement[] {
  return Array.from(document.body.querySelectorAll<HTMLElement>('[role="option"]'))
}

export function getOptionLabels(): string[] {
  return getOptions().map((option) => option.textContent?.trim() ?? '')
}

/** Ouvre une liste (Select : pointerdown ; Combobox : clic). */
export async function openSelect(trigger: Trigger): Promise<void> {
  const el = trigger.element as HTMLElement
  if (el.getAttribute('data-slot') === 'combobox-trigger') {
    await trigger.trigger('click')
  } else {
    await trigger.trigger('pointerdown', { button: 0, ctrlKey: false, pointerType: 'mouse' })
    // Relâchement du bouton : Reka ignore ce premier pointerup (sinon l'option sous le curseur serait choisie).
    el.dispatchEvent(new Event('pointerup', { bubbles: true, cancelable: true }))
  }
  await flushPromises()
}

/** Clique l'option dont le libellé correspond exactement. */
export async function clickOption(label: string): Promise<void> {
  const option = getOptions().find((o) => o.textContent?.trim() === label)
  if (!option) {
    throw new Error(`Option « ${label} » introuvable (options : ${getOptionLabels().join(' | ')})`)
  }
  // Select (Reka) sélectionne au pointerup, Combobox au clic.
  option.dispatchEvent(new Event('pointerup', { bubbles: true, cancelable: true }))
  option.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }))
  await flushPromises()
}

/** Ouvre la liste puis choisit l'option par son libellé. */
export async function chooseOption(trigger: Trigger, label: string): Promise<void> {
  await openSelect(trigger)
  await clickOption(label)
}

/** Texte affiché par un déclencheur. */
export function triggerText(wrapper: VueWrapper | DOMWrapper<Element>): string {
  return wrapper.text().trim()
}

/** Ferme les listes encore ouvertes entre deux tests. */
export function cleanupPortals(): void {
  document.body.replaceChildren()
}

/**
 * Variante sans portail pour les specs qui stubbent Teleport (le contenu Reka ne s'y monte pas) :
 * retrouve le SimpleSelect/SearchableSelect dont le déclencheur porte `#id` ou
 * `[data-testid="…"]` et lui fait émettre la valeur, comme le ferait un clic sur une option.
 */
function findSelectComponent(wrapper: VueWrapper, selector: string) {
  const idMatch = selector.match(/^#(.+)$/)
  const testIdMatch = selector.match(/^\[data-testid="(.+)"\]$/)
  if (!idMatch && !testIdMatch) throw new Error(`Sélecteur non pris en charge : ${selector}`)
  const candidates = [
    ...wrapper.findAllComponents(SimpleSelect),
    ...wrapper.findAllComponents(SearchableSelect),
  ]
  const found = candidates.find((c) => {
    const attrs = c.vm.$attrs as Record<string, unknown>
    return idMatch ? attrs.id === idMatch[1] : attrs['data-testid'] === testIdMatch![1]
  })
  if (!found) throw new Error(`Aucune liste shadcn ne correspond à ${selector}`)
  return found
}

export async function setSelectValue(wrapper: VueWrapper, selector: string, value: string): Promise<void> {
  findSelectComponent(wrapper, selector).vm.$emit('update:modelValue', value)
  await flushPromises()
}

/** Libellés proposés par la liste (option « vide » comprise, en tête). */
export function getSelectLabels(wrapper: VueWrapper, selector: string): string[] {
  const props = findSelectComponent(wrapper, selector).props() as {
    options: ReadonlyArray<{ label: string }>
    emptyLabel?: string
  }
  return [...(props.emptyLabel ? [props.emptyLabel] : []), ...props.options.map((o) => o.label)]
}

/** Valeur courante transmise à la liste. */
export function getSelectValue(wrapper: VueWrapper, selector: string): string {
  return String((findSelectComponent(wrapper, selector).props() as { modelValue?: unknown }).modelValue ?? '')
}

/** Options proposées par la liste (option « vide » comprise, en tête, avec la valeur ''). */
export function getSelectOptions(wrapper: VueWrapper, selector: string): Array<{ value: string; label: string }> {
  const props = findSelectComponent(wrapper, selector).props() as {
    options: ReadonlyArray<{ value: string | number; label: string }>
    emptyLabel?: string
  }
  return [
    ...(props.emptyLabel ? [{ value: '', label: props.emptyLabel }] : []),
    ...props.options.map((o) => ({ value: String(o.value), label: o.label })),
  ]
}
