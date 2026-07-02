import type { FaceSubscriptionTier, TierCapabilities } from './types'

interface TierPresentation {
  name: string
  tagline: string
  badge: string | null // shown as the teal "Populaire" pill; Élite uses the Crown icon
  miseEnAvant: string
}

export const TIER_PRESENTATION: Record<FaceSubscriptionTier, TierPresentation> = {
  free: {
    name: 'Découverte',
    tagline: 'Pour tester la plateforme',
    badge: null,
    miseEnAvant: 'Standard',
  },
  starter: {
    name: 'Starter',
    tagline: 'Décroche tes premiers contrats UGC',
    badge: null,
    miseEnAvant: 'Boostée',
  },
  pro: {
    name: 'Pro',
    tagline: 'Acting + UGC, le combo sérieux',
    badge: 'Populaire',
    miseEnAvant: 'Premium',
  },
  elite: {
    name: 'Élite',
    tagline: "L'offre VIP des tops profils",
    badge: 'VIP',
    miseEnAvant: 'Prioritaire',
  },
}

export interface TierFeatureLine {
  text: string
  highlight: boolean
}

/**
 * Build a tier card's "Inclus" feature list from the live capabilities matrix
 * (decision #4 — config-driven, no static drift). `tier` only supplies the
 * mise-en-avant label.
 */
export function buildTierFeatureLines(
  tier: FaceSubscriptionTier,
  capabilities: TierCapabilities,
): TierFeatureLine[] {
  const lines: TierFeatureLine[] = []
  const plural = (n: number): string => (n > 1 ? 's' : '')

  lines.push({
    text: `${capabilities.max_album_photos} photo${plural(capabilities.max_album_photos)} dans la galerie`,
    highlight: false,
  })

  if (capabilities.max_presentation_videos > 0) {
    lines.push({ text: '1 vidéo de présentation', highlight: false })
  }
  if (capabilities.max_acting_videos > 0) {
    lines.push({
      text: `${capabilities.max_acting_videos} vidéo${plural(capabilities.max_acting_videos)} Acting`,
      highlight: false,
    })
  }
  if (capabilities.max_ugc_videos > 0) {
    lines.push({ text: '1 vidéo modèle UGC', highlight: false })
  }
  if (capabilities.ugc_access) {
    lines.push({ text: 'Accès complet au module UGC', highlight: false })
  }

  lines.push({
    text: `Mise en avant ${TIER_PRESENTATION[tier].miseEnAvant}`,
    highlight: false,
  })

  if (capabilities.commission_rate < 0.1) {
    const rate = Math.round(capabilities.commission_rate * 100)
    lines.push({ text: `Commission réduite à ${rate}% (au lieu de 10%)`, highlight: true })
  }
  if (capabilities.has_elite_badge) {
    lines.push({ text: 'Badge VIP / Élite sur le profil', highlight: true })
  }

  return lines
}
