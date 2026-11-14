import type { FaceVideoView } from '../types'

export interface LabelledPortfolioVideo {
  video: FaceVideoView
  label: string
  /** Rang (à partir de 1) dans son type */
  rank: number
}

/**
 * Vidéos de portfolio exploitables : sans URL ignorées, puis acting par
 * position croissante, ensuite ugc par position croissante.
 */
export function orderPortfolioVideos(
  videos: FaceVideoView[] | null | undefined,
): FaceVideoView[] {
  const usable = (videos ?? []).filter((v) => !!v.video_url)
  const byPosition = (a: FaceVideoView, b: FaceVideoView): number => a.position - b.position
  return [
    ...usable.filter((v) => v.type === 'acting').sort(byPosition),
    ...usable.filter((v) => v.type === 'ugc').sort(byPosition),
  ]
}

/**
 * Libellé d'une vidéo selon son rang (à partir de 1) dans son type.
 */
export function portfolioVideoLabel(video: FaceVideoView, rankInType: number): string {
  const base = video.type === 'acting' ? "Vidéo d'acting" : 'Vidéo UGC'
  return rankInType > 1 ? `${base} ${rankInType}` : base
}

/**
 * Liste ordonnée avec libellés (les rangs sont calculés une seule fois ici).
 */
export function labelledPortfolioVideos(
  videos: FaceVideoView[] | null | undefined,
): LabelledPortfolioVideo[] {
  const ranks: Record<string, number> = {}
  return orderPortfolioVideos(videos).map((video) => {
    const rank = (ranks[video.type] ?? 0) + 1
    ranks[video.type] = rank
    return { video, label: portfolioVideoLabel(video, rank), rank }
  })
}
