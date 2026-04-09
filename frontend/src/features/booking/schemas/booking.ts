import { z } from 'zod'

/**
 * Booking form validation schema
 * Mirrors backend validation rules in CreateBookingRequest
 */
export const bookingSchema = z
  .object({
    face_id: z.string({ message: 'La Face est obligatoire' }).uuid(),

    date_debut: z
      .string({ message: 'La date de début est obligatoire' })
      .min(1, 'La date de début est obligatoire'),

    date_fin: z
      .string({ message: 'La date de fin est obligatoire' })
      .min(1, 'La date de fin est obligatoire'),

    duree_heures: z
      .number({ message: 'La durée est obligatoire' })
      .int('La durée doit être un nombre entier')
      .min(4, 'La durée minimale est de 4 heures')
      .max(720, 'La durée ne peut pas dépasser 720 heures'),

    type_contenu: z
      .string({ message: 'Le type de contenu est obligatoire' })
      .min(1, 'Le type de contenu est obligatoire')
      .max(100, 'Le type de contenu ne peut pas dépasser 100 caractères'),

    message: z
      .string()
      .max(1000, 'Le message ne peut pas dépasser 1000 caractères')
      .optional()
      .or(z.literal('')),
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

export type BookingFormData = z.infer<typeof bookingSchema>
