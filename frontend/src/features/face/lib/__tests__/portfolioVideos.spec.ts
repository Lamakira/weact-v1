import { describe, it, expect } from 'vitest'
import {
  orderPortfolioVideos,
  portfolioVideoLabel,
  labelledPortfolioVideos,
} from '../portfolioVideos'
import type { FaceVideoView } from '../../types'

function v(id: string, type: 'acting' | 'ugc', position: number, url: string | null = `https://x/${id}.mp4`): FaceVideoView {
  return { id, type, video_url: url, thumbnail_url: null, position }
}

describe('portfolioVideos', () => {
  it('ordonne acting par position puis ugc par position', () => {
    const result = orderPortfolioVideos([v('u2', 'ugc', 2), v('a2', 'acting', 2), v('u1', 'ugc', 1), v('a1', 'acting', 1)])
    expect(result.map((x) => x.id)).toEqual(['a1', 'a2', 'u1', 'u2'])
  })

  it('ignore les éléments sans video_url', () => {
    const result = orderPortfolioVideos([v('a1', 'acting', 1, null), v('a2', 'acting', 2)])
    expect(result.map((x) => x.id)).toEqual(['a2'])
  })

  it('gère null et undefined', () => {
    expect(orderPortfolioVideos(null)).toEqual([])
    expect(orderPortfolioVideos(undefined)).toEqual([])
  })

  it('génère les libellés selon le rang', () => {
    expect(portfolioVideoLabel(v('a', 'acting', 1), 1)).toBe("Vidéo d'acting")
    expect(portfolioVideoLabel(v('a', 'acting', 2), 2)).toBe("Vidéo d'acting 2")
    expect(portfolioVideoLabel(v('u', 'ugc', 1), 1)).toBe('Vidéo UGC')
    expect(portfolioVideoLabel(v('u', 'ugc', 3), 3)).toBe('Vidéo UGC 3')
  })

  it('renvoie la liste ordonnée avec libellés calculés par type', () => {
    const result = labelledPortfolioVideos([v('u1', 'ugc', 1), v('a2', 'acting', 5), v('a1', 'acting', 1)])
    expect(result.map((x) => x.label)).toEqual(["Vidéo d'acting", "Vidéo d'acting 2", 'Vidéo UGC'])
  })
})
