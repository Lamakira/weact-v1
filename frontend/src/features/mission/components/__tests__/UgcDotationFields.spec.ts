import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import UgcDotationFields from '../UgcDotationFields.vue'

/**
 * Composant présentationnel pur (story 1.4 AC1/AC2/AC4) :
 *  - product → encart « 2 vidéos » verrouillé, pas de champ rémunération
 *  - hybrid → #nombre_videos éditable + champ « Montant de la rémunération Face »
 *  - encart livrables 7j/14j toujours présent
 *  - émet les updates v-model
 */
const mountFields = (props: Record<string, unknown> = {}) =>
  mount(UgcDotationFields, {
    props: {
      compensationType: 'product',
      nomProduit: '',
      valeurProduit: undefined,
      nombreVideos: undefined,
      montantRemuneration: undefined,
      ...props,
    },
  })

describe('UgcDotationFields', () => {
  it('1. product : encart "2 vidéos" verrouillé + pas de champ rémunération', () => {
    const wrapper = mountFields({ compensationType: 'product' })

    expect(wrapper.text()).toContain('2 vidéos')
    expect(wrapper.text()).toContain('1 Unboxing + 1 Avis')
    expect(wrapper.find('#nombre_videos').exists()).toBe(false)
    expect(wrapper.find('#montant_remuneration').exists()).toBe(false)
  })

  it('2. hybrid : #nombre_videos éditable + champ "Montant de la rémunération Face"', () => {
    const wrapper = mountFields({ compensationType: 'hybrid' })

    expect(wrapper.find('#nombre_videos').exists()).toBe(true)
    expect(wrapper.find('#montant_remuneration').exists()).toBe(true)
    expect(wrapper.text()).toContain('Montant de la rémunération Face')
  })

  it('3. encart livrables présent et décrit les chronos 7j / 14j', () => {
    const wrapper = mountFields()

    const deliverables = wrapper.find('[data-testid="ugc-deliverables"]')
    expect(deliverables.exists()).toBe(true)
    expect(deliverables.text()).toContain('Unboxing')
    expect(deliverables.text()).toContain('7 jours')
    expect(deliverables.text()).toContain('Avis')
    expect(deliverables.text()).toContain('14 jours')
  })

  it('4. saisie #valeur_produit → émet "update:valeurProduit"', async () => {
    const wrapper = mountFields()

    await wrapper.find('#valeur_produit').setValue('25000')

    expect(wrapper.emitted('update:valeurProduit')).toBeTruthy()
    expect(wrapper.emitted('update:valeurProduit')![0]).toEqual([25000])
  })

  it('5. clic "Produit + argent" → émet "update:compensationType" avec hybrid', async () => {
    const wrapper = mountFields({ compensationType: 'product' })

    await wrapper.find('[data-testid="compensation-hybrid"]').trigger('click')

    expect(wrapper.emitted('update:compensationType')).toBeTruthy()
    expect(wrapper.emitted('update:compensationType')![0]).toEqual(['hybrid'])
  })
})
