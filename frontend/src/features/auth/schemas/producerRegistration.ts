import { z } from 'zod'

/**
 * Base schema for common fields
 */
const baseSchema = {
  email: z
    .string({ message: "L'email est obligatoire" })
    .min(1, "L'email est obligatoire")
    .email("L'email doit être une adresse email valide"),

  password: z
    .string({ message: 'Le mot de passe est obligatoire' })
    .min(8, 'Le mot de passe doit contenir au moins 8 caractères')
    .regex(/[A-Z]/, 'Le mot de passe doit contenir au moins une majuscule')
    .regex(/\d/, 'Le mot de passe doit contenir au moins un chiffre'),

  password_confirmation: z
    .string({ message: 'La confirmation du mot de passe est obligatoire' })
    .min(1, 'La confirmation du mot de passe est obligatoire'),
}

/**
 * Agency base object schema (without refine for discriminatedUnion)
 */
const agencyBaseSchema = z.object({
  type: z.literal('agency'),
  agency_name: z
    .string({ message: "Le nom de l'agence est obligatoire" })
    .min(1, "Le nom de l'agence est obligatoire")
    .max(255, "Le nom de l'agence ne peut pas dépasser 255 caractères"),
  ...baseSchema,
})

/**
 * Particulier base object schema (without refine for discriminatedUnion)
 */
const particulierBaseSchema = z.object({
  type: z.literal('particulier'),
  first_name: z
    .string({ message: 'Le prénom est obligatoire' })
    .min(1, 'Le prénom est obligatoire')
    .max(255, 'Le prénom ne peut pas dépasser 255 caractères'),
  last_name: z
    .string({ message: 'Le nom est obligatoire' })
    .min(1, 'Le nom est obligatoire')
    .max(255, 'Le nom ne peut pas dépasser 255 caractères'),
  ...baseSchema,
})

/**
 * Agency schema with password confirmation validation
 */
export const agencySchema = agencyBaseSchema.refine(
  (data) => data.password === data.password_confirmation,
  {
    message: 'La confirmation du mot de passe ne correspond pas',
    path: ['password_confirmation'],
  },
)

/**
 * Particulier schema with password confirmation validation
 */
export const particulierSchema = particulierBaseSchema.refine(
  (data) => data.password === data.password_confirmation,
  {
    message: 'La confirmation du mot de passe ne correspond pas',
    path: ['password_confirmation'],
  },
)

/**
 * Discriminated union schema for Producer registration
 * Uses 'type' field to determine which schema to use
 * Note: Uses base schemas (without refine) as discriminatedUnion requires ZodObject
 */
export const producerRegistrationSchema = z.discriminatedUnion('type', [
  agencyBaseSchema,
  particulierBaseSchema,
])

