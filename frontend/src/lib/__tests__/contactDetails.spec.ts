import { describe, expect, it } from 'vitest'
import { containsContactDetails } from '../contactDetails'

describe('containsContactDetails (miroir de NoContactDetails)', () => {
  it.each([
    'Appelez-moi au 97 12 34 56',
    'Mon numéro : +229 01 97 12 34 56',
    '９７１２３４５６',
    'Écrivez-moi à awa.diallo@gmail.com',
    'awa＠gmail.com',
    'Suivez-moi sur @awa.officiel',
    'https://monportfolio.bj/awa',
    'www.awa-diallo.com',
    'instagram.com/awa.officiel',
    'wa.me/22997123456',
  ])('rejette « %s »', (text) => {
    expect(containsContactDetails(text)).toBe(true)
  })

  it.each([
    'Publicité de 30 secondes, deux tenues à prévoir.',
    'Tournage le 12/10/2026 à Cotonou',
    'Tournage le 12.10.2026',
    'Budget de 15 000 000 FCFA',
    'Rendez-vous à 8h30',
    'Prix @ négocier',
    'Merci.Bonne journée',
  ])('accepte « %s »', (text) => {
    expect(containsContactDetails(text)).toBe(false)
  })
})
