import { describe, expect, it } from 'vitest'

/**
 * Lot F3 : vouvoiement partout. Aucun texte d'interface (composants, pages, vues,
 * constantes de contenu) ne doit tutoyer l'utilisateur.
 */
const sources = import.meta.glob('/src/**/*.{vue,ts}', { query: '?raw', import: 'default', eager: true }) as Record<
  string,
  string
>

const TUTOIEMENT =
  /(?<![\p{L}\p{N}_$.>-])(?:(?:tu|toi|ton|ta|tes)(?![\p{L}\p{N}_-])|t['’](?=\p{L}))|(?<![\p{L}\p{N}_$.>-])te(?= [a-zéèêàâîôû])/iu

const IMPERATIFS =
  /\b(?:Rejoins|Deviens|Confirme|Reprends|Envoie|Postule|Découvre|Inscris|Vérifie|Complète|Retrouve|Surveille|Apporte|Pense|Dépose|Uploade|Filme|Régularise|Consulte|Réessaie|Gère|Choisis|Termine|Fais|Indique|Commence|Crée)\b/u

// « ton » désigne ici la teinte (composants Régie) ; les codes pays ne sont pas du texte.
const EXCLUDED = [/__tests__/, /\.spec\.ts$/, /territoryOptions/, /components\/regie\//]

describe('registre : vouvoiement', () => {
  it("n'affiche aucun tutoiement dans les sources de l'interface", () => {
    const files = Object.entries(sources).filter(([path]) => !EXCLUDED.some((re) => re.test(path)))
    expect(files.length).toBeGreaterThan(100)

    const offenders: string[] = []
    for (const [path, content] of files) {
      content.split('\n').forEach((line, i) => {
        const trimmed = line.trim()
        if (trimmed.startsWith('//') || trimmed.startsWith('*') || trimmed.startsWith('/*') || trimmed.startsWith('<!--')) {
          return
        }
        if (TUTOIEMENT.test(line) || IMPERATIFS.test(line)) offenders.push(`${path}:${i + 1} ${trimmed.slice(0, 120)}`)
      })
    }

    expect(offenders).toEqual([])
  })
})
