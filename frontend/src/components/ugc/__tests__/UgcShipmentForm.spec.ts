import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import UgcShipmentForm from '../UgcShipmentForm.vue'

function mountForm(props: { isSubmitting?: boolean } = {}) {
  return mount(UgcShipmentForm, { props })
}

describe('UgcShipmentForm', () => {
  it('selecting a carrier chip sets the transporteur', async () => {
    const wrapper = mountForm()

    await wrapper.find('[data-testid="carrier-chip-DHL"]').trigger('click')

    expect(wrapper.find('[data-testid="carrier-chip-DHL"]').classes()).toContain('bg-weact')
    // Un chip nommé ne révèle pas le champ texte libre.
    expect(wrapper.find('[data-testid="carrier-free-text"]').exists()).toBe(false)
  })

  it('selecting Autre reveals the free-text carrier field', async () => {
    const wrapper = mountForm()

    await wrapper.find('[data-testid="carrier-chip-Autre"]').trigger('click')

    expect(wrapper.find('[data-testid="carrier-free-text"]').exists()).toBe(true)
  })

  it('emits the payload with the chip carrier on submit', async () => {
    const wrapper = mountForm()

    await wrapper.find('[data-testid="carrier-chip-Gozem"]').trigger('click')
    await wrapper.find('[data-testid="tracking-number-input"]').setValue('GZM-COT-882194')
    await wrapper.find('[data-testid="confirm-shipment-btn"]').trigger('click')

    expect(wrapper.emitted('submit')).toHaveLength(1)
    expect(wrapper.emitted('submit')![0]![0]).toEqual({
      transporteur: 'Gozem',
      numero_suivi: 'GZM-COT-882194',
    })
  })

  it('omits an empty note_envoi from the emitted payload', async () => {
    const wrapper = mountForm()

    await wrapper.find('[data-testid="carrier-chip-DHL"]').trigger('click')
    await wrapper.find('[data-testid="tracking-number-input"]').setValue('TRK-001')
    await wrapper.find('[data-testid="confirm-shipment-btn"]').trigger('click')

    const payload = wrapper.emitted('submit')![0]![0] as Record<string, unknown>
    expect('note_envoi' in payload).toBe(false)
  })

  it('shows a field error when Autre is selected with an empty carrier', async () => {
    const wrapper = mountForm()

    await wrapper.find('[data-testid="carrier-chip-Autre"]').trigger('click')
    await wrapper.find('[data-testid="tracking-number-input"]').setValue('TRK-001')
    await wrapper.find('[data-testid="confirm-shipment-btn"]').trigger('click')

    expect(wrapper.emitted('submit')).toBeUndefined()
    expect(wrapper.text()).toContain('Le transporteur est obligatoire')
  })

  it('shows a field error when numero_suivi is empty', async () => {
    const wrapper = mountForm()

    await wrapper.find('[data-testid="carrier-chip-DHL"]').trigger('click')
    await wrapper.find('[data-testid="confirm-shipment-btn"]').trigger('click')

    expect(wrapper.emitted('submit')).toBeUndefined()
    expect(wrapper.text()).toContain('Le numéro de suivi est obligatoire')
  })

  it('shows a field error when the note exceeds 500 characters', async () => {
    const wrapper = mountForm()

    await wrapper.find('[data-testid="carrier-chip-DHL"]').trigger('click')
    await wrapper.find('[data-testid="tracking-number-input"]').setValue('TRK-001')
    // happy-dom n'applique pas maxlength sur un setValue programmatique :
    // la branche d'erreur du schéma est atteignable (une note > 500 ne part jamais).
    await wrapper.find('[data-testid="note-envoi-input"]').setValue('z'.repeat(501))
    await wrapper.find('[data-testid="confirm-shipment-btn"]').trigger('click')

    expect(wrapper.emitted('submit')).toBeUndefined()
    expect(wrapper.text()).toContain('La note ne peut pas dépasser 500 caractères')
  })

  it('disables the submit button while isSubmitting', () => {
    const wrapper = mountForm({ isSubmitting: true })

    const button = wrapper.find('[data-testid="confirm-shipment-btn"]')
    expect(button.attributes('disabled')).toBeDefined()
  })

  it('renders the chrono reminder hint', () => {
    const wrapper = mountForm()

    expect(wrapper.find('[data-testid="chrono-reminder-hint"]').text()).toContain(
      'Le chrono démarrera quand la Face cliquera sur « Produit reçu »',
    )
  })
})
