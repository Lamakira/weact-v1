import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import UgcFaceRewardSummary from '../UgcFaceRewardSummary.vue'

const normalize = (text: string): string => text.replace(/[  ]/g, ' ')

describe('UgcFaceRewardSummary', () => {
  it('produit seul : produit, valeur et « rien à payer », sans rémunération ni commission', () => {
    const wrapper = mount(UgcFaceRewardSummary, {
      props: { nomProduit: 'Sneakers Shade Fit', valeurProduit: 20000, typeCompensation: 'product' },
    })
    const text = normalize(wrapper.text())

    expect(text).toContain('Ce que vous recevez')
    expect(text).toContain('Sneakers Shade Fit')
    expect(text).toContain('20 000')
    expect(text).toContain('Vous n\'avez rien à payer.')
    expect(text).not.toContain('Rémunération nette')
    expect(text).not.toContain('Commission')
    expect(text).not.toContain('À payer')
  })

  it('hybride : ajoute la rémunération nette et la mention de versement', () => {
    const wrapper = mount(UgcFaceRewardSummary, {
      props: {
        nomProduit: 'Sneakers Shade Fit',
        valeurProduit: 20000,
        typeCompensation: 'hybrid',
        montantFaceRecoit: 27000,
      },
    })
    const text = normalize(wrapper.text())

    expect(text).toContain('Rémunération nette')
    expect(text).toContain('27 000')
    expect(text).toContain('versée après validation de vos vidéos')
    expect(text).toContain('Vous n\'avez rien à payer.')
    expect(text).not.toContain('Commission')
    expect(text).not.toContain('Frais de service')
  })
})
