import { z } from 'zod'

/** True only when the value is a real, finite number (not '', NaN or undefined). */
const isFilledNumber = (v: unknown): v is number => typeof v === 'number' && Number.isFinite(v)

/**
 * Booking form validation schema
 * Mirrors backend validation rules in CreateBookingRequest
 */
export const bookingSchema = z
  .object({
    face_id: z.string({ message: 'La Face est obligatoire' }).uuid(),

    // Optionnels au niveau objet ; requis conditionnellement (non-UGC) via refines plus bas.
    // Une dotation UGC n'a ni date ni lieu de tournage (la Face filme chez elle).
    date_debut: z.string().optional().or(z.literal('')),

    date_fin: z.string().optional().or(z.literal('')),

    duree_heures: z
      .number({ message: 'La durée est obligatoire' })
      .int('La durée doit être un nombre entier')
      .min(4, 'La durée minimale est de 4 heures')
      .max(720, 'La durée ne peut pas dépasser 720 heures'),

    type_contenu: z
      .string({ message: 'Le type de contenu est obligatoire' })
      .min(1, 'Le type de contenu est obligatoire')
      .max(100, 'Le type de contenu ne peut pas dépasser 100 caractères'),

    lieu: z
      .string()
      .max(100, 'Le lieu ne peut pas dépasser 100 caractères')
      .optional()
      .or(z.literal('')),

    message: z
      .string()
      .max(1000, 'Le message ne peut pas dépasser 1000 caractères')
      .optional()
      .or(z.literal('')),

    // --- Champs UGC (optionnels au niveau objet ; requis conditionnellement via refines) ---
    type_compensation: z.string().optional(),
    nom_produit: z
      .string()
      .optional()
      .or(z.literal('')),
    valeur_produit: z.union([z.number(), z.nan(), z.literal(''), z.undefined()]),
    nombre_videos: z.union([z.number(), z.nan(), z.literal(''), z.undefined()]),
    montant_remuneration: z.union([z.number(), z.nan(), z.literal(''), z.undefined()]),
  })
  .refine(
    (data) => {
      if (!data.date_debut) return true
      const today = new Date()
      today.setHours(0, 0, 0, 0)
      const dateDebut = new Date(data.date_debut + 'T00:00:00')
      return dateDebut > today
    },
    {
      message: 'La date de début doit être dans le futur',
      path: ['date_debut'],
    },
  )
  .refine(
    (data) => {
      if (!data.date_debut || !data.date_fin) return true
      const dateDebut = new Date(data.date_debut + 'T00:00:00')
      const dateFin = new Date(data.date_fin + 'T00:00:00')
      return dateFin >= dateDebut
    },
    {
      message: 'La date de fin doit être le jour même ou après la date de début',
      path: ['date_fin'],
    },
  )
  .refine(
    (data) => {
      if (!data.date_debut || !data.date_fin) return true
      const dateDebut = new Date(data.date_debut + 'T00:00:00')
      const dateFin = new Date(data.date_fin + 'T00:00:00')
      const millisecondsPerDay = 24 * 60 * 60 * 1000
      const inclusiveDays = Math.floor((dateFin.getTime() - dateDebut.getTime()) / millisecondsPerDay) + 1
      return data.duree_heures <= inclusiveDays * 8
    },
    {
      message: 'La durée sélectionnée dépasse le maximum autorisé pour cette plage de dates',
      path: ['duree_heures'],
    },
  )
  // --- Refines UGC (gardés par type_contenu === 'UGC') ---
  .refine(
    (d) => d.type_contenu !== 'UGC' || d.type_compensation === 'product' || d.type_compensation === 'hybrid',
    {
      message: 'Le type de compensation est obligatoire',
      path: ['type_compensation'],
    },
  )
  .refine(
    (d) => d.type_contenu !== 'UGC' || (typeof d.nom_produit === 'string' && d.nom_produit.trim().length > 0),
    {
      message: 'Le nom du produit est obligatoire',
      path: ['nom_produit'],
    },
  )
  .refine(
    (d) => d.type_contenu !== 'UGC' || typeof d.nom_produit !== 'string' || d.nom_produit.length <= 255,
    {
      message: 'Le nom du produit ne peut pas dépasser 255 caractères',
      path: ['nom_produit'],
    },
  )
  .refine(
    (d) =>
      d.type_contenu !== 'UGC' ||
      (isFilledNumber(d.valeur_produit) && Number.isInteger(d.valeur_produit) && d.valeur_produit >= 1),
    {
      message: 'La valeur du produit doit être un entier supérieur ou égal à 1',
      path: ['valeur_produit'],
    },
  )
  .refine(
    (d) =>
      d.type_contenu !== 'UGC' ||
      d.type_compensation !== 'hybrid' ||
      (isFilledNumber(d.nombre_videos) &&
        Number.isInteger(d.nombre_videos) &&
        d.nombre_videos >= 1 &&
        d.nombre_videos <= 20),
    {
      message: 'Le nombre de vidéos doit être un entier entre 1 et 20',
      path: ['nombre_videos'],
    },
  )
  .refine(
    (d) =>
      d.type_contenu !== 'UGC' ||
      d.type_compensation !== 'hybrid' ||
      (isFilledNumber(d.montant_remuneration) &&
        Number.isInteger(d.montant_remuneration) &&
        d.montant_remuneration >= 1),
    {
      message: 'Le montant de la rémunération doit être un entier supérieur ou égal à 1',
      path: ['montant_remuneration'],
    },
  )
  // --- Refines « champs de tournage requis si NON-UGC » (l'UGC n'a ni date ni lieu) ---
  .refine(
    (d) => d.type_contenu === 'UGC' || (typeof d.date_debut === 'string' && d.date_debut.length > 0),
    {
      message: 'La date de début est obligatoire',
      path: ['date_debut'],
    },
  )
  .refine(
    (d) => d.type_contenu === 'UGC' || (typeof d.date_fin === 'string' && d.date_fin.length > 0),
    {
      message: 'La date de fin est obligatoire',
      path: ['date_fin'],
    },
  )
  .refine(
    (d) => d.type_contenu === 'UGC' || (typeof d.lieu === 'string' && d.lieu.length > 0),
    {
      message: 'Le lieu de tournage est obligatoire',
      path: ['lieu'],
    },
  )
