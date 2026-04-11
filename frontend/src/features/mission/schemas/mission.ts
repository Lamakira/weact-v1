import { z } from 'zod'
import { MissionType, MissionGender } from '../types'

/**
 * Mission form validation schema
 * Mirrors backend validation rules in StoreMissionRequest
 */
export const missionSchema = z
  .object({
    titre: z
      .string({ message: 'Le titre est obligatoire' })
      .min(1, 'Le titre est obligatoire')
      .max(150, 'Le titre ne peut pas dépasser 150 caractères'),

    description: z
      .string({ message: 'La description est obligatoire' })
      .min(1, 'La description est obligatoire'),

    date_tournage: z
      .string({ message: 'La date de tournage est obligatoire' })
      .min(1, 'La date de tournage est obligatoire'),

    profil_recherche: z
      .string({ message: 'Le profil recherché est obligatoire' })
      .min(1, 'Le profil recherché est obligatoire'),

    budget: z
      .union([z.number(), z.nan(), z.undefined()])
      .refine((val) => val !== undefined && !Number.isNaN(val), {
        message: 'Le budget est obligatoire',
      })
      .refine((val) => val === undefined || Number.isNaN(val) || Number.isInteger(val), {
        message: 'Le budget doit être un nombre entier',
      })
      .refine((val) => val === undefined || Number.isNaN(val) || val >= 1, {
        message: 'Le budget doit être un nombre positif',
      }),

    date_limite_candidature: z
      .string({ message: 'La date limite de candidature est obligatoire' })
      .min(1, 'La date limite de candidature est obligatoire'),

    nombre_faces_voulu: z
      .number({ message: 'Le nombre de profils doit être un nombre' })
      .int('Le nombre de profils doit être un nombre entier')
      .min(1, 'Le nombre de profils doit être au moins 1')
      .optional(),

    type_mission: z.enum(
      [
        MissionType.PUBLICITE,
        MissionType.FILM,
        MissionType.COURT_METRAGE,
        MissionType.CLIP_MUSICAL,
        MissionType.SHOOTING_PHOTO,
        MissionType.AUTRE,
      ],
      { message: 'Le type de mission est obligatoire' },
    ),

    type_mission_autre: z.string().max(200, 'Le type de mission ne peut pas dépasser 200 caractères').optional().or(z.literal('')),

    genre_voulu: z.enum(
      [MissionGender.HOMME, MissionGender.FEMME, MissionGender.TOUS],
      { message: 'Le genre voulu est obligatoire' },
    ),

    lieu: z
      .string({ message: 'Le lieu est obligatoire' })
      .min(1, 'Le lieu est obligatoire')
      .max(150, 'Le lieu ne peut pas dépasser 150 caractères'),

    duree: z
      .string({ message: 'La durée est obligatoire' })
      .min(1, 'La durée est obligatoire')
      .max(100, 'La durée ne peut pas dépasser 100 caractères'),
  })
  .refine(
    (data) => {
      // Skip validation if date is empty (required check handles that)
      if (!data.date_limite_candidature) return true
      const today = new Date()
      today.setHours(0, 0, 0, 0)
      // Parse date explicitly to avoid timezone issues with YYYY-MM-DD format
      const dateLimite = new Date(data.date_limite_candidature + 'T00:00:00')
      return dateLimite > today
    },
    {
      message: 'La date limite de candidature doit être postérieure à aujourd\'hui',
      path: ['date_limite_candidature'],
    },
  )
  .refine(
    (data) => {
      // Skip validation if either date is empty (required checks handle that)
      if (!data.date_tournage || !data.date_limite_candidature) return true
      // Parse dates explicitly to avoid timezone issues
      const dateTournage = new Date(data.date_tournage + 'T00:00:00')
      const dateLimite = new Date(data.date_limite_candidature + 'T00:00:00')
      return dateTournage > dateLimite
    },
    {
      message: 'La date de tournage doit être postérieure à la date limite de candidature',
      path: ['date_tournage'],
    },
  )
  .refine(
    (data) => data.type_mission !== 'autre' || (data.type_mission_autre && data.type_mission_autre.trim().length > 0),
    {
      message: 'Veuillez préciser le type de mission',
      path: ['type_mission_autre'],
    },
  )
