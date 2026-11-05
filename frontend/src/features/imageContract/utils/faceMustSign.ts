import type { ImageContract, MissionImageTerms } from '../types'

/**
 * Une Face doit signer le contrat d'image d'une mission quand la mission a des conditions,
 * que sa candidature est retenue (`accepted`) et qu'aucun contrat n'existe encore pour elle.
 */
export function faceMustSignMission(
  mission: { image_terms?: MissionImageTerms | null } | null | undefined,
  candidature: { status: string; image_contract?: ImageContract | null } | null | undefined,
): boolean {
  if (!mission?.image_terms || !candidature) return false
  return candidature.status === 'accepted' && !candidature.image_contract
}
