import type { CreateMissionData, Mission } from '../types'

/**
 * Lot C9 : pré-remplissage du formulaire de publication à partir d'une mission clôturée sans Face
 * (`/producer/missions/publish?from={uuid}`). On ne reprend que le brief : titre, description,
 * type, budget, nombre de Faces, profil/genre, ville/lieu et dotation UGC. Les dates restent
 * VIDES (le Producteur les ajuste) ; paiement, candidatures, statuts et photos produit ne sont
 * jamais copiés — la nouvelle mission repart de zéro.
 */
export function buildRepublishValues(mission: Mission): Partial<CreateMissionData> {
  const values: Partial<CreateMissionData> = {
    titre: mission.titre,
    description: mission.description,
    profil_recherche: mission.profil_recherche,
    nombre_faces_voulu: mission.nombre_faces_voulu,
    type_mission: mission.type_mission,
    type_mission_autre: mission.type_mission_autre ?? undefined,
    genre_voulu: mission.genre_voulu,
    date_tournage: '',
    date_limite_candidature: '',
  }

  if ((mission.type_mission as string) === 'ugc') {
    // Mission UGC : le budget est dérivé par le serveur, les champs de tournage sont masqués.
    values.type_compensation = (mission.type_compensation as CreateMissionData['type_compensation']) ?? undefined
    values.nom_produit = mission.nom_produit ?? undefined
    values.valeur_produit = mission.valeur_produit ?? undefined
    values.nombre_videos = mission.nombre_videos ?? undefined
    values.montant_remuneration = mission.montant_remuneration ?? undefined
    return values
  }

  values.budget = mission.budget
  values.lieu = mission.lieu
  values.duree = mission.duree
  return values
}
