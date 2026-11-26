import { describe, it, expect } from 'vitest'
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs'
import { join, resolve } from 'node:path'
import { contrepartieLines, netLine, type Contrepartie } from '../contrepartie'
import { formatMoney } from '@/utils/money'

interface SharedCase {
  name: string
  contrepartie: Contrepartie
  lines: string[]
}

// Fixture partagée avec le backend : garantit que l'application et le PDF disent la même chose.
// Vitest se lance depuis frontend/ ; le chemin est relatif à ce dossier.
const FIXTURE = resolve(process.cwd(), '../backend/tests/Fixtures/image-contract-contrepartie.json')

describe('contrepartieLines : fixture partagée avec le PDF', () => {
  it('lit la fixture partagée', () => {
    expect(existsSync(FIXTURE), `fixture introuvable : ${FIXTURE}`).toBe(true)
  })

  const cases: SharedCase[] = existsSync(FIXTURE) ? JSON.parse(readFileSync(FIXTURE, 'utf8')) : []

  it('contient au moins un cas', () => {
    expect(cases.length).toBeGreaterThan(0)
  })

  it.each(cases.map((c) => [c.name, c] as const))('%s', (_name, c) => {
    expect(contrepartieLines(c.contrepartie)).toEqual(c.lines)
  })
})

describe('contrepartieLines', () => {
  it('cash : cachet brut avant commission', () => {
    expect(contrepartieLines({ kind: 'cash', cachet: 150000 })).toEqual([
      `Cachet : ${formatMoney(150000)}, avant commission WeAct`,
    ])
  })

  it('cash sans cachet connu : renvoie au tarif de la Face', () => {
    expect(contrepartieLines({ kind: 'cash', cachet: null })).toEqual([
      'Cachet selon le tarif de la Face, avant commission WeAct',
    ])
    expect(contrepartieLines({ kind: 'cash' })).toEqual([
      'Cachet selon le tarif de la Face, avant commission WeAct',
    ])
  })

  it('variante parFace', () => {
    expect(contrepartieLines({ kind: 'cash', cachet: 150000 }, { parFace: true })).toEqual([
      `Cachet : ${formatMoney(150000)} par Face, avant commission WeAct`,
    ])
    const hybride = contrepartieLines(
      { kind: 'ugc_hybrid', cachet: 20000, nom_produit: 'Crème', valeur_produit: 15000 },
      { parFace: true },
    )
    expect(hybride).toEqual([
      `Produit remis : Crème (valeur ${formatMoney(15000)})`,
      `Cachet : ${formatMoney(20000)} par Face, avant commission WeAct`,
    ])
  })

  it('hybride sans cachet ou cachet 0 : seulement la ligne produit', () => {
    const produit = [`Produit remis : Crème (valeur ${formatMoney(15000)})`]
    expect(contrepartieLines({ kind: 'ugc_hybrid', cachet: 0, nom_produit: 'Crème', valeur_produit: 15000 })).toEqual(produit)
    expect(contrepartieLines({ kind: 'ugc_hybrid', cachet: null, nom_produit: 'Crème', valeur_produit: 15000 })).toEqual(produit)
  })

  it('produit seul, nom vide : « Produit offert »', () => {
    expect(contrepartieLines({ kind: 'ugc_product', nom_produit: '', valeur_produit: 8000 })).toEqual([
      `Produit remis : Produit offert (valeur ${formatMoney(8000)})`,
    ])
  })
})

describe('netLine', () => {
  it('affiche le net de la Face', () => {
    expect(netLine(135000)).toBe(`Vous recevez ${formatMoney(135000)}`)
  })

  it('null si le net est null, undefined ou 0', () => {
    expect(netLine(null)).toBeNull()
    expect(netLine(undefined)).toBeNull()
    expect(netLine(0)).toBeNull()
  })
})

describe('garde-fou : les textes de contrepartie viennent de utils/contrepartie.ts', () => {
  const SRC = resolve(process.cwd(), 'src')
  const FORBIDDEN = ['avant commission WeAct', 'selon le tarif de la Face', 'Produit remis', 'Vous recevez ${']

  function vueFiles(dir: string): string[] {
    return readdirSync(dir).flatMap((entry) => {
      const full = join(dir, entry)
      if (statSync(full).isDirectory()) return entry === '__tests__' ? [] : vueFiles(full)
      return full.endsWith('.vue') ? [full] : []
    })
  }

  it('aucun composant .vue n\'écrit ces phrases à la main', () => {
    const files = vueFiles(SRC)
    expect(files.length).toBeGreaterThan(50)
    const offenders = files.flatMap((file) => {
      const content = readFileSync(file, 'utf8')
      return FORBIDDEN.filter((text) => content.includes(text)).map((text) => `${file} : ${text}`)
    })
    expect(offenders).toEqual([])
  })
})
