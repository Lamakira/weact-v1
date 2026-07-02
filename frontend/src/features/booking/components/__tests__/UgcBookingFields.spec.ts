import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import UgcBookingFields from '../UgcBookingFields.vue'
import type { UgcCompensationType } from '@/components/ugc'

const mountFields = (props: Record<string, unknown> = {}) =>
  mount(UgcBookingFields, {
    props: {
      compensationType: 'product' as UgcCompensationType,
      nomProduit: '',
      valeurProduit: undefined,
      nombreVideos: undefined,
      montantRemuneration: undefined,
      ...props,
    },
  })

describe('UgcBookingFields', () => {
  it('product mode shows the locked "2 vidéos" card and hides the remuneration field', () => {
    const wrapper = mountFields({ compensationType: 'product' })

    expect(wrapper.text()).toContain('2 vidéos')
    expect(wrapper.text()).toContain('1 Unboxing + 1 Avis')
    // No editable video count, no Face remuneration field in product mode
    expect(wrapper.find('#nombre_videos').exists()).toBe(false)
    expect(wrapper.find('#montant_remuneration').exists()).toBe(false)
  })

  it('hybrid mode exposes an editable video count and the Face remuneration field', () => {
    const wrapper = mountFields({ compensationType: 'hybrid' })

    expect(wrapper.find('#nombre_videos').exists()).toBe(true)
    expect(wrapper.find('#montant_remuneration').exists()).toBe(true)
    expect(wrapper.text()).toContain('Montant de la rémunération Face')
  })

  it('emits update:valeurProduit when the merchant value is typed', async () => {
    const wrapper = mountFields()

    await wrapper.find('#valeur_produit').setValue('45000')

    const emitted = wrapper.emitted('update:valeurProduit')
    expect(emitted).toBeTruthy()
    expect(emitted![0]).toEqual([45000])
  })

  it('emits update:compensationType when the toggle changes', async () => {
    const wrapper = mountFields({ compensationType: 'product' })

    await wrapper.find('[data-testid="compensation-hybrid"]').trigger('click')

    const emitted = wrapper.emitted('update:compensationType')
    expect(emitted).toBeTruthy()
    expect(emitted![0]).toEqual(['hybrid'])
  })
})
