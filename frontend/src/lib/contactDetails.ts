/**
 * Miroir client de la règle backend App\Rules\NoContactDetails (lot B12) : aucun numéro de
 * téléphone, e-mail, @pseudo ni lien dans un texte échangé avant paiement. Le backend reste
 * l'autorité ; ce miroir donne seulement un retour immédiat dans le formulaire.
 */
export const CONTACT_DETAILS_MESSAGE =
  'Pour votre sécurité, ne partagez pas de coordonnées (numéro de téléphone, e-mail, @pseudo ou lien vers un site ou un réseau social). Les échanges se font sur WeAct, la messagerie s\'ouvre après le paiement.'

const PHONE = /(?<!\d)(?:\+|00)?\d(?:[\s.\-()]{0,3}\d){7,}(?!\d)(?!\s*(?:f\s?cfa|xof|cfa|francs?)\b)/giu
const EMAIL = /[\p{L}\p{N}._%+-]+\s*(?:@|\(at\)|\[at\])\s*[\p{L}\p{N}-]+(?:\.[\p{L}\p{N}-]+)*\.\p{L}{2,}/iu
const HANDLE = /(?<![\p{L}\p{N}_.])@[\p{L}\p{N}_][\p{L}\p{N}_.]+/u
const URL_PATTERN = /(?:https?:\/\/|www\.)\S+/iu
const SOCIAL_DOMAIN =
  /(?<![\p{L}\p{N}-])(?:[\p{L}\p{N}-]+\.)*(?:instagram\.com|instagr\.am|facebook\.com|fb\.com|fb\.me|m\.me|tiktok\.com|snapchat\.com|twitter\.com|x\.com|linkedin\.com|youtube\.com|youtu\.be|wa\.me|whatsapp\.com|t\.me|telegram\.me|threads\.net)\b/iu
const DATE_LIKE = /^\d{1,2}[.-]\d{1,2}[.-]\d{4}$/

export function containsContactDetails(value: string): boolean {
  const text = value.normalize('NFKC')

  if ([EMAIL, URL_PATTERN, SOCIAL_DOMAIN, HANDLE].some((pattern) => pattern.test(text))) {
    return true
  }

  for (const match of text.matchAll(PHONE)) {
    if (!DATE_LIKE.test(match[0].trim())) return true
  }

  return false
}
