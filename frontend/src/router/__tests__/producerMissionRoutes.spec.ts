import { describe, expect, it } from 'vitest'
import type { RouteLocationNormalized } from 'vue-router'
import router from '../index'

type RedirectFn = (to: Pick<RouteLocationNormalized, 'params' | 'query' | 'hash'>) => unknown

function redirectOf(name: string): RedirectFn {
  const record = router.getRoutes().find((r) => r.name === name)
  expect(record, `route ${name}`).toBeDefined()
  expect(typeof record?.redirect).toBe('function')
  return record!.redirect as RedirectFn
}

describe('Lot C2 — routes mission Producteur', () => {
  it('/producer/missions/:id est la page détail (et non plus une redirection vers l’édition)', () => {
    const resolved = router.resolve('/producer/missions/mission-uuid-1')

    expect(resolved.name).toBe('producer-mission-detail')
    expect(resolved.params.id).toBe('mission-uuid-1')
  })

  it('la page détail n’est pas en keep-alive (le cache est indexé par nom de route)', () => {
    const resolved = router.resolve('/producer/missions/mission-uuid-1')

    expect(resolved.meta.keepAlive).toBeUndefined()
  })

  it('/producer/missions/publish reste la page de publication', () => {
    expect(router.resolve('/producer/missions/publish').name).toBe('publish-mission')
  })

  it('/producer/missions/:id/edit reste la page d’édition', () => {
    expect(router.resolve('/producer/missions/mission-uuid-1/edit').name).toBe('edit-mission')
  })

  it('/candidatures redirige vers l’onglet Candidatures en conservant toute la query (retour FedaPay, liens e-mail)', () => {
    const redirect = redirectOf('producer-mission-candidatures')

    expect(
      redirect({
        params: { id: 'mission-uuid-1' },
        query: { payment_return: 'candidature_escrow', candidature: 'cand-uuid-1', fedapay_status: 'approved' },
        hash: '#section',
      }),
    ).toEqual({
      name: 'producer-mission-detail',
      params: { id: 'mission-uuid-1' },
      query: {
        payment_return: 'candidature_escrow',
        candidature: 'cand-uuid-1',
        fedapay_status: 'approved',
        tab: 'candidatures',
      },
      hash: '#section',
    })
  })

  it('/attendance redirige vers l’onglet Présences en conservant la query', () => {
    const redirect = redirectOf('producer-mission-attendance')

    expect(redirect({ params: { id: 'mission-uuid-1' }, query: { pay: 'x' }, hash: '' })).toEqual({
      name: 'producer-mission-detail',
      params: { id: 'mission-uuid-1' },
      query: { pay: 'x', tab: 'presences' },
      hash: '',
    })
  })

  it('les anciennes URLs gardent leur nom de route (liens existants) et pointent vers le bon chemin', () => {
    expect(router.resolve({ name: 'producer-mission-candidatures', params: { id: 'm1' } }).path).toBe(
      '/producer/missions/m1/candidatures',
    )
    expect(router.resolve({ name: 'producer-mission-attendance', params: { id: 'm1' } }).path).toBe(
      '/producer/missions/m1/attendance',
    )
  })
})
