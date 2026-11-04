/**
 * Autorisation d'utilisation de l'image : types (incrément 1, bookings).
 */

export type ImageSupport = 'social' | 'web' | 'tv' | 'print' | 'cinema' | 'all'
export type ImageTerritoire = 'benin' | 'west_africa' | 'world'
export type ImageDuree = 1 | 3 | 5 | 10

/** Conditions particulières saisies par le Producteur. */
export interface ImageTerms {
  finalite: string
  supports: ImageSupport[]
  territoire: ImageTerritoire
  duree_annees: ImageDuree
}

/** Signature électronique : nom saisi + code reçu par e-mail. */
export interface ImageSignature {
  name: string
  code: string
}

export type ImageContractStatus = 'awaiting_face' | 'signed' | 'effective' | 'void'

export interface ImageContractOption {
  value: string
  label: string
}

/** Contrat tel que l'expose `BookingResource.image_contract` (jamais d'e-mail complet). */
export interface ImageContract {
  id: string
  status: ImageContractStatus
  finalite: string
  supports: ImageContractOption[]
  territoire: ImageContractOption
  duree_annees: number
  producer_signed_at: string | null
  producer_signed_name: string | null
  face_signed_at: string | null
  face_signed_name: string | null
  guardian_name: string | null
  effective_at: string | null
  pdf_ready: boolean
  /** Seulement pour la Face : elle a moins de 18 ans, un parent doit signer. */
  requires_guardian?: boolean
}

export interface ContractArticle {
  title: string
  body: string
  bullets?: string[]
}

export interface ContractTemplate {
  version: string
  articles: ContractArticle[]
  supports?: unknown
  territoires?: unknown
  durees?: unknown
}

export type GuardianRelation = 'mere' | 'pere' | 'tuteur'

export interface GuardianInfo {
  guardian_name: string
  guardian_relation: GuardianRelation
  guardian_email: string
}

/** Corps de `POST /image-contract/code`. */
export type SignatureCodePayload =
  | ({ purpose: 'booking_request' })
  | ({ purpose: 'booking_accept'; booking_id: string } & Partial<GuardianInfo>)

export interface SignatureCodeResponse {
  data: {
    sent_to: string
    expires_at: string
    retry_after: number
  }
}

/** Résumé affiché dans la fenêtre de signature (conditions particulières). */
export interface ContractSummary {
  /** Projet concerné (type de contenu, produit...). */
  projet: string
  finalite: string
  /** Libellés des supports. */
  supports: string[]
  territoire: string
  dureeAnnees: number
  /** Contrepartie en clair (cachet net, produit...). */
  contrepartie: string
}

/** Résultat de la soumission finale (création du booking ou acceptation). */
export type SignatureSubmitOutcome =
  | { ok: true }
  | { ok: false; code?: string | null; message?: string | null }
