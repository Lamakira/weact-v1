import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CguView from '../CguView.vue'
import PolitiqueConfidentialiteView from '../PolitiqueConfidentialiteView.vue'

const mountView = (view: typeof CguView) =>
  mount(view, { global: { stubs: { RouterLink: true } } })

const flat = (s: string) => s.replace(/\s+/g, ' ').trim()

describe('CGU : autorisation d\'utilisation de l\'image', () => {
  const text = flat(mountView(CguView).text())

  it.each([
    '25 novembre 2026',
    'Signer l\'autorisation d\'utilisation de l\'image lorsqu\'elle est demandée, et faire signer un parent ou tuteur si la Face a moins de 18 ans',
    'N\'utiliser l\'image de la Face que dans les limites de l\'autorisation signée, et pas avant sa prise d\'effet',
    'Pour les bookings et missions concernés, le Producteur et la Face signent en ligne une autorisation d\'utilisation de l\'image, au moyen d\'un code reçu par e-mail. Elle fixe la finalité, les supports, le territoire et la durée. Elle prend effet après le paiement de la Face ou, pour un produit seul, à sa réception. WEACT fournit le modèle et archive le contrat signé, sans y être partie.',
  ])('contient : %s', (expected) => {
    expect(text).toContain(expected)
  })

  it('place les ajouts dans les bons articles', () => {
    const w = mountView(CguView)
    const items = w.findAll('li').map((l) => flat(l.text()))
    const faces = items.indexOf('Ne pas contourner la plateforme pour des transactions directes avec les Producteurs')
    expect(items[faces + 1]).toMatch(/^Signer l'autorisation/)
    const prod = items.indexOf('Garantir la sécurité et le bien-être des Faces lors des missions')
    expect(items[prod + 1]).toMatch(/^N'utiliser l'image/)
  })
})

describe('Politique de confidentialité : autorisation d\'utilisation de l\'image', () => {
  const w = mountView(PolitiqueConfidentialiteView)
  const text = flat(w.text())
  const items = w.findAll('li').map((l) => flat(l.text()))

  it.each([
    '25 novembre 2026',
    'Signature et archivage des autorisations d\'utilisation de l\'image',
    'Données de signature',
    'Nom du signataire, date, e-mail ayant reçu le code, navigateur utilisé',
    'Pour une Face mineure : nom, lien et e-mail du parent ou tuteur',
    'Les signataires d\'un contrat : le Producteur, la Face et, pour une Face mineure, son parent ou tuteur reçoivent une copie du contrat signé',
    'Autorisations d\'image et preuves de signature',
    'Durée de l\'autorisation + 5 ans',
    'Pour une Face de moins de 18 ans, l\'autorisation d\'utilisation de l\'image est signée par un parent ou tuteur.',
  ])('contient : %s', (expected) => {
    expect(text).toContain(expected)
  })

  it('place les ajouts au bon endroit', () => {
    const fin = items.indexOf('Signature et archivage des autorisations d\'utilisation de l\'image')
    expect(items[fin + 1]).toBe('Amélioration de la plateforme et de l\'expérience utilisateur')
    const autres = items.findIndex((i) => i.startsWith('Les autres utilisateurs :'))
    expect(items[autres + 1]).toMatch(/^Les signataires d'un contrat :/)
    const rows = w.findAll('tbody tr').map((r) => r.findAll('td').map((c) => flat(c.text())))
    const tx = rows.findIndex((r) => r[0] === 'Données de transaction')
    expect(rows[tx + 1]).toEqual(['Autorisations d\'image et preuves de signature', 'Durée de l\'autorisation + 5 ans'])
  })
})
