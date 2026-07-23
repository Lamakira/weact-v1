/**
 * Garde de conception pour `meta.preserveScrollOnQueryChange`.
 *
 * DashboardLayout remet le <main> (unique scroller des dashboards) en haut à
 * chaque changement de route.fullPath. Une route qui porte de l'ÉTAT DE VUE
 * dans sa query — les onglets du profil Face (?tab=&section=) — doit s'y
 * soustraire : sinon chaque clic d'onglet renvoie l'utilisateur au-dessus du
 * pli sur la mise en page empilée du mobile.
 *
 * L'inverse est tout aussi important : les listes paginées du dashboard
 * mettent ?page= dans leur query et DOIVENT repartir du haut à chaque page.
 * Leur poser le flag serait une régression silencieuse — d'où l'assertion
 * négative ci-dessous.
 */
import { describe, it, expect } from 'vitest'
import router from '@/router'

function metaOf(name: string): Record<string, unknown> {
  const record = router.getRoutes().find((r) => r.name === name)
  expect(record, `route ${name} introuvable`).toBeDefined()
  return record!.meta as Record<string, unknown>
}

describe('meta.preserveScrollOnQueryChange', () => {
  it('est posé sur le profil Face, dont les onglets vivent dans la query', () => {
    expect(metaOf('face-profile').preserveScrollOnQueryChange).toBe(true)
  })

  it("n'est PAS posé sur les listes paginées du dashboard (?page= doit repartir du haut)", () => {
    for (const name of [
      'face-bookings',
      'face-candidatures',
      'producer-bookings',
      'producer-missions',
    ]) {
      expect(
        metaOf(name).preserveScrollOnQueryChange,
        `${name} ne doit pas conserver le scroll : sa query porte la pagination`,
      ).toBeUndefined()
    }
  })
})
